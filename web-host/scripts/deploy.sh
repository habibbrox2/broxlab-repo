#!/bin/bash

set -e

BASE="/home/tdhuedhn/broxlab"
APP="$BASE/app"
RELEASES="$APP/releases"
SHARED="$APP/shared"
CURRENT="$APP/current"

REPO="git@github.com:habibbrox2/broxlab-repo.git"

DATE=$(date +"%Y%m%d_%H%M%S")
NEW_RELEASE="$RELEASES/$DATE"

# ============= CLEAN OLD RELEASES (Keep latest 3) =============
echo "Cleaning old releases (keeping latest 3)..."
cd "$RELEASES" 2>/dev/null || true
ls -t 2>/dev/null | tail -n +4 | xargs -r rm -rf 2>/dev/null || true
echo "✅ Old releases cleaned"

# ============= DISK SPACE CHECK =============
check_disk_space() {
    local required_gb=8
    local available=$(df "$SHARED" 2>/dev/null | tail -1 | awk '{print $4}')
    local available_gb=$((available / 1024 / 1024))
    
    if [ "$available_gb" -lt "$required_gb" ]; then
        echo "⚠️  WARNING: Low disk space (${available_gb}GB available, ${required_gb}GB required)"
        echo "Attempting cleanup..."
        rm -rf ~/.cache/pip/* 2>/dev/null || true
        rm -rf ~/.npm/* 2>/dev/null || true
        
        available=$(df "$SHARED" 2>/dev/null | tail -1 | awk '{print $4}')
        available_gb=$((available / 1024 / 1024))
        
        if [ "$available_gb" -lt "$required_gb" ]; then
            echo "⚠️  WARNING: Still low disk space (${available_gb}GB < ${required_gb}GB). Continuing..."
        else
            echo "✅ Disk space OK after cleanup (${available_gb}GB available)"
        fi
    else
        echo "✅ Disk space OK (${available_gb}GB available)"
    fi
}

echo "Starting deployment..."
check_disk_space

mkdir -p $NEW_RELEASE

git clone --depth=1 $REPO $NEW_RELEASE

cd $NEW_RELEASE

echo "Link shared files"

ln -sfn $SHARED/.env .env
ln -sfn $SHARED/storage storage
ln -sfn $SHARED/broxlab-firebase.json Config/broxlab-firebase.json
ln -sfn $SHARED/cache storage/cache
ln -sfn $SHARED/logs storage/logs
ln -sfn $SHARED/tmp storage/tmp
ln -sfn $SHARED/uploads public_html/uploads

echo "Installing dependencies"

php /home/tdhuedhn/broxlab/composer install \
--no-dev \
--optimize-autoloader \
--no-interaction

echo "Installing Node.js dependencies"

export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

nvm install 20
nvm use 20

npm install --legacy-peer-deps

echo "Building assets"

npm run build

echo "Setting up Python environment for RAG system"

# Check if Python 3.8+ is available
if command -v python3 &> /dev/null; then
    PYTHON_CMD="python3"
elif command -v python &> /dev/null; then
    PYTHON_CMD="python"
else
    echo "⚠️  WARNING: Python not found. Skipping RAG system setup"
    PYTHON_CMD=""
fi

if [ -n "$PYTHON_CMD" ]; then
    # Create virtual environment for RAG system
    if [ ! -d "$SHARED/rag_env" ]; then
        echo "Creating Python virtual environment..."
        $PYTHON_CMD -m venv $SHARED/rag_env 2>/dev/null || {
            echo "⚠️  WARNING: Failed to create virtual environment"
            PYTHON_CMD=""
        }
    fi
    
    if [ -n "$PYTHON_CMD" ]; then
        # Activate virtual environment and install dependencies (non-blocking)
        echo "Installing Python dependencies (may take time)..."
        source $SHARED/rag_env/bin/activate 2>/dev/null || true
        
        pip install --upgrade pip 2>/dev/null || true
        pip install -r rag_system/requirements.txt 2>/dev/null || {
            echo "⚠️  WARNING: Python dependencies installation failed"
            echo "   RAG system may not work properly, but deployment continues"
        }
        
        deactivate 2>/dev/null || true
        
        # Create symlink to virtual environment
        ln -sfn $SHARED/rag_env rag_env 2>/dev/null || true
        echo "✅ Python setup completed (with warnings if any)"
    fi
else
    echo "⚠️  Skipping RAG system setup"
fi

echo "Creating version file"

# Auto-increment version and update deployment info
VERSION_FILE="$SHARED/version.json"

# Function to increment semantic version
increment_version() {
    local version=$1
    # Remove 'v' prefix if exists
    version=${version#v}
    # Split version into parts (major.minor.patch)
    local major=$(echo $version | cut -d. -f1)
    local minor=$(echo $version | cut -d. -f2)
    local patch=$(echo $version | cut -d. -f3)
    
    # Increment patch version
    patch=$((patch + 1))
    
    # Return new version with 'v' prefix
    echo "v${major}.${minor}.${patch}"
}

if [ -f "$VERSION_FILE" ]; then
    # Read current version
    CURRENT_VERSION=$(jq -r '.version' "$VERSION_FILE")
    # Auto-increment version
    NEW_VERSION=$(increment_version "$CURRENT_VERSION")
    # Update JSON with new version and deployment timestamp
    jq --arg date "$DATE" --arg version "$NEW_VERSION" '.deployed_at = $date | .version = $version' "$VERSION_FILE" > "$VERSION_FILE.tmp" && mv "$VERSION_FILE.tmp" "$VERSION_FILE"
    echo "✅ Version updated: $CURRENT_VERSION → $NEW_VERSION"
else
    # Create new version.json if it doesn't exist (start with v1.0.0)
    cat > "$VERSION_FILE" << EOF
{
    "version": "v1.0.0",
    "deployed_at": "$DATE",
    "backup": "",
    "migrations": [],
    "history": []
}
EOF
    echo "✅ Initial version.json created with v1.0.0"
fi

# Also create simple version.txt for backward compatibility
echo "$DATE" > version.txt
cp version.txt $SHARED/version.txt

# ============= PRE-SWITCH BACKUP =============
echo ""
echo "Creating pre-deployment backup..."
BACKUP_SCRIPT="$BASE/scripts/backup.sh"
if [[ -x "$BACKUP_SCRIPT" ]]; then
    if $BACKUP_SCRIPT 2>/dev/null; then
        echo "✅ Backup completed successfully"
    else
        echo "⚠️  Backup failed, but continuing deployment"
    fi
else
    echo "⚠️  Backup script not found, skipping backup"
fi

# ============= SWITCH SYMLINK =============
echo ""
echo "Switching current symlink to new release..."
ln -sfn $NEW_RELEASE $CURRENT
VERIFY_LINK=$(readlink $CURRENT)
if [[ "$VERIFY_LINK" == "$NEW_RELEASE" ]]; then
    echo "✅ Symlink switched successfully"
else
    echo "❌ Failed to switch symlink!"
    exit 1
fi

# ============= POST-DEPLOYMENT CLEANUP =============
echo ""
echo "Running post-deployment cleanup..."
CLEANUP_SCRIPT="$BASE/scripts/cleanup.sh"
if [[ -x "$CLEANUP_SCRIPT" ]]; then
    if $CLEANUP_SCRIPT 2>/dev/null; then
        echo "✅ Cleanup completed successfully"
    else
        echo "⚠️  Cleanup failed (non-blocking)"
    fi
else
    echo "⚠️  Cleanup script not found, skipping"
fi

# ============= DEPLOYMENT SUMMARY =============
echo ""
echo "╔════════════════════════════════════════════╗"
echo "║   ✅ DEPLOYMENT COMPLETED SUCCESSFULLY     ║"
echo "╠════════════════════════════════════════════╣"
echo "║ Release: $(basename $NEW_RELEASE)"
echo "║ Version: $NEW_VERSION"
echo "║ Timestamp: $DATE"
echo "║ Current: $CURRENT → $NEW_RELEASE"
echo "╚════════════════════════════════════════════╝"
echo ""
echo "📋 Deployment Details:"
echo "  Base Directory: $BASE"
echo "  Release: $(basename $NEW_RELEASE)"
echo "  Shared (persistent): $SHARED"
echo ""
echo "📝 Next Steps:"
echo "  1. Monitor application: Check logs in $SHARED/logs/"
echo "  2. Verify deployment: Visit website and test critical features"
echo "  3. Emergency rollback: $BASE/scripts/rollback.sh"
echo ""
echo "📊 Disk Usage After Deployment:"
echo "  $(du -sh $BASE/app/releases/ | awk '{print "  Releases: " $1}')"
echo "  $(du -sh $BASE/backups/ 2>/dev/null | awk '{print "  Backups: " $1}' || echo '  Backups: N/A')"
echo ""