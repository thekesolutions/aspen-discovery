<?php

/**
 * Reads the environment variables used by the Docker scripts, falling back to a default
 * when a variable is unset or empty, so a stack can start with a minimal .env file.
 */
class DockerEnv {
	// Also defaulted elsewhere, keep in sync: SITE_NAME in entrypoint.sh and docker-compose.yml,
	// DATABASE_NAME/USER/PASSWORD in docker-compose.yml's db service.
	const DEFAULTS = [
		'SITE_NAME' => 'aspen',
		'URL' => 'http://localhost:85',
		'TITLE' => 'Aspen Discovery',
		'LIBRARY' => 'Test Library',
		'SUPPORTING_COMPANY' => 'ByWater Solutions',
		'ASPEN_ADMIN_PASSWORD' => 'aspenPass123',
		'TZ' => 'UTC',
		'SOLR_HOST' => 'solr',
		'SOLR_PORT' => '8983',
		'PHP_FPM_HOST' => 'backend',
		'PHP_FPM_PORT' => '9000',
		'DATABASE_HOST' => 'db',
		'DATABASE_PORT' => '3306',
		'DATABASE_NAME' => 'aspen',
		'DATABASE_USER' => 'aspenusr',
		'DATABASE_PASSWORD' => 'aspenpasswd',
		'FILE_GROUP_OWNER' => 'www-data',
		'DEBUG' => 'false',
	];

	/**
	 * Get a variable's value, or its default ('' if it has none) when unset or empty.
	 */
	public static function get(string $name): string {
		$value = getenv($name);
		if ($value === false || $value === '') {
			return self::DEFAULTS[$name] ?? '';
		}
		return $value;
	}

	/**
	 * Whether get() would return the default because the variable is unset or empty.
	 */
	public static function usesDefault(string $name): bool {
		$value = getenv($name);
		return ($value === false || $value === '') && isset(self::DEFAULTS[$name]);
	}
}
