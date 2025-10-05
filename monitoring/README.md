# Monitoring & Observability Setup

This directory contains all monitoring, alerting, and observability configurations for COR4EDU SMS production system.

## Files

- **`slo-definitions.md`** - Service Level Objectives and monitoring strategy
- **`alert-policies.yaml`** - Cloud Monitoring alert policy configurations
- **`dashboard.json`** - Cloud Monitoring dashboard definition
- **`deploy-monitoring.sh`** - Deployment script for monitoring configuration

## Quick Start

### 1. Deploy Monitoring Dashboard

```bash
# From project root
cd monitoring

# Create the dashboard in Cloud Monitoring
gcloud monitoring dashboards create --config-from-file=dashboard.json --project=sms-edu-47
```

### 2. Configure Alert Notification Channels

Before deploying alerts, create notification channels:

```bash
# Create email notification channel
gcloud alpha monitoring channels create \
  --display-name="COR4EDU SMS Alerts" \
  --type=email \
  --channel-labels=email_address=admin@cor4edu.com \
  --project=sms-edu-47

# List channels to get IDs
gcloud alpha monitoring channels list --project=sms-edu-47
```

### 3. Deploy Alert Policies

Update `alert-policies.yaml` with your notification channel IDs, then:

```bash
# Deploy each alert policy
gcloud alpha monitoring policies create \
  --policy-from-file=alert-policies.yaml \
  --project=sms-edu-47
```

## Health Check Endpoint

The application exposes a health check endpoint at `/health.php` that:

- Returns 200 OK when healthy
- Returns 503 Service Unavailable when degraded
- Checks database connectivity, filesystem, and PHP extensions

**Usage:**
```bash
curl https://sms-edu-938209083489.us-central1.run.app/health.php
```

## Structured Logging

The application uses Monolog for structured logging. Logs are automatically sent to Cloud Logging.

**Log Levels:**
- **ERROR**: System failures, 5xx errors, database issues
- **WARN**: Degraded performance, retry attempts, permission denials
- **INFO**: Successful operations, user actions (audit trail)
- **DEBUG**: Detailed diagnostic info (local only)

**Example Usage:**
```php
use Cor4Edu\Infrastructure\Logger;

$logger = Logger::getInstance();

// Log authentication
$logger->logAuthentication('username', true, '192.168.1.1');

// Log data access (FERPA audit trail)
$logger->logDataAccess($staffID, 'student', $studentID, 'view');

// Log payment (financial audit trail)
$logger->logPayment($paymentID, $studentID, 100.00, 'credit_card', $staffID);

// Log security event
$logger->logSecurityEvent('brute_force_attempt', 'Multiple failed logins detected');

// Log performance
$logger->logPerformance('generate_report', 1234.5);
```

## Viewing Logs

**Cloud Logging:**
```bash
# View all logs
gcloud logging read "resource.type=cloud_run_revision AND resource.labels.service_name=sms-edu" \
  --project=sms-edu-47 \
  --limit=50 \
  --format=json

# View errors only
gcloud logging read "resource.type=cloud_run_revision AND resource.labels.service_name=sms-edu AND severity>=ERROR" \
  --project=sms-edu-47 \
  --limit=20

# View authentication events
gcloud logging read "resource.type=cloud_run_revision AND jsonPayload.event_type=authentication" \
  --project=sms-edu-47 \
  --limit=20
```

**Web Console:**
https://console.cloud.google.com/logs/query?project=sms-edu-47

## SLO Tracking

### Current SLOs

| Metric | Target | Alert Threshold |
|--------|--------|-----------------|
| Availability | 99.5% | <99% over 1 hour |
| Latency (P95) | <2 seconds | >3s for 5 minutes |
| Error Rate | <1% | >5% for 10 minutes |
| Database Latency (P95) | <500ms | >1s for 5 minutes |

### Error Budget

**Monthly Error Budget:** 0.5% (from 99.5% SLO)

- If >50% consumed: Review and improve
- If exhausted: Freeze deployments
- If <10% consumed: Can take calculated risks

## Alert Response

When you receive an alert:

1. **Acknowledge** the alert (if using PagerDuty/OpsGenie)
2. **Check** the dashboard for context
3. **Follow** the runbook linked in alert documentation
4. **Document** actions taken in incident log
5. **Post-mortem** for P0/P1 incidents

## Runbooks

See `/docs/runbooks/` for detailed incident response procedures:

- `high-error-rate.md` - Error rate >5%
- `high-latency.md` - Latency issues
- `database-down.md` - Database connectivity failures
- `security-incident.md` - Security events
- `low-availability.md` - Availability below SLO

## Monitoring Best Practices

1. **Review dashboard daily** - Check for trends
2. **Investigate warnings** - Don't wait for critical alerts
3. **Update SLOs** - As system evolves, refine targets
4. **Test alerts** - Periodically verify alert delivery
5. **Document incidents** - Build institutional knowledge

## Cost Monitoring

Cloud Monitoring costs:
- Logs ingestion: ~$0.50/GB
- Metrics: First 150 MB free
- Dashboards: Free

**Optimize:**
- Set log retention policies (30-90 days)
- Sample high-volume debug logs
- Use structured logging (more efficient)

## Support

Questions? Contact: dev@cor4edu.com
