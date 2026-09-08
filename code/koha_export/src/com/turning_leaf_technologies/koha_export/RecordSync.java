package com.turning_leaf_technologies.koha_export;

import com.turning_leaf_technologies.indexing.IlsExtractLogEntry;
import com.turning_leaf_technologies.indexing.IndexingProfile;
import com.turning_leaf_technologies.net.WebServiceResponse;
import org.apache.logging.log4j.Logger;
import org.aspen_discovery.grouping.MarcRecordGrouper;
import org.aspen_discovery.grouping.RemoveRecordFromWorkResult;
import org.aspen_discovery.reindexer.GroupedWorkIndexer;
import org.ini4j.Ini;
import org.json.JSONArray;
import org.json.JSONObject;
import org.marc4j.marc.Record;

import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.text.SimpleDateFormat;
import java.util.*;

/**
 * Incremental and full biblio record sync via Koha REST API.
 *
 * Replaces the direct-DB updateRecords()/updateBibRecord() flow with:
 * - GET /biblios (MARCXML, timestamp-filtered, paginated)
 * - GET /biblios/{id}/items (JSON with checkout embed) — only for Koha < 25.11
 * - GET /deleted/biblios (timestamp-filtered)
 * - GET /authorities (MARCXML, timestamp-filtered)
 *
 * Koha 25.11+ supports x-koha-embed: items on the biblios endpoint,
 * which embeds 952 item fields directly into the MARCXML response.
 * This eliminates the per-record item fetch (N+1 → 1 request per page).
 */
public class RecordSync {
	// Koha's RFC3339 parser rejects timestamps without a timezone designator.
	private static final SimpleDateFormat KOHA_TIMESTAMP_FORMAT = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'");
	static {
		KOHA_TIMESTAMP_FORMAT.setTimeZone(TimeZone.getTimeZone("UTC"));
	}

	// GET /biblios has no x-koha-embed parameter at all (confirmed against the live
	// swagger spec: /biblios and /biblios/{id} both lack it; only /biblios/{id}/items
	// and /deleted/biblios support embedding). Items are always fetched per-record below.
	private static final float KOHA_VERSION_EMBED_ITEMS = Float.MAX_VALUE;

	private final KohaApiClient api;
	private final Connection dbConn;
	private final String serverName;
	private final Ini configIni;
	private final IndexingProfile indexingProfile;
	private final IlsExtractLogEntry logEntry;
	private final Logger logger;
	private final boolean embedItemsSupported;

	private MarcRecordGrouper recordGrouper;
	private GroupedWorkIndexer indexer;

	public RecordSync(KohaApiClient api, Connection dbConn, String serverName, Ini configIni,
	                  IndexingProfile indexingProfile, float kohaVersion,
	                  IlsExtractLogEntry logEntry, Logger logger) {
		this.api = api;
		this.dbConn = dbConn;
		this.serverName = serverName;
		this.configIni = configIni;
		this.indexingProfile = indexingProfile;
		this.logEntry = logEntry;
		this.logger = logger;
		this.embedItemsSupported = kohaVersion >= KOHA_VERSION_EMBED_ITEMS;
	}

	/**
	 * Main entry point. Returns total number of changes processed.
	 */
	public int syncRecords() {
		int totalChanges = 0;

		boolean fullUpdate = indexingProfile.isRunFullUpdate();
		long lastUpdate = indexingProfile.getLastUpdateOfChangedRecords();
		if (lastUpdate == 0) {
			lastUpdate = (System.currentTimeMillis() / 1000) - (24 * 60 * 60); // 24h ago
		} else {
			lastUpdate -= 60; // 60s buffer for clock drift
		}

		String sinceTimestamp = KOHA_TIMESTAMP_FORMAT.format(new Date(lastUpdate * 1000));

		// 1. Process changed/new biblios
		totalChanges += processChangedBiblios(fullUpdate, sinceTimestamp);

		// 2. Process deleted biblios
		totalChanges += processDeletedBiblios(fullUpdate, sinceTimestamp);

		// 3. Process records-to-reload queue
		totalChanges += processRecordsToReload();

		// 4. Process changed authorities
		totalChanges += processChangedAuthorities(fullUpdate, sinceTimestamp);

		// 5. Finalize
		if (indexer != null) {
			indexer.finishIndexingFromExtract(logEntry);
		}
		closeGrouper();
		closeIndexer();

		// Update timestamps
		if (fullUpdate) {
			indexingProfile.updateLastChangeProcessed(dbConn, logEntry);
		} else if (!logEntry.hasErrors()) {
			indexingProfile.updateLastChangeProcessed(dbConn, logEntry);
		}

		return totalChanges;
	}

