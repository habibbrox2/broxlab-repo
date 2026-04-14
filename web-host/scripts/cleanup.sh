#!/bin/bash

# BroxLab Cleanup & Maintenance Script  
# Removes old releases, backups, logs, and cache based on retention policy
# Includes Twig template cache cleanup
# Usage: ./cleanup.sh [--dry-run] [--releases N] [--backups N] [--logs-days N] [--db-backups N]
# Exit codes: 0=success, 1=error, 2=dry-run

set -euo pipefail

# ============== CONFIGURATION ==============
BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
RELEASES="$BASE/app/releases"
BACKUPS="$BASE/backups"
LOGS="$BASE/logs"
DB_BACKUPS="$BASE/app/shared/backups/database"

# Retention policy defaults
KEEP_RELEASES=${KEEP_RELEASES:-3}
KEEP_BACKUPS=${KEEP_BACKUPS:-10}
KEEP_LOGS_DAYS=${KEEP_LOGS_DAYS:-30}
KEEP_DB_BACKUPS=${KEEP_DB_BACKUPS:-10}
DRY_RUN=false

# ============== ARGUMENT PARSING ==============
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run) DRY_RUN=true; shift ;;
        --releases) KEEP_RELEASES="$2"; shift 2 ;;
        --backups) KEEP_BACKUPS="$2"; shift 2 ;;
        --logs-days) KEEP_LOGS_DAYS="$2"; shift 2 ;;
        --db-backups) KEEP_DB_BACKUPS="$2"; shift 2 ;;
        --base) BASE="$2"; shift 2 ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
done

# ============== COLOR CODES ==============
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============== LOGGING FUNCTIONS ==============
log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/cleanup.log"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/cleanup.log"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/cleanup.log"
}

log_debug() {
    echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/cleanup.log"
}

# ============== UTILITY FUNCTIONS ==============
cleanup_directory() {
    local dir=$1
    local pattern=$2
    local keep=$3
    local type=$4  # "releases" or "files"
    local dry_run=$5
    
    if [[ ! -d "$dir" ]]; then
        log_warn "Directory not found: $dir"
        return
    fi
    
    local count=0
    if [[ "$type" == "releases" ]]; then
        count=$(ls -d $dir/*/ 2>/dev/null | wc -l | tr -d ' ' || echo 0)
    else
        count=$(ls $dir/$pattern 2>/dev/null | wc -l | tr -d ' ' || echo 0)
    fi
    
    if [[ $count -gt $keep ]]; then
        local to_delete=$((count - keep))
        local msg="Deleting $to_delete items (kept $keep, had $count)"
        
        if [[ "$dry_run" == "true" ]]; then
            log_debug "[DRY-RUN] Would delete: $msg"
        else
            if [[ "$type" == "releases" ]]; then
                ls -dt $dir/*/ 2>/dev/null | tail -n +$((keep + 1)) | xargs -r rm -rf 2>/dev/null || true
            else
                ls -t $dir/$pattern 2>/dev/null | tail -n +$((keep + 1)) | xargs -r rm -f 2>/dev/null || true
            fi
            log_info "✅ $msg"
        fi
    else
        log_debug "No cleanup needed ($count items, limit: $keep)"
    fi
}

# ============== INITIALIZATION ==============
mkdir -p "$LOGS"

if $DRY_RUN; then
    log_info "[DRY-RUN MODE] No files will be modified"
fi

log_info "Starting cleanup operations..."
log_debug "Retention policy: Releases=$KEEP_RELEASES, Backups=$KEEP_BACKUPS, Logs=${KEEP_LOGS_DAYS}d, DB-Backups=$KEEP_DB_BACKUPS"

# ============== CLEANUP OLD RELEASES ==============
log_info "Cleaning old releases (keeping latest $KEEP_RELEASES)..."
cleanup_directory "$RELEASES" "" "$KEEP_RELEASES" "releases" "$DRY_RUN"

# ============== CLEANUP OLD BACKUPS ==============
log_info "Cleaning old backups (keeping latest $KEEP_BACKUPS)..."
cleanup_directory "$BACKUPS" "backup_*.tar.gz" "$KEEP_BACKUPS" "files" "$DRY_RUN"

# ============== CLEANUP OLD LOGS ==============
log_info "Cleaning old logs (keeping last ${KEEP_LOGS_DAYS} days)..."
if [[ -d "$LOGS" ]]; then
    if $DRY_RUN; then
        log_count=$(find "$LOGS" -name '*.log' -type f -mtime +"$KEEP_LOGS_DAYS" 2>/dev/null | wc -l || echo 0)
        log_debug "[DRY-RUN] Would delete $log_count log files older than ${KEEP_LOGS_DAYS} days"
    else
        deleted=$(find "$LOGS" -name '*.log' -type f -mtime +"$KEEP_LOGS_DAYS" -delete 2>/dev/null | wc -l || echo 0)
        log_info "✅ Deleted $deleted log files older than ${KEEP_LOGS_DAYS} days"
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
        log_debug "[DRY-RUN] Would delete Twig cache: $CACHE_SIZE"
    else
        rm -rf "$TWIG_CACHE" && mkdir -p "$TWIG_CACHE"
        log_info "✅ Twig cache cleared"
    fi
else
    log_debug "Twig cache directory not found: $TWIG_CACHE"
fi

# ============== CLEANUP APPLICATION CACHE ==============
log_info "Cleaning application cache files..."
CACHE_DIR="$BASE/app/shared/storage/cache"
if [[ -d "$CACHE_DIR" ]]; then
    if $DRY_RUN; then
        CACHE_FILES=$(find "$CACHE_DIR" -type f ! -name "twig" -mtime +7 2>/dev/null | wc -l || echo 0)
        log_debug "[DRY-RUN] Would delete $CACHE_FILES cache files older than 7 days"
    else
        find "$CACHE_DIR" -type f ! -path "*/twig/*" -mtime +7 -delete 2>/dev/null || true
        log_info "✅ Application cache cleaned"
    fi
fi

# ============== DISK USAGE SUMMARY ==============
log_info "Current disk usage summary:"
echo "  Releases: $(du -sh $BASE/app/releases/ 2>/dev/null || echo 'N/A')" | tee -a "$LOGS/cleanup.log"
echo "  Backups: $(du -sh $BASE/backups/ 2>/dev/null || echo 'N/A')" | tee -a "$LOGS/cleanup.log"
echo "  Shared Storage: $(du -sh $BASE/app/shared/ 2>/dev/null || echo 'N/A')" | tee -a "$LOGS/cleanup.log"

log_info "✅ Cleanup completed successfully"
exit 0