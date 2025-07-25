<?php

/**
 * Factory class for creating test data objects
 * Provides convenient methods to generate realistic test data
 */
class TestDataFactory
{
	private static $counter = 0;

	/**
	 * Generate a unique identifier for test data
	 */
	private static function getUniqueId(): string
	{
		return 'test_' . (++self::$counter) . '_' . uniqid();
	}

	/**
	 * Create a test user with realistic data
	 */
	public static function createUser(array $overrides = []): array
	{
		$defaults = [
			'username' => 'testuser_' . self::getUniqueId(),
			'displayName' => 'Test User ' . self::$counter,
			'firstname' => 'Test',
			'lastname' => 'User',
			'email' => 'testuser' . self::$counter . '@example.com',
			'phone' => '555-' . str_pad(self::$counter, 4, '0', STR_PAD_LEFT),
			'patronType' => 'adult',
			'homeLibraryId' => 1,
			'created' => date('Y-m-d H:i:s'),
			'lastLoginTime' => date('Y-m-d H:i:s'),
		];

		return array_merge($defaults, $overrides);
	}

	/**
	 * Create a test library with realistic data
	 */
	public static function createLibrary(array $overrides = []): array
	{
		$defaults = [
			'subdomain' => 'testlib_' . self::getUniqueId(),
			'displayName' => 'Test Library ' . self::$counter,
			'libraryId' => -1,
			'showDisplayNameInHeader' => 1,
			'allowPinReset' => 1,
			'loginFormUsernameLabel' => 'Library Card Number',
			'loginFormPasswordLabel' => 'PIN',
			'isDefault' => 0,
		];

		return array_merge($defaults, $overrides);
	}

	/**
	 * Create test grouped work data
	 */
	public static function createGroupedWork(array $overrides = []): array
	{
		$defaults = [
			'permanent_id' => 'test_work_' . self::getUniqueId(),
			'author' => 'Test Author ' . self::$counter,
			'title' => 'Test Title ' . self::$counter,
			'grouping_category' => 'book',
			'full_title' => 'Test Title ' . self::$counter . ': A Test Book',
			'date_updated' => time(),
		];

		return array_merge($defaults, $overrides);
	}

	/**
	 * Create test MARC record data
	 */
	public static function createMarcRecord(array $overrides = []): array
	{
		$defaults = [
			'id' => 'test_marc_' . self::getUniqueId(),
			'source' => 'test_ils',
			'title' => 'Test MARC Title ' . self::$counter,
			'author' => 'Test MARC Author ' . self::$counter,
			'format' => 'Book',
			'format_category' => 'Books',
			'isbn' => '978' . str_pad(self::$counter, 10, '0', STR_PAD_LEFT),
			'publication_date' => date('Y'),
		];

		return array_merge($defaults, $overrides);
	}

	/**
	 * Create test API response structure
	 */
	public static function createApiResponse(array $data = [], bool $success = true, string $message = ''): array
	{
		return [
			'success' => $success,
			'message' => $message,
			'data' => $data,
			'timestamp' => time(),
		];
	}

	/**
	 * Create test search results structure
	 */
	public static function createSearchResults(array $overrides = []): array
	{
		$defaults = [
			'success' => true,
			'totalResults' => 10,
			'resultsPage' => 1,
			'resultsPerPage' => 20,
			'searchTerm' => 'test query',
			'searchType' => 'Keyword',
			'results' => [
				[
					'id' => 'test_result_1',
					'title' => 'Test Result 1',
					'author' => 'Test Author 1',
					'format' => 'Book',
				],
				[
					'id' => 'test_result_2',
					'title' => 'Test Result 2',
					'author' => 'Test Author 2',
					'format' => 'eBook',
				],
			],
			'facets' => [
				'format' => [
					'Book' => 5,
					'eBook' => 3,
					'DVD' => 2,
				],
				'availability' => [
					'Available' => 8,
					'Checked Out' => 2,
				],
			],
		];

		return array_merge($defaults, $overrides);
	}

	/**
	 * Create test ILS response data
	 */
	public static function createIlsResponse(string $type, array $overrides = []): array
	{
		switch ($type) {
			case 'patron_info':
				$defaults = [
					'success' => true,
					'patron' => [
						'id' => 'test_patron_' . self::getUniqueId(),
						'name' => 'Test Patron ' . self::$counter,
						'email' => 'patron' . self::$counter . '@example.com',
						'homeLibrary' => 'Main Library',
						'expires' => date('Y-m-d', strtotime('+1 year')),
						'fines' => '0.00',
					],
				];
				break;

			case 'holds':
				$defaults = [
					'success' => true,
					'holds' => [
						[
							'id' => 'hold_' . self::getUniqueId(),
							'title' => 'Test Hold Title',
							'author' => 'Test Hold Author',
							'format' => 'Book',
							'status' => 'Ready for Pickup',
							'pickupLocation' => 'Main Library',
							'expirationDate' => date('Y-m-d', strtotime('+7 days')),
						],
					],
				];
				break;

			case 'checkouts':
				$defaults = [
					'success' => true,
					'checkouts' => [
						[
							'id' => 'checkout_' . self::getUniqueId(),
							'title' => 'Test Checkout Title',
							'author' => 'Test Checkout Author',
							'format' => 'Book',
							'dueDate' => date('Y-m-d', strtotime('+14 days')),
							'renewCount' => 0,
							'maxRenewals' => 3,
						],
					],
				];
				break;

			default:
				$defaults = [
					'success' => true,
					'message' => 'Test response',
				];
		}

		return array_merge($defaults, $overrides);
	}

	/**
	 * Create test file upload data
	 */
	public static function createFileUpload(array $overrides = []): array
	{
		$defaults = [
			'title' => 'Test File ' . self::$counter,
			'type' => 'web_builder_image',
			'fullPath' => '/tmp/test_file_' . self::getUniqueId() . '.jpg',
			'owningLibrary' => -1,
			'sharing' => 2, // All Libraries
		];

		return array_merge($defaults, $overrides);
	}

	/**
	 * Generate test image data (base64 encoded 1x1 pixel)
	 */
	public static function createTestImage(): string
	{
		// 1x1 pixel transparent PNG
		return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
	}

	/**
	 * Create test configuration array
	 */
	public static function createTestConfig(array $overrides = []): array
	{
		$defaults = [
			'Site' => [
				'isProduction' => false,
				'url' => 'http://test.localhost',
				'title' => 'Test Aspen Discovery',
			],
			'Database' => [
				'database_aspen_host' => 'localhost',
				'database_aspen_dbname' => 'aspen_test',
				'database_user' => 'test_user',
				'database_password' => 'test_pass',
			],
			'Solr' => [
				'url' => 'http://localhost:8983/solr',
			],
			'Testing' => [
				'environment' => 'test',
				'mock_external_services' => true,
			],
		];

		return array_merge_recursive($defaults, $overrides);
	}

	/**
	 * Reset the counter (useful for tests that need predictable IDs)
	 */
	public static function resetCounter(): void
	{
		self::$counter = 0;
	}
}