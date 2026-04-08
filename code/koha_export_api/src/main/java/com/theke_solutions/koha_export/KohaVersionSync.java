package com.theke_solutions.koha_export;

import com.turning_leaf_technologies.net.WebServiceResponse;
import org.apache.logging.log4j.Logger;
import org.json.JSONArray;
import org.json.JSONObject;

import java.sql.*;

/**
 * Syncs Koha version info via GET /status/version.
 */
public class KohaVersionSync {

	/**
	 * Returns the Koha version as a float (e.g. 24.05) for version-gated logic.
	 */
	public static float getKohaVersion(KohaApiClient api, Logger logger) {
		WebServiceResponse response = api.get("/api/v1/status/version");
		if (!response.isSuccess()) {
			logger.error("Failed to get Koha version: " + response.getMessage());
			return 0;
		}
		JSONObject json = response.getJSONResponse();
		if (json == null) {
			logger.error("Invalid JSON from /status/version");
			return 0;
		}
		String release = json.optString("release", "0.0");
		try {
			return Float.parseFloat(release);
		} catch (NumberFormatException e) {
			logger.error("Could not parse Koha release version: " + release);
			return 0;
		}
	}
}
