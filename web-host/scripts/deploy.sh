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

# Node.js is optional on this host: when node/npm are unavailable the deploy
# falls back to PHP-only mode (USE_PHP_ONLY=true, same as the explicit flag)
# and skips the esbuild/Vite asset builds. Non-interactive SSH shells (GitHub
# Actions) often miss the nvm PATH entries, so bootstrap Node before
# validating instead of failing outright.
#
# Only nvm.sh is sourced — profile files (.profile/.bash_profile and
# /etc/profile.d/nvm.sh) may `exit` or return a failing status, which under
# `set -e` kills the deploy silently. Everything else uses PATH scanning.
if [[ "${USE_PHP_ONLY:-false}" != "true" ]] && ! command -v node >/dev/null 2>&1; then
    if [[ -n "${HOME:-}" && -s "$HOME/.nvm/nvm.sh" ]]; then
        # shellcheck disable=SC1091
        source "$HOME/.nvm/nvm.sh" >/dev/null 2>&1 || true
    fi

    if ! command -v node >/dev/null 2>&1; then
        for node_dir in \
            "${HOME:-}/.nvm/versions/node"/*/bin \
            /opt/alt/*/usr/bin \
            /usr/local/nodejs/bin \
            /usr/local/lib/nodejs/bin \
            /opt/nodejs/bin \
            /usr/local/bin \
            /usr/bin; do
            if [[ -x "$node_dir/node" ]]; then
                PATH="$node_dir:$PATH"
                break
            fi
        done
    fi

    if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
        log_warn "node/npm not found in PATH — falling back to PHP-only deployment (USE_PHP_ONLY=true)"
        log_warn "Frontend asset builds (esbuild/vite) will be SKIPPED; install Node or set USE_PHP_ONLY=false to enable them"
        USE_PHP_ONLY=true
    fi
fi

require_command git
require_command php
if [[ "${USE_PHP_ONLY:-false}" != "true" ]]; then
    require_command node
    require_command npm
fi
log_info "All required commands found${USE_PHP_ONLY:+ (PHP-only mode — Node skipped)}"

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
# Phase 8 layout: the Laravel app lives at the repo root (no laravel/ subdir,
# no legacy Config/). The legacy front controller is retired; public_html is
# the shared assets/uploads store only. Rollback = redeploy a pre-Phase-8
# release (USE_LEGACY_DOCROOT was removed with the legacy app).
mkdir -p storage/firebase public_html
# Root .env is the Laravel app's env (APP_KEY, DB, queues, FCM flags...). It is
# provisioned on the server (it is gitignored). Only symlink the shared legacy
# .env when no root .env exists yet, and warn: the shared file predates Laravel
# and may lack APP_KEY — add it before serving traffic.
if [[ ! -f .env && ! -L .env ]]; then
    ln -sfn "$SHARED/.env" .env
    log_warn "Root .env missing — symlinked shared legacy .env; verify APP_KEY and Laravel vars are present"
fi

if [[ -f "$SHARED/Config/broxlab-firebase.json" ]]; then
    ln -sfn "$SHARED/Config/broxlab-firebase.json" "storage/firebase/broxlab-firebase.json"
elif [[ -f "$SHARED/broxlab-firebase.json" ]]; then
    ln -sfn "$SHARED/broxlab-firebase.json" "storage/firebase/broxlab-firebase.json"
fi

ln -sfn "$STORAGE/uploads" "public_html/uploads"
ln -sfn "$STORAGE/cache" "storage/cache"
ln -sfn "$STORAGE/logs" "storage/logs"
ln -sfn "$STORAGE/tmp" "storage/tmp"
ln -sfn "$STORAGE/ocr-temp" "storage/ocr-temp"
ln -sfn "$STORAGE/sessions" "storage/sessions"



log_section "INSTALLING DEPENDENCIES"
# Phase 8: the root composer.json IS the Laravel app's composer.json — one
# install covers everything. The legacy composer.json moved to /old/.
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

# Phase 8: build the Laravel frontend bundle (Alpine + Vite) from the merged
# root package.json.
if [[ "${SKIP_BUILD}" == "false" ]]; then
    if command -v npm >/dev/null 2>&1 && [[ -f "node_modules/.bin/vite" ]]; then
        npm run build:laravel 2>&1 | tee -a "$LOG_FILE" || log_warn "Laravel asset build reported warnings/errors"
    else
        log_warn "vite not found in node_modules; skipping Laravel asset build (npm install first)"
    fi
else
    log_info "SKIP_BUILD=true — skipping Laravel asset build"
fi

