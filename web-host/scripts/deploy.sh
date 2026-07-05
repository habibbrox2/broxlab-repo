#!/bin/bash

# BroxLab shared-hosting deployment script - Production Ready
# Deploys new release, symlinks shared resources, switches current release.
# Enhanced with comprehensive validation, locking, and rollback safety.

set -euo pipefail

BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
GIT_REPO="${GIT_REPO:-git@github.com:habibbrox2/broxlab-repo.git}"
NODE_ENV="${NODE_ENV:-production}"
APP="$BASE/app"
RELEASES="$APP/releases"
SHARED="$APP/shared"
CURRENT="$APP/current"
STORAGE="$SHARED/storage"
CODE_BACKUPS="$SHARED/backups/code"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
DEPLOY_LOCK="$SHARED/.deploy.lock"
DEPLOY_TIMEOUT=7200  # 2 hours

DATE=$(date +"%Y%m%d_%H%M%S")
NEW_RELEASE="$RELEASES/$DATE"
DEPLOYMENT_SUCCESS=false
DEPLOYMENT_START_TIME=$(date +%s)

SKIP_BACKUP=false
SKIP_DB_BACKUP=false
SKIP_CLEANUP=false
SKIP_BUILD=false
KEEP_RELEASES=3

while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-backup) SKIP_BACKUP=true; shift ;;
        --skip-db-backup) SKIP_DB_BACKUP=true; shift ;;
        --skip-cleanup) SKIP_CLEANUP=true; shift ;;
        --skip-build) SKIP_BUILD=true; shift ;;
        --keep) KEEP_RELEASES="$2"; shift 2 ;;
        --base) BASE="$2"; shift 2 ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--skip-backup] [--skip-db-backup] [--skip-cleanup] [--skip-build] [--keep N] [--base PATH] [--no-node-start]"
            exit 1
            ;;
    esac
done

APP="$BASE/app"
RELEASES="$APP/releases"
SHARED="$APP/shared"
CURRENT="$APP/current"
STORAGE="$SHARED/storage"
CODE_BACKUPS="$SHARED/backups/code"
DB_BACKUPS="$SHARED/backups/database"
LOGS="$BASE/logs"
NEW_RELEASE="$RELEASES/$DATE"
export BASE_PATH="$BASE"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

mkdir -p "$LOGS" "$RELEASES" "$CODE_BACKUPS" "$DB_BACKUPS"
LOG_FILE="$LOGS/deploy_$DATE.log"

