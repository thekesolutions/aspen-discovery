package com.theke_solutions.koha_export;

import com.turning_leaf_technologies.indexing.IlsExtractLogEntry;
import com.turning_leaf_technologies.net.WebServiceResponse;
import org.apache.logging.log4j.Logger;
import org.json.JSONArray;
import org.json.JSONObject;

import java.sql.*;

/**
 * Syncs library/branch info from Koha REST API into Aspen's
 * library, location, location_hours, and records_to_include tables.
 *
 * Equivalent to the existing updateBranchInfo() which reads directly
 * from Koha's branches, library_hours, repeatable_holidays, and
 * special_holidays tables.
 *
 * NOTE: Holidays (repeatable_holidays, special_holidays) and library_groups
 * have no Koha API endpoint yet. Holiday sync is skipped until those
 * endpoints are contributed upstream.
 */
public class LibrarySync {

	public static void syncLibraries(KohaApiClient api, Connection dbConn, long indexingProfileId,
	                                  Logger logger, IlsExtractLogEntry logEntry) {
		WebServiceResponse response = api.get("/api/v1/libraries?_per_page=-1", buildEmbedHeaders());
		if (!response.isSuccess()) {
			logEntry.incErrors("Failed to fetch libraries: " + response.getMessage());
			return;
		}

		JSONArray libraries = response.getJSONResponseAsArray();
		if (libraries == null) {
			logEntry.incErrors("Invalid JSON from /libraries");
			return;
		}

		try {
			PreparedStatement existingLocationStmt = dbConn.prepareStatement(
					"SELECT locationId, libraryId, allowUpdatingContactInfoFromILS, allowUpdatingHoursFromILS " +
					"FROM location WHERE code = ?");
			PreparedStatement insertLibraryStmt = dbConn.prepareStatement(
					"INSERT INTO library (subdomain, displayName, browseCategoryGroupId, groupedWorkDisplaySettingId) " +
					"VALUES (?, ?, 1, 1)", Statement.RETURN_GENERATED_KEYS);
			PreparedStatement insertLocationStmt = dbConn.prepareStatement(
					"INSERT INTO location (libraryId, displayName, code, browseCategoryGroupId, groupedWorkDisplaySettingId, " +
					"address, phone, contactEmail) VALUES (?, ?, ?, -1, -1, ?, ?, ?)", Statement.RETURN_GENERATED_KEYS);
			PreparedStatement updateContactStmt = dbConn.prepareStatement(
					"UPDATE location SET address = ?, phone = ?, contactEmail = ? WHERE locationId = ?");
			PreparedStatement insertLocationRecordsOwnedStmt = dbConn.prepareStatement(
					"INSERT INTO location_records_to_include (locationId, indexingProfileId, location, subLocation, weight, markRecordsAsOwned) " +
					"VALUES (?, ?, ?, '', 1, 1)");
			PreparedStatement insertLocationRecordsToIncludeStmt = dbConn.prepareStatement(
					"INSERT INTO location_records_to_include (locationId, indexingProfileId, location, subLocation, weight) " +
					"VALUES (?, ?, '.*', '', 2)");
			PreparedStatement insertLibraryRecordsOwnedStmt = dbConn.prepareStatement(
					"INSERT INTO library_records_to_include (libraryId, indexingProfileId, location, subLocation, weight, markRecordsAsOwned) " +
					"VALUES (?, ?, ?, '', 1, 1) ON DUPLICATE KEY UPDATE location = CONCAT(location, '|', VALUES(location))");
			PreparedStatement insertLibraryRecordsToIncludeStmt = dbConn.prepareStatement(
					"INSERT INTO library_records_to_include (libraryId, indexingProfileId, location, subLocation, weight) " +
					"VALUES (?, ?, '.*', '', 2) ON DUPLICATE KEY UPDATE location = location");

			// Hours statements
			PreparedStatement existingHoursStmt = dbConn.prepareStatement(
					"SELECT id FROM location_hours WHERE locationId = ? AND day = ?");
			PreparedStatement insertHoursStmt = dbConn.prepareStatement(
					"INSERT INTO location_hours (locationId, day, open, close) VALUES (?, ?, ?, ?)");
			PreparedStatement updateHoursStmt = dbConn.prepareStatement(
					"UPDATE location_hours SET open = ?, close = ? WHERE locationId = ? AND day = ?");

			for (int i = 0; i < libraries.length(); i++) {
				JSONObject lib = libraries.getJSONObject(i);
				String branchcode = lib.getString("library_id");
				String branchname = lib.optString("name", branchcode);

				String address = buildAddress(lib);
				String phone = lib.optString("phone", null);
				String email = lib.optString("email", null);

				existingLocationStmt.setString(1, branchcode);
				ResultSet existingRS = existingLocationStmt.executeQuery();

				if (existingRS.next()) {
					// Existing location — update contact info if allowed
					long locationId = existingRS.getLong("locationId");
					boolean updateContact = existingRS.getBoolean("allowUpdatingContactInfoFromILS");
					boolean updateHours = existingRS.getBoolean("allowUpdatingHoursFromILS");

					if (updateContact) {
						updateContactStmt.setString(1, address);
						updateContactStmt.setString(2, phone);
						updateContactStmt.setString(3, email);
						updateContactStmt.setLong(4, locationId);
						updateContactStmt.executeUpdate();
					}

					if (updateHours) {
						syncHours(lib, locationId, existingHoursStmt, insertHoursStmt, updateHoursStmt);
					}
				} else {
					// New branch — create library and location
					String subdomain = branchcode.toLowerCase();
					if (subdomain.length() > 25) subdomain = subdomain.substring(0, 25);
					String displayName = branchname.length() > 50 ? branchname.substring(0, 50) : branchname;

					insertLibraryStmt.setString(1, subdomain);
					insertLibraryStmt.setString(2, displayName);
					insertLibraryStmt.executeUpdate();
					ResultSet libraryKeys = insertLibraryStmt.getGeneratedKeys();
					long libraryId = libraryKeys.next() ? libraryKeys.getLong(1) : 0;

					String locationDisplay = branchname.length() > 60 ? branchname.substring(0, 60) : branchname;
					insertLocationStmt.setLong(1, libraryId);
					insertLocationStmt.setString(2, locationDisplay);
					insertLocationStmt.setString(3, branchcode);
					insertLocationStmt.setString(4, address);
					insertLocationStmt.setString(5, phone);
					insertLocationStmt.setString(6, email);
					insertLocationStmt.executeUpdate();
					ResultSet locationKeys = insertLocationStmt.getGeneratedKeys();
					long locationId = locationKeys.next() ? locationKeys.getLong(1) : 0;

					// Records to include for location
					insertLocationRecordsOwnedStmt.setLong(1, locationId);
					insertLocationRecordsOwnedStmt.setLong(2, indexingProfileId);
					insertLocationRecordsOwnedStmt.setString(3, branchcode);
					insertLocationRecordsOwnedStmt.executeUpdate();

					insertLocationRecordsToIncludeStmt.setLong(1, locationId);
					insertLocationRecordsToIncludeStmt.setLong(2, indexingProfileId);
					insertLocationRecordsToIncludeStmt.executeUpdate();

					// Records to include for library
					insertLibraryRecordsOwnedStmt.setLong(1, libraryId);
					insertLibraryRecordsOwnedStmt.setLong(2, indexingProfileId);
					insertLibraryRecordsOwnedStmt.setString(3, branchcode);
					insertLibraryRecordsOwnedStmt.executeUpdate();

					insertLibraryRecordsToIncludeStmt.setLong(1, libraryId);
					insertLibraryRecordsToIncludeStmt.setLong(2, indexingProfileId);
					insertLibraryRecordsToIncludeStmt.executeUpdate();

					// Sync hours for new location too
					syncHours(lib, locationId, existingHoursStmt, insertHoursStmt, updateHoursStmt);

					logEntry.addNote("Added new library/location for branch " + branchcode);
				}
				existingRS.close();
			}

			logEntry.addNote("Finished syncing " + libraries.length() + " libraries");
		} catch (SQLException e) {
			logEntry.incErrors("Error syncing libraries: " + e.getMessage());
			logger.error("Error syncing libraries", e);
		}
	}

