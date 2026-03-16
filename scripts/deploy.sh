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

echo "Starting deployment..."

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

npm install

echo "Building assets"

npm run build

echo "Creating version file"

# Update version.json with deployment info
VERSION_FILE="$SHARED/version.json"
if [ -f "$VERSION_FILE" ]; then
    # Read current version
    CURRENT_VERSION=$(jq -r '.version' "$VERSION_FILE")
    # Update deployed_at timestamp
    jq --arg date "$DATE" --arg version "$CURRENT_VERSION" '.deployed_at = $date | .version = $version' "$VERSION_FILE" > "$VERSION_FILE.tmp" && mv "$VERSION_FILE.tmp" "$VERSION_FILE"
else
    # Create new version.json if it doesn't exist
    cat > "$VERSION_FILE" << EOF
{
    "version": "v1.0.0",
    "deployed_at": "$DATE",
    "backup": "",
    "migrations": [],
    "history": []
}
EOF
fi

# Also create simple version.txt for backward compatibility
echo "$DATE" > version.txt
cp version.txt $SHARED/version.txt

ln -sfn $NEW_RELEASE $CURRENT

echo "Deployment completed"#