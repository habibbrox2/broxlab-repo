#!/bin/bash

# BroxLab Rollback Script
# Safely switches current symlink to previous release with validation
# Includes safety backups and optional database restore
# Usage: ./rollback.sh [--skip-db-restore] [--no-backup]
# Exit codes: 0=success, 1=error, 3=user aborted

set -euo pipefail

# ============== CONFIGURATION ==============
BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
APP="$BASE/app"
RELEASES="$APP/releases"
CURRENT="$APP/current"
SHARED="$APP/shared"
VERSION_FILE="$SHARED/version.json"
LOGS="$BASE/logs"

SKIP_DB_RESTORE=false
SKIP_BACKUP=false

# ============== ARGUMENT PARSING ==============
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-db-restore) SKIP_DB_RESTORE=true; shift ;;
        --no-backup) SKIP_BACKUP=true; shift ;;
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
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/rollback.log"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/rollback.log"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/rollback.log"
}

log_debug() {
    echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/rollback.log"
}

# ============== INITIALIZATION ==============
mkdir -p "$LOGS"

log_info "Starting rollback process..."
if $SKIP_DB_RESTORE; then
    log_info "Database restore will be skipped"
fi
if $SKIP_BACKUP; then
    log_info "Pre-rollback backup will be skipped"
fi

# ============== VALIDATION ==============
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

log_info "Current release: $(basename "$CURRENT_RELEASE")"
log_debug "Current release path: $CURRENT_RELEASE"

# ============== FIND PREVIOUS RELEASE ==============
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
log_debug "Previous release path: $RELEASES/$PREVIOUS"

# Validate previous release exists and is healthy
if [[ ! -d "$RELEASES/$PREVIOUS" ]]; then
    log_error "Previous release directory not found: $RELEASES/$PREVIOUS"
    exit 1
fi

if [[ ! -d "$RELEASES/$PREVIOUS/public_html" ]]; then
    log_error "Previous release appears corrupted (missing public_html)"
    exit 1
fi

log_debug "Previous release validation passed"

# ============== CONFIRMATION PROMPT ==============
echo ""
log_warn "ROLLBACK CONFIRMATION REQUIRED"
log_warn "==============================="
log_warn "Current:  $(basename "$CURRENT_RELEASE")"
log_warn "Rollback: $PREVIOUS"
log_warn ""
log_warn "This action will:"
log_warn "  1. Switch to previous release code"
if [[ "$SKIP_BACKUP" == "false" ]]; then
    log_warn "  2. Create safety backup of current release"
fi
if [[ "$SKIP_DB_RESTORE" == "false" ]]; then
    log_warn "  3. Allow optional database restore"
fi
echo ""
read -p "Type 'ROLLBACK' to confirm: " CONFIRM_TEXT

if [[ "$CONFIRM_TEXT" != "ROLLBACK" ]]; then
    log_info "Rollback cancelled by user"
    exit 3
fi

# ============== CREATE SAFETY BACKUP ==============
if [[ "$SKIP_BACKUP" == "false" ]]; then
    log_info "Creating safety backup of current release..."
    BACKUP_FILE="$BASE/backups/rollback_backup_$(date +%Y%m%d_%H%M%S).tar.gz"
    mkdir -p "$BASE/backups"
    
    if tar --exclude='node_modules' --exclude='vendor' --exclude='.git' -czf "$BACKUP_FILE" \
            -C "$RELEASES" "$(basename "$CURRENT_RELEASE")" 2>/dev/null; then
        BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
        log_info "✅ Safety backup created: $BACKUP_FILE ($BACKUP_SIZE)"
    else
        log_warn "⚠️  Could not create safety backup, continuing with rollback anyway"
    fi
else
    log_info "Skipping safety backup (--no-backup specified)"
fi

# ============== PERFORM ROLLBACK ==============
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

# ============== UPDATE VERSION FILE ==============
if [[ -f "$VERSION_FILE" ]] && command -v jq &> /dev/null; then
    TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")
    if jq --arg msg "Rolled back from $(basename "$CURRENT_RELEASE") to $PREVIOUS at $TIMESTAMP" \
            '.last_action = $msg' "$VERSION_FILE" > "$VERSION_FILE.tmp" 2>/dev/null; then
        mv "$VERSION_FILE.tmp" "$VERSION_FILE"
        log_debug "Version file updated with rollback info"
    else
        log_warn "Could not update version.json (not critical)"
    fi
fi

log_info ""
log_info "╔════════════════════════════════════════════╗"
log_info "║        ✅ CODE ROLLBACK COMPLETED          ║"
log_info "╠════════════════════════════════════════════╣"
log_info "║ From: $(basename "$CURRENT_RELEASE")"
log_info "║ To:   $PREVIOUS"
log_info "║ Time: $TIMESTAMP"
log_info "╚════════════════════════════════════════════╝"
log_info ""

# ============== OPTIONAL DATABASE RESTORE ==============
if [[ "$SKIP_DB_RESTORE" == "false" ]]; then
    echo ""
    log_warn "⚠️  DATABASE RESTORE (Optional)"
    log_info ""
    log_info "The code has been rolled back, but the database remains unchanged."
    read -p "Do you want to restore database from backup? (yes/no): " RESTORE_DB
    
    if [[ "$RESTORE_DB" == "yes" ]]; then
        log_info "Starting database restore..."
        DB_RESTORE_SCRIPT="$BASE/scripts/database-restore.sh"
        
        if [[ -x "$DB_RESTORE_SCRIPT" ]]; then
            # Run database restore (this will prompt user again for confirmation)
            if $DB_RESTORE_SCRIPT; then
                log_info "✅ Database restore completed successfully"
            else
                log_warn "Database restore failed or was skipped"
            fi
        else
            log_error "Database restore script not found: $DB_RESTORE_SCRIPT"
        fi
    else
        log_info "Database restore skipped"
    fi
else
    log_info "Database restore skipped (--skip-db-restore specified)"
fi

echo ""
log_info "✅ Complete rollback finished"
log_warn "⚠️  Verify the application is working correctly:"
log_info "  - Check critical features are functioning"
log_info "  - Monitor application logs: $SHARED/logs/"
log_info "  - Visit the website and test key workflows"
log_info ""
log_info "If further rollback is needed:"
log_info "  $0"
log_info ""

exit 0
