#!/bin/bash

# Script to run Aspen Discovery tests in Docker containers

set -e

SUITE=${1:-all}
PHP_VERSION=${PHP_VERSION:-8.4}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}🧪 Running Aspen Discovery Tests in Container${NC}"
echo -e "Test Suite: ${YELLOW}${SUITE}${NC}"
echo ""

# Ensure containers are running
if ! docker compose -f docker-compose.test.yml ps | grep -q "Up"; then
    echo -e "${YELLOW}⚠️  Test containers not running. Starting them...${NC}"
    docker compose -f docker-compose.test.yml up -d
    sleep 5
fi

# Function to run tests
run_test_suite() {
    local test_suite=$1
    local description=$2

    echo -e "${BLUE}📋 Running ${description}...${NC}"
    echo "=================================="

    if [ "$test_suite" = "all" ]; then
        docker compose -f docker-compose.test.${PHP_VERSION:-8.4}.yml exec -T php-test bash -c "
            cd tests/phpunit &&
            php phpunit.phar --configuration phpunit.xml --colors=always
        "
    else
        docker compose -f docker-compose.test.${PHP_VERSION:-8.4}.yml exec -T php-test bash -c "
            cd tests/phpunit &&
            php phpunit.phar --configuration phpunit.xml --testsuite=${test_suite} --colors=always
        "
    fi

    echo ""
}

# Function to show container info
show_environment() {
    echo -e "${BLUE}🔍 Test Environment Information${NC}"
    echo "=================================="

    echo -e "${YELLOW}PHP Version:${NC}"
    docker compose -f docker-compose.test.${PHP_VERSION:-8.4}.yml exec -T php-test php --version | head -1

    echo -e "${YELLOW}PHP Extensions:${NC}"
    docker compose -f docker-compose.test.${PHP_VERSION:-8.4}.yml exec -T php-test php -m | grep -E "(pdo_mysql|gd|curl|xml|mbstring)" | head -5

    echo -e "${YELLOW}MySQL Status:${NC}"
    docker compose -f docker-compose.test.yml exec -T mysql-test mysql -uroot -ptestpass aspen_test -e "SELECT 'Database Connected Successfully' as status;" 2>/dev/null || echo "Database connection failed"

    echo -e "${YELLOW}Test Files Available:${NC}"
    docker compose -f docker-compose.test.${PHP_VERSION:-8.4}.yml exec -T php-test find tests/phpunit/tests -name "*.php" | wc -l | tr -d ' '
    echo ""
}

# Function to show memory and performance info
show_performance_info() {
    echo -e "${BLUE}📊 Performance Information${NC}"
    echo "=================================="

    docker compose -f docker-compose.test.${PHP_VERSION:-8.4}.yml exec -T php-test bash -c "
        cd tests/phpunit &&
        echo 'Memory limit: ' && php -r 'echo ini_get(\"memory_limit\") . PHP_EOL;' &&
        echo 'Max execution time: ' && php -r 'echo ini_get(\"max_execution_time\") . PHP_EOL;'
    "
    echo ""
}

# Show environment information
show_environment
show_performance_info

# Run the requested test suite
case $SUITE in
    "unit")
        run_test_suite "unit" "Unit Tests (Fast, Isolated)"
        ;;
    "integration")
        run_test_suite "integration" "Integration Tests (Database Required)"
        ;;
    "functional")
        run_test_suite "functional" "Functional Tests (End-to-End Workflows)"
        ;;
    "performance")
        run_test_suite "performance" "Performance Tests (Benchmarking)"
        ;;
    "all")
        echo -e "${GREEN}🚀 Running Complete Test Suite${NC}"
        echo ""

        run_test_suite "initialization" "Initialization Tests"
        run_test_suite "unit" "Unit Tests"
        run_test_suite "integration" "Integration Tests"
        run_test_suite "functional" "Functional Tests"
        run_test_suite "performance" "Performance Tests"
        run_test_suite "finalization" "Finalization Tests"
        ;;
    "debug")
        echo -e "${YELLOW}🐛 Running Tests in Debug Mode${NC}"
        docker compose -f docker-compose.test.${PHP_VERSION:-8.4}.yml exec -T php-test bash -c "
            cd tests/phpunit &&
            php phpunit.phar --configuration phpunit.xml --debug --stop-on-failure
        "
        ;;
    "coverage")
        echo -e "${BLUE}📈 Generating Coverage Report${NC}"
        docker compose -f docker-compose.test.${PHP_VERSION:-8.4}.yml exec -T php-test bash -c "
            cd tests/phpunit &&
            php phpunit.phar --configuration phpunit.xml --coverage-text --colors=always
        "
        ;;
    *)
        echo -e "${RED}❌ Unknown test suite: ${SUITE}${NC}"
        echo ""
        echo -e "${YELLOW}Available options:${NC}"
        echo "  unit         - Unit tests only"
        echo "  integration  - Integration tests only"
        echo "  functional   - Functional tests only"
        echo "  performance  - Performance tests only"
        echo "  all          - All test suites (default)"
        echo "  debug        - Run with debug output"
        echo "  coverage     - Generate coverage report"
        exit 1
        ;;
esac

echo -e "${GREEN}✅ Test execution completed!${NC}"
