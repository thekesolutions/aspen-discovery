<?php

require_once __DIR__ . '/../logger/DockerLogger.php';
require_once __DIR__ . '/../env/DockerEnv.php';
DockerLogger::init('BACKEND');

// =============================================================================
// Argument Validation
// =============================================================================

if (count($argv) < 2) {
	DockerLogger::error("Configuration directory required as argument");
} elseif (count($argv) > 2) {
	DockerLogger::error("Too many arguments provided");
}

$siteDir = $argv[1];

if (!file_exists($siteDir)) {
	DockerLogger::error("Directory does not exist: {$siteDir}");
}

if (!is_dir($siteDir)) {
	DockerLogger::error("Path is not a directory: {$siteDir}");
}

DockerLogger::info("Creating configuration files for site directory: {$siteDir}");

// =============================================================================
// Environment Variables Collection
// =============================================================================

$variables = [
	//ASPEN
	'sitename' => DockerEnv::get('SITE_NAME'),
	'servername' => preg_replace('~https?://~', '', DockerEnv::get('URL')),
	'supportingCompany' => DockerEnv::get('SUPPORTING_COMPANY'),
	'library' => DockerEnv::get('LIBRARY'),
	'title' => DockerEnv::get('TITLE'),
	'url' => DockerEnv::get('URL'),
	'configDir' => $siteDir,
	'solrHost' => DockerEnv::get('SOLR_HOST'),
	'solrPort' => DockerEnv::get('SOLR_PORT'),
	'phpFpmHost' => DockerEnv::get('PHP_FPM_HOST'),
	'phpFpmPort' => DockerEnv::get('PHP_FPM_PORT'),
	'timezone' => DockerEnv::get('TZ'),
	'aspenAdminPassword' => DockerEnv::get('ASPEN_ADMIN_PASSWORD'),
	'databaseHost' => DockerEnv::get('DATABASE_HOST'),
	'databasePort' => DockerEnv::get('DATABASE_PORT'),
	'databaseName' => DockerEnv::get('DATABASE_NAME'),
	'databaseUser' => DockerEnv::get('DATABASE_USER'),
	'databasePassword' => DockerEnv::get('DATABASE_PASSWORD'),
	'fileGroupOwner' => DockerEnv::get('FILE_GROUP_OWNER'),
];

if (DockerEnv::usesDefault('DATABASE_PASSWORD')) {
	DockerLogger::warn("DATABASE_PASSWORD is not set, using the default database password");
}

// =============================================================================
// Directory Validation
// =============================================================================

$requiredDirs = [
	'templateDir' => "/usr/local/aspen-discovery/sites/template.linux",
	'defaultDir' => "/usr/local/aspen-discovery/sites/default",
	'dockerDir' => "/usr/local/aspen-discovery/docker"
];

foreach ($requiredDirs as $name => $dir) {
	if (!file_exists($dir)) {
		DockerLogger::error("Required directory does not exist: {$dir}");
	}
	if (!is_dir($dir)) {
		DockerLogger::error("Path is not a directory: {$dir}");
	}
}

DockerLogger::info("Required directories validated successfully");

// =============================================================================
// Configuration File Creation
// =============================================================================

set_error_handler("customErrorHandler");

try {
	DockerLogger::info("Copying configuration templates");
	
	// Copy from template and replace variables
	copy($requiredDirs['templateDir'] . '/httpd-{sitename}.conf', "$siteDir/httpd-{$variables['sitename']}.conf");
	recursive_copy($requiredDirs['templateDir'] . '/conf', $siteDir . '/conf');
	rename($siteDir . '/conf/config.pwd.ini.template', $siteDir . "/conf/config.pwd.ini");

	DockerLogger::info("Replacing template variables");
	
	// Replace variables in configuration files
	$configFiles = [
		"$siteDir/httpd-{$variables['sitename']}.conf",
		"$siteDir/conf/config.ini",
		"$siteDir/conf/config.cron.ini", 
		"$siteDir/conf/config.pwd.ini",
		"$siteDir/conf/crontab_settings.txt"
	];
	
	foreach ($configFiles as $file) {
		replaceVariables($file, $variables);
	}

	DockerLogger::info("Copying additional configuration files");
	
	// Copy from default site directory
	copy($requiredDirs['defaultDir'] . "/conf/badBotsLocal.conf", $siteDir . "/conf/badBotsLocal.conf");
	copy($requiredDirs['defaultDir'] . "/conf/badBotsDefault.conf", $siteDir . "/conf/badBotsDefault.conf");

	// Copy from docker directory
	copy($requiredDirs['dockerDir'] . "/files/php_fpm/php-fpm.conf", $siteDir . "/conf/php-fpm.conf");

	// Replace variables in docker files
	replaceVariables($siteDir . "/conf/php-fpm.conf", $variables);

	// Create temp directory
	if (!file_exists('/tmp')) {
		mkdir('/tmp');
	}

} catch (ErrorException $e) {
	DockerLogger::error("Configuration creation failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}

DockerLogger::info("Configuration files created successfully");

// =============================================================================
// Helper Functions
// =============================================================================

function customErrorHandler(int $errno, string $errstr, string $errfile, int $errline): void {
	if (!(error_reporting() & $errno)) {
		return;
	}
	if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
		return;
	}
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

function replaceVariables($filename, $variables): void {
	$contents = file($filename);
	$fHnd = fopen($filename, 'w');
	foreach ($contents as $line) {
		foreach ($variables as $name => $value) {
			$line = str_replace('{' . $name . '}', $value, $line);
		}
		fwrite($fHnd, $line);
	}
	fclose($fHnd);
}

function recursive_copy($src, $dst): void {
	$dir = opendir($src);
	@mkdir($dst);
	while (($file = readdir($dir))) {
		if (($file != '.') && ($file != '..')) {
			if (is_dir($src . '/' . $file)) {
				recursive_copy($src . '/' . $file, $dst . '/' . $file);
			} else {
				copy($src . '/' . $file, $dst . '/' . $file);
			}
		}
	}
	closedir($dir);
}
?>
