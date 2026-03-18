#!/bin/bash

# Database Restore Script for BroxBhai Deployment
# Restores MySQL/MariaDB from compressed backups

set -e

BASE="/home/tdhuedhn/broxlab"
APP="$BASE/app"
SHARED="$APP/shared"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
ENV_FILE="$SHARED/.env"

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

# Ensure logs directory exists
mkdir -p "$LOGS"

# Determine backup file to restore
BACKUP_FILE="${1:--}"  # Use argument or read from stdin

if [[ "$BACKUP_FILE" == "-" ]]; then
    # Try to use latest symlink
    if [[ -L "$DB_BACKUPS/latest.sql.gz" ]]; then
        BACKUP_FILE="$DB_BACKUPS/latest.sql.gz"
    else
        # Find most recent backup
        BACKUP_FILE=$(ls -t "$DB_BACKUPS"/database_backup_*.sql.gz 2>/dev/null | head -1)
    fi
    
    if [[ -z "$BACKUP_FILE" ]]; then
        echo -e "${RED}[ERROR]${NC} No backup file found and no argument provided"
        echo "Usage: $0 [backup_file]"
        echo "   or: $0  (uses latest backup)"
        exit 1
    fi
fi

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="$LOGS/database-restore_$TIMESTAMP.log"

log_info "Starting database restore from: $BACKUP_FILE"

# Validate backup file exists
if [[ ! -f "$BACKUP_FILE" ]]; then
    log_error "Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Check if MySQL is available
if ! command -v mysql &> /dev/null; then
    log_error "mysql client not found. Cannot restore database."
    exit 1
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

# Create pre-restore backup (optional safety net)
log_info "Creating safety backup of current database..."
SAFETY_BACKUP="$SHARED/backups/database/pre-restore_$TIMESTAMP.sql.gz"
MYSQL_CMD="mysql --host=$DB_HOST --user=$DB_USER"

if [[ -n "$DB_PASS" ]]; then
    MYSQL_CMD="$MYSQL_CMD --password=$DB_PASS"
fi

if mysqldump --host=$DB_HOST --user=$DB_USER $([ -n "$DB_PASS" ] && echo "--password=$DB_PASS") --single-transaction --quick --lock-tables=false $DB_NAME 2>>"$LOG_FILE" | gzip > "$SAFETY_BACKUP"; then
    SAFETY_SIZE=$(du -h "$SAFETY_BACKUP" | awk '{print $1}')
    log_info "✅ Safety backup created: $SAFETY_SIZE at $SAFETY_BACKUP"
else
    log_warn "Could not create safety backup, continuing anyway"
fi

# Ask for confirmation
log_warn "⚠️  WARNING: This will restore the database from: $(basename $BACKUP_FILE)"
log_warn "⚠️  Current database WILL BE OVERWRITTEN!"
read -p "Are you sure you want to restore? (yes/no): " CONFIRM
if [[ "$CONFIRM" != "yes" ]]; then
    log_info "Restore cancelled"
    exit 0
fi

# Restore database
log_info "Restoring database from backup..."
if zcat "$BACKUP_FILE" 2>>"$LOG_FILE" | $MYSQL_CMD $DB_NAME 2>>"$LOG_FILE"; then
    log_info "✅ Database restore completed successfully"
else
    log_error "❌ Database restore failed"
    log_error "Safety backup available at: $SAFETY_BACKUP"
    exit 1
fi

log_info "✅ Database restore script completed successfully"
log_info "⚠️  Please verify data integrity before considering restore complete"
