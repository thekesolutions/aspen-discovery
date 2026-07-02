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

		//tomas

		// stephen

		//other

	];
}
