# Service Level Objectives (SLOs)
## COR4EDU SMS Production System

### Purpose
Define measurable reliability targets to ensure the system meets user expectations and business requirements. These SLOs drive our monitoring, alerting, and incident response strategy.

---

## 1. Availability SLO

**Target: 99.5% uptime** (43.8 hours downtime/year max)

**Measurement:**
- Monitor HTTP 2xx/3xx response codes vs 5xx errors
- Exclude planned maintenance windows
- Measure over rolling 30-day window

**Why 99.5%:**
- Educational institution - not 24/7 critical
- Allows ~3.6 hours/month for maintenance
- Balances reliability with operational costs

**Alert Threshold:** <99% over 1 hour (indicates trend toward SLO violation)

---

## 2. Latency SLO

**Target: 95th percentile < 2 seconds**

**Measurement:**
- Track request duration from Cloud Run metrics
- Measure at application level (not just network)
- Focus on critical user paths:
  - Student profile load
  - Payment processing
  - Report generation

**Why 2 seconds:**
- User perception: <2s feels responsive
- Educational admin work - not real-time trading
- Report generation may take longer (excluded from SLO)

**Alert Threshold:** P95 latency > 3s for 5 minutes

---

## 3. Error Rate SLO

**Target: <1% of requests result in 5xx errors**

**Measurement:**
- Count HTTP 5xx responses / total requests
- Exclude 4xx (client errors - not system failures)
- Measure over 1-hour windows

**Why 1%:**
- Most errors should be prevented by tests
- Some errors inevitable (database timeouts, etc.)
- 1% = 1 failure per 100 requests is acceptable

**Alert Threshold:** >5% error rate over 10 minutes (requires immediate attention)

---

## 4. Database Performance SLO

**Target: Query latency P95 < 500ms**

**Measurement:**
- Monitor Cloud SQL query performance metrics
- Track slow query log (>1s queries)
- Focus on frequently-used queries

**Why 500ms:**
- Most queries should be <100ms with indexes
- Some complex reports may be slower (acceptable)
- Prevents database from becoming bottleneck

**Alert Threshold:** P95 > 1s for 5 minutes

---

## 5. Security & Compliance SLO

**Target: Zero unauthorized data access incidents**

**Measurement:**
- Monitor authentication failures (>10 failures/minute)
- Track permission denied logs
- Audit trail completeness (FERPA requirement)

**Alert Threshold:**
- >50 auth failures from single IP (potential brute force)
- Any SQL injection attempt detected

---

## Monitoring Strategy

### Health Check Endpoint
Create `/health` endpoint that checks:
- Database connectivity
- File system write access
- Critical services availability

Returns:
- 200 OK: All systems operational
- 503 Service Unavailable: Degraded/down

### Log Levels
- **ERROR**: System failures, 5xx errors, database connection lost
- **WARN**: Degraded performance, retry attempts, permission denials
- **INFO**: Successful operations, user actions (audit trail)
- **DEBUG**: Detailed diagnostic info (disabled in production)

### Alert Priorities

**P0 - Critical (Page immediately):**
- Error rate >10%
- Availability <95%
- Database unreachable
- Security breach detected

**P1 - High (Alert within 15 min):**
- Error rate >5%
- Latency P95 >5s
- Approaching SLO violation

**P2 - Medium (Alert within 1 hour):**
- Latency P95 >3s
- Warning logs increasing
- Disk space >80%

**P3 - Low (Daily digest):**
- Performance trends
- Usage statistics
- Recommendations

---

## Error Budget

**Monthly Error Budget:** 0.5% (from 99.5% SLO)

**Budget Tracking:**
- If error budget >50% consumed: Review and improve
- If error budget exhausted: Freeze deployments until improved
- If error budget <10% consumed: Can take more risks

**Current Status:** Track in monitoring dashboard

---

## Next Steps

1. ✅ Define SLOs (this document)
2. ⏳ Create Cloud Monitoring dashboard
3. ⏳ Configure alert policies
4. ⏳ Implement structured logging
5. ⏳ Create runbooks for common incidents
