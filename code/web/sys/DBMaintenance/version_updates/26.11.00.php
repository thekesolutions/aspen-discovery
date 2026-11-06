<?php
/** @noinspection SqlDialectInspection */

/** @noinspection PhpUnused */
function getUpdates26_11_00(): array {
	$now = time();

	return [
		/*'name' => [
			 'title' => '',
			 'description' => '',
			 'continueOnError' => false,
			 'sql' => [
				 ''
			 ]
		 ], //name*/

		//mark n

		//kirstien

		//kodi

		//yanjun
		'paypal_client_credentials_length' => [
			'title' => 'Expand PayPal Client Credential Lengths',
			'description' => 'Increase the maximum length of PayPal Client ID and Client Secret to 100 characters.',
			'continueOnError' => false,
			'sql' => [
				'ALTER TABLE paypal_settings MODIFY COLUMN clientId VARCHAR(100) DEFAULT NULL',
				'ALTER TABLE paypal_settings MODIFY COLUMN clientSecret VARCHAR(100) DEFAULT NULL',
			]
		], //paypal_client_credentials_length

		//imani

		//galen

		//chloe

		//pedro

		//mark j

		//lucas
		'oauth2_rate_limits_per_client' => [
			'title' => 'OAuth2 rate limits per client',
			'description' => 'Count OAuth2 API requests per client and endpoint',
			'continueOnError' => false,
			'sql' => [
				// The stored counters belong to placeholder client ids and expire within the hour anyway
				'TRUNCATE TABLE oauth2_rate_limits',
				'ALTER TABLE oauth2_rate_limits DROP INDEX unique_rate_limit, ADD UNIQUE KEY unique_rate_limit (client_id, endpoint)',
			],
		], //oauth2_rate_limits_per_client

		'oauth2_client_rate_limits' => [
			'title' => 'OAuth2 client rate limits',
			'description' => 'Allow a rate limit per OAuth2 client',
			'continueOnError' => false,
			'sql' => [
				'ALTER TABLE oauth2_clients ADD COLUMN rate_limit_requests INT NOT NULL DEFAULT 0 AFTER is_active',
				'ALTER TABLE oauth2_clients ADD COLUMN rate_limit_window INT NOT NULL DEFAULT 0 AFTER rate_limit_requests',
			],
		], //oauth2_client_rate_limits

		'storage_objects' => [
			'title' => 'Add storage object registry',
			'description' => 'One row per stored file. Keys are random and never reused. Each file fills a slot of the object that owns it: the property and its variant (thumbnail, a cover size).',
			'continueOnError' => false,
			'sql' => [
				"CREATE TABLE IF NOT EXISTS storage_objects (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					storageSettingId INT NOT NULL,
					storageKey VARCHAR(500) NOT NULL,
					originalName VARCHAR(255) NOT NULL DEFAULT '',
					mimeType VARCHAR(100) NOT NULL DEFAULT '',
					size BIGINT NOT NULL DEFAULT 0,
					sha256 CHAR(64) NOT NULL DEFAULT '',
					ownerTable VARCHAR(64) NOT NULL,
					ownerId INT NOT NULL,
					ownerField VARCHAR(100) NOT NULL,
					variant VARCHAR(20) NOT NULL DEFAULT '',
					fingerprint VARCHAR(64) NOT NULL DEFAULT '',
					dateCreated INT NOT NULL,
					UNIQUE INDEX storageKey (storageSettingId, storageKey(255)),
					UNIQUE INDEX slot (ownerTable, ownerId, ownerField, variant, fingerprint),
					INDEX dateCreated (dateCreated)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
			],
		], //storage_objects
		'storage_deletions' => [
			'title' => 'Add storage deletion queue',
			'description' => 'Files released from the registry are deleted by the nightly cleanup once the delete delay has passed.',
			'continueOnError' => false,
			'sql' => [
				"CREATE TABLE IF NOT EXISTS storage_deletions (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					storageSettingId INT NOT NULL,
					storageKey VARCHAR(500) NOT NULL,
					dateQueued INT NOT NULL,
					INDEX dateQueued (dateQueued)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
			],
		], //storage_deletions
		'storage_settings_delete_delay' => [
			'title' => 'Add delete delay to storage settings',
			'description' => 'Number of days a released file is kept before it is deleted.',
			'continueOnError' => false,
			'sql' => [
				"ALTER TABLE storage_settings ADD COLUMN deleteDelayDays INT NOT NULL DEFAULT 7",
			],
		], //storage_settings_delete_delay
		'storage_api_oauth2_client' => [
			'title' => 'Add the storage API OAuth2 client',
			'description' => 'Service client that background processes use to store files through the storage API.',
			'continueOnError' => false,
			'sql' => [
				'createStorageApiOAuth2Client',
			],
		], //storage_api_oauth2_client
		'storage_migrate_theme_images' => [
			'title' => 'Move theme images to the storage registry',
			'description' => 'Registers every theme image where it is, so its URL does not change. Images whose file is missing are cleared and listed.',
			'continueOnError' => false,
			'sql' => [
				'migrateThemeImagesToStorage',
			],
		], //storage_migrate_theme_images
		'storage_migrate_uploaded_covers' => [
			'title' => 'Move uploaded covers to the storage registry',
			'description' => 'Registers every uploaded record cover where it is. Records whose uploaded cover is missing go back to their other cover sources and are listed.',
			'continueOnError' => false,
			'sql' => [
				'migrateUploadedCoversToStorage',
			],
		], //storage_migrate_uploaded_covers

		//tomas

		// stephen

		//jacob - OpenFifth


	];
}

