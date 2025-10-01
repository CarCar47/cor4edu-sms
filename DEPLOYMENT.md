# COR4EDU SMS - Google Cloud Deployment & Permission Fix Guide

**Quick Fix for Permission/Tab Errors** | Complete deployment guide for COR4EDU SMS on Google Cloud

---

## 🚨 Quick Fix for Existing Deployments

### Problem
Your Cloud deployment has missing tabs (Reports, Permissions, Staff) and permission errors, but local works fine.

### Root Cause
Cloud SQL database is missing 3 critical permission tables:
- ❌ `cor4edu_system_permissions`
- ❌ `cor4edu_role_permission_defaults`
- ❌ `cor4edu_staff_role_types`

### Solution (5 minutes)

#### Step 1: Deploy Updated Code

```bash
# Commit new files
git add database_complete_schema.sql public/run_migration.php setup_database.php
git commit -m "Add permission system migration fix"
git push

# Deploy to Cloud
gcloud builds submit --config cloudbuild.yaml --project=sms-edu-47
```

#### Step 2: Run Web Migration

1. **Login to Cloud Run** as SuperAdmin:
   - URL: `https://sms-edu-938209083489.us-central1.run.app`
   - Username: `superadmin`
   - Password: `admin123`

2. **Navigate to migration script**:
   - Go to: `https://sms-edu-938209083489.us-central1.run.app/run_migration.php`

3. **Execute migration**:
   - Click "✅ Run Migration Now"
   - Wait for completion (5-10 seconds)
   - See "✅ MIGRATION COMPLETE!"

4. **Verify fix**:
   - Return to dashboard
   - Confirm **Reports**, **Permissions**, and **Staff** tabs appear
   - Test each tab functionality

#### Step 3: Security Cleanup (Optional)

```bash
# Remove migration script after successful execution
rm public/run_migration.php
git add -u
git commit -m "Remove migration script after execution"
git push
gcloud builds submit --config cloudbuild.yaml --project=sms-edu-47
```

---

## 📋 Files Created/Modified

### New Files
- **`database_complete_schema.sql`** - Complete schema with all tables (base + permissions)
- **`public/run_migration.php`** - Web-based migration tool (SuperAdmin only)
- **`DEPLOYMENT.md`** - This documentation

### Modified Files
- **`setup_database.php`** - Now uses `database_complete_schema.sql` instead of old schema
- **`bootstrap.php`** - Improved error logging for missing permission tables

---

## 🏗️ Architecture Overview

```
┌──────────────────────────────────────────────────┐
│         Google Cloud Project (sms-edu-47)        │
├──────────────────────────────────────────────────┤
│                                                   │
│  Cloud Run Service          Cloud SQL Instance   │
│  ┌────────────────┐        ┌──────────────────┐ │
│  │ cor4edu-sms    │───────>│  sms-edu-db      │ │
│  │ PHP 8.1/Apache │ Socket │  MySQL 8.0       │ │
│  │ Port: 8080     │        │  cor4edu_sms DB  │ │
│  └────────────────┘        └──────────────────┘ │
│         ↓                            ↓           │
│  ┌────────────────┐        ┌──────────────────┐ │
│  │ Artifact       │        │  Secret Manager  │ │
│  │ Registry       │        │  - DB Username   │ │
│  └────────────────┘        │  - DB Password   │ │
│                             └──────────────────┘ │
└──────────────────────────────────────────────────┘
```

---

## 🗄️ Database Table Structure

### Core Tables (Always Present)
- `cor4edu_staff` - Staff authentication and profiles
- `cor4edu_students` - Student records
- `cor4edu_programs` - Academic programs
- `cor4edu_documents` - File storage metadata
- `cor4edu_payments` - Payment records
- `cor4edu_academic_records` - Academic history
- `cor4edu_career_services` - Career tracking

### Permission System Tables (Were Missing in Cloud)
- `cor4edu_staff_role_types` ⭐ - Role definitions (Admissions, Bursar, etc.)
- `cor4edu_system_permissions` ⭐ - Master permission registry (34 permissions)
- `cor4edu_role_permission_defaults` ⭐ - Default permissions per role
- `cor4edu_staff_permissions` - Individual permission overrides
- `cor4edu_staff_tab_access` - Legacy tab access (deprecated)

