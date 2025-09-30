# COR4EDU SMS - Google Cloud Deployment Guide

Complete guide for deploying COR4EDU Student Management System to Google Cloud Platform.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Prerequisites](#prerequisites)
3. [First-Time Setup](#first-time-setup)
4. [Deploying a New School](#deploying-a-new-school)
5. [Database Setup](#database-setup)
6. [Post-Deployment Configuration](#post-deployment-configuration)
7. [Monitoring & Maintenance](#monitoring--maintenance)
8. [Cost Optimization](#cost-optimization)
9. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

### Production Stack

- **Application**: Cloud Run (containerized PHP 8.1 + Apache)
- **Database**: Cloud SQL MySQL 8.0
- **Storage**: Cloud Storage (for file uploads)
- **Secrets**: Secret Manager
- **CI/CD**: Cloud Build
- **Artifacts**: Artifact Registry

### Multi-School Deployment Strategy

Each school gets its own Google Cloud project for:
- Complete data isolation
- Independent billing and cost tracking
- Separate access controls
- Custom configurations per school

**Estimated Monthly Cost**: $8-15 per school (small to medium size)

---

## Prerequisites

### Required Tools

1. **Google Cloud CLI** (gcloud)
   ```bash
   # Install: https://cloud.google.com/sdk/docs/install
   gcloud --version
   ```

2. **Git**
   ```bash
   git --version
   ```

3. **Docker** (for local testing)
   ```bash
   docker --version
   ```

### Google Cloud Account

1. Create Google Cloud account: https://cloud.google.com
2. Enable billing
3. Verify you have Organization Admin or Project Creator role

---

## First-Time Setup

### 1. Clone Repository

```bash
# Clone from your GitHub repository
git clone https://github.com/YOUR_ORG/cor4edu-sms.git
cd cor4edu-sms
```

### 2. Authenticate with Google Cloud

```bash
gcloud auth login
gcloud auth application-default login
```

### 3. Configure Git Credentials (if using private repo)

```bash
git config credential.helper store
```

---

## Deploying a New School

### Automated Deployment Script

The easiest way to deploy for a new school is using the automated script:

```bash
# Make the script executable (Linux/Mac)
chmod +x deploy-new-school.sh

# Run the deployment script
./deploy-new-school.sh
```

The script will prompt you for:
- School name (e.g., "Lincoln High School")
- School abbreviation (e.g., "lincoln")
- Google Cloud Project ID (suggested: `lincoln-cor4edu`)
- Region (default: `us-central1`)
- Database password

**What the script does:**
1. ✅ Verifies gcloud CLI installation
2. ✅ Creates/sets Google Cloud project
3. ✅ Enables required APIs
4. ✅ Creates Artifact Registry repository
5. ✅ Creates Cloud SQL instance (MySQL 8.0)
6. ✅ Creates application database
7. ✅ Creates database user
8. ✅ Stores credentials in Secret Manager
9. ✅ Builds Docker image
10. ✅ Deploys to Cloud Run

### Manual Deployment (Advanced)

If you prefer manual control:

#### Step 1: Create Google Cloud Project

```bash
# Set variables
PROJECT_ID="lincoln-cor4edu"
REGION="us-central1"

# Create project
gcloud projects create $PROJECT_ID --name="Lincoln High School - COR4EDU"
gcloud config set project $PROJECT_ID

# Link billing account
gcloud billing projects link $PROJECT_ID --billing-account=BILLING_ACCOUNT_ID
```

#### Step 2: Enable APIs

```bash
gcloud services enable \
    run.googleapis.com \
    sqladmin.googleapis.com \
    cloudbuild.googleapis.com \
    artifactregistry.googleapis.com \
    secretmanager.googleapis.com
```

#### Step 3: Create Artifact Registry

```bash
gcloud artifacts repositories create cor4edu-containers \
    --repository-format=docker \
    --location=$REGION \
    --description="COR4EDU SMS container images"
```

#### Step 4: Create Cloud SQL Instance

```bash
gcloud sql instances create lincoln-cor4edu-db \
    --database-version=MYSQL_8_0 \
    --tier=db-f1-micro \
    --region=$REGION \
    --root-password="YOUR_SECURE_PASSWORD" \
    --availability-type=zonal \
    --backup \
    --backup-start-time=03:00
```

#### Step 5: Create Database

```bash
gcloud sql databases create lincoln_cor4edu_sms \
    --instance=lincoln-cor4edu-db \
    --charset=utf8mb4 \
    --collation=utf8mb4_general_ci
```

#### Step 6: Create Database User

```bash
gcloud sql users create cor4edu_admin \
    --instance=lincoln-cor4edu-db \
    --password="YOUR_DB_PASSWORD"
```

#### Step 7: Store Secrets

```bash
echo -n "cor4edu_admin" | gcloud secrets create lincoln-db-username --data-file=-
echo -n "YOUR_DB_PASSWORD" | gcloud secrets create lincoln-db-password --data-file=-
```

#### Step 8: Build and Deploy

```bash
gcloud builds submit \
    --config=cloudbuild.yaml \
    --substitutions=_SERVICE_NAME="lincoln-cor4edu-sms",_REGION="us-central1",_ARTIFACT_REPO="cor4edu-containers",_CLOUDSQL_INSTANCE="lincoln-cor4edu-db",_DB_NAME="lincoln_cor4edu_sms",_DB_USERNAME_SECRET="lincoln-db-username",_DB_PASSWORD_SECRET="lincoln-db-password"
```

---

## Database Setup

### Import Schema

After deployment, you need to import the database schema:

#### Option 1: Cloud SQL Proxy (Recommended)

```bash
# Download Cloud SQL Proxy
curl -o cloud-sql-proxy https://storage.googleapis.com/cloud-sql-connectors/cloud-sql-proxy/v2.8.0/cloud-sql-proxy.linux.amd64
chmod +x cloud-sql-proxy

# Start proxy
./cloud-sql-proxy PROJECT_ID:REGION:INSTANCE_NAME

# In another terminal, import schema
mysql -h 127.0.0.1 -u cor4edu_admin -p lincoln_cor4edu_sms < database/schema.sql
mysql -h 127.0.0.1 -u cor4edu_admin -p lincoln_cor4edu_sms < database/modules/students.sql
mysql -h 127.0.0.1 -u cor4edu_admin -p lincoln_cor4edu_sms < database/modules/staff.sql
mysql -h 127.0.0.1 -u cor4edu_admin -p lincoln_cor4edu_sms < database/modules/programs.sql
mysql -h 127.0.0.1 -u cor4edu_admin -p lincoln_cor4edu_sms < database/modules/reports.sql
```

#### Option 2: Google Cloud Console

1. Go to Cloud SQL Instances
2. Select your instance
3. Click "Import"
4. Upload SQL files from `database/` directory
5. Execute in order: schema.sql, then module files

### Create Initial Admin User

```bash
# Connect via Cloud SQL Proxy
mysql -h 127.0.0.1 -u cor4edu_admin -p lincoln_cor4edu_sms

# Create admin user
INSERT INTO cor4edu_staff (
    username,
    password,
    firstName,
    lastName,
    email,
    status,
    isAdmin
) VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'System',
    'Administrator',
    'admin@lincoln.edu',
    'active',
    1
);
```

---

## Post-Deployment Configuration

### 1. Access Your Application

```bash
# Get service URL
gcloud run services describe lincoln-cor4edu-sms \
    --region=us-central1 \
    --format="value(status.url)"
```

Visit the URL and login with:
- Username: `admin`
- Password: `password` (change immediately!)

### 2. Custom Domain (Optional)

```bash
# Map custom domain
gcloud run domain-mappings create \
    --service=lincoln-cor4edu-sms \
    --domain=sms.lincoln.edu \
    --region=us-central1
```

Follow DNS instructions provided by Cloud Run.

### 3. SSL Certificate

Cloud Run automatically provisions SSL certificates for custom domains.

### 4. Configure Cloud Storage (Future)

For file uploads in production:

```bash
# Create storage bucket
gsutil mb -l us-central1 gs://lincoln-cor4edu-uploads

# Set bucket permissions
gsutil iam ch serviceAccount:PROJECT_NUMBER-compute@developer.gserviceaccount.com:objectAdmin gs://lincoln-cor4edu-uploads
```

Update `.env` in Cloud Run:
```
STORAGE_DRIVER=gcs
GCS_BUCKET=lincoln-cor4edu-uploads
GCS_PROJECT_ID=lincoln-cor4edu
```

---

## Monitoring & Maintenance

### Health Check

Your application has a health check endpoint: `https://YOUR_APP_URL/health`

Returns:
```json
{
  "status": "healthy",
  "timestamp": "2025-09-30T10:30:00+00:00",
  "service": "cor4edu-sms",
  "database": "healthy"
}
```

### View Logs

```bash
# Application logs
gcloud run services logs read lincoln-cor4edu-sms --region=us-central1

# Cloud SQL logs
gcloud sql operations list --instance=lincoln-cor4edu-db
```

### Database Backups

Cloud SQL automatically backs up daily at 3:00 AM (configured in deployment).

**Manual backup:**
```bash
gcloud sql backups create \
    --instance=lincoln-cor4edu-db \
    --description="Manual backup before update"
```

**Restore from backup:**
```bash
# List backups
gcloud sql backups list --instance=lincoln-cor4edu-db

# Restore
gcloud sql backups restore BACKUP_ID \
    --backup-instance=lincoln-cor4edu-db \
    --backup-id=BACKUP_ID
```

### Updates & Redeployment

```bash
# Pull latest code
git pull origin main

# Redeploy
gcloud builds submit --config=cloudbuild.yaml \
    --substitutions=_SERVICE_NAME="lincoln-cor4edu-sms",...
```

---

## Cost Optimization

### Estimated Monthly Costs (Small School)

| Service | Configuration | Est. Cost |
|---------|---------------|-----------|
| Cloud Run | 512MB RAM, 1 CPU, minimal traffic | $0-5 |
| Cloud SQL | db-f1-micro (shared core) | $7 |
| Cloud Storage | 10GB uploads | $0.20 |
| Artifact Registry | 1GB images | $0.10 |
| **Total** | | **~$8/month** |

### Cost Reduction Tips

1. **Use smallest Cloud SQL tier**: `db-f1-micro` for <100 students
2. **Set min instances to 0**: Allow cold starts for low-traffic schools
3. **Enable Cloud SQL automatic storage increase**: Only pay for what you use
4. **Use lifecycle policies on Storage**: Delete old files after X days
5. **Schedule Cloud SQL shutdown**: Stop during nights/weekends (advanced)

### Monitor Costs

```bash
# View current month billing
gcloud billing accounts describe BILLING_ACCOUNT_ID

# Set budget alerts (via console)
# Go to: Billing > Budgets & alerts > Create Budget
```

---

## Troubleshooting

### Application Won't Start

**Check logs:**
```bash
gcloud run services logs read lincoln-cor4edu-sms --region=us-central1 --limit=50
```

**Common issues:**
- Database connection failed → Check DB_SOCKET environment variable
- Secrets not found → Verify Secret Manager permissions
- Port 8080 not exposed → Check Dockerfile EXPOSE directive

### Database Connection Errors

**Test Cloud SQL connectivity:**
```bash
gcloud sql connect lincoln-cor4edu-db --user=cor4edu_admin
```

**Check Unix socket path:**
```bash
# Should be: /cloudsql/PROJECT_ID:REGION:INSTANCE_NAME
gcloud run services describe lincoln-cor4edu-sms \
    --region=us-central1 \
    --format="value(spec.template.spec.containers[0].env)"
```

### 503 Service Unavailable

- Check health endpoint: `/health`
- Verify database is running:
  ```bash
  gcloud sql instances list
  ```
- Check Cloud Run revision status:
  ```bash
  gcloud run revisions list --service=lincoln-cor4edu-sms --region=us-central1
  ```

### Build Failures

```bash
# View build logs
gcloud builds list --limit=5
gcloud builds log BUILD_ID
```

**Common issues:**
- Composer install fails → Check `composer.json` syntax
- Docker build fails → Verify Dockerfile syntax
- Timeout → Increase timeout in `cloudbuild.yaml`

---

## Security Best Practices

1. **Rotate database passwords regularly**
   ```bash
   gcloud sql users set-password cor4edu_admin \
       --instance=lincoln-cor4edu-db \
       --password="NEW_PASSWORD"

   # Update secret
   echo -n "NEW_PASSWORD" | gcloud secrets versions add lincoln-db-password --data-file=-
   ```

2. **Enable Cloud Armor** (for DDoS protection)
3. **Set up VPC for private Cloud SQL** (advanced)
4. **Enable audit logging**
5. **Implement IP allowlisting** (if needed)

---

## Next Steps

After successful deployment:

1. ✅ Change default admin password
2. ✅ Create staff accounts
3. ✅ Import student data
4. ✅ Configure programs
5. ✅ Set up Google Identity Platform (optional SSO)
6. ✅ Configure custom domain
7. ✅ Set up monitoring alerts
8. ✅ Train school staff

---

## Support

For issues or questions:
- **Technical Issues**: Create GitHub issue
- **Deployment Help**: Contact COR4EDU support
- **Google Cloud Issues**: https://cloud.google.com/support

---

**Last Updated**: 2025-09-30
**Version**: 1.0.0
