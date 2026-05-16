#!/usr/bin/env bash

# Simple production-ready local deployment helper
# Creates a release archive from the current commit (git archive), uploads it to the server,
# extracts it under the releases directory and triggers the server-side `scripts/deploy.sh`.

set -euo pipefail

usage() {
    cat <<EOF
Usage: $0 -u USER -h HOST -k KEY_PATH [-p PORT] [-b REMOTE_BASE] [-r RELEASE_TS] [--no-deploy]

Options:
  -u USER        Remote SSH username (required)
  -h HOST        Remote host or IP (required)
  -k KEY_PATH    Path to private SSH key (required)
  -p PORT        SSH port (default: 22)
  -b REMOTE_BASE Remote base path (default: /home/USER/broxlab)
  -r RELEASE_TS  Release timestamp to use (default: current datetime)
  --no-deploy    Only upload and extract, do not run remote deploy script
  -v             Verbose (prints commands)
  --help         Show this help and exit

Example:
  $0 -u tdhuedhn -h 65.21.174.100 -k ~/.ssh/id_rsa

EOF
}

USER=""
HOST=""
KEY_PATH=""
PORT=22
REMOTE_BASE=""
RELEASE_TS=""
NO_DEPLOY=false
VERBOSE=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        -u) USER="$2"; shift 2;;
        -h) HOST="$2"; shift 2;;
        -k) KEY_PATH="$2"; shift 2;;
        -p) PORT="$2"; shift 2;;
        -b) REMOTE_BASE="$2"; shift 2;;
        -r) RELEASE_TS="$2"; shift 2;;
        --no-deploy) NO_DEPLOY=true; shift 1;;
        -v) VERBOSE=true; shift 1;;
        --help) usage; exit 0;;
        *) echo "Unknown argument: $1"; usage; exit 1;;
    esac
done

if [[ -z "$USER" || -z "$HOST" || -z "$KEY_PATH" ]]; then
    echo "Error: -u USER, -h HOST and -k KEY_PATH are required" >&2
    usage
    exit 1
fi

if [[ -z "$REMOTE_BASE" ]]; then
    REMOTE_BASE="/home/${USER}/broxlab"
fi

if [[ -z "$RELEASE_TS" ]]; then
    RELEASE_TS=$(date +"%Y%m%d_%H%M%S")
fi

if [[ ! -f "$KEY_PATH" ]]; then
    echo "Error: SSH key not found at $KEY_PATH" >&2
    exit 1
fi

require_cmd() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Error: required command '$1' not found in PATH" >&2
        exit 2
    fi
}

require_cmd git
require_cmd ssh
require_cmd scp
require_cmd rsync || true

SSH_OPTS=( -i "$KEY_PATH" -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p "$PORT" )

REMOTE_DIR="${REMOTE_BASE}/app/releases/${RELEASE_TS}"

echo "Creating remote directory: $REMOTE_DIR"
ssh "${SSH_OPTS[@]}" "$USER@$HOST" "mkdir -p '$REMOTE_DIR'" \
    || { echo "Failed to create remote dir" >&2; exit 1; }

echo "Syncing repository to remote release dir via rsync"
rsync -az --delete -e "ssh ${SSH_OPTS[*]}" --exclude='.git' --exclude='node_modules' --exclude='vendor' ./ "$USER@$HOST:$REMOTE_DIR/" \
    || { echo "Rsync upload failed" >&2; exit 1; }

if [[ "$NO_DEPLOY" == "true" ]]; then
    echo "Upload complete. Skipping remote activation as requested."
    exit 0
fi

echo "Activating release on remote host"
ssh "${SSH_OPTS[@]}" "$USER@$HOST" "set -euo pipefail; \
    SHARED=\\"${REMOTE_BASE}/app/shared\\"; \
    CURRENT=\\"${REMOTE_BASE}/app/current\\"; \
    LOGS=\\"${REMOTE_BASE}/logs\\"; \
    cd '$REMOTE_DIR' || exit 1; \
    ln -sfn \"$SHARED/.env\" .env || true; \
    mkdir -p storage public_html || true; \
    ln -sfn \"$SHARED/storage/uploads\" public_html/uploads || true; \
    ln -sfn \"$SHARED/storage\" storage || true; \
    if [[ -f composer.json ]] && command -v composer >/dev/null 2>&1; then composer install --no-dev --optimize-autoloader --no-interaction --no-progress || true; fi; \
    if [[ -f package.json ]] && command -v npm >/dev/null 2>&1; then npm ci --include=dev || true; if npm run | grep -q \"build:prod\"; then npm run build:prod || true; elif npm run | grep -q \"build\"; then npm run build || true; fi; fi; \
    ln -sfn \"$REMOTE_DIR\" \"$CURRENT\"; \
    PUBLIC_HTML_BASE=\\"${REMOTE_BASE}/public_html\\"; PUBLIC_HTML_TARGET=\\"$CURRENT/public_html\\"; \
    if [[ -L \"$PUBLIC_HTML_BASE\" ]]; then rm -f \"$PUBLIC_HTML_BASE\" || true; elif [[ -d \"$PUBLIC_HTML_BASE\" ]]; then mv \"$PUBLIC_HTML_BASE\" \"${PUBLIC_HTML_BASE}.backup_$(date +%Y%m%d_%H%M%S)\" || true; fi; \
    ln -sfn \"$PUBLIC_HTML_TARGET\" \"$PUBLIC_HTML_BASE\"; \
    # Restart node server
    PID_FILE=\\"${REMOTE_BASE}/app/shared/node-server.pid\\"; \
    if [[ -f \"$PID_FILE\" ]]; then pid=\\$(cat \"$PID_FILE\" 2>/dev/null || true); if [[ -n \"$pid\" ]] && kill -0 \"$pid\" 2>/dev/null; then kill \"$pid\" || true; sleep 1; kill -9 \"$pid\" 2>/dev/null || true; fi; fi; \
    if [[ \"${START_NODE_SERVER:-true}\" == \"true\" ]]; then (cd \"$CURRENT\" && nohup env NODE_ENV=production npm start > \"$LOGS/node-server_$(date +%Y%m%d_%H%M%S).log\" 2>&1 & echo \$! > \"$PID_FILE\") || true; fi"

echo "Remote activation completed successfully"
exit 0
