#!/usr/bin/env bash

set -euo pipefail

BASE="${BASE_PATH:-/home/${USER:-deploy}/broxlab}"
GIT_REPO="${GIT_REPO:-git@github.com:habibbrox2/broxlab-repo.git}"
REF="${REF:-main}"
KEEP_RELEASES="${KEEP_RELEASES:-3}"
NODE_ENV="${NODE_ENV:-production}"
START_NODE_SERVER="${START_NODE_SERVER:-true}"
USE_HTTPS="${USE_HTTPS:-false}"
SKIP_BUILD="${SKIP_BUILD:-false}"
DRY_RUN="${DRY_RUN:-false}"
GIT_CLONE_RETRIES="${GIT_CLONE_RETRIES:-3}"
NODE_HEALTH_URL="${NODE_HEALTH_URL:-}"

APP="$BASE/app"
RELEASES="$APP/releases"
SHARED="$APP/shared"
CURRENT="$APP/current"
LOGS="$BASE/logs"
PID_FILE="$SHARED/node-server.pid"
DEPLOY_LOCK="$SHARED/.deploy.lock"
DATE="$(date +%Y%m%d_%H%M%S)"
NEW_RELEASE="$RELEASES/$DATE"
PREVIOUS_RELEASE=""
DEPLOYMENT_SUCCESS=false

usage() {
  cat <<EOF
Usage: $0 [--base PATH] [--repo URL] [--ref BRANCH] [--keep N] [--no-start] [--use-https] [--dry-run] [--skip-build] [--git-retries N]
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base)
      BASE="$2"
      shift 2
      ;;
    --repo)
      GIT_REPO="$2"
      shift 2
      ;;
    --ref)
      REF="$2"
      shift 2
      ;;
    --keep)
      KEEP_RELEASES="$2"
      shift 2
      ;;
    --no-start)
      START_NODE_SERVER=false
      shift 1
      ;;
    --use-https)
      USE_HTTPS=true
      shift 1
      ;;
    --dry-run)
      DRY_RUN=true
      shift 1
      ;;
    --skip-build)
      SKIP_BUILD=true
      shift 1
      ;;
    --git-retries)
      GIT_CLONE_RETRIES="$2"
      shift 2
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

log() {
  printf '[deploy] %s - %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1"
}

log_error() {
  printf '[deploy][ERROR] %s - %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1" >&2
}

log_warn() {
  printf '[deploy][WARN] %s - %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1"
}

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    log_error "Required command not found: $1"
    exit 2
  fi
}

acquire_lock() {
  if [[ -f "$DEPLOY_LOCK" ]]; then
    local lock_pid lock_ts
    lock_pid=$(cut -d: -f1 "$DEPLOY_LOCK" 2>/dev/null || true)
    lock_ts=$(cut -d: -f2 "$DEPLOY_LOCK" 2>/dev/null || true)
    if [[ -n "$lock_pid" ]] && kill -0 "$lock_pid" 2>/dev/null; then
      log_error "Deployment already in progress (PID: $lock_pid, started at $lock_ts)"
      return 1
    fi
  fi
  printf '%s:%s' "$$" "$(date +%s)" > "$DEPLOY_LOCK"
}

release_lock() {
  rm -f "$DEPLOY_LOCK" || true
}

update_public_html() {
  local target="$1/public_html"
  local link="$BASE/public_html"
  if [[ -e "$link" || -L "$link" ]]; then
    rm -rf "$link"
  fi
  ln -sfn "$target" "$link"
}

stop_node_server() {
  if [[ -f "$PID_FILE" ]]; then
    local pid
    pid=$(cat "$PID_FILE" 2>/dev/null || true)
    if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
      log "Stopping existing Node process (PID: $pid)"
      kill "$pid" 2>/dev/null || true
      for _ in $(seq 1 15); do
        if ! kill -0 "$pid" 2>/dev/null; then
          break
        fi
        sleep 1
      done
      kill -9 "$pid" 2>/dev/null || true
    fi
    rm -f "$PID_FILE"
  fi
}

