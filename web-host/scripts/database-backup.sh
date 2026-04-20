#!/bin/bash

# BroxLab database backup script for shared hosting.

set -euo pipefail

BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
APP="$BASE/app"
SHARED="$APP/shared"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
ENV_FILE="$SHARED/.env"
KEEP_BACKUPS=${KEEP_DB_BACKUPS:-10}
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run) DRY_RUN=true; shift ;;
        --keep) KEEP_BACKUPS="$2"; shift 2 ;;
        --base) BASE="$2"; shift 2 ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
done

APP="$BASE/app"
SHARED="$APP/shared"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
ENV_FILE="$SHARED/.env"
export BASE_PATH="$BASE"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$DB_BACKUPS/database_backup_$TIMESTAMP.sql.gz"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

mkdir -p "$DB_BACKUPS" "$LOGS"
LOG_FILE="$LOGS/database-backup_$TIMESTAMP.log"

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_debug() { echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }

parse_env() {
    local key="$1"
    grep -i "^${key}=" "$ENV_FILE" | head -1 | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs
}

if ! command -v mysqldump >/dev/null 2>&1; then
    log_warn "mysqldump not found; database backup skipped"
    exit 3
fi

if [[ ! -f "$ENV_FILE" ]]; then
    log_error ".env not found at $ENV_FILE"
    exit 1
fi

DB_HOST=$(parse_env "DB_HOST")
DB_USER=$(parse_env "DB_USER")
DB_PASS=$(parse_env "DB_PASS")
DB_NAME=$(parse_env "DB_NAME")

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-broxlab}"

log_debug "Target: ${DB_HOST}:3306 / ${DB_NAME} / ${DB_USER}"

if $DRY_RUN; then
    log_info "[DRY-RUN] Would create $BACKUP_FILE"
    exit 2
fi

if [[ -n "$DB_PASS" ]]; then
    if ! MYSQL_PWD="$DB_PASS" mysqldump --host="$DB_HOST" --user="$DB_USER" --single-transaction --quick --lock-tables=false "$DB_NAME" | gzip > "$BACKUP_FILE"; then
        log_error "MySQL not reachable at ${DB_HOST}:3306/${DB_NAME}"
        log_error "Check DB_HOST, DB_USER, DB_PASS, and DB_NAME"
        rm -f "$BACKUP_FILE"
        exit 1
    fi
else
    if ! mysqldump --host="$DB_HOST" --user="$DB_USER" --single-transaction --quick --lock-tables=false "$DB_NAME" | gzip > "$BACKUP_FILE"; then
        log_error "MySQL not reachable at ${DB_HOST}:3306/${DB_NAME}"
        log_error "Check DB_HOST, DB_USER, DB_PASS, and DB_NAME"
        rm -f "$BACKUP_FILE"
        exit 1
    fi
fi

if [[ ! -s "$BACKUP_FILE" ]]; then
    log_error "Backup file is empty"
    rm -f "$BACKUP_FILE"
    exit 1
fi

ln -sf "$BACKUP_FILE" "$DB_BACKUPS/latest.sql.gz" 2>/dev/null || true

COUNT=$(find "$DB_BACKUPS" -maxdepth 1 -name "database_backup_*.sql.gz" -type f 2>/dev/null | wc -l)
if [[ "$COUNT" -gt "$KEEP_BACKUPS" ]]; then
    find "$DB_BACKUPS" -maxdepth 1 -name "database_backup_*.sql.gz" -type f -printf '%T@ %p\n' 2>/dev/null \
        | sort -rn | tail -n "+$(( KEEP_BACKUPS + 1 ))" | awk '{print $2}' | xargs -r rm -f || true
fi

log_info "Database backup completed: $(basename "$BACKUP_FILE")"
exit 0
