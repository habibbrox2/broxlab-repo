#!/bin/bash

# BroxLab Production Deployment Script
# Full deployment pipeline: Git clone → Dependencies → Build → Deploy → Start services
# Includes automatic backups, validation, and rollback support
# Usage: ./deploy.sh [OPTIONS]
#   --skip-backup       Skip pre-deployment file backup
#   --skip-db-backup    Skip pre-deployment database backup
#   --skip-cleanup      Skip post-deployment cleanup
#   --keep N            Number of releases to keep (default: 5)
#   --base PATH         Override base directory
# Exit codes: 0=success, 1=error, 2=validation error

set -euo pipefail

# ============== CONFIGURATION ==============
BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
APP="$BASE/app"
RELEASES="$APP/releases"
SHARED="$APP/shared"
CURRENT="$APP/current"
GIT_REPO="${GIT_REPO:-git@github.com:habibbrox2/broxlab-repo.git}"
LOGS="$BASE/logs"
STORAGE="$SHARED/storage"

DATE=$(date +"%Y%m%d_%H%M%S")
NEW_RELEASE="$RELEASES/$DATE"
# BUG FIX: DEPLOYMENT_SUCCESS was declared twice (lines 23 and 82). Removed duplicate.
DEPLOYMENT_SUCCESS=false

# Deployment options
SKIP_BACKUP=false
SKIP_DB_BACKUP=false
SKIP_CLEANUP=false
KEEP_RELEASES=5
MIN_DISK_SPACE=2  # 2GB minimum free space
NODE_ENV="production"

# BroxLab-specific validators
NODE_MIN_VERSION="18"

# ============== ARGUMENT PARSING ==============
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-backup)    SKIP_BACKUP=true;         shift ;;
        --skip-db-backup) SKIP_DB_BACKUP=true;      shift ;;
        --skip-cleanup)   SKIP_CLEANUP=true;        shift ;;
        --keep)           KEEP_RELEASES="$2";       shift 2 ;;
        --base)           BASE="$2";                shift 2 ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--skip-backup] [--skip-db-backup] [--skip-cleanup] [--keep N] [--base PATH]"
            exit 1
            ;;
    esac
done

# ============== COLOR CODES ==============
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# ============== LOGGING SETUP ==============
# BUG FIX: Log directory must exist before any log_* call. mkdir moved here,
# before the first logging function is invoked.
mkdir -p "$LOGS" "$RELEASES"
LOG_FILE="$LOGS/deploy_$DATE.log"