	private static java.util.HashMap<String, String> buildEmbedHeaders() {
		java.util.HashMap<String, String> headers = new java.util.HashMap<>();
		headers.put("x-koha-embed", "library_hours");
		return headers;
	}

	private static String buildAddress(JSONObject lib) {
		StringBuilder addr = new StringBuilder();
		appendIfPresent(addr, lib, "address1", "");
		appendIfPresent(addr, lib, "address2", "\n");
		appendIfPresent(addr, lib, "address3", "\n");
		String city = lib.optString("city", "");
		String state = lib.optString("state", "");
		String zip = lib.optString("postal_code", "");
		String country = lib.optString("country", "");

		StringBuilder cityLine = new StringBuilder();
		if (!city.isEmpty()) cityLine.append(city);
		if (!state.isEmpty()) {
			if (cityLine.length() > 0) cityLine.append(", ");
			cityLine.append(state);
		}
		if (!zip.isEmpty()) {
			if (cityLine.length() > 0) cityLine.append(" ");
			cityLine.append(zip);
		}
		if (cityLine.length() > 0) {
			if (addr.length() > 0) addr.append("\n");
			addr.append(cityLine);
		}
		if (!country.isEmpty()) {
			if (addr.length() > 0) addr.append(", ");
			addr.append(country);
		}
		return addr.toString();
	}