health_check() {
  if [[ -z "$NODE_HEALTH_URL" ]]; then
    log "No NODE_HEALTH_URL set; skipping health check."
    return 0
  fi

  if command -v curl >/dev/null 2>&1; then
    curl -fsS --max-time 5 "$NODE_HEALTH_URL" >/dev/null
    return $?
  fi

  if command -v wget >/dev/null 2>&1; then
    wget -q --timeout=5 -O /dev/null "$NODE_HEALTH_URL"
    return $?
  fi

  log_error "Neither curl nor wget is available for health checks"
  return 1
}

start_node_server() {
  local node_log="$LOGS/node-server_$DATE.log"
  log "Starting Node server from $CURRENT"
  (cd "$CURRENT" && nohup env NODE_ENV="$NODE_ENV" npm start > "$node_log" 2>&1 & echo $! > "$PID_FILE")

  local pid
  pid=$(cat "$PID_FILE" 2>/dev/null || true)
  if [[ -z "$pid" ]]; then
    log_error "Failed to start Node server or to write PID file"
    return 1
  fi

  for _ in $(seq 1 20); do
    if ! kill -0 "$pid" 2>/dev/null; then
      log_error "Node process exited before health check"
      return 1
    fi
    if health_check; then
      log "Node health check passed"
      return 0
    fi
    sleep 2
  done

  if [[ -n "$NODE_HEALTH_URL" ]]; then
    log_error "Node health check failed after timeout"
    return 1
  fi

  log "No health URL configured; assuming server is running"
  return 0
}

rollback_to_previous_release() {
  if [[ -n "$PREVIOUS_RELEASE" && -d "$PREVIOUS_RELEASE" ]]; then
    log "Rolling back to previous release: $PREVIOUS_RELEASE"
    ln -sfn "$PREVIOUS_RELEASE" "$CURRENT"
    update_public_html "$PREVIOUS_RELEASE"
    stop_node_server
    if [[ "$START_NODE_SERVER" == "true" ]]; then
      if ! start_node_server; then
        log_error "Failed to restart previous release after rollback"
        return 1
      fi
    fi
    return 0
  fi

  log_error "No previous release available to restore"
  return 1
}

cleanup_failed_release() {
  local exit_code=$?
  if [[ "$DEPLOYMENT_SUCCESS" != "true" ]]; then
    log_error "Deployment failed with exit code $exit_code"
    if [[ -L "$CURRENT" && "$(readlink -f "$CURRENT")" == "$NEW_RELEASE" ]]; then
      if [[ -n "$PREVIOUS_RELEASE" && -d "$PREVIOUS_RELEASE" ]]; then
        log_warn "Restoring previous release during cleanup"
        ln -sfn "$PREVIOUS_RELEASE" "$CURRENT"
        update_public_html "$PREVIOUS_RELEASE"
        stop_node_server
        if ! start_node_server; then
          log_error "Unable to restart previous release during cleanup"
        fi
      fi
    fi
    rm -rf "$NEW_RELEASE" || true
  fi
  release_lock
  return $exit_code
}

trap cleanup_failed_release EXIT

mkdir -p "$RELEASES" "$SHARED" "$LOGS"

if [[ -L "$CURRENT" ]]; then
  PREVIOUS_RELEASE="$(readlink -f "$CURRENT")"
fi

if [[ "$DRY_RUN" == "true" ]]; then
  log "Dry run enabled"
  log "Base: $BASE"
  log "Repo: $GIT_REPO"
  log "Ref: $REF"
  log "Keep releases: $KEEP_RELEASES"
  log "Use HTTPS: $USE_HTTPS"
  exit 0
fi

require_command git
require_command node
require_command npm

if [[ -f "$NEW_RELEASE/composer.json" || -f "$SHARED/composer" || -f "$SHARED/composer.phar" ]]; then
  if ! command -v php >/dev/null 2>&1; then
    log_error "PHP is required for composer install or PHP syntax checks"
    exit 2
  fi
fi

if [[ ! -f "$SHARED/.env" ]]; then
  log_error "Shared .env not found: $SHARED/.env"
  exit 1
fi

acquire_lock

log "Preparing new release in $NEW_RELEASE"
rm -rf "$NEW_RELEASE"
mkdir -p "$NEW_RELEASE"
clone_url="$GIT_REPO"
if [[ "$USE_HTTPS" == "true" ]]; then
  clone_url="${GIT_REPO/git@github.com:/https://github.com/}"
