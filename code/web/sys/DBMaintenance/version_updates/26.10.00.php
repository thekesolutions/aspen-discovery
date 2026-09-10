<?php
/** @noinspection SqlDialectInspection */

/** @noinspection PhpUnused */
function getUpdates26_10_00(): array {
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

		//imani

		//galen

		//chloe
	
		//pedro

		//mark j

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

		//tomas

		// stephen

		//jacob - OpenFifth


	];
}