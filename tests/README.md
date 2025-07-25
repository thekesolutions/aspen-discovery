# Aspen Discovery Test Suite

This directory contains the comprehensive test suite for Aspen Discovery, providing multiple levels of testing to ensure code quality, functionality, and performance.

## Test Structure

The test suite is organized into multiple layers:

```
tests/
├── phpunit/                    # PHP unit and integration tests
│   ├── src/                   # Test framework base classes
│   ├── tests/
│   │   ├── unit/              # Unit tests for individual classes
│   │   ├── integration/       # Integration tests for system components
│   │   ├── functional/        # End-to-end workflow tests
│   │   └── performance/       # Performance and load tests
│   ├── fixtures/              # Test data fixtures
│   └── logs/                  # Test output and coverage reports
├── e2e/                       # Selenium browser automation tests
├── junit/                     # Java-based tests for indexing components
└── unit_tests.sql            # Test database schema and data
```

## Quick Start

### Prerequisites

- PHP 8.2+ with extensions: mysql, pdo_mysql, gd, imagick, curl, xml, mbstring
- Composer (for PHP dependencies)
- MariaDB/MySQL database
- Apache Solr (for search-related tests)

### Setup

1. **Install PHP dependencies:**
   ```bash
   cd tests/phpunit
   composer install
   ```

2. **Setup test database:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE aspen_test;"
   mysql -u root -p aspen_test < ../../install/aspen.sql
   mysql -u root -p aspen_test < ../unit_tests.sql
   ```

3. **Configure test environment:**
   ```bash
   cp sites/default/conf/config.ini sites/test_site/conf/config.ini
   # Edit sites/test_site/conf/config.ini with test database settings
   ```

### Running Tests

#### Run All Tests
```bash
cd tests/phpunit
composer test
```

#### Run Specific Test Suites
```bash
# Unit tests only
composer test:unit

# Integration tests only  
composer test:integration

# Functional tests only
composer test:functional

# Performance tests only
composer test:performance
```

#### Run Individual Test Files
```bash
./phpunit.phar tests/unit/SystemVariablesTest.php
./phpunit.phar tests/integration/DatabaseConnectionTest.php
```

#### Generate Coverage Reports
```bash
# HTML coverage report
composer test:coverage

# Text coverage summary
composer test:coverage-text
```

## Test Types

### Unit Tests (`tests/unit/`)
Test individual classes and methods in isolation.

**Examples:**
- `SystemVariablesTest.php` - Tests system configuration management
- `Utils/StringUtilsTest.php` - Tests string utility functions

**Best Practices:**
- Mock external dependencies
- Test both success and failure scenarios
- Verify edge cases and boundary conditions
- Each test should be independent and isolated

### Integration Tests (`tests/integration/`)
Test how components work together, including database operations and external services.

**Examples:**
- `DatabaseConnectionTest.php` - Tests database connectivity and operations
- `SolrIntegrationTest.php` - Tests search index operations

**Best Practices:**
- Use test transactions for database isolation
- Test actual component interactions
- Verify data persistence and retrieval
- Test error handling with real services

### Functional Tests (`tests/functional/`)
Test complete user workflows and API endpoints end-to-end.

**Examples:**
- `ApiWorkflowTest.php` - Tests complete API request/response cycles
- `UserRegistrationWorkflowTest.php` - Tests user registration process

**Best Practices:**
- Test realistic user scenarios
- Verify complete data flow
- Test permission and access controls
- Include error and edge case workflows

### Performance Tests (`tests/performance/`)
Benchmark system performance and identify bottlenecks.

**Examples:**
- `SearchPerformanceTest.php` - Benchmarks search query performance
- `DatabasePerformanceTest.php` - Tests database operation speed

**Best Practices:**
- Set realistic performance baselines
- Test with representative data volumes
- Monitor memory usage
- Test concurrent operation scenarios

## Test Framework Classes

### AspenTestCase
Base class for all tests providing:
- Test environment setup
- Memory usage monitoring
- Temporary file management  
- Fixture loading utilities
- Common assertion helpers

### DatabaseTestCase
Extends AspenTestCase for database tests:
- Automatic transaction management
- Database assertion helpers
- Test data cleanup
- Test user/library creation utilities

### TestDataFactory
Generates realistic test data:
- User accounts with valid attributes
- Library configurations
- Grouped work records
- API response structures
- Test file uploads

## Writing New Tests

### 1. Choose the Right Test Type
- **Unit Test**: Testing a single class method in isolation
- **Integration Test**: Testing component interactions
- **Functional Test**: Testing complete user workflows
- **Performance Test**: Benchmarking system performance

### 2. Use Appropriate Base Class
```php
<?php
require_once __DIR__ . '/../../src/AspenTestCase.php';

