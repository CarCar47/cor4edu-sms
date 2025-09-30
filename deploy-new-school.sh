#!/bin/bash
# COR4EDU SMS - New School Deployment Script
# Automates deployment of COR4EDU SMS for a new school on Google Cloud

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to prompt for user input
prompt_input() {
    local prompt_text="$1"
    local var_name="$2"
    local default_value="$3"

    if [ -n "$default_value" ]; then
        read -p "$prompt_text [$default_value]: " input
        eval "$var_name=\"${input:-$default_value}\""
    else
        read -p "$prompt_text: " input
        eval "$var_name=\"$input\""
    fi
}

# Banner
echo "=========================================="
echo "  COR4EDU SMS - New School Deployment"
echo "=========================================="
echo ""

# Step 1: Collect school information
print_info "Step 1/10: Collecting school information..."
prompt_input "Enter school name (e.g., 'Lincoln High School')" SCHOOL_NAME
prompt_input "Enter school abbreviation (lowercase, no spaces, e.g., 'lincoln')" SCHOOL_ABBR
prompt_input "Enter Google Cloud Project ID" PROJECT_ID "${SCHOOL_ABBR}-cor4edu"
prompt_input "Enter Google Cloud Region" REGION "us-central1"
prompt_input "Enter database password" DB_PASSWORD

# Derived values
SERVICE_NAME="${SCHOOL_ABBR}-cor4edu-sms"
CLOUDSQL_INSTANCE="${SCHOOL_ABBR}-cor4edu-db"
DB_NAME="${SCHOOL_ABBR}_cor4edu_sms"
DB_USERNAME="cor4edu_admin"
ARTIFACT_REPO="cor4edu-containers"

print_success "Configuration collected for ${SCHOOL_NAME}"
echo ""

# Step 2: Verify gcloud CLI
print_info "Step 2/10: Verifying Google Cloud CLI..."
if ! command -v gcloud &> /dev/null; then
    print_error "gcloud CLI not found. Please install: https://cloud.google.com/sdk/docs/install"
    exit 1
fi
print_success "Google Cloud CLI found"
echo ""

# Step 3: Set active project
print_info "Step 3/10: Setting active Google Cloud project..."
gcloud config set project "$PROJECT_ID"
print_success "Active project: $PROJECT_ID"
echo ""

# Step 4: Enable required APIs
print_info "Step 4/10: Enabling required Google Cloud APIs..."
gcloud services enable \
    run.googleapis.com \
    sqladmin.googleapis.com \
    cloudbuild.googleapis.com \
    artifactregistry.googleapis.com \
    secretmanager.googleapis.com \
    iamcredentials.googleapis.com

print_success "APIs enabled"
echo ""

# Step 5: Create Artifact Registry repository
print_info "Step 5/10: Creating Artifact Registry repository..."
if gcloud artifacts repositories describe "$ARTIFACT_REPO" --location="$REGION" &> /dev/null; then
    print_warning "Artifact Registry repository already exists, skipping..."
else
    gcloud artifacts repositories create "$ARTIFACT_REPO" \
        --repository-format=docker \
        --location="$REGION" \
        --description="COR4EDU SMS container images"
    print_success "Artifact Registry repository created"
fi
echo ""

# Step 6: Create Cloud SQL instance
print_info "Step 6/10: Creating Cloud SQL instance (this may take 5-10 minutes)..."
if gcloud sql instances describe "$CLOUDSQL_INSTANCE" &> /dev/null; then
    print_warning "Cloud SQL instance already exists, skipping..."
else
    gcloud sql instances create "$CLOUDSQL_INSTANCE" \
        --database-version=MYSQL_8_0 \
        --tier=db-f1-micro \
        --region="$REGION" \
        --root-password="$DB_PASSWORD" \
        --availability-type=zonal \
        --backup \
        --backup-start-time=03:00
    print_success "Cloud SQL instance created"
fi
echo ""

# Step 7: Create database
print_info "Step 7/10: Creating application database..."
if gcloud sql databases describe "$DB_NAME" --instance="$CLOUDSQL_INSTANCE" &> /dev/null; then
    print_warning "Database already exists, skipping..."
else
    gcloud sql databases create "$DB_NAME" \
        --instance="$CLOUDSQL_INSTANCE" \
        --charset=utf8mb4 \
        --collation=utf8mb4_general_ci
    print_success "Database created: $DB_NAME"
fi
echo ""

# Step 8: Create database user
print_info "Step 8/10: Creating database user..."
gcloud sql users create "$DB_USERNAME" \
    --instance="$CLOUDSQL_INSTANCE" \
    --password="$DB_PASSWORD" 2>/dev/null || print_warning "User may already exist"
print_success "Database user configured"
echo ""

# Step 9: Store secrets in Secret Manager
print_info "Step 9/10: Storing database credentials in Secret Manager..."

# Create DB username secret
if gcloud secrets describe "${SCHOOL_ABBR}-db-username" &> /dev/null; then
    print_warning "DB username secret exists, updating..."
    echo -n "$DB_USERNAME" | gcloud secrets versions add "${SCHOOL_ABBR}-db-username" --data-file=-
else
    echo -n "$DB_USERNAME" | gcloud secrets create "${SCHOOL_ABBR}-db-username" --data-file=-
fi

# Create DB password secret
if gcloud secrets describe "${SCHOOL_ABBR}-db-password" &> /dev/null; then
    print_warning "DB password secret exists, updating..."
    echo -n "$DB_PASSWORD" | gcloud secrets versions add "${SCHOOL_ABBR}-db-password" --data-file=-
else
    echo -n "$DB_PASSWORD" | gcloud secrets create "${SCHOOL_ABBR}-db-password" --data-file=-
fi

print_success "Secrets stored in Secret Manager"
echo ""

# Step 10: Build and deploy application
print_info "Step 10/10: Building and deploying application to Cloud Run..."
gcloud builds submit \
    --config=cloudbuild.yaml \
    --substitutions=_SERVICE_NAME="$SERVICE_NAME",_REGION="$REGION",_ARTIFACT_REPO="$ARTIFACT_REPO",_CLOUDSQL_INSTANCE="$CLOUDSQL_INSTANCE",_DB_NAME="$DB_NAME",_DB_USERNAME_SECRET="${SCHOOL_ABBR}-db-username",_DB_PASSWORD_SECRET="${SCHOOL_ABBR}-db-password"

print_success "Application deployed successfully!"
echo ""

# Get service URL
SERVICE_URL=$(gcloud run services describe "$SERVICE_NAME" --region="$REGION" --format="value(status.url)")

# Final summary
echo "=========================================="
echo "  Deployment Complete!"
echo "=========================================="
echo ""
echo "School: $SCHOOL_NAME"
echo "Project ID: $PROJECT_ID"
echo "Service URL: $SERVICE_URL"
echo "Cloud SQL Instance: $CLOUDSQL_INSTANCE"
echo "Database: $DB_NAME"
echo ""
print_warning "IMPORTANT NEXT STEPS:"
echo "1. Access your application: $SERVICE_URL"
echo "2. Run database migrations (see DEPLOYMENT.md)"
echo "3. Configure Google Identity Platform for SSO (optional)"
echo "4. Set up custom domain (optional)"
echo "5. Configure monitoring and alerts"
echo ""
print_info "For detailed instructions, see DEPLOYMENT.md"
echo ""
