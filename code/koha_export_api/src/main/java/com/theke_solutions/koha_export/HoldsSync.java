package com.theke_solutions.koha_export;

import com.turning_leaf_technologies.indexing.IlsExtractLogEntry;
import com.turning_leaf_technologies.net.WebServiceResponse;
import org.apache.logging.log4j.Logger;
import org.json.JSONArray;
import org.json.JSONObject;

import java.sql.*;
import java.util.HashMap;

/**
 * Syncs hold counts per biblio from Koha REST API into Aspen's
 * ils_hold_summary table.
 *
 * Equivalent to exportHolds() which does:
 *   SELECT count(*) as numHolds, biblionumber FROM reserves GROUP BY biblionumber
 *
 * The API equivalent fetches all holds and aggregates client-side.
 */
public class HoldsSync {

	public static void syncHolds(KohaApiClient api, Connection dbConn,
	                              Logger logger, IlsExtractLogEntry logEntry) {
		// Fetch all active holds, paginated
		HashMap<Integer, Integer> holdCounts = new HashMap<>();
		int page = 1;
		int perPage = 1000;

		while (true) {
			WebServiceResponse response = api.get(
					"/api/v1/holds?_per_page=" + perPage + "&_page=" + page);
			if (!response.isSuccess()) {
				logEntry.incErrors("Failed to fetch holds (page " + page + "): " + response.getMessage());
				return;
			}

			JSONArray holds = response.getJSONResponseAsArray();
			if (holds == null || holds.length() == 0) break;

			for (int i = 0; i < holds.length(); i++) {
				JSONObject hold = holds.getJSONObject(i);
				int biblioId = hold.optInt("biblio_id", 0);
				if (biblioId > 0) {
					holdCounts.merge(biblioId, 1, Integer::sum);
				}
			}

			if (holds.length() < perPage) break;
			page++;
		}

		// Write to ils_hold_summary
		try {
			Savepoint savepoint = dbConn.setSavepoint();
			try {
				dbConn.prepareStatement("TRUNCATE ils_hold_summary").executeUpdate();

				PreparedStatement insertStmt = dbConn.prepareStatement(
						"INSERT INTO ils_hold_summary (ilsId, numHolds) VALUES (?, ?)");
				for (var entry : holdCounts.entrySet()) {
					insertStmt.setString(1, String.valueOf(entry.getKey()));
					insertStmt.setInt(2, entry.getValue());
					insertStmt.executeUpdate();
				}

				dbConn.releaseSavepoint(savepoint);
				logEntry.addNote("Synced hold counts for " + holdCounts.size() + " biblios");
			} catch (SQLException e) {
				dbConn.rollback(savepoint);
				logEntry.incErrors("Error writing hold summary, rolled back: " + e.getMessage());
			}
		} catch (SQLException e) {
			logEntry.incErrors("Error syncing holds: " + e.getMessage());
			logger.error("Error syncing holds", e);
		}
	}
}
