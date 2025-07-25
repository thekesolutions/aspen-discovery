<?php

require_once __DIR__ . '/AspenTestCase.php';

/**
 * Base test case for tests that interact with the database
 * Provides database setup, cleanup, and assertion utilities
 */
abstract class DatabaseTestCase extends AspenTestCase
{
	protected static $dbBackup = null;
	protected $createdRecords = [];

	/**
	 * Set up database test environment
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->setupTestDatabase();
	}

	/**
	 * Clean up database changes after each test
	 */
	protected function tearDown(): void
	{
		$this->cleanupDatabaseChanges();
		parent::tearDown();
	}

	/**
	 * Setup test database state
	 */
	protected function setupTestDatabase(): void
	{
		global $aspen_db;
		
		if ($aspen_db === null) {
			$this->fail('Database connection not available for testing');
		}

		// Begin transaction for isolation
		$aspen_db->beginTransaction();
	}

	/**
	 * Cleanup database changes made during test
	 */
	protected function cleanupDatabaseChanges(): void
	{
		global $aspen_db;
		
		// Clean up any records we created
		foreach (array_reverse($this->createdRecords) as $record) {
			if (is_object($record) && method_exists($record, 'delete')) {
				$record->delete();
			}
		}
		$this->createdRecords = [];

		// Rollback transaction to restore original state
		if ($aspen_db && $aspen_db->inTransaction()) {
			$aspen_db->rollback();
		}
	}

	/**
	 * Track a database record for cleanup
	 */
	protected function trackRecordForCleanup($record): void
	{
		$this->createdRecords[] = $record;
	}

	/**
	 * Assert that a database table contains a record matching conditions
	 */
	protected function assertDatabaseHas(string $table, array $conditions): void
	{
		global $aspen_db;
		
		$whereClauses = [];
		$params = [];
		
		foreach ($conditions as $column => $value) {
			$whereClauses[] = "{$column} = ?";
			$params[] = $value;
		}
		
		$whereClause = implode(' AND ', $whereClauses);
		$sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$whereClause}";
		
		$stmt = $aspen_db->prepare($sql);
		$stmt->execute($params);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		
		$this->assertGreaterThan(
			0,
			$result['count'],
			"Database table '{$table}' does not contain a record matching the given conditions"
		);
	}

	/**
	 * Assert that a database table does not contain a record matching conditions
	 */
	protected function assertDatabaseMissing(string $table, array $conditions): void
	{
		global $aspen_db;
		
		$whereClauses = [];
		$params = [];
		
		foreach ($conditions as $column => $value) {
			$whereClauses[] = "{$column} = ?";
			$params[] = $value;
		}
		
		$whereClause = implode(' AND ', $whereClauses);
		$sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$whereClause}";
		
		$stmt = $aspen_db->prepare($sql);
		$stmt->execute($params);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		
		$this->assertEquals(
			0,
			$result['count'],
			"Database table '{$table}' contains a record matching the given conditions when it shouldn't"
		);
	}

	/**
	 * Get count of records in a table matching conditions
	 */
	protected function getDatabaseCount(string $table, array $conditions = []): int
	{
		global $aspen_db;
		
		if (empty($conditions)) {
			$sql = "SELECT COUNT(*) as count FROM {$table}";
			$stmt = $aspen_db->prepare($sql);
			$stmt->execute();
		} else {
			$whereClauses = [];
			$params = [];
			
			foreach ($conditions as $column => $value) {
				$whereClauses[] = "{$column} = ?";
				$params[] = $value;
			}
			
			$whereClause = implode(' AND ', $whereClauses);
			$sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$whereClause}";
			
			$stmt = $aspen_db->prepare($sql);
			$stmt->execute($params);
		}
		
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		return (int)$result['count'];
	}

	/**
	 * Execute raw SQL for test setup (use sparingly)
	 */
	protected function executeTestSql(string $sql, array $params = []): void
	{
		global $aspen_db;
		
		$stmt = $aspen_db->prepare($sql);
		$result = $stmt->execute($params);
		
		$this->assertTrue($result, "Failed to execute test SQL: {$sql}");
	}

	/**
	 * Create a test user with specified attributes
	 */
	protected function createTestUser(array $attributes = []): User
	{
		require_once ROOT_DIR . '/sys/Account/User.php';
		
		$user = new User();
		$user->username = $attributes['username'] ?? 'test_user_' . uniqid();
		$user->displayName = $attributes['displayName'] ?? 'Test User';
		$user->email = $attributes['email'] ?? 'test@example.com';
		$user->password = $attributes['password'] ?? password_hash('testpass', PASSWORD_DEFAULT);
		$user->created = $attributes['created'] ?? date('Y-m-d H:i:s');
		
		// Set additional attributes
		foreach ($attributes as $property => $value) {
			if (property_exists($user, $property)) {
				$user->$property = $value;
			}
		}
		
		$result = $user->insert();
		$this->assertNotEmpty($result, 'Failed to create test user');
		
		$this->trackRecordForCleanup($user);
		return $user;
	}

	/**
	 * Create a test library with specified attributes
	 */
	protected function createTestLibrary(array $attributes = []): Library
	{
		require_once ROOT_DIR . '/sys/LibraryLocation/Library.php';
		
		$library = new Library();
		$library->subdomain = $attributes['subdomain'] ?? 'test_library_' . uniqid();
		$library->displayName = $attributes['displayName'] ?? 'Test Library';
		$library->libraryId = $attributes['libraryId'] ?? -1;
		
		// Set additional attributes
		foreach ($attributes as $property => $value) {
			if (property_exists($library, $property)) {
				$library->$property = $value;
			}
		}
		
		$result = $library->insert();
		$this->assertNotEmpty($result, 'Failed to create test library');
		
		$this->trackRecordForCleanup($library);
		return $library;
	}
}