package com.turning_leaf_technologies.koha_export;

import com.turning_leaf_technologies.config.ConfigUtil;
import com.turning_leaf_technologies.file.JarUtil;
import com.turning_leaf_technologies.indexing.IlsExtractLogEntry;
import com.turning_leaf_technologies.indexing.IndexingProfile;
import com.turning_leaf_technologies.indexing.IndexingUtils;
import com.turning_leaf_technologies.logging.LoggingUtil;
import com.turning_leaf_technologies.strings.AspenStringUtils;
import com.turning_leaf_technologies.util.SystemUtils;
import org.apache.logging.log4j.Logger;
import org.ini4j.Ini;

import java.sql.*;
import java.util.Date;

/**
 * Koha export process that uses the Koha REST API instead of direct DB access.
 * <p>
 * Backward compatible: launched as {@code java -jar koha_export.jar <serverName>}
 * so checkBackgroundProcesses.php can manage it unchanged.
 * <p>
 * Exposes HTTP endpoints on a configurable port (default 8080):
 *   /health, /ready, /status
 */
public class KohaExportApiMain {
	private static final String PROCESS_NAME = "koha_export";

	private static Logger logger;
	private static Ini configIni;
	private static Connection dbConn;
	private static String serverName;
	private static IlsExtractLogEntry logEntry;
	private static StatusServer statusServer;
	private static KohaApiClient kohaApi;