/**
 * The Java exporters find this client as the active service client with the storage:write scope,
 * so an admin can rotate its secret, or replace the client, from the OAuth2 clients page.
 *
 * @noinspection PhpUnused
 */
function createStorageApiOAuth2Client(&$update): void {
	global $aspen_db;
	$existing = $aspen_db->query("SELECT COUNT(*) FROM oauth2_clients WHERE client_type = 'service_application' AND FIND_IN_SET('storage:write', scopes) > 0")->fetchColumn();
	if ($existing == 0) {
		require_once ROOT_DIR . '/sys/Authentication/OAuth2/OAuth2Client.php';
		$client = new OAuth2Client();
		$client->__set('name', 'Aspen Storage');
		$client->__set('client_type', 'service_application');
		$client->__set('scopes', ['storage:write']);
		$client->__set('redirect_uri', '');
		$client->__set('is_active', 1);
		// Exporters send one request per cover, the default of 100 an hour would take days for a catalog.
		$client->__set('rate_limit_requests', 1200);
		$client->__set('rate_limit_window', 60);
		if (!$client->insert()) {
			$update['success'] = false;
			$update['status'] = translate([
				'text' => 'Could not create the storage API OAuth2 client',
				'isAdminFacing' => true,
			]);
			return;
		}
	}
	$update['success'] = true;
	$update['status'] = translate([
		'text' => 'The storage API OAuth2 client exists',
		'isAdminFacing' => true,
	]);
}

/** @noinspection PhpUnused */
function migrateThemeImagesToStorage(&$update): void {
	require_once ROOT_DIR . '/sys/Theming/Theme.php';
	reportStorageMigration($update, Theme::migrateImagesToStorage(), 'Registered %1% theme images in the storage registry');
}

/** @noinspection PhpUnused */
function migrateUploadedCoversToStorage(&$update): void {
	require_once ROOT_DIR . '/sys/Covers/BookCoverInfo.php';
	reportStorageMigration($update, BookCoverInfo::migrateUploadedCoversToStorage(), 'Registered %1% uploaded covers in the storage registry');
}

/**
 * The status lists every item that was cleared or could not be registered, so the admin can see on
 * the Database Maintenance page what to upload again. The items are logged too, since updates run
 * from the command line (upgrade scripts, Docker startup) only report the status of failed updates.
 */
function reportStorageMigration(&$update, array $result, string $summary): void {
	global $logger;
	foreach ($result['notes'] as $note) {
		$logger->log("$update[title]: $note", Logger::LOG_WARNING, true);
	}
	$update['success'] = $result['success'];
	$status = translate([
		'text' => $summary,
		1 => $result['numRegistered'],
		'isAdminFacing' => true,
	]);
	if (!empty($result['notes'])) {
		// "Warning" makes the Database Maintenance page highlight the update
		$status .= '<br>Warning:<br>' . implode('<br>', array_map('htmlspecialchars', $result['notes']));
	}
	$update['status'] = $status;
}
