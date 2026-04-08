package com.theke_solutions.koha_export;

import com.turning_leaf_technologies.indexing.IlsExtractLogEntry;
import com.turning_leaf_technologies.net.WebServiceResponse;
import org.apache.logging.log4j.Logger;
import org.json.JSONArray;
import org.json.JSONObject;

import java.sql.*;

/**
 * Syncs patron categories from Koha REST API into Aspen's ptype table.
 *
 * Equivalent to the existing updatePatronTypes() which reads from
 * Koha's categories table and suggestionPatronCategoryExceptions syspref.
 *
 * NOTE: The suggestionPatronCategoryExceptions syspref has no API endpoint.
 * Purchase suggestion permission sync is skipped until a syspref endpoint
 * is available.
 */
public class PatronCategorySync {

	public static void syncPatronCategories(KohaApiClient api, Connection dbConn, long accountProfileId,
	                                         Logger logger, IlsExtractLogEntry logEntry) {
		WebServiceResponse response = api.get("/api/v1/patron_categories?_per_page=-1");
		if (!response.isSuccess()) {
			logEntry.incErrors("Failed to fetch patron categories: " + response.getMessage());
			return;
		}

		JSONArray categories = response.getJSONResponseAsArray();
		if (categories == null) {
			logEntry.incErrors("Invalid JSON from /patron_categories");
			return;
		}

		try {
			PreparedStatement existingStmt = dbConn.prepareStatement(
					"SELECT id FROM ptype WHERE pType = ? AND accountProfileId = ?");
			PreparedStatement insertStmt = dbConn.prepareStatement(
					"INSERT INTO ptype (pType, accountProfileId) VALUES (?, ?)");

			int added = 0;
			for (int i = 0; i < categories.length(); i++) {
				JSONObject cat = categories.getJSONObject(i);
				String categoryCode = cat.getString("patron_category_id");

				existingStmt.setString(1, categoryCode);
				existingStmt.setLong(2, accountProfileId);
				ResultSet rs = existingStmt.executeQuery();
				if (!rs.next()) {
					insertStmt.setString(1, categoryCode);
					insertStmt.setLong(2, accountProfileId);
					insertStmt.executeUpdate();
					added++;
				}
				rs.close();
			}

			logEntry.addNote("Synced patron categories: " + categories.length() + " total, " + added + " new");
		} catch (SQLException e) {
			logEntry.incErrors("Error syncing patron categories: " + e.getMessage());
			logger.error("Error syncing patron categories", e);
		}
	}
}
