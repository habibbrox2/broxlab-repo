#!/bin/bash

# BroxLab Database Backup Script
# Creates compressed MySQL/MariaDB backups before deployment
# Includes automatic retention policy and safety checks
# Usage: ./database-backup.sh [--dry-run] [--keep N] [OPTIONS]
# Exit codes: 0=success, 1=error, 2=dry-run, 3=skipped
#
# ENVIRONMENT VARIABLES:
#   DB_HOST, DB_USER, DB_PASS, DB_NAME (from .env or explicit)
#   BASE_PATH, KEEP_DB_BACKUPS, MYSQLDUMP_OPTS

set -euo pipefail

# ============== CONFIGURATION ==============
BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
APP="$BASE/app"
SHARED="$APP/shared"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
ENV_FILE="$SHARED/.env"
KEEP_BACKUPS=${KEEP_DB_BACKUPS:-10}
DRY_RUN=false

# ============== ARGUMENT PARSING ==============
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run) DRY_RUN=true; shift ;;
        --keep) KEEP_BACKUPS="$2"; shift 2 ;;
        --base) BASE="$2"; shift 2 ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
done

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$DB_BACKUPS/database_backup_$TIMESTAMP.sql.gz"
LOG_FILE="$LOGS/database-backup_$TIMESTAMP.log"

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
    log_error "Database backup interrupted"
    [[ -f "$BACKUP_FILE" && $DRY_RUN != true ]] && rm -f "$BACKUP_FILE"
}

trap cleanup_on_error EXIT ERR

# ============== INITIALIZATION ==============
mkdir -p "$DB_BACKUPS" "$LOGS"

if $DRY_RUN; then
    log_info "[DRY-RUN MODE] No backup will be created"
fi

log_info "Starting database backup..."

# ============== PREREQUISITE CHECKS ==============
if ! command -v mysqldump &> /dev/null; then
    log_warn "mysqldump command not found. Database backup skipped."
    exit 3
fi

# ============== LOAD DATABASE CREDENTIALS ==============
if [[ ! -f "$ENV_FILE" ]]; then
    log_error ".env file not found at: $ENV_FILE"
    log_error "Database backup cannot proceed without credentials"
    exit 1
fi

# Extract database variables from .env (strict mode)
DB_HOST=$(grep -i "^DB_HOST=" "$ENV_FILE" | head -1 | cut -d'=' -f2 | tr -d ' "' 2>/dev/null || echo "")
DB_USER=$(grep -i "^DB_USER=" "$ENV_FILE" | head -1 | cut -d'=' -f2 | tr -d ' "' 2>/dev/null || echo "")
DB_PASS=$(grep -i "^DB_PASS=" "$ENV_FILE" | head -1 | cut -d'=' -f2 | tr -d ' "' 2>/dev/null || echo "")
DB_NAME=$(grep -i "^DB_NAME=" "$ENV_FILE" | head -1 | cut -d'=' -f2 | tr -d ' "' 2>/dev/null || echo "")

# Validate required variables
if [[ -z "$DB_HOST" ]] || [[ -z "$DB_USER" ]] || [[ -z "$DB_NAME" ]]; then
    log_error "Missing required database configuration in .env"
    log_error "Required: DB_HOST, DB_USER, DB_NAME"
    exit 1
fi

log_debug "Database Host: $DB_HOST"
log_debug "Database Name: $DB_NAME"
log_debug "Database User: $DB_USER"

# ============== DISK SPACE CHECK ==============
REQUIRED_SPACE=$((1 * 1024 * 1024)) # 1GB in KB
AVAILABLE_SPACE=$(df "$DB_BACKUPS" 2>/dev/null | tail -1 | awk '{print $4 * 1024}' || echo 0)

if [[ $AVAILABLE_SPACE -lt $REQUIRED_SPACE ]]; then
    log_warn "Low disk space. Available: $((AVAILABLE_SPACE / 1024 / 1024))MB, Required: 1000MB"
    log_info "Cleaning old database backups..."
    if $DRY_RUN; then
        log_debug "[DRY-RUN] Would clean old database backups"
    else
        ls -t "$DB_BACKUPS"/database_backup_*.sql.gz 2>/dev/null | tail -n +4 | xargs -r rm -f || true
    fi
fi

# ============== CREATE DATABASE BACKUP ==============
log_info "Creating database dump: $(basename "$BACKUP_FILE")"

if $DRY_RUN; then
    mysqldump_cmd="mysqldump --host=$DB_HOST --user=$DB_USER --single-transaction --quick --lock-tables=false $DB_NAME"
    log_debug "[DRY-RUN] Would execute: $mysqldump_cmd | gzip > $BACKUP_FILE"
    exit 2
fi

MYSQLDUMP_RESULT=1

# Use stdin for password to avoid command-line exposure (security best practice)
if [[ -n "$DB_PASS" ]]; then
    if echo "$DB_PASS" | mysqldump --host=$DB_HOST --user=$DB_USER --password --single-transaction --quick --lock-tables=false "$DB_NAME" 2>>"$LOG_FILE" | gzip > "$BACKUP_FILE"; then
        MYSQLDUMP_RESULT=0
    fi
else
    if mysqldump --host=$DB_HOST --user=$DB_USER --single-transaction --quick --lock-tables=false "$DB_NAME" 2>>"$LOG_FILE" | gzip > "$BACKUP_FILE"; then
        MYSQLDUMP_RESULT=0
    fi
fi

if [[ $MYSQLDUMP_RESULT -eq 0 ]]; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
    log_info "✅ Database backup completed successfully: $BACKUP_SIZE"
    
    # ============== RETENTION CLEANUP ==============
    log_info "Applying retention policy (keeping last $KEEP_BACKUPS backups)..."
    DB_BACKUP_COUNT=$(ls $DB_BACKUPS/database_backup_*.sql.gz 2>/dev/null | wc -l || echo 0)
    if [[ $DB_BACKUP_COUNT -gt $KEEP_BACKUPS ]]; then
        FILES_TO_DELETE=$((DB_BACKUP_COUNT - KEEP_BACKUPS))
        log_info "Deleted $FILES_TO_DELETE old database backups (kept $KEEP_BACKUPS, had $DB_BACKUP_COUNT)"
        ls -t "$DB_BACKUPS"/database_backup_*.sql.gz 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm -f || true
    else
        log_debug "No cleanup needed ($DB_BACKUP_COUNT backups, limit: $KEEP_BACKUPS)"
    fi
    
    # Create symlink to latest backup for easy restore
    ln -sf "$BACKUP_FILE" "$DB_BACKUPS/latest.sql.gz" 2>/dev/null || true
    log_debug "Latest backup symlink updated"
else
    log_error "❌ Database backup failed"
    log_error "Check MySQL connectivity and credentials at $DB_HOST:$DB_NAME"
    rm -f "$BACKUP_FILE"
    # Non-blocking: deployment should continue even if DB backup fails
    exit 0
fi

log_info "✅ Database backup script completed successfully"
exit 0
