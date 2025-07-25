#!/bin/bash

# Setup script for running Aspen Discovery tests in containers

set -e

# Set default PHP version if not provided
PHP_VERSION=${PHP_VERSION:-8.4}

echo "🐳 Setting up Aspen Discovery test environment in containers..."
echo "🐘 Using PHP version: ${PHP_VERSION}"

# Update docker compose to use the specified PHP version
sed "s/php:8.4-cli/php:${PHP_VERSION}-cli/g" docker-compose.test.yml > docker-compose.test.${PHP_VERSION}.yml

# Start the test services
echo "📦 Starting test containers..."
docker compose -f docker-compose.test.${PHP_VERSION}.yml up -d

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
sleep 10

# Install PHP extensions in the PHP container
echo "🔧 Installing required PHP extensions..."
docker compose -f docker-compose.test.${PHP_VERSION}.yml exec -T php-test bash -c "
    apt-get update &&
    apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libonig-dev \
        default-mysql-client \
        unzip \
        git &&
    docker-php-ext-configure gd --with-freetype --with-jpeg &&
    docker-php-ext-install \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        xml \
        curl \
        mbstring
"

# Install Composer
echo "📝 Installing Composer..."
docker compose -f docker-compose.test.${PHP_VERSION}.yml exec -T php-test bash -c "
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
"

# Install PHP dependencies
echo "📚 Installing PHP test dependencies..."
docker compose -f docker-compose.test.${PHP_VERSION}.yml exec -T php-test bash -c "
    cd tests/phpunit &&
    composer install --no-interaction --prefer-dist
"

# Setup test database
echo "🗄️ Setting up test database..."
docker compose -f docker-compose.test.yml exec -T mysql-test mysql -uroot -ptestpass -e "
    CREATE DATABASE IF NOT EXISTS aspen_test;
    GRANT ALL PRIVILEGES ON aspen_test.* TO 'testuser'@'%';
    FLUSH PRIVILEGES;
"

# Import database schema (simplified for demo)
echo "📊 Creating basic test database structure..."
docker compose -f docker-compose.test.yml exec -T mysql-test mysql -uroot -ptestpass aspen_test -e "
    CREATE TABLE IF NOT EXISTS system_variables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        errorEmail VARCHAR(255),
        ticketEmail VARCHAR(255),
        catalogStatus INT DEFAULT 0,
        offlineMessage TEXT,
        maintenanceMode INT DEFAULT 0,
        maintenanceMessage TEXT
    );

    INSERT IGNORE INTO system_variables (id, errorEmail, catalogStatus) VALUES (1, 'test@example.com', 0);

    CREATE TABLE IF NOT EXISTS libraries (
        libraryId INT AUTO_INCREMENT PRIMARY KEY,
        subdomain VARCHAR(255) UNIQUE,
        displayName VARCHAR(255),
        showDisplayNameInHeader TINYINT DEFAULT 1
    );

    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) UNIQUE,
        displayName VARCHAR(255),
        email VARCHAR(255),
        created DATETIME DEFAULT CURRENT_TIMESTAMP
    );
"

# Create test site configuration
echo "⚙️ Creating test configuration..."
docker compose -f docker-compose.test.${PHP_VERSION}.yml exec -T php-test bash -c "
    mkdir -p sites/test_site/conf
    cat > sites/test_site/conf/config.ini << 'EOF'
[Site]
isProduction = false
url = \"http://test.localhost\"
title = \"Test Aspen Discovery\"

[Database]
database_aspen_host = \"mysql-test\"
database_aspen_dbname = \"aspen_test\"
database_user = \"testuser\"
database_password = \"testpass\"
database_aspen_dbport = 3306

[Testing]
environment = \"test\"
mock_external_services = true
EOF
"

echo "✅ Test environment setup complete!"
echo ""
echo "🚀 Ready to run tests! Use these commands:"
echo "  ./run-tests.sh unit         # Run unit tests"
echo "  ./run-tests.sh integration  # Run integration tests"
echo "  ./run-tests.sh functional   # Run functional tests"
echo "  ./run-tests.sh all          # Run all tests"
