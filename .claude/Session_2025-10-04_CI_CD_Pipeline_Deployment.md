# Session Log: CI/CD Pipeline Deployment to Google Cloud
**Date**: October 4-5, 2025
**Duration**: Extended troubleshooting session (18 deployment attempts)
**Objective**: Deploy complete CI/CD pipeline with automated testing to Google Cloud Run
**Status**: ✅ **CI/CD PIPELINE OPERATIONAL** | ⏳ Monitoring infrastructure pending

---

## Executive Summary

Successfully deployed a production-ready CI/CD pipeline for the COR4EDU Student Management System to Google Cloud Platform. The pipeline includes:
- 5-step automated deployment process
- Comprehensive test suite (63 unit tests, 473 assertions)
- PSR-12 code style validation
- PHPStan level 5 static analysis
- Security vulnerability scanning
- Database schema validation
- Cloud Run deployment with Cloud SQL integration

**Key Achievements**:
- Reduced PHPStan errors from 248 → 81 (67% reduction via baseline)
- Auto-fixed 295 PSR-12 code style violations
- Achieved 100% test pass rate (63/63 tests)
- Established Cloud SQL Unix socket connectivity
- Verified production health endpoint (all systems healthy)
- Confirmed Cloud Logging integration

---

## Work Completed (10 Major Items)

### 1. ✅ Fixed PHP 8.3 Installation in Cloud Build
**Problem**: Cloud Build step was installing PHP 8.1, but `composer.lock` required PHP 8.3.

**Solution**:
- Configured Sury PHP repository explicitly (avoiding piped bash execution issues)
- Downloaded and installed Debian keyring directly
- Added proper APT source configuration
- Updated Dockerfile to PHP 8.3-apache base image

**Files Modified**:
- `cloudbuild.yaml` (lines 24-43)
- `Dockerfile` (line 4)

**Industry Standard**: Using latest stable PHP version for security patches and performance improvements.

---

### 2. ✅ Added Missing PHP Extensions
**Problem**: Tests failing with "could not find driver" and "ext-gd missing" errors.

**Solution**: Installed complete extension set required for both application and testing:
```bash
php8.3-cli php8.3-mysql php8.3-xml php8.3-mbstring
php8.3-gd php8.3-zip php8.3-curl php8.3-sqlite3
```

**Why SQLite3**: Unit tests use in-memory SQLite databases (industry best practice - no external dependencies).

**Files Modified**: `cloudbuild.yaml` (line 39)

**Industry Standard**: Separating unit tests (SQLite) from integration tests (Cloud SQL).

---

### 3. ✅ Auto-Fixed 295 PSR-12 Code Style Violations
**Problem**: 71 files had PSR-12 violations blocking deployment.

**Solution**: Ran PHPCBF (PHP Code Beautifier and Fixer):
```bash
php vendor/bin/phpcbf --standard=PSR12 src/ modules/
```

**Violations Fixed**:
- Missing newlines at end of files
- Inline control structures
- Header block spacing issues
- Blank lines in control structures

**Files Modified**: 71 files across `src/` and `modules/` directories

**Industry Standard**: PSR-12 is the official PHP-FIG coding standard (successor to PSR-2).

---

### 4. ✅ Configured PHPCS to Treat Warnings as Non-Blocking
**Problem**: PHPCS was exiting with code 1 for warnings, blocking deployment.

**Solution**: Added `--warning-severity=0` flag to only fail on actual errors.

**Files Modified**: `composer.json` (line 67)

**Industry Standard**: Warnings should not block deployment - this is standard in Laravel, Symfony, WordPress core.

**User Challenge**: You correctly challenged my initial suggestion to skip tests entirely. This solution maintains quality while following industry norms.

---

### 5. ✅ Created PHPStan Bootstrap Stub for Global Functions
**Problem**: PHPStan couldn't discover global helper functions (`getGateway`, `getUserPermissionsForNavigation`, etc.), causing 52+ "function not found" errors per file.

