#!/usr/bin/env bash
set -euo pipefail

WITH_VENDOR=0
DRY_RUN=0

for arg in "$@"; do
  case "$arg" in
    --with-vendor) WITH_VENDOR=1 ;;
    --dry-run) DRY_RUN=1 ;;
  esac
done

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$REPO_ROOT/.env"

if [[ ! -f "$ENV_FILE" ]]; then
  echo ".env not found at $ENV_FILE"
  exit 1
fi

get_env() {
  local key="$1"
  local val
  val="$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d= -f2-)"
  val="${val%\"}"; val="${val#\"}"
  val="${val%\'}"; val="${val#\'}"
  echo "$val"
}

DEPLOY_SSH_HOST="$(get_env DEPLOY_SSH_HOST)"
DEPLOY_SSH_USER="$(get_env DEPLOY_SSH_USER)"
DEPLOY_SSH_PORT="$(get_env DEPLOY_SSH_PORT)"
DEPLOY_SSH_KEY="$(get_env DEPLOY_SSH_KEY)"
DEPLOY_REMOTE_PATH="$(get_env DEPLOY_REMOTE_PATH)"

if [[ -z "$DEPLOY_SSH_HOST" || -z "$DEPLOY_SSH_USER" || -z "$DEPLOY_REMOTE_PATH" ]]; then
  echo "Missing DEPLOY_* values in .env"
  exit 1
fi

DEPLOY_SSH_PORT="${DEPLOY_SSH_PORT:-22}"

STAGE_DIR="$REPO_ROOT/.deploy/stage"
ZIP_PATH="$REPO_ROOT/.deploy/release.zip"
mkdir -p "$REPO_ROOT/.deploy"

RSYNC_EXCLUDES=(
  --exclude ".git"
  --exclude ".deploy"
  --exclude "node_modules"
  --exclude "uploads"
  --exclude "public_html/uploads"
  --exclude "public_html/assets/uploads"
  --exclude "storage"
  --exclude "Database"
  --exclude ".env"
  --exclude "*.log"
  --exclude "*.sql"
  --exclude "*.zip"
)
if [[ "$WITH_VENDOR" -eq 0 ]]; then
  RSYNC_EXCLUDES+=(--exclude "vendor")
fi

if ! command -v rsync >/dev/null 2>&1; then
  echo "rsync is required for deploy.sh"
  exit 1
fi

RSYNC_FLAGS=(-a --delete)
if [[ "$DRY_RUN" -eq 1 ]]; then
  RSYNC_FLAGS+=(--dry-run)
fi

# Cleanup function for temp SSH key
cleanup_ssh_key() {
    if [[ -n "$SSH_KEY_FILE" && -f "$SSH_KEY_FILE" ]]; then
        rm -f "$SSH_KEY_FILE"
    fi
}
trap cleanup_ssh_key EXIT

echo "Staging files..."
rsync "${RSYNC_FLAGS[@]}" "${RSYNC_EXCLUDES[@]}" "$REPO_ROOT/" "$STAGE_DIR/"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "Dry run completed. No archive/upload performed."
  exit 0
fi

rm -f "$ZIP_PATH"
echo "Creating archive..."
cd "$STAGE_DIR" && zip -qr "$ZIP_PATH" .

REMOTE_ZIP="$DEPLOY_REMOTE_PATH/.deploy/release.zip"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
REMOTE_NEW="${DEPLOY_REMOTE_PATH}__new"
REMOTE_OLD="${DEPLOY_REMOTE_PATH}__old_${TIMESTAMP}"

SSH_OPTS=(-p "$DEPLOY_SSH_PORT")
SCP_OPTS=(-P "$DEPLOY_SSH_PORT")
if [[ -n "$DEPLOY_SSH_KEY" ]]; then
    # Write SSH key to temporary file
    SSH_KEY_FILE=$(mktemp)
    echo "$DEPLOY_SSH_KEY" > "$SSH_KEY_FILE"
    chmod 600 "$SSH_KEY_FILE"
    SSH_OPTS+=(-i "$SSH_KEY_FILE")
    SCP_OPTS+=(-i "$SSH_KEY_FILE")
fi

echo "Uploading archive..."
scp "${SCP_OPTS[@]}" "$ZIP_PATH" "${DEPLOY_SSH_USER}@${DEPLOY_SSH_HOST}:${REMOTE_ZIP}"

echo "Deploying on server..."
ssh "${SSH_OPTS[@]}" "${DEPLOY_SSH_USER}@${DEPLOY_SSH_HOST}" bash -s <<EOF
set -e
mkdir -p "${DEPLOY_REMOTE_PATH}/.deploy"
rm -rf "${REMOTE_NEW}"
mkdir -p "${REMOTE_NEW}"
unzip -oq "${REMOTE_ZIP}" -d "${REMOTE_NEW}"
if [ -d "${DEPLOY_REMOTE_PATH}" ]; then
  mv "${DEPLOY_REMOTE_PATH}" "${REMOTE_OLD}"
fi
mv "${REMOTE_NEW}" "${DEPLOY_REMOTE_PATH}"
EOF

echo "Deploy completed."
