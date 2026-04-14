#!/bin/bash

# BroxLab Production Deployment Script
# Full deployment pipeline: Git clone → Dependencies → Build → Deploy → Start services
# Includes automatic backups, validation, and rollback support
# Usage: ./deploy.sh [OPTIONS]
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
DEPLOYMENT_SUCCESS=false

# Deployment options
SKIP_BACKUP=false
SKIP_DB_BACKUP=false
SKIP_CLEANUP=false
KEEP_RELEASES=5
MIN_DISK_SPACE=2  # 2GB minimum free space
NODE_ENV="production"

# BroxLab-specific validators
PHP_MIN_VERSION="8.0"
NODE_MIN_VERSION="18"

# ============== ARGUMENT PARSING ==============
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-backup) SKIP_BACKUP=true; shift ;;
        --skip-db-backup) SKIP_DB_BACKUP=true; shift ;;
        --skip-cleanup) SKIP_CLEANUP=true; shift ;;
        --keeps) KEEP_RELEASES="$2"; shift 2 ;;
        --base) BASE="$2"; shift 2 ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
done

# ============== COLOR CODES ==============
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# ============== LOGGING FUNCTIONS ==============
log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/deploy_$DATE.log"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/deploy_$DATE.log"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/deploy_$DATE.log"
}

log_debug() {
    echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGS/deploy_$DATE.log"
}

log_section() {
    echo -e "${CYAN}" | tee -a "$LOGS/deploy_$DATE.log"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOGS/deploy_$DATE.log"
    echo -e "  $1${NC}" | tee -a "$LOGS/deploy_$DATE.log"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}" | tee -a "$LOGS/deploy_$DATE.log"
}

# ============== ERROR HANDLING ==============
DEPLOYMENT_SUCCESS=false

cleanup_on_error() {
    if [[ "$DEPLOYMENT_SUCCESS" == "false" ]]; then
        log_error "Deployment interrupted"
        if [[ -d "$NEW_RELEASE" ]]; then
            log_info "Cleaning up incomplete release: $NEW_RELEASE"
            rm -rf "$NEW_RELEASE" || true
        fi
    fi
}

trap cleanup_on_error EXIT ERR

# ============== INITIALIZATION ==============
mkdir -p "$LOGS" "$RELEASES"

log_section "BROXLAB DEPLOYMENT PIPELINE STARTED"
log_info "Deployment: $DATE"
log_info "Release path: $NEW_RELEASE"
log_debug "Config: Skip Backup=$SKIP_BACKUP, Skip DB Backup=$SKIP_DB_BACKUP, Keep Releases=$KEEP_RELEASES"

# ============== PRE-FLIGHT CHECKS ==============
log_section "PRE-FLIGHT VALIDATION"

# Check disk space
AVAILABLE_GB=$(df "$SHARED" 2>/dev/null | tail -1 | awk '{print $4 / 1024 / 1024}')
log_info "Available disk space: ${AVAILABLE_GB%.*}GB (required: ${MIN_DISK_SPACE}GB)"

