package com.turning_leaf_technologies.storage;

import com.turning_leaf_technologies.encryption.EncryptionUtils;
import org.apache.logging.log4j.Logger;
import org.ini4j.Ini;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.HashMap;
import java.util.Map;
import java.util.StringJoiner;
import java.util.UUID;

/**
 * Stores files through Aspen's storage API, so the storage registry is only implemented in PHP.
 * <p>
 * The client authenticates as the active OAuth2 service client with the storage:write scope, which
 * a database update creates; its secret is read from the database and decrypted. Requests go to
 * [Site] internalUrl when it is set (e.g. http://apache inside a Docker stack) and to [Site] url
 * otherwise, as they are: the address must reach this site's virtual host by itself.
 * <p>
 * Responses are handled the same way for every call:
 * - a redirect stops the client: a POST would lose its body and the token would be sent to
 *   another host, so the configured address is wrong
 * - 401 renews the token once and sends the request again; a second 401 stops the client
 * - 403 stops the client, the client is missing the storage:write scope
 * - 429 waits for Retry-After and sends the request again, it is not a failure
 * - network errors and 5xx are retried after a delay; after several calls in a row fail anyway
 *   the client stops
 * - other 4xx are errors of that request only (e.g. an invalid image) and are returned
 * Once stopped, callers find out with isAvailable() and leave the remaining work for the next run,
 * which finds it still pending.
 */
public class StorageClient {
	private static final int[] RETRY_DELAYS_MILLIS = {1000, 5000, 30000};
	private static final int MAX_CONSECUTIVE_FAILURES = 5;
	private static final int MAX_RATE_LIMIT_WAIT_SECONDS = 3600;
	// Renew the token a little before it expires, so a request never carries an expired one
	private static final long TOKEN_RENEWAL_MARGIN_MILLIS = 60_000;

	private final String baseUrl;
	private final Logger logger;
	private String clientId;
	private String clientSecret;
	private String accessToken = null;
	private long accessTokenExpiresAtMillis = 0;
	private int consecutiveFailures = 0;
	private String stopReason = null;

	public StorageClient(Ini configIni, String serverName, Connection dbConn, Logger logger) {
		this.logger = logger;
		String internalUrl = trimTrailingSlash(configIni.get("Site", "internalUrl"));
		this.baseUrl = (internalUrl == null || internalUrl.isEmpty()) ? trimTrailingSlash(configIni.get("Site", "url")) : internalUrl;
		if (baseUrl == null || baseUrl.isEmpty()) {
			stopReason = "[Site] url is not set";
			return;
		}
		loadClientCredentials(serverName, dbConn);
	}

	private static String trimTrailingSlash(String url) {
		if (url == null) {
			return null;
		}
		url = url.trim();
		while (url.endsWith("/")) {
			url = url.substring(0, url.length() - 1);
		}
		return url;
	}

	private void loadClientCredentials(String serverName, Connection dbConn) {
		try (PreparedStatement statement = dbConn.prepareStatement("SELECT client_id, client_secret FROM oauth2_clients WHERE client_type = 'service_application' AND is_active = 1 AND FIND_IN_SET('storage:write', scopes) > 0 ORDER BY id");
			 ResultSet resultSet = statement.executeQuery()) {
			if (!resultSet.next()) {
				stopReason = "There is no active OAuth2 service client with the storage:write scope, run the database updates or create one";
				return;
			}
			clientId = resultSet.getString("client_id");
			clientSecret = EncryptionUtils.decryptString(resultSet.getString("client_secret"), serverName, null);
			if (resultSet.next()) {
				logger.warn("Several active OAuth2 service clients have the storage:write scope, using " + clientId);
			}
		} catch (Exception e) {
			stopReason = "Could not load the storage API OAuth2 client: " + e;
		}
	}

	/**
	 * False once calls stopped for this run; getStopReason() says why.
	 */
	public boolean isAvailable() {
		return stopReason == null;
	}

	public String getStopReason() {
		return stopReason;
	}

