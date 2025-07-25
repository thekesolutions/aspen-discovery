<?php
require_once __DIR__ . '/../../../src/AspenTestCase.php';
require_once ROOT_DIR . '/sys/Utils/StringUtils.php';

/**
 * Unit tests for StringUtils class
 */
class StringUtilsTest extends AspenTestCase
{
	public function testRemoveTrailingPunctuation()
	{
		$testCases = [
			['Hello World!', 'Hello World'],
			['Test String.', 'Test String'],
			['Another Test;', 'Another Test'],
			['No Punctuation', 'No Punctuation'],
			['Multiple!!!', 'Multiple'],
			['', ''],
		];

		foreach ($testCases as [$input, $expected]) {
			$result = StringUtils::removeTrailingPunctuation($input);
			$this->assertEquals($expected, $result, "Failed for input: '{$input}'");
		}
	}

	public function testFormatBytes()
	{
		$testCases = [
			[0, '0 B'],
			[1024, '1.00 KB'],
			[1048576, '1.00 MB'],
			[1073741824, '1.00 GB'],
			[1500, '1.46 KB'],
			[2048000, '1.95 MB'],
		];

		foreach ($testCases as [$input, $expected]) {
			$result = StringUtils::formatBytes($input);
			$this->assertEquals($expected, $result, "Failed for input: {$input}");
		}
	}

	public function testTruncate()
	{
		$longString = 'This is a very long string that should be truncated';
		
		// Test basic truncation
		$result = StringUtils::truncate($longString, 20);
		$this->assertEquals(20, strlen($result));
		$this->assertStringEndsWith('...', $result);
		
		// Test truncation without ellipsis
		$result = StringUtils::truncate($longString, 20, false);
		$this->assertEquals(20, strlen($result));
		$this->assertStringEndsNotWith('...', $result);
		
		// Test string shorter than limit
		$shortString = 'Short';
		$result = StringUtils::truncate($shortString, 20);
		$this->assertEquals($shortString, $result);
	}

	public function testSlugify()
	{
		$testCases = [
			['Hello World', 'hello-world'],
			['Test String!', 'test-string'],
			['  Multiple   Spaces  ', 'multiple-spaces'],
			['Special@#$%Characters', 'special-characters'],
			['accented-éñglîsh', 'accented-english'],
			['', ''],
		];

		foreach ($testCases as [$input, $expected]) {
			$result = StringUtils::slugify($input);
			$this->assertEquals($expected, $result, "Failed for input: '{$input}'");
		}
	}

	public function testCleanString()
	{
		$testCases = [
			["Hello\r\nWorld", 'Hello World'],
			["Tab\tSeparated", 'Tab Separated'],
			["Multiple\n\n\nLineBreaks", 'Multiple LineBreaks'],
			["  Extra   Spaces  ", 'Extra Spaces'],
			['Normal String', 'Normal String'],
		];

		foreach ($testCases as [$input, $expected]) {
			$result = StringUtils::cleanString($input);
			$this->assertEquals($expected, $result, "Failed for input: '{$input}'");
		}
	}

	public function testStartsWith()
	{
		$this->assertTrue(StringUtils::startsWith('Hello World', 'Hello'));
		$this->assertTrue(StringUtils::startsWith('Test', 'Test'));
		$this->assertFalse(StringUtils::startsWith('Hello World', 'World'));
		$this->assertFalse(StringUtils::startsWith('Test', 'Testing'));
		$this->assertTrue(StringUtils::startsWith('', ''));
	}

	public function testEndsWith()
	{
		$this->assertTrue(StringUtils::endsWith('Hello World', 'World'));
		$this->assertTrue(StringUtils::endsWith('Test', 'Test'));
		$this->assertFalse(StringUtils::endsWith('Hello World', 'Hello'));
		$this->assertFalse(StringUtils::endsWith('Test', 'Testing'));
		$this->assertTrue(StringUtils::endsWith('', ''));
	}

	public function testContains()
	{
		$this->assertTrue(StringUtils::contains('Hello World', 'lo Wo'));
		$this->assertTrue(StringUtils::contains('Test String', 'String'));
		$this->assertFalse(StringUtils::contains('Hello World', 'xyz'));
		$this->assertTrue(StringUtils::contains('Test', ''));
		$this->assertFalse(StringUtils::contains('', 'test'));
	}

	public function testIsValidEmail()
	{
		$validEmails = [
			'test@example.com',
			'user.name@domain.org',
			'admin+tag@site.net',
			'first.last@subdomain.example.com',
		];

		$invalidEmails = [
			'invalid-email',
			'@domain.com',
			'user@',
			'user name@domain.com',
			'',
		];

		foreach ($validEmails as $email) {
			$this->assertTrue(StringUtils::isValidEmail($email), "Should be valid: {$email}");
		}

		foreach ($invalidEmails as $email) {
			$this->assertFalse(StringUtils::isValidEmail($email), "Should be invalid: {$email}");
		}
	}

	public function testMemoryUsage()
	{
		$this->assertMemoryUsageReasonable(10);
	}
}