log_info()    { echo -e "${GREEN}[INFO]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_warn()    { echo -e "${YELLOW}[WARN]${NC}  $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_error()   { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_debug()   { echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"; }
log_section() {
    echo -e "${CYAN}" | tee -a "$LOG_FILE"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"
    echo -e "  $1${NC}" | tee -a "$LOG_FILE"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}" | tee -a "$LOG_FILE"
}

# ============== ERROR HANDLING ==============
cleanup_on_error() {
    local exit_code=$?
    if [[ "$DEPLOYMENT_SUCCESS" == "false" ]]; then
        log_error "Deployment interrupted (exit code: $exit_code)"
        if [[ -d "$NEW_RELEASE" ]]; then
            log_info "Cleaning up incomplete release: $NEW_RELEASE"
            rm -rf "$NEW_RELEASE" || true
        fi
    fi
}

# BUG FIX: Trap only EXIT — trapping ERR separately alongside EXIT with set -e
# causes the handler to fire twice on errors. EXIT always fires and covers both
# normal and error exits.
trap cleanup_on_error EXIT

# ============== INITIALIZATION ==============
log_section "BROXLAB DEPLOYMENT PIPELINE STARTED"
log_info "Deployment ID: $DATE"
log_info "Release path: $NEW_RELEASE"
log_debug "Options: SKIP_BACKUP=$SKIP_BACKUP | SKIP_DB_BACKUP=$SKIP_DB_BACKUP | KEEP_RELEASES=$KEEP_RELEASES"

# ============== PRE-FLIGHT CHECKS ==============
log_section "PRE-FLIGHT VALIDATION"

# BUG FIX: Original used `bc` for float comparison which may not be installed.
# Replaced with pure-bash integer arithmetic (KB units from df).
AVAILABLE_KB=$(df "$BASE" 2>/dev/null | tail -1 | awk '{print $4}')
REQUIRED_KB=$(( MIN_DISK_SPACE * 1024 * 1024 ))
AVAILABLE_GB=$(( AVAILABLE_KB / 1024 / 1024 ))
log_info "Available disk space: ${AVAILABLE_GB}GB (required: ${MIN_DISK_SPACE}GB)"

if [[ "$AVAILABLE_KB" -lt "$REQUIRED_KB" ]]; then
    log_warn "Insufficient disk space — attempting npm cache cleanup..."
    npm cache clean --force 2>/dev/null || true
    AVAILABLE_KB=$(df "$BASE" 2>/dev/null | tail -1 | awk '{print $4}')
    if [[ "$AVAILABLE_KB" -lt "$REQUIRED_KB" ]]; then
        log_error "❌ Disk space still insufficient after cleanup (${AVAILABLE_GB}GB < ${MIN_DISK_SPACE}GB)"
        exit 2
    fi
    log_info "✅ Sufficient space available after cleanup"
else
    log_info "✅ Disk space check passed"
fi

# Verify Node.js
if ! command -v node &>/dev/null; then
    log_error "❌ Node.js not found in PATH"
    log_info "Install Node.js $NODE_MIN_VERSION+ via NVM: nvm install $NODE_MIN_VERSION && nvm use $NODE_MIN_VERSION"
    exit 2
fi

# BUG FIX: grep -oP is not available on all systems (requires PCRE). Use sed instead.
NODE_VERSION_CHECK=$(node --version 2>/dev/null | sed 's/v//' | cut -d. -f1)
if [[ -n "$NODE_VERSION_CHECK" ]] && [[ "$NODE_VERSION_CHECK" -lt "$NODE_MIN_VERSION" ]]; then
    log_warn "⚠️  Node.js v${NODE_VERSION_CHECK} is below recommended minimum (v${NODE_MIN_VERSION})"
fi
log_info "✅ Node.js: $(node -v)"

# Verify npm
if ! command -v npm &>/dev/null; then
    log_error "❌ npm not found in PATH"
    exit 2
fi
log_info "✅ npm: $(npm -v)"

# ============== DATABASE BACKUP ==============
if [[ "$SKIP_DB_BACKUP" == "false" ]]; then
    log_section "DATABASE BACKUP"
    DB_BACKUP_SCRIPT="$BASE/scripts/database-backup.sh"
    if [[ -x "$DB_BACKUP_SCRIPT" ]]; then
        if "$DB_BACKUP_SCRIPT" 2>&1 | tee -a "$LOG_FILE"; then
            log_info "✅ Database backup completed"
        else
            log_warn "⚠️  Database backup had warnings — deployment continues"
        fi
    else
        log_warn "Database backup script not found or not executable: $DB_BACKUP_SCRIPT"
    fi
else
    log_info "Database backup skipped (--skip-db-backup)"
fi

# ============== PRE-DEPLOYMENT BACKUP ==============
if [[ "$SKIP_BACKUP" == "false" ]] && [[ -L "$CURRENT" ]] && [[ -d "$CURRENT" ]]; then
    log_section "PRE-DEPLOYMENT BACKUP"
    BACKUP_SCRIPT="$BASE/scripts/backup.sh"
    if [[ -x "$BACKUP_SCRIPT" ]]; then
        if "$BACKUP_SCRIPT" 2>&1 | tee -a "$LOG_FILE"; then
            log_info "✅ Backup completed"
        else
            log_warn "⚠️  Backup failed — deployment continues"
        fi
    else
        log_warn "Backup script not found or not executable: $BACKUP_SCRIPT"
    fi
else
    if [[ "$SKIP_BACKUP" == "true" ]]; then
        log_info "Release backup skipped (--skip-backup)"
    else
        log_info "No current release found to back up"
    fi
fi

# ============== INITIALIZE SHARED STORAGE ==============
log_section "INITIALIZING SHARED STORAGE"

log_info "Ensuring shared storage directories exist..."
mkdir -p \
    "$STORAGE/uploads" "$STORAGE/cache" "$STORAGE/logs" \
    "$STORAGE/tmp" "$STORAGE/ocr-temp" "$STORAGE/sessions" \
    "$SHARED/backups/database" "$SHARED/backups/code" "$SHARED/backups/logs"

chmod -R 755 "$STORAGE" "$SHARED/backups"

# Validate .env
log_info "Validating .env configuration..."
if [[ ! -f "$SHARED/.env" ]]; then
    log_error "❌ CRITICAL: .env not found at $SHARED/.env"
    log_error "Upload it to the server before deploying."
    log_error "Required keys: DB_HOST, DB_USER, DB_PASS, DB_NAME, REDIS_HOST, …"
    exit 1
fi
log_info "✅ .env found"

log_info "✅ Shared storage initialized"

# ============== GIT CLONE ==============
log_section "GIT REPOSITORY CLONE"

mkdir -p "$NEW_RELEASE"
log_info "Cloning: $GIT_REPO"

if ! git clone --depth=1 "$GIT_REPO" "$NEW_RELEASE" 2>&1 | tee -a "$LOG_FILE"; then
    log_error "❌ Failed to clone repository"
    rm -rf "$NEW_RELEASE"
    exit 1
fi
log_info "✅ Repository cloned"

# Sync helper scripts from repo
if [[ -d "$NEW_RELEASE/web-host/scripts" ]]; then
    log_debug "Syncing helper scripts from repository..."
    mkdir -p "$BASE/scripts"
    cp -f "$NEW_RELEASE/web-host/scripts"/*.sh "$BASE/scripts/" 2>/dev/null \
        || log_warn "⚠️  Some scripts failed to sync"
    chmod +x "$BASE/scripts"/*.sh 2>/dev/null || true
    log_debug "✅ Helper scripts synchronized"
fi

cd "$NEW_RELEASE"

# ============== LINK SHARED FILES ==============
log_section "LINKING SHARED RESOURCES"

mkdir -p Config storage public_html

# .env symlinks
ln -sfn "$SHARED/.env" .env
ln -sfn "$SHARED/.env" "Config/.env"

# Firebase config (try both known locations)
if [[ -f "$SHARED/Config/broxlab-firebase.json" ]]; then
    ln -sfn "$SHARED/Config/broxlab-firebase.json" "Config/broxlab-firebase.json"
elif [[ -f "$SHARED/broxlab-firebase.json" ]]; then
    ln -sfn "$SHARED/broxlab-firebase.json" "Config/broxlab-firebase.json"
else
    log_warn "broxlab-firebase.json not found in shared — Firebase features may be unavailable"
fi

# Storage symlinks
ln -sfn "$STORAGE/uploads"   "public_html/uploads"
ln -sfn "$STORAGE/cache"     "storage/cache"
ln -sfn "$STORAGE/logs"      "storage/logs"
ln -sfn "$STORAGE/tmp"       "storage/tmp"
ln -sfn "$STORAGE/ocr-temp"  "storage/ocr-temp"
ln -sfn "$STORAGE/sessions"  "storage/sessions"

log_info "✅ Shared resources linked"

# ============== DEPENDENCY INSTALLATION ==============
log_section "INSTALLING DEPENDENCIES"

# --- PHP / Composer ---
log_info "Installing PHP dependencies..."

COMPOSER_CMD=""
if command -v composer &>/dev/null; then
    COMPOSER_CMD="composer"
elif [[ -f "$SHARED/composer" ]]; then
    COMPOSER_CMD="$SHARED/composer"
elif [[ -f "$SHARED/composer.phar" ]]; then
    COMPOSER_CMD="php $SHARED/composer.phar"
elif [[ -f "./composer.phar" ]]; then
    COMPOSER_CMD="php ./composer.phar"
else
    log_warn "⚠️  Composer not found — attempting download..."
    if command -v curl &>/dev/null; then
        mkdir -p "$SHARED"
        curl -sS https://getcomposer.org/installer \
            | php -- --install-dir="$SHARED" --filename=composer 2>&1 | tee -a "$LOG_FILE"
        if [[ -f "$SHARED/composer" ]]; then
            COMPOSER_CMD="$SHARED/composer"
            log_info "✅ Composer downloaded"
        fi
    fi
fi

if [[ -z "$COMPOSER_CMD" ]]; then
    log_error "❌ Composer not available and could not be downloaded"
    exit 1
fi

# BUG FIX: Split COMPOSER_CMD before use — quoting a string with embedded
# spaces as a single variable causes "command not found" when it contains
# `php ./composer.phar`. Use eval or an array.
if ! eval "$COMPOSER_CMD install --no-dev --optimize-autoloader -q" 2>&1 | tee -a "$LOG_FILE"; then
    log_error "❌ Composer install failed"
    exit 1
fi
log_info "✅ PHP dependencies installed"

# --- Node.js ---
log_info "Installing Node.js dependencies (devDependencies included for tsx)..."
# IMPORTANT: tsx lives in devDependencies and is required for TypeScript compilation.
if ! NODE_ENV="" npm ci --include=dev 2>&1 | tee -a "$LOG_FILE"; then
    log_warn "⚠️  npm ci failed — falling back to npm install --legacy-peer-deps..."
    if ! NODE_ENV="" npm install --legacy-peer-deps 2>&1 | tee -a "$LOG_FILE"; then
        log_error "❌ Failed to install Node.js dependencies"
        exit 1
    fi
fi
log_info "✅ Node.js dependencies installed"

# ============== ASSET BUILD ==============
log_section "BUILDING ASSETS"

if [[ -f "package.json" ]]; then
    log_info "Building for $NODE_ENV..."
    if [[ "$NODE_ENV" == "production" ]]; then
        if ! npm run build:prod 2>&1 | tee -a "$LOG_FILE"; then
            log_error "❌ Production build failed"
            exit 1
        fi
    else
        if ! npm run build 2>&1 | tee -a "$LOG_FILE"; then
            log_error "❌ Development build failed"
            exit 1
        fi
    fi

    if [[ -d "public_html/assets/js/dist" ]]; then
        ASSET_COUNT=$(find public_html/assets/js/dist -name "*.js" 2>/dev/null | wc -l || echo 0)
        log_info "✅ Assets built ($ASSET_COUNT JS files)"
    else
        log_warn "⚠️  Asset dist directory not found — continuing"
    fi
fi

# ============== PHP SYNTAX VALIDATION ==============
log_info "Validating PHP syntax..."
if command -v php &>/dev/null; then
    PHP_ERROR_COUNT=0
    while IFS= read -r php_file; do
        if ! php -l "$php_file" >/dev/null 2>&1; then
            log_error "PHP syntax error in: $php_file"
            PHP_ERROR_COUNT=$(( PHP_ERROR_COUNT + 1 ))
        fi
    done < <(find app/ Config/ -name "*.php" -type f 2>/dev/null)

    if [[ $PHP_ERROR_COUNT -gt 0 ]]; then
        log_error "❌ $PHP_ERROR_COUNT PHP file(s) have syntax errors — aborting"
        exit 1
    fi
    log_info "✅ PHP syntax validation passed"
fi

# ============== VERSION MANAGEMENT ==============
log_section "VERSION MANAGEMENT"

VERSION_FILE="$SHARED/version.json"

if [[ -f "$VERSION_FILE" ]] && command -v jq &>/dev/null; then
    CURRENT_VERSION=$(jq -r '.version // "v1.0.0"' "$VERSION_FILE" 2>/dev/null || echo "v1.0.0")
    MAJOR=$(echo "$CURRENT_VERSION" | cut -d. -f1 | sed 's/v//')
    MINOR=$(echo "$CURRENT_VERSION" | cut -d. -f2)
    PATCH=$(echo "$CURRENT_VERSION" | cut -d. -f3)
    NEW_VERSION="v${MAJOR}.${MINOR}.$((PATCH + 1))"
else
    CURRENT_VERSION="v0.0.0"
    NEW_VERSION="v1.0.0"
fi

# BUG FIX: version.json was only written when the file didn't exist. Now always
# written so deployed_at and release_name stay current after every deploy.
cat > "$VERSION_FILE" <<EOF
{
    "version": "$NEW_VERSION",
    "previous_version": "$CURRENT_VERSION",
    "deployed_at": "$DATE",
    "release_name": "$DATE",
    "status": "active"
}
EOF

log_info "Version: $CURRENT_VERSION → $NEW_VERSION"

# ============== SWITCH SYMLINK ==============
log_section "SWITCHING DEPLOYMENT"

log_info "Switching current symlink to new release..."
ln -sfn "$NEW_RELEASE" "$CURRENT"

VERIFY_LINK=$(readlink "$CURRENT")
if [[ "$VERIFY_LINK" != "$NEW_RELEASE" ]]; then
    log_error "❌ Symlink verification failed: $CURRENT → $VERIFY_LINK (expected $NEW_RELEASE)"
    exit 1
fi
log_info "✅ Symlink switched: $CURRENT → $NEW_RELEASE"

# --- public_html symlink ---
PUBLIC_HTML_BASE="$BASE/public_html"
PUBLIC_HTML_TARGET="$CURRENT/public_html"

if [[ ! -d "$PUBLIC_HTML_TARGET" ]]; then
    log_error "❌ public_html not found in release: $PUBLIC_HTML_TARGET"
    exit 1
fi

if [[ -L "$PUBLIC_HTML_BASE" ]]; then
    rm -f "$PUBLIC_HTML_BASE" || { log_error "❌ Cannot remove old public_html symlink"; exit 1; }
elif [[ -d "$PUBLIC_HTML_BASE" ]]; then
    log_warn "Directory exists at $PUBLIC_HTML_BASE — backing up..."
    mv "$PUBLIC_HTML_BASE" "${PUBLIC_HTML_BASE}.backup_$DATE" \
        || { log_error "❌ Failed to back up existing public_html"; exit 1; }
fi

ln -sfn "$PUBLIC_HTML_TARGET" "$PUBLIC_HTML_BASE"
if [[ ! -L "$PUBLIC_HTML_BASE" ]]; then
    log_error "❌ Failed to create public_html symlink"
    exit 1
fi
log_info "✅ public_html symlink: $PUBLIC_HTML_BASE → $(readlink "$PUBLIC_HTML_BASE")"

# --- PHP-FPM reload ---
log_info "Reloading PHP-FPM..."
if command -v systemctl &>/dev/null; then
    # BUG FIX: Original ran both the outer check and the reload in a single
    # compound command without proper error separation, which could silently
    # swallow failures. Split into two clear steps.
    if systemctl is-active --quiet php-fpm 2>/dev/null; then
        systemctl reload php-fpm 2>&1 | tee -a "$LOG_FILE" || \
            log_warn "⚠️  php-fpm reload failed via systemctl"
    else
        log_debug "php-fpm service not active — skipping reload"
    fi
elif command -v php-fpm &>/dev/null; then
    php-fpm -t 2>&1 | tee -a "$LOG_FILE"
    killall -HUP php-fpm 2>/dev/null || true
    log_debug "✅ PHP-FPM signaled via HUP"
else
    log_debug "PHP-FPM not found — skipping reload"
fi

# ============== SERVICE RESTART ==============
log_section "STARTING SERVICES"

# ============== AGGRESSIVE PORT CLEANUP - BATTLE TESTED ==============
# Kill old service processes and wait for port cleanup
# This is more aggressive because ports 3000-3003 MUST be free

log_info "Killing old service processes..."

# Step 1: Kill npm process running nodes:start (parent of all services)
if pgrep -f "npm.*nodes:start" >/dev/null 2>&1; then
    log_info "  • Found npm nodes:start process, killing..."
    pkill -9 -f "npm.*nodes:start" 2>/dev/null || true
fi

# Step 2: Kill service-manager service (can spawn multiple processes)
if pgrep -f "node.*service-manager" >/dev/null 2>&1; then
    log_info "  • Found service-manager processes, killing..."
    pkill -9 -f "node.*service-manager" 2>/dev/null || true
fi

# Step 3: Kill by individual service name
for service in reverse-proxy broxlab-node notification-websocket; do
    if pgrep -f "$service" >/dev/null 2>&1; then
        log_info "  • Found $service process, killing..."
        pkill -9 -f "$service" 2>/dev/null || true
    fi
done

# Step 4: Kill any other node processes listening on our ports
if command -v fuser &>/dev/null; then
    for port in 3000 3001 3002 3003; do
        if fuser ${port}/tcp &>/dev/null 2>&1; then
            log_info "  • Killing process on port $port..."
            fuser -k ${port}/tcp 2>/dev/null || true
        fi
    done
fi

# Step 5: Wait longer for TCP TIME_WAIT to expire and ports to be truly free
log_info "Waiting 5 seconds for ports to be released..."
sleep 5

# Step 6: Verify at least one port is free (as sanity check)
PORT_STATUS="unknown"
if command -v lsof &>/dev/null; then
    PORTS_USED=$(lsof -i :3000-3003 2>/dev/null | wc -l)
    if [[ $PORTS_USED -lt 2 ]]; then
        PORT_STATUS="free"
    else
        PORT_STATUS="in_use"
    fi
elif command -v netstat &>/dev/null; then
    PORTS_USED=$(netstat -tln 2>/dev/null | grep -E ":3000|:3001|:3002|:3003" | wc -l)
    if [[ $PORTS_USED -lt 2 ]]; then
        PORT_STATUS="free"
    else
        PORT_STATUS="in_use"
    fi
fi

if [[ "$PORT_STATUS" == "in_use" ]]; then
    log_warn "⚠️  Some ports still in use, attempting final cleanup..."
    # Last resort: kill ALL node processes
    pkill -9 node 2>/dev/null || true
    log_warn "  Killed all node processes"
    sleep 5
fi

log_info "✅ Port cleanup complete, proceeding with service startup"

log_info "Starting Node services..."
cd "$NEW_RELEASE" || exit 1
nohup npm run nodes:start > "$LOGS/service-manager_$DATE.log" 2>&1 &
SERVICE_MANAGER_PID=$!
log_info "✅ Service manager started (PID: $SERVICE_MANAGER_PID)"

log_info "Waiting 10 seconds for services to start..."
sleep 10

log_info "Verifying services are running..."
MAX_RETRIES=6
RETRY_COUNT=0
SERVICES_FOUND=0

while [[ $RETRY_COUNT -lt $MAX_RETRIES ]]; do
    SERVICES_FOUND=$(ps aux | grep -E "reverse-proxy|broxlab-node|notification-websocket" | grep -v grep | wc -l)
    
    if [[ $SERVICES_FOUND -ge 3 ]]; then
        log_info "✅ All services verified running ($SERVICES_FOUND processes found)"
        ps aux | grep -E "reverse-proxy|broxlab-node|notification-websocket" | grep -v grep | sed 's/^/    /' | tee -a "$LOG_FILE"
        break
    fi
    
    RETRY_COUNT=$(( RETRY_COUNT + 1 ))
    if [[ $RETRY_COUNT -lt $MAX_RETRIES ]]; then
        log_warn "⚠️  Only $SERVICES_FOUND/3 services running — retry $RETRY_COUNT/$MAX_RETRIES"
        sleep 3
    fi
done

if [[ $SERVICES_FOUND -lt 3 ]]; then
    log_error "❌ Services failed to start ($SERVICES_FOUND/3 found after $MAX_RETRIES retries)"
    log_error ""
    log_error "=== Service Manager Log ==="
    tail -150 "$LOGS/service-manager_$DATE.log" | tee -a "$LOG_FILE"
    log_error ""
    log_error "=== Service Error Logs ==="
    for log in "$STORAGE/logs"/*-error.log; do
        [[ -f "$log" ]] && {
            log_error "--- $(basename "$log") ---"
            tail -50 "$log" | tee -a "$LOG_FILE"
        }
    done
    log_error ""
    log_error "Deployment failed - could not start services"
    exit 1
fi

log_info "✅ Services started successfully"

log_info "✅ Services started successfully"
# Mark deployment as successful so cleanup errors won't trigger rollback
DEPLOYMENT_SUCCESS=true

# ============== POST-DEPLOYMENT CLEANUP ==============
if [[ "$SKIP_CLEANUP" == "false" ]]; then
    log_section "POST-DEPLOYMENT CLEANUP"

    # Remove broken symlinks in old releases
    find "$RELEASES" -maxdepth 2 -type l -xtype l 2>/dev/null | while read -r broken; do
        log_debug "Removing broken symlink: $broken"
        rm -f "$broken" 2>/dev/null || true
    done

    CLEANUP_SCRIPT="$BASE/scripts/cleanup.sh"
    if [[ -x "$CLEANUP_SCRIPT" ]]; then
        if "$CLEANUP_SCRIPT" --releases "$KEEP_RELEASES" 2>&1 | tee -a "$LOG_FILE"; then
            log_info "✅ Cleanup completed"
        else
            log_warn "⚠️  Cleanup had errors (non-blocking)"
        fi
    else
        log_warn "Cleanup script not found or not executable: $CLEANUP_SCRIPT"
    fi
else
    log_info "Cleanup skipped (--skip-cleanup)"
fi

# ============== DEPLOYMENT SUMMARY ==============
# BUG FIX: log_section was called twice back-to-back (duplicate section header).
# Removed the first erroneous call.
log_section "DEPLOYMENT COMPLETED SUCCESSFULLY ✅"

echo ""
log_info "╔════════════════════════════════════════════════════════════╗"
log_info "║      ✅ BROXLAB DEPLOYMENT COMPLETED SUCCESSFULLY         ║"
log_info "╠════════════════════════════════════════════════════════════╣"
log_info "║ Release ID:   $DATE"
log_info "║ Version:      $CURRENT_VERSION → $NEW_VERSION"
log_info "║ Environment:  $NODE_ENV"
log_info "╚════════════════════════════════════════════════════════════╝"
echo ""
log_info "📋 Deployment details:"
log_info "  • Base:           $BASE"
log_info "  • Current release: $(readlink "$CURRENT" 2>/dev/null || echo 'N/A')"
log_info "  • Shared storage: $STORAGE"
log_info "  • Log:            $LOG_FILE"
log_info ""
log_info "✅ Finished at $(date '+%Y-%m-%d %H:%M:%S')"
log_info "📝 Next: verify the deployment and monitor logs"

exit 0