	public static void main(String[] args) {
		int statusPort = -1;

		if (args.length == 0) {
			serverName = AspenStringUtils.getInputFromCommandLine("Please enter the server name");
			if (serverName.isEmpty()) {
				System.out.println("You must provide the server name as the first argument.");
				System.exit(1);
			}
		} else {
			serverName = args[0];
			for (int i = 1; i < args.length; i++) {
				if (args[i].startsWith("--status-port=")) {
					statusPort = Integer.parseInt(args[i].substring("--status-port=".length()));
				}
			}
		}

		String profileToLoad = "ils";
		logger = LoggingUtil.setupLogging(serverName, PROCESS_NAME);

		long myChecksumAtStart = JarUtil.getChecksumForJar(logger, PROCESS_NAME, "./" + PROCESS_NAME + ".jar");
		long reindexerChecksumAtStart = JarUtil.getChecksumForJar(logger, "reindexer", "../reindexer/reindexer.jar");

		if (statusPort > 0) {
			try {
				statusServer = new StatusServer(statusPort, logger);
			} catch (Exception e) {
				logger.error("Failed to start status server on port " + statusPort, e);
			}
		}

		Runtime.getRuntime().addShutdownHook(new Thread(() -> {
			if (statusServer != null) {
				StatusServer.ProcessStatus s = new StatusServer.ProcessStatus();
				s.state = StatusServer.ProcessState.SHUTTING_DOWN;
				statusServer.updateStatus(s);
				statusServer.stop();
			}
		}));

		while (true) {
			Date startTime = new Date();
			long startTimeForLogging = startTime.getTime() / 1000;
			logger.info(startTime + ": Starting Koha API Export");

			updatePhase(StatusServer.ProcessState.RUNNING, "initializing");

			configIni = ConfigUtil.loadConfigFile("config.ini", serverName, logger);
			int numChanges = 0;

			try {
				String dbUrl = ConfigUtil.cleanIniValue(configIni.get("Database", "database_aspen_jdbc"));
				if (dbUrl == null) {
					logger.error("Please provide database_aspen_jdbc within config.pwd.ini");
					System.exit(1);
				}
				dbConn = DriverManager.getConnection(dbUrl);

				// JAR checksum change → exit for restart
				if (myChecksumAtStart != JarUtil.getChecksumForJar(logger, PROCESS_NAME, "./" + PROCESS_NAME + ".jar")
						|| reindexerChecksumAtStart != JarUtil.getChecksumForJar(logger, "reindexer", "../reindexer/reindexer.jar")) {
					IndexingUtils.markNightlyIndexNeeded(dbConn, logger);
					disconnectDatabase();
					break;
				}

				logEntry = new IlsExtractLogEntry(dbConn, profileToLoad, logger);

				// Purge old log entries (>45 days)
				long cutoff = startTimeForLogging - (60 * 60 * 24 * 45);
				try {
					dbConn.prepareStatement("DELETE FROM ils_extract_log WHERE startTime < " + cutoff + " AND indexingProfile = '" + profileToLoad + "'").executeUpdate();
				} catch (SQLException e) {
					logger.error("Error deleting old log entries", e);
				}

				SystemUtils.quitIfOffline(dbConn, logger, logEntry);

				// Initialize Koha API client from account_profiles
				kohaApi = initializeKohaApiClient(dbConn);
				if (kohaApi == null) {
					logEntry.incErrors("Could not initialize Koha API client");
					logEntry.setFinished();
					updatePhase(StatusServer.ProcessState.ERROR, "auth_failed");
					sleepMinutes(5);
					continue;
				}

				profileToLoad = loadProfileName(dbConn);
				IndexingProfile indexingProfile = IndexingProfile.loadIndexingProfile(serverName, dbConn, profileToLoad, logger, logEntry);
				logEntry.setIsFullUpdate(indexingProfile.isRunFullUpdate());

				updatePhase(StatusServer.ProcessState.RUNNING, "syncing_reference_data");

				float kohaVersion = KohaVersionSync.getKohaVersion(kohaApi, logger);
				logEntry.addNote("Koha version: " + kohaVersion);
				logEntry.saveResults();

				long indexingProfileId = indexingProfile.getId();
				long accountProfileId = loadAccountProfileId(dbConn);

				LibrarySync.syncLibraries(kohaApi, dbConn, indexingProfileId, logger, logEntry);
				logEntry.addNote("Finished updating branch information");
				logEntry.saveResults();

				PatronCategorySync.syncPatronCategories(kohaApi, dbConn, accountProfileId, logger, logEntry);
				logEntry.addNote("Finished updating patron types");
				logEntry.saveResults();

				AuthorisedValueSync.syncTranslationMaps(kohaApi, dbConn, indexingProfileId, indexingProfile, logger, logEntry);
				ItemTypeSync.syncItemTypes(kohaApi, dbConn, indexingProfileId, logger, logEntry);
				logEntry.addNote("Finished updating translation maps");
				logEntry.saveResults();

				HoldsSync.syncHolds(kohaApi, dbConn, logger, logEntry);
				logEntry.addNote("Finished loading holds");
				logEntry.saveResults();

				updatePhase(StatusServer.ProcessState.RUNNING, "processing_records");

				// Biblio record sync via API
				RecordSync recordSync = new RecordSync(
						kohaApi, dbConn, serverName, configIni, indexingProfile, kohaVersion, logEntry, logger);
				numChanges = recordSync.syncRecords();

				logEntry.setFinished();
				logger.info(new Date() + ": Finished Koha API Export");

			} catch (Exception e) {
				logger.error("Error in export cycle", e);
				if (logEntry != null) {
					logEntry.incErrors("Unexpected error: " + e.getMessage());
					logEntry.setFinished();
				}
				updatePhase(StatusServer.ProcessState.ERROR, e.getMessage());
			}

			// JAR checksum change → exit
			if (myChecksumAtStart != JarUtil.getChecksumForJar(logger, PROCESS_NAME, "./" + PROCESS_NAME + ".jar")) {
				try { IndexingUtils.markNightlyIndexNeeded(dbConn, logger); } catch (Exception ignored) {}
				disconnectDatabase();
				break;
			}

			updatePhase(StatusServer.ProcessState.IDLE, "waiting");

			if (numChanges == 0) {
				sleepMinutes(5);
			} else {
				sleepMinutes(1);
			}

			// Auto-restart after 15 hours (between midnight and 1am)
			Date now = new Date();
			if (now.getTime() - startTime.getTime() > 15 * 60 * 60 * 1000) {
				int hour = new java.util.GregorianCalendar().get(java.util.Calendar.HOUR_OF_DAY);
				if (hour == 0) {
					logger.info("Restarting after 15+ hours");
					disconnectDatabase();
					break;
				}
			}
		}

		if (statusServer != null) statusServer.stop();
	}

