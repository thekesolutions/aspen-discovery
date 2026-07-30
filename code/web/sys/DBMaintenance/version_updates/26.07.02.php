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

		//tomas

		// stephen

		//other

	];
}
