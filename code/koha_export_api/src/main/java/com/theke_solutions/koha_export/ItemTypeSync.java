package com.theke_solutions.koha_export;

import com.turning_leaf_technologies.indexing.IlsExtractLogEntry;
import com.turning_leaf_technologies.net.WebServiceResponse;
import org.apache.logging.log4j.Logger;
import org.json.JSONArray;
import org.json.JSONObject;

import java.sql.*;

/**
 * Syncs item types from Koha REST API into Aspen's format_map_values
 * and itype translation map.
 *
 * Equivalent to the itemtype portion of updateTranslationMaps().
 */
public class ItemTypeSync {

	public static void syncItemTypes(KohaApiClient api, Connection dbConn, long indexingProfileId,
	                                  Logger logger, IlsExtractLogEntry logEntry) {
		WebServiceResponse response = api.get("/api/v1/item_types?_per_page=-1");
		if (!response.isSuccess()) {
			logEntry.incErrors("Failed to fetch item types: " + response.getMessage());
			return;
		}

		JSONArray itemTypes = response.getJSONResponseAsArray();
		if (itemTypes == null) {
			logEntry.incErrors("Invalid JSON from /item_types");
			return;
		}

		try {
			// format_map_values
			PreparedStatement insertFormatStmt = dbConn.prepareStatement(
					"INSERT IGNORE INTO format_map_values (indexingProfileId, value, format, formatCategory, formatBoost, appliesToItemType) " +
					"VALUES (?, ?, '', 'Other', 1, 1)");

			// itype translation map
			long itypeMapId = getOrCreateTranslationMap(dbConn, "itype", indexingProfileId);
			PreparedStatement existingValueStmt = dbConn.prepareStatement(
					"SELECT id FROM translation_map_values WHERE translationMapId = ? AND LOWER(value) = ?");
			PreparedStatement insertValueStmt = dbConn.prepareStatement(
					"INSERT INTO translation_map_values (translationMapId, value, translation) VALUES (?, ?, ?)");

			int added = 0;
			for (int i = 0; i < itemTypes.length(); i++) {
				JSONObject it = itemTypes.getJSONObject(i);
				String code = it.getString("item_type_id");
				String description = it.optString("description", code);

				// format_map_values
				insertFormatStmt.setLong(1, indexingProfileId);
				insertFormatStmt.setString(2, code);
				insertFormatStmt.executeUpdate();

				// itype translation map
				existingValueStmt.setLong(1, itypeMapId);
				existingValueStmt.setString(2, code.toLowerCase());
				ResultSet rs = existingValueStmt.executeQuery();
				if (!rs.next()) {
					insertValueStmt.setLong(1, itypeMapId);
					insertValueStmt.setString(2, code);
					insertValueStmt.setString(3, description);
					insertValueStmt.executeUpdate();
					added++;
				}
				rs.close();
			}

			logEntry.addNote("Synced item types: " + itemTypes.length() + " total, " + added + " new translation map entries");
		} catch (SQLException e) {
			logEntry.incErrors("Error syncing item types: " + e.getMessage());
			logger.error("Error syncing item types", e);
		}
	}

	static long getOrCreateTranslationMap(Connection dbConn, String mapName, long indexingProfileId) throws SQLException {
		PreparedStatement getStmt = dbConn.prepareStatement(
				"SELECT id FROM translation_maps WHERE name = ? AND indexingProfileId = ?");
		getStmt.setString(1, mapName);
		getStmt.setLong(2, indexingProfileId);
		ResultSet rs = getStmt.executeQuery();
		if (rs.next()) {
			long id = rs.getLong("id");
			rs.close();
			return id;
		}
		rs.close();

		PreparedStatement insertStmt = dbConn.prepareStatement(
				"INSERT INTO translation_maps (name, indexingProfileId) VALUES (?, ?)",
				Statement.RETURN_GENERATED_KEYS);
		insertStmt.setString(1, mapName);
		insertStmt.setLong(2, indexingProfileId);
		insertStmt.executeUpdate();
		ResultSet keys = insertStmt.getGeneratedKeys();
		keys.next();
		return keys.getLong(1);
	}
}