	/**
	 * When the uploaded cover of each record of the type was stored (seconds since epoch), or
	 * null if the list could not be loaded.
	 */
	public Map<String, Long> listCoverOriginals(String recordType) {
		StorageResponse response = call("listCoverOriginals", Map.of("recordType", recordType), null, null, null);
		if (!response.success) {
			return null;
		}
		HashMap<String, Long> originals = new HashMap<>();
		JSONObject originalsJson = response.result.getJSONObject("originals");
		for (String recordId : originalsJson.keySet()) {
			originals.put(recordId, originalsJson.getLong(recordId));
		}
		return originals;
	}

	/**
	 * Stores an image as the uploaded cover of the record and resets the cover of its grouped work.
	 */
	public StorageResponse storeCoverOriginal(String recordType, String recordId, String groupedWorkId, byte[] image) {
		HashMap<String, String> fields = new HashMap<>();
		fields.put("recordType", recordType);
		fields.put("recordId", recordId);
		if (groupedWorkId != null) {
			fields.put("groupedWorkId", groupedWorkId);
		}
		return call("storeCoverOriginal", null, fields, "cover", image);
	}

	/**
	 * Sends one API call, renewing the token and waiting or retrying as described on the class.
	 *
	 * @param queryFields  sent in the URL of a GET when there are no form fields
	 * @param formFields   sent as multipart form data of a POST, with the file if there is one
	 */
	private StorageResponse call(String method, Map<String, String> queryFields, Map<String, String> formFields, String fileField, byte[] fileContents) {
		if (stopReason != null) {
			return StorageResponse.failure(0, stopReason);
		}
		StringJoiner query = new StringJoiner("&");
		query.add("method=" + URLEncoder.encode(method, StandardCharsets.UTF_8));
		if (queryFields != null) {
			queryFields.forEach((name, value) -> query.add(URLEncoder.encode(name, StandardCharsets.UTF_8) + "=" + URLEncoder.encode(value, StandardCharsets.UTF_8)));
		}
		String url = baseUrl + "/API/StorageAPI?" + query;

		boolean tokenRenewed = false;
		int attempt = 0;
		while (true) {
			if (!ensureAccessToken()) {
				return StorageResponse.failure(0, stopReason);
			}
			StorageResponse response = send(url, formFields, fileField, fileContents, "Bearer " + accessToken);
			if (response.success) {
				consecutiveFailures = 0;
				return response;
			}
			if (response.isRedirect()) {
				return stop(response, "Storage API " + method + " was redirected to " + response.location + ", check [Site] url and [Site] internalUrl");
			}
			if (response.responseCode == 401) {
				if (tokenRenewed) {
					return stop(response, "Storage API " + method + " rejected a new token: " + response.message);
				}
				accessToken = null;
				tokenRenewed = true;
				continue;
			}
			if (response.responseCode == 403) {
				return stop(response, "Storage API " + method + " is not allowed for OAuth2 client " + clientId + ", it needs the storage:write scope: " + response.message);
			}
			if (response.responseCode == 429) {
				waitSeconds(response.retryAfterSeconds);
				continue;
			}
			if (!response.isRetryable()) {
				// An error of this request only, e.g. an invalid image
				return response;
			}
			if (attempt < RETRY_DELAYS_MILLIS.length) {
				logger.warn("Storage API " + method + " failed (" + response.message + "), retrying");
				waitMillis(RETRY_DELAYS_MILLIS[attempt++]);
				continue;
			}
			if (++consecutiveFailures >= MAX_CONSECUTIVE_FAILURES) {
				return stop(response, "Storage API failed " + consecutiveFailures + " times in a row, last error: " + response.message);
			}
			return response;
		}
	}

	private StorageResponse stop(StorageResponse response, String reason) {
		stopReason = reason;
		return StorageResponse.failure(response.responseCode, reason);
	}

