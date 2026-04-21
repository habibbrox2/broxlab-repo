#!/bin/bash

# BroxLab code backup script - Production Ready
# Stores backups in the shared hosting backup area.
# Enhanced with error handling, validation, and safety checks.

set -euo pipefail

# Script configuration
BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
APP="$BASE/app"
SHARED="$APP/shared"
RELEASES="$APP/releases"
BACKUPS="$SHARED/backups/code"
LOGS="$BASE/logs"
CURRENT="$APP/current"
KEEP_COUNT=${BACKUP_KEEP:-10}
DRY_RUN=false
LOCK_FILE="$SHARED/.backup.lock"
BACKUP_TIMEOUT=3600  # 1 hour timeout

usage() {
    echo "Usage: $0 [--dry-run] [--keep N] [--base PATH]"
    exit 1
}

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run) DRY_RUN=true; shift ;;
        --keep) KEEP_COUNT="$2"; shift 2 ;;
        --base) BASE="$2"; shift 2 ;;
        *) echo "Unknown option: $1"; usage ;;
    esac
done

APP="$BASE/app"
SHARED="$APP/shared"
RELEASES="$APP/releases"
BACKUPS="$SHARED/backups/code"
LOGS="$BASE/logs"
CURRENT="$APP/current"
export BASE_PATH="$BASE"

DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUPS/backup_$DATE.tar.gz"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

mkdir -p "$BACKUPS" "$LOGS"
LOG_FILE="$LOGS/backup_$DATE.log"

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_debug() { echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }

# Lock file management
acquire_lock() {
    if [[ -f "$LOCK_FILE" ]]; then
        local lock_pid
        lock_pid=$(cat "$LOCK_FILE" 2>/dev/null || true)
        if [[ -n "$lock_pid" ]] && kill -0 "$lock_pid" 2>/dev/null; then
            log_error "Backup already in progress (PID: $lock_pid)"
            return 1
        fi
    fi
    echo $$ > "$LOCK_FILE"
    return 0
}

release_lock() {
    rm -f "$LOCK_FILE"
}

cleanup() {
    release_lock
}

trap cleanup EXIT

# Acquire lock before proceeding
if ! acquire_lock; then
    exit 1
fi
if $DRY_RUN; then
    log_info "[DRY-RUN] No files will be modified"
fi

if [[ ! -L "$CURRENT" ]]; then
    log_error "Current release symlink not found: $CURRENT"
    exit 1
fi
if [[ ! -d "$CURRENT" ]]; then
    log_error "Current release symlink is broken: $CURRENT"
    exit 1
fi

CURRENT_RELEASE=$(readlink -f "$CURRENT")
CURRENT_RELEASE_NAME=$(basename "$CURRENT_RELEASE")
log_info "Backing up current release: $CURRENT_RELEASE_NAME"

CURRENT_SIZE=$(du -sb "$CURRENT_RELEASE" 2>/dev/null | awk '{print $1}')
REQUIRED_SPACE=$(( CURRENT_SIZE * 2 ))
AVAILABLE_SPACE=$(df "$BACKUPS" | tail -1 | awk '{print $4 * 1024}')

if [[ "$AVAILABLE_SPACE" -lt "$REQUIRED_SPACE" ]]; then
    log_warn "Low disk space, cleaning old backups first"
    find "$BACKUPS" -maxdepth 1 -name "backup_*.tar.gz" -type f -printf '%T@ %p\n' 2>/dev/null \
        | sort -rn | tail -n "+$(( KEEP_COUNT + 1 ))" | awk '{print $2}' | xargs -r rm -f || true
fi

if $DRY_RUN; then
    log_debug "Would create: $BACKUP_FILE"
    exit 2
fi

if tar \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='storage/cache' \
    --exclude='.git' \
    --exclude='.env' \
    --exclude='.gitignore' \
    -czf "$BACKUP_FILE" -C "$RELEASES" "$CURRENT_RELEASE_NAME"
then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
    log_info "Backup completed: $BACKUP_SIZE -> $(basename "$BACKUP_FILE")"
else
    log_error "Backup failed"
    rm -f "$BACKUP_FILE"
    exit 1
fi

BACKUP_COUNT=$(find "$BACKUPS" -maxdepth 1 -name "backup_*.tar.gz" -type f 2>/dev/null | wc -l)
if [[ "$BACKUP_COUNT" -gt "$KEEP_COUNT" ]]; then
    find "$BACKUPS" -maxdepth 1 -name "backup_*.tar.gz" -type f -printf '%T@ %p\n' 2>/dev/null \
        | sort -rn | tail -n "+$(( KEEP_COUNT + 1 ))" | awk '{print $2}' | xargs -r rm -f || true
fi

log_info "Backup script completed successfully"
exit 0
