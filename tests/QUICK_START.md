# Quick Start - Aspen Discovery Testing

Choose your preferred method to run tests:

## 🚀 **Option 1: Docker (Recommended)**
*Clean, isolated environment with all dependencies included*

```bash
# One-time setup (5 minutes)
cd tests
./setup-container.sh

# Run tests (30 seconds)
./run-tests.sh unit
```

**Pros:** ✅ No local setup ✅ Consistent across machines ✅ Isolated database  
**Cons:** ❌ Requires Docker ❌ Slightly slower startup

---

## 💻 **Option 2: Local Installation**
*Direct execution on your development machine*

### Prerequisites
```bash
# Check requirements
php --version        # Need PHP 8.2+
mysql --version      # Need MySQL/MariaDB
composer --version   # Need Composer

# Check PHP extensions
php -m | grep -E "(pdo_mysql|gd|curl|xml|mbstring)"
```

### Setup (One-time)
```bash
# 1. Install dependencies
cd tests/phpunit
composer install

# 2. Setup test database
mysql -u root -p -e "CREATE DATABASE aspen_test;"
mysql -u root -p aspen_test < ../../install/aspen.sql
mysql -u root -p aspen_test < ../unit_tests.sql

# 3. Create test config
mkdir -p ../../sites/test_local/conf
cat > ../../sites/test_local/conf/config.ini << 'EOF'
[Database]
database_aspen_host = "localhost"
database_aspen_dbname = "aspen_test"
database_user = "root"
database_password = "your_password"

[Testing]
environment = "test"
mock_external_services = true
EOF
```

### Run Tests
```bash
cd tests/phpunit

# All tests
composer test

# Specific suites
composer test:unit
composer test:integration
composer test:functional

# Individual test
./phpunit.phar tests/unit/SystemVariablesTest.php

# With coverage
composer test:coverage
```

**Pros:** ✅ Faster execution ✅ Easy debugging ✅ IDE integration  
**Cons:** ❌ Local setup required ❌ Environment differences ❌ Potential conflicts

---

## 🤖 **Option 3: CI/CD Automation**
*Automatic execution on every commit/PR*

### GitHub Actions (Already Configured)
Tests run automatically on:
- ✅ Every push to main branches (`main`, `develop`, `*.*.00`)
- ✅ Every pull request
- ✅ Multiple PHP versions (8.2, 8.3, 8.4)
- ✅ All test suites (unit, integration, functional, performance)

### View Results
```bash
# Check status
git push origin your-branch
# → Visit GitHub → Actions tab → See test results

# Local status check
git status
git log --oneline -5
```

### Configuration Files
- `.github/workflows/test-suite.yml` - Main CI/CD pipeline
- `.github/workflows/pull_request_qa.yml` - PR quality checks

**Pros:** ✅ Zero setup ✅ Automatic ✅ Multiple environments ✅ Blocks bad code  
**Cons:** ❌ Requires GitHub ❌ Slower feedback ❌ Limited debugging

---

## 📋 **Quick Commands Reference**

| Task | Docker | Local | CI/CD |
|------|--------|-------|-------|
| **Setup** | `./setup-container.sh` | `composer install + DB setup` | Automatic |
| **All Tests** | `./run-tests.sh all` | `composer test` | On git push |
| **Unit Tests** | `./run-tests.sh unit` | `composer test:unit` | On PR |
| **Coverage** | `./run-tests.sh coverage` | `composer test:coverage` | Automatic |
| **Debug** | `./run-tests.sh debug` | `./phpunit.phar --debug` | Check logs |

---

## 🏁 **Recommended Workflow**

### For Development
1. **Start with Docker** - ensure tests work in clean environment
2. **Switch to Local** - for faster iteration during development
3. **Let CI/CD validate** - final check before merge

### For Teams
1. **CI/CD runs automatically** - no manual intervention needed
2. **Docker for debugging** - isolate environment issues  
3. **Local for development** - fast feedback loop

### For Production
1. **All tests must pass** in CI/CD before deployment
2. **Performance tests** monitor for regressions
3. **Coverage reports** track testing completeness

---

## ❓ **Need Help?**

**Common Issues:**
- Docker not starting → Check Docker Desktop is running
- Local tests failing → Verify PHP extensions installed
- Database errors → Check connection settings in config.ini
- CI/CD failures → Check GitHub Actions tab for details

**Documentation:**
- Full details: `tests/RUNNING_TESTS.md`
- Framework info: `tests/README.md`
- Architecture: `CLAUDE.md`

**Support:**
- Check existing tests for examples
- Test framework is self-documenting
- All edge cases are covered in documentation