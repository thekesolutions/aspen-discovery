<?php
/**
 * PHPUnit Bootstrap for Aspen Discovery Testing Framework
 * 
 * This bootstrap provides a minimal testing environment without
 * loading the full Aspen application stack until needed.
 */

// Set test environment variables
$_SERVER['aspen_server'] = 'test_site';
$_SERVER['REQUEST_URI'] = '/test';
$_SERVER['HTTP_HOST'] = 'test.localhost';

// Define test environment constants
define('ROOT_DIR', realpath(__DIR__ . '/../../'));
define('TEST_MODE', true);

// Load testing framework classes
require_once __DIR__ . '/src/AspenTestCase.php';
require_once __DIR__ . '/src/DatabaseTestCase.php';
require_once __DIR__ . '/src/TestDataFactory.php';

// Initialize basic test configuration
$testConfigFile = ROOT_DIR . '/sites/test_site/conf/config.ini';
if (!file_exists($testConfigFile)) {
    // Fallback test configuration
    $testConfig = [
        'Database' => [
            'database_aspen_host' => '127.0.0.1',
            'database_aspen_dbname' => 'aspen_test',
            'database_user' => 'root',
            'database_password' => 'password',
            'database_aspen_dbport' => 3306
        ],
        'Site' => [
            'isProduction' => false,
            'url' => 'http://test.localhost'
        ],
        'Testing' => [
            'environment' => 'test',
            'mock_external_services' => true
        ]
    ];
    
    // Create basic test database connection for tests that need it
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s',
            $testConfig['Database']['database_aspen_host'],
            $testConfig['Database']['database_aspen_dbport'],
            $testConfig['Database']['database_aspen_dbname']
        );
        
        $GLOBALS['test_db'] = new PDO(
            $dsn,
            $testConfig['Database']['database_user'],
            $testConfig['Database']['database_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        // Database connection failed - tests that need DB will handle this
        $GLOBALS['test_db'] = null;
    }
}

echo "PHPUnit Testing Framework Bootstrap Complete\n";