fi

clone_attempts=0
while [[ $clone_attempts -lt $GIT_CLONE_RETRIES ]]; do
  if GIT_SSH_COMMAND='ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null' git clone --depth 1 --branch "$REF" "$clone_url" "$NEW_RELEASE"; then
    break
  fi
  clone_attempts=$((clone_attempts + 1))
  if [[ $clone_attempts -lt $GIT_CLONE_RETRIES ]]; then
    log "Retrying clone ($clone_attempts/$GIT_CLONE_RETRIES)"
    sleep 5
  fi
  if [[ $clone_attempts -eq $GIT_CLONE_RETRIES && "$USE_HTTPS" != "true" ]]; then
    clone_url="${GIT_REPO/git@github.com:/https://github.com/}"
  fi
  rm -rf "$NEW_RELEASE"
  mkdir -p "$NEW_RELEASE"
done

if [[ ! -d "$NEW_RELEASE" || ! -d "$NEW_RELEASE/.git" ]]; then
  log_error "Repository clone failed"
  exit 1
fi

log "Linking shared resources"
mkdir -p "$SHARED/storage/uploads" "$SHARED/backups/code" "$SHARED/backups/database"
ln -sfn "$SHARED/.env" "$NEW_RELEASE/.env"
mkdir -p "$NEW_RELEASE/public_html"
ln -sfn "$SHARED/storage/uploads" "$NEW_RELEASE/public_html/uploads"
ln -sfn "$SHARED/storage" "$NEW_RELEASE/storage"

if [[ -f "$NEW_RELEASE/composer.json" ]]; then
  if command -v composer >/dev/null 2>&1; then
    log "Installing PHP dependencies"
    (cd "$NEW_RELEASE" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress)
  elif [[ -f "$SHARED/composer" ]]; then
    log "Installing PHP dependencies with shared composer"
    (cd "$NEW_RELEASE" && "$SHARED/composer" install --no-dev --optimize-autoloader --no-interaction --no-progress)
  elif [[ -f "$SHARED/composer.phar" ]]; then
    log "Installing PHP dependencies with shared composer.phar"
    (cd "$NEW_RELEASE" && php "$SHARED/composer.phar" install --no-dev --optimize-autoloader --no-interaction --no-progress)
  else
    log_error "composer is not available for PHP dependency install"
    exit 1
  fi
fi

if [[ -f "$NEW_RELEASE/package.json" ]]; then
  log "Installing Node dependencies"
  (cd "$NEW_RELEASE" && npm ci --include=dev)
  if [[ "$SKIP_BUILD" != "true" ]]; then
    if npm run --prefix "$NEW_RELEASE" build:prod >/dev/null 2>&1; then
      (cd "$NEW_RELEASE" && npm run build:prod)
    elif npm run --prefix "$NEW_RELEASE" build >/dev/null 2>&1; then
      (cd "$NEW_RELEASE" && npm run build)
    else
      log "No recognized build script found; skipping build"
    fi
  fi
fi

if command -v php >/dev/null 2>&1; then
  files=$(find "$NEW_RELEASE" -type f -name '*.php' 2>/dev/null || true)
  if [[ -n "$files" ]]; then
    while IFS= read -r file; do
      php -l "$file" >/dev/null
    done <<< "$files"
  fi
fi

log "Stopping existing server before switching release"
stop_node_server

log "Activating new release"
ln -sfn "$NEW_RELEASE" "$CURRENT"
update_public_html "$NEW_RELEASE"

if [[ "$START_NODE_SERVER" == "true" ]]; then
  if ! start_node_server; then
    log_error "New release failed to start; rolling back"
    rollback_to_previous_release || log_error "Rollback after failed start also failed"
    exit 1
  fi
else
  log "Node server start skipped by option"
fi

if [[ "$KEEP_RELEASES" =~ ^[0-9]+$ ]]; then
  count=0
  for release_dir in $(ls -1dt "$RELEASES"/* 2>/dev/null || true); do
    count=$((count + 1))
    if [[ $count -gt $KEEP_RELEASES ]]; then
      rm -rf "$release_dir" || true
    fi
  done
fi

DEPLOYMENT_SUCCESS=true
log "Deployment completed successfully"
exit 0
