#!/bin/bash
# FokusLog Deployment Script - Development Environment
# This script deploys FokusLog to the development environment
# Usage: ./scripts/deploy-dev.sh

set -e  # Exit on error

echo "🚀 FokusLog Deployment - Development Environment"
echo "=================================================="
echo ""

# Configuration
ENVIRONMENT="development"
DEPLOY_DIR="/var/www/fokuslog-dev"
REPO_URL="https://github.com/[your-org]/fokuslog-app.git"
BRANCH="develop"
CURRENT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check prerequisites
log_info "Checking prerequisites..."
if ! command -v git &> /dev/null; then
    log_error "Git is not installed"
    exit 1
fi

if ! command -v php &> /dev/null; then
    log_error "PHP is not installed"
    exit 1
fi

if ! command -v composer &> /dev/null; then
    log_warning "Composer is not installed. Skipping vendor installation."
fi

# Create deployment directory if it doesn't exist
if [ ! -d "$DEPLOY_DIR" ]; then
    log_info "Creating deployment directory: $DEPLOY_DIR"
    mkdir -p "$DEPLOY_DIR"
fi

# Clone or pull repository
if [ -d "$DEPLOY_DIR/.git" ]; then
    log_info "Repository already exists. Pulling latest changes..."
    cd "$DEPLOY_DIR"
    git fetch origin
    git checkout "$BRANCH"
    git pull origin "$BRANCH"
else
    log_info "Cloning repository..."
    cd "$(dirname "$DEPLOY_DIR")"
    git clone -b "$BRANCH" "$REPO_URL" "$(basename "$DEPLOY_DIR")"
    cd "$DEPLOY_DIR"
fi

# Set permissions
log_info "Setting permissions..."
chmod -R 755 api/ app/ db/ scripts/
chmod -R 755 logs/ 2>/dev/null || true
chmod -R 755 backups/ 2>/dev/null || true

# Configure environment
log_info "Configuring environment..."
if [ ! -f "$DEPLOY_DIR/api/.env" ]; then
    cp "$DEPLOY_DIR/api/.env.example" "$DEPLOY_DIR/api/.env"
    log_warning "Created .env file. Please edit it with your database credentials!"
    log_warning "File: $DEPLOY_DIR/api/.env"
else
    log_info ".env file already exists"
fi

# Database migration (if database doesn't exist)
log_info "Checking database..."
mysql_host=$(grep 'DB_HOST=' "$DEPLOY_DIR/api/.env" | cut -d '=' -f 2 | cut -d ':' -f 1)
mysql_port=$(grep 'DB_HOST=' "$DEPLOY_DIR/api/.env" | cut -d ':' -f 2 || echo "3306")
mysql_db=$(grep 'DB_NAME=' "$DEPLOY_DIR/api/.env" | cut -d '=' -f 2)
mysql_user=$(grep 'DB_USER=' "$DEPLOY_DIR/api/.env" | cut -d '=' -f 2)
mysql_pass=$(grep 'DB_PASS=' "$DEPLOY_DIR/api/.env" | cut -d '=' -f 2)

# Check if database exists
if ! mysql -h "$mysql_host" -P "$mysql_port" -u "$mysql_user" -p"$mysql_pass" -e "USE $mysql_db;" 2>/dev/null; then
    log_info "Database doesn't exist. Creating..."
    mysql -h "$mysql_host" -P "$mysql_port" -u "$mysql_user" -p"$mysql_pass" -e "CREATE DATABASE $mysql_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    log_info "Running database schema..."
    mysql -h "$mysql_host" -P "$mysql_port" -u "$mysql_user" -p"$mysql_pass" "$mysql_db" < "$DEPLOY_DIR/db/schema.sql"
else
    log_info "Database already exists"
fi

# Install dependencies if composer.json exists
if [ -f "$DEPLOY_DIR/composer.json" ] && command -v composer &> /dev/null; then
    log_info "Installing PHP dependencies..."
    cd "$DEPLOY_DIR"
    composer install --no-dev
fi

# Create required directories
log_info "Creating required directories..."
mkdir -p "$DEPLOY_DIR/logs"
mkdir -p "$DEPLOY_DIR/backups"
chmod 777 "$DEPLOY_DIR/logs" "$DEPLOY_DIR/backups"

# Import help content into glossary table
log_info "Importing help content into glossary..."
if [ -f "$DEPLOY_DIR/app/help/import_help.php" ]; then
    php "$DEPLOY_DIR/app/help/import_help.php" && log_info "Help import completed" || log_warning "Help import failed"
else
    log_warning "Help import script not found"
fi

# Clear logs
log_info "Clearing old logs..."
find "$DEPLOY_DIR/logs" -type f -name "*.log" -mtime +30 -delete 2>/dev/null || true

# Print deployment summary
echo ""
echo "✅ Deployment Complete!"
echo "=================================================="
echo "Environment: $ENVIRONMENT"
echo "Deploy Directory: $DEPLOY_DIR"
echo "Branch: $BRANCH"
echo "Database: $mysql_db"
echo ""
echo "📝 Next Steps:"
echo "1. Verify the .env file configuration:"
echo "   nano $DEPLOY_DIR/api/.env"
echo ""
echo "2. Configure your web server (Apache/Nginx):"
echo "   - Point document root to: $DEPLOY_DIR/app"
echo "   - Configure rewrite rules for API: /api/* -> /api/index.php"
echo ""
echo "3. Test the application:"
echo "   curl http://localhost/app/index.html"
echo ""
echo "4. Check logs for errors:"
echo "   tail -f $DEPLOY_DIR/logs/app.log"
echo ""
echo "📚 Documentation:"
echo "   Tech Docs: $DEPLOY_DIR/docs/TECHNICAL_ARCHITECTURE.md"
echo "   Deployment: $DEPLOY_DIR/docs/DEPLOYMENT.md"
echo ""
