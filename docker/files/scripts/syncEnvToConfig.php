<?php
/**
 * Sync environment variables to existing config files.
 * Environment variables are the only source for the mapped keys: each key is set to its variable's
 * value, or to the default when the variable is unset or empty.
 * Preserves all other configuration.
 *
 * Runs on every container start to apply env var changes.
 */

require_once __DIR__ . '/../logger/DockerLogger.php';
require_once __DIR__ . '/../env/DockerEnv.php';
DockerLogger::init('BACKEND');

$siteName = DockerEnv::get('SITE_NAME');

$configDir = "/usr/local/aspen-discovery/sites/{$siteName}/conf";

if (!is_dir($configDir)) {
	DockerLogger::info("Config directory not found, skipping env sync: {$configDir}");
	exit(0);
}

// Map env vars to ini file locations: [envVar => [file, section, key]]
$envMapping = [
	// Database (config.pwd.ini)
	'DATABASE_HOST'     => ['config.pwd.ini', 'Database', 'database_aspen_host'],
	'DATABASE_PORT'     => ['config.pwd.ini', 'Database', 'database_aspen_dbport'],
	'DATABASE_NAME'     => ['config.pwd.ini', 'Database', 'database_aspen_dbname'],
	'DATABASE_USER'     => ['config.pwd.ini', 'Database', 'database_user'],
	'DATABASE_PASSWORD' => ['config.pwd.ini', 'Database', 'database_password'],

	// Site (config.ini)
	'TZ'                => ['config.ini', 'Site', 'timezone'],
	'URL'               => ['config.ini', 'Site', 'url'],
	'INTERNAL_URL'      => ['config.ini', 'Site', 'internalUrl'],
	'TITLE'             => ['config.ini', 'Site', 'title'],
	'LIBRARY'           => ['config.ini', 'Site', 'libraryName'],
	'FILE_GROUP_OWNER'  => ['config.ini', 'Site', 'fileGroupOwner'],

	// Solr (config.ini)
	'SOLR_HOST'         => ['config.ini', 'Index', 'solrHost'],
	'SOLR_PORT'         => ['config.ini', 'Index', 'solrPort'],

	// System (config.ini)
	'DEBUG'             => ['config.ini', 'System', 'debug'],
];

// Keys whose values are INI booleans and must stay unquoted
$booleanKeys = ['debug'];

// Group changes by file
$fileChanges = [];
$defaultedEnvVars = [];

foreach ($envMapping as $envVar => [$file, $section, $key]) {
	$fileChanges[$file][$section][$key] = DockerEnv::get($envVar);
	if (DockerEnv::usesDefault($envVar)) {
		$defaultedEnvVars[] = $envVar;
	}
}

DockerLogger::info("Syncing environment variables: " . implode(', ', array_keys($envMapping)));
if (!empty($defaultedEnvVars)) {
	DockerLogger::info("Using defaults for: " . implode(', ', $defaultedEnvVars));
}

// Apply changes to each file
foreach ($fileChanges as $file => $sections) {
	$filePath = "{$configDir}/{$file}";
	if (!file_exists($filePath)) {
		DockerLogger::warn("Config file not found: {$filePath}");
		continue;
	}

	$updated = updateIniFile($filePath, $sections, $booleanKeys);
	if ($updated) {
		DockerLogger::info("Updated {$file}");
	}
}

rebuildConnectionStrings($configDir);
DockerLogger::info("Rebuilt database_dsn and database_aspen_jdbc");

DockerLogger::info("Environment sync complete");

// =============================================================================
// Helper Functions
// =============================================================================

/**
 * Update specific values in an INI file while preserving all other content.
 *
 * @param string $filePath Path to the INI file
 * @param array $changes Array of [section => [key => value]] to update
 * @param array $booleanKeys Keys written as INI booleans instead of strings
 * @return bool True if file was modified
 */