	/**
	 * Build a KohaApiClient from the account_profiles table.
	 * Reads the Koha base URL and OAuth2 credentials from the same config
	 * the PHP side uses.
	 */
	private static KohaApiClient initializeKohaApiClient(Connection dbConn) {
		try {
			PreparedStatement stmt = dbConn.prepareStatement(
					"SELECT patronApiUrl, oAuthClientId, oAuthClientSecret " +
					"FROM account_profiles WHERE ils = 'koha' LIMIT 1"
			);
			ResultSet rs = stmt.executeQuery();
			if (!rs.next()) {
				logger.error("No Koha account profile found");
				return null;
			}

			String baseApiUrl = rs.getString("patronApiUrl");
			String clientId = rs.getString("oAuthClientId");
			String clientSecret = rs.getString("oAuthClientSecret");
			rs.close();

			if (baseApiUrl == null || baseApiUrl.isEmpty()) {
				logger.error("patronApiUrl not configured in account_profiles for Koha");
				return null;
			}
			if (clientId == null || clientId.isEmpty() || clientSecret == null || clientSecret.isEmpty()) {
				logger.error("OAuth2 client credentials not configured in account_profiles for Koha");
				return null;
			}

			KohaApiClient client = new KohaApiClient(baseApiUrl, clientId, clientSecret, logger);
			if (!client.isAuthenticated()) {
				logger.error("Failed to authenticate with Koha API at " + baseApiUrl);
				return null;
			}

			logger.info("Connected to Koha API at " + baseApiUrl);
			return client;
		} catch (SQLException e) {
			logger.error("Error reading account_profiles", e);
			return null;
		}
	}

	private static String loadProfileName(Connection dbConn) {
		try {
			PreparedStatement stmt = dbConn.prepareStatement(
					"SELECT recordSource FROM account_profiles WHERE ils = 'koha' LIMIT 1"
			);
			ResultSet rs = stmt.executeQuery();
			if (rs.next()) {
				String name = rs.getString("recordSource");
				if (name != null && !name.isEmpty()) return name;
			}
		} catch (SQLException e) {
			logger.error("Error loading profile name", e);
		}
		return "ils";
	}

	private static long loadAccountProfileId(Connection dbConn) {
		try {
			PreparedStatement stmt = dbConn.prepareStatement(
					"SELECT id FROM account_profiles WHERE ils = 'koha' LIMIT 1"
			);
			ResultSet rs = stmt.executeQuery();
			if (rs.next()) return rs.getLong("id");
		} catch (SQLException e) {
			logger.error("Error loading account profile id", e);
		}
		return 0;
	}

	private static void updatePhase(StatusServer.ProcessState state, String phase) {
		if (statusServer == null) return;
		StatusServer.ProcessStatus s = statusServer.getStatus();
		s.state = state;
		s.currentPhase = phase;
		if (state == StatusServer.ProcessState.RUNNING && s.lastRunStarted == 0) {
			s.lastRunStarted = System.currentTimeMillis() / 1000;
		}
		if (state == StatusServer.ProcessState.IDLE) {
			s.lastRunFinished = System.currentTimeMillis() / 1000;
		}
		statusServer.updateStatus(s);
	}

	private static void sleepMinutes(int minutes) {
		try {
			Thread.sleep(minutes * 60_000L);
		} catch (InterruptedException e) {
			logger.info("Sleep interrupted");
		}
	}

	private static void disconnectDatabase() {
		try {
			if (dbConn != null) dbConn.close();
		} catch (Exception e) {
			logger.error("Error disconnecting from database", e);
		}
	}
}
