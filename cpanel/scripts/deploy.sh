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

echo "Installing dependencies"

composer install \
--no-dev \
--optimize-autoloader \
--no-interaction

echo "Switching release"

ln -sfn $NEW_RELEASE $CURRENT

echo "Deployment completed"