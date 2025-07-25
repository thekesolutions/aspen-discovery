# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Aspen Discovery is a flexible, open-source discovery layer that empowers libraries to serve their communities. It integrates with multiple ILS systems, eContent platforms, event platforms, websites, archives, and e-commerce solutions.

## Architecture

### Core Structure
- **PHP-based web application**: Main application in `code/web/`
- **Java utilities**: Various indexing and export tools in `code/` subdirectories
- **Solr integration**: Multiple Solr cores for search functionality
- **MySQL/MariaDB**: Primary data storage
- **Multi-tenant**: Supports multiple library sites

### Key Directories
- `code/web/sys/`: Core PHP classes and data models
- `code/web/services/`: API endpoints and service controllers
- `code/web/interface/`: Smarty templates for UI
- `code/web/Drivers/`: ILS integration drivers
- `code/web/RecordDrivers/`: Record handling for different content types
- `code/cron/`: Background processing tasks
- `install/`: Installation and upgrade scripts
- `sites/`: Multi-site configuration files
- `data_dir_setup/`: Template for data directories

### Data Architecture
- **Grouped Works**: Central concept for bibliographic records - groups related manifestations
- **Libraries & Locations**: Multi-institutional support with scoping
- **Users**: Patron accounts with ILS integration
- **Collections**: Various content sources (physical, digital, events, websites)

## Development Commands

### Docker Development
```bash
# Start development environment
docker compose up -d

# View logs
docker compose logs -f backend

# Access container shell
docker exec -it <container_name> bash
```

### Installation
```bash
# Debian/Ubuntu installation
sudo ./install/installer_debian.sh

# CentOS installation
sudo ./install/installer_centos9.sh
```

### Database Operations
```bash
# Run database updates
php /usr/local/aspen-discovery/code/web/cron/runScheduledUpdate.php [sitename]

# Import SQL backup
php /usr/local/aspen-discovery/install/importAspenBackup.php [sitename] [backup_file]
```

### Indexing & Cron
```bash
# Manual reindexing
php /usr/local/aspen-discovery/code/web/cron/runScheduledUpdate.php [sitename]

# Check background processes
php /usr/local/aspen-discovery/code/web/cron/checkBackgroundProcesses.php [sitename]
```

### Testing

Aspen has a comprehensive test suite with multiple layers:

**Test Structure:**
- **Unit tests**: `tests/phpunit/tests/unit/` - Individual class/method testing
- **Integration tests**: `tests/phpunit/tests/integration/` - Component interaction testing
- **Functional tests**: `tests/phpunit/tests/functional/` - End-to-end workflow testing
- **Performance tests**: `tests/phpunit/tests/performance/` - Performance benchmarking
- **E2E browser tests**: `tests/e2e/selenium-ide/` - Selenium automation tests

**Quick Commands:**
```bash
# Install test dependencies
cd tests/phpunit && composer install

# Run all tests
composer test

# Run specific test suites
composer test:unit
composer test:integration
composer test:functional
composer test:performance

# Generate coverage report
composer test:coverage

# Run individual test file
./phpunit.phar tests/unit/SystemVariablesTest.php
```

**Test Database Setup:**
```bash
mysql -u root -p -e "CREATE DATABASE aspen_test;"
mysql -u root -p aspen_test < install/aspen.sql
mysql -u root -p aspen_test < tests/unit_tests.sql
```

**Writing Tests:**
- Extend `AspenTestCase` for basic tests
- Extend `DatabaseTestCase` for database operations
- Use `TestDataFactory` for generating test data
- Follow naming convention: `*Test.php`

See `tests/README.md` for complete testing documentation.

## Configuration Files

### Site Configuration
- Each site has configuration in `sites/{sitename}/`
- Apache virtual host: `sites/{sitename}/httpd-{sitename}.conf`
- Database connection: Configured through web interface

### Key Configuration Files
- `code/web/bootstrap.php`: Core application bootstrap
- `install/createSiteTemplate.ini`: Template for new sites
- `data_dir_setup/`: Directory structure templates

## Multi-ILS Support

Aspen supports multiple ILS systems through driver architecture:
- CarlX, Evergreen, FOLIO, Horizon, Koha, Millennium, Polaris, Sierra, Symphony
- Drivers located in `code/web/Drivers/`
- Each ILS has specific export utilities in `code/` subdirectories

## Content Sources

### Physical Materials
- MARC record processing and indexing
- ILS integration for holdings and patron data

### Digital Content
- OverDrive, Hoopla, CloudLibrary, Axis 360, Palace Project
- API integration for availability and usage tracking

### Events & Websites
- Event indexing from calendar systems
- Website crawling and indexing
- Open Archives Protocol support

## Key PHP Classes

### Core Framework
- `DataObject`: Base class for database objects
- `SolrDataObject`: Base for Solr-indexed content
- `Interface`: Smarty templating interface
- `Module`: MVC controller base class

### Search & Discovery
- `GroupedWorkSearcher`: Main search functionality
- `SolrConnector` classes: Solr communication
- `RecordDrivers`: Content-type specific record handling

### User Management
- `User`: Patron account management
- `Library`/`Location`: Institution configuration
- Authentication drivers for various systems

## Development Guidelines

### Code Style
- Follow existing PHP conventions in the codebase
- Use meaningful variable and function names
- Comment complex logic, especially in indexing code

### Database Changes
- All schema changes go through `sys/DBMaintenance/version_updates/`
- Update version number in appropriate release file
- Test with sample data

### Adding New Features
- Follow MVC pattern: Module (controller) → Interface (view) → DataObject (model)
- Add appropriate permissions and role checks
- Consider multi-site/multi-library implications
- Update relevant Solr schemas if needed

### Security Considerations
- All user input must be sanitized
- Use parameterized queries for database operations
- Implement proper access controls for admin functions
- Never commit sensitive data like API keys

## Common Tasks

### Adding a New Content Source
1. Create driver in `code/web/Drivers/`
2. Add record driver in `code/web/RecordDrivers/`
3. Create Solr core configuration
4. Add indexing logic
5. Update search interfaces

### ILS Integration
1. Extend appropriate base driver class
2. Implement required methods for patron operations
3. Add configuration options in admin interface
4. Test with real ILS connection

### Performance Optimization
- Monitor slow queries in admin interface
- Use Solr for search-heavy operations
- Implement appropriate caching strategies
- Consider background processing for heavy operations
