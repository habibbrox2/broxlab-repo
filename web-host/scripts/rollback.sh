#!/bin/bash

# Rollback Script for BroxBhai Deployment
# Safely switches current symlink to previous release with validation and logging

set -e

BASE="/home/tdhuedhn/broxlab"
APP="$BASE/app"
RELEASES="$APP/releases"
CURRENT="$APP/current"
SHARED="$APP/shared"
VERSION_FILE="$SHARED/version.json"
LOGS="$BASE/logs"

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Logging functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/rollback.log"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/rollback.log"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/rollback.log"
}

# Ensure logs directory exists
mkdir -p "$LOGS"

log_info "Starting rollback process..."

# Validate prerequisites
if [[ ! -d "$RELEASES" ]]; then
    log_error "Releases directory not found: $RELEASES"
    exit 1
fi

if [[ ! -L "$CURRENT" ]]; then
    log_error "Current symlink not found: $CURRENT"
    exit 1
fi

# Get current release
CURRENT_RELEASE=$(readlink "$CURRENT" 2>/dev/null || echo "")
if [[ -z "$CURRENT_RELEASE" ]]; then
    log_error "Cannot determine current release"
    exit 1
fi

log_info "Current release: $CURRENT_RELEASE"

# Find previous release (second most recent)
PREVIOUS=$(ls -dt $RELEASES/*/ 2>/dev/null | sed -n '2p' | xargs -I {} basename {})

if [[ -z "$PREVIOUS" ]]; then
    log_error "No previous release found for rollback"
    exit 1
fi

if [[ "$PREVIOUS" == "$(basename "$CURRENT_RELEASE")" ]]; then
    log_error "Current and previous releases are the same"
    exit 1
fi

log_info "Previous release: $PREVIOUS"

# Validate previous release exists and is healthy
if [[ ! -d "$RELEASES/$PREVIOUS" ]]; then
    log_error "Previous release directory not found: $RELEASES/$PREVIOUS"
    exit 1
fi

if [[ ! -d "$RELEASES/$PREVIOUS/public_html" ]]; then
    log_error "Previous release appears corrupted (missing public_html)"
    exit 1
fi

# Create backup of current state before rollback (optional, non-blocking)
log_info "Creating safety backup of current release..."
BACKUP_FILE="$BASE/backups/rollback_backup_$(date +%Y%m%d_%H%M%S).tar.gz"
mkdir -p "$BASE/backups"
if tar --exclude='node_modules' --exclude='vendor' -czf "$BACKUP_FILE" \
        -C "$RELEASES" "$(basename "$CURRENT_RELEASE")" 2>/dev/null; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
    log_info "✅ Safety backup created: $BACKUP_FILE ($BACKUP_SIZE)"
else
    log_warn "⚠️  Could not create safety backup, continuing with rollback anyway"
fi

# Perform rollback
log_info "Switching current symlink to previous release..."
ln -sfn "$RELEASES/$PREVIOUS" "$CURRENT"

# Verify rollback
NEW_CURRENT=$(readlink "$CURRENT")
if [[ "$NEW_CURRENT" != "$RELEASES/$PREVIOUS" ]]; then
    log_error "❌ Rollback verification failed"
    log_error "Expected: $RELEASES/$PREVIOUS"
    log_error "Got: $NEW_CURRENT"
    exit 1
fi

log_info "✅ Successfully rolled back to: $PREVIOUS"

# Update version.json with rollback info (optional, non-blocking)
if [[ -f "$VERSION_FILE" ]] && command -v jq &> /dev/null; then
    TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")
    if jq --arg msg "Rolled back from $(basename "$CURRENT_RELEASE") to $PREVIOUS at $TIMESTAMP" \
            '.last_action = $msg' "$VERSION_FILE" > "$VERSION_FILE.tmp" 2>/dev/null; then
        mv "$VERSION_FILE.tmp" "$VERSION_FILE"
        log_info "Version file updated with rollback info"
    else
        log_warn "Could not update version.json"
    fi
fi

log_info "✅ Rollback completed successfully"
log_info ""
log_info "⚠️  IMPORTANT: Please verify the application is working correctly:"
log_info "  - Check application logs: $SHARED/logs/"
log_info "  - Monitor error reports in real-time"
log_info "  - If issues persist, rollback again: $0"
log_info ""