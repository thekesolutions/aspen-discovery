<?php

/**
 * Standalone test to demonstrate the testing framework
 * This test runs without requiring the full Aspen bootstrap
 */

require_once __DIR__ . '/src/TestDataFactory.php';

// Simple test class that extends PHPUnit without Aspen dependencies
class StandaloneFrameworkTest extends PHPUnit\Framework\TestCase
{
    public function testPHPUnitIsWorking()
    {
        $this->assertTrue(true, 'PHPUnit should be working');
        $this->assertEquals(4, 2 + 2, 'Basic math should work');
        $this->assertIsString('hello', 'String validation should work');
    }

    public function testTestDataFactory()
    {
        $userData = TestDataFactory::createUser([
            'username' => 'test_demo_user',
            'email' => 'demo@example.com'
        ]);

        $this->assertIsArray($userData, 'TestDataFactory should return array');
        $this->assertEquals('test_demo_user', $userData['username']);
        $this->assertEquals('demo@example.com', $userData['email']);
        $this->assertArrayHasKey('displayName', $userData);
        $this->assertArrayHasKey('created', $userData);
    }

    public function testApiResponseGeneration()
    {
        $response = TestDataFactory::createApiResponse(['message' => 'Hello World'], true, 'Success');

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertEquals('Success', $response['message']);
        $this->assertEquals('Hello World', $response['data']['message']);
        $this->assertArrayHasKey('timestamp', $response);
    }

    public function testSearchResultsStructure()
    {
        $results = TestDataFactory::createSearchResults([
            'searchTerm' => 'test query',
            'totalResults' => 42
        ]);

        $this->assertIsArray($results);
        $this->assertTrue($results['success']);
        $this->assertEquals(42, $results['totalResults']);
        $this->assertEquals('test query', $results['searchTerm']);
        $this->assertArrayHasKey('results', $results);
        $this->assertArrayHasKey('facets', $results);
        $this->assertIsArray($results['results']);
        $this->assertIsArray($results['facets']);
    }

    public function testStringOperations()
    {
        // Test string manipulation without requiring StringUtils class
        $testString = "  Hello World!  ";
        $cleaned = trim($testString);
        $this->assertEquals("Hello World!", $cleaned);

        $lowercased = strtolower($cleaned);
        $this->assertEquals("hello world!", $lowercased);

        $replaced = str_replace("World", "Universe", $cleaned);
        $this->assertEquals("Hello Universe!", $replaced);
    }

    public function testArrayOperations()
    {
        $testArray = ['apple', 'banana', 'cherry'];
        
        $this->assertCount(3, $testArray);
        $this->assertContains('banana', $testArray);
        $this->assertEquals('apple', $testArray[0]);
        
        $filtered = array_filter($testArray, function($item) {
            return strlen($item) > 5;
        });
        
        $this->assertCount(2, $filtered); // banana, cherry
        $this->assertContains('banana', $filtered);
        $this->assertContains('cherry', $filtered);
    }

    public function testMemoryUsage()
    {
        $initialMemory = memory_get_usage(true);
        
        // Create some data to use memory
        $largeArray = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeArray[] = "Test string number " . $i;
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryUsed = $finalMemory - $initialMemory;
        
        // Should use some memory but not excessive
        $this->assertGreaterThan(0, $memoryUsed, 'Should use some memory');
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 'Should not use more than 10MB');
        
        unset($largeArray); // Clean up
    }

    public function testPerformanceTiming()
    {
        $startTime = microtime(true);
        
        // Simulate some work
        $result = 0;
        for ($i = 0; $i < 10000; $i++) {
            $result += sqrt($i);
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $this->assertGreaterThan(0, $duration, 'Should take some time');
        $this->assertLessThan(1.0, $duration, 'Should complete within 1 second');
        $this->assertGreaterThan(0, $result, 'Should produce a result');
    }

    public function testJsonOperations()
    {
        $data = [
            'title' => 'Test Book',
            'author' => 'Test Author',
            'year' => 2024,
            'available' => true
        ];

        $json = json_encode($data);
        $this->assertIsString($json);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals($data, $decoded);
        $this->assertEquals('Test Book', $decoded['title']);
        $this->assertTrue($decoded['available']);
    }
}