	private int processChangedBiblios(boolean fullUpdate, String sinceTimestamp) {
		int processed = 0;
		int page = 1;
		int perPage = 100;

		// "me." disambiguates from biblioitems.timestamp, which the list endpoint prefetches/joins.
		String qFilter = fullUpdate ? "" : "&q=" + urlEncode("{\"me.timestamp\":{\">\": \"" + sinceTimestamp + "\"}}");
		String orderBy = "&_order_by=+biblio_id";

		if (embedItemsSupported) {
			logEntry.addNote("Koha 26.05+: using x-koha-embed: items for batch MARCXML fetch");
		}

		while (true) {
			// Koha 25.11+: embed items directly into MARCXML (952 fields included)
			// Older Koha: plain MARCXML, items fetched per-record below
			HashMap<String, String> headers = embedItemsSupported ? marcXmlWithItemsHeaders() : marcXmlHeaders();

			WebServiceResponse response = api.get(
					"/api/v1/biblios?_per_page=" + perPage + "&_page=" + page + qFilter + orderBy,
					headers);
			if (!response.isSuccess()) {
				if (response.getResponseCode() == 404) break;

				// A single malformed MARC record (e.g. invalid control chars) makes Koha fail
				// the whole batch response. Fall back to fetching this page's records one at a
				// time so the rest of the page — and all subsequent pages — aren't blocked.
				logEntry.addNote("Batch fetch failed for biblios page " + page + ", falling back to per-record fetch: " + response.getMessage());
				List<Integer> pageIds = fetchBiblioIdsForPage(page, perPage, qFilter, orderBy);
				if (pageIds == null) {
					logEntry.incErrors("Failed to fetch biblios (page " + page + "): " + response.getMessage());
					break;
				}
				for (int id : pageIds) {
					Record marcRecord = MarcRecordBuilder.buildRecord(api, id, logger);
					if (marcRecord == null) {
						logEntry.incErrors("Failed to fetch biblio " + id + ", skipping");
						logEntry.incSkipped();
						continue;
					}
					processed += processOneBiblio(String.valueOf(id), marcRecord);
				}
				logEntry.saveResults();
				if (pageIds.size() < perPage) break;
				page++;
				continue;
			}

			List<Record> records = MarcRecordBuilder.parseMarcXmlCollection(response.getMessage(), logger);
			if (records.isEmpty()) break;

			for (Record marcRecord : records) {
				String biblioId = extractBiblioId(marcRecord);
				if (biblioId == null) continue;

				processed += processOneBiblio(biblioId, marcRecord);
			}

			logEntry.saveResults();
			if (records.size() < perPage) break;
			page++;
		}

		logEntry.addNote("Processed " + processed + " changed biblios");
		return processed;
	}

	/**
	 * Process a single biblio: save, group, index.
	 * If items are not already embedded (Koha < 25.11), fetches them per-record.
	 */
	private int processOneBiblio(String biblioId, Record marcRecord) {
		try {
			// Check if authority record (leader type 'z')
			char typeOfRecord = marcRecord.getLeader().getTypeOfRecord();
			if (typeOfRecord == 'z') {
				RemoveRecordFromWorkResult result = getRecordGrouper().removeRecordFromGroupedWork(
						indexingProfile.getName(), biblioId);
				handleRemoveResult(result);
				logEntry.incDeleted();
				return 1;
			}

			// Fetch items only if not already embedded by the API
			if (!embedItemsSupported) {
				WebServiceResponse itemsResponse = api.get(
						"/api/v1/biblios/" + biblioId + "/items?_per_page=-1",
						itemEmbedHeaders());
				if (itemsResponse.isSuccess()) {
					JSONArray items = itemsResponse.getJSONResponseAsArray();
					if (items != null) {
						for (int i = 0; i < items.length(); i++) {
							marcRecord.addVariableField(MarcRecordBuilder.buildItemField(items.getJSONObject(i)));
						}
					}
				}
			}

			// Save to Aspen DB
			GroupedWorkIndexer.MarcStatus status = getIndexer().saveMarcRecordToDatabase(
					indexingProfile, biblioId, marcRecord);
			if (status == GroupedWorkIndexer.MarcStatus.NEW) {
				logEntry.incAdded();
			} else {
				logEntry.incUpdated();
			}

			// Group and reindex
			String groupedWorkId = getRecordGrouper().processMarcRecord(
					marcRecord, true, null, getIndexer());
			if (groupedWorkId != null) {
				getIndexer().processGroupedWork(groupedWorkId);
			}

			return 1;
		} catch (Exception e) {
			logEntry.incErrors("Error processing biblio " + biblioId + ": " + e.getMessage());
			logEntry.incSkipped();
			return 0;
		}
	}

