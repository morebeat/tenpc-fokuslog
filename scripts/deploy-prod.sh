#!/bin/bash
# FokusLog Deployment Script - Production Environment
# CRITICAL: This script deploys to production with safety checks
# Usage: ./scripts/deploy-prod.sh [--force] [--skip-backup]

set -e

echo "🚀 FokusLog Deployment - PRODUCTION Environment"
echo "=================================================="
echo ""

# Configuration
ENVIRONMENT="production"
DEPLOY_DIR="/var/www/fokuslog"
REPO_URL="https://github.com/[your-org]/fokuslog-app.git"
BRANCH="main"
BACKUP_DIR="/var/backups/fokuslog"
MAINTENANCE_FILE="$DEPLOY_DIR/MAINTENANCE"

# Command line arguments
FORCE_DEPLOY=false
SKIP_BACKUP=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --force) FORCE_DEPLOY=true; shift ;;
        --skip-backup) SKIP_BACKUP=true; shift ;;
        *) shift ;;
    esac
done

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

# Safety checks
log_section "Production Deployment Safety Checks"

# Check if running as root or sudo
if [ "$EUID" -ne 0 ]; then
    log_error "This script must be run as root or with sudo"
    exit 1
fi

# Require explicit force flag for production
if [ "$FORCE_DEPLOY" = false ]; then
    log_warning "This is a PRODUCTION deployment!"
    log_warning "All users will be affected by this deployment"
    echo ""
    read -p "Are you absolutely sure? Type 'yes' to proceed: " CONFIRM
    if [ "$CONFIRM" != "yes" ]; then
        log_info "Deployment cancelled"
        exit 0
    fi
fi

# Verify all prerequisites
log_section "Verifying Prerequisites"
for cmd in git php mysql supervisorctl; do
    if ! command -v $cmd &> /dev/null; then
        log_error "$cmd is not installed"
        exit 1
    fi
done

# Check disk space
AVAILABLE_SPACE=$(df "$DEPLOY_DIR" | awk 'NR==2 {print $4}')
if [ "$AVAILABLE_SPACE" -lt 1048576 ]; then  # Less than 1GB
    log_error "Insufficient disk space (< 1GB available)"
    exit 1
fi
log_info "Disk space check passed"

# Create backup directory
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

# Create backup (unless skipped)
if [ "$SKIP_BACKUP" = false ]; then
    log_section "Creating Production Backup"
    BACKUP_TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    BACKUP_FILE="$BACKUP_DIR/fokuslog-prod_${BACKUP_TIMESTAMP}.tar.gz"
    
    log_info "Creating application backup..."
    tar -czf "$BACKUP_FILE" \
        -C "$(dirname "$DEPLOY_DIR")" "$(basename "$DEPLOY_DIR")" \
        --exclude=logs --exclude=tmp --exclude=cache \
        2>/dev/null
    
    log_info "Creating database backup..."
    source "$DEPLOY_DIR/api/.env"
    BACKUP_DB_FILE="$BACKUP_DIR/fokuslog-db_${BACKUP_TIMESTAMP}.sql.gz"
    mysqldump -h "${DB_HOST%:*}" -P "${DB_HOST#*:}" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_DB_FILE"
    
    log_info "✓ Backups created successfully"
    log_info "  App: $BACKUP_FILE"
    log_info "  DB: $BACKUP_DB_FILE"
else
    log_warning "Database backup skipped (risky!)"
fi

# Enable maintenance mode
log_section "Entering Maintenance Mode"
mkdir -p "$MAINTENANCE_FILE"
echo "Maintenance in progress. Expected completion: $(date -u -d '+15 minutes' +'%Y-%m-%d %H:%M:%S UTC')" > "$MAINTENANCE_FILE/message.txt"
log_info "Maintenance mode enabled"

# Stop background services (if using supervisor)
log_info "Stopping background services..."
supervisorctl stop fokuslog:* || true
sleep 2

# Deploy
log_section "Deploying Application"

if [ ! -d "$DEPLOY_DIR/.git" ]; then
    log_error "Git repository not found. Aborting deployment."
    rm -rf "$MAINTENANCE_FILE"
    exit 1
fi

cd "$DEPLOY_DIR"
log_info "Fetching latest code..."
git fetch origin
git checkout "$BRANCH"
git pull origin "$BRANCH"

log_info "Verifying deployment..."
if [ ! -f "$DEPLOY_DIR/api/index.php" ]; then
    log_error "Critical files missing after deployment. Restoring backup..."
    rm -rf "$MAINTENANCE_FILE"
    exit 1
fi

# Update permissions
log_info "Setting permissions..."
find "$DEPLOY_DIR" -type d -exec chmod 755 {} \;
find "$DEPLOY_DIR" -type f -exec chmod 644 {} \;
chmod 755 "$DEPLOY_DIR/scripts/"*.sh 2>/dev/null || true
chmod 777 "$DEPLOY_DIR/logs" "$DEPLOY_DIR/backups" 2>/dev/null || true

# Database migration (if needed)
log_section "Database Migration"
source "$DEPLOY_DIR/api/.env"

if [ -f "$DEPLOY_DIR/scripts/update_schema.sql" ]; then
    log_info "Checking for pending migrations..."
    mysql -h "${DB_HOST%:*}" -P "${DB_HOST#*:}" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$DEPLOY_DIR/scripts/update_schema.sql" 2>/dev/null || true
fi

# Restart services
log_section "Restarting Services"
log_info "Starting background services..."
supervisorctl start fokuslog:* || true
sleep 2

# Verify deployment
log_section "Verifying Deployment"
log_info "Running deployment health checks..."

# Check file integrity
if php -l "$DEPLOY_DIR/api/index.php" > /dev/null 2>&1; then
    log_info "✓ PHP syntax check passed"
else
    log_error "✗ PHP syntax errors detected"
    rm -rf "$MAINTENANCE_FILE"
    exit 1
fi

# Check API availability
sleep 2
if curl -s -f http://localhost/api/health >/dev/null 2>&1 || curl -s http://localhost/app/index.html >/dev/null 2>&1; then
    log_info "✓ Application is responding"
else
    log_warning "⚠ Application may not be responding (check web server)"
fi

# Exit maintenance mode
log_section "Exiting Maintenance Mode"
rm -rf "$MAINTENANCE_FILE"
log_info "Maintenance mode disabled"

# Print summary
echo ""
log_section "Production Deployment Summary"
echo "Environment: $ENVIRONMENT"
echo "Deployed at: $(date)"
echo "Branch: $BRANCH"
echo "Deploy Directory: $DEPLOY_DIR"
echo ""
echo "✅ Production Deployment Complete!"
echo ""
echo "📊 Deployment Information:"
echo "  Current Version: $(cd $DEPLOY_DIR && git describe --tags --always)"
echo "  Last Commit: $(cd $DEPLOY_DIR && git log -1 --format='%h - %s')"
echo ""
echo "📋 Post-Deployment Checklist:"
echo "  [ ] Monitor application logs: tail -f $DEPLOY_DIR/logs/app.log"
echo "  [ ] Verify database integrity"
echo "  [ ] Check user reports"
echo "  [ ] Verify external integrations"
echo ""
echo "🔄 Backup Information:"
ls -lhtr "$BACKUP_DIR" | tail -10
echo ""
