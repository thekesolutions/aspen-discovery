<?php
require_once __DIR__ . '/../../src/DatabaseTestCase.php';

/**
 * Integration tests for database connectivity and basic operations
 */
class DatabaseConnectionTest extends DatabaseTestCase
{
	public function testDatabaseConnection()
	{
		$db = $GLOBALS['test_db'] ?? null;
		
		if ($db === null) {
			$this->markTestSkipped('Database connection not available for testing');
			return;
		}
		
		$this->assertInstanceOf('PDO', $db, 'Database should be PDO instance');
		
		// Test basic connectivity
		$result = $db->query('SELECT 1 as test_value');
		$this->assertNotFalse($result, 'Should be able to execute basic query');
		
		$row = $result->fetch(PDO::FETCH_ASSOC);
		$this->assertEquals(1, $row['test_value'], 'Query should return expected value');
	}

	public function testBasicCrudOperations()
	{
		$db = $GLOBALS['test_db'] ?? null;
		
		if ($db === null) {
			$this->markTestSkipped('Database not available for CRUD testing');
			return;
		}
		
		// Create a temporary test table
		$tableName = 'test_integration_' . uniqid();
		$createSql = "CREATE TEMPORARY TABLE {$tableName} (
			id INT AUTO_INCREMENT PRIMARY KEY,
			name VARCHAR(100) NOT NULL,
			email VARCHAR(100),
			status ENUM('active', 'inactive') DEFAULT 'active',
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		)";
		
		$db->exec($createSql);
		
		// Test INSERT
		$testData = [
			'name' => 'Test User ' . uniqid(),
			'email' => 'test' . uniqid() . '@example.com',
			'status' => 'active'
		];
		
		$insertSql = "INSERT INTO {$tableName} (name, email, status) VALUES (?, ?, ?)";
		$stmt = $db->prepare($insertSql);
		$insertResult = $stmt->execute(array_values($testData));
		
		$this->assertTrue($insertResult, 'Insert operation should succeed');
		$insertedId = $db->lastInsertId();
		$this->assertGreaterThan(0, $insertedId, 'Should return valid insert ID');
		
		// Test SELECT
		$selectSql = "SELECT * FROM {$tableName} WHERE id = ?";
		$stmt = $db->prepare($selectSql);
		$stmt->execute([$insertedId]);
		$selectedRow = $stmt->fetch(PDO::FETCH_ASSOC);
		
		$this->assertNotFalse($selectedRow, 'Select operation should find the inserted row');
		$this->assertEquals($testData['name'], $selectedRow['name']);
		$this->assertEquals($testData['email'], $selectedRow['email']);
		$this->assertEquals($testData['status'], $selectedRow['status']);
		
		// Test UPDATE
		$newName = 'Updated Test User';
		$updateSql = "UPDATE {$tableName} SET name = ? WHERE id = ?";
		$stmt = $db->prepare($updateSql);
		$updateResult = $stmt->execute([$newName, $insertedId]);
		
		$this->assertTrue($updateResult, 'Update operation should succeed');
		
		// Verify update
		$stmt = $db->prepare($selectSql);
		$stmt->execute([$insertedId]);
		$updatedRow = $stmt->fetch(PDO::FETCH_ASSOC);
		$this->assertEquals($newName, $updatedRow['name']);
		
		// Test DELETE
		$deleteSql = "DELETE FROM {$tableName} WHERE id = ?";
		$stmt = $db->prepare($deleteSql);
		$deleteResult = $stmt->execute([$insertedId]);
		
		$this->assertTrue($deleteResult, 'Delete operation should succeed');
		
