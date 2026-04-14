#!/usr/bin/env bash

# ═══════════════════════════════════════════════════════
# BroxBhai cPanel Deployment Setup
# Production Ready Version
# ═══════════════════════════════════════════════════════

set -euo pipefail

echo "🚀 Starting BroxBhai Deployment Setup..."

# -------------------------------------------------------
# Configuration
# -------------------------------------------------------

CPANEL_USER="tdhuedhn"
DEPLOY_DIR="/home/$CPANEL_USER/broxlab"

# SSH repo recommended
REPO_URL="git@github.com:habibbrox2/broxlab-repo.git"

APP_DIR="$DEPLOY_DIR/app"
RELEASES_DIR="$APP_DIR/releases"
SHARED_DIR="$APP_DIR/shared"

SCRIPTS_DIR="$DEPLOY_DIR/scripts"
LOGS_DIR="$DEPLOY_DIR/logs"
BACKUPS_DIR="$DEPLOY_DIR/backups"

CURRENT_LINK="$APP_DIR/current"
PUBLIC_LINK="$DEPLOY_DIR/public_html"

# -------------------------------------------------------
# Colors
# -------------------------------------------------------

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error(){ echo -e "${RED}[ERROR]${NC} $1"; }

# -------------------------------------------------------
# Validate cPanel Environment
# -------------------------------------------------------

if [[ ! -d "/home/$CPANEL_USER" ]]; then
    log_error "cPanel user directory not found!"
    exit 1
fi

log_info "Environment verified."

# -------------------------------------------------------
# Create Base Structure
# -------------------------------------------------------

log_info "Creating directory structure..."

mkdir -p "$RELEASES_DIR"
mkdir -p "$SHARED_DIR/storage"
mkdir -p "$SHARED_DIR/logs"
mkdir -p "$SHARED_DIR/cache"

mkdir -p "$SCRIPTS_DIR"
mkdir -p "$LOGS_DIR"
mkdir -p "$BACKUPS_DIR"

cd "$DEPLOY_DIR"

# -------------------------------------------------------
# Git Setup
# -------------------------------------------------------

if [[ ! -d "$DEPLOY_DIR/.git" ]]; then

    log_info "Cloning repository..."

    git clone "$REPO_URL" repo-temp

    mv repo-temp/* repo-temp/.[!.]* . 2>/dev/null || true

    rm -rf repo-temp

else

    log_info "Repository exists. Pulling latest..."

    git pull origin main

fi

# Git safe directory fix (common cPanel issue)

git config --global --add safe.directory "$DEPLOY_DIR" || true

# -------------------------------------------------------
# Setup Shared ENV
# -------------------------------------------------------

if [[ -f ".env" ]]; then

    log_info "Moving .env to shared directory"

    mv .env "$SHARED_DIR/.env"

else

    log_warn ".env not found. Create: $SHARED_DIR/.env"

fi

# -------------------------------------------------------
# Initial Release Setup
# -------------------------------------------------------

INITIAL_RELEASE="$RELEASES_DIR/initial"

if [[ ! -d "$INITIAL_RELEASE" ]]; then

    log_info "Creating initial release..."

    mkdir -p "$INITIAL_RELEASE"

    rsync -a --exclude=".git" ./ "$INITIAL_RELEASE"

fi

# -------------------------------------------------------
# Current Symlink Fix
# -------------------------------------------------------

log_info "Creating current symlink..."

ln -sfn "$INITIAL_RELEASE" "$CURRENT_LINK"

# -------------------------------------------------------
# Public HTML Symlink
# -------------------------------------------------------

log_info "Linking public_html..."

if [[ -L "$PUBLIC_LINK" ]]; then
    rm "$PUBLIC_LINK"
fi

ln -sfn "$CURRENT_LINK/public_html" "$PUBLIC_LINK"

# -------------------------------------------------------
# .htaccess Safety
# -------------------------------------------------------

if [[ -f "$CURRENT_LINK/public_html/.htaccess.example" ]]; then

    cp "$CURRENT_LINK/public_html/.htaccess.example" \
       "$CURRENT_LINK/public_html/.htaccess"

fi

# -------------------------------------------------------
# Scripts Permission
# -------------------------------------------------------

chmod +x "$SCRIPTS_DIR"/*.sh 2>/dev/null || true

# -------------------------------------------------------
# Status Script
# -------------------------------------------------------

cat > "$SCRIPTS_DIR/status.sh" << 'EOF'
#!/usr/bin/env bash

echo "===== Deployment Status ====="

echo ""
echo "Current Release:"
readlink app/current

echo ""
echo "Available Releases:"
ls -la app/releases/

echo ""
echo "Disk Usage:"
du -sh app logs backups

echo ""
echo "Recent Backups:"
ls -la backups | tail -5
EOF

chmod +x "$SCRIPTS_DIR/status.sh"

# -------------------------------------------------------
# Cleanup Cron Job
# -------------------------------------------------------

log_info "Setting cleanup cron..."

CRON_JOB="0 2 * * * $SCRIPTS_DIR/cleanup.sh"

(crontab -l 2>/dev/null | grep -v cleanup.sh ; echo "$CRON_JOB") | crontab -

# -------------------------------------------------------
# Finish
# -------------------------------------------------------

echo ""
log_info "Setup Completed Successfully!"

echo ""
echo "════════════════════════════════════"
echo "Deployment Directory:"
echo "$DEPLOY_DIR"
echo ""

echo "Structure:"
echo ""
echo "app/"
echo " ├── releases/"
echo " ├── shared/"
echo " └── current -> active release"
echo ""
echo "scripts/"
echo "logs/"
echo "backups/"
echo ""
echo "public_html -> app/current/public_html"
echo ""
echo "Next Steps:"
echo "1. Configure shared env:"
echo "   $SHARED_DIR/.env"
echo ""
echo "2. Test deploy:"
echo "   ./scripts/deploy.sh"
echo ""
echo "3. Check status:"
echo "   ./scripts/status.sh"
echo ""

log_warn "Point your domain document root to:"
echo "$PUBLIC_LINK"