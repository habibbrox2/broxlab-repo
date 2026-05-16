#!/usr/bin/env bash

set -euo pipefail

BASE="${BASE_PATH:-/home/${USER:-deploy}/broxlab}"
APP="$BASE/app"
CURRENT="$APP/current"
SHARED="$APP/shared"
PID_FILE="$SHARED/node-server.pid"
TARGET=""
ASSUME_YES=false
DRY_RUN=false

usage() {
  cat <<EOF
Usage: $0 [--base PATH] [--target RELEASE] [--yes] [--dry-run]
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base)
      BASE="$2"
      shift 2
      ;;
    --target)
      TARGET="$2"
      shift 2
      ;;
    --yes)
      ASSUME_YES=true
      shift 1
      ;;
    --dry-run)
      DRY_RUN=true
      shift 1
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

APP="$BASE/app"
CURRENT="$APP/current"
SHARED="$APP/shared"
PID_FILE="$SHARED/node-server.pid"

log() {
  printf '[rollback] %s - %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1"
}

log_error() {
  printf '[rollback][ERROR] %s - %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1" >&2
}

confirm() {
  if [[ "$ASSUME_YES" == "true" ]]; then
    return 0
  fi
  read -rp "$1 [y/N]: " answer
  [[ "$answer" =~ ^[Yy]$ ]]
}

if [[ ! -L "$CURRENT" ]]; then
  log_error "Current symlink missing: $CURRENT"
  exit 1
fi

CURRENT_RELEASE="$(readlink -f "$CURRENT")"
if [[ -z "$CURRENT_RELEASE" || ! -d "$CURRENT_RELEASE" ]]; then
  log_error "Current release invalid: $CURRENT_RELEASE"
  exit 1
fi

if [[ -n "$TARGET" ]]; then
  TARGET_RELEASE="$TARGET"
else
  TARGET_RELEASE=""
  for dir in $(ls -1dt "$APP/releases"/* 2>/dev/null || true); do
    if [[ "$dir" != "$CURRENT_RELEASE" ]]; then
      TARGET_RELEASE="$dir"
      break
    fi
  done
fi

if [[ -z "$TARGET_RELEASE" ]]; then
  log_error "No previous release found for rollback."
  exit 1
fi

if [[ "$DRY_RUN" == "true" ]]; then
  log "Dry run: would roll back to $TARGET_RELEASE"
  exit 0
fi

if [[ "$ASSUME_YES" != "true" ]]; then
  if ! confirm "Roll back to $TARGET_RELEASE?"; then
    log "Cancelled by user."
    exit 0
  fi
fi

if [[ -f "$PID_FILE" ]]; then
  pid=$(cat "$PID_FILE" 2>/dev/null || true)
  if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
    log "Stopping Node process (PID: $pid)"
    kill "$pid" || true
    sleep 2
  fi
  rm -f "$PID_FILE"
fi

ln -sfn "$TARGET_RELEASE" "$CURRENT"
rm -rf "$BASE/public_html"
ln -sfn "$TARGET_RELEASE/public_html" "$BASE/public_html"

log "Rollback complete. Now using $TARGET_RELEASE"
exit 0
