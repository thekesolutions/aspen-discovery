package com.theke_solutions.koha_export;

import com.turning_leaf_technologies.net.NetworkUtils;
import com.turning_leaf_technologies.net.WebServiceResponse;
import org.apache.logging.log4j.Logger;
import org.json.JSONObject;

import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;

/**
 * Koha REST API client with OAuth2 client credentials flow.
 * Mirrors the PHP KohaApiUserAgent implementation.
 */
public class KohaApiClient {
	private final String baseUrl;
	private final String clientId;
	private final String clientSecret;
	private final Logger logger;

	private String accessToken;
	private long tokenExpiresAt; // epoch millis

	public KohaApiClient(String baseUrl, String clientId, String clientSecret, Logger logger) {
		this.baseUrl = baseUrl.replaceAll("/+$", "");
		this.clientId = clientId;
		this.clientSecret = clientSecret;
		this.logger = logger;
	}

	/**
	 * Obtain or refresh the OAuth2 access token using client_credentials grant.
	 */
	private synchronized boolean ensureToken() {
		if (accessToken != null && System.currentTimeMillis() < tokenExpiresAt - 30_000) {
			return true;
		}
		String tokenUrl = baseUrl + "/api/v1/oauth/token";
		String postData = "grant_type=client_credentials"
				+ "&client_id=" + URLEncoder.encode(clientId, StandardCharsets.UTF_8)
				+ "&client_secret=" + URLEncoder.encode(clientSecret, StandardCharsets.UTF_8);

		// Use the full 11-param overload — the 10-param one has a bug that drops headers
		WebServiceResponse response = NetworkUtils.postToURL(
				tokenUrl, postData, "application/x-www-form-urlencoded",
				null, logger, null, 10000, 30000, null, null, true
		);

		if (!response.isSuccess()) {
			logger.error("OAuth2 token request failed: " + response.getResponseCode() + " " + response.getMessage());
			accessToken = null;
			return false;
		}

		try {
			JSONObject json = response.getJSONResponse();
			accessToken = json.getString("access_token");
			int expiresIn = json.optInt("expires_in", 3600);
			tokenExpiresAt = System.currentTimeMillis() + (expiresIn * 1000L);
			logger.info("OAuth2 token acquired, expires in " + expiresIn + "s");
			return true;
		} catch (Exception e) {
			logger.error("Failed to parse OAuth2 token response", e);
			accessToken = null;
			return false;
		}
	}

	private HashMap<String, String> authHeaders() {
		HashMap<String, String> headers = new HashMap<>();
		headers.put("Authorization", "Bearer " + accessToken);
		headers.put("Accept", "application/json");
		return headers;
	}

	public WebServiceResponse get(String path) {
		return get(path, null);
	}

	public WebServiceResponse get(String path, HashMap<String, String> extraHeaders) {
		if (!ensureToken()) {
			return new WebServiceResponse(false, 401, "Unable to authenticate with Koha API");
		}
		HashMap<String, String> headers = authHeaders();
		if (extraHeaders != null) {
			headers.putAll(extraHeaders);
		}
		return NetworkUtils.getURL(baseUrl + path, logger, headers, 60000, true);
	}

	/**
	 * GET expecting MARC-XML response.
	 */
	public WebServiceResponse getMarc(String path) {
		HashMap<String, String> extra = new HashMap<>();
		extra.put("Accept", "application/marcxml+xml");
		return get(path, extra);
	}

	public WebServiceResponse post(String path, String body, String contentType) {
		if (!ensureToken()) {
			return new WebServiceResponse(false, 401, "Unable to authenticate with Koha API");
		}
		HashMap<String, String> headers = authHeaders();
		return NetworkUtils.postToURL(
				baseUrl + path, body, contentType, null, logger,
				null, 10000, 60000, null, headers, true
		);
	}

	public boolean isAuthenticated() {
		return ensureToken();
	}

	public String getBaseUrl() {
		return baseUrl;
	}
}
