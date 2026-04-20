#!/bin/bash

# BroxLab Rollback Script
# Safely switches the current symlink to the previous release with full validation
# Includes pre-rollback safety backup and optional database restore
# Usage: ./rollback.sh [--skip-db-restore] [--no-backup] [--base PATH]
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
        --no-backup)       SKIP_BACKUP=true;     shift ;;
        --base)            BASE="$2";            shift 2 ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--skip-db-restore] [--no-backup] [--base PATH]"
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
# BUG FIX: Log directory must exist before first log_* call.
mkdir -p "$LOGS"
ROLLBACK_LOG="$LOGS/rollback.log"

log_info()  { echo -e "${GREEN}[INFO]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ROLLBACK_LOG"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ROLLBACK_LOG"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ROLLBACK_LOG"; }
log_debug() { echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ROLLBACK_LOG"; }

# ============== INITIALIZATION ==============
log_info "Starting rollback process..."
$SKIP_DB_RESTORE && log_info "Database restore will be skipped (--skip-db-restore)"
$SKIP_BACKUP     && log_info "Pre-rollback backup will be skipped (--no-backup)"

# ============== VALIDATION ==============
if [[ ! -d "$RELEASES" ]]; then
    log_error "Releases directory not found: $RELEASES"
    exit 1
fi

if [[ ! -L "$CURRENT" ]]; then
    log_error "Current symlink not found: $CURRENT"
    exit 1
fi

CURRENT_RELEASE=$(readlink "$CURRENT" 2>/dev/null || true)
if [[ -z "$CURRENT_RELEASE" ]]; then
    log_error "Cannot determine current release (symlink may be broken)"
    exit 1
fi

log_info "Current release: $(basename "$CURRENT_RELEASE")"
log_debug "Current release path: $CURRENT_RELEASE"

# ============== FIND PREVIOUS RELEASE ==============
# BUG FIX: The original used `ls -dt $RELEASES/*/` (unquoted glob) piped
# through sed + xargs to extract a basename. This is fragile:
#   1. Unquoted glob fails with "no matches" when the directory is empty.
#   2. `xargs -I {} basename {}` spawns a subprocess per entry.
#   3. `sed -n '2p'` selects the second line of ls output, which sorts by
#      mtime, but ls mtime ordering is unreliable for directories created
#      rapidly (same-second timestamps).
# Fix: use find with -printf to sort numerically by mtime.
PREVIOUS_PATH=$(find "$RELEASES" -mindepth 1 -maxdepth 1 -type d \
    -printf '%T@ %p\n' 2>/dev/null \
    | sort -rn \
    | awk 'NR==2 {print $2}')

if [[ -z "$PREVIOUS_PATH" ]]; then
    log_error "No previous release found — cannot roll back"
    exit 1
fi

PREVIOUS=$(basename "$PREVIOUS_PATH")

if [[ "$PREVIOUS_PATH" == "$CURRENT_RELEASE" ]]; then
    log_error "Previous and current releases are the same: $PREVIOUS"
    exit 1
fi

log_info "Previous release: $PREVIOUS"
log_debug "Previous release path: $PREVIOUS_PATH"

# Validate previous release directory
if [[ ! -d "$PREVIOUS_PATH" ]]; then
    log_error "Previous release directory not found: $PREVIOUS_PATH"
    exit 1
fi

if [[ ! -d "$PREVIOUS_PATH/public_html" ]]; then
    log_error "Previous release appears corrupted (missing public_html): $PREVIOUS_PATH"
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
log_warn "  1. Switch live code to the previous release"
[[ "$SKIP_BACKUP" == "false" ]]     && log_warn "  2. Create a safety backup of the current release"
[[ "$SKIP_DB_RESTORE" == "false" ]] && log_warn "  3. Optionally restore the database from backup"
echo ""
read -rp "Type 'ROLLBACK' to confirm: " CONFIRM_TEXT

if [[ "$CONFIRM_TEXT" != "ROLLBACK" ]]; then
    log_info "Rollback cancelled by user"
    exit 3
fi

# ============== CREATE SAFETY BACKUP ==============
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

if [[ "$SKIP_BACKUP" == "false" ]]; then
    log_info "Creating safety backup of current release..."
    mkdir -p "$BASE/backups"
    BACKUP_FILE="$BASE/backups/rollback_backup_$TIMESTAMP.tar.gz"

    if tar \
        --exclude='node_modules' \
        --exclude='vendor' \
        --exclude='.git' \
        -czf "$BACKUP_FILE" \
        -C "$RELEASES" "$(basename "$CURRENT_RELEASE")" \
        2>/dev/null
    then
        BACKUP_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
        log_info "✅ Safety backup created: $BACKUP_FILE ($BACKUP_SIZE)"
    else
        log_warn "⚠️  Safety backup failed — continuing with rollback"
        rm -f "$BACKUP_FILE" 2>/dev/null || true
    fi
else
    log_info "Skipping safety backup (--no-backup)"
fi

# ============== PERFORM ROLLBACK ==============
log_info "Switching current symlink to previous release..."
ln -sfn "$PREVIOUS_PATH" "$CURRENT"

NEW_CURRENT=$(readlink "$CURRENT")
if [[ "$NEW_CURRENT" != "$PREVIOUS_PATH" ]]; then
    log_error "❌ Rollback verification failed"
    log_error "Expected: $PREVIOUS_PATH"
    log_error "Got:      $NEW_CURRENT"
    exit 1
fi

log_info "✅ Symlink switched to: $PREVIOUS"

# ============== UPDATE VERSION FILE ==============
if [[ -f "$VERSION_FILE" ]] && command -v jq &>/dev/null; then
    ROLLBACK_MSG="Rolled back from $(basename "$CURRENT_RELEASE") to $PREVIOUS at $TIMESTAMP"
    # BUG FIX: The original wrote to $VERSION_FILE.tmp then moved it, but used
    # `>` redirection into the original while jq still has it open — this can
    # produce an empty file on some systems. The tmp-then-move pattern was
    # correct, but the original also suppressed jq errors silently. Made robust.
    if jq --arg msg "$ROLLBACK_MSG" '.last_action = $msg | .status = "rolled_back"' \
            "$VERSION_FILE" > "$VERSION_FILE.tmp" 2>/dev/null
    then
        mv "$VERSION_FILE.tmp" "$VERSION_FILE"
        log_debug "version.json updated: $ROLLBACK_MSG"
    else
        rm -f "$VERSION_FILE.tmp"
        log_warn "Could not update version.json (non-critical)"
    fi
fi

echo ""
log_info "╔════════════════════════════════════════════╗"
log_info "║      ✅ CODE ROLLBACK COMPLETED            ║"
log_info "╠════════════════════════════════════════════╣"
log_info "║ From: $(basename "$CURRENT_RELEASE")"
log_info "║ To:   $PREVIOUS"
log_info "║ Time: $TIMESTAMP"
log_info "╚════════════════════════════════════════════╝"
echo ""

# ============== OPTIONAL DATABASE RESTORE ==============
if [[ "$SKIP_DB_RESTORE" == "false" ]]; then
    echo ""
    log_warn "⚠️  DATABASE RESTORE (Optional)"
    log_info "The code has been rolled back, but the database is unchanged."
    read -rp "Restore database from backup? (yes/no): " RESTORE_DB

    if [[ "$RESTORE_DB" == "yes" ]]; then
        log_info "Starting database restore..."
        DB_RESTORE_SCRIPT="$BASE/scripts/database-restore.sh"

        if [[ ! -x "$DB_RESTORE_SCRIPT" ]]; then
            log_error "Database restore script not found or not executable: $DB_RESTORE_SCRIPT"
        else
            # BUG FIX: The original called `$DB_RESTORE_SCRIPT` without `bash`
            # or ensuring it is executable. Using an explicit variable is fine,
            # but the -x check above ensures it; kept as-is and added set -e
            # awareness: wrap in if/else to avoid aborting the parent script.
            if "$DB_RESTORE_SCRIPT"; then
                log_info "✅ Database restore completed"
            else
                DB_EXIT=$?
                if [[ $DB_EXIT -eq 3 ]]; then
                    log_info "Database restore cancelled by user"
                else
                    log_warn "⚠️  Database restore failed or was skipped (exit: $DB_EXIT)"
                fi
            fi
        fi
    else
        log_info "Database restore skipped"
    fi
else
    log_info "Database restore skipped (--skip-db-restore)"
fi

echo ""
log_info "✅ Rollback complete"
log_warn "⚠️  Verify the application is working correctly:"
log_info "  • Check critical features"
log_info "  • Monitor logs: $SHARED/logs/"
log_info "  • Test key workflows on the site"
echo ""
log_info "To roll back further, run: $0"
echo ""

exit 0
