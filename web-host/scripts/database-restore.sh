#!/bin/bash

# BroxLab Database Restore Script
# Safely restores MySQL/MariaDB from compressed backups
# Includes pre-restore safety backup and user confirmation
# Usage: ./database-restore.sh [backup_file]
#        FORCE_RESTORE=true ./database-restore.sh [backup_file]  (non-interactive)
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

# ============== LOGGING SETUP ==============
# BUG FIX: Directories and LOG_FILE must be set up before the first log_*
# call. The original declared LOG_FILE after the function definitions but
# before any calls — safe in the original order, but fragile. Consolidated here.
mkdir -p "$LOGS"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="$LOGS/database-restore_$TIMESTAMP.log"

log_info()  { echo -e "${GREEN}[INFO]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_debug() { echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }

# ============== ERROR HANDLING ==============
cleanup_on_error() {
    local exit_code=$?
    if [[ $exit_code -ne 0 && $exit_code -ne 3 ]]; then
        log_error "Database restore interrupted (exit code: $exit_code)"
    fi
}

# BUG FIX: Trap EXIT only (not ERR) to avoid double-firing with set -e.
trap cleanup_on_error EXIT

# Recompute derived paths after argument parsing so --base is applied before
# backup resolution and all subsequent filesystem lookups.
APP="$BASE/app"
SHARED="$APP/shared"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
ENV_FILE="$SHARED/.env"
export BASE_PATH="$BASE"

# ============== RESOLVE BACKUP FILE ==============
# BUG FIX: Original used `BACKUP_FILE="${1:--}"` and then checked for "-" to
# detect "no argument". This is ambiguous — a file literally named "-" (rare
# but valid) would be treated as "use latest". Use a proper conditional instead.
if [[ $# -gt 0 && -n "$1" ]]; then
    BACKUP_FILE="$1"
else
    # Auto-select: prefer the latest symlink, then fall back to most recent file
    if [[ -L "$DB_BACKUPS/latest.sql.gz" && -f "$DB_BACKUPS/latest.sql.gz" ]]; then
        BACKUP_FILE="$DB_BACKUPS/latest.sql.gz"
    else
        BACKUP_FILE=$(find "$DB_BACKUPS" -maxdepth 1 -name "database_backup_*.sql.gz" -type f \
            -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1 | awk '{print $2}')
    fi

    if [[ -z "$BACKUP_FILE" ]]; then
        echo -e "${RED}[ERROR]${NC} No backup file found and none provided"
        echo "Usage: $0 [backup_file]"
        echo "   or: $0         (uses most recent backup)"
        exit 1
    fi
fi

log_info "Starting database restore process..."
log_debug "Backup file: $BACKUP_FILE"

# ============== VALIDATION ==============
if [[ ! -f "$BACKUP_FILE" ]]; then
    log_error "Backup file not found: $BACKUP_FILE"
    exit 1
fi

# BUG FIX: Validate the backup file is a valid gzip archive before attempting
# a restore. A truncated or corrupted file would destroy the database and leave
# nothing to recover from.
if ! gzip -t "$BACKUP_FILE" 2>/dev/null; then
    log_error "Backup file is corrupted or not a valid gzip archive: $BACKUP_FILE"
    exit 1
fi

BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
log_info "Backup size: $BACKUP_SIZE"

# Check mysql client
if ! command -v mysql &>/dev/null; then
    log_error "mysql client not found — cannot restore"
    exit 1
fi

# ============== LOAD DATABASE CREDENTIALS ==============
parse_env() {
    local key="$1"
    # BUG FIX: Use cut -f2- (not -f2) so values containing '=' are preserved
    # (e.g. base64 passwords). Strip both double and single quotes.
    grep -i "^${key}=" "$ENV_FILE" | head -1 | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs
}

if [[ ! -f "$ENV_FILE" ]]; then
    log_warn ".env not found — using fallback defaults (localhost/root/broxlab)"
    DB_HOST="localhost"
    DB_USER="root"
    DB_PASS=""
    DB_NAME="broxlab"
else
    DB_HOST=$(parse_env "DB_HOST")
    DB_USER=$(parse_env "DB_USER")
    DB_PASS=$(parse_env "DB_PASS")
    DB_NAME=$(parse_env "DB_NAME")

    # Apply defaults for any missing values
    DB_HOST="${DB_HOST:-localhost}"
    DB_USER="${DB_USER:-root}"
    DB_PASS="${DB_PASS:-}"
    DB_NAME="${DB_NAME:-broxlab}"
fi

log_debug "DB Host: $DB_HOST | DB Name: $DB_NAME | DB User: $DB_USER"

# ============== PRE-RESTORE SAFETY BACKUP ==============
log_info "Creating safety backup of current database before restore..."
SAFETY_BACKUP="$DB_BACKUPS/pre-restore_$TIMESTAMP.sql.gz"
mkdir -p "$(dirname "$SAFETY_BACKUP")"
SAFETY_OK=false

# BUG FIX: Same stdin-password bug as in database-backup.sh — `echo $DB_PASS |
# mysqldump --password` doesn't work. Use MYSQL_PWD env variable instead.
if [[ -n "$DB_PASS" ]]; then
    if MYSQL_PWD="$DB_PASS" mysqldump \
            --host="$DB_HOST" --user="$DB_USER" \
            --single-transaction --quick --lock-tables=false \
            "$DB_NAME" 2>>"$LOG_FILE" | gzip > "$SAFETY_BACKUP"
    then
        SAFETY_OK=true
    fi
else
    if mysqldump \
            --host="$DB_HOST" --user="$DB_USER" \
            --single-transaction --quick --lock-tables=false \
            "$DB_NAME" 2>>"$LOG_FILE" | gzip > "$SAFETY_BACKUP"
    then
        SAFETY_OK=true
    fi
fi

if [[ "$SAFETY_OK" == "true" ]]; then
    SAFETY_SIZE=$(du -h "$SAFETY_BACKUP" | awk '{print $1}')
    log_info "✅ Safety backup created ($SAFETY_SIZE): $SAFETY_BACKUP"
else
    log_warn "⚠️  Could not create safety backup — proceeding anyway"
    # Remove the (possibly empty) file so it doesn't mislead anyone
    rm -f "$SAFETY_BACKUP"
    SAFETY_BACKUP=""
fi

# ============== CONFIRMATION PROMPT ==============
if [[ -t 0 ]]; then
    # Interactive terminal
    echo ""
    log_warn "⚠️  WARNING: This will OVERWRITE the current database!"
    log_warn "   Backup file: $(basename "$BACKUP_FILE")"
    log_warn "   Target DB:   $DB_NAME @ $DB_HOST"
    if [[ -n "$SAFETY_BACKUP" ]]; then
        log_info "   Safety backup: $SAFETY_BACKUP"
    fi
    echo ""
    read -rp "Type 'yes' to confirm restore: " CONFIRM
    if [[ "$CONFIRM" != "yes" ]]; then
        log_info "Restore cancelled by user"
        exit 3
    fi
else
    # Non-interactive (CI/CD, cron, etc.)
    if [[ "${FORCE_RESTORE:-}" != "true" ]]; then
        log_error "Non-interactive restore requires FORCE_RESTORE=true"
        log_info "Usage: FORCE_RESTORE=true $0 [backup_file]"
        exit 1
    fi
    log_warn "Non-interactive restore: FORCE_RESTORE=true — database WILL BE OVERWRITTEN"
fi

# ============== PERFORM RESTORE ==============
log_info "Restoring database from: $(basename "$BACKUP_FILE")..."
RESTORE_OK=false

# BUG FIX: Original had a critical pipe ordering bug:
#   zcat "$FILE" | echo "$DB_PASS" | mysql ...
# `echo "$DB_PASS"` replaces the zcat output entirely — mysql receives only
# the password string, not the SQL. The correct form is either MYSQL_PWD env
# variable or a mysql option file (--defaults-extra-file). Use MYSQL_PWD.
if [[ -n "$DB_PASS" ]]; then
    if zcat "$BACKUP_FILE" 2>>"$LOG_FILE" \
        | MYSQL_PWD="$DB_PASS" mysql --host="$DB_HOST" --user="$DB_USER" "$DB_NAME" 2>>"$LOG_FILE"
    then
        RESTORE_OK=true
    fi
else
    if zcat "$BACKUP_FILE" 2>>"$LOG_FILE" \
        | mysql --host="$DB_HOST" --user="$DB_USER" "$DB_NAME" 2>>"$LOG_FILE"
    then
        RESTORE_OK=true
    fi
fi

if [[ "$RESTORE_OK" == "true" ]]; then
    log_info "✅ Database restore completed successfully"
    echo ""
    log_warn "⚠️  Please verify data integrity before considering restore complete:"
    log_info "  • Check critical tables and data"
    log_info "  • Monitor application logs"
    if [[ -n "$SAFETY_BACKUP" ]]; then
        log_info "  • Safety backup available at: $SAFETY_BACKUP"
    fi
else
    log_error "❌ Database restore failed"
    if [[ -n "$SAFETY_BACKUP" ]]; then
        log_error "Safety backup available at: $SAFETY_BACKUP"
    fi
    log_error "Check MySQL connectivity and credentials"
    exit 1
fi

log_info "✅ Database restore script completed"
exit 0