	/**
	 * Gets a token with the client credentials grant when there is none or it is about to expire.
	 */
	private boolean ensureAccessToken() {
		if (stopReason != null) {
			return false;
		}
		if (accessToken != null && System.currentTimeMillis() < accessTokenExpiresAtMillis) {
			return true;
		}
		HashMap<String, String> fields = new HashMap<>();
		fields.put("grant_type", "client_credentials");
		fields.put("client_id", clientId);
		fields.put("client_secret", clientSecret);
		fields.put("scope", "storage:write");
		int attempt = 0;
		while (true) {
			StorageResponse response = send(baseUrl + "/Authentication/OAuth2_Token", fields, null, null, null);
			if (response.responseCode == 200 && response.json != null && response.json.has("access_token")) {
				accessToken = response.json.getString("access_token");
				long expiresInMillis = response.json.optLong("expires_in", 3600) * 1000;
				accessTokenExpiresAtMillis = System.currentTimeMillis() + Math.max(expiresInMillis - TOKEN_RENEWAL_MARGIN_MILLIS, 0);
				return true;
			}
			if (response.isRedirect()) {
				stopReason = "The token request was redirected to " + response.location + ", check [Site] url and [Site] internalUrl";
				return false;
			}
			if (response.responseCode == 429) {
				waitSeconds(response.retryAfterSeconds);
				continue;
			}
			if (response.isRetryable() && attempt < RETRY_DELAYS_MILLIS.length) {
				waitMillis(RETRY_DELAYS_MILLIS[attempt++]);
				continue;
			}
			// A rejected client (wrong or rotated secret, inactive client) fails the same way every time
			stopReason = "Could not get a storage API token for OAuth2 client " + clientId + ": " + response.message;
			return false;
		}
	}

	private void waitSeconds(int seconds) {
		waitMillis(Math.min(Math.max(seconds, 1), MAX_RATE_LIMIT_WAIT_SECONDS) * 1000L);
	}

	private void waitMillis(long millis) {
		try {
			Thread.sleep(millis);
		} catch (InterruptedException e) {
			Thread.currentThread().interrupt();
			stopReason = "Interrupted";
		}
	}

	/**
	 * Sends one request without following redirects. With form fields it is a POST, multipart when
	 * there is a file and url encoded otherwise; without them it is a GET.
	 */
	private StorageResponse send(String url, Map<String, String> formFields, String fileField, byte[] fileContents, String authorization) {
		HttpURLConnection connection = null;
		try {
			connection = (HttpURLConnection) URI.create(url).toURL().openConnection();
			connection.setConnectTimeout(10000);
			connection.setReadTimeout(120000);
			connection.setInstanceFollowRedirects(false);
			connection.setRequestProperty("User-Agent", "Aspen Storage Client");
			connection.setRequestProperty("Accept", "application/json");
			if (authorization != null) {
				connection.setRequestProperty("Authorization", authorization);
			}
			if (formFields != null) {
				connection.setRequestMethod("POST");
				connection.setDoOutput(true);
				if (fileField != null) {
					writeMultipart(connection, formFields, fileField, fileContents);
				} else {
					writeUrlEncoded(connection, formFields);
				}
			} else {
				connection.setRequestMethod("GET");
			}

			int responseCode = connection.getResponseCode();
			if (responseCode >= 300 && responseCode < 400) {
				return new StorageResponse(false, responseCode, "HTTP " + responseCode, null, null, connection.getHeaderField("Location"), 0);
			}
			InputStream responseStream = responseCode < 400 ? connection.getInputStream() : connection.getErrorStream();
			String responseBody = responseStream == null ? "" : readAll(responseStream);
			JSONObject json = null;
			try {
				json = new JSONObject(responseBody);
			} catch (JSONException e) {
				// Not an Aspen response, e.g. an error page from a proxy
			}
			// Methods answer {"result": {...}}, OAuth2 errors {"error": ..., "error_description": ...}
			JSONObject result = json == null ? null : json.optJSONObject("result");
			boolean success = responseCode == 200 && result != null && result.optBoolean("success", false);
			String message;
			if (result != null && result.has("message")) {
				message = result.optString("message");
			} else if (json != null && json.has("error")) {
				message = json.optString("error") + (json.has("error_description") ? ": " + json.optString("error_description") : "");
			} else {
				message = "HTTP " + responseCode;
			}
			int retryAfterSeconds = parseRetryAfter(connection.getHeaderField("Retry-After"), json);
			return new StorageResponse(success, responseCode, message, json, result, null, retryAfterSeconds);
		} catch (IOException e) {
			return StorageResponse.failure(0, e.toString());
		} finally {
			if (connection != null) {
				connection.disconnect();
			}
		}
	}

