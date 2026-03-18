#!/bin/bash

# Backup Script for BroxBhai Deployment
# Creates compressed backups of current release with automatic cleanup

set -e

BASE="/home/tdhuedhn/broxlab"
APP="$BASE/app"
BACKUPS="$BASE/backups"
LOGS="$BASE/logs"
CURRENT="$APP/current"

DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUPS/backup_$DATE.tar.gz"
LOG_FILE="$LOGS/backup_$DATE.log"

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Logging functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Ensure directories exist
mkdir -p "$BACKUPS" "$LOGS"

log_info "Starting backup of current release..."

# Validate current release exists
if [[ ! -L "$CURRENT" ]] || [[ ! -d "$CURRENT" ]]; then
    log_error "Current release symlink not found or broken: $CURRENT"
    exit 1
fi

# Get current release path
CURRENT_RELEASE=$(readlink "$CURRENT")
log_info "Current release: $CURRENT_RELEASE"

# Check disk space (need at least 2x current size for backup + compression)
CURRENT_SIZE=$(du -sb "$CURRENT" | awk '{print $1}')
REQUIRED_SPACE=$((CURRENT_SIZE * 2))
AVAILABLE_SPACE=$(df "$BACKUPS" | tail -1 | awk '{print $4 * 1024}')

if [[ $AVAILABLE_SPACE -lt $REQUIRED_SPACE ]]; then
    log_warn "Low disk space. Available: $((AVAILABLE_SPACE / 1024 / 1024))MB, Required: $((REQUIRED_SPACE / 1024 / 1024))MB"
    log_info "Cleaning old backups..."
    ls -t "$BACKUPS"/backup_*.tar.gz 2>/dev/null | tail -n +6 | xargs -r rm -f
fi

# Create backup with exclusions
log_info "Creating backup: $BACKUP_FILE"
if tar --exclude='node_modules' --exclude='vendor' --exclude='storage/cache' \
        --exclude='.git' --exclude='.env' -czf "$BACKUP_FILE" -C "$APP" current 2>>"$LOG_FILE"; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
    log_info "✅ Backup completed successfully: $BACKUP_SIZE"
    
    # Keep only last 10 backups
    log_info "Cleaning old backups (keeping last 10)..."
    ls -t "$BACKUPS"/backup_*.tar.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
    log_info "Old backups cleaned"
else
    log_error "❌ Backup failed"
    exit 1
fi

log_info "✅ Backup script completed successfully"