log_section "VALIDATING PHP"
if command -v php >/dev/null 2>&1; then
    while IFS= read -r php_file; do
        php -l "$php_file" >/dev/null
    done < <(find app -name "*.php" -type f 2>/dev/null)

    # Phase 8: lint the Laravel framework/config/routes/database PHP too —
    # the app now lives at the repo root.
    while IFS= read -r php_file; do
        php -l "$php_file" >/dev/null
    done < <(find config routes bootstrap database -name "*.php" -type f 2>/dev/null)
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

# Shared uploads must remain publicly reachable after the docroot is switched
# to public/. The Laravel uploads disk still points at public_html/uploads
# (lowest-risk option, unchanged), so the shared storage is reachable from both
# the public_html asset path and the Laravel disk. For public/ to serve
# /uploads/*, deploy creates public/uploads -> $STORAGE/uploads (same shared
# storage target as public_html/uploads). Fail loudly so a broken uploads path
# is visible in deploy logs.
mkdir -p storage public
ln -sfn "$STORAGE/uploads" storage/uploads
log_info "Storage uploads symlinked: storage/uploads -> $STORAGE/uploads"
if [[ -L "public/uploads" || -d "public/uploads" ]]; then
    rm -f public/uploads
fi
ln -sfn "$STORAGE/uploads" public/uploads
log_info "Public uploads symlinked: public/uploads -> $STORAGE/uploads"

# Site assets/uploads live in public_html/ (shared static store, also used by
# the legacy app pre-Phase-8). The docroot public/ serves them through
# relative symlinks so the same files are reachable at their historical URLs
# (/assets/*, /cdn/*, /rtceditor/*, /uploads/*, ...).
for shared_dir in assets cdn rtceditor smart_design_assets ai uploads; do
    ln -sfn "../public_html/$shared_dir" "public/$shared_dir"
done
ln -sfn "../public_html/robots.txt" "public/robots.txt"
ln -sfn "../public_html/firebase-messaging-sw.js" "public/firebase-messaging-sw.js"
log_info "Shared asset symlinks created in public/ (assets, cdn, rtceditor, smart_design_assets, ai, uploads, robots.txt, firebase-messaging-sw.js)"

# Optimize the legacy release size by removing local copy of node_modules if
# they exist in the repo root; the deploy keeps them only when explicitly needed.
if [[ -d "node_modules" && "${KEEP_NODE_MODULES:-false}" != "true" ]]; then
    log_info "Removing repo-root node_modules from release (not required for deploy)"
    rm -rf node_modules
fi

# Web server document root — symlink points to current release.
# Phase 8: the document root is public/ (the Laravel front controller at the
# repo root). The legacy app moved to /old/ and is no longer deployable;
# USE_LEGACY_DOCROOT was removed with it. Rollback = redeploy the previous
# release directory ($BASE/releases/<date>) or git revert the Phase 8 commit.
PUBLIC_HTML_BASE="$BASE/public_html"
PUBLIC_HTML_TARGET="$CURRENT/public"
log_info "Docroot set to public/ (Laravel-only, Phase 8)"

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

# Laravel cache: clear before deploy, then bootstrap/cache from the new release.
# This is the production default; skip only when SKIP_LARAVEL_CACHE is set or
# ./artisan is absent.
#
# NOTE: `php artisan migrate --force` is INTENTIONALLY NOT run here. The
# database schema is frozen (shared with the legacy data model); the Laravel
# scaffold migrations (users/cache/jobs) are never executed against the
# shared DB — the tables already exist. Adding migrations requires an
# explicit review, not a deploy step.
if [[ -f "artisan" ]]; then
    log_section "UPDATING LARAVEL CACHE"
    if [[ "${SKIP_LARAVEL_CACHE:-false}" != "true" ]]; then
        php artisan config:clear 2>&1 | tee -a "$LOG_FILE" || log_warn "artisan config:clear failed"
        php artisan route:clear 2>&1 | tee -a "$LOG_FILE" || log_warn "artisan route:clear failed"
        php artisan view:clear 2>&1 | tee -a "$LOG_FILE" || log_warn "artisan view:clear failed"
        php artisan config:cache 2>&1 | tee -a "$LOG_FILE" || log_warn "artisan config:cache failed"
        php artisan route:cache 2>&1 | tee -a "$LOG_FILE" || log_warn "artisan route:cache failed"
        php artisan view:cache 2>&1 | tee -a "$LOG_FILE" || log_warn "artisan view:cache failed"
    else
        log_info "SKIP_LARAVEL_CACHE=true — clearing Laravel cache only"
        php artisan config:clear 2>&1 | tee -a "$LOG_FILE" || log_warn "artisan config:clear failed"
        php artisan route:clear 2>&1 | tee -a "$LOG_FILE" || log_warn "artisan route:clear failed"
        php artisan view:clear 2>&1 | tee -a "$LOG_FILE" || log_warn "artisan view:clear failed"
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
