#!/bin/bash

# BroxLab database restore script for shared hosting - Production Ready
# Safely restores MySQL database from compressed backups.
# Enhanced with safety backups, validation, and confirmation.

set -euo pipefail

BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
APP="$BASE/app"
SHARED="$APP/shared"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
ENV_FILE="$SHARED/.env"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

mkdir -p "$LOGS"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="$LOGS/database-restore_$TIMESTAMP.log"

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_debug() { echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }

parse_env() {
    local key="$1"
    grep -i "^${key}=" "$ENV_FILE" | head -1 | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs
}

if [[ $# -gt 0 && -n "$1" ]]; then
    BACKUP_FILE="$1"
else
    if [[ -L "$DB_BACKUPS/latest.sql.gz" && -f "$DB_BACKUPS/latest.sql.gz" ]]; then
        BACKUP_FILE="$DB_BACKUPS/latest.sql.gz"
    else
        BACKUP_FILE=$(find "$DB_BACKUPS" -maxdepth 1 -name "database_backup_*.sql.gz" -type f -printf '%T@ %p\n' 2>/dev/null | sort -rn | awk 'NR==1 {print $2}')
    fi
fi

if [[ -z "${BACKUP_FILE:-}" ]]; then
    log_error "No backup file found"
    exit 1
fi

if [[ ! -f "$BACKUP_FILE" ]]; then
    log_error "Backup file not found: $BACKUP_FILE"
    exit 1
fi

if ! gzip -t "$BACKUP_FILE" 2>/dev/null; then
    log_error "Backup file is not a valid gzip archive"
    exit 1
fi

if ! command -v mysql >/dev/null 2>&1; then
    log_error "mysql client not found"
    exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
    log_warn ".env not found; falling back to localhost/root/broxlab"
    DB_HOST="localhost"
    DB_USER="root"
    DB_PASS=""
    DB_NAME="broxlab"
else
    DB_HOST=$(parse_env "DB_HOST")
    DB_USER=$(parse_env "DB_USER")
    DB_PASS=$(parse_env "DB_PASS")
    DB_NAME=$(parse_env "DB_NAME")
    DB_HOST="${DB_HOST:-localhost}"
    DB_USER="${DB_USER:-root}"
    DB_PASS="${DB_PASS:-}"
    DB_NAME="${DB_NAME:-broxlab}"
fi

log_debug "Target: ${DB_HOST}:3306 / ${DB_NAME} / ${DB_USER}"

SAFETY_BACKUP="$DB_BACKUPS/pre-restore_$TIMESTAMP.sql.gz"
mkdir -p "$DB_BACKUPS"
if [[ -n "$DB_PASS" ]]; then
    if ! MYSQL_PWD="$DB_PASS" mysqldump --host="$DB_HOST" --user="$DB_USER" --single-transaction --quick --lock-tables=false "$DB_NAME" | gzip > "$SAFETY_BACKUP"; then
        log_warn "Could not create a safety backup before restore"
        rm -f "$SAFETY_BACKUP"
        SAFETY_BACKUP=""
    fi
else
    if ! mysqldump --host="$DB_HOST" --user="$DB_USER" --single-transaction --quick --lock-tables=false "$DB_NAME" | gzip > "$SAFETY_BACKUP"; then
        log_warn "Could not create a safety backup before restore"
        rm -f "$SAFETY_BACKUP"
        SAFETY_BACKUP=""
    fi
fi

# Validate backup integrity before restore
if ! gzip -t "$BACKUP_FILE" 2>/dev/null; then
    log_error "Backup file validation failed"
    exit 1
fi

BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
log_info "Backup file size: $BACKUP_SIZE"

if [[ -t 0 ]]; then
    echo ""
    log_warn "This will overwrite the current database"
    log_warn "Backup file: $(basename "$BACKUP_FILE")"
    log_warn "Database:    $DB_NAME @ $DB_HOST"
    if [[ -n "${SAFETY_BACKUP:-}" ]]; then
        log_info "Safety backup: $SAFETY_BACKUP"
    fi
    echo ""
    read -rp "Type 'yes' to confirm restore: " CONFIRM
    if [[ "$CONFIRM" != "yes" ]]; then
        log_info "Restore cancelled"
        exit 3
    fi
elif [[ "${FORCE_RESTORE:-}" != "true" ]]; then
    log_error "Non-interactive restore requires FORCE_RESTORE=true"
    exit 1
fi

if [[ -n "$DB_PASS" ]]; then
    if ! zcat "$BACKUP_FILE" | MYSQL_PWD="$DB_PASS" mysql --host="$DB_HOST" --user="$DB_USER" "$DB_NAME"; then
        log_error "MySQL restore failed at ${DB_HOST}:3306/${DB_NAME}"
        log_error "Safety backup: ${SAFETY_BACKUP:-not available}"
        exit 1
    fi
else
    if ! zcat "$BACKUP_FILE" | mysql --host="$DB_HOST" --user="$DB_USER" "$DB_NAME"; then
        log_error "MySQL restore failed at ${DB_HOST}:3306/${DB_NAME}"
        log_error "Safety backup: ${SAFETY_BACKUP:-not available}"
        exit 1
    fi
fi

log_info "Database restore completed successfully"
exit 0