	private static int parseRetryAfter(String header, JSONObject json) {
		if (header != null) {
			try {
				return Integer.parseInt(header.trim());
			} catch (NumberFormatException e) {
				// Only the number of seconds is sent by Aspen
			}
		}
		return json == null ? 60 : json.optInt("retry_after", 60);
	}

	private static void writeUrlEncoded(HttpURLConnection connection, Map<String, String> fields) throws IOException {
		StringJoiner body = new StringJoiner("&");
		fields.forEach((name, value) -> body.add(URLEncoder.encode(name, StandardCharsets.UTF_8) + "=" + URLEncoder.encode(value, StandardCharsets.UTF_8)));
		connection.setRequestProperty("Content-Type", "application/x-www-form-urlencoded");
		try (OutputStream output = connection.getOutputStream()) {
			output.write(body.toString().getBytes(StandardCharsets.UTF_8));
		}
	}

	private static void writeMultipart(HttpURLConnection connection, Map<String, String> fields, String fileField, byte[] fileContents) throws IOException {
		String boundary = "----AspenStorage" + UUID.randomUUID().toString().replace("-", "");
		connection.setRequestProperty("Content-Type", "multipart/form-data; boundary=" + boundary);
		try (OutputStream body = connection.getOutputStream()) {
			for (Map.Entry<String, String> field : fields.entrySet()) {
				body.write(("--" + boundary + "\r\nContent-Disposition: form-data; name=\"" + field.getKey() + "\"\r\n\r\n" + field.getValue() + "\r\n").getBytes(StandardCharsets.UTF_8));
			}
			body.write(("--" + boundary + "\r\nContent-Disposition: form-data; name=\"" + fileField + "\"; filename=\"" + fileField + "\"\r\nContent-Type: application/octet-stream\r\n\r\n").getBytes(StandardCharsets.UTF_8));
			body.write(fileContents);
			body.write("\r\n".getBytes(StandardCharsets.UTF_8));
			body.write(("--" + boundary + "--\r\n").getBytes(StandardCharsets.UTF_8));
		}
	}

	private static String readAll(InputStream inputStream) throws IOException {
		try (inputStream) {
			ByteArrayOutputStream contents = new ByteArrayOutputStream();
			inputStream.transferTo(contents);
			return contents.toString(StandardCharsets.UTF_8);
		}
	}

	public static class StorageResponse {
		public final boolean success;
		public final int responseCode;
		public final String message;
		final JSONObject json;
		final JSONObject result;
		final String location;
		final int retryAfterSeconds;

		StorageResponse(boolean success, int responseCode, String message, JSONObject json, JSONObject result, String location, int retryAfterSeconds) {
			this.success = success;
			this.responseCode = responseCode;
			this.message = message;
			this.json = json;
			this.result = result;
			this.location = location;
			this.retryAfterSeconds = retryAfterSeconds;
		}

		static StorageResponse failure(int responseCode, String message) {
			return new StorageResponse(false, responseCode, message, null, null, null, 0);
		}

		boolean isRedirect() {
			return responseCode >= 300 && responseCode < 400;
		}

		/**
		 * Network errors and server errors may go away; client errors (bad image, too large) will not.
		 */
		boolean isRetryable() {
			return !success && (responseCode == 0 || responseCode >= 500);
		}
	}
}