log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_debug() {
    echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_section() {
    echo -e "${CYAN}============================================================${NC}" | tee -a "$LOG_FILE"
    echo -e "${CYAN}$1${NC}" | tee -a "$LOG_FILE"
    echo -e "${CYAN}============================================================${NC}" | tee -a "$LOG_FILE"
}

acquire_deploy_lock() {
    if [[ -f "$DEPLOY_LOCK" ]]; then
        local lock_pid
        local lock_time
        lock_pid=$(cat "$DEPLOY_LOCK" 2>/dev/null | cut -d: -f1 || true)
        lock_time=$(cat "$DEPLOY_LOCK" 2>/dev/null | cut -d: -f2 || echo 0)
        local current_time
        current_time=$(date +%s)

        if [[ -n "$lock_pid" ]] && kill -0 "$lock_pid" 2>/dev/null; then
            local elapsed=$((current_time - lock_time))
            if [[ $elapsed -lt $DEPLOY_TIMEOUT ]]; then
                log_error "Deployment already in progress (PID: $lock_pid, elapsed: ${elapsed}s)"
                return 1
            fi
        fi
    fi
    echo "$$:$(date +%s)" > "$DEPLOY_LOCK"
    return 0
}

release_deploy_lock() {
    rm -f "$DEPLOY_LOCK"
}

deployment_cleanup() {
    local exit_code=$?
    if [[ "$DEPLOYMENT_SUCCESS" != "true" ]]; then
        log_error "Deployment failed (exit code: $exit_code)"
        release_deploy_lock
        rm -rf "$NEW_RELEASE" 2>/dev/null || true
    else
        release_deploy_lock
    fi
    return $exit_code
}

trap deployment_cleanup EXIT

require_command() {
    local name="$1"
    if ! command -v "$name" >/dev/null 2>&1; then
        log_error "$name not found in PATH"
        exit 2
    fi
}

if ! acquire_deploy_lock; then
    exit 1
fi

log_section "BROXLAB DEPLOYMENT STARTED"
log_info "Release: $DATE"
log_info "Target: $NEW_RELEASE"
log_info "Shared storage: $SHARED"
log_info "Node environment: $NODE_ENV"

log_section "PRE-DEPLOYMENT VALIDATION"

AVAILABLE_KB=$(df "$BASE" 2>/dev/null | tail -1 | awk '{print $4}')
REQUIRED_KB=$((2 * 1024 * 1024))
if [[ -z "${AVAILABLE_KB:-}" || "$AVAILABLE_KB" -lt "$REQUIRED_KB" ]]; then
    log_error "Not enough free disk space for deployment (required: 2GB, available: $((AVAILABLE_KB / 1024))MB)"
    exit 2
fi
log_info "Disk space check passed ($((AVAILABLE_KB / 1024 / 1024))GB available)"

require_command git
require_command node
require_command npm
require_command php
log_info "All required commands found"

ensure_env_secret() {
    local key="$1"
    local env_file="$SHARED/.env"
    local value=""

    if [[ ! -f "$env_file" ]]; then
        log_error ".env not found at $env_file"
        exit 1
    fi

    value=$(php -r 'echo bin2hex(random_bytes(32));')
    if grep -q "^${key}=" "$env_file"; then
        if grep -q "^${key}=$" "$env_file"; then
            log_warn "${key} is empty in shared .env; generating a secure value"
            sed -i "s|^${key}=.*|${key}=${value}|" "$env_file"
        fi
    else
        log_warn "${key} is missing in shared .env; generating a secure value"
        printf '%s=%s\n' "$key" "$value" >> "$env_file"
    fi
}

if command -v composer >/dev/null 2>&1 || [[ -f "$SHARED/composer" ]] || [[ -f "$SHARED/composer.phar" ]]; then
    log_debug "Composer available"
else
    log_warn "Composer not found; PHP dependencies will be skipped if vendor is already present"
fi

if [[ ! -f "$SHARED/.env" ]]; then
    log_error ".env not found at $SHARED/.env"
    exit 1
fi

ensure_env_secret "JWT_SECRET"
ensure_env_secret "CSRF_SECRET"
ensure_env_secret "NODE_SERVICE_API_KEY"

mkdir -p \
    "$STORAGE/uploads" \
    "$STORAGE/cache" \
    "$STORAGE/logs" \
    "$STORAGE/tmp" \
    "$STORAGE/ocr-temp" \
    "$STORAGE/sessions" \
    "$SHARED/backups/code" \
    "$SHARED/backups/database"

if [[ "$SKIP_DB_BACKUP" == "false" ]]; then
    DB_BACKUP_SCRIPT="$BASE/scripts/database-backup.sh"
    if [[ -x "$DB_BACKUP_SCRIPT" ]]; then
        BASE_PATH="$BASE" "$DB_BACKUP_SCRIPT" 2>&1 | tee -a "$LOG_FILE" || log_warn "Database backup failed, continuing"
    else
        log_warn "Database backup script not found: $DB_BACKUP_SCRIPT"
    fi
fi

if [[ "$SKIP_BACKUP" == "false" && -L "$CURRENT" ]]; then
    BACKUP_SCRIPT="$BASE/scripts/backup.sh"
    if [[ -x "$BACKUP_SCRIPT" ]]; then
        BASE_PATH="$BASE" "$BACKUP_SCRIPT" 2>&1 | tee -a "$LOG_FILE" || log_warn "Code backup failed, continuing"
    fi
fi

log_section "FETCHING RELEASE"
mkdir -p "$NEW_RELEASE"
if ! git clone --depth=1 "$GIT_REPO" "$NEW_RELEASE" 2>&1 | tee -a "$LOG_FILE"; then
    log_error "Failed to clone repository"
    exit 1
fi

if [[ -d "$NEW_RELEASE/web-host/scripts" ]]; then
    mkdir -p "$BASE/scripts"
    cp -f "$NEW_RELEASE/web-host/scripts"/*.sh "$BASE/scripts/" 2>/dev/null || true
    chmod +x "$BASE/scripts"/*.sh 2>/dev/null || true
fi

cd "$NEW_RELEASE"

log_section "LINKING SHARED RESOURCES"
mkdir -p Config storage public_html
ln -sfn "$SHARED/.env" .env
ln -sfn "$SHARED/.env" "Config/.env"

if [[ -f "$SHARED/Config/broxlab-firebase.json" ]]; then
    ln -sfn "$SHARED/Config/broxlab-firebase.json" "Config/broxlab-firebase.json"
elif [[ -f "$SHARED/broxlab-firebase.json" ]]; then
    ln -sfn "$SHARED/broxlab-firebase.json" "Config/broxlab-firebase.json"
fi

ln -sfn "$STORAGE/uploads" "public_html/uploads"
ln -sfn "$STORAGE/cache" "storage/cache"
ln -sfn "$STORAGE/logs" "storage/logs"
ln -sfn "$STORAGE/tmp" "storage/tmp"
ln -sfn "$STORAGE/ocr-temp" "storage/ocr-temp"
ln -sfn "$STORAGE/sessions" "storage/sessions"

log_section "INSTALLING DEPENDENCIES"
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1 | tee -a "$LOG_FILE"
elif [[ -f "$SHARED/composer" ]]; then
    "$SHARED/composer" install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1 | tee -a "$LOG_FILE"
elif [[ -f "$SHARED/composer.phar" ]]; then
    php "$SHARED/composer.phar" install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1 | tee -a "$LOG_FILE"
else
    log_warn "Composer unavailable; skipping PHP dependency install"
fi

if [[ -f "package.json" && "${USE_PHP_ONLY:-false}" != "true" ]]; then
    npm ci --include=dev 2>&1 | tee -a "$LOG_FILE" || npm install --legacy-peer-deps 2>&1 | tee -a "$LOG_FILE"
fi

# If using PHP-only deployment (USE_PHP_ONLY=true), skip Node build steps
if [[ "$SKIP_BUILD" == "false" && -f "package.json" && "${USE_PHP_ONLY:-false}" != "true" ]]; then
    log_section "BUILDING ASSETS"
    npm run build:prod 2>&1 | tee -a "$LOG_FILE"
else
    if [[ "${USE_PHP_ONLY:-false}" == "true" ]]; then
        log_info "USE_PHP_ONLY=true — skipping Node/npm build steps"
    fi
fi

log_section "VALIDATING PHP"
if command -v php >/dev/null 2>&1; then
    while IFS= read -r php_file; do
        php -l "$php_file" >/dev/null
    done < <(find app Config -name "*.php" -type f 2>/dev/null)
fi

log_section "UPDATING VERSION"
VERSION_FILE="$SHARED/version.json"
CURRENT_VERSION="v0.0.0"
NEW_VERSION="v1.0.0"
if [[ -f "$VERSION_FILE" ]] && command -v jq >/dev/null 2>&1; then
    CURRENT_VERSION=$(jq -r '.version // "v0.0.0"' "$VERSION_FILE" 2>/dev/null || echo "v0.0.0")
    MAJOR=$(echo "$CURRENT_VERSION" | cut -d. -f1 | sed 's/^v//')
    MINOR=$(echo "$CURRENT_VERSION" | cut -d. -f2)
    PATCH=$(echo "$CURRENT_VERSION" | cut -d. -f3)
    NEW_VERSION="v${MAJOR}.${MINOR}.$((PATCH + 1))"
fi

cat > "$VERSION_FILE" <<EOF
{
  "version": "$NEW_VERSION",
  "previous_version": "$CURRENT_VERSION",
  "deployed_at": "$DATE",
  "release_name": "$DATE",
  "status": "active"
}
EOF

log_section "SWITCHING RELEASE"
ln -sfn "$NEW_RELEASE" "$CURRENT"
# Web server document root — symlink points to current release's public_html
PUBLIC_HTML_BASE="$BASE/public_html"
PUBLIC_HTML_TARGET="$CURRENT/public_html"
if [[ -L "$PUBLIC_HTML_BASE" ]]; then
    rm -f "$PUBLIC_HTML_BASE"
elif [[ -d "$PUBLIC_HTML_BASE" ]]; then
    mv "$PUBLIC_HTML_BASE" "${PUBLIC_HTML_BASE}.backup_$DATE"
fi
ln -sfn "$PUBLIC_HTML_TARGET" "$PUBLIC_HTML_BASE"

if [[ "$SKIP_CLEANUP" == "false" ]]; then
    CLEANUP_SCRIPT="$BASE/scripts/cleanup.sh"
    if [[ -x "$CLEANUP_SCRIPT" ]]; then
        BASE_PATH="$BASE" "$CLEANUP_SCRIPT" --releases "$KEEP_RELEASES" 2>&1 | tee -a "$LOG_FILE" || log_warn "Cleanup reported warnings"
    fi
fi

DEPLOYMENT_SUCCESS=true
log_section "DEPLOYMENT COMPLETED"
log_info "Release: $DATE"
log_info "Version: $CURRENT_VERSION -> $NEW_VERSION"
log_info "Current: $(readlink "$CURRENT" 2>/dev/null || echo N/A)"
log_info "Public HTML: $(readlink "$PUBLIC_HTML_BASE" 2>/dev/null || echo N/A)"
log_info "Log: $LOG_FILE"

exit 0