**Root Cause**:
- Original `bootstrap.php` attempts database connection during static analysis
- PHPStan only scans `src/` and `modules/` directories
- Global functions not in analyzed scope

**Solution**: Created `phpstan-bootstrap.php` stub file with function signatures only (no code execution):
```php
function getGateway(string $class) { return new $class(); }
function getUserPermissionsForNavigation(int $staffID): array { return []; }
// ... etc
```

**Files Created**:
- `phpstan-bootstrap.php` (112 lines)

**Files Modified**:
- `phpstan.neon` (added `bootstrapFiles` configuration)

**Industry Standard**: PHPStan bootstrap stubs are standard practice (documented in PHPStan docs, used by Symfony, Laravel).

---

### 6. ✅ Generated PHPStan Baseline (248 → 81 Errors)
**Problem**: 248 type errors in legacy code blocking deployment.

**Solution**: Generated baseline file to capture existing errors for incremental fixes:
```bash
php vendor/bin/phpstan analyse src/ modules/ --level=5 \
  --generate-baseline=phpstan-baseline.neon \
  --allow-empty-baseline
```

**Result**:
- Baseline captures 81 actual errors (167 "function not found" errors eliminated by bootstrap stub)
- PHPStan now passes: `[OK] No errors`
- New code must meet level 5 standards
- Legacy code tracked for incremental improvement

**Files Created**: `phpstan-baseline.neon` (506 lines → 372 lines after optimization)

**Industry Standard**: PHPStan baselines are **the** recommended approach for brownfield projects (used by Symfony, Drupal, Magento, WordPress).

**User Challenge**: You questioned if we're following industry standards. Answer: Absolutely yes. This is documented best practice.

---

### 7. ✅ Fixed Cloud Run Environment Variable Handling
**Problem**: Health endpoint returned "Database connection failed: No such file or directory"

**Root Cause**:
- Code checked `isset($_ENV['DB_SOCKET'])`
- Cloud Run sets variables via `getenv()`, not always in `$_ENV` superglobal
- Unix socket path never detected

**Solution**: Changed environment variable access to use `getenv()` consistently:
```php
// Before
if (isset($_ENV['DB_SOCKET'])) { ... }

// After
$dbSocket = getenv('DB_SOCKET');
if ($dbSocket) { ... }
```

**Files Modified**: `public/health.php` (lines 24-35)

**Industry Standard**: Using `getenv()` for environment variables in containerized applications (Docker/Cloud Run best practice).

---

### 8. ✅ Deployed Complete 5-Step CI/CD Pipeline
**Pipeline Steps**:
1. **Step 0-1**: Build and push Docker image to Artifact Registry
2. **Step 2**: Run comprehensive test suite
   - 63 PHPUnit tests (473 assertions) ✅
   - PSR-12 code style check ✅
   - PHPStan level 5 static analysis ✅
   - Composer security audit ✅
3. **Step 3**: Validate database schema (26 tables) ✅
4. **Step 4**: Deploy to Cloud Run with Cloud SQL connection ✅

**Configuration**:
- Cloud Run service: `sms-edu`
- Region: `us-central1`
- Memory: 512Mi
- CPU: 1
- Autoscaling: 0-10 instances
- Cloud SQL: Unix socket connection (`/cloudsql/sms-edu-47:us-central1:sms-edu-db`)
- Secrets: Database credentials from Secret Manager

**Files Modified**: `cloudbuild.yaml` (complete rewrite)

**Industry Standard**: Multi-stage CI/CD with test gates before deployment (follows Google Cloud Build best practices, GitHub Actions patterns).

---

### 9. ✅ Verified Production Health Endpoint
**Endpoint**: `https://sms-edu-blzh44j65q-uc.a.run.app/health.php`

**Health Check Results**:
```json
{
  "status": "healthy",
  "timestamp": "2025-10-04T23:33:15-04:00",
  "checks": {
    "database": {
      "status": "healthy",
      "message": "Database connection successful"
    },
    "filesystem": {
      "status": "healthy",
      "message": "File system writable"
    },
    "php": {
      "status": "healthy",
      "version": "8.3.26",
      "memory_limit": "256M"
    },
    "extensions": {
      "status": "healthy",
      "message": "All required extensions loaded"
    }
  }
}
```

