#!/usr/bin/env bash
set -euo pipefail

WITH_VENDOR=0
DRY_RUN=0
SKIP_DB=0
DUMP_FILE=""

for arg in "$@"; do
  case "$arg" in
    --with-vendor) WITH_VENDOR=1 ;;
    --dry-run) DRY_RUN=1 ;;
    --skip-db) SKIP_DB=1 ;;
    --dump-file=*) DUMP_FILE="${arg#*=}" ;;
  esac
done

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

run_step() {
  local label="$1"
  shift
  echo "==> $label"
  "$@"
}

if [[ "$SKIP_DB" -eq 0 ]]; then
  run_step "DB backup" powershell -ExecutionPolicy Bypass -File "$REPO_ROOT/scripts/db_backup.ps1"
fi

DEPLOY_ARGS=()
[[ "$WITH_VENDOR" -eq 1 ]] && DEPLOY_ARGS+=(--with-vendor)
[[ "$DRY_RUN" -eq 1 ]] && DEPLOY_ARGS+=(--dry-run)
run_step "Deploy" "$REPO_ROOT/scripts/deploy.sh" "${DEPLOY_ARGS[@]}"

if [[ "$SKIP_DB" -eq 0 ]]; then
  TRANSFER_ARGS=()
  [[ -n "$DUMP_FILE" ]] && TRANSFER_ARGS+=(-DumpFile "$DUMP_FILE")
  [[ "$DRY_RUN" -eq 1 ]] && TRANSFER_ARGS+=(-DryRun)
  run_step "DB transfer" powershell -ExecutionPolicy Bypass -File "$REPO_ROOT/scripts/db_transfer.ps1" "${TRANSFER_ARGS[@]}"
fi

echo "All steps completed."
