package com.theke_solutions.koha_export;

import com.turning_leaf_technologies.net.WebServiceResponse;
import org.apache.logging.log4j.Logger;
import org.json.JSONArray;
import org.json.JSONObject;
import org.marc4j.MarcXmlReader;
import org.marc4j.marc.DataField;
import org.marc4j.marc.MarcFactory;
import org.marc4j.marc.Record;

import java.io.ByteArrayInputStream;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;

/**
 * Builds MARC records from Koha REST API responses.
 * Fetches MARCXML for the bib and JSON for items, then assembles
 * 952 item fields matching the existing KohaExportMain mapping.
 */
public class MarcRecordBuilder {
	private static final MarcFactory marcFactory = MarcFactory.newInstance();

	/**
	 * Fetch a single biblio as a MARC Record with items injected as 952 fields.
	 * Returns null if the record cannot be fetched.
	 */
	public static Record buildRecord(KohaApiClient api, int biblioId, Logger logger) {
		// 1. Fetch bib as MARCXML
		WebServiceResponse marcResponse = api.getMarc("/api/v1/biblios/" + biblioId);
		if (!marcResponse.isSuccess()) {
			return null;
		}

		Record marcRecord = parseMarcXml(marcResponse.getMessage(), logger);
		if (marcRecord == null) return null;

		// 2. Fetch items with embeds
		WebServiceResponse itemsResponse = api.get(
				"/api/v1/biblios/" + biblioId + "/items?_per_page=-1",
				buildItemEmbedHeaders());
		if (itemsResponse.isSuccess()) {
			JSONArray items = itemsResponse.getJSONResponseAsArray();
			if (items != null) {
				for (int i = 0; i < items.length(); i++) {
					DataField field = buildItemField(items.getJSONObject(i));
					marcRecord.addVariableField(field);
				}
			}
		}

		return marcRecord;
	}

	/**
	 * Parse a list of MARCXML records from a collection response.
	 * The API returns a MARCXML collection when listing biblios.
	 */
	public static List<Record> parseMarcXmlCollection(String xml, Logger logger) {
		List<Record> records = new ArrayList<>();
		if (xml == null || xml.isEmpty()) return records;
		try {
			MarcXmlReader reader = new MarcXmlReader(
					new ByteArrayInputStream(xml.getBytes(StandardCharsets.UTF_8)));
			while (reader.hasNext()) {
				records.add(reader.next());
			}
		} catch (Exception e) {
			logger.error("Error parsing MARCXML collection", e);
		}
		return records;
	}

	static Record parseMarcXml(String xml, Logger logger) {
		if (xml == null || xml.isEmpty()) return null;
		try {
			MarcXmlReader reader = new MarcXmlReader(
					new ByteArrayInputStream(xml.getBytes(StandardCharsets.UTF_8)));
			if (reader.hasNext()) {
				return reader.next();
			}
		} catch (Exception e) {
			logger.error("Error parsing MARCXML", e);
		}
		return null;
	}

	/**
	 * Build a MARC 952 DataField from a Koha item JSON object.
	 * Mapping matches the existing KohaExportMain 952 subfield assignments.
	 */
	static DataField buildItemField(JSONObject item) {
		DataField f = marcFactory.newDataField("952", ' ', ' ');

		addSub(f, '0', item.optString("withdrawn", null));
		addSub(f, '1', item.optString("lost_status", null));
		addSub(f, '2', item.optString("call_number_source", null));
		addSub(f, '3', item.optString("materials_notes", null));
		addSub(f, '4', item.optString("damaged_status", null));
		addSub(f, '5', item.optString("restricted_status", null));
		addSub(f, '6', item.optString("call_number_sort", null));
		addSub(f, '7', item.optString("not_for_loan_status", null));
		addSub(f, '8', item.optString("collection_code", null));
		addSub(f, '9', intToString(item.optInt("item_id", 0)));
		addSub(f, 'a', item.optString("home_library_id", null));
		addSub(f, 'b', item.optString("holding_library_id", null));
		addSub(f, 'c', item.optString("location", null));
		addSub(f, 'd', item.optString("acquisition_date", null));
		addSub(f, 'e', item.optString("acquisition_source", null));
		addSub(f, 'f', item.optString("coded_location_qualifier", null));
		addSub(f, 'g', item.optString("purchase_price", null));
		addSub(f, 'h', item.optString("serial_issue_number", null));
		addSub(f, 'i', item.optString("inventory_number", null));
		addSub(f, 'j', item.optString("shelving_control_number", null));

		// $k = due date from embedded checkout (Aspen-specific subfield)
		JSONObject checkout = item.optJSONObject("checkout");
		if (checkout != null) {
			addSub(f, 'k', checkout.optString("due_date", null));
		}

		addSub(f, 'l', item.optString("checkouts_count", null));
		addSub(f, 'm', item.optString("renewals_count", null));
		addSub(f, 'n', item.optString("renewals_count", null));
		addSub(f, 'o', item.optString("callnumber", null));
		addSub(f, 'p', item.optString("external_id", null)); // barcode
		addSub(f, 'q', item.optString("checked_out_date", null)); // onloan
		addSub(f, 'r', item.optString("last_seen_date", null));
		addSub(f, 's', item.optString("last_checkout_date", null));
		addSub(f, 't', item.optString("copy_number", null));
		addSub(f, 'u', item.optString("uri", null));
		addSub(f, 'v', item.optString("replacement_price", null));
		addSub(f, 'w', item.optString("replacement_price_date", null));
		addSub(f, 'y', item.optString("item_type_id", null));
		addSub(f, 'z', item.optString("public_notes", null));

		return f;
	}

	private static java.util.HashMap<String, String> buildItemEmbedHeaders() {
		java.util.HashMap<String, String> headers = new java.util.HashMap<>();
		headers.put("x-koha-embed", "checkout");
		return headers;
	}

	private static void addSub(DataField field, char code, String data) {
		if (data != null && !data.isEmpty() && !data.equals("null")) {
			field.addSubfield(marcFactory.newSubfield(code, data));
		}
	}

	private static String intToString(int val) {
		return val > 0 ? String.valueOf(val) : null;
	}
}
