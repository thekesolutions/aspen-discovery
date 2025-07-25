<?php
require_once __DIR__ . '/../../src/AspenTestCase.php';

/**
 * Functional tests for complete workflow scenarios
 */
class ApiWorkflowTest extends AspenTestCase
{
	public function testCompleteUserWorkflow()
	{
		// Test a complete user workflow simulation
		$userData = TestDataFactory::createUser([
			'username' => 'workflow_user_' . uniqid(),
			'email' => 'workflow@example.com',
			'status' => 'active'
		]);
		
		$this->assertIsArray($userData, 'User data should be created');
		$this->assertEquals('active', $userData['status'], 'User should be active');
		
		// Simulate user authentication workflow
		$authResult = $this->simulateAuthentication($userData);
		$this->assertTrue($authResult['success'], 'Authentication should succeed');
		
		// Simulate search workflow
		$searchResult = $this->simulateSearch('test query');
		$this->assertIsArray($searchResult['results'], 'Search should return results');
		$this->assertGreaterThanOrEqual(0, $searchResult['total'], 'Total should be non-negative');
	}

	public function testApiResponseWorkflow()
	{
		// Test API response creation and validation
		$apiResponse = TestDataFactory::createApiResponse(
			['message' => 'Test successful'],
			true,
			'Test operation completed'
		);
		
		$this->assertIsArray($apiResponse, 'API response should be array');
		$this->assertTrue($apiResponse['success'], 'API response should indicate success');
		$this->assertEquals('Test operation completed', $apiResponse['message'], 'Message should match');
		
		// Test error response
		$errorResponse = TestDataFactory::createApiResponse(
			['error' => 'Test error'],
			false,
			'Test operation failed'
		);
		
		$this->assertFalse($errorResponse['success'], 'Error response should indicate failure');
		$this->assertEquals('Test operation failed', $errorResponse['message'], 'Error message should match');
	}

	public function testDatabaseWorkflow()
	{
		$db = $GLOBALS['test_db'] ?? null;
		
		if ($db === null) {
			$this->markTestSkipped('Database not available for workflow testing');
			return;
		}
		
		// Test complete database workflow
		$tableName = 'test_workflow_' . uniqid();
		
		try {
			// Create test table
			$createSQL = "CREATE TEMPORARY TABLE {$tableName} (
				id INT AUTO_INCREMENT PRIMARY KEY,
				name VARCHAR(100) NOT NULL,
				status VARCHAR(20) DEFAULT 'active',
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)";
			$db->exec($createSQL);
			
			// Insert test data
			$insertSQL = "INSERT INTO {$tableName} (name, status) VALUES (?, ?)";
			$stmt = $db->prepare($insertSQL);
			
			$testData = [
				['Test Item 1', 'active'],
				['Test Item 2', 'inactive'],
				['Test Item 3', 'active']
			];
			
			foreach ($testData as $item) {
				$stmt->execute($item);
			}
			
			// Query and verify data
			$selectSQL = "SELECT * FROM {$tableName} WHERE status = 'active'";
			$result = $db->query($selectSQL);
			$activeItems = $result->fetchAll(PDO::FETCH_ASSOC);
			
			$this->assertCount(2, $activeItems, 'Should have 2 active items');
			$this->assertEquals('Test Item 1', $activeItems[0]['name'], 'First item name should match');
			
			// Update workflow
			$updateSQL = "UPDATE {$tableName} SET status = 'archived' WHERE name = ?";
			$stmt = $db->prepare($updateSQL);
			$stmt->execute(['Test Item 1']);
			
			// Verify update
			$result = $db->query($selectSQL);
			$activeItems = $result->fetchAll(PDO::FETCH_ASSOC);
			$this->assertCount(1, $activeItems, 'Should have 1 active item after update');
			
			// Delete workflow
			$deleteSQL = "DELETE FROM {$tableName} WHERE status = 'archived'";
			$db->exec($deleteSQL);
			
			// Verify deletion
			$countSQL = "SELECT COUNT(*) as total FROM {$tableName}";
			$result = $db->query($countSQL);
			$count = $result->fetch(PDO::FETCH_ASSOC);
			$this->assertEquals(2, $count['total'], 'Should have 2 items after deletion');
			
		} finally {
			// Cleanup is automatic for temporary tables
		}
	}

	public function testPerformanceWorkflow()
	{
		$startTime = microtime(true);
		$startMemory = memory_get_usage(true);
		
		// Simulate processing multiple items
		$items = [];
		for ($i = 0; $i < 1000; $i++) {
			$items[] = TestDataFactory::createUser([
				'username' => 'perf_user_' . $i,
				'email' => "user{$i}@example.com"
			]);
		}
		
		// Process the items
		$processedCount = 0;
		foreach ($items as $item) {
			if ($item['username']) {
				$processedCount++;
			}
		}
		
		$endTime = microtime(true);
		$endMemory = memory_get_usage(true);
		
		$executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
		$memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB
		
		$this->assertEquals(1000, $processedCount, 'Should process all 1000 items');
		$this->assertLessThan(1000, $executionTime, 'Should complete within 1 second');
		$this->assertLessThan(50, $memoryUsed, 'Should use less than 50MB additional memory');
	}

	public function testErrorHandlingWorkflow()
	{
		// Test graceful error handling in workflows
		
		// Test with invalid data
		$invalidUser = TestDataFactory::createUser(['username' => '']);
		$this->assertEmpty($invalidUser['username'], 'Invalid user should have empty username');
		
		// Test with database errors (if database available)
		$db = $GLOBALS['test_db'] ?? null;
		if ($db !== null) {
			try {
				// Attempt invalid SQL
				$db->query('SELECT * FROM non_existent_table');
				$this->fail('Should throw exception for invalid SQL');
			} catch (PDOException $e) {
				$this->assertStringContains('non_existent_table', $e->getMessage(), 'Should get table error');
			}
		}
		
		// Test memory management
		$this->assertMemoryUsageReasonable(25);
	}

	public function testIntegrationWorkflow()
	{
		// Test integration between different components
		$user = TestDataFactory::createUser(['role' => 'admin']);
		$searchResult = TestDataFactory::createSearchResult(['query' => 'integration test']);
		$apiResponse = TestDataFactory::createApiResponse($searchResult, true, 'Search completed');
		
		// Verify integration
		$this->assertEquals('admin', $user['role'], 'User role should be preserved');
		$this->assertEquals('integration test', $searchResult['query'], 'Search query should be preserved');
		$this->assertTrue($apiResponse['success'], 'API response should indicate success');
		$this->assertArrayHasKey('query', $apiResponse['data'], 'API response should contain search data');
	}

	// Helper methods for workflow simulation
	private function simulateAuthentication($userData): array
	{
		return [
			'success' => !empty($userData['username']),
			'user_id' => $userData['id'] ?? 1,
			'message' => 'Authentication completed'
		];
	}

	private function simulateSearch($query): array
	{
		return [
			'results' => [
				['title' => 'Result 1', 'relevance' => 0.95],
				['title' => 'Result 2', 'relevance' => 0.87]
			],
			'total' => 2,
			'query' => $query,
			'execution_time' => 0.05
		];
	}
}