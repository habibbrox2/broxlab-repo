#!/bin/bash

# BroxLab rollback script for the shared-hosting single Node server flow.

set -euo pipefail

BASE="${BASE_PATH:-/home/tdhuedhn/broxlab}"
START_NODE_SERVER="${START_NODE_SERVER:-true}"

APP="$BASE/app"
RELEASES="$APP/releases"
CURRENT="$APP/current"
SHARED="$APP/shared"
VERSION_FILE="$SHARED/version.json"
LOGS="$BASE/logs"
PID_FILE="$SHARED/node-server.pid"
CODE_BACKUPS="$SHARED/backups/code"

SKIP_DB_RESTORE=false
SKIP_BACKUP=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-db-restore) SKIP_DB_RESTORE=true; shift ;;
        --no-backup) SKIP_BACKUP=true; shift ;;
        --base) BASE="$2"; shift 2 ;;
        --no-node-start) START_NODE_SERVER=false; shift ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--skip-db-restore] [--no-backup] [--no-node-start] [--base PATH]"
            exit 1
            ;;
    esac
done

APP="$BASE/app"
RELEASES="$APP/releases"
CURRENT="$APP/current"
SHARED="$APP/shared"
VERSION_FILE="$SHARED/version.json"
LOGS="$BASE/logs"
PID_FILE="$SHARED/node-server.pid"
CODE_BACKUPS="$SHARED/backups/code"
export BASE_PATH="$BASE"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

mkdir -p "$LOGS" "$CODE_BACKUPS"
ROLLBACK_LOG="$LOGS/rollback.log"

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ROLLBACK_LOG"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ROLLBACK_LOG"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ROLLBACK_LOG"; }
log_debug() { echo -e "${BLUE}[DEBUG]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ROLLBACK_LOG"; }

ensure_env_secret() {
    local key="$1"
    local env_file="$SHARED/.env"
    local value=""

    if [[ ! -f "$env_file" ]]; then
        log_error ".env not found at $env_file"
        exit 1
    fi

    value=$(php -r 'echo bin2hex(random_bytes(32));')
    if grep -q "^${key}=" "$env_file"; then
        if grep -q "^${key}=$" "$env_file"; then
            log_warn "${key} is empty in shared .env; generating a secure value"
            sed -i "s|^${key}=.*|${key}=${value}|" "$env_file"
        fi
    else
        log_warn "${key} is missing in shared .env; generating a secure value"
        printf '%s=%s\n' "$key" "$value" >> "$env_file"
    fi
}

stop_node_server() {
    if [[ -f "$PID_FILE" ]]; then
        local pid
        pid=$(cat "$PID_FILE" 2>/dev/null || true)
        if [[ -n "${pid:-}" ]] && kill -0 "$pid" 2>/dev/null; then
            log_info "Stopping Node server (PID: $pid)"
            kill "$pid" 2>/dev/null || true
            for _ in $(seq 1 10); do
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
    local url="${NODE_HEALTH_URL:-http://127.0.0.1:3000/health}"
    if command -v curl >/dev/null 2>&1; then
        curl -fsS "$url" >/dev/null 2>&1
        return $?
    fi
    if command -v wget >/dev/null 2>&1; then
        wget -qO- "$url" >/dev/null 2>&1
        return $?
    fi
    return 1
}

start_node_server() {
    local node_log="$LOGS/node-server_rollback_$(date +"%Y%m%d_%H%M%S").log"
    log_info "Starting Node server after rollback"
    (
        cd "$CURRENT"
        nohup env NODE_ENV=production npm start > "$node_log" 2>&1 &
        echo $! > "$PID_FILE"
    )

    local pid
    pid=$(cat "$PID_FILE" 2>/dev/null || true)
    if [[ -z "${pid:-}" ]]; then
        log_error "Node PID file was not created"
        return 1
    fi

    for _ in $(seq 1 30); do
        if ! kill -0 "$pid" 2>/dev/null; then
            log_error "Node server exited early. Check: $node_log"
            tail -40 "$node_log" 2>/dev/null || true
            return 1
        fi
        if health_check; then
            log_info "Node server is healthy"
            return 0
        fi
        sleep 2
    done

    log_warn "Node server started, but health check timed out"
    return 0
}

