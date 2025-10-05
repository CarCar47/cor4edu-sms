# Alert Policy Setup Guide

## Overview
This guide explains how to set up critical alerts for the COR4EDU SMS application. Two alert policies have been configured:

1. **Health Check Failure** - Immediate notification when the /health.php endpoint returns 503
2. **High Error Rate** - Notification when error rate exceeds 10% for 5 minutes

## Option 1: Quick Setup via Cloud Console (Recommended for Solo Developer)

### Step 1: Set Up Email Notification Channel
1. Go to [Cloud Console Monitoring](https://console.cloud.google.com/monitoring/alerting/notifications?project=sms-edu-47)
2. Click **"Add new"** or **"Notification channels"**
3. Select **"Email"**
4. Enter your email address
5. Click **"Save"**

### Step 2: Create Health Check Alert
1. Go to [Alert Policies](https://console.cloud.google.com/monitoring/alerting/policies?project=sms-edu-47)
2. Click **"Create Policy"**
3. Click **"Add condition"**:
   - **Target**: Cloud Run Revision
   - **Metric**: Request count
   - **Filter**: 
     ```
     resource.type="cloud_run_revision"
     resource.labels.service_name="sms-edu"
     metric.labels.response_code="503"
     ```
   - **Condition**: Above threshold
   - **Threshold value**: 0
   - **Duration**: 0 minutes (immediate)
4. Click **"Next"**
5. Select your email notification channel
6. **Name**: "COR4EDU SMS - Health Check Failure"
7. **Documentation**:
   ```
   The SMS application health endpoint is failing. This indicates a critical system failure.
   
   Immediate Actions:
   1. Check Cloud Run logs: https://console.cloud.google.com/run/detail/us-central1/sms-edu/logs?project=sms-edu-47
   2. Verify database connectivity
   3. Check service status
   4. Review recent deployments
   ```
8. Click **"Create Policy"**

### Step 3: Create Error Rate Alert
1. Go to [Alert Policies](https://console.cloud.google.com/monitoring/alerting/policies?project=sms-edu-47)
2. Click **"Create Policy"**
3. Click **"Add condition"**:
   - **Target**: Cloud Run Revision
   - **Metric**: Request count
   - **Filter**:
     ```
     resource.type="cloud_run_revision"
     resource.labels.service_name="sms-edu"
     (metric.labels.response_code_class="4xx" OR metric.labels.response_code_class="5xx")
     ```
   - **Condition**: Above threshold
   - **Threshold value**: 0.1 (10%)
   - **Duration**: 5 minutes
4. Click **"Next"**
5. Select your email notification channel
6. **Name**: "COR4EDU SMS - High Error Rate"
7. **Documentation**:
   ```
   The SMS application is experiencing a high error rate (>10% of requests failing).
   
   Immediate Actions:
   1. Check Cloud Run logs for error patterns
   2. Review recent code deployments
   3. Check database performance
   4. Verify external service integrations
   5. Consider rollback if errors persist
   ```
8. Click **"Create Policy"**

## Option 2: Setup via gcloud CLI (Requires gcloud alpha components)

If you have gcloud alpha components installed:

### 1. Create Email Notification Channel
```bash
gcloud alpha monitoring channels create \
  --display-name="Developer Email" \
  --type=email \
  --channel-labels=email_address=YOUR_EMAIL@example.com \
  --project=sms-edu-47
```

### 2. Deploy Health Check Alert
```bash
gcloud alpha monitoring policies create \
  --policy-from-file=monitoring/alert-health-check.json \
  --notification-channels=CHANNEL_ID \
  --project=sms-edu-47
```

### 3. Deploy Error Rate Alert
```bash
gcloud alpha monitoring policies create \
  --policy-from-file=monitoring/alert-error-rate.json \
  --notification-channels=CHANNEL_ID \
  --project=sms-edu-47
```

## Testing Alerts

See Phase 6.6 in the project plan for alert testing procedures.

## What You'll Receive

When an alert triggers, you'll receive an email with:
- Alert name and severity
- Detailed documentation on what's wrong
- Step-by-step troubleshooting actions
- Direct links to relevant Cloud Console pages
- Graphs showing the metric that triggered the alert

## Viewing Active Alerts

Dashboard: https://console.cloud.google.com/monitoring/alerting?project=sms-edu-47

All configured alerts will appear here with their current status.
