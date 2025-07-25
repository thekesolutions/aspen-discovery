<?php
require_once __DIR__ . '/../../src/AspenTestCase.php';

/**
 * Unit tests for basic testing framework functionality
 */
class SystemVariablesTest extends AspenTestCase
{
	public function testFrameworkBasics()
	{
		// Test that PHPUnit is working
		$this->assertTrue(true, 'PHPUnit is functional');
		
		// Test basic PHP functionality
		$this->assertEquals(4, 2 + 2, 'Basic arithmetic works');
		
		// Test string operations
		$testString = 'Aspen Discovery Testing Framework';
		$this->assertStringContains('Testing', $testString, 'String operations work');
	}

	public function testTestDataFactory()
	{
		// Test our test data factory
		$userData = TestDataFactory::createUser([
			'username' => 'test_user_' . uniqid(),
			'email' => 'test@example.com'
		]);
		
		$this->assertIsArray($userData, 'TestDataFactory creates arrays');
		$this->assertArrayHasKey('username', $userData, 'User data has username');
		$this->assertArrayHasKey('email', $userData, 'User data has email');
		$this->assertEquals('test@example.com', $userData['email'], 'Email is set correctly');
	}

	public function testMemoryMonitoring()
	{
		$initialMemory = memory_get_usage(true);
		
		// Create some test data to use memory
		$largeArray = array_fill(0, 1000, 'test data string');
		
		$finalMemory = memory_get_usage(true);
		
		// Memory should have increased
		$this->assertGreaterThan($initialMemory, $finalMemory, 'Memory usage increased as expected');
		
		// Test memory monitoring utility
		$this->assertMemoryUsageReasonable(50); // Max 50MB increase
		
		// Clean up
		unset($largeArray);
	}

	public function testPerformanceTiming()
	{
		$startTime = microtime(true);
		
		// Simulate some work
		for ($i = 0; $i < 10000; $i++) {
			$temp = sqrt($i);
		}
		
		$endTime = microtime(true);
		$executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
		
		// Should complete quickly
		$this->assertLessThan(1000, $executionTime, 'Performance test completed in under 1 second');
	}

	public function testDatabaseConnectionAvailable()
	{
		// Test if database connection is available
		$db = $GLOBALS['test_db'] ?? null;
		
		if ($db !== null) {
			$this->assertInstanceOf(PDO::class, $db, 'Database connection is PDO instance');
			
			// Test basic query
			$result = $db->query('SELECT 1 as test_value');
			$row = $result->fetch(PDO::FETCH_ASSOC);
			$this->assertEquals(1, $row['test_value'], 'Database query works');
		} else {
			$this->markTestSkipped('Database connection not available in test environment');
		}
	}

	public function testTestEnvironmentVariables()
	{
		// Verify test environment is properly set up
		$this->assertTrue(defined('TEST_MODE'), 'TEST_MODE constant is defined');
		$this->assertTrue(defined('ROOT_DIR'), 'ROOT_DIR constant is defined');
		
		// Check server variables
		$this->assertEquals('test_site', $_SERVER['aspen_server'], 'Test server name is set');
		$this->assertEquals('test.localhost', $_SERVER['HTTP_HOST'], 'Test host is set');
	}
}