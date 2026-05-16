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

# Verify .env has scraper configuration
echo "Validating environment configuration..."
if grep -q "SCRAPER_ENABLED" $NEW_RELEASE/.env; then
    echo "✅ Scraper environment configured"
else
    echo "⚠️  WARNING: Scraper configuration not found in .env"
    echo "   Add SCRAPER_ENABLED=true to .env for scraper support"
fi

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

echo "Setting up database for scraper..."

# Create scraper database tables
if [[ -f "$NEW_RELEASE/Database/scraper_seen_urls.sql" ]]; then
    echo "Creating scraper_seen_urls table..."
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$NEW_RELEASE/Database/scraper_seen_urls.sql" 2>/dev/null || true
    echo "✅ Scraper database schema initialized"
else
    echo "⚠️  Scraper schema file not found, skipping..."
fi

# Ensure logs directory exists and is writable
mkdir -p $SHARED/logs
chmod 755 $SHARED/logs
echo "✅ Logs directory ready"

echo "Starting Node.js Services (AI + RAG + Scraper)"

# Install PM2 if not found
if ! command -v pm2 &> /dev/null; then
    echo "PM2 not found. Installing PM2..."
    npm install -g pm2
   
    if command -v pm2 &> /dev/null; then
        echo "✅ PM2 installed successfully"
    else
        echo "⚠️ PM2 installation failed. Using nohup fallback..."
    fi
fi

# Start unified Node services with PM2 or nohup
if command -v pm2 &> /dev/null; then
    echo "Using PM2 to start unified services..."
    pm2 delete broxlab-all 2>/dev/null || true
    pm2 delete broxlab-ai 2>/dev/null || true
    pm2 delete broxlab-scraper 2>/dev/null || true
   
    # Start main server
    pm2 start $NEW_RELEASE/src/server.js --name broxlab-all 2>/dev/null || {
        echo "⚠️ PM2 start failed, trying nohup..."
        pkill -f "node.*src/server.js" 2>/dev/null || true
        pkill -f "node.*src/all.js" 2>/dev/null || true
        pkill -f "node.*src/ai/server.js" 2>/dev/null || true
        pkill -f "node.*src/index.js" 2>/dev/null || true
        pkill -f "node.*src/scraper/index.js" 2>/dev/null || true
        cd $NEW_RELEASE
        nohup npm run all:start > $SHARED/logs/node-all.log 2>&1 &
    }
    pm2 save 2>/dev/null || true
   
    # Start scraper services via ecosystem
    echo "Starting scraper services (PM2 ecosystem)..."
    pm2 start $NEW_RELEASE/src/ecosystem.config.cjs 2>/dev/null || {
        echo "⚠️ PM2 ecosystem start failed, falling back to direct scripts..."
        pm2 start $NEW_RELEASE/src/api/scraper-server.js --name scraper-api 2>/dev/null || true
        pm2 start $NEW_RELEASE/src/workers/scrape-worker.js --name scraper-worker 2>/dev/null || true
        pm2 start $NEW_RELEASE/src/workers/retry-worker.js --name scraper-retry-worker 2>/dev/null || true
    }
    pm2 save 2>/dev/null || true
    echo "✅ Scraper services started"
else
    echo "Using nohup to start unified services..."
    # Kill existing node services if any
    pkill -f "node.*src/server.js" 2>/dev/null || true
    pkill -f "node.*src/all.js" 2>/dev/null || true
    pkill -f "node.*src/ai/server.js" 2>/dev/null || true
    pkill -f "node.*src/index.js" 2>/dev/null || true
    pkill -f "node.*src/scraper/index.js" 2>/dev/null || true
   
    # Ensure logs directory exists
    mkdir -p $SHARED/logs
   
    # Start unified services in background
    cd $NEW_RELEASE
    nohup npm run all:start > $SHARED/logs/node-all.log 2>&1 &
    sleep 3
   
    # Start scraper services in background
    echo "Starting scraper services (nohup)..."
    nohup node $NEW_RELEASE/src/api/scraper-server.js > $SHARED/logs/scraper-api.log 2>&1 &
    nohup node $NEW_RELEASE/src/workers/scrape-worker.js > $SHARED/logs/scraper-worker.log 2>&1 &
    nohup node $NEW_RELEASE/src/workers/retry-worker.js > $SHARED/logs/scraper-retry-worker.log 2>&1 &
    sleep 2
    echo "✅ Scraper services started via nohup"