		// Verify deletion
		$stmt = $db->prepare($selectSql);
		$stmt->execute([$insertedId]);
		$deletedRow = $stmt->fetch(PDO::FETCH_ASSOC);
		$this->assertFalse($deletedRow, 'Row should be deleted');
	}

	public function testTransactionSupport()
	{
		$db = $GLOBALS['test_db'] ?? null;
		
		if ($db === null) {
			$this->markTestSkipped('Database not available for transaction testing');
			return;
		}
		
		// Create temporary table for transaction testing
		$tableName = 'test_transaction_' . uniqid();
		$createSql = "CREATE TEMPORARY TABLE {$tableName} (
			id INT AUTO_INCREMENT PRIMARY KEY,
			name VARCHAR(100) NOT NULL
		)";
		$db->exec($createSql);
		
		// Start transaction
		$transactionStarted = $db->beginTransaction();
		$this->assertTrue($transactionStarted, 'Transaction should start successfully');
		$this->assertTrue($db->inTransaction(), 'Should be in transaction');
		
		// Insert test data
		$insertSql = "INSERT INTO {$tableName} (name) VALUES (?)";
		$stmt = $db->prepare($insertSql);
		$stmt->execute(['Transaction Test']);
		$insertedId = $db->lastInsertId();
		
		// Verify data exists within transaction
		$selectSql = "SELECT COUNT(*) FROM {$tableName} WHERE id = ?";
		$stmt = $db->prepare($selectSql);
		$stmt->execute([$insertedId]);
		$count = $stmt->fetchColumn();
		$this->assertEquals(1, $count, 'Data should be visible within transaction');
		
		// Rollback transaction
		$rollbackResult = $db->rollback();
		$this->assertTrue($rollbackResult, 'Rollback should succeed');
		$this->assertFalse($db->inTransaction(), 'Should not be in transaction after rollback');
		
		// Verify data was rolled back
		$stmt = $db->prepare($selectSql);
		$stmt->execute([$insertedId]);
		$count = $stmt->fetchColumn();
		$this->assertEquals(0, $count, 'Data should be rolled back');
	}

	public function testMultipleConnections()
	{
		$db = $GLOBALS['test_db'] ?? null;
		
		if ($db === null) {
			$this->markTestSkipped('Database not available for connection testing');
			return;
		}
		
		// Test that we can perform multiple operations
		$queries = [
			'SELECT VERSION() as mysql_version',
			'SELECT NOW() as current_time',
			'SELECT CONNECTION_ID() as connection_id'
		];
		
		foreach ($queries as $query) {
			$result = $db->query($query);
			$this->assertNotFalse($result, "Query should execute: {$query}");
			
			$row = $result->fetch(PDO::FETCH_ASSOC);
			$this->assertNotEmpty($row, "Query should return data: {$query}");
		}
	}

	public function testDatabasePerformance()
	{
		$db = $GLOBALS['test_db'] ?? null;
		
		if ($db === null) {
			$this->markTestSkipped('Database not available for performance testing');
			return;
		}
		
		$startTime = microtime(true);
		
		// Perform multiple simple queries to test performance
		for ($i = 0; $i < 10; $i++) {
			$result = $db->query("SELECT {$i} as iteration");
			$row = $result->fetch(PDO::FETCH_ASSOC);
			$this->assertEquals($i, $row['iteration']);
		}
		
		$queryTime = microtime(true) - $startTime;
		
		$this->assertLessThan(1.0, $queryTime, '10 simple queries should complete within 1 second');
	}

	public function testDatabaseErrorHandling()
	{
		$db = $GLOBALS['test_db'] ?? null;
		
		if ($db === null) {
			$this->markTestSkipped('Database not available for error testing');
			return;
		}
		
		// Test handling of SQL errors
		try {
			$db->query('SELECT * FROM non_existent_table_12345');
			$this->fail('Should throw exception for non-existent table');
		} catch (PDOException $e) {
			$this->assertStringContains('non_existent_table_12345', $e->getMessage());
		}
		
		// Test that connection is still valid after error
		$result = $db->query('SELECT 1 as recovery_test');
		$row = $result->fetch(PDO::FETCH_ASSOC);
		$this->assertEquals(1, $row['recovery_test'], 'Connection should recover from errors');
	}

	public function testMemoryUsage()
	{
		$this->assertMemoryUsageReasonable(20);
	}
}