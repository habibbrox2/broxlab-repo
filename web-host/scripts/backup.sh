#!/bin/bash

# BroxLab Code Backup Script
# Creates compressed backups of current release before deployment
# Excludes node_modules, vendor, and cache for efficient storage
# Usage: ./backup.sh [--dry-run] [--keep N] [--base PATH]
# Exit codes: 0=success, 1=error, 2=dry-run

set -euo pipefail

# ============== CONFIGURATION ==============
BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
APP="$BASE/app"
BACKUPS="$BASE/backups"
LOGS="$BASE/logs"
CURRENT="$APP/current"
KEEP_COUNT=${BACKUP_KEEP:-10}
DRY_RUN=false

# ============== ARGUMENT PARSING ==============
usage() {
    echo "Usage: $0 [--dry-run] [--keep N] [--base PATH]"
    echo "  --dry-run      Show what would happen without making changes"
    echo "  --keep N       Number of backups to retain (default: 10)"
    echo "  --base PATH    Override base directory"
    exit 1
}

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run) DRY_RUN=true;       shift ;;
        --keep)    KEEP_COUNT="$2";    shift 2 ;;
        --base)    BASE="$2";          shift 2 ;;
        *)         echo "Unknown option: $1"; usage ;;
    esac
done

DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUPS/backup_$DATE.tar.gz"

# ============== COLOR CODES ==============
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============== LOGGING SETUP ==============
# BUG FIX: Log directory must exist before any log_* function is called.
# The original created dirs later in the script, but logging calls happen
# earlier (even inside cleanup_on_error via the trap).
mkdir -p "$BACKUPS" "$LOGS"
LOG_FILE="$LOGS/backup_$DATE.log"

log_info()  { echo -e "${GREEN}[INFO]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_debug() { echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }

# ============== ERROR HANDLING ==============
cleanup_on_error() {
    local exit_code=$?
    # Only treat non-zero, non-dry-run exits as errors
    if [[ $exit_code -ne 0 && $exit_code -ne 2 ]]; then
        log_error "Backup process interrupted (exit code: $exit_code)"
        if [[ -f "$BACKUP_FILE" && "$DRY_RUN" != "true" ]]; then
            rm -f "$BACKUP_FILE"
        fi
    fi
}

# BUG FIX: Trapping both EXIT and ERR with set -e fires the handler twice on
# errors. Trap EXIT only — it always fires and the exit code distinguishes
# success from failure.
trap cleanup_on_error EXIT

# ============== DRY-RUN NOTICE ==============
if $DRY_RUN; then
    log_info "[DRY-RUN MODE] No files will be modified"
fi

log_info "Starting backup of current release..."

# ============== VALIDATION ==============
# BUG FIX: The original test `-L "$CURRENT" || -d "$CURRENT"` was inverted
# logic: it passed even when the symlink was broken (exists as a dangling link
# but the target directory is gone). The correct guard is:
#   - Must be a symlink AND the resolved path must be a real directory.
if [[ ! -L "$CURRENT" ]]; then
    log_error "Current release symlink not found: $CURRENT"
    exit 1
fi
if [[ ! -d "$CURRENT" ]]; then
    log_error "Current release symlink is broken (target missing): $CURRENT"
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

REQUIRED_SPACE=$(( CURRENT_SIZE * 2 ))
# BUG FIX: `df` output column 4 is in 1 KB blocks on Linux, so multiply by
# 1024 to get bytes (original was correct for Linux but fragile; made explicit).
AVAILABLE_SPACE=$(df "$BACKUPS" | tail -1 | awk '{print $4 * 1024}')

log_debug "Current: $(( CURRENT_SIZE   / 1024 / 1024 ))MB | \
Required: $(( REQUIRED_SPACE  / 1024 / 1024 ))MB | \
Available: $(( AVAILABLE_SPACE / 1024 / 1024 ))MB"

if [[ "$AVAILABLE_SPACE" -lt "$REQUIRED_SPACE" ]]; then
    log_warn "Low disk space — pre-cleaning old backups..."
    if $DRY_RUN; then
        log_debug "[DRY-RUN] Would clean old backups"
    else
        # BUG FIX: The original kept only the 5 most recent but KEEP_COUNT could
        # be anything. Use KEEP_COUNT here for consistency with retention policy.
        # Also, the expression `tail -n +6` was hardcoded; now uses KEEP_COUNT.
        ls -t "$BACKUPS"/backup_*.tar.gz 2>/dev/null \
            | tail -n "+$(( KEEP_COUNT + 1 ))" \
            | xargs -r rm -f || true
    fi
fi

# ============== CREATE BACKUP ==============
log_info "Creating backup archive: $(basename "$BACKUP_FILE")"

if $DRY_RUN; then
    log_debug "[DRY-RUN] Would run: tar --exclude='node_modules' --exclude='vendor' \
--exclude='storage/cache' --exclude='.git' --exclude='.env' \
-czf '$BACKUP_FILE' -C '$APP' current"
    exit 2
fi

if tar \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='storage/cache' \
    --exclude='.git' \
    --exclude='.env' \
    --exclude='.gitignore' \
    -czf "$BACKUP_FILE" -C "$APP" current \
    2>>"$LOG_FILE"
then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
    log_info "✅ Backup completed: $BACKUP_SIZE → $(basename "$BACKUP_FILE")"
else
    log_error "❌ Backup failed — tar returned an error"
    rm -f "$BACKUP_FILE"
    exit 1
fi

# ============== RETENTION CLEANUP ==============
log_info "Applying retention policy (keeping last $KEEP_COUNT backups)..."
BACKUP_COUNT=$(ls "$BACKUPS"/backup_*.tar.gz 2>/dev/null | wc -l || echo 0)

if [[ "$BACKUP_COUNT" -gt "$KEEP_COUNT" ]]; then
    FILES_TO_DELETE=$(( BACKUP_COUNT - KEEP_COUNT ))
    log_info "Removing $FILES_TO_DELETE old backup(s) (have $BACKUP_COUNT, keeping $KEEP_COUNT)..."
    ls -t "$BACKUPS"/backup_*.tar.gz 2>/dev/null \
        | tail -n "+$(( KEEP_COUNT + 1 ))" \
        | xargs -r rm -f
    log_info "✅ Old backups removed"
else
    log_debug "No cleanup needed ($BACKUP_COUNT backup(s), limit: $KEEP_COUNT)"
fi

log_info "✅ Backup script completed successfully"
exit 0
