<?php
/** @noinspection SqlDialectInspection */

/** @noinspection PhpUnused */
function getUpdates26_07_02(): array {
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
		'increase_user_username_column' => [
			'title' => 'Increase username column in user table',
			'description' => 'Increase username column in user table',
			'continueOnError' => false,
			'sql' => [
				'ALTER TABLE user CHANGE COLUMN username username VARCHAR(255) NOT NULL',
			]
		], //increase_user_username_column

		//kirstien

		//kodi

		//yanjun

		//imani

		//galen

		//chloe

		//pedro

		//mark j

		//lucas
		'storage_settings' => [
			'title' => 'Add storage settings table',
			'description' => 'Add table to configure the storage backend for uploaded files.',
			'continueOnError' => false,
			'sql' => [
				"CREATE TABLE IF NOT EXISTS storage_settings (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					name VARCHAR(255) NOT NULL DEFAULT '',
					driver ENUM('local') NOT NULL DEFAULT 'local',
					isActive TINYINT(1) NOT NULL DEFAULT 0
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
				"INSERT INTO storage_settings (name, driver, isActive) VALUES ('Local Storage', 'local', 1)",
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('System Administration', 'Administer Storage Settings', '', 0, 'Allows the user to configure the storage backend for uploaded files.')",
				"INSERT INTO role_permissions(roleId, permissionId) VALUES ((SELECT roleId FROM roles WHERE name='opacAdmin'), (SELECT id FROM permissions WHERE name='Administer Storage Settings'))",
			],
		], //storage_settings

		//lucas
		'image_uploads_storage_setting_id' => [
			'title' => 'Track storage backend per image upload',
			'description' => 'Add storageSettingId to image_uploads so each file remembers which storage backend it was actually written to. NULL means Local Storage, since that was the only backend before this feature existed.',
			'continueOnError' => false,
			'sql' => [
				"ALTER TABLE image_uploads ADD COLUMN storageSettingId INT NULL DEFAULT NULL",
			],
		], //image_uploads_storage_setting_id

		//lucas
		'add_open_archives_cover_source_type' => [
			'title' => 'Add cover source type to Open Archives collections',
			'description' => 'Adds a column letting an Open Archives collection opt into a platform-specific bulk cover-prefetch resolver (e.g. DSpace 7+) instead of relying solely on the default per-record page scrape.',
			'continueOnError' => false,
			'sql' => [
				"ALTER TABLE open_archives_collection ADD COLUMN coverSourceType VARCHAR(50) NOT NULL DEFAULT 'default' AFTER imageRegex",
			]
		], //add_open_archives_cover_source_type

		'add_open_archives_record_cover_url' => [
			'title' => 'Add cover URL to Open Archives records',
			'description' => 'Adds a column to store a cover URL discovered ahead of time by a platform-specific bulk resolver, shared across all cover sizes so the per-record page scrape only runs when no URL is already known.',
			'continueOnError' => false,
			'sql' => [
				"ALTER TABLE open_archives_record ADD COLUMN coverUrl TEXT NULL AFTER permanentUrl",
			]
		], //add_open_archives_record_cover_url

		//lucas
		'header_background_image_height' => [
			'title' => 'Header Background Image Height',
			'description' => 'Add headerBackgroundImageHeight to themes so the header can have an explicit height independent of the Logo image size.',
			'continueOnError' => false,
			'sql' => [
				'ALTER TABLE themes ADD COLUMN headerBackgroundImageHeight VARCHAR(10) DEFAULT NULL'
			]
		], //header_background_image_height
		'header_background_image_adapt_height' => [
			'title' => 'Header Background Image Adapt Height',
			'description' => 'Add headerBackgroundImageAdaptHeight to themes so the header can size itself to the background image\'s own proportions instead of a fixed height.',
			'continueOnError' => false,
			'sql' => [
				'ALTER TABLE themes ADD COLUMN headerBackgroundImageAdaptHeight TINYINT(1) DEFAULT 0'
			]
		], //header_background_image_adapt_height

		//tomas

		// stephen

		//other

	];
}
