#!/bin/bash

# BroxLab Database Restore Script
# Safely restores MySQL/MariaDB from compressed backups
# Includes safety backups and user confirmation
# Usage: ./database-restore.sh [backup_file]
# Exit codes: 0=success, 1=error, 3=aborted by user

set -euo pipefail

# ============== CONFIGURATION ==============
BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
APP="$BASE/app"
SHARED="$APP/shared"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
ENV_FILE="$SHARED/.env"

# ============== COLOR CODES ==============
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============== LOGGING FUNCTIONS ==============
log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_debug() {
    echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# ============== ERROR HANDLING ==============
cleanup_on_error() {
    log_error "Database restore interrupted"
}

trap cleanup_on_error EXIT ERR

# ============== INITIALIZATION ==============
mkdir -p "$LOGS"

# Determine backup file to restore
BACKUP_FILE="${1:--}"  # Use argument or default

if [[ "$BACKUP_FILE" == "-" ]]; then
    # Try to use latest symlink or find most recent backup
    if [[ -L "$DB_BACKUPS/latest.sql.gz" ]]; then
        BACKUP_FILE="$DB_BACKUPS/latest.sql.gz"
    else
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

log_info "Starting database restore process..."
log_debug "Backup file: $BACKUP_FILE"

# ============== VALIDATION ==============
# Validate backup file exists
if [[ ! -f "$BACKUP_FILE" ]]; then
    log_error "Backup file not found: $BACKUP_FILE"
    exit 1
fi

BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
log_info "Backup size: $BACKUP_SIZE"

# Check if MySQL is available
if ! command -v mysql &> /dev/null; then
    log_error "mysql client not found. Cannot restore database."
    exit 1
fi

# ============== LOAD DATABASE CREDENTIALS ==============
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

log_debug "Database Host: $DB_HOST"
log_debug "Database Name: $DB_NAME"
log_debug "Database User: $DB_USER"

# ============== PRE-RESTORE SAFETY BACKUP ==============
log_info "Creating safety backup of current database before restore..."
SAFETY_BACKUP="$SHARED/backups/database/pre-restore_$TIMESTAMP.sql.gz"
mkdir -p "$(dirname "$SAFETY_BACKUP")"

MYSQLDUMP_OK=false

# Use stdin for password to avoid command-line exposure
if [[ -n "$DB_PASS" ]]; then
    if echo "$DB_PASS" | mysqldump --host=$DB_HOST --user=$DB_USER --password --single-transaction --quick --lock-tables=false "$DB_NAME" 2>>"$LOG_FILE" | gzip > "$SAFETY_BACKUP"; then
        MYSQLDUMP_OK=true
    fi
else
    if mysqldump --host=$DB_HOST --user=$DB_USER --single-transaction --quick --lock-tables=false "$DB_NAME" 2>>"$LOG_FILE" | gzip > "$SAFETY_BACKUP"; then
        MYSQLDUMP_OK=true
    fi
fi

if [[ "$MYSQLDUMP_OK" == "true" ]]; then
    SAFETY_SIZE=$(du -h "$SAFETY_BACKUP" | awk '{print $1}')
    log_info "✅ Safety backup created: $SAFETY_SIZE"
    log_debug "Safety backup saved to: $SAFETY_BACKUP"
else
    log_warn "⚠️  Could not create safety backup. Continuing anyway..."
fi

# ============== CONFIRMATION PROMPT ==============
if [[ -t 0 ]]; then
    # Running interactively
    log_warn ""
    log_warn "⚠️  WARNING: This will restore the database from backup"
    log_warn "⚠️  Current database WILL BE OVERWRITTEN!"
    log_warn "⚠️  Backup file: $(basename "$BACKUP_FILE")"
    echo ""
    read -p "Are you sure you want to restore? (type 'yes' to confirm): " CONFIRM
    if [[ "$CONFIRM" != "yes" ]]; then
        log_info "Restore cancelled by user"
        exit 3
    fi
else
    # Running non-interactively (cron, CI/CD, etc.)
    if [[ "$FORCE_RESTORE" != "true" ]]; then
        log_error "Cannot restore database in non-interactive mode without FORCE_RESTORE=true"
        log_info "Usage: FORCE_RESTORE=true $0 [backup_file]"
        exit 1
    fi
    log_warn "Non-interactive restore: FORCE_RESTORE=true (database WILL BE OVERWRITTEN!)"
fi

# ============== PERFORM RESTORE ==============
log_info "Restoring database from backup..."

RESTORE_OK=false

# Use stdin for password to avoid command-line exposure
if [[ -n "$DB_PASS" ]]; then
    if zcat "$BACKUP_FILE" 2>>"$LOG_FILE" | echo "$DB_PASS" | mysql --host=$DB_HOST --user=$DB_USER --password "$DB_NAME" 2>>"$LOG_FILE"; then
        RESTORE_OK=true
    fi
else
    if zcat "$BACKUP_FILE" 2>>"$LOG_FILE" | mysql --host=$DB_HOST --user=$DB_USER "$DB_NAME" 2>>"$LOG_FILE"; then
        RESTORE_OK=true
    fi
fi

if [[ "$RESTORE_OK" == "true" ]]; then
    log_info "✅ Database restore completed successfully"
    echo ""
    log_warn "⚠️  Please verify data integrity before considering restore complete"
    log_info "   - Check critical tables and data"
    log_info "   - Monitor application logs"
    if [[ -f "$SAFETY_BACKUP" ]]; then
        log_info "   - Safety backup available at: $SAFETY_BACKUP"
    fi
else
    log_error "❌ Database restore failed"
    log_error "Safety backup available at: $SAFETY_BACKUP"
    log_error "Check MySQL connectivity and credentials"
    exit 1
fi

log_info "✅ Database restore script completed successfully"
exit 0
