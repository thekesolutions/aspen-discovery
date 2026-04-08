package com.theke_solutions.koha_export;

import com.turning_leaf_technologies.indexing.IlsExtractLogEntry;
import com.turning_leaf_technologies.indexing.IndexingProfile;
import com.turning_leaf_technologies.net.WebServiceResponse;
import org.apache.logging.log4j.Logger;
import org.json.JSONArray;
import org.json.JSONObject;

import java.sql.*;

/**
 * Syncs translation maps from Koha REST API:
 * - location map from GET /libraries
 * - sub_location, shelf_location, collection maps from GET /authorised_value_categories/{cat}/authorised_values
 *
 * Equivalent to updateTranslationMaps() minus the item type portion
 * (handled by ItemTypeSync).
 */
public class AuthorisedValueSync {

	public static void syncTranslationMaps(KohaApiClient api, Connection dbConn, long indexingProfileId,
	                                        IndexingProfile profile, Logger logger, IlsExtractLogEntry logEntry) {
		try {
			// 1. Location map from libraries
			syncLocationMap(api, dbConn, indexingProfileId, logger, logEntry);

			// 2. Sub-location, shelf location, collection from authorised values
			syncAuthorisedValueMap(api, dbConn, indexingProfileId, profile.getSubLocationSubfield(), "sub_location", logger, logEntry);
			syncAuthorisedValueMap(api, dbConn, indexingProfileId, profile.getShelvingLocationSubfield(), "shelf_location", logger, logEntry);
			syncAuthorisedValueMap(api, dbConn, indexingProfileId, profile.getCollectionSubfield(), "collection", logger, logEntry);
		} catch (SQLException e) {
			logEntry.incErrors("Error syncing translation maps: " + e.getMessage());
			logger.error("Error syncing translation maps", e);
		}
	}

	private static void syncLocationMap(KohaApiClient api, Connection dbConn, long indexingProfileId,
	                                     Logger logger, IlsExtractLogEntry logEntry) throws SQLException {
		WebServiceResponse response = api.get("/api/v1/libraries?_per_page=-1");
		if (!response.isSuccess()) {
			logEntry.incErrors("Failed to fetch libraries for location map: " + response.getMessage());
			return;
		}

		JSONArray libraries = response.getJSONResponseAsArray();
		if (libraries == null) return;

		long mapId = ItemTypeSync.getOrCreateTranslationMap(dbConn, "location", indexingProfileId);
		PreparedStatement existingStmt = dbConn.prepareStatement(
				"SELECT id FROM translation_map_values WHERE translationMapId = ? AND LOWER(value) = ?");
		PreparedStatement insertStmt = dbConn.prepareStatement(
				"INSERT INTO translation_map_values (translationMapId, value, translation) VALUES (?, ?, ?)");

		int added = 0;
		for (int i = 0; i < libraries.length(); i++) {
			JSONObject lib = libraries.getJSONObject(i);
			String code = lib.getString("library_id");
			String name = lib.optString("name", code);

			existingStmt.setLong(1, mapId);
			existingStmt.setString(2, code.toLowerCase());
			ResultSet rs = existingStmt.executeQuery();
			if (!rs.next()) {
				insertStmt.setLong(1, mapId);
				insertStmt.setString(2, code);
				insertStmt.setString(3, name);
				insertStmt.executeUpdate();
				added++;
			}
			rs.close();
		}
		logEntry.addNote("Location translation map: " + libraries.length() + " libraries, " + added + " new");
	}

	private static void syncAuthorisedValueMap(KohaApiClient api, Connection dbConn, long indexingProfileId,
	                                            char subfield, String mapName,
	                                            Logger logger, IlsExtractLogEntry logEntry) throws SQLException {
		String category = getAuthorisedValueCategory(subfield);
		if (category == null) {
			return; // No known AV category for this subfield
		}

		WebServiceResponse response = api.get(
				"/api/v1/authorised_value_categories/" + category + "/authorised_values?_per_page=-1");
		if (!response.isSuccess()) {
			logEntry.addNote("No authorised values for category " + category + " (map: " + mapName + ")");
			return;
		}

		JSONArray values = response.getJSONResponseAsArray();
		if (values == null) return;

		long mapId = ItemTypeSync.getOrCreateTranslationMap(dbConn, mapName, indexingProfileId);
		PreparedStatement existingStmt = dbConn.prepareStatement(
				"SELECT id FROM translation_map_values WHERE translationMapId = ? AND LOWER(value) = ?");
		PreparedStatement insertStmt = dbConn.prepareStatement(
				"INSERT INTO translation_map_values (translationMapId, value, translation) VALUES (?, ?, ?)");

		int added = 0;
		for (int i = 0; i < values.length(); i++) {
			JSONObject av = values.getJSONObject(i);
			String code = av.getString("value");
			String description = av.optString("description", code);

			existingStmt.setLong(1, mapId);
			existingStmt.setString(2, code.toLowerCase());
			ResultSet rs = existingStmt.executeQuery();
			if (!rs.next()) {
				insertStmt.setLong(1, mapId);
				insertStmt.setString(2, code);
				insertStmt.setString(3, description);
				insertStmt.executeUpdate();
				added++;
			}
			rs.close();
		}
		logEntry.addNote(mapName + " translation map (" + category + "): " + values.length() + " values, " + added + " new");
	}

	/**
	 * Maps MARC 952 subfield codes to Koha authorised value categories.
	 * Matches the hardcoded mapping in the existing KohaExportMain.
	 */
	private static String getAuthorisedValueCategory(char subfield) {
		switch (subfield) {
			case '8': return "CCODE";
			case 'c': return "LOC";
			default: return null;
		}
	}
}
