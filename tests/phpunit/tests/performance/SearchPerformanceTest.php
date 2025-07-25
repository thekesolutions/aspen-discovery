<?php
require_once __DIR__ . '/../../src/AspenTestCase.php';

/**
 * Performance tests for general system operations
 */
class SearchPerformanceTest extends AspenTestCase
{
	public function testDataProcessingPerformance()
	{
		$startTime = microtime(true);
		$startMemory = memory_get_usage(true);
		
		// Simulate processing a large dataset
		$searchResults = [];
		for ($i = 0; $i < 1000; $i++) {
			$searchResults[] = TestDataFactory::createSearchResult([
				'title' => "Search Result #{$i}",
				'relevance' => rand(50, 100) / 100,
				'id' => $i
			]);
		}
		
		// Process and filter results
		$filteredResults = array_filter($searchResults, function($result) {
			return $result['relevance'] > 0.7;
		});
		
		// Sort by relevance
		usort($filteredResults, function($a, $b) {
			return $b['relevance'] <=> $a['relevance'];
		});
		
		$endTime = microtime(true);
		$endMemory = memory_get_usage(true);
		
		$executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
		$memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB
		
		// Performance assertions
		$this->assertLessThan(500, $executionTime, 'Data processing should complete within 500ms');
		$this->assertLessThan(25, $memoryUsed, 'Should use less than 25MB for processing 1000 items');
		$this->assertGreaterThan(0, count($filteredResults), 'Should have some high-relevance results');
		
		// Verify first result has highest relevance
		if (count($filteredResults) > 1) {
			$this->assertGreaterThanOrEqual(
				$filteredResults[1]['relevance'], 
				$filteredResults[0]['relevance'],
				'Results should be sorted by relevance'
			);
		}
	}

	public function testApiResponsePerformance()
	{
		$times = [];
		
		// Test API response generation performance
		for ($i = 0; $i < 100; $i++) {
			$startTime = microtime(true);
			
			$apiResponse = TestDataFactory::createApiResponse(
				['results' => range(1, 10), 'total' => 10],
				true,
				'Search completed successfully'
			);
			
			$endTime = microtime(true);
			$times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
			
			// Verify response structure
			$this->assertIsArray($apiResponse);
			$this->assertTrue($apiResponse['success']);
		}
		
		$averageTime = array_sum($times) / count($times);
		$maxTime = max($times);
		
		$this->assertLessThan(5, $averageTime, 'Average API response time should be under 5ms');
		$this->assertLessThan(20, $maxTime, 'Maximum API response time should be under 20ms');
	}

	public function testDatabasePerformance()
	{
		$db = $GLOBALS['test_db'] ?? null;
		
		if ($db === null) {
			$this->markTestSkipped('Database not available for performance testing');
			return;
		}
		
		// Create temporary table for performance testing
		$tableName = 'test_perf_' . uniqid();
		$createSql = "CREATE TEMPORARY TABLE {$tableName} (
			id INT AUTO_INCREMENT PRIMARY KEY,
			title VARCHAR(255),
			content TEXT,
			score DECIMAL(5,2),
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_score (score),
			INDEX idx_title (title)
		)";
		
		$db->exec($createSql);
		
		// Measure bulk insert performance
		$insertStartTime = microtime(true);
		
		$insertSql = "INSERT INTO {$tableName} (title, content, score) VALUES (?, ?, ?)";
		$stmt = $db->prepare($insertSql);
		
		for ($i = 0; $i < 1000; $i++) {
			$stmt->execute([
				"Title {$i}",
				"Content for item {$i} with some descriptive text to simulate real data",
				rand(1, 100) / 10
			]);
		}
		
		$insertEndTime = microtime(true);
		$insertTime = ($insertEndTime - $insertStartTime) * 1000;
		
		// Measure query performance
		$queryStartTime = microtime(true);
		
		$result = $db->query("SELECT COUNT(*) as total FROM {$tableName} WHERE score > 5.0");
		$count = $result->fetch(PDO::FETCH_ASSOC);
		
		$queryEndTime = microtime(true);
		$queryTime = ($queryEndTime - $queryStartTime) * 1000;
		
		// Performance assertions
		$this->assertLessThan(2000, $insertTime, '1000 inserts should complete within 2 seconds');
		$this->assertLessThan(100, $queryTime, 'Query should complete within 100ms');
		$this->assertGreaterThan(0, $count['total'], 'Should find some records with score > 5.0');
	}

	public function testMemoryEfficiency()
	{
		$baselineMemory = memory_get_usage(true);
		
		// Create and process large amounts of data
		$data = [];
		for ($i = 0; $i < 5000; $i++) {
			$data[] = TestDataFactory::createUser([
				'username' => "user_{$i}",
				'email' => "user{$i}@example.com"
			]);
		}
		
		$peakMemory = memory_get_usage(true);
		$memoryUsed = ($peakMemory - $baselineMemory) / 1024 / 1024; // MB
		
		// Process the data to simulate real usage
		$activeUsers = array_filter($data, function($user) {
			return !empty($user['username']);
		});
		
		$processedCount = count($activeUsers);
		
		// Clean up
		unset($data);
		unset($activeUsers);
		
		$finalMemory = memory_get_usage(true);
		$memoryLeaked = ($finalMemory - $baselineMemory) / 1024 / 1024; // MB
		
		// Memory efficiency assertions
		$this->assertEquals(5000, $processedCount, 'Should process all 5000 users');
		$this->assertLessThan(100, $memoryUsed, 'Should use less than 100MB for 5000 users');
		$this->assertLessThan(10, $memoryLeaked, 'Should have minimal memory leakage after cleanup');
	}

	public function testConcurrentOperations()
	{
		// Simulate concurrent operations
		$startTime = microtime(true);
		
		$operations = [];
		for ($i = 0; $i < 50; $i++) {
			$operationStart = microtime(true);
			
			// Simulate multiple concurrent operations
			$user = TestDataFactory::createUser(['id' => $i]);
			$search = TestDataFactory::createSearchResult(['query' => "test {$i}"]);
			$api = TestDataFactory::createApiResponse($search, true, 'Success');
			
			$operationEnd = microtime(true);
			$operations[] = ($operationEnd - $operationStart) * 1000;
		}
		
		$totalTime = (microtime(true) - $startTime) * 1000;
		$averageOperationTime = array_sum($operations) / count($operations);
		
		$this->assertLessThan(1000, $totalTime, '50 concurrent operations should complete within 1 second');
		$this->assertLessThan(20, $averageOperationTime, 'Average operation time should be under 20ms');
	}

	public function testOverallMemoryUsage()
	{
		$this->assertMemoryUsageReasonable(30);
	}
}