#!/bin/bash

# BroxLab Cleanup & Maintenance Script
# Removes old releases, backups, logs, and cache based on retention policy
# Includes Twig template cache cleanup
# Usage: ./cleanup.sh [OPTIONS]
#   --dry-run           Show what would be deleted without actually deleting
#   --releases N        Releases to keep (default: 3)
#   --backups N         File backups to keep (default: 10)
#   --logs-days N       Log files older than N days are deleted (default: 30)
#   --db-backups N      DB backups to keep (default: 10)
#   --base PATH         Override base directory
# Exit codes: 0=success, 1=error, 2=dry-run

set -euo pipefail

# ============== CONFIGURATION ==============
BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
APP="$BASE/app"
RELEASES="$APP/releases"
BACKUPS="$BASE/backups"
LOGS="$BASE/logs"
DB_BACKUPS="$APP/shared/backups/database"

# Retention policy defaults
KEEP_RELEASES=${KEEP_RELEASES:-3}
KEEP_BACKUPS=${KEEP_BACKUPS:-10}
KEEP_LOGS_DAYS=${KEEP_LOGS_DAYS:-30}
KEEP_DB_BACKUPS=${KEEP_DB_BACKUPS:-10}
DRY_RUN=false

# ============== ARGUMENT PARSING ==============
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)   DRY_RUN=true;           shift ;;
        --releases)  KEEP_RELEASES="$2";     shift 2 ;;
        --backups)   KEEP_BACKUPS="$2";      shift 2 ;;
        --logs-days) KEEP_LOGS_DAYS="$2";    shift 2 ;;
        --db-backups) KEEP_DB_BACKUPS="$2"; shift 2 ;;
        --base)      BASE="$2";              shift 2 ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--dry-run] [--releases N] [--backups N] [--logs-days N] [--db-backups N] [--base PATH]"
            exit 1
            ;;
    esac
done

# ============== COLOR CODES ==============
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============== LOGGING SETUP ==============
# BUG FIX: Log directory must exist before first log call. mkdir moved here
# ahead of any log_* invocation to avoid "No such file or directory" errors
# when the directory doesn't yet exist.
mkdir -p "$LOGS"
CLEANUP_LOG="$LOGS/cleanup.log"

