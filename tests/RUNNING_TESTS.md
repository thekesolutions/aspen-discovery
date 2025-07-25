# Running Aspen Discovery Tests - Complete Guide

This guide shows you exactly how to run the Aspen Discovery test suite and what to expect from the output.

## 🚀 Quick Start

### Prerequisites Check
```bash
# Check PHP version (needs 8.2+)
php --version

# Check required extensions
php -m | grep -E "(pdo_mysql|gd|curl|xml|mbstring)"

# Check if composer is available
composer --version
```

### Initial Setup
```bash
# 1. Navigate to test directory
cd tests/phpunit

# 2. Install PHP dependencies
composer install

# 3. Create logs directory
mkdir -p logs

# 4. Verify PHPUnit is available
./phpunit.phar --version
```

## 📋 Available Test Commands

### Composer Scripts (Recommended)

```bash
# Run all tests
composer test

# Run specific test suites
composer test:unit              # Unit tests only
composer test:integration       # Integration tests only  
composer test:functional        # Functional tests only
composer test:performance       # Performance tests only

# Generate coverage reports
composer test:coverage          # HTML coverage report in logs/coverage/
composer test:coverage-text     # Text coverage summary to console

# Clean up
composer clean                  # Remove logs and vendor files
```

### Direct PHPUnit Commands

```bash
# Run all tests with verbose output
./phpunit.phar --configuration phpunit.xml --display-warnings

# Run specific test suite
./phpunit.phar --configuration phpunit.xml --testsuite=unit

# Run individual test file
./phpunit.phar tests/unit/SystemVariablesTest.php

# Run with debug output
./phpunit.phar --debug tests/unit/SystemVariablesTest.php

# Run tests matching pattern
./phpunit.phar --filter="testSystemVariables" tests/unit/

# Run tests and generate coverage
./phpunit.phar --coverage-html logs/coverage

# List available test suites
./phpunit.phar --list-suites
```

## 🔧 Test Environment Setup

### Database Setup (Required for Integration/Functional Tests)

```bash
# Create test database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS aspen_test;"

# Import base schema
mysql -u root -p aspen_test < ../../install/aspen.sql

# Import test data
mysql -u root -p aspen_test < ../unit_tests.sql

# Verify setup
mysql -u root -p aspen_test -e "SHOW TABLES;" | head -10
```

### Configuration Setup

```bash
# Create test site configuration
mkdir -p ../../sites/test_site/conf

# Create minimal test config
cat > ../../sites/test_site/conf/config.ini << 'EOF'
[Site]
isProduction = false
url = "http://test.localhost"
title = "Test Aspen Discovery"

[Database]
database_aspen_host = "127.0.0.1"
database_aspen_dbname = "aspen_test"
database_user = "root"
database_password = "your_password"
database_aspen_dbport = 3306

[Testing]
environment = "test"
mock_external_services = true
EOF
```

## 📊 Expected Output Examples

### Successful Unit Test Run
```
PHPUnit 10.5.0 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.0
Configuration: /path/to/phpunit.xml

Unit Tests
..........                                                        10 / 10 (100%)

Time: 00:02.543, Memory: 24.00 MB

OK (10 tests, 25 assertions)

Generating code coverage report in HTML format ... done [00:01.234]
```

### Test with Warnings
```
PHPUnit 10.5.0 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.0
Configuration: /path/to/phpunit.xml

Unit Tests
..W.......                                                        10 / 10 (100%)

Time: 00:02.123, Memory: 26.00 MB

OK, but with warnings (10 tests, 25 assertions)

Warnings:
1) SystemVariablesTest::testMemoryUsage
   Memory usage increased by 15.2MB, approaching limit

WARNINGS!
Tests: 10, Assertions: 25, Warnings: 1.
```

### Test Failure Output
```
PHPUnit 10.5.0 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.0
Configuration: /path/to/phpunit.xml

Unit Tests
.F........                                                        10 / 10 (100%)

Time: 00:02.456, Memory: 28.00 MB

FAILURES!
Tests: 10, Assertions: 24, Failures: 1.

There was 1 failure:

1) SystemVariablesTest::testUpdateSystemVariables
Failed asserting that false is not false.
/path/to/SystemVariablesTest.php:45

FAILURES!
Tests: 10, Assertions: 24, Failures: 1.
```

