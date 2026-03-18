#!/bin/bash

# Database Backup Script for BroxBhai Deployment
# Creates compressed MySQL/MariaDB backups before deployment

set -e

BASE="/home/tdhuedhn/broxlab"
APP="$BASE/app"
SHARED="$APP/shared"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
ENV_FILE="$SHARED/.env"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$DB_BACKUPS/database_backup_$TIMESTAMP.sql.gz"
LOG_FILE="$LOGS/database-backup_$TIMESTAMP.log"

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Logging functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Ensure directories exist
mkdir -p "$DB_BACKUPS" "$LOGS"

log_info "Starting database backup..."

# Check if MySQL is available
if ! command -v mysqldump &> /dev/null; then
    log_warn "mysqldump not found. Database backup skipped."
    exit 0
fi

# Load database credentials from .env
if [[ ! -f "$ENV_FILE" ]]; then
    log_warn ".env file not found. Using default MySQL credentials."
    DB_HOST="localhost"
    DB_USER="root"
    DB_PASS=""
    DB_NAME="broxlab"
else
    # Extract database variables from .env
    DB_HOST=$(grep -i "^DB_HOST=" "$ENV_FILE" | cut -d'=' -f2 | tr -d ' "' || echo "localhost")
    DB_USER=$(grep -i "^DB_USER=" "$ENV_FILE" | cut -d'=' -f2 | tr -d ' "' || echo "root")
    DB_PASS=$(grep -i "^DB_PASS=" "$ENV_FILE" | cut -d'=' -f2 | tr -d ' "' || echo "")
    DB_NAME=$(grep -i "^DB_NAME=" "$ENV_FILE" | cut -d'=' -f2 | tr -d ' "' || echo "broxlab")
fi

log_info "Database Host: $DB_HOST"
log_info "Database Name: $DB_NAME"
log_info "Database User: $DB_USER"

# Check disk space (need at least 1GB for backup)
REQUIRED_SPACE=$((1 * 1024 * 1024)) # 1GB in MB
AVAILABLE_SPACE=$(df "$DB_BACKUPS" | tail -1 | awk '{print $4 * 1024}')

if [[ $AVAILABLE_SPACE -lt $REQUIRED_SPACE ]]; then
    log_warn "Low disk space for database backup. Available: $((AVAILABLE_SPACE / 1024 / 1024))MB, Required: 1000MB"
    log_info "Cleaning old database backups..."
    ls -t "$DB_BACKUPS"/database_backup_*.sql.gz 2>/dev/null | tail -n +4 | xargs -r rm -f
fi

# Create MySQL backup
log_info "Creating database dump..."
MYSQLDUMP_CMD="mysqldump --host=$DB_HOST --user=$DB_USER"

if [[ -n "$DB_PASS" ]]; then
    MYSQLDUMP_CMD="$MYSQLDUMP_CMD --password=$DB_PASS"
fi

MYSQLDUMP_CMD="$MYSQLDUMP_CMD --single-transaction --quick --lock-tables=false $DB_NAME"

if $MYSQLDUMP_CMD 2>>"$LOG_FILE" | gzip > "$BACKUP_FILE"; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
    log_info "✅ Database backup completed successfully: $BACKUP_SIZE"
    
    # Keep only last 10 database backups
    log_info "Cleaning old database backups (keeping last 10)..."
    ls -t "$DB_BACKUPS"/database_backup_*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
    log_info "Old database backups cleaned"
    
    # Create symlink to latest backup for easy restore
    ln -sf "$BACKUP_FILE" "$DB_BACKUPS/latest.sql.gz" 2>/dev/null || true
    log_info "Latest backup symlink updated"
else
    log_error "❌ Database backup failed"
    log_error "Check MySQL connectivity and credentials"
    # Don't exit with error - deployment should continue even if DB backup fails
    exit 0
fi

log_info "✅ Database backup script completed successfully"
