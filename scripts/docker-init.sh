#!/bin/bash

# FokusLog Docker Initialization Script
# Sets up Docker environment for local development

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

# Check prerequisites
log_info "Checking Docker prerequisites..."

if ! command -v docker &> /dev/null; then
    log_error "Docker is not installed. Please install Docker first."
    echo "Visit: https://www.docker.com/get-started"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    log_error "Docker Compose is not installed. Please install Docker Compose first."
    echo "Visit: https://docs.docker.com/compose/install/"
    exit 1
fi

log_success "Docker and Docker Compose are installed"

# Check if Docker daemon is running
if ! docker info > /dev/null 2>&1; then
    log_error "Docker daemon is not running. Please start Docker and try again."
    exit 1
fi

log_success "Docker daemon is running"

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    log_info "Creating .env file from .env.docker.example..."
    cp .env.docker.example .env
    log_success ".env file created. Edit it to customize your environment."
else
    log_warning ".env file already exists. Skipping creation."
fi

# Create necessary directories
log_info "Creating necessary directories..."

directories=(
    "logs"
    "backups"
    "cache"
    "cache/sessions"
    "docker"
)

for dir in "${directories[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        log_success "Created directory: $dir"
    fi
done

# Set permissions
log_info "Setting directory permissions..."
chmod -R 755 logs backups cache docker
log_success "Permissions set"

# Build images
log_info "Building Docker images..."
docker-compose build --no-cache
log_success "Docker images built"

# Create and start containers
log_info "Starting Docker containers..."
docker-compose up -d
log_success "Docker containers started"

# Wait for MySQL to be ready
log_info "Waiting for MySQL to be ready..."
max_attempts=30
attempt=1

while ! docker-compose exec -T mysql mysqladmin ping -h localhost -u fokuslog_user -pfokuslog_password 2>/dev/null; do
    if [ $attempt -eq $max_attempts ]; then
        log_error "MySQL failed to start after $max_attempts attempts"
        log_info "Checking logs:"
        docker-compose logs mysql
        exit 1
    fi
    
    echo -n "."
    sleep 1
    ((attempt++))
done

echo ""
log_success "MySQL is ready"

# Wait for application to be ready
log_info "Waiting for application to be ready..."
attempt=1

while ! docker-compose exec -T app curl -f http://localhost/ > /dev/null 2>&1; do
    if [ $attempt -eq 30 ]; then
        log_warning "Application health check timeout"
        break
    fi
    
    echo -n "."
    sleep 1
    ((attempt++))
done

echo ""
log_success "Application is ready"

# Display status
log_info "Current container status:"
docker-compose ps

# Display useful information
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}FokusLog Docker Setup Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Access the application at:"
echo -e "  ${BLUE}http://localhost:8000${NC}"
echo ""
echo "Useful commands:"
echo "  View logs:              docker-compose logs -f app"
echo "  Database management:    http://localhost:8080 (PhpMyAdmin)"
echo "  Stop containers:        docker-compose down"
echo "  Restart containers:     docker-compose restart"
echo "  Execute shell:          docker-compose exec app bash"
echo "  MySQL CLI:              docker-compose exec mysql mysql -u fokuslog_user -p"
echo ""
echo "Database credentials:"
echo "  Host:     mysql"
echo "  Port:     3306"
echo "  Database: fokuslog"
echo "  User:     fokuslog_user"
echo "  Password: fokuslog_password"
echo ""
echo "To remove all containers and volumes:"
echo "  docker-compose down -v"
echo ""