	private int processDeletedBiblios(boolean fullUpdate, String sinceTimestamp) {
		int deleted = 0;
		int page = 1;
		int perPage = 100;

		// Koha::Old::Biblio maps the DB "timestamp" column to "deleted_on" on the API.
		// "me." disambiguates from biblioitems.timestamp, which the deleted-biblios list also joins.
		String qFilter = fullUpdate ? "" : "&q=" + urlEncode("{\"me.deleted_on\":{\">\": \"" + sinceTimestamp + "\"}}");

		while (true) {
			WebServiceResponse response = api.get(
					"/api/v1/deleted/biblios?_per_page=" + perPage + "&_page=" + page + qFilter);
			if (!response.isSuccess()) {
				if (response.getResponseCode() == 404) break;
				logEntry.incErrors("Failed to fetch deleted biblios (page " + page + "): " + response.getMessage());
				break;
			}

			JSONArray deletedBibs = response.getJSONResponseAsArray();
			if (deletedBibs == null || deletedBibs.length() == 0) break;

			for (int i = 0; i < deletedBibs.length(); i++) {
				JSONObject bib = deletedBibs.getJSONObject(i);
				String biblioId = String.valueOf(bib.optInt("biblio_id", 0));
				if (biblioId.equals("0")) continue;

				RemoveRecordFromWorkResult result = getRecordGrouper().removeRecordFromGroupedWork(
						indexingProfile.getName(), biblioId);
				handleRemoveResult(result);
				logEntry.incDeleted();
				deleted++;
			}

			if (deletedBibs.length() < perPage) break;
			page++;
		}

		if (deleted > 0) {
			logEntry.addNote("Processed " + deleted + " deleted biblios");
		}
		return deleted;
	}

	private int processRecordsToReload() {
		int reloaded = 0;
		try {
			PreparedStatement stmt = dbConn.prepareStatement(
					"SELECT identifier FROM record_identifiers_to_reload " +
					"WHERE processed = 0 AND type = 'ils'");
			ResultSet rs = stmt.executeQuery();

			PreparedStatement markProcessed = dbConn.prepareStatement(
					"UPDATE record_identifiers_to_reload SET processed = 1 WHERE identifier = ? AND type = 'ils'");

			while (rs.next()) {
				String id = rs.getString("identifier");
				int biblioId;
				try {
					biblioId = Integer.parseInt(id);
				} catch (NumberFormatException e) {
					continue;
				}

				Record record = MarcRecordBuilder.buildRecord(api, biblioId, logger);
				if (record != null) {
					processOneBiblio(id, record);
					reloaded++;
				}

				markProcessed.setString(1, id);
				markProcessed.executeUpdate();
			}
		} catch (SQLException e) {
			logEntry.incErrors("Error processing records to reload: " + e.getMessage());
		}
		return reloaded;
	}