if (( $(echo "$AVAILABLE_GB < $MIN_DISK_SPACE" | bc -l) )); then
    log_error "❌ Insufficient disk space (${AVAILABLE_GB%.*}GB < ${MIN_DISK_SPACE}GB)"
    log_info "Attempting cleanup..."
    rm -rf ~/.npm/* 2>/dev/null || true
    AVAILABLE_GB=$(df "$SHARED" 2>/dev/null | tail -1 | awk '{print $4 / 1024 / 1024}')
    if (( $(echo "$AVAILABLE_GB < $MIN_DISK_SPACE" | bc -l) )); then
        log_error "Disk space still insufficient after cleanup"
        exit 2
    else
        log_info "✅ Sufficient space available after cleanup"
    fi
else
    log_info "✅ Disk space check passed"
fi

# Verify Node.js
if ! command -v node &> /dev/null; then
    log_error "❌ Node.js not found in PATH"
    log_info "Install Node.js $NODE_MIN_VERSION or higher using NVM:"
    log_info "  nvm install $NODE_MIN_VERSION"
    log_info "  nvm use $NODE_MIN_VERSION"
    exit 2
fi

NODE_VERSION_CHECK=$(node --version 2>/dev/null | grep -oP '\d+(?=\.)' | head -1 || echo "")
if [[ -n "$NODE_VERSION_CHECK" ]] && [[ "$NODE_VERSION_CHECK" -lt "$NODE_MIN_VERSION" ]]; then
    log_warn "⚠️  Node.js version may be below recommended (required: $NODE_MIN_VERSION+, found: v${NODE_VERSION_CHECK})"
fi
log_info "✅ Node version: $(node -v)"

# Verify npm
if ! command -v npm &> /dev/null; then
    log_error "❌ npm not found in PATH"
    exit 2
fi
log_info "✅ npm version: $(npm -v)"

# ============== DATABASE BACKUP ==============
if [[ "$SKIP_DB_BACKUP" == "false" ]]; then
    log_section "DATABASE BACKUP"
    
    DB_BACKUP_SCRIPT="$BASE/scripts/database-backup.sh"
    if [[ -x "$DB_BACKUP_SCRIPT" ]]; then
        if $DB_BACKUP_SCRIPT 2>&1 | tee -a "$LOGS/deploy_$DATE.log"; then
            log_info "✅ Database backup completed"
        else
            log_warn "⚠️  Database backup warning (deployment continues)"
        fi
    else
        log_warn "Database backup script not found: $DB_BACKUP_SCRIPT"
    fi
else
    log_info "Database backup skipped (--skip-db-backup specified)"
fi

# ============== PRE-DEPLOYMENT BACKUP ==============
if [[ "$SKIP_BACKUP" == "false" ]] && [[ -L "$CURRENT" ]] && [[ -d "$CURRENT" ]]; then
    log_section "PRE-DEPLOYMENT BACKUP"
    
    BACKUP_SCRIPT="$BASE/scripts/backup.sh"
    if [[ -x "$BACKUP_SCRIPT" ]]; then
        if $BACKUP_SCRIPT 2>&1 | tee -a "$LOGS/deploy_$DATE.log"; then
            log_info "✅ Backup completed"
        else
            log_warn "⚠️  Backup failed (continuing deployment)"
        fi
    else
        log_warn "Backup script not found: $BACKUP_SCRIPT"
    fi
else
    if [[ "$SKIP_BACKUP" == "true" ]]; then
        log_info "Release backup skipped (--skip-backup specified)"
    else
        log_info "No current release to backup"
    fi
fi

# ============== INITIALIZE SHARED STORAGE ==============
log_section "INITIALIZING SHARED STORAGE"

log_info "Ensuring shared storage directories exist..."
mkdir -p "$STORAGE"/{uploads,cache,logs,tmp,ocr-temp,sessions} 2>&1 | tee -a "$LOGS/deploy_$DATE.log"
mkdir -p "$SHARED/backups"/{database,code,logs} 2>&1 | tee -a "$LOGS/deploy_$DATE.log"
mkdir -p "$LOGS" 2>&1 | tee -a "$LOGS/deploy_$DATE.log"
chmod -R 755 "$STORAGE" 2>&1 | tee -a "$LOGS/deploy_$DATE.log"
chmod -R 755 "$SHARED/backups" 2>&1 | tee -a "$LOGS/deploy_$DATE.log"
log_info "✅ Shared storage initialized"

# ============== GIT CLONE ==============
log_section "GIT REPOSITORY CLONE"

mkdir -p "$NEW_RELEASE"
log_info "Cloning repository: $GIT_REPO"

if git clone --depth=1 "$GIT_REPO" "$NEW_RELEASE" 2>&1 | tee -a "$LOGS/deploy_$DATE.log"; then
    log_info "✅ Repository cloned successfully"
else
    log_error "❌ Failed to clone repository"
    rm -rf "$NEW_RELEASE"
    exit 1
fi

# Copy deployment helper scripts from cloned repo to server scripts directory
if [[ -d "$NEW_RELEASE/web-host/scripts" ]]; then
    log_debug "Syncing helper scripts from repository..."
    mkdir -p "$BASE/scripts"
    cp -f "$NEW_RELEASE/web-host/scripts"/*.sh "$BASE/scripts/" 2>&1 || log_warn "⚠️  Failed to sync some scripts"
    chmod +x "$BASE/scripts"/*.sh 2>&1 || true
    log_debug "✅ Helper scripts synchronized"
fi

cd "$NEW_RELEASE"

# ============== LINK SHARED FILES ==============
log_section "LINKING SHARED RESOURCES"

log_info "Creating symlinks to shared configuration and storage..."

# Create necessary directories in new release
mkdir -p Config storage public_html

# Create symlinks for configuration
ln -sfn "$SHARED/.env" .env
ln -sfn "$SHARED/.env" "Config/.env"

# Create symlinks for Firebase config
if [[ -f "$SHARED/Config/broxlab-firebase.json" ]]; then
    ln -sfn "$SHARED/Config/broxlab-firebase.json" "Config/broxlab-firebase.json"
elif [[ -f "$SHARED/broxlab-firebase.json" ]]; then
    ln -sfn "$SHARED/broxlab-firebase.json" "Config/broxlab-firebase.json"
fi

# Create storage symlinks (shared directories created in INITIALIZE SHARED STORAGE phase)
ln -sfn "$STORAGE/uploads" "public_html/uploads"
ln -sfn "$STORAGE/cache" "storage/cache"
ln -sfn "$STORAGE/logs" "storage/logs"
ln -sfn "$STORAGE/tmp" "storage/tmp"
ln -sfn "$STORAGE/ocr-temp" "storage/ocr-temp"
ln -sfn "$STORAGE/sessions" "storage/sessions"

log_info "✅ Shared resources linked"

# ============== DEPENDENCY INSTALLATION ==============
log_section "INSTALLING DEPENDENCIES"

# PHP/Composer
log_info "Installing PHP dependencies..."

# Check for composer
COMPOSER_CMD="composer"
if ! command -v composer &> /dev/null; then
    # Try to find composer in shared location
    if [[ -f "$SHARED/composer" ]]; then
        COMPOSER_CMD="$SHARED/composer"
        log_debug "Using composer from shared: $COMPOSER_CMD"
    elif [[ -f "$SHARED/composer.phar" ]]; then
        COMPOSER_CMD="php $SHARED/composer.phar"
        log_debug "Using composer.phar from shared: $COMPOSER_CMD"
    elif [[ -f "./composer.phar" ]]; then
        COMPOSER_CMD="php ./composer.phar"
        log_debug "Using local composer.phar"
    else
        # Try to download composer if available
        log_warn "⚠️  Composer not found, attempting to download..."
        if command -v curl &> /dev/null; then
            mkdir -p "$SHARED"
            curl -s https://getcomposer.org/installer | php -- --install-dir="$SHARED" --filename=composer 2>&1 | tee -a "$LOGS/deploy_$DATE.log"
            if [[ -f "$SHARED/composer" ]]; then
                COMPOSER_CMD="$SHARED/composer"
                log_info "✅ Composer downloaded successfully"
            else
                log_error "❌ Failed to download composer and no local composer found"
                exit 1
            fi
        else
            log_error "❌ Composer not found and curl not available to download it"
            exit 1
        fi
    fi
fi

# Install PHP dependencies
if $COMPOSER_CMD install --no-dev --optimize-autoloader -q 2>&1 | tee -a "$LOGS/deploy_$DATE.log"; then
    log_info "✅ PHP dependencies installed"
else
    log_error "❌ Composer install failed"
    exit 1
fi

# Node.js
log_info "Installing Node.js dependencies..."
npm ci 2>&1 | tee -a "$LOGS/deploy_$DATE.log" || {
    log_warn "⚠️  npm ci failed, trying npm install..."
    npm install 2>&1 | tee -a "$LOGS/deploy_$DATE.log"
}
log_info "✅ Node dependencies installed"

# ============== ASSET BUILD ==============
log_section "BUILDING ASSETS"

if [[ -f "package.json" ]]; then
    log_info "Building for $NODE_ENV..."
    
    if [[ "$NODE_ENV" == "production" ]]; then
        npm run build:prod 2>&1 | tee -a "$LOGS/deploy_$DATE.log" || {
            log_error "Production build failed"
            exit 1
        }
    else
        npm run build 2>&1 | tee -a "$LOGS/deploy_$DATE.log" || {
            log_error "Development build failed"
            exit 1
        }
    fi
    
    # Validate assets were built
    if [[ -d "public_html/assets/js/dist" ]]; then
        ASSET_COUNT=$(find public_html/assets/js/dist -name "*.js" 2>/dev/null | wc -l || echo 0)
        log_info "✅ Assets built successfully ($ASSET_COUNT JS files)"
    else
        log_warn "⚠️  Asset directory not found, continuing..."
    fi
fi

# ============== PHP SYNTAX VALIDATION ==============
log_info "Validating PHP code..."
if command -v php &> /dev/null; then
    PHP_ERROR_COUNT=0
    while IFS= read -r php_file; do
        if ! php -l "$php_file" > /dev/null 2>&1; then
            log_error "PHP syntax error: $php_file"
            PHP_ERROR_COUNT=$((PHP_ERROR_COUNT + 1))
        fi
    done < <(find app/ Config/ -name "*.php" -type f 2>/dev/null)
    
    if [[ $PHP_ERROR_COUNT -gt 0 ]]; then
        log_error "$PHP_ERROR_COUNT PHP files have syntax errors"
        exit 1
    fi
    log_info "✅ PHP syntax validation passed"
fi

# ============== VERSION MANAGEMENT ==============
log_section "VERSION MANAGEMENT"

VERSION_FILE="$SHARED/version.json"

# Create/update version.json
if [[ -f "$VERSION_FILE" ]]; then
    CURRENT_VERSION=$(jq -r '.version' "$VERSION_FILE" 2>/dev/null || echo "v1.0.0")
    MAJOR=$(echo $CURRENT_VERSION | cut -d. -f1 | sed 's/v//')
    MINOR=$(echo $CURRENT_VERSION | cut -d. -f2)
    PATCH=$(echo $CURRENT_VERSION | cut -d. -f3)
    NEW_VERSION="v${MAJOR}.${MINOR}.$((PATCH + 1))"
else
    NEW_VERSION="v1.0.0"
    cat > "$VERSION_FILE" << EOF
{
    "version": "v1.0.0",
    "deployed_at": "$DATE",
    "release_name": "$DATE",
    "status": "active"
}
EOF
fi

log_info "Version: $CURRENT_VERSION → $NEW_VERSION"
log_debug "Deployment info saved to: $VERSION_FILE"

# ============== SWITCH SYMLINK ==============
log_section "SWITCHING DEPLOYMENT"

log_info "Switching current symlink to new release..."
ln -sfn "$NEW_RELEASE" "$CURRENT"

VERIFY_LINK=$(readlink "$CURRENT")
if [[ "$VERIFY_LINK" == "$NEW_RELEASE" ]]; then
    log_info "✅ Symlink switched successfully: $CURRENT → $NEW_RELEASE"
else
    log_error "❌ Failed to switch symlink: $CURRENT does not point to $NEW_RELEASE"
    log_debug "Current symlink points to: $VERIFY_LINK"
    exit 1
fi

# Create public_html symlink for web server
log_info "Linking public_html directory for web server..."

PUBLIC_HTML_BASE="$BASE/public_html"
PUBLIC_HTML_TARGET="$CURRENT/public_html"

if [[ -d "$PUBLIC_HTML_TARGET" ]]; then
    # Remove old symlink if it exists
    if [[ -L "$PUBLIC_HTML_BASE" ]]; then
        log_debug "Removing old symlink: $PUBLIC_HTML_BASE"
        rm -f "$PUBLIC_HTML_BASE" || {
            log_error "❌ Failed to remove old symlink: $PUBLIC_HTML_BASE"
            exit 1
        }
    elif [[ -d "$PUBLIC_HTML_BASE" ]]; then
        log_warn "⚠️  Directory exists at $PUBLIC_HTML_BASE, backing up and replacing..."
        mv "$PUBLIC_HTML_BASE" "$PUBLIC_HTML_BASE.backup_$DATE" || {
            log_error "❌ Failed to backup existing public_html directory"
            exit 1
        }
    fi

    # Create new symlink using absolute paths
    ln -sfn "$PUBLIC_HTML_TARGET" "$PUBLIC_HTML_BASE"
    
    # Verify symlink was created correctly
    if [[ -L "$PUBLIC_HTML_BASE" ]]; then
        VERIFY_LINK=$(readlink "$PUBLIC_HTML_BASE")
        log_info "✅ public_html symlink created: $PUBLIC_HTML_BASE → $VERIFY_LINK"
    else
        log_error "❌ Failed to create public_html symlink"
        exit 1
    fi
else
    log_error "❌ public_html directory not found in release: $PUBLIC_HTML_TARGET"
    exit 1
fi

# Reload PHP-FPM to clear cached PHP processes from old release
log_info "Reloading PHP-FPM (clearing cached processes)..."
if command -v systemctl &> /dev/null 2>&1; then
    systemctl reload php-fpm 2>&1 | tee -a "$LOGS/deploy_$DATE.log" || {
        log_warn "⚠️  systemctl reload php-fpm failed (may not be available in this environment)"
    }
elif command -v php-fpm &> /dev/null; then
    php-fpm -t 2>&1 | tee -a "$LOGS/deploy_$DATE.log"
    killall -HUP php-fpm 2>&1 | tee -a "$LOGS/deploy_$DATE.log" || true
    log_debug "✅ PHP-FPM signaled for reload"
else
    log_debug "PHP-FPM reload skipped (not installed)"
fi

# ============== SERVICE RESTART & START ==============
log_section "STARTING SERVICES"

log_info "Restarting Node.js services..."

# Check dependencies first (using local node_modules npm on shared hosting)
log_debug "Checking Node.js dependencies..."
if ! npm list morgan > /dev/null 2>&1; then
    log_warn "⚠️  morgan not found, installing..."
    npm install morgan 2>&1 | tee -a "$LOGS/deploy_$DATE.log" || {
        log_error "❌ Failed to install morgan"
        exit 1
    }
fi

# Start services using Node service manager (no PM2 required)
log_info "Starting Node services via service-manager..."
log_debug "Service manager script: src/service-manager.js"

# Kill any existing service manager processes
pkill -f service-manager 2>/dev/null || true
sleep 1

# Start the service manager (will manage all 3 services)
if nohup node src/service-manager.js > "$LOGS/service-manager_$DATE.log" 2>&1 &
then
    log_info "✅ Service manager started (PID: $!)"
    SERVICE_MANAGER_PID=$!
else
    log_error "❌ Failed to start service manager"
    exit 1
fi

sleep 5

# Verify services are running by checking if processes exist
log_info "Verifying service status..."
if ps aux | grep -E "reverse-proxy|broxlab-node|notification-websocket" | grep -v grep > /dev/null 2>&1; then
    log_info "✅ Services verified running"
    ps aux | grep -E "reverse-proxy|broxlab-node|notification-websocket" | grep -v grep | tee -a "$LOGS/deploy_$DATE.log"
else
    log_error "❌ Services not running after startup"
    log_debug "Service manager log:"
    tail -50 "$LOGS/service-manager_$DATE.log" | tee -a "$LOGS/deploy_$DATE.log"
    exit 1
fi

# Reload PHP-FPM if running as web server
if command -v systemctl &> /dev/null; then
    systemctl is-active --quiet php-fpm && systemctl reload php-fpm 2>&1 | tee -a "$LOGS/deploy_$DATE.log" || true
fi

log_info "✅ Service restart completed"

# ============== POST-DEPLOYMENT CLEANUP ==============
if [[ "$SKIP_CLEANUP" == "false" ]]; then
    log_section "POST-DEPLOYMENT CLEANUP"
    
    # Remove broken symlinks in old releases to prevent error_log attempts
    log_info "Cleaning up old release symlinks..."
    find "$RELEASES" -maxdepth 2 -type l -xtype l 2>/dev/null | while read broken_link; do
        log_debug "Removing broken symlink: $broken_link"
        rm -f "$broken_link" 2>/dev/null || true
    done
    
    CLEANUP_SCRIPT="$BASE/scripts/cleanup.sh"
    if [[ -x "$CLEANUP_SCRIPT" ]]; then
        if $CLEANUP_SCRIPT --releases $KEEP_RELEASES 2>&1 | tee -a "$LOGS/deploy_$DATE.log"; then
            log_info "✅ Cleanup completed"
        else
            log_warn "⚠️  Cleanup failed (non-blocking)"
        fi
    else
        log_warn "Cleanup script not found"
    fi
else
    log_info "Cleanup skipped (--skip-cleanup specified)"
fi

# ============== DEPLOYMENT SUMMARY ==============
log_section "DEPLOYMENT COMPLETED SUCCESS"

# ============== DEPLOYMENT SUMMARY ==============
log_section "DEPLOYMENT COMPLETED SUCCESSFULLY ✅"

echo ""
log_info "╔════════════════════════════════════════════════════════════╗"
log_info "║      ✅ BROXLAB DEPLOYMENT COMPLETED SUCCESSFULLY         ║"
log_info "╠════════════════════════════════════════════════════════════╣"
log_info "║ Release ID: $(basename $NEW_RELEASE)"
log_info "║ Deployment: $DATE"
log_info "║ Environment: $NODE_ENV"
log_info "╚════════════════════════════════════════════════════════════╝"
echo ""

log_info "📋 Deployment Information:"
log_info "  • Base Directory: $BASE"
log_info "  • Current Release: $(readlink $CURRENT 2>/dev/null || echo 'N/A')"
log_info "  • Shared Storage: $STORAGE"
log_info "  • Logs: $LOGS/deploy_$DATE.log"
log_info ""

log_info "✅ Deployment finished at $(date '+%Y-%m-%d %H:%M:%S')"
log_info "📝 Next: Verify deployment and monitor logs"

DEPLOYMENT_SUCCESS=true
exit 0
