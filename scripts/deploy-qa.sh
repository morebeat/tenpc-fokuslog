#!/bin/bash
# FokusLog Deployment Script - QA Environment
# This script deploys FokusLog to the QA environment with testing
# Usage: ./scripts/deploy-qa.sh

set -e

echo "🚀 FokusLog Deployment - QA Environment"
echo "=================================================="
echo ""

# Configuration
ENVIRONMENT="qa"
DEPLOY_DIR="/var/www/fokuslog-qa"
REPO_URL="https://github.com/[your-org]/fokuslog-app.git"
BRANCH="main"
BACKUP_DIR="/var/backups/fokuslog-qa"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_section() { echo -e "\n${BLUE}=== $1 ===${NC}\n"; }

# Pre-deployment checks
log_section "Pre-Deployment Checks"
log_info "Checking prerequisites..."

for cmd in git php mysql; do
    if ! command -v $cmd &> /dev/null; then
        log_error "$cmd is not installed"
        exit 1
    fi
done

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup current deployment (if exists)
if [ -d "$DEPLOY_DIR" ]; then
    log_section "Creating Backup"
    BACKUP_TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    BACKUP_FILE="$BACKUP_DIR/fokuslog-qa_${BACKUP_TIMESTAMP}.tar.gz"
    
    log_info "Backing up current deployment to: $BACKUP_FILE"
    tar -czf "$BACKUP_FILE" -C "$(dirname "$DEPLOY_DIR")" "$(basename "$DEPLOY_DIR")"
    log_info "Backup completed"
fi

# Deploy
log_section "Deploying Application"

if [ ! -d "$DEPLOY_DIR/.git" ]; then
    log_info "Cloning repository..."
    mkdir -p "$(dirname "$DEPLOY_DIR")"
    git clone -b "$BRANCH" "$REPO_URL" "$DEPLOY_DIR"
else
    log_info "Updating repository..."
    cd "$DEPLOY_DIR"
    git fetch origin
    git checkout "$BRANCH"
    git pull origin "$BRANCH"
fi

# Set permissions
log_info "Setting file permissions..."
chmod -R 755 "$DEPLOY_DIR"/api "$DEPLOY_DIR"/app "$DEPLOY_DIR"/db "$DEPLOY_DIR"/scripts

# Configure environment
log_info "Configuring QA environment..."
if [ ! -f "$DEPLOY_DIR/api/.env" ]; then
    cp "$DEPLOY_DIR/api/.env.example" "$DEPLOY_DIR/api/.env"
    
    # Set QA-specific settings
    sed -i 's/APP_ENV=development/APP_ENV=qa/' "$DEPLOY_DIR/api/.env"
    sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' "$DEPLOY_DIR/api/.env"
    sed -i 's/SESSION_SECURE=false/SESSION_SECURE=true/' "$DEPLOY_DIR/api/.env"
    sed -i 's/LOG_LEVEL=INFO/LOG_LEVEL=WARNING/' "$DEPLOY_DIR/api/.env"
    
    log_warning "Created .env file with QA defaults. Please verify database credentials!"
fi

# Database setup
log_section "Database Setup"
source "$DEPLOY_DIR/api/.env"

if ! mysql -h "${DB_HOST%:*}" -P "${DB_HOST#*:}" -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME;" 2>/dev/null; then
    log_info "Database doesn't exist. Creating..."
    mysql -h "${DB_HOST%:*}" -P "${DB_HOST#*:}" -u "$DB_USER" -p"$DB_PASS" \
        -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -h "${DB_HOST%:*}" -P "${DB_HOST#*:}" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$DEPLOY_DIR/db/schema.sql"
    log_info "Database created and schema loaded"
else
    log_info "Database already exists"
fi

# Create directories
log_info "Creating required directories..."
mkdir -p "$DEPLOY_DIR/logs" "$DEPLOY_DIR/backups"
chmod 777 "$DEPLOY_DIR/logs" "$DEPLOY_DIR/backups"

# Import help content into glossary table
log_info "Importing help content into glossary..."
if [ -f "$DEPLOY_DIR/app/help/import_help.php" ]; then
    php "$DEPLOY_DIR/app/help/import_help.php" && log_info "Help import completed" || log_warning "Help import failed"
else
    log_warning "Help import script not found"
fi

# Run basic tests
log_section "Running Tests"

log_info "Checking API health..."
if php -S localhost:8000 -t "$DEPLOY_DIR/api" 2>/dev/null &
    SERVER_PID=$!
    sleep 2
    if curl -s http://localhost:8000/api/me >/dev/null 2>&1; then
        log_info "✓ API responds to requests"
    else
        log_warning "⚠ API health check incomplete (may need authentication)"
    fi
    kill $SERVER_PID 2>/dev/null || true
fi

# Print summary
echo ""
log_section "Deployment Summary"
echo "Environment: $ENVIRONMENT"
echo "Deploy Directory: $DEPLOY_DIR"
echo "Branch: $BRANCH"
echo "Database: $DB_NAME"
echo ""
echo "✅ QA Deployment Complete!"
echo ""
echo "📋 Checklist:"
echo "  [ ] Verify .env configuration: $DEPLOY_DIR/api/.env"
echo "  [ ] Configure web server SSL certificate"
echo "  [ ] Update DNS records if needed"
echo "  [ ] Run user acceptance tests"
echo "  [ ] Verify database backups"
echo ""
echo "📝 Recent backups:"
ls -lht "$BACKUP_DIR" | head -5
echo ""