log_info "Starting rollback process"
$SKIP_DB_RESTORE && log_info "Database restore will be skipped"
$SKIP_BACKUP && log_info "Safety backup will be skipped"

ensure_env_secret "JWT_SECRET"
ensure_env_secret "CSRF_SECRET"
ensure_env_secret "NODE_SERVICE_API_KEY"

if [[ ! -d "$RELEASES" ]]; then
    log_error "Releases directory not found: $RELEASES"
    exit 1
fi

if [[ ! -L "$CURRENT" ]]; then
    log_error "Current symlink not found: $CURRENT"
    exit 1
fi

CURRENT_RELEASE=$(readlink "$CURRENT" 2>/dev/null || true)
if [[ -z "$CURRENT_RELEASE" ]]; then
    log_error "Could not resolve current release"
    exit 1
fi

PREVIOUS_PATH=$(find "$RELEASES" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null | sort -rn | awk 'NR==2 {print $2}')
if [[ -z "$PREVIOUS_PATH" || "$PREVIOUS_PATH" == "$CURRENT_RELEASE" ]]; then
    log_error "No previous release available for rollback"
    exit 1
fi

echo ""
log_warn "ROLLBACK CONFIRMATION REQUIRED"
log_warn "Current release: $(basename "$CURRENT_RELEASE")"
log_warn "Rollback to:     $(basename "$PREVIOUS_PATH")"
echo ""
read -rp "Type 'ROLLBACK' to confirm: " CONFIRM_TEXT
if [[ "$CONFIRM_TEXT" != "ROLLBACK" ]]; then
    log_info "Rollback cancelled"
    exit 3
fi

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
if [[ "$SKIP_BACKUP" == "false" ]]; then
    BACKUP_FILE="$CODE_BACKUPS/rollback_backup_$TIMESTAMP.tar.gz"
    log_info "Creating safety backup: $BACKUP_FILE"
    tar --exclude='node_modules' --exclude='vendor' --exclude='.git' -czf "$BACKUP_FILE" -C "$RELEASES" "$(basename "$CURRENT_RELEASE")"
fi

stop_node_server

log_info "Switching current symlink"
ln -sfn "$PREVIOUS_PATH" "$CURRENT"

if [[ -f "$VERSION_FILE" ]] && command -v jq >/dev/null 2>&1; then
    ROLLBACK_MSG="Rolled back from $(basename "$CURRENT_RELEASE") to $(basename "$PREVIOUS_PATH") at $TIMESTAMP"
    jq --arg msg "$ROLLBACK_MSG" '.last_action = $msg | .status = "rolled_back"' "$VERSION_FILE" > "$VERSION_FILE.tmp" && mv "$VERSION_FILE.tmp" "$VERSION_FILE" || rm -f "$VERSION_FILE.tmp"
fi

if [[ "$START_NODE_SERVER" == "true" ]]; then
    start_node_server
else
    log_warn "Node server restart skipped; restart it manually after rollback"
fi

if [[ "$SKIP_DB_RESTORE" == "false" ]]; then
    echo ""
    read -rp "Restore database from backup? (yes/no): " RESTORE_DB
    if [[ "$RESTORE_DB" == "yes" ]]; then
        DB_RESTORE_SCRIPT="$BASE/scripts/database-restore.sh"
        if [[ -x "$DB_RESTORE_SCRIPT" ]]; then
            BASE_PATH="$BASE" "$DB_RESTORE_SCRIPT" || true
        else
            log_warn "Database restore script not found: $DB_RESTORE_SCRIPT"
        fi
    fi
fi

echo ""
log_info "Rollback complete"
log_info "Current release: $(readlink "$CURRENT" 2>/dev/null || echo N/A)"
log_info "Log: $ROLLBACK_LOG"
exit 0

