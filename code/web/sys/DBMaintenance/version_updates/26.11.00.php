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

		//tomas

		// stephen

		//jacob - OpenFifth


	];
}