log_info()  { echo -e "${GREEN}[INFO]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$CLEANUP_LOG"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$CLEANUP_LOG"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$CLEANUP_LOG"; }
log_debug() { echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$CLEANUP_LOG"; }

# ============== UTILITY FUNCTIONS ==============
# cleanup_directory DIR GLOB KEEP TYPE dry_run
#   TYPE: "releases" — counts/removes subdirectories (ls -d)
#         "files"    — counts/removes files matching GLOB
cleanup_directory() {
    local dir="$1"
    local pattern="$2"
    local keep="$3"
    local type="$4"
    local dry_run="$5"

    if [[ ! -d "$dir" ]]; then
        log_warn "Directory not found, skipping: $dir"
        return 0
    fi

    local count=0
    if [[ "$type" == "releases" ]]; then
        # BUG FIX: `ls -d $dir/*/` without quotes fails (glob expansion error)
        # when the directory is empty. Use find for robustness.
        count=$(find "$dir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l)
    else
        # BUG FIX: Same unquoted glob issue. Use find with -name to match safely.
        count=$(find "$dir" -maxdepth 1 -name "$pattern" -type f 2>/dev/null | wc -l)
    fi

    if [[ "$count" -gt "$keep" ]]; then
        local to_delete=$(( count - keep ))
        if [[ "$dry_run" == "true" ]]; then
            log_debug "[DRY-RUN] Would delete $to_delete item(s) in $dir (have $count, keeping $keep)"
        else
            if [[ "$type" == "releases" ]]; then
                # BUG FIX: `ls -dt $dir/*/` suffers from the same glob issue.
                # Use find + sort by modification time to be portable and safe.
                find "$dir" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null \
                    | sort -rn \
                    | tail -n "+$(( keep + 1 ))" \
                    | awk '{print $2}' \
                    | xargs -r rm -rf 2>/dev/null || true
            else
                find "$dir" -maxdepth 1 -name "$pattern" -type f -printf '%T@ %p\n' 2>/dev/null \
                    | sort -rn \
                    | tail -n "+$(( keep + 1 ))" \
                    | awk '{print $2}' \
                    | xargs -r rm -f 2>/dev/null || true
            fi
            log_info "✅ Deleted $to_delete item(s) in $dir (had $count, kept $keep)"
        fi
    else
        log_debug "No cleanup needed in $dir ($count item(s), limit: $keep)"
    fi
}

# ============== INITIALIZATION ==============
if $DRY_RUN; then
    log_info "[DRY-RUN MODE] No files will be modified"
fi

log_info "Starting cleanup operations..."
log_debug "Policy: releases=$KEEP_RELEASES | backups=$KEEP_BACKUPS | logs=${KEEP_LOGS_DAYS}d | db-backups=$KEEP_DB_BACKUPS"

# ============== CLEANUP OLD RELEASES ==============
log_info "Cleaning old releases (keeping latest $KEEP_RELEASES)..."
cleanup_directory "$RELEASES" "" "$KEEP_RELEASES" "releases" "$DRY_RUN"

# ============== CLEANUP OLD FILE BACKUPS ==============
log_info "Cleaning old file backups (keeping latest $KEEP_BACKUPS)..."
cleanup_directory "$BACKUPS" "backup_*.tar.gz" "$KEEP_BACKUPS" "files" "$DRY_RUN"

# ============== CLEANUP OLD LOGS ==============
log_info "Cleaning log files older than ${KEEP_LOGS_DAYS} days..."
if [[ -d "$LOGS" ]]; then
    if $DRY_RUN; then
        log_count=$(find "$LOGS" -name '*.log' -type f -mtime +"$KEEP_LOGS_DAYS" 2>/dev/null | wc -l)
        log_debug "[DRY-RUN] Would delete $log_count log file(s) older than ${KEEP_LOGS_DAYS} days"
    else
        # BUG FIX: The original piped `find -delete` into `wc -l` to count
        # deleted files, but `-delete` produces no output so the count was
        # always 0. Count first, then delete.
        deleted=$(find "$LOGS" -name '*.log' -type f -mtime +"$KEEP_LOGS_DAYS" 2>/dev/null | wc -l)
        find "$LOGS" -name '*.log' -type f -mtime +"$KEEP_LOGS_DAYS" -delete 2>/dev/null || true
        log_info "✅ Deleted $deleted log file(s) older than ${KEEP_LOGS_DAYS} days"
    fi
else
    log_warn "Logs directory not found: $LOGS"
fi

# ============== CLEANUP OLD DATABASE BACKUPS ==============
log_info "Cleaning old database backups (keeping latest $KEEP_DB_BACKUPS)..."
cleanup_directory "$DB_BACKUPS" "database_backup_*.sql.gz" "$KEEP_DB_BACKUPS" "files" "$DRY_RUN"

# ============== CLEANUP TWIG CACHE ==============
log_info "Cleaning Twig template cache..."
TWIG_CACHE="$BASE/app/shared/storage/cache/twig"
if [[ -d "$TWIG_CACHE" ]]; then
    if $DRY_RUN; then
        CACHE_SIZE=$(du -sh "$TWIG_CACHE" 2>/dev/null | awk '{print $1}')
        log_debug "[DRY-RUN] Would clear Twig cache ($CACHE_SIZE)"
    else
        rm -rf "$TWIG_CACHE"
        mkdir -p "$TWIG_CACHE"
        log_info "✅ Twig cache cleared"
    fi
else
    log_debug "Twig cache directory not found: $TWIG_CACHE (nothing to clear)"
fi

# ============== CLEANUP APPLICATION CACHE ==============
log_info "Cleaning application cache files older than 7 days..."
CACHE_DIR="$BASE/app/shared/storage/cache"
if [[ -d "$CACHE_DIR" ]]; then
    if $DRY_RUN; then
        # BUG FIX: Original used `-type f ! -name "twig"` which never matches
        # because "twig" is a directory name, not a file name. The intent is to
        # exclude files under the twig/ subdirectory. Use `! -path "*/twig/*"`.
        CACHE_FILES=$(find "$CACHE_DIR" -type f ! -path "*/twig/*" -mtime +7 2>/dev/null | wc -l)
        log_debug "[DRY-RUN] Would delete $CACHE_FILES cache file(s) older than 7 days"
    else
        find "$CACHE_DIR" -type f ! -path "*/twig/*" -mtime +7 -delete 2>/dev/null || true
        log_info "✅ Application cache cleaned"
    fi
fi

# ============== DISK USAGE SUMMARY ==============
log_info "Current disk usage summary:"
{
    echo "  Releases:       $(du -sh "$BASE/app/releases/" 2>/dev/null | awk '{print $1}' || echo 'N/A')"
    echo "  Backups:        $(du -sh "$BASE/backups/"      2>/dev/null | awk '{print $1}' || echo 'N/A')"
    echo "  Shared Storage: $(du -sh "$BASE/app/shared/"   2>/dev/null | awk '{print $1}' || echo 'N/A')"
} | tee -a "$CLEANUP_LOG"

log_info "✅ Cleanup completed successfully"
exit 0
