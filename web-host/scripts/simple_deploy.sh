#!/usr/bin/env bash

# Simple deploy script for BroxLab
# - Uses HTTPS to clone the repository
# - Minimal, defensive steps: clone, link shared, install/build if needed, switch symlink, restart node
# - Intended for single-server deployments; keep it simple and robust.

set -euo pipefail

BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
GIT_REPO="${GIT_REPO:-https://github.com/habibbrox2/broxlab-repo.git}"
REF="${REF:-main}"
KEEP_RELEASES=${KEEP_RELEASES:-3}
START_NODE_SERVER=${START_NODE_SERVER:-true}
NODE_ENV=${NODE_ENV:-production}

usage() {
  cat <<EOF
Usage: $0 [--base PATH] [--repo GIT_HTTPS_URL] [--ref BRANCH_OR_TAG] [--keep N] [--no-start]

Example:
  $0 --repo https://github.com/owner/repo.git --ref main --base /home/user/broxlab
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base) BASE="$2"; shift 2;;
    --repo) GIT_REPO="$2"; shift 2;;
    --ref) REF="$2"; shift 2;;
    --keep) KEEP_RELEASES="$2"; shift 2;;
    --no-start) START_NODE_SERVER=false; shift 1;;
    --help) usage; exit 0;;
    *) echo "Unknown option: $1"; usage; exit 1;;
  esac
done

APP="$BASE/app"
RELEASES="$APP/releases"
SHARED="$APP/shared"
CURRENT="$APP/current"
LOGS="$BASE/logs"
PID_FILE="$SHARED/node-server.pid"
DATE=$(date +"%Y%m%d_%H%M%S")
NEW_RELEASE="$RELEASES/$DATE"

mkdir -p "$RELEASES" "$SHARED" "$LOGS"

log() { echo "[deploy] $(date '+%Y-%m-%d %H:%M:%S') - $1"; }

require() {
  if ! command -v "$1" >/dev/null 2>&1; then
    log "Required command not found: $1"
    exit 2
  fi
}

require git
require rsync || true

log "Starting simple deploy"
log "Repo: $GIT_REPO  Ref: $REF"

if [[ -d "$NEW_RELEASE" && -n "$(ls -A "$NEW_RELEASE" 2>/dev/null)" ]]; then
  log "Release directory already populated, skipping clone"
else
  mkdir -p "$NEW_RELEASE"
  log "Cloning $GIT_REPO (ref: $REF) into $NEW_RELEASE"
  git clone --depth=1 --branch "$REF" "$GIT_REPO" "$NEW_RELEASE"
fi

cd "$NEW_RELEASE"

# Link shared resources
log "Linking shared resources"
ln -sfn "$SHARED/.env" .env || true
mkdir -p storage public_html
ln -sfn "$SHARED/storage/uploads" public_html/uploads || true
ln -sfn "$SHARED/storage" storage || true

# Install dependencies / build
if [[ -f "composer.json" ]]; then
  if command -v composer >/dev/null 2>&1; then
    log "Installing PHP dependencies (composer)"
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress || log "composer install failed"
  else
    log "composer not found; skipping PHP dependencies"
  fi
fi

if [[ -f package.json ]]; then
  if command -v npm >/dev/null 2>&1; then
    log "Installing Node dependencies and building assets"
    npm ci --include=dev || log "npm ci failed"
    if npm run | grep -q "build:prod"; then
      npm run build:prod || log "npm build:prod failed"
    elif npm run | grep -q "build"; then
      npm run build || log "npm build failed"
    fi
  else
    log "npm not found; skipping Node install/build"
  fi
fi

# Switch current symlink
log "Switching current to $NEW_RELEASE"
ln -sfn "$NEW_RELEASE" "$CURRENT"

# Ensure public_html points to current/public_html
PUBLIC_HTML_BASE="$BASE/public_html"
PUBLIC_HTML_TARGET="$CURRENT/public_html"
if [[ -L "$PUBLIC_HTML_BASE" ]]; then
  rm -f "$PUBLIC_HTML_BASE" || true
elif [[ -d "$PUBLIC_HTML_BASE" ]]; then
  mv "$PUBLIC_HTML_BASE" "${PUBLIC_HTML_BASE}.backup_$DATE" || true
fi
ln -sfn "$PUBLIC_HTML_TARGET" "$PUBLIC_HTML_BASE"

restart_node() {
  if [[ -f "$PID_FILE" ]]; then
    pid=$(cat "$PID_FILE" 2>/dev/null || true)
    if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
      log "Stopping existing Node process (PID: $pid)"
      kill "$pid" || true
      sleep 1
      kill -9 "$pid" 2>/dev/null || true
    fi
  fi

  if [[ "$START_NODE_SERVER" == "true" ]]; then
    log "Starting Node server"
    (cd "$CURRENT" && nohup env NODE_ENV="$NODE_ENV" npm start > "$LOGS/node-server_$DATE.log" 2>&1 & echo $! > "$PID_FILE")
  else
    log "Node server start skipped ( --no-start )"
  fi
}

restart_node

# Cleanup old releases
log "Cleaning up old releases, keeping $KEEP_RELEASES"
count=0
for d in $(ls -1dt "$RELEASES"/* 2>/dev/null || true); do
  count=$((count+1))
  if [[ $count -gt $KEEP_RELEASES ]]; then
    log "Removing old release: $d"
    rm -rf "$d" || true
  fi
done

log "Deploy completed: $NEW_RELEASE"
exit 0