function updateIniFile(string $filePath, array $changes, array $booleanKeys = []): bool {
	$lines = file($filePath, FILE_IGNORE_NEW_LINES);
	$currentSection = null;
	$modified = false;

	foreach ($lines as $i => $line) {
		$trimmed = trim($line);

		// Track current section
		if (preg_match('/^\[(.+)\]$/', $trimmed, $m)) {
			$currentSection = $m[1];
			continue;
		}

		// Skip comments and empty lines
		if ($trimmed === '' || (isset($trimmed[0]) && $trimmed[0] === ';')) {
			continue;
		}

		// Check if this line should be updated
		if ($currentSection && isset($changes[$currentSection])) {
			foreach ($changes[$currentSection] as $key => $newValue) {
				// Match key at start of line (handles various spacing)
				if (preg_match('/^' . preg_quote($key, '/') . '\s*=/', $trimmed)) {
					$formatted = formatIniValue($newValue, in_array($key, $booleanKeys));

					// Preserve original indentation
					$indent = '';
					if (preg_match('/^(\s*)/', $line, $indentMatch)) {
						$indent = $indentMatch[1];
					}

					$lines[$i] = "{$indent}{$key} = {$formatted}";
					$modified = true;
					unset($changes[$currentSection][$key]);
				}
			}
		}
	}

	// Add keys that weren't found, right after their section header
	foreach ($changes as $section => $remaining) {
		if (empty($remaining)) {
			continue;
		}
		foreach ($lines as $i => $line) {
			if (trim($line) === "[{$section}]") {
				$newLines = [];
				foreach ($remaining as $key => $newValue) {
					$newLines[] = "{$key} = " . formatIniValue($newValue, in_array($key, $booleanKeys));
				}
				array_splice($lines, $i + 1, 0, $newLines);
				$modified = true;
				break;
			}
		}
	}

	if ($modified) {
		file_put_contents($filePath, implode("\n", $lines) . "\n");
	}

	return $modified;
}

/**
 * Quote a value if it contains characters that are special in INI files,
 * escaping the ones PHP's INI parser still interprets inside double quotes.
 * Words like yes/no/none are also quoted, since PHP would otherwise turn them
 * into "1" or "", unless the key is a boolean.
 */
function formatIniValue(string $value, bool $isBoolean = false): string {
	$reservedWords = ['true', 'false', 'on', 'off', 'yes', 'no', 'none', 'null'];
	$isReserved = in_array(strtolower($value), $reservedWords);
	if (preg_match('~^[\w.:/@+,-]*$~', $value) && ($isBoolean || !$isReserved)) {
		return $value;
	}
	return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
}

/**
 * Rebuild database_dsn (PHP) and database_aspen_jdbc (Java) from environment variables or their defaults.
 *
 * @param string $configDir Path to the config directory
 */
function rebuildConnectionStrings(string $configDir): void {
	$pwdFile = "{$configDir}/config.pwd.ini";

	if (!file_exists($pwdFile)) {
		return;
	}

	$host = DockerEnv::get('DATABASE_HOST');
	$port = DockerEnv::get('DATABASE_PORT');
	$name = DockerEnv::get('DATABASE_NAME');
	$user = DockerEnv::get('DATABASE_USER');
	$password = DockerEnv::get('DATABASE_PASSWORD');
	$timezone = DockerEnv::get('TZ');

	$dsn = "mysql:host={$host};port={$port};dbname={$name}";

	// Percent-encode the parameters: Connector/J decodes them, and it keeps characters like ; and "
	// out of the INI value, which Java's ConfigUtil.cleanIniValue() would otherwise truncate or strip
	$jdbc = "jdbc:mysql://{$host}:{$port}/" . rawurlencode($name)
		. '?user=' . rawurlencode($user)
		. '&password=' . rawurlencode($password)
		. '&useUnicode=yes&characterEncoding=UTF-8'
		. '&serverTimezone=' . rawurlencode($timezone);

	updateIniFile($pwdFile, ['Database' => ['database_dsn' => $dsn, 'database_aspen_jdbc' => $jdbc]]);
}