## 🏃‍♂️ Running Tests Step by Step

### Step 1: Unit Tests (Fast, No Dependencies)
```bash
# Run unit tests - these should always pass
composer test:unit

# Expected: Fast execution, no database/external service calls
# Output: Green dots, all tests passing
```

### Step 2: Integration Tests (Database Required)
```bash
# Run integration tests - requires database
composer test:integration

# Expected: Database connections, CRUD operations tested
# Output: May show database interaction messages
```

### Step 3: Functional Tests (Full System)
```bash
# Run functional tests - tests complete workflows  
composer test:functional

# Expected: API calls, complete request/response cycles
# Output: More verbose, shows system interactions
```

### Step 4: Performance Tests (Benchmarking)
```bash
# Run performance tests - establishes baselines
composer test:performance

# Expected: Timing measurements, memory usage reports
# Output: Performance metrics and benchmark results
```

## 🐛 Troubleshooting Common Issues

### Database Connection Errors
```bash
# Error: "SQLSTATE[HY000] [2002] Connection refused"
# Solution: Ensure MySQL is running and credentials are correct

# Check MySQL status
brew services list | grep mysql    # macOS
systemctl status mysql             # Linux

# Test connection manually
mysql -u root -p -e "SELECT 1;"
```

### Memory Limit Errors
```bash
# Error: "Fatal error: Allowed memory size exhausted"
# Solution: Increase PHP memory limit

# Run with higher memory limit
php -d memory_limit=2G ./phpunit.phar

# Or update php.ini permanently
echo "memory_limit = 2G" >> /usr/local/etc/php/php.ini
```

### Missing Extensions
```bash
# Error: "Extension 'pdo_mysql' not loaded"
# Solution: Install required PHP extensions

# macOS with Homebrew
brew install php@8.4-mysql php@8.4-gd

# Ubuntu/Debian
sudo apt-get install php8.4-mysql php8.4-gd php8.4-curl
```

### Permission Errors
```bash
# Error: "Permission denied" for logs directory
# Solution: Fix directory permissions

chmod 755 logs/
chmod 755 vendor/
```

## 📈 Reading Test Results

### Success Indicators
- **Green dots (.)**: Passed tests
- **Memory usage**: Should be reasonable (< 50MB for unit tests)
- **Execution time**: Unit tests < 10s, integration tests < 30s
- **Coverage**: Aim for > 80% code coverage

### Warning Signs
- **Yellow W**: Warnings (not failures, but worth investigating)
- **High memory usage**: May indicate memory leaks
- **Slow execution**: Performance regressions
- **Skipped tests (S)**: Tests that couldn't run (missing dependencies)

### Failure Investigation
```bash
# Run single failing test with debug info
./phpunit.phar --debug tests/unit/FailingTest.php

# Run with verbose error reporting
./phpunit.phar --display-warnings --stop-on-failure tests/unit/

# Check logs for detailed error information
cat logs/junit.xml
cat logs/phpunit.log
```

## 🎯 Best Practices

### Before Running Tests
1. **Always pull latest code**: `git pull origin main`
2. **Update dependencies**: `composer install`
3. **Reset test database**: Re-import clean test data
4. **Check system resources**: Ensure adequate memory/disk space

### During Development
1. **Run unit tests frequently**: After each code change
2. **Run integration tests**: Before committing changes
3. **Run full suite**: Before pushing to remote
4. **Check coverage**: Ensure new code is tested

### Continuous Integration
- Tests run automatically on every push/PR
- All test suites must pass before merging
- Performance tests detect regressions
- Coverage reports track testing completeness

## 📚 Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Aspen Discovery Testing Guide](tests/README.md)
- [CI/CD Pipeline](.github/workflows/test-suite.yml)
- [Test Writing Guidelines](CLAUDE.md#testing)