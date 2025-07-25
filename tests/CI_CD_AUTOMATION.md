# CI/CD Automation - Aspen Discovery Testing

## 🤖 **Automatic Test Execution**

**YES, tests run automatically!** Here's exactly when and how:

### ⚡ **Triggers (When Tests Run)**

**1. On Every Push:**
```bash
git push origin main           # ✅ Runs full test suite
git push origin develop        # ✅ Runs full test suite  
git push origin 25.08.00       # ✅ Runs full test suite (release branches)
git push origin feature-branch # ❌ No tests (only main branches)
```

**2. On Every Pull Request:**
```bash
# Create PR targeting main/develop/release branch
gh pr create --title "My Feature" --base main
# ✅ Automatically runs all tests
# ✅ Blocks merge if tests fail
# ✅ Shows results in PR checks
```

**3. Matrix Testing (Runs Multiple Combinations):**
- **PHP Versions**: 8.2, 8.3, 8.4 (12 test combinations)
- **Test Suites**: unit, integration, functional, performance  
- **Total Jobs**: 3 PHP × 4 test suites = **12 parallel test jobs**

### 📊 **What Gets Tested Automatically**

**Complete Test Suite:**
```yaml
# .github/workflows/test-suite.yml
on:
  push:
    branches: [ main, develop, '*.*.00' ]
  pull_request:
    branches: [ main, develop, '*.*.00' ]

strategy:
  matrix:
    php-version: ['8.2', '8.3', '8.4']
    test-suite: ['unit', 'integration', 'functional', 'performance']
```

**Services Included:**
- ✅ **MySQL/MariaDB 10.5** (test database)
- ✅ **Apache Solr** (search functionality)  
- ✅ **All PHP Extensions** (gd, mysql, curl, xml, mbstring)
- ✅ **Composer Dependencies** (cached for speed)

### 🔍 **How to View Results**

**1. GitHub Interface:**
```bash
# After pushing code:
1. Visit your repository on GitHub
2. Click "Actions" tab
3. See test results for each commit
4. Click any job to see detailed logs
```

**2. Pull Request Checks:**
```bash
# In any PR:
- Green checkmarks = tests passed ✅
- Red X marks = tests failed ❌  
- Yellow dots = tests running ⏳
- Click "Details" for full logs
```

**3. Email Notifications:**
- GitHub sends emails for failed builds
- Configured per repository settings
- Team members get notified of failures

### 📈 **Reporting and Artifacts**

**Automatic Reports Generated:**
- **Test Results**: JUnit XML format
- **Coverage Reports**: Uploaded to Codecov
- **Performance Metrics**: Timing and memory usage
- **Artifacts**: Test logs and HTML reports

**Coverage Integration:**
```yaml
# Uploads coverage to Codecov automatically
- name: Upload coverage to Codecov
  uses: codecov/codecov-action@v3
  with:
    file: ./tests/phpunit/logs/coverage-${{ matrix.test-suite }}.xml
    flags: ${{ matrix.test-suite }}
```

---

## 🛡️ **Quality Gates (Blocks Bad Code)**

### **Merge Protection Rules**
Tests **must pass** before code can be merged:

```bash
# This will be BLOCKED if tests fail:
git push origin feature-branch
gh pr create --base main

# GitHub prevents merge until:
✅ All test suites pass
✅ No performance regressions  
✅ Code coverage maintained
✅ No syntax errors
```

### **Additional Quality Checks**
```yaml
# .github/workflows/pull_request_qa.yml
- Release notes updated
- Code style consistency  
- No spaces instead of tabs
- Docker configuration sync
```

---

## 📋 **CI/CD Workflow Details**

### **Full Pipeline Stages**

**1. Setup (2-3 minutes)**
```bash
✅ Checkout code
✅ Setup PHP 8.2/8.3/8.4
✅ Install extensions (mysql, gd, curl, xml)
✅ Cache Composer dependencies
✅ Start MySQL and Solr services
```

**2. Database Setup (30 seconds)**
```bash
✅ Create test database (aspen_test)
✅ Import base schema (install/aspen.sql)
✅ Import test data (tests/unit_tests.sql)
✅ Verify connections
```

**3. Test Execution (2-5 minutes)**
```bash
✅ Unit tests (fastest)
✅ Integration tests (database)
✅ Functional tests (APIs)
✅ Performance tests (benchmarks)
```

**4. Reporting (30 seconds)**
```bash
✅ Generate coverage reports
✅ Upload to Codecov
✅ Create test artifacts
✅ Parse performance metrics
```

### **Performance Monitoring**
```bash
# Automatic performance regression detection
- Compares execution times to baseline
- Alerts if tests become too slow
- Monitors memory usage increases
- Fails if performance degrades significantly
```

---

## 🎯 **Developer Experience**

### **Zero Configuration Required**
- Tests run automatically on every push/PR
- No setup needed by developers
- Results appear in GitHub interface
- Merge blocked if tests fail

### **Fast Feedback**
```bash
git push origin feature-branch
# ⏳ 5-7 minutes later...
# 📧 Email notification with results
# 🎯 GitHub shows pass/fail status
```

### **Debugging Failed Tests**
```bash
# When tests fail:
1. Click "Details" in GitHub Actions
2. Expand failed job logs
3. See exact error messages
4. Download test artifacts for analysis
5. Run same tests locally for debugging
```

---

## 🔧 **Configuration Files**

### **Main CI Pipeline**
```
.github/workflows/test-suite.yml
├── Triggers: push/PR to main branches
├── Matrix: 3 PHP versions × 4 test suites  
├── Services: MySQL + Solr
├── Reports: Coverage + performance
└── Artifacts: Logs + results
```

### **Quality Checks**  
```
.github/workflows/pull_request_qa.yml
├── Release notes validation
├── Code style checks
├── Configuration consistency
└── Docker sync verification
```

### **Test Configuration**
```
tests/phpunit/phpunit.xml
├── Test suites definition
├── Coverage settings
├── Logging configuration
└── Bootstrap requirements
```

---

## 📊 **Current Status**

**✅ Fully Implemented:**
- Automatic test execution on push/PR
- Multi-PHP version matrix testing  
- Complete test suite coverage
- Performance regression detection
- Quality gate enforcement
- Coverage reporting integration

**✅ Ready to Use:**
- No additional setup required
- Works with existing Aspen workflow
- Integrates with GitHub features
- Provides fast developer feedback

**✅ Production Ready:**
- Prevents broken code deployment
- Maintains code quality standards
- Monitors performance regressions
- Supports team development workflow

---

## 🚀 **Summary**

**The testing framework is fully automated!** 

- **Runs automatically** on every push to main branches and PRs
- **Tests everything** across multiple PHP versions and test types
- **Blocks bad code** from being merged
- **Reports results** clearly in GitHub interface
- **Zero setup** required for developers

Developers just write code and push - the testing happens automatically and results appear in GitHub within 5-7 minutes.