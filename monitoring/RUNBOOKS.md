# Deployment Runbooks
## COR4EDU Student Management System - Google Cloud Operations

**Last Updated**: October 2025

---

## Table of Contents
1. [Standard Deployment](#1-standard-deployment)
2. [Emergency Rollback](#2-emergency-rollback)
3. [Database Restore](#3-database-restore)
4. [Incident Response](#4-incident-response)
5. [Health Check Troubleshooting](#5-health-check-troubleshooting)
6. [Performance Issues](#6-performance-issues)

---

## 1. Standard Deployment

### Prerequisites
- [ ] All changes committed to git
- [ ] Local tests passing (`composer test`)
- [ ] Code reviewed (if applicable)

### Deployment Steps

```bash
# Step 1: Commit changes
git add .
git commit -m "Description of changes

🤖 Generated with Claude Code
Co-Authored-By: Claude <noreply@anthropic.com>"

# Step 2: Push to repository
git push origin main

# Step 3: Deploy to Cloud Run (triggers CI/CD pipeline)
gcloud builds submit --config=cloudbuild.yaml --project=sms-edu-47

# Step 4: Monitor build progress
# Build logs will stream in terminal
# Watch for:
# - ✅ Step #2: Test Suite (PHPUnit, PHPCS, PHPStan)
# - ✅ Step #3: Schema Validation
# - ✅ Step #4: Cloud Run Deployment

# Step 5: Verify deployment
curl https://sms-edu-938209083489.us-central1.run.app/health.php | jq .

# Expected output:
# {
#   "status": "healthy",
#   "timestamp": "...",
#   "checks": {
#     "database": { "status": "healthy" },
#     "filesystem": { "status": "healthy" },
#     "php": { "status": "healthy" },
#     "extensions": { "status": "healthy" }
#   }
# }

# Step 6: Check application logs
gcloud logging read "resource.type=cloud_run_revision AND resource.labels.service_name=sms-edu" \
  --limit=20 --format=json --project=sms-edu-47

# Step 7: Smoke test critical features
# - Login
# - View student list
# - Create/Edit student record
# - Generate report
```

### Expected Duration
- Build time: 5-7 minutes
- Total deployment: 8-10 minutes

### Rollback Criteria
Roll back if:
- Health check returns "unhealthy"
- Error rate >10% within 5 minutes
- Critical feature broken
- Database connectivity issues

---

## 2. Emergency Rollback

### When to Roll Back
- Production errors affecting users
- Failed health checks
- Critical security vulnerability discovered
- Data corruption detected

### Rollback Steps

```bash
# Option A: Roll back to previous Cloud Run revision (FASTEST)

# Step 1: List recent revisions
gcloud run revisions list --service=sms-edu --region=us-central1 --project=sms-edu-47

# Output example:
# REVISION                 ACTIVE  SERVICE   DEPLOYED                 DEPLOYED BY
# sms-edu-00015-abc       yes     sms-edu   2025-10-04 15:30:00 UTC  user@example.com
# sms-edu-00014-def       no      sms-edu   2025-10-03 10:15:00 UTC  user@example.com

# Step 2: Route 100% traffic to previous revision
gcloud run services update-traffic sms-edu \
  --to-revisions=sms-edu-00014-def=100 \
  --region=us-central1 \
  --project=sms-edu-47

# Step 3: Verify rollback
curl https://sms-edu-938209083489.us-central1.run.app/health.php

# Duration: 30-60 seconds

# Option B: Roll back code and redeploy (if revision deleted)

# Step 1: Find last good commit
git log --oneline -10

# Step 2: Create rollback branch
git checkout -b rollback-emergency <commit-hash>

# Step 3: Redeploy
gcloud builds submit --config=cloudbuild.yaml --project=sms-edu-47

# Duration: 8-10 minutes
```

### Post-Rollback Actions
1. Document the issue in incident log
2. Create GitHub issue for the bug
3. Fix the issue in development
4. Test thoroughly before redeploying
5. Notify stakeholders of resolution

---

## 3. Database Restore

### When to Restore
- Data corruption detected
- Accidental mass deletion
- Failed migration
- Security breach requiring clean state

### Restore Steps

```bash
# Step 1: List available backups
gcloud sql backups list --instance=sms-edu-instance --project=sms-edu-47

# Output example:
# ID    WINDOW_START_TIME                      STATUS
# 123   2025-10-04T03:00:00.000Z              SUCCESSFUL
# 122   2025-10-03T03:00:00.000Z              SUCCESSFUL

# Step 2: CRITICAL - Stop application traffic (prevents data inconsistency)
gcloud run services update sms-edu \
  --min-instances=0 \
  --max-instances=0 \
  --region=us-central1 \
  --project=sms-edu-47

# Step 3: Restore backup
gcloud sql backups restore 123 \
  --backup-instance=sms-edu-instance \
  --instance=sms-edu-instance \
  --project=sms-edu-47

# This will prompt for confirmation - type 'y'

# Step 4: Monitor restore progress
gcloud sql operations list --instance=sms-edu-instance --project=sms-edu-47

# Duration: 5-15 minutes depending on database size

# Step 5: Verify data integrity
# Connect to database and run verification queries

# Step 6: Restart application
gcloud run services update sms-edu \
  --min-instances=0 \
  --max-instances=10 \
  --region=us-central1 \
  --project=sms-edu-47

# Step 7: Verify health
curl https://sms-edu-938209083489.us-central1.run.app/health.php
```

### Important Notes
- **Data Loss**: Any data created after the backup will be lost
- **Downtime**: 15-30 minutes typical
- **Notify Users**: Always notify users before planned restores
- **Test First**: Consider restoring to a new instance for testing

---

## 4. Incident Response

### Incident Severity Levels

**P0 - Critical (Immediate Response)**
- Complete service outage
- Data breach or security incident
- Data loss or corruption

**P1 - High (Response within 1 hour)**
- Partial service degradation
- Health check failures
- Error rate >25%

**P2 - Medium (Response within 4 hours)**
- Non-critical feature broken
- Performance degradation
- Error rate 10-25%

**P3 - Low (Response within 24 hours)**
- Minor bugs
- Cosmetic issues
- Single user reports

### Response Procedure

```bash
# Step 1: Assess severity and triage
# Check monitoring dashboard:
# https://console.cloud.google.com/monitoring/dashboards?project=sms-edu-47

# Step 2: Collect diagnostic information

# Get recent logs
gcloud logging read "resource.type=cloud_run_revision AND severity>=ERROR" \
  --limit=100 --format=json --project=sms-edu-47 \
  > incident-logs-$(date +%Y%m%d-%H%M%S).json

# Get current service status
gcloud run services describe sms-edu --region=us-central1 --project=sms-edu-47 \
  > service-status-$(date +%Y%m%d-%H%M%S).txt

# Check database status
gcloud sql instances describe sms-edu-instance --project=sms-edu-47

# Step 3: Identify root cause
# Common issues:
# - Database connection timeouts
# - Memory limit exceeded
# - External API failures
# - Code bugs

# Step 4: Implement fix
# - If code issue: Fix and redeploy (See Standard Deployment)
# - If database issue: See Database Restore
# - If configuration issue: Update environment variables
# - If external dependency: Contact vendor or implement fallback

# Step 5: Verify resolution
curl https://sms-edu-938209083489.us-central1.run.app/health.php

# Step 6: Document incident
# Create file: .claude/incident-YYYYMMDD-description.md
# Include:
# - Timeline of events
# - Root cause analysis
# - Steps taken
# - Prevention measures
```

---

## 5. Health Check Troubleshooting

### Symptom: Health endpoint returns 503

```bash
# Step 1: Check health endpoint
curl -i https://sms-edu-938209083489.us-central1.run.app/health.php

# Step 2: Analyze response
# If database check fails:
{
  "status": "unhealthy",
  "checks": {
    "database": {
      "status": "unhealthy",
      "error": "SQLSTATE[HY000] [2002] Connection refused"
    }
  }
}

# Troubleshooting steps:

# A. Verify Cloud SQL instance is running
gcloud sql instances describe sms-edu-instance --project=sms-edu-47 | grep state
# Expected: state: RUNNABLE

# B. Check Cloud SQL connections
gcloud sql operations list --instance=sms-edu-instance --limit=5 --project=sms-edu-47

# C. Verify environment variables
gcloud run services describe sms-edu --region=us-central1 --project=sms-edu-47 | grep -A 10 env

# D. Check Cloud SQL proxy connection
# View Cloud Run logs for connection errors
gcloud logging read "resource.type=cloud_run_revision AND textPayload=~\"Cloud SQL\"" \
  --limit=20 --project=sms-edu-47

# E. Test database connectivity manually
# This requires Cloud SQL proxy installed locally
# cloud_sql_proxy -instances=sms-edu-47:us-central1:sms-edu-instance=tcp:3306
```

### Symptom: Filesystem check fails

```bash
# Usually indicates permissions issues or disk full

# Check Cloud Run logs
gcloud logging read "resource.type=cloud_run_revision AND textPayload=~\"filesystem\"" \
  --limit=20 --project=sms-edu-47

# Cloud Run uses ephemeral storage - should not fill up
# If this occurs, check for:
# - Large file uploads not being cleaned up
# - Temp file accumulation
# - Log file growth
```

---

## 6. Performance Issues

### Symptom: Slow response times

```bash
# Step 1: Check current performance metrics
# View in dashboard:
# https://console.cloud.google.com/monitoring/dashboards?project=sms-edu-47

# Or via CLI:
gcloud monitoring time-series list \
  --filter='metric.type="run.googleapis.com/request_latencies"' \
  --project=sms-edu-47

# Step 2: Identify bottleneck

# Check database query performance
gcloud logging read "resource.type=cloud_run_revision AND jsonPayload.query" \
  --limit=50 --project=sms-edu-47

# Check memory usage
gcloud logging read "resource.type=cloud_run_revision AND jsonPayload.message=~\"memory\"" \
  --limit=20 --project=sms-edu-47

# Step 3: Common solutions

# A. Increase memory allocation (if memory-constrained)
gcloud run services update sms-edu \
  --memory=1Gi \
  --region=us-central1 \
  --project=sms-edu-47

# B. Increase CPU allocation (if CPU-constrained)
gcloud run services update sms-edu \
  --cpu=2 \
  --region=us-central1 \
  --project=sms-edu-47

# C. Increase max instances (if traffic spike)
gcloud run services update sms-edu \
  --max-instances=20 \
  --region=us-central1 \
  --project=sms-edu-47

# D. Add minimum instances (to reduce cold starts)
gcloud run services update sms-edu \
  --min-instances=1 \
  --region=us-central1 \
  --project=sms-edu-47

# Note: Minimum instances increase cost
```

### Symptom: Database connection pool exhausted

```bash
# Check for connection leaks in code
# Look for unclosed PDO connections

# Increase Cloud SQL max connections (if needed)
gcloud sql instances patch sms-edu-instance \
  --database-flags=max_connections=100 \
  --project=sms-edu-47
```

---

## Quick Reference: Essential Commands

```bash
# View service status
gcloud run services describe sms-edu --region=us-central1 --project=sms-edu-47

# View recent logs (last 20 entries)
gcloud logging read "resource.type=cloud_run_revision" --limit=20 --project=sms-edu-47

# View error logs only
gcloud logging read "resource.type=cloud_run_revision AND severity>=ERROR" --limit=50 --project=sms-edu-47

# Check health
curl https://sms-edu-938209083489.us-central1.run.app/health.php | jq .

# List revisions
gcloud run revisions list --service=sms-edu --region=us-central1 --project=sms-edu-47

# List backups
gcloud sql backups list --instance=sms-edu-instance --project=sms-edu-47

# Deploy
gcloud builds submit --config=cloudbuild.yaml --project=sms-edu-47

# Rollback traffic
gcloud run services update-traffic sms-edu --to-revisions=REVISION_NAME=100 --region=us-central1 --project=sms-edu-47
```

---

## Emergency Contacts

**System Administrator**: [Your Email]
**Google Cloud Support**: https://cloud.google.com/support
**Institution IT Support**: [Your Institution Contact]

---

## Maintenance Windows

**Preferred Deployment Times**:
- Weekdays: 6:00 AM - 8:00 AM (low traffic)
- Avoid: Monday 9:00 AM - 5:00 PM (peak registration)
- Avoid: End of semester (heavy usage)

**Backup Schedule**: Daily at 3:00 AM UTC (automatically)

---

## Document Version

| Date | Version | Changes |
|------|---------|---------|
| 2025-10-04 | 1.0 | Initial runbook creation |