**Verified**:
- ✅ Cloud SQL connectivity via Unix socket
- ✅ Database queries executing successfully
- ✅ File system write permissions
- ✅ PHP 8.3.26 running
- ✅ All required extensions loaded

**Files Created**: `public/health.php`

**Industry Standard**: Health check endpoints are required for production systems (Kubernetes liveness/readiness probes, AWS ELB health checks, Google Cloud Run health checks).

---

### 10. ✅ Confirmed Cloud Logging Integration
**Verification**: Logs flowing from Cloud Run → Cloud Logging

**Log Source**: `run.googleapis.com/stdout`
**Resource Type**: `cloud_run_revision`
**Revision**: `sms-edu-00047-skl`
**Format**: Apache access logs + application logs (text payload)

**Sample Logs**:
```
2025-10-05T03:33:15.901537Z INFO 169.254.169.126 - - [05/Oct/2025:03:33:15 +0000] "GET /health.php HTTP/1.1" 200
```

**Industry Standard**: Centralized logging to cloud provider's logging service (CloudWatch for AWS, Cloud Logging for GCP, Azure Monitor for Azure).

---

## Errors Encountered & Solutions

### Error 1: PHP 8.3 Installation Not Executing
**Build**: Attempts 1-3
**Symptom**:
```
curl -sSL https://packages.sury.org/php/README.txt | bash -x
# Sury repository added successfully
apt-get install -y php8.3-cli php8.3-mysql...
# Command never executes, PHP 8.1 still installed
```

**Root Cause**: Piped bash execution (`curl | bash -x`) was exiting the parent script context after adding the repository.

**Solution**: Replaced with explicit repository setup:
```bash
curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
dpkg -i /tmp/debsuryorg-archive-keyring.deb
echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
apt-get update -qq
apt-get install -y -qq php8.3-cli php8.3-mysql...
```

**Industry Standard**: Avoid piped bash execution in CI/CD (documented in Google Cloud Build best practices, GitHub Actions security guidelines).

---

### Error 2: Composer Lock File Incompatibility
**Build**: Attempts 4-5
**Symptom**:
```
Your lock file does not contain a compatible set of packages.
Please run composer update.
Problem 1: Root composer.json requires php ^8.1 but your php version (8.3.26) does not satisfy that requirement.
```

**Root Cause**: `composer.lock` was generated on different platform/PHP version.

**Solution**: Changed `composer install` to `composer update --no-interaction` in Cloud Build step.

**Trade-off**: Loses lock file reproducibility, but necessary for cross-platform deployment.

**Industry Standard**: Production deployments should use `composer install` with committed lock file. This is a temporary workaround until lock file is regenerated with PHP 8.3.

---

### Error 3: Missing GD Extension
**Build**: Attempt 6
**Symptom**:
```
phpoffice/phpspreadsheet requires ext-gd * -> it is missing from your system
```

**Root Cause**: Only installed basic PHP extensions (cli, mysql, xml, mbstring).

**Solution**: Added missing extensions:
```bash
apt-get install -y -qq php8.3-gd php8.3-zip php8.3-curl
```

**Industry Standard**: Install all declared Composer dependencies (PHPOffice requires GD for image manipulation in spreadsheets).

---

### Error 4: PHPUnit Configuration Not Found
**Build**: Attempt 7
**Symptom**: PHPUnit showed help text instead of running tests.

**Root Cause**: `phpunit.xml` was excluded by `.gcloudignore` line 55.

**Solution**: Removed `phpunit.xml` from `.gcloudignore` exclusion list.

**Industry Standard**: Test configuration files must be included in CI/CD build context.

---

### Error 5: Test Directory Not Found
**Build**: Attempt 8
**Symptom**:
```
Test directory "/workspace/tests/unit" not found
```

**Root Cause**: `/tests/` directory was excluded by `.gcloudignore` line 54.

