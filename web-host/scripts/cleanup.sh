#!/bin/bash

# Cleanup Script for BroxBhai Deployment
# Removes old releases, backups, and logs based on retention policy

set -e

BASE="/home/tdhuedhn/broxlab"
RELEASES="$BASE/app/releases"
BACKUPS="$BASE/backups"
LOGS="$BASE/logs"

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Logging functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/cleanup.log"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/cleanup.log"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/cleanup.log"
}

# Ensure logs directory exists
mkdir -p "$LOGS"

log_info "Starting cleanup operations..."

# ============= CLEANUP OLD RELEASES (Keep latest 3) =============
log_info "Cleaning old releases (keeping latest 3)..."
if [[ -d "$RELEASES" ]]; then
    RELEASE_COUNT=$(ls -d $RELEASES/*/ 2>/dev/null | wc -l)
    
    if [[ $RELEASE_COUNT -gt 3 ]]; then
        DELETED=$(ls -dt $RELEASES/*/ 2>/dev/null | tail -n +4 | xargs -r rm -rf 2>/dev/null && echo $((RELEASE_COUNT - 3)) || echo 0)
        log_info "✅ Deleted $DELETED old releases (kept latest 3, had $RELEASE_COUNT total)"
    else
        log_info "Releases count: $RELEASE_COUNT (no cleanup needed)"
    fi
else
    log_error "Releases directory not found: $RELEASES"
fi

# ============= CLEANUP OLD BACKUPS (Keep latest 10) =============
log_info "Cleaning old backups (keeping latest 10)..."
if [[ -d "$BACKUPS" ]]; then
    BACKUP_COUNT=$(ls $BACKUPS/backup_*.tar.gz 2>/dev/null | wc -l || echo 0)
    
    if [[ $BACKUP_COUNT -gt 10 ]]; then
        DELETED=$(ls -t $BACKUPS/backup_*.tar.gz 2>/dev/null | tail -n +11 | xargs -r rm -f 2>/dev/null && echo $((BACKUP_COUNT - 10)) || echo 0)
        log_info "✅ Deleted $DELETED old backups (kept latest 10, had $BACKUP_COUNT total)"
    else
        log_info "Backups count: $BACKUP_COUNT (no cleanup needed)"
    fi
else
    log_warn "Backups directory not found: $BACKUPS"
fi

# ============= CLEANUP OLD LOGS (Keep last 30 days) =============
log_info "Cleaning old logs (keeping last 30 days)..."
if [[ -d "$LOGS" ]]; then
    DELETED=$(find "$LOGS" -name '*.log' -type f -mtime +30 -delete 2>/dev/null | wc -l || echo 0)
    log_info "✅ Deleted $DELETED log files older than 30 days"
else
    log_warn "Logs directory not found: $LOGS"
fi

# ============= SHOW DISK USAGE =============
log_info "Current disk usage:"
echo "  Releases: $(du -sh $BASE/app/releases/ 2>/dev/null || echo 'N/A')" >> "$LOGS/cleanup.log"
echo "  Backups: $(du -sh $BASE/backups/ 2>/dev/null || echo 'N/A')" >> "$LOGS/cleanup.log"
echo "  Shared: $(du -sh $BASE/app/shared/ 2>/dev/null || echo 'N/A')" >> "$LOGS/cleanup.log"

log_info "✅ Cleanup completed successfully"