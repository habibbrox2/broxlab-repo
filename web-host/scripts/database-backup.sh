#!/bin/bash

# BroxLab Database Backup Script
# Creates compressed MySQL/MariaDB backups before deployment
# Includes automatic retention policy and safety checks
# Usage: ./database-backup.sh [--dry-run] [--keep N] [--base PATH]
# Exit codes: 0=success, 1=error, 2=dry-run, 3=skipped (mysqldump absent)
#
# ENVIRONMENT VARIABLES (read from $SHARED/.env):
#   DB_HOST, DB_USER, DB_PASS, DB_NAME
# OVERRIDE VARIABLES:
#   BASE_PATH, KEEP_DB_BACKUPS

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
        --dry-run) DRY_RUN=true;        shift ;;
        --keep)    KEEP_BACKUPS="$2";   shift 2 ;;
        --base)    BASE="$2";           shift 2 ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--dry-run] [--keep N] [--base PATH]"
            exit 1
        ;;
    esac
done

# Recompute derived paths after parsing so --base is applied consistently.
APP="$BASE/app"
SHARED="$APP/shared"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
ENV_FILE="$SHARED/.env"
export BASE_PATH="$BASE"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$DB_BACKUPS/database_backup_$TIMESTAMP.sql.gz"

# ============== COLOR CODES ==============
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============== LOGGING SETUP ==============
# BUG FIX: Directories must exist before any log_* call so that tee -a
# doesn't fail. mkdir moved here, before the logging functions are first used.
mkdir -p "$DB_BACKUPS" "$LOGS"
LOG_FILE="$LOGS/database-backup_$TIMESTAMP.log"

