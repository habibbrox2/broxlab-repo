#!/bin/bash

# cPanel Deployment Setup Script for BroxBhai
# This script sets up the complete deployment framework on cPanel server

set -e

echo "🚀 Setting up BroxBhai cPanel Deployment Framework..."

# Configuration
DEPLOY_DIR="/home/tdhuedhn/broxlab"
REPO_URL="https://github.com/habibbrox2/broxlab-repo.git"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running on cPanel
if [[ ! -d "/home/tdhuedhn" ]]; then
    log_error "This script is designed for cPanel environment with user 'tdhuedhn'"
    log_error "Current user home: $HOME"
    exit 1
fi

# Create deployment directory
log_info "Creating deployment directory: $DEPLOY_DIR"
mkdir -p "$DEPLOY_DIR"
cd "$DEPLOY_DIR"

# Clone repository
if [[ ! -d ".git" ]]; then
    log_info "Cloning repository..."
    git clone "$REPO_URL" .
else
    log_info "Repository already exists, pulling latest changes..."
    git pull origin main
fi

# Create directory structure
log_info "Creating directory structure..."
mkdir -p app/releases
mkdir -p app/shared/storage
mkdir -p app/shared/logs
mkdir -p app/shared/cache
mkdir -p scripts
mkdir -p backups
mkdir -p logs

# Copy shared files
if [[ -f ".env" ]]; then
    log_info "Setting up shared .env file..."
    cp .env app/shared/.env
    log_warn "Please edit app/shared/.env with production values!"
else
    log_warn ".env file not found. Please create and configure app/shared/.env manually."
fi

# Make scripts executable
log_info "Making deployment scripts executable..."
chmod +x scripts/*.sh

# Create initial current symlink (pointing to nothing initially)
if [[ ! -L "app/current" ]]; then
    log_info "Creating initial current symlink..."
    ln -sfn releases app/current
fi

# Setup public_html symlink
log_info "Setting up public_html symlink..."
if [[ -L "public_html" ]]; then
    rm public_html
fi
ln -sfn app/current/public_html public_html

# Create .htaccess for public_html if needed
if [[ ! -f "public_html/.htaccess" ]]; then
    log_info "Creating .htaccess for public_html..."
    cp public_html/.htaccess.example public_html/.htaccess 2>/dev/null || true
fi

# Setup cron job for cleanup (optional)
log_info "Setting up cron job for automatic cleanup..."
CRON_JOB="0 2 * * * $DEPLOY_DIR/scripts/cleanup.sh"
if ! crontab -l | grep -q "$DEPLOY_DIR/scripts/cleanup.sh"; then
    (crontab -l ; echo "$CRON_JOB") | crontab -
    log_info "Added cleanup cron job (runs daily at 2 AM)"
else
    log_info "Cleanup cron job already exists"
fi

# Create status script
cat > scripts/status.sh << 'EOF'
#!/bin/bash
echo "=== BroxBhai Deployment Status ==="
echo "Current Release: $(readlink app/current)"
echo ""
echo "Available Releases:"
ls -la app/releases/
echo ""
echo "Disk Usage:"
du -sh app/ backups/ logs/
echo ""
echo "Recent Backups:"
ls -la backups/ | tail -5
EOF

chmod +x scripts/status.sh

# Final instructions
log_info "✅ Setup completed successfully!"
echo ""
echo "=== Next Steps ==="
echo "1. Configure app/shared/.env with production settings"
echo "2. Test deployment: ./scripts/deploy.sh"
echo "3. Check status: ./scripts/status.sh"
echo "4. Setup GitHub secrets for auto-deploy:"
echo "   - HOST: your cPanel hostname"
echo "   - USER: tdhuedhn"
echo "   - SSH_KEY: deployment SSH key"
echo ""
echo "=== Directory Structure ==="
echo "$DEPLOY_DIR/"
echo "├── app/"
echo "│   ├── releases/          # Versioned releases"
echo "│   ├── shared/            # Shared files (.env, storage, logs, cache)"
echo "│   └── current -> releases/  # Symlink to current release"
echo "├── scripts/               # Deployment scripts"
echo "├── backups/               # Backup archives"
echo "├── logs/                  # Deployment logs"
echo "└── public_html -> app/current/public  # Web root symlink"
echo ""
log_warn "Remember to configure your domain to point to: $DEPLOY_DIR/public_html"