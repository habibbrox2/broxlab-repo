#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<EOF
Usage: $0 -u USER -h HOST -k KEY_PATH [-p PORT] [-b REMOTE_BASE] [-r RELEASE_TS] [--no-start] [--no-build] [--dry-run]

Options:
  -u USER        Remote SSH username (required)
  -h HOST        Remote host or IP (required)
  -k KEY_PATH    Path to private SSH key (required)
  -p PORT        SSH port (default: 22)
  -b REMOTE_BASE Remote base path (default: /home/USER/broxlab)
  -r RELEASE_TS  Release timestamp to use (default: current datetime)
  --no-start    Do not start the remote Node server after upload
  --no-build    Do not build on the remote host
  --dry-run     Print actions without executing
  --help         Show help and exit
EOF
}

USER=""
HOST=""
KEY_PATH=""
PORT=22
REMOTE_BASE=""
RELEASE_TS=""
NO_START=false
NO_BUILD=false
DRY_RUN=false

while [[ $# -gt 0 ]]; do
  case "$1" in
    -u)
      USER="$2"
      shift 2
      ;;
    -h)
      HOST="$2"
      shift 2
      ;;
    -k)
      KEY_PATH="$2"
      shift 2
      ;;
    -p)
      PORT="$2"
      shift 2
      ;;
    -b)
      REMOTE_BASE="$2"
      shift 2
      ;;
    -r)
      RELEASE_TS="$2"
      shift 2
      ;;
    --no-start)
      NO_START=true
      shift
      ;;
    --no-build)
      NO_BUILD=true
      shift
      ;;
    --dry-run)
      DRY_RUN=true
      shift
      ;;
    --help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$USER" || -z "$HOST" || -z "$KEY_PATH" ]]; then
  echo "Error: -u USER, -h HOST and -k KEY_PATH are required." >&2
  usage
  exit 1
fi

if [[ -z "$REMOTE_BASE" ]]; then
  REMOTE_BASE="/home/$USER/broxlab"
fi

if [[ -z "$RELEASE_TS" ]]; then
  RELEASE_TS="$(date +%Y%m%d_%H%M%S)"
fi

REMOTE_APP="$REMOTE_BASE/app"
REMOTE_RELEASE="$REMOTE_APP/releases/$RELEASE_TS"
REMOTE_SHARED="$REMOTE_APP/shared"
REMOTE_LOGS="$REMOTE_BASE/logs"
REMOTE_PID="$REMOTE_SHARED/node-server.pid"

SSH_OPTS=( -i "$KEY_PATH" -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p "$PORT" )
EXCLUDES=(.git node_modules vendor storage public_html/uploads)

log() {
  echo "[local-deploy] $1"
}

if [[ "$DRY_RUN" == "true" ]]; then
  log "Dry run: would deploy to $HOST:$REMOTE_RELEASE"
  exit 0
fi

if [[ ! -f "$KEY_PATH" ]]; then
  echo "Error: SSH key not found: $KEY_PATH" >&2
  exit 1
fi

ssh "${SSH_OPTS[@]}" "$USER@$HOST" "mkdir -p '$REMOTE_RELEASE' '$REMOTE_SHARED' '$REMOTE_LOGS'"

RSYNC_EXCLUDES=()
for exclude in "${EXCLUDES[@]}"; do
  RSYNC_EXCLUDES+=(--exclude="$exclude")
done

log "Syncing changes to remote release"
rsync -az --delete -e "ssh ${SSH_OPTS[*]}" "${RSYNC_EXCLUDES[@]}" ./ "$USER@$HOST:$REMOTE_RELEASE/"

REMOTE_APP="$REMOTE_BASE/app"

log "Activating remote release"
ssh "${SSH_OPTS[@]}" "$USER@$HOST" bash -s <<EOF
set -euo pipefail
BASE='$REMOTE_BASE'
RELEASE='$REMOTE_RELEASE'
SHARED='$REMOTE_SHARED'
LOGS='$REMOTE_LOGS'
PID_FILE='$REMOTE_PID'
REMOTE_APP='$REMOTE_APP'
RELEASE_TS='$RELEASE_TS'
NO_BUILD='$NO_BUILD'

if [[ ! -f "\$SHARED/.env" ]]; then
  echo "[remote] Missing \$SHARED/.env" >&2
  exit 1
fi
mkdir -p "\$SHARED/storage/uploads" "\$SHARED/backups/code" "\$SHARED/backups/database" "\$LOGS"
ln -sfn "\$SHARED/.env" "\$RELEASE/.env"
mkdir -p "\$RELEASE/public_html"
ln -sfn "\$SHARED/storage/uploads" "\$RELEASE/public_html/uploads"
ln -sfn "\$SHARED/storage" "\$RELEASE/storage"

if [[ -f "\$RELEASE/composer.json" && command -v composer >/dev/null 2>&1 ]]; then
  echo "[remote] Installing PHP dependencies"
  (cd "\$RELEASE" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress)
fi

if [[ -f "\$RELEASE/package.json" && command -v npm >/dev/null 2>&1 ]]; then
  echo "[remote] Installing Node dependencies"
  (cd "\$RELEASE" && npm ci --include=dev)
  if [[ "\$NO_BUILD" != "true" ]]; then
    if npm run --prefix "\$RELEASE" build:prod >/dev/null 2>&1; then
      (cd "\$RELEASE" && npm run build:prod)
    elif npm run --prefix "\$RELEASE" build >/dev/null 2>&1; then
      (cd "\$RELEASE" && npm run build)
    fi
  fi
fi

if [[ -f "\$PID_FILE" ]]; then
  pid=$(cat "\$PID_FILE" 2>/dev/null || true)
  if [[ -n "\$pid" ]] && kill -0 "\$pid" 2>/dev/null; then
    echo "[remote] Stopping existing Node process \$pid"
    kill "\$pid" || true
    sleep 2
  fi
  rm -f "\$PID_FILE"
fi

ln -sfn "\$RELEASE" "\$REMOTE_APP/current"
rm -rf "\$REMOTE_BASE/public_html"
ln -sfn "\$RELEASE/public_html" "\$REMOTE_BASE/public_html"

if [[ "\$NO_START" != "true" ]]; then
  echo "[remote] Starting Node server"
  nohup env NODE_ENV=production bash -lc "cd '\$RELEASE' && npm start" > "\$LOGS/node-server_\$RELEASE_TS.log" 2>&1 &
  echo $! > "\$PID_FILE"
fi
EOF