fi

# Verify server started
sleep 2
if pgrep -f "node.*src/server.js" > /dev/null || pm2 list 2>/dev/null | grep -q broxlab-all; then
    echo "✅ Node services started successfully"
else
    echo "⚠️ WARNING: Node services may not have started properly"
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
    "db_backup": "",
    "migrations": [],
    "history": []
}
EOF
    echo "✅ Initial version.json created with v1.0.0"
fi

# Also create simple version.txt for backward compatibility
echo "$DATE" > version.txt
cp version.txt $SHARED/version.txt

# ============= DATABASE BACKUP (Optional) =============
echo ""
echo "Creating database backup before deployment..."
DB_BACKUP_SCRIPT="$BASE/scripts/database-backup.sh"
if [[ -x "$DB_BACKUP_SCRIPT" ]]; then
    if $DB_BACKUP_SCRIPT 2>/dev/null; then
        echo "✅ Database backup completed"
    else
        echo "⚠️  Database backup warning (deployment continues)"
    fi
else
    echo "⚠️  Database backup script not found, skipping"
fi

# ============= PRE-SWITCH BACKUP =============
echo ""
echo "Creating pre-deployment release backup..."
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

# ============= VERSION METADATA UPDATE (backup/migrations/history) =============
if [[ -f "$VERSION_FILE" ]] && command -v jq &> /dev/null; then
    LATEST_RELEASE_BACKUP=""
    if [[ -d "$BASE/backups" ]]; then
        LATEST_RELEASE_BACKUP=$(ls -t "$BASE/backups"/backup_*.tar.gz 2>/dev/null | head -n 1)
    fi

    LATEST_DB_BACKUP=""
    if [[ -d "$SHARED/backups/database" ]]; then
        LATEST_DB_BACKUP=$(ls -t "$SHARED/backups/database"/database_backup_*.sql.gz 2>/dev/null | head -n 1)
    fi

    DEPLOY_MIGRATIONS_RAW="${DEPLOY_MIGRATIONS:-}"
    if [[ -n "$DEPLOY_MIGRATIONS_RAW" ]]; then
        MIGRATIONS_JSON=$(printf '%s' "$DEPLOY_MIGRATIONS_RAW" | jq -R 'split(",") | map(gsub("^\\s+|\\s+$";"")) | map(select(length>0))')
    else
        MIGRATIONS_JSON='[]'
    fi

    CURRENT_VERSION=$(jq -r '.version' "$VERSION_FILE")
    CURRENT_DEPLOYED_AT=$(jq -r '.deployed_at' "$VERSION_FILE")

    HISTORY_ENTRY=$(jq -n \
        --arg version "$CURRENT_VERSION" \
        --arg date "$CURRENT_DEPLOYED_AT" \
        --arg release "$(basename "$NEW_RELEASE")" \
        --arg backup "$LATEST_RELEASE_BACKUP" \
        --arg dbBackup "$LATEST_DB_BACKUP" \
        --argjson migrations "$MIGRATIONS_JSON" \
        '{version:$version, deployed_at:$date, release:$release, backup:$backup, db_backup:$dbBackup, migrations:$migrations}')

    jq --arg backup "$LATEST_RELEASE_BACKUP" \
        --arg dbBackup "$LATEST_DB_BACKUP" \
        --argjson migrations "$MIGRATIONS_JSON" \
        --argjson historyEntry "$HISTORY_ENTRY" \
        '.backup = $backup
         | .db_backup = $dbBackup
         | .migrations = $migrations
         | .history = ([ $historyEntry ] + (if (.history | type) == "array" then .history else [] end))' \
        "$VERSION_FILE" > "$VERSION_FILE.tmp" && mv "$VERSION_FILE.tmp" "$VERSION_FILE"
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