---

## 🔧 Complete Fresh Deployment

### Prerequisites
- Google Cloud project (`sms-edu-47`)
- `gcloud` CLI installed and authenticated
- PHP 8.1+ for local testing

### 1. Google Cloud Setup

```bash
# Set project
gcloud config set project sms-edu-47

# Create Cloud SQL instance
gcloud sql instances create sms-edu-db \
  --database-version=MYSQL_8_0 \
  --tier=db-f1-micro \
  --region=us-central1 \
  --root-password=YOUR_STRONG_PASSWORD

# Create database
gcloud sql databases create cor4edu_sms \
  --instance=sms-edu-db

# Create secrets
echo -n "root" | gcloud secrets create cor4edu-db-username --data-file=-
echo -n "YOUR_PASSWORD" | gcloud secrets create cor4edu-db-password --data-file=-

# Grant access to secrets
PROJECT_NUMBER=$(gcloud projects describe sms-edu-47 --format='value(projectNumber)')
gcloud secrets add-iam-policy-binding cor4edu-db-username \
  --member="serviceAccount:${PROJECT_NUMBER}-compute@developer.gserviceaccount.com" \
  --role="roles/secretmanager.secretAccessor"
gcloud secrets add-iam-policy-binding cor4edu-db-password \
  --member="serviceAccount:${PROJECT_NUMBER}-compute@developer.gserviceaccount.com" \
  --role="roles/secretmanager.secretAccessor"

# Create Artifact Registry
gcloud artifacts repositories create cor4edu-containers \
  --repository-format=docker \
  --location=us-central1
```

### 2. Deploy Application

```bash
# Clone repository
git clone https://github.com/CarCar47/cor4edu-sms.git
cd cor4edu-sms

# Deploy to Cloud Run
gcloud builds submit --config cloudbuild.yaml --project=sms-edu-47
```

### 3. Initialize Database

```bash
# Get Cloud Run URL
CLOUD_RUN_URL=$(gcloud run services describe cor4edu-sms \
  --platform=managed \
  --region=us-central1 \
  --format='value(status.url)')

echo "Your app is at: $CLOUD_RUN_URL"

# Login and run migration:
# 1. Go to: $CLOUD_RUN_URL
# 2. Login: superadmin / admin123
# 3. Visit: $CLOUD_RUN_URL/run_migration.php
# 4. Click "Run Migration Now"
```

---

## 🐛 Troubleshooting

### Tabs Still Missing After Migration

**Check migration ran successfully:**
```bash
# Connect to Cloud SQL
gcloud sql connect sms-edu-db --user=root --project=sms-edu-47

# Verify tables exist
USE cor4edu_sms;
SHOW TABLES LIKE 'cor4edu_system%';
SHOW TABLES LIKE 'cor4edu_role%';

# Count permissions (should be 34+)
SELECT COUNT(*) FROM cor4edu_system_permissions;

# Check role types (should be 6)
SELECT * FROM cor4edu_staff_role_types;
```