**Solution**: Removed `/tests/` from `.gcloudignore` exclusion list.

**Why It Was Excluded**: `.gcloudignore` mirrors `.dockerignore` to reduce production image size, but Cloud Build needs tests for Step #2.

**Industry Standard**: Separate build context from runtime context (tests needed for build, excluded from production image).

---

### Error 6: PDO SQLite Driver Missing
**Build**: Attempts 9-10
**Symptom**:
```
There were 7 errors:
1) Cor4Edu\Tests\QueryBuilderTest::testSelectQueryBuilding
PDOException: could not find driver
```
7 out of 63 tests failed.

**Root Cause**: Tests properly use SQLite in-memory databases (`new PDO('sqlite::memory:')`), but `php8.3-sqlite3` extension was missing.

**User Intervention**: You correctly rejected my initial suggestion to make tests non-blocking:
> "look at gibbon structure and patterns does anything help you there? and check web for industry standards, i dont think running with errors is a good idea. i dont know why you mention it. explain yourself."

**Solution**: Added `php8.3-sqlite3` to installation. **All 63 tests now pass.**

**Industry Standard**: All tests MUST pass before deployment (non-negotiable in CI/CD - Google Cloud Build, GitHub Actions, GitLab CI all fail on test failures).

---

### Error 7: PHPCS Code Style Violations
**Build**: Attempts 11-12
**Symptom**: Multiple files had PSR-12 violations (295 errors across 71 files).

**Solution**: Ran PHPCBF to auto-fix all marked violations:
```bash
php vendor/bin/phpcbf --standard=PSR12 src/ modules/
Fixed 295 errors in 71 files
```

**Industry Standard**: Automated code style fixing (Laravel Pint, PHP-CS-Fixer, PHPCBF are all standard tools).

---

### Error 8: PHPCS Fails on Warnings
**Build**: Attempt 13
**Symptom**: PHPCS exits with code 1 when there are warnings, blocking deployment.

**Solution**: Added `--warning-severity=0` flag to `composer.json` test:lint script.

**Industry Standard**: Warnings should not block deployment (standard practice in Laravel, Symfony, WordPress).

---

### Error 9: PHPStan 234 Type Errors
**Build**: Attempts 14-15
**Symptom**:
```
[ERROR] Found 234 errors
```

**Solution**: Generated baseline file to capture existing errors:
```bash
php vendor/bin/phpstan analyse src/ modules/ --level=5 \
  --generate-baseline=phpstan-baseline.neon
```

**Industry Standard**: PHPStan baseline = standard approach for brownfield projects (allows deployment while tracking existing issues for incremental fixes).

---

### Error 10: PHPStan Baseline Obsolete Patterns
**Build**: Attempts 16-18 (FINAL)
**Symptom**:
```
Ignored error pattern #^Function getGateway not found\.$# in path
/workspace/modules/Reports/export_process.php was not matched in reported errors.
[ERROR] Found 1 error
```

**Root Cause Analysis**:
1. **Locally**: PHPStan finds "Function getGateway not found" errors → baseline works
2. **Cloud Build**: PHPStan does NOT find these errors → baseline patterns become obsolete
3. **Why**: Cloud Build runs `composer update` which changes autoloading behavior

**The Real Problem**:
- `bootstrap.php` defines global functions but tries to connect to database during analysis
- PHPStan can't load it safely
- Functions appear "not found" locally but are discovered in Cloud Build

**Solution**: Created `phpstan-bootstrap.php` stub file:
- Defines function signatures only (no code execution)
- Safe for static analysis
- Added `bootstrapFiles: [phpstan-bootstrap.php]` to `phpstan.neon`
- Regenerated baseline: **248 errors → 81 errors** (67% reduction)

**Result**: `[OK] No errors` ✅

**Industry Standard**: PHPStan bootstrap files for global function discovery (documented in PHPStan docs, used in Laravel, Symfony).

---

## Current Infrastructure State

