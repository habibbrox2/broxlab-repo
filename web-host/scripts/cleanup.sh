#!/bin/bash

# BroxLab cleanup script for shared hosting.

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
KEEP_BACKUPS=${KEEP_BACKUPS:-10}
KEEP_LOGS_DAYS=${KEEP_LOGS_DAYS:-30}
KEEP_DB_BACKUPS=${KEEP_DB_BACKUPS:-10}
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

cleanup_directory() {
    local dir="$1"
    local pattern="$2"
    local keep="$3"
    local type="$4"

    [[ -d "$dir" ]] || return 0

    local count=0
    if [[ "$type" == "releases" ]]; then
        count=$(find "$dir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l)
    else
        count=$(find "$dir" -maxdepth 1 -name "$pattern" -type f 2>/dev/null | wc -l)
    fi

    if [[ "$count" -le "$keep" ]]; then
        return 0
    fi

    if $DRY_RUN; then
        log_debug "[DRY-RUN] Would delete $((count - keep)) item(s) from $dir"
        return 0
    fi

    if [[ "$type" == "releases" ]]; then
        find "$dir" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null \
            | sort -rn | tail -n "+$(( keep + 1 ))" | awk '{print $2}' | xargs -r rm -rf || true
    else
        find "$dir" -maxdepth 1 -name "$pattern" -type f -printf '%T@ %p\n' 2>/dev/null \
            | sort -rn | tail -n "+$(( keep + 1 ))" | awk '{print $2}' | xargs -r rm -f || true
    fi
}

if $DRY_RUN; then
    log_info "[DRY-RUN] No files will be modified"
fi

log_info "Starting cleanup"
log_debug "Policy: releases=$KEEP_RELEASES backups=$KEEP_BACKUPS logs=${KEEP_LOGS_DAYS}d db-backups=$KEEP_DB_BACKUPS"

cleanup_directory "$RELEASES" "" "$KEEP_RELEASES" "releases"
cleanup_directory "$CODE_BACKUPS" "backup_*.tar.gz" "$KEEP_BACKUPS" "files"
cleanup_directory "$LEGACY_CODE_BACKUPS" "backup_*.tar.gz" "$KEEP_BACKUPS" "files"
cleanup_directory "$DB_BACKUPS" "database_backup_*.sql.gz" "$KEEP_DB_BACKUPS" "files"

if [[ -d "$LOGS" ]]; then
    if $DRY_RUN; then
        COUNT=$(find "$LOGS" -name '*.log' -type f -mtime +"$KEEP_LOGS_DAYS" 2>/dev/null | wc -l)
        log_debug "[DRY-RUN] Would delete $COUNT log file(s)"
    else
        COUNT=$(find "$LOGS" -name '*.log' -type f -mtime +"$KEEP_LOGS_DAYS" 2>/dev/null | wc -l)
        find "$LOGS" -name '*.log' -type f -mtime +"$KEEP_LOGS_DAYS" -delete 2>/dev/null || true
        log_info "Deleted $COUNT log file(s) older than ${KEEP_LOGS_DAYS} days"
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
