#!/bin/bash

# BroxLab cleanup script for shared hosting - Production Ready
# Safely removes old releases, backups, and logs.
# Enhanced with safety checks and validation.

set -euo pipefail

BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
APP="$BASE/app"
SHARED="$APP/shared"
RELEASES="$APP/releases"
CODE_BACKUPS="$SHARED/backups/code"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
LEGACY_CODE_BACKUPS="$BASE/backups"

KEEP_RELEASES=${KEEP_RELEASES:-3}
KEEP_BACKUPS=${KEEP_BACKUPS:-5}
KEEP_LOGS_DAYS=${KEEP_LOGS_DAYS:-30}
KEEP_DB_BACKUPS=${KEEP_DB_BACKUPS:-5}
DRY_RUN=false

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

APP="$BASE/app"
SHARED="$APP/shared"
RELEASES="$APP/releases"
CODE_BACKUPS="$SHARED/backups/code"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
LEGACY_CODE_BACKUPS="$BASE/backups"
export BASE_PATH="$BASE"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

mkdir -p "$LOGS"
CLEANUP_LOG="$LOGS/cleanup.log"

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$CLEANUP_LOG"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$CLEANUP_LOG"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$CLEANUP_LOG"; }
log_debug() { echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$CLEANUP_LOG"; }

cleanup_old_release_artifacts() {
    local release_dir="$1"
    
    # Aggressively remove large artifacts from old releases
    if [[ -d "$release_dir/node_modules" ]]; then
        local size=$(du -sh "$release_dir/node_modules" 2>/dev/null | awk '{print $1}')
        log_info "Removing node_modules from old release (freed: $size)"
        rm -rf "$release_dir/node_modules" 2>/dev/null || true
    fi
    
    if [[ -d "$release_dir/vendor" ]]; then
        local size=$(du -sh "$release_dir/vendor" 2>/dev/null | awk '{print $1}')
        log_info "Removing vendor directory from old release (freed: $size)"
        rm -rf "$release_dir/vendor" 2>/dev/null || true
    fi
    
    if [[ -d "$release_dir/.git" ]]; then
        rm -rf "$release_dir/.git" 2>/dev/null || true
    fi
}

cleanup_directory() {
    local dir="$1"
    local pattern="$2"
    local keep="$3"
    local type="$4"

    [[ -d "$dir" ]] || { log_debug "Directory not found: $dir"; return 0; }

    local count=0
    if [[ "$type" == "releases" ]]; then
        count=$(find "$dir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l)
    elif [[ "$type" == "dirs" ]]; then
        count=$(find "$dir" -maxdepth 1 -name "$pattern" -type d 2>/dev/null | wc -l)
    else
        count=$(find "$dir" -maxdepth 1 -name "$pattern" -type f 2>/dev/null | wc -l)
    fi

    if [[ "$count" -le "$keep" ]]; then
        log_debug "$dir: $count item(s), keeping $keep"
        return 0
    fi

    local delete_count=$((count - keep))
    if $DRY_RUN; then
        log_debug "[DRY-RUN] Would delete $delete_count item(s) from $dir"
        return 0
    fi

    log_info "Removing $delete_count old item(s) from $dir"
    if [[ "$type" == "releases" ]]; then
        find "$dir" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null \
            | sort -rn | tail -n "+$((keep + 1))" | awk '{print $2}' | while read -r item; do
            log_debug "Removing: $item"
            rm -rf "$item" 2>/dev/null || log_warn "Failed to remove: $item"
        done
    elif [[ "$type" == "dirs" ]]; then
        find "$dir" -maxdepth 1 -name "$pattern" -type d -printf '%T@ %p\n' 2>/dev/null \
            | sort -rn | tail -n "+$((keep + 1))" | awk '{print $2}' | while read -r item; do
            log_debug "Removing: $item"
            rm -rf "$item" 2>/dev/null || log_warn "Failed to remove: $item"
        done
    else
        find "$dir" -maxdepth 1 -name "$pattern" -type f -printf '%T@ %p\n' 2>/dev/null \
            | sort -rn | tail -n "+$((keep + 1))" | awk '{print $2}' | while read -r item; do
            log_debug "Removing: $item"
            rm -f "$item" 2>/dev/null || log_warn "Failed to remove: $item"
        done
    fi
}

if $DRY_RUN; then
    log_info "[DRY-RUN] No files will be modified"
fi

log_info "Starting cleanup"
log_debug "Policy: releases=$KEEP_RELEASES backups=$KEEP_BACKUPS logs=${KEEP_LOGS_DAYS}d db-backups=$KEEP_DB_BACKUPS"

cleanup_directory "$RELEASES" "" "$KEEP_RELEASES" "releases"
# Aggressively clean old release artifacts
if [[ -d "$RELEASES" ]]; then
    find "$RELEASES" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null \
        | sort -rn | tail -n "+$((KEEP_RELEASES + 1))" | awk '{print $2}' | while read -r old_release; do
        cleanup_old_release_artifacts "$old_release"
        log_debug "Cleaned up artifacts from: $old_release"
    done
fi
cleanup_directory "$CODE_BACKUPS" "backup_*" "$KEEP_BACKUPS" "dirs"
cleanup_directory "$LEGACY_CODE_BACKUPS" "backup_*" "$KEEP_BACKUPS" "dirs"
cleanup_directory "$DB_BACKUPS" "database_backup_*.sql.gz" "$KEEP_DB_BACKUPS" "files"

if [[ -d "$LOGS" ]]; then
    if $DRY_RUN; then
        COUNT=$(find "$LOGS" -name '*.log' -type f -mtime +"$KEEP_LOGS_DAYS" 2>/dev/null | wc -l)
        log_debug "[DRY-RUN] Would delete $COUNT log file(s)"
    else
        COUNT=$(find "$LOGS" -name '*.log' -type f -mtime +"$KEEP_LOGS_DAYS" 2>/dev/null | wc -l)
        find "$LOGS" -name '*.log' -type f -mtime +"$KEEP_LOGS_DAYS" -delete 2>/dev/null || true
        log_info "Deleted $COUNT log file(s) older than ${KEEP_LOGS_DAYS} days"
        
        # Also clean up old Node server logs from today's deployments
        find "$LOGS" -name 'node-server_*.log' -type f -mtime +7 -delete 2>/dev/null || true
        find "$LOGS" -name 'service-manager_*.log' -type f -mtime +7 -delete 2>/dev/null || true
    fi
fi

TWIG_CACHE="$SHARED/storage/cache/twig"
if [[ -d "$TWIG_CACHE" && "$DRY_RUN" != "true" ]]; then
    rm -rf "$TWIG_CACHE"
    mkdir -p "$TWIG_CACHE"
fi

CACHE_DIR="$SHARED/storage/cache"
if [[ -d "$CACHE_DIR" && "$DRY_RUN" != "true" ]]; then
    find "$CACHE_DIR" -type f ! -path "*/twig/*" -mtime +7 -delete 2>/dev/null || true
fi

log_info "Cleanup completed successfully"
exit 0
