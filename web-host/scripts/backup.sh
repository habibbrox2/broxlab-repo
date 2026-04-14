#!/bin/bash

# BroxLab Code Backup Script
# Creates compressed backups of current release before deployment
# Excludes node_modules, vendor, and cache for efficient storage
# Usage: ./backup.sh [--dry-run] [--keep N]
# Exit codes: 0=success, 1=error, 2=dry-run

set -euo pipefail

# ============== CONFIGURATION ==============
BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
APP="$BASE/app"
BACKUPS="$BASE/backups"
LOGS="$BASE/logs"
CURRENT="$APP/current"
KEEP_COUNT=${BACKUP_KEEP:-10}  # Default: keep last 10 backups
DRY_RUN=false

# ============== ARGUMENT PARSING ==============
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run) DRY_RUN=true; shift ;;
        --keep) KEEP_COUNT="$2"; shift 2 ;;
        --base) BASE="$2"; shift 2 ;;
        *) echo "Unknown option: $1"; usage; exit 1 ;;
    esac
done

DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUPS/backup_$DATE.tar.gz"
LOG_FILE="$LOGS/backup_$DATE.log"

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

# ============== UTILITY FUNCTIONS ==============
cleanup_on_error() {
    log_error "Backup process interrupted"
    [[ -f "$BACKUP_FILE" && $DRY_RUN != true ]] && rm -f "$BACKUP_FILE"
}

trap cleanup_on_error EXIT ERR

# ============== VALIDATION ==============
mkdir -p "$BACKUPS" "$LOGS"

if $DRY_RUN; then
    log_info "[DRY-RUN MODE] No files will be modified"
fi

log_info "Starting backup of current release..."

# Validate current release exists
if [[ ! -L "$CURRENT" ]] || [[ ! -d "$CURRENT" ]]; then
    log_error "Current release symlink not found or broken: $CURRENT"
    exit 1
fi

CURRENT_RELEASE=$(readlink "$CURRENT")
log_debug "Current release path: $CURRENT_RELEASE"

# ============== DISK SPACE CHECK ==============
CURRENT_SIZE=$(du -sb "$CURRENT" 2>/dev/null | awk '{print $1}')
if [[ -z "$CURRENT_SIZE" ]]; then
    log_error "Unable to calculate current release size"
    exit 1
fi

REQUIRED_SPACE=$((CURRENT_SIZE * 2))
AVAILABLE_SPACE=$(df "$BACKUPS" | tail -1 | awk '{print $4 * 1024}')

log_debug "Current size: $((CURRENT_SIZE / 1024 / 1024))MB, Required: $((REQUIRED_SPACE / 1024 / 1024))MB, Available: $((AVAILABLE_SPACE / 1024 / 1024))MB"

if [[ $AVAILABLE_SPACE -lt $REQUIRED_SPACE ]]; then
    log_warn "Low disk space detected. Cleaning old backups..."
    if $DRY_RUN; then
        log_debug "[DRY-RUN] Would clean old backups"
    else
        ls -t "$BACKUPS"/backup_*.tar.gz 2>/dev/null | tail -n +6 | xargs -r rm -f || true
    fi
fi

# ============== CREATE BACKUP ==============
log_info "Creating backup archive: $(basename "$BACKUP_FILE")"

if $DRY_RUN; then
    log_debug "[DRY-RUN] Would create backup with: tar --exclude='node_modules' --exclude='vendor' --exclude='storage/cache' --exclude='.git' --exclude='.env' -czf '$BACKUP_FILE' -C '$APP' current"
    exit 2
fi

if tar --exclude='node_modules' --exclude='vendor' --exclude='storage/cache' \
        --exclude='.git' --exclude='.env' --exclude='.gitignore' \
    -czf "$BACKUP_FILE" -C "$APP" current 2>>"$LOG_FILE"; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
    log_info "✅ Backup completed successfully: $BACKUP_SIZE"
    
    # ============== RETENTION CLEANUP ==============
    log_info "Applying retention policy (keeping last $KEEP_COUNT backups)..."
    BACKUP_COUNT=$(ls $BACKUPS/backup_*.tar.gz 2>/dev/null | wc -l || echo 0)
    if [[ $BACKUP_COUNT -gt $KEEP_COUNT ]]; then
        FILES_TO_DELETE=$((BACKUP_COUNT - KEEP_COUNT))
        log_info "Deleting $FILES_TO_DELETE old backups (had $BACKUP_COUNT, keeping $KEEP_COUNT)..."
        ls -t "$BACKUPS"/backup_*.tar.gz 2>/dev/null | tail -n +$((KEEP_COUNT + 1)) | xargs -r rm -f
        log_info "Old backups cleaned"
    else
        log_debug "No cleanup needed ($BACKUP_COUNT backups, limit: $KEEP_COUNT)"
    fi
else
    log_error "❌ Backup failed - tar command returned error"
    rm -f "$BACKUP_FILE"
    exit 1
fi

log_info "✅ Backup script completed successfully"
exit 0