	private int processChangedAuthorities(boolean fullUpdate, String sinceTimestamp) {
		// Authorities are fetched as MARCXML and saved for the reindexer
		// The existing process reads auth_header — we use GET /authorities
		int processed = 0;
		int page = 1;
		int perPage = 100;

		// Koha::Authority maps the DB "modification_time" column to "modified_date" on the API.
		String qFilter = fullUpdate ? "" : "&q=" + urlEncode("{\"modified_date\":{\">\": \"" + sinceTimestamp + "\"}}");

		while (true) {
			WebServiceResponse response = api.get(
					"/api/v1/authorities?_per_page=" + perPage + "&_page=" + page + qFilter,
					marcXmlHeaders());
			if (!response.isSuccess()) {
				if (response.getResponseCode() == 404) break;
				// Authorities may not be critical — log and continue
				logEntry.addNote("Could not fetch authorities (page " + page + "): " + response.getMessage());
				break;
			}

			List<Record> records = MarcRecordBuilder.parseMarcXmlCollection(response.getMessage(), logger);
			if (records.isEmpty()) break;

			// Authority records are stored for the reindexer to use during grouping
			// The existing process saves them to the grouped_work_author_authorities table
			// via the GroupedWorkIndexer
			processed += records.size();

			if (records.size() < perPage) break;
			page++;
		}

		if (processed > 0) {
			logEntry.addNote("Processed " + processed + " authority records");
		}
		return processed;
	}

	private void handleRemoveResult(RemoveRecordFromWorkResult result) {
		if (result.reindexWork) {
			getIndexer().processGroupedWork(result.permanentId);
		} else if (result.deleteWork) {
			getIndexer().deleteRecord(result.permanentId, result.groupedWorkId);
		}
	}

	/**
	 * Extract the biblio ID from a MARC record's 999$c (Koha's biblionumber field).
	 */
	private String extractBiblioId(Record record) {
		org.marc4j.marc.DataField f999 = (org.marc4j.marc.DataField) record.getVariableField("999");
		if (f999 != null) {
			org.marc4j.marc.Subfield sf = f999.getSubfield('c');
			if (sf != null) return sf.getData();
		}
		return null;
	}

	private MarcRecordGrouper getRecordGrouper() {
		if (recordGrouper == null) {
			recordGrouper = new MarcRecordGrouper(serverName, dbConn, indexingProfile, logEntry, logger);
		}
		return recordGrouper;
	}

	private GroupedWorkIndexer getIndexer() {
		if (indexer == null) {
			indexer = new GroupedWorkIndexer(serverName, dbConn, configIni, false, false, logEntry, logger);
		}
		return indexer;
	}

	private void closeGrouper() {
		if (recordGrouper != null) {
			recordGrouper.close();
			recordGrouper = null;
		}
	}

	private void closeIndexer() {
		if (indexer != null) {
			indexer.close();
			indexer = null;
		}
	}

	/**
	 * Fetches just the biblio_ids for a page via the JSON representation, which doesn't
	 * decode MARC metadata and so succeeds even when the MARCXML batch fetch fails on a
	 * malformed record. Returns null on failure (distinct from an empty/short last page).
	 */
	private List<Integer> fetchBiblioIdsForPage(int page, int perPage, String qFilter, String orderBy) {
		WebServiceResponse response = api.get(
				"/api/v1/biblios?_per_page=" + perPage + "&_page=" + page + qFilter + orderBy,
				jsonHeaders());
		if (!response.isSuccess()) return null;

		JSONArray bibs = response.getJSONResponseAsArray();
		if (bibs == null) return null;

		List<Integer> ids = new ArrayList<>();
		for (int i = 0; i < bibs.length(); i++) {
			int id = bibs.getJSONObject(i).optInt("biblio_id", 0);
			if (id > 0) ids.add(id);
		}
		return ids;
	}

	private static HashMap<String, String> jsonHeaders() {
		HashMap<String, String> h = new HashMap<>();
		h.put("Accept", "application/json");
		return h;
	}

	private static HashMap<String, String> marcXmlHeaders() {
		HashMap<String, String> h = new HashMap<>();
		h.put("Accept", "application/marcxml+xml");
		return h;
	}

	private static HashMap<String, String> marcXmlWithItemsHeaders() {
		HashMap<String, String> h = new HashMap<>();
		h.put("Accept", "application/marcxml+xml");
		h.put("x-koha-embed", "items");
		return h;
	}

	private static HashMap<String, String> itemEmbedHeaders() {
		HashMap<String, String> h = new HashMap<>();
		h.put("x-koha-embed", "checkout");
		return h;
	}

	private static String urlEncode(String s) {
		return URLEncoder.encode(s, StandardCharsets.UTF_8);
	}
}