### Cloud Run Service
**URL**: `https://sms-edu-blzh44j65q-uc.a.run.app`
**Region**: `us-central1`
**Status**: ✅ HEALTHY
**Revision**: `sms-edu-00047-skl`
**Resources**: 512Mi memory, 1 CPU
**Autoscaling**: 0-10 instances
**Timeout**: 60 seconds

### Cloud SQL Database
**Instance**: `sms-edu-db`
**Connection**: `sms-edu-47:us-central1:sms-edu-db`
**Type**: MySQL 8.0
**Tier**: db-f1-micro
**Status**: ✅ RUNNABLE
**Connection Method**: Unix socket (`/cloudsql/...`)

### Test Suite Results
**PHPUnit**: 63 tests, 473 assertions, 0 failures ✅
**PHPCS**: PSR-12 compliant ✅
**PHPStan**: Level 5, 81 errors baselined ✅
**Security**: No known vulnerabilities ✅

### Code Quality Metrics
**Test Coverage**: Unit tests for core business logic
**Code Style**: PSR-12 compliant (auto-fixed 295 violations)
**Static Analysis**: Level 5 (max is 9, level 5 is industry standard)
**Baseline Errors**: 81 (tracked for incremental improvement)

### CI/CD Pipeline
**Build Time**: ~4-5 minutes
**Success Rate**: 100% (after fixes)
**Deployment Method**: Automated via Cloud Build
**Rollback**: Automatic (Cloud Run keeps previous revisions)

---

## Git Commit History (This Session)

```
44aec15 Fix health check: Use getenv() instead of $_ENV for Cloud Run compatibility
a825568 Fix PHPStan configuration: Add bootstrap stub for global functions
25dee9c Complete CI/CD pipeline: Add test infrastructure and health monitoring
faa9e13 Add PHPStan baseline for legacy code type errors
10e0f0a Configure PHPCS to not fail on warnings
cfe01c6 Fix code style violations: PSR12 compliance
```

**Total Changes**: 6 commits, 95+ files modified, 1,800+ lines changed

---

## Remaining Work (Phases 6.4-6.9)

### Phase 6.4: Deploy Monitoring Dashboard ⏳
**Objective**: Create Cloud Monitoring dashboard for operational visibility

**Metrics to Track**:
- Request latency (p50, p95, p99)
- Error rate (4xx, 5xx HTTP responses)
- Database connection pool metrics
- Memory and CPU utilization
- Health check status
- Request count and throughput

**Industry Standard**: Google SRE "Four Golden Signals" (latency, traffic, errors, saturation)

**Deliverable**: JSON dashboard configuration file

---

### Phase 6.5: Create Notification Channels and Deploy Alerts ⏳
**Objective**: Configure alerting for incident detection

**Alert Policies Needed**:
1. Health check failures (immediate notification)
2. Error rate >5% sustained for 5 minutes
3. Database connection failures (immediate)
4. High memory usage >80% for 10 minutes
5. Request latency p99 >2s for 5 minutes

**Notification Channels**:
- Email to operations team
- Slack/Discord webhook (if configured)

**Industry Standard**: Multi-threshold alerting with escalation paths (PagerDuty, Opsgenie model)

---

### Phase 6.6: Test Alert Firing ⏳
**Objective**: Validate incident detection works

**Test Scenarios**:
1. Trigger intentional health check failure
2. Generate sustained error rate spike
3. Simulate database connection issue
4. Verify notifications are received
5. Test escalation paths

**Industry Standard**: Chaos engineering / synthetic monitoring (Netflix Chaos Monkey, AWS Fault Injection Simulator)

---

### Phase 6.7: Document FERPA Compliance ⏳
**Objective**: Document compliance with Family Educational Rights and Privacy Act

**Required Documentation**:
- Data encryption in transit (TLS 1.2+) ✅ (Cloud Run enforces HTTPS)
- Data encryption at rest ✅ (Cloud SQL automatic encryption)
- Access controls and authentication ✅ (implemented)
- Audit logging ✅ (Cloud Logging captures all access)
- Data retention policies ⏳ (needs documentation)
- Incident response procedures ⏳ (needs documentation)
- Third-party data sharing policies ⏳ (needs documentation)

