<?php

require_once __DIR__ . '/../logger/DockerLogger.php';
require_once __DIR__ . '/../env/DockerEnv.php';
DockerLogger::init('BACKEND');

// Initialize database
$aspenAdminPassword = DockerEnv::get('ASPEN_ADMIN_PASSWORD');
$supportingCompany = DockerEnv::get('SUPPORTING_COMPANY');
$databaseHost = DockerEnv::get('DATABASE_HOST');
$databasePort = DockerEnv::get('DATABASE_PORT');
$databaseName = DockerEnv::get('DATABASE_NAME');
$databaseUser = DockerEnv::get('DATABASE_USER');
$databasePassword = DockerEnv::get('DATABASE_PASSWORD');

$databaseDsn = "mysql:host=$databaseHost;port=$databasePort;dbname=$databaseName";

$mysqlConnectionCommand = "mariadb -u$databaseUser -p$databasePassword -h$databaseHost";
if ($databasePort != "3306") {
	$mysqlConnectionCommand .= " --port=$databasePort";
}
if ((getenv('DATABASE_SSL_MODE') ?: 'DISABLED') === 'DISABLED') {
	$mysqlConnectionCommand .= " --skip-ssl";
}

DockerLogger::info("Checking database connection to: {$databaseHost}:{$databasePort}");

//Check if aspen database has already been initialized
$maxTries = 5;
$tries = 0;
$databaseIsDown = true;

while ($databaseIsDown) {
	try {
		$statement = 'SELECT libraryId FROM library LIMIT 1;';
		$aspenDatabase = new PDO($databaseDsn, $databaseUser, $databasePassword);
		$updateUserStmt = $aspenDatabase->prepare($statement);
		$databaseIsDown = false;
	} catch (PDOException $e) {
		$tries++;
		if ($tries >= $maxTries) {
			DockerLogger::error("Database connection failed after {$maxTries} attempts: " . $e->getMessage());
		}
		DockerLogger::warn("Database not ready, retrying... (attempt {$tries}/{$maxTries})");
		sleep(5);
	}
}

try {
	$updateUserStmt->execute();
	DockerLogger::info("Aspen database has already been initialized");
	exit(0);
} catch (PDOException $e) {
	DockerLogger::info("Database is empty, initializing...");
}

//Load default database
$aspenDir = '/usr/local/aspen-discovery/';
DockerLogger::info("Loading default database schema");
exec("$mysqlConnectionCommand $databaseName < $aspenDir/install/aspen.sql", $output, $errorCode);
if ($errorCode != 0) {
	DockerLogger::error("Database '{$databaseName}' could not be loaded");
}
DockerLogger::info("Default database schema loaded successfully");

// Connect to the database
$aspenDatabase = new PDO($databaseDsn, $databaseUser, $databasePassword);
$updateUserStmt = $aspenDatabase->prepare("UPDATE user set cat_password=" . $aspenDatabase->quote($aspenAdminPassword) . ", password=" . $aspenDatabase->quote($aspenAdminPassword) . " where username = 'aspen_admin'");
$updateUserStmt->execute();
if (DockerEnv::usesDefault('ASPEN_ADMIN_PASSWORD')) {
	DockerLogger::warn("ASPEN_ADMIN_PASSWORD is not set, aspen_admin was given the default password; change it before going live");
}

// Assign supportingCompany in the database
$postSupportingCompanyStmt = $aspenDatabase->prepare("UPDATE system_variables set supportingCompany=" . $aspenDatabase->quote($supportingCompany));
$postSupportingCompanyStmt->execute();

DockerLogger::info("Database initialization completed successfully");

// Close connection 
$aspenDatabase = null;
?>