	private static void appendIfPresent(StringBuilder sb, JSONObject obj, String key, String prefix) {
		String val = obj.optString(key, "");
		if (!val.isEmpty()) {
			if (sb.length() > 0 && !prefix.isEmpty()) sb.append(prefix);
			sb.append(val);
		}
	}

	private static void syncHours(JSONObject lib, long locationId,
	                               PreparedStatement existingHoursStmt,
	                               PreparedStatement insertHoursStmt,
	                               PreparedStatement updateHoursStmt) throws SQLException {
		if (lib.isNull("library_hours")) return;
		JSONArray hours = lib.optJSONArray("library_hours");
		if (hours == null) return;

		for (int h = 0; h < hours.length(); h++) {
			JSONObject hourEntry = hours.getJSONObject(h);
			int day = hourEntry.getInt("day");
			// Koha returns HH:MM:SS, Aspen stores HH:MM
			String open = trimTime(hourEntry.optString("open_time", "09:00"));
			String close = trimTime(hourEntry.optString("close_time", "17:00"));

			existingHoursStmt.setLong(1, locationId);
			existingHoursStmt.setInt(2, day);
			ResultSet rs = existingHoursStmt.executeQuery();
			if (rs.next()) {
				updateHoursStmt.setString(1, open);
				updateHoursStmt.setString(2, close);
				updateHoursStmt.setLong(3, locationId);
				updateHoursStmt.setInt(4, day);
				updateHoursStmt.executeUpdate();
			} else {
				insertHoursStmt.setLong(1, locationId);
				insertHoursStmt.setInt(2, day);
				insertHoursStmt.setString(3, open);
				insertHoursStmt.setString(4, close);
				insertHoursStmt.executeUpdate();
			}
			rs.close();
		}
	}

	private static String trimTime(String time) {
		if (time == null || time.isEmpty()) return "09:00";
		// HH:MM:SS → HH:MM
		if (time.length() > 5) return time.substring(0, 5);
		return time;
	}
}