**Solutions:**
1. Clear browser cache and reload
2. Log out and log back in
3. Run migration again (it's safe to run multiple times)
4. Check Cloud Run logs for errors

### Cloud Run Deployment Fails

**Check logs:**
```bash
gcloud run services logs read cor4edu-sms \
  --region=us-central1 \
  --limit=50
```

**Common issues:**
- Database connection: Verify `DB_SOCKET` environment variable
- Secrets access: Ensure service account has permissions
- Build timeout: Increase `timeout` in `cloudbuild.yaml`

### Local Works, Cloud Doesn't

**Key differences:**

| Aspect | Local | Cloud |
|--------|-------|-------|
| Database Connection | TCP `localhost:3306` | Unix socket `/cloudsql/...` |
| Environment | `.env` file | Cloud Run env vars + secrets |
| Debug Mode | `APP_DEBUG=true` | `APP_DEBUG=false` |

**Enable debug temporarily:**
```bash
gcloud run services update cor4edu-sms \
  --update-env-vars APP_DEBUG=true \
  --region=us-central1

# Check logs, then disable:
gcloud run services update cor4edu-sms \
  --update-env-vars APP_DEBUG=false \
  --region=us-central1
```

### Migration Script Shows Access Denied

**Verify SuperAdmin status:**
```sql
SELECT staffID, username, isSuperAdmin
FROM cor4edu_staff
WHERE username = 'superadmin';

# If isSuperAdmin = 'N', fix it:
UPDATE cor4edu_staff
SET isSuperAdmin = 'Y'
WHERE username = 'superadmin';
```

---

## 🔐 Security

### Post-Deployment Security

1. **Change default password:**
   ```sql
   UPDATE cor4edu_staff
   SET passwordStrong = '$2y$10$...' -- Use password_hash() in PHP
   WHERE username = 'superadmin';
   ```

2. **Remove migration script:**
   ```bash
   rm public/run_migration.php
   git add -u && git commit -m "Remove migration script"
   gcloud builds submit --config cloudbuild.yaml
   ```

3. **Enable Cloud Armor** (optional):
   - Add firewall rules
   - Rate limiting
   - DDoS protection

4. **Enable Cloud SQL SSL:**
   ```bash
   gcloud sql instances patch sms-edu-db --require-ssl
   ```

---

## 🔄 Development Workflow

```
┌──────────┐      ┌──────────────┐      ┌──────────┐
│  Local   │ ───> │ Google Cloud │ ───> │ GitHub   │
│  Dev/Test│      │ (sms-edu-47) │      │ (backup) │
└──────────┘      └──────────────┘      └──────────┘
     ↑                   Test                  │
     └───────────────── Pull ─────────────────┘
```

**Recommended flow:**
1. Develop locally with `database_complete_schema.sql`
2. Test thoroughly with local MySQL
3. Deploy to Google Cloud Run
4. Run migration (first time only)
5. Verify tabs and permissions work
6. Push to GitHub once Cloud is verified stable

---

## 📊 Monitoring

```bash
# View logs
gcloud run services logs read cor4edu-sms --region=us-central1 --limit=100

# Check service status
gcloud run services describe cor4edu-sms --region=us-central1

# Database backups
gcloud sql backups list --instance=sms-edu-db

# Create on-demand backup
gcloud sql backups create --instance=sms-edu-db
```

**Cloud Console URLs:**
- Logs: https://console.cloud.google.com/logs
- Cloud Run: https://console.cloud.google.com/run
- Cloud SQL: https://console.cloud.google.com/sql
- Secret Manager: https://console.cloud.google.com/security/secret-manager

---

## 📚 Additional Resources

- **GitHub Repository:** https://github.com/CarCar47/cor4edu-sms
- **Google Cloud Run Docs:** https://cloud.google.com/run/docs
- **Cloud SQL Docs:** https://cloud.google.com/sql/docs

---

## ✅ Post-Fix Verification Checklist

After running the migration, verify:

- [ ] **Dashboard** loads correctly
- [ ] **Students tab** appears and works
- [ ] **Programs tab** appears and works
- [ ] **Reports tab** appears ⭐ (was missing)
- [ ] **Permissions tab** appears ⭐ (was missing)
- [ ] **Staff tab** appears ⭐ (was missing)
- [ ] Can create new staff users
- [ ] Can assign roles to staff (Admissions, Bursar, etc.)
- [ ] Role-based permissions work correctly
- [ ] Non-admin users see appropriate tabs only
- [ ] SuperAdmin sees all tabs

---

## 💬 Support

For issues or questions:
1. Check Cloud Run logs first
2. Verify database connectivity
3. Review this troubleshooting guide
4. Open issue on GitHub: https://github.com/CarCar47/cor4edu-sms/issues

---

**Last Updated:** 2025-10-01
**Version:** 1.0.0
**Status:** ✅ Production Ready

---

*Remember: The migration script (`run_migration.php`) is safe to run multiple times and can be removed after successful execution for security.*
