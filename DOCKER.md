# Docker Setup Guide for FokusLog

Complete guide for running FokusLog locally using Docker for development and testing.

**Table of Contents:**
1. [Quick Start](#quick-start)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Running Containers](#running-containers)
5. [Development Workflow](#development-workflow)
6. [Database Management](#database-management)
7. [Debugging](#debugging)
8. [Troubleshooting](#troubleshooting)
9. [Stopping & Cleaning Up](#stopping--cleaning-up)

---

## Quick Start

Get FokusLog running in Docker in 3 minutes:

```bash
# 1. Clone repository
git clone https://github.com/[your-org]/fokuslog-app.git
cd fokuslog-app

# 2. Run initialization script
bash scripts/docker-init.sh

# 3. Access application
# Open: http://localhost:8000
```

That's it! The script handles everything.

---

## Installation

### Prerequisites

**macOS / Linux / Windows (WSL2):**

1. **Install Docker Desktop**
   - macOS: https://docs.docker.com/docker-for-mac/install/
   - Windows: https://docs.docker.com/docker-for-windows/install/
   - Linux: https://docs.docker.com/engine/install/

2. **Install Docker Compose**
   ```bash
   # Usually comes with Docker Desktop
   docker-compose --version
   ```

3. **Verify Installation**
   ```bash
   docker --version
   docker-compose --version
   ```

### Windows WSL2 Setup

If using Windows with WSL2:

```powershell
# In Windows PowerShell (as Administrator)
# Enable WSL2
wsl --set-default-version 2

# Install Ubuntu:
wsl --install -d Ubuntu

# In WSL2 Ubuntu terminal:
sudo apt-get update
sudo apt-get install docker.io docker-compose
```

---

## Configuration

### Environment File

```bash
# Copy example to .env
cp .env.docker.example .env

# Edit as needed
nano .env
```

**Key Variables:**

| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_PORT` | 8000 | HTTP port for application |
| `APP_SSL_PORT` | 8443 | HTTPS port for application |
| `DB_NAME` | fokuslog | Database name |
| `DB_USER` | fokuslog_user | Database user |
| `DB_PASS` | fokuslog_password | Database password |
| `PMA_PORT` | 8080 | PhpMyAdmin port |

### Docker Files Overview

```
.
├── Dockerfile              # Multi-stage build for application
├── docker-compose.yml      # Services configuration
├── .dockerignore           # Files excluded from build
├── .env.docker.example     # Environment template
└── docker/
    ├── php.ini             # PHP configuration
    └── apache.conf         # Apache configuration
```

---

## Running Containers

### Automatic Setup (Recommended)

```bash
# Initialize all services
bash scripts/docker-init.sh

# This will:
# - Check Docker installation
# - Create .env file
# - Create required directories
# - Build Docker images
# - Start all containers
# - Verify health
```

### Manual Setup

```bash
# Build images
docker-compose build

# Start containers in background
docker-compose up -d

# Or start with logs
docker-compose up
```

### View Status

```bash
# List running containers
docker-compose ps

# View container logs
docker-compose logs -f app

# Follow specific service
docker-compose logs -f mysql
```

---

## Development Workflow

### Editing Code

All code changes are immediately reflected (volume mounts):

```bash
# Edit files locally in your IDE
nano api/index.php
nano app/dashboard.html

# Changes appear instantly in running container
# No rebuild needed!
```

### Accessing Container Shell

```bash
# Enter application container
docker-compose exec app bash

# Inside container:
cd /var/www/html
php -v
ls -la

# Exit
exit
```

### Running PHP Commands

```bash
# Without entering shell
docker-compose exec app php -v
docker-compose exec app composer install
docker-compose exec app php -l api/index.php
```

### Database Access

```bash
# MySQL CLI inside container
docker-compose exec mysql mysql -u fokuslog_user -p fokuslog

# Commands inside MySQL:
SHOW TABLES;
SELECT VERSION();
exit
```

### Testing API

```bash
# From host machine
curl http://localhost:8000/api/

# Or from app container
docker-compose exec app curl http://localhost/api/
```

---

## Database Management

### PhpMyAdmin (GUI)

Access database visually:

```
URL: http://localhost:8080

Login:
- Server: mysql
- Username: fokuslog_user
- Password: fokuslog_password
- Database: fokuslog
```

### Database Backup

```bash
# Backup entire database
docker-compose exec mysql mysqldump -u fokuslog_user -p fokuslog > backup.sql

# Backup to file in backups directory
docker-compose exec mysql mysqldump -u fokuslog_user -p fokuslog > backups/fokuslog_$(date +%Y%m%d_%H%M%S).sql
```

### Database Restore

```bash
# Restore from backup
docker-compose exec -T mysql mysql -u fokuslog_user -p fokuslog < backup.sql

# Or restore from backups directory
docker-compose exec -T mysql mysql -u fokuslog_user -p fokuslog < backups/fokuslog_20240101_120000.sql
```

### Database Reset

```bash
# Remove data volume (CAUTION: Deletes all data!)
docker-compose down -v

# Recreate and start
docker-compose up -d

# Database will be reinitialized from schema.sql
```

### View Database Logs

```bash
# MySQL error log
docker-compose exec mysql tail -f /var/log/mysql/error.log

# Or through PhpMyAdmin
```

---

## Debugging

### View Application Logs

```bash
# PHP error log
docker-compose exec app tail -f /var/www/html/logs/php_error.log

# Apache error log
docker-compose exec app tail -f /var/www/html/logs/apache_error.log

# Apache access log
docker-compose exec app tail -f /var/www/html/logs/apache_access.log

# Application log (if implemented)
docker-compose exec app tail -f /var/www/html/logs/app.log
```

### Xdebug Setup (Optional)

For advanced debugging with IDE:

```bash
# Add to .env
XDEBUG_MODE=debug
XDEBUG_CONFIG=client_host=host.docker.internal

# Configure IDE to listen on port 9003
# Set breakpoints in your code
# Requests will pause at breakpoints
```

### Checking Container Health

```bash
# Health status
docker-compose ps

# Detailed inspection
docker inspect fokuslog-app | grep -A 5 '"Health"'

# Check service availability
docker-compose exec app curl -I http://localhost/
docker-compose exec app curl -I http://mysql:3306
```

### Performance Monitoring

```bash
# CPU and memory usage
docker stats

# Container resource limits
docker-compose exec app free -h
docker-compose exec mysql free -h
```

---

## Troubleshooting

### Container Won't Start

```bash
# Check logs
docker-compose logs app
docker-compose logs mysql

# Try rebuilding
docker-compose build --no-cache
docker-compose up -d

# Check Docker resources (Windows/Mac)
# Settings → Resources → increase CPU/Memory
```

### MySQL Connection Error

```bash
# Verify MySQL is running
docker-compose ps mysql

# Check MySQL logs
docker-compose logs mysql

# Verify credentials in .env
grep DB_ .env

# Try connecting manually
docker-compose exec mysql mysql -u fokuslog_user -p fokuslog
```

### Port Already in Use

```bash
# Find what's using port 8000
lsof -i :8000           # macOS/Linux
netstat -ano | findstr :8000  # Windows

# Change port in .env
APP_PORT=8001

# Or stop other containers
docker ps
docker stop [container-id]
```

### Permission Denied Errors

```bash
# Fix directory permissions
docker-compose exec app chmod -R 777 /var/www/html/logs
docker-compose exec app chmod -R 777 /var/www/html/backups

# Or from host
sudo chmod -R 777 logs backups
```

### Database Size Growing Too Fast

```bash
# Check database size
docker-compose exec mysql mysql -u fokuslog_user -p fokuslog -e "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size FROM information_schema.TABLES WHERE table_schema = 'fokuslog';"

# Optimize tables
docker-compose exec mysql mysql -u fokuslog_user -p fokuslog -e "OPTIMIZE TABLE entries, users, medications;"
```

### PHP Memory Limit Issues

```bash
# Edit docker/php.ini
nano docker/php.ini

# Change memory_limit
memory_limit = 512M

# Rebuild container
docker-compose build --no-cache app
docker-compose restart app
```

### Application Slow

```bash
# Check container resources
docker stats

# If CPU/memory constrained:
# 1. Increase Docker resources (Docker Desktop settings)
# 2. Optimize code
# 3. Use production-like deployment

# Check database performance
docker-compose exec mysql mysql -u fokuslog_user -p fokuslog
SHOW PROCESSLIST;
SHOW ENGINE INNODB STATUS;
```

---

## Stopping & Cleaning Up

### Stop Containers

```bash
# Stop all containers (keep data)
docker-compose stop

# Restart containers
docker-compose start

# Restart specific service
docker-compose restart app
```

### Remove Containers

```bash
# Remove containers (keep volumes)
docker-compose down

# Remove containers and volumes (DELETE DATA)
docker-compose down -v

# Remove containers, volumes, and images
docker-compose down -v --rmi all
```

### Clean Up Docker

```bash
# Remove unused images
docker image prune

# Remove unused networks
docker network prune

# Remove unused volumes
docker volume prune

# Remove everything unused (BE CAREFUL!)
docker system prune -a --volumes
```

---

## Docker Compose Profiles

### Debug Profile

Enable PhpMyAdmin (disabled by default):

```bash
# Start with debug profile
docker-compose --profile debug up -d

# Now PhpMyAdmin is available at http://localhost:8080
```

---

## Production-like Deployment

To test in a production-like environment:

```bash
# 1. Update .env
cp .env.docker.example .env
nano .env
# Set: APP_ENV=production, APP_DEBUG=false, SESSION_SECURE=true

# 2. Build with production Dockerfile
# (currently uses development Dockerfile)

# 3. Use production database settings
# Configure real database credentials

# 4. Enable HTTPS
# Configure SSL certificates in docker/apache.conf
```

---

## Useful Docker Commands

```bash
# View image layers
docker history fokuslog-app:latest

# Inspect image
docker inspect fokuslog-app:latest

# Export/backup image
docker save fokuslog-app:latest | gzip > fokuslog-app-latest.tar.gz

# Load image
docker load < fokuslog-app-latest.tar.gz

# Push to registry
docker tag fokuslog-app:latest myregistry/fokuslog-app:latest
docker push myregistry/fokuslog-app:latest
```

---

## Best Practices

✅ **Do:**
- Use volume mounts for code (instant updates)
- Commit .dockerignore and Dockerfile to Git
- Use `.env` for local configuration
- Backup database regularly
- Test locally before deploying
- Use specific image versions (not `latest`)
- Monitor container resources
- Document custom configurations

❌ **Don't:**
- Commit `.env` file with credentials
- Run containers as root
- Ignore health check failures
- Mix development and production configs
- Use unstable base images
- Store sensitive data in images
- Skip testing in Docker
- Modify Dockerfile without rebuilding

---

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Reference](https://docs.docker.com/compose/compose-file/)
- [Best Practices for PHP Dockerfiles](https://docs.docker.com/language/php/)
- [MySQL Docker Hub](https://hub.docker.com/_/mysql)
- [Apache Docker Hub](https://hub.docker.com/_/httpd)

---

## Support

For issues:

1. Check this troubleshooting section
2. View logs: `docker-compose logs`
3. Check [DEPLOYMENT.md](./DEPLOYMENT.md)
4. Review [TECHNICAL_ARCHITECTURE.md](../docs/TECHNICAL_ARCHITECTURE.md)

---

**Last Updated:** February 3, 2026  
**Version:** 1.0.0