log_info()  { echo -e "${GREEN}[INFO]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_debug() { echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }

# ============== ERROR HANDLING ==============
cleanup_on_error() {
    local exit_code=$?
    if [[ $exit_code -ne 0 && $exit_code -ne 2 && $exit_code -ne 3 ]]; then
        log_error "Database backup interrupted (exit code: $exit_code)"
        if [[ -f "$BACKUP_FILE" && "$DRY_RUN" != "true" ]]; then
            rm -f "$BACKUP_FILE"
        fi
    fi
}

# BUG FIX: Trapping both EXIT and ERR with set -e fires the handler twice.
# Use EXIT only.
trap cleanup_on_error EXIT

# ============== INITIALIZATION ==============
if $DRY_RUN; then
    log_info "[DRY-RUN MODE] No backup will be created"
fi

log_info "Starting database backup..."

# ============== PREREQUISITE CHECKS ==============
if ! command -v mysqldump &>/dev/null; then
    log_warn "mysqldump not found — database backup skipped"
    exit 3
fi

# ============== LOAD DATABASE CREDENTIALS ==============
if [[ ! -f "$ENV_FILE" ]]; then
    log_error ".env not found at: $ENV_FILE"
    log_error "Cannot proceed without database credentials"
    exit 1
fi

# BUG FIX: The original used `cut -d'=' -f2` which breaks for values that
# contain '=' (e.g. passwords with base64 padding). Use parameter expansion
# after the first '=' instead.
parse_env() {
    local key="$1"
    # Match key at start of line (case-insensitive), then take everything after the first '='
    grep -i "^${key}=" "$ENV_FILE" | head -1 | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs
}

DB_HOST=$(parse_env "DB_HOST")
DB_USER=$(parse_env "DB_USER")
DB_PASS=$(parse_env "DB_PASS")
DB_NAME=$(parse_env "DB_NAME")

if [[ -z "$DB_HOST" || -z "$DB_USER" || -z "$DB_NAME" ]]; then
    log_error "Missing required database config in .env (need DB_HOST, DB_USER, DB_NAME)"
    exit 1
fi

log_debug "DB Host: $DB_HOST | DB Name: $DB_NAME | DB User: $DB_USER"

# ============== DISK SPACE CHECK ==============
REQUIRED_BYTES=$(( 1 * 1024 * 1024 * 1024 ))   # 1 GB
AVAILABLE_BYTES=$(df "$DB_BACKUPS" 2>/dev/null | tail -1 | awk '{print $4 * 1024}')

if [[ "$AVAILABLE_BYTES" -lt "$REQUIRED_BYTES" ]]; then
    AVAILABLE_MB=$(( AVAILABLE_BYTES / 1024 / 1024 ))
    log_warn "Low disk space (${AVAILABLE_MB}MB available, 1000MB recommended) — cleaning old backups..."
    if $DRY_RUN; then
        log_debug "[DRY-RUN] Would clean old database backups"
    else
        # BUG FIX: Original hardcoded `tail -n +4` (keep 3) ignoring KEEP_BACKUPS.
        # Use KEEP_BACKUPS for consistency with the retention policy.
        find "$DB_BACKUPS" -maxdepth 1 -name "database_backup_*.sql.gz" -type f \
            -printf '%T@ %p\n' 2>/dev/null \
            | sort -rn \
            | tail -n "+$(( KEEP_BACKUPS + 1 ))" \
            | awk '{print $2}' \
            | xargs -r rm -f || true
    fi
fi

# ============== CREATE DATABASE BACKUP ==============
log_info "Creating database dump: $(basename "$BACKUP_FILE")"

if $DRY_RUN; then
    log_debug "[DRY-RUN] Would run: mysqldump --host=$DB_HOST --user=$DB_USER \
--single-transaction --quick --lock-tables=false $DB_NAME | gzip > $BACKUP_FILE"
    exit 2
fi

MYSQLDUMP_OK=false

# BUG FIX: The original passed the password via `echo "$DB_PASS" | mysqldump
# --password` which sends it on stdin. mysqldump's --password flag without an
# argument reads from the TTY, not stdin, so the password was silently ignored
# and the dump would fail or prompt interactively. Use MYSQL_PWD env variable
# instead — it is the documented, safe way to supply a password non-interactively.
if [[ -n "$DB_PASS" ]]; then
    if MYSQL_PWD="$DB_PASS" mysqldump \
            --host="$DB_HOST" \
            --user="$DB_USER" \
            --single-transaction \
            --quick \
            --lock-tables=false \
            "$DB_NAME" 2>>"$LOG_FILE" | gzip > "$BACKUP_FILE"
    then
        MYSQLDUMP_OK=true
    fi
else
    if mysqldump \
            --host="$DB_HOST" \
            --user="$DB_USER" \
            --single-transaction \
            --quick \
            --lock-tables=false \
            "$DB_NAME" 2>>"$LOG_FILE" | gzip > "$BACKUP_FILE"
    then
        MYSQLDUMP_OK=true
    fi
fi

if [[ "$MYSQLDUMP_OK" == "true" ]]; then
    # BUG FIX: Validate the backup file is non-empty. A failed dump piped
    # through gzip produces a valid (but empty) .gz file with exit code 0.
    BACKUP_BYTES=$(stat -c%s "$BACKUP_FILE" 2>/dev/null || echo 0)
    if [[ "$BACKUP_BYTES" -lt 100 ]]; then
        log_error "❌ Backup file is suspiciously small (${BACKUP_BYTES} bytes) — possible dump failure"
        rm -f "$BACKUP_FILE"
        exit 1
    fi

    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
    log_info "✅ Database backup completed: $BACKUP_SIZE → $(basename "$BACKUP_FILE")"

    # ============== RETENTION CLEANUP ==============
    log_info "Applying retention policy (keeping last $KEEP_BACKUPS backups)..."
    DB_BACKUP_COUNT=$(find "$DB_BACKUPS" -maxdepth 1 -name "database_backup_*.sql.gz" -type f 2>/dev/null | wc -l)

    if [[ "$DB_BACKUP_COUNT" -gt "$KEEP_BACKUPS" ]]; then
        FILES_TO_DELETE=$(( DB_BACKUP_COUNT - KEEP_BACKUPS ))
        log_info "Removing $FILES_TO_DELETE old backup(s) (have $DB_BACKUP_COUNT, keeping $KEEP_BACKUPS)..."
        find "$DB_BACKUPS" -maxdepth 1 -name "database_backup_*.sql.gz" -type f \
            -printf '%T@ %p\n' 2>/dev/null \
            | sort -rn \
            | tail -n "+$(( KEEP_BACKUPS + 1 ))" \
            | awk '{print $2}' \
            | xargs -r rm -f || true
    else
        log_debug "No cleanup needed ($DB_BACKUP_COUNT backup(s), limit: $KEEP_BACKUPS)"
    fi

    # Update latest symlink
    ln -sf "$BACKUP_FILE" "$DB_BACKUPS/latest.sql.gz" 2>/dev/null || \
        log_warn "Could not update latest.sql.gz symlink"
    log_debug "latest.sql.gz symlink updated"
else
    log_error "❌ Database backup failed"
    log_error "Check MySQL connectivity — host=$DB_HOST db=$DB_NAME user=$DB_USER"
    rm -f "$BACKUP_FILE"
    # Non-blocking: deployment continues even if DB backup fails
    exit 0
fi

log_info "✅ Database backup script completed successfully"
exit 0