class MyNewTest extends AspenTestCase 
{
    public function testSomething()
    {
        // Your test code here
    }
}
```

### 3. Follow Naming Conventions
- Test files: `*Test.php` or `*Tests.php`
- Test methods: `test*` or use `@test` annotation
- Descriptive names: `testUserCanLoginWithValidCredentials()`

### 4. Use Test Data Factory
```php
public function testUserCreation()
{
    $userData = TestDataFactory::createUser([
        'username' => 'testuser123',
        'email' => 'test@example.com'
    ]);
    
    $user = new User();
    // Use $userData for test...
}
```

### 5. Clean Up Resources
```php
protected function tearDown(): void
{
    $this->cleanupTempFiles($this->tempFiles);
    parent::tearDown();
}
```

## Continuous Integration

The test suite runs automatically on GitHub Actions for:
- All pushes to main branches
- Pull requests
- Multiple PHP versions (8.2, 8.3, 8.4)
- All test suites (unit, integration, functional, performance)

### CI Features:
- Parallel test execution across PHP versions
- Code coverage reporting
- Performance regression detection
- Test result artifacts
- Comprehensive test reporting

## Test Data and Fixtures

### Database Fixtures
Test database is reset before each test suite run using:
- `install/aspen.sql` - Base database schema
- `tests/unit_tests.sql` - Test-specific data

### Test Fixtures
Located in `tests/phpunit/fixtures/`:
- JSON configuration files
- Sample MARC records
- Mock API responses
- Image files for upload tests

### Sample Data
Located in `tests/junit/sample_marcs/`:
- Real MARC records for testing format detection
- Records from various ILS systems
- Different material types and formats

## Troubleshooting

### Common Issues

**Database Connection Errors:**
```bash
# Ensure test database exists and is accessible
mysql -u root -p -e "SHOW DATABASES;" | grep aspen_test
```

**Solr Connection Errors:**
```bash
# Check Solr is running
curl http://localhost:8983/solr/admin/cores?action=STATUS
```

**Memory Limit Errors:**
```bash
# Increase PHP memory limit
php -d memory_limit=2G phpunit.phar
```

**Permission Errors:**
```bash
# Ensure logs directory is writable
chmod 755 tests/phpunit/logs/
```

### Debug Mode
Run tests with verbose output:
```bash
./phpunit.phar --display-warnings --debug tests/unit/MyTest.php
```

### Coverage Issues
Ensure Xdebug is installed for coverage:
```bash
php -m | grep xdebug
```

## Contributing

When adding new functionality to Aspen Discovery:

1. **Write tests first** (Test-Driven Development)
2. **Ensure adequate coverage** (aim for >80%)
3. **Test all code paths** (success, failure, edge cases)
4. **Update existing tests** when changing functionality
5. **Add performance tests** for performance-critical features
6. **Document test requirements** in commit messages

### Test Review Checklist
- [ ] Tests follow naming conventions
- [ ] Appropriate test type chosen
- [ ] Edge cases covered
- [ ] Error conditions tested
- [ ] Performance implications considered
- [ ] Database transactions used properly
- [ ] External dependencies mocked appropriately
- [ ] Test data cleaned up properly

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Aspen Discovery Developer Documentation](https://dev.aspendiscovery.org)
- [Test-Driven Development Best Practices](https://testdriven.io/test-driven-development/)
- [Database Testing Patterns](https://phpunit.de/manual/current/en/database.html)