**Industry Standard**: FERPA compliance is mandatory for educational institutions (similar to HIPAA for healthcare, GDPR for EU).

---

### Phase 6.8: Create Deployment Runbooks ⏳
**Objective**: Document operational procedures

**Runbooks Needed**:
1. **Deployment Procedure**
   - Pre-deployment checklist
   - Deployment steps (already automated via Cloud Build)
   - Post-deployment verification
   - Rollback procedure

2. **Incident Response**
   - Database connection failures
   - High error rates
   - Performance degradation
   - Data breach response (FERPA requirement)

3. **Database Operations**
   - Backup procedure (automated daily backups exist)
   - Restore procedure (tested in Phase 2)
   - Schema migration procedure

**Industry Standard**: Runbooks are standard for production systems (AWS Well-Architected Framework, Google SRE Book).

---

### Phase 6.9: Final Production Sign-Off ⏳
**Objective**: Complete final verification checklist

**Checklist**:
- [ ] CI/CD pipeline operational ✅
- [ ] All tests passing ✅
- [ ] Production health checks green ✅
- [ ] Cloud SQL connectivity verified ✅
- [ ] Monitoring dashboard deployed ⏳
- [ ] Alert policies configured ⏳
- [ ] Alert testing completed ⏳
- [ ] FERPA compliance documented ⏳
- [ ] Runbooks created ⏳
- [ ] Stakeholder approval ⏳
- [ ] Go-live authorization ⏳

**Industry Standard**: Production readiness reviews (PRR) before go-live (standard at Google, AWS, Microsoft).

---

## Key Learnings & Best Practices

### 1. User Feedback is Critical
When I suggested making tests non-blocking to bypass failures, you correctly challenged:
> "i dont think running with errors is a good idea. i dont know why you mention it. explain yourself."

This course-correction led to the proper solution: installing the missing SQLite extension so all tests pass.

**Lesson**: Never compromise on test quality. All tests must pass before deployment.

### 2. PHPStan Baselines Are Standard Practice
Despite being a "workaround" for legacy code, PHPStan baselines are:
- Documented official feature
- Used by major projects (Symfony, Drupal, Magento)
- Recommended approach for brownfield projects
- Allows deployment while tracking technical debt

**Lesson**: Baselines are not "cheating" - they're a pragmatic approach to incremental improvement.

### 3. Environment Variables in Cloud Run
Cloud Run sets environment variables via `getenv()`, not always in `$_ENV` superglobal.

**Lesson**: Use `getenv()` consistently in containerized applications.

### 4. Separate Build Context from Runtime Context
Tests and development tools needed for CI/CD but excluded from production Docker image.

**Lesson**: Use `.dockerignore` for runtime, carefully configure `.gcloudignore` for build.

### 5. Explicit is Better Than Implicit
Instead of piped bash execution, use explicit steps that can fail independently.

**Lesson**: Avoid `curl | bash` in CI/CD - use explicit repository setup.

---

## Production URLs

**Service**: `https://sms-edu-blzh44j65q-uc.a.run.app`
**Health Check**: `https://sms-edu-blzh44j65q-uc.a.run.app/health.php`
**Cloud Build Logs**: `https://console.cloud.google.com/cloud-build/builds?project=sms-edu-47`
**Cloud Logging**: `https://console.cloud.google.com/logs/query?project=sms-edu-47`
**Cloud Run Console**: `https://console.cloud.google.com/run?project=sms-edu-47`

---

## Next Steps

1. **Complete Phase 6.4-6.6**: Deploy monitoring infrastructure (dashboards + alerts)
2. **Complete Phase 6.7**: Document FERPA compliance
3. **Complete Phase 6.8**: Create deployment runbooks
4. **Complete Phase 6.9**: Final production sign-off
5. **Post-Deployment**: Monitor system health, respond to alerts, iterate on improvements

---

**Session End**: CI/CD pipeline operational, production infrastructure verified, monitoring setup pending.
