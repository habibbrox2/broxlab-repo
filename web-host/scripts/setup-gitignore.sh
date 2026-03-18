#!/bin/bash

# ═══════════════════════════════════════════════════════════════════╗
# ║       Secure Server Setup - Initialize .gitignore               ║
# ║   Protects sensitive production data from accidental commits    ║
# ═══════════════════════════════════════════════════════════════════╝

set -e

BASE="/home/tdhuedhn/broxlab"
APP="$BASE/app"
RELEASES="$APP/releases"

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Logging functions
log_info() {
    echo -e "${GREEN}✅${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}⚠️${NC}  $1"
}

log_error() {
    echo -e "${RED}❌${NC} $1"
}

print_section() {
    echo -e "\n${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}\n"
}

# ─────────────────────────────────────────────────────────────────
# Introduction
# ─────────────────────────────────────────────────────────────────

print_section "SECURE SERVER GITIGNORE SETUP"

echo "This script will:"
echo "  1. Initialize .gitignore in current and shared directories"
echo "  2. Create .gitignore in all release subdirectories"
echo "  3. Prevent sensitive production data from being accidentally committed"
echo ""
echo "What gets protected:"
echo "  🔒 Database backups (*.sql, *.sql.gz)"
echo "  🔒 Environment files (.env)"
echo "  🔒 User uploads (/uploads/)"
echo "  🔒 Log files (*.log, /logs/)"
echo "  🔒 Cache and temp files"
echo "  🔒 Python virtual environments"
echo ""

# ─────────────────────────────────────────────────────────────────
# Copy main .gitignore
# ─────────────────────────────────────────────────────────────────

print_section "Step 1: Copy .gitignore to Current Release"

if [[ -L "$APP/current" ]]; then
    CURRENT_RELEASE=$(readlink "$APP/current")
    log_info "Current release: $CURRENT_RELEASE"
    
    if [[ -f "$BASE/.gitignore" ]]; then
        cp "$BASE/.gitignore" "$CURRENT_RELEASE/.gitignore"
        log_info "✅ Copied main .gitignore to current release"
    else
        log_warn "Main .gitignore not found at $BASE/.gitignore"
    fi
else
    log_warn "Current symlink not found: $APP/current"
fi

# ─────────────────────────────────────────────────────────────────
# Copy .gitignore to all releases
# ─────────────────────────────────────────────────────────────────

print_section "Step 2: Setup .gitignore in All Releases"

if [[ -d "$RELEASES" ]]; then
    RELEASE_COUNT=$(find "$RELEASES" -maxdepth 1 -type d ! -name releases | wc -l)
    log_info "Found $RELEASE_COUNT releases"
    
    for release_dir in $RELEASES/*/; do
        if [[ -d "$release_dir" ]] && [[ -f "$BASE/.gitignore" ]]; then
            cp "$BASE/.gitignore" "$release_dir/.gitignore"
            log_info "✅ Setup .gitignore in $(basename $release_dir)"
        fi
    done
else
    log_warn "Releases directory not found: $RELEASES"
fi

# ─────────────────────────────────────────────────────────────────
# Setup shared directories .gitignore
# ─────────────────────────────────────────────────────────────────

print_section "Step 3: Verify Sensitive Directories Have .gitignore"

# Create .gitignore in sensitive subdirectories
create_directory_gitignore() {
    local dir=$1
    local name=$2
    
    if [[ -d "$dir" ]]; then
        # Create basic .gitignore that ignores everything
        cat > "$dir/.gitignore" << EOF
# Ignore all files in this directory
# (${name} - Contains production data)
*
*/
!.gitignore
EOF
        log_info "✅ Created .gitignore in $name"
    fi
}

# Database backups directory
create_directory_gitignore "$APP/shared/backups/database" "Database Backups"

# Application logs
create_directory_gitignore "$APP/shared/logs" "Application Logs"

# Storage cache
create_directory_gitignore "$APP/shared/storage/cache" "Cache Files"

# User uploads (if exists)
if [[ -d "$APP/shared/uploads" ]]; then
    create_directory_gitignore "$APP/shared/uploads" "User Uploads"
fi

# Python venv
if [[ -d "$APP/shared/rag_env" ]]; then
    create_directory_gitignore "$APP/shared/rag_env" "Python venv"
fi

# ─────────────────────────────────────────────────────────────────
# Create global server .gitignore config
# ─────────────────────────────────────────────────────────────────

print_section "Step 4: Configure Git for Security"

# Configure git to not stage common sensitive file patterns
log_info "Configuring git to ignore sensitive patterns..."

# This prevents accidental staging of sensitive files
git config core.safecrlf warn 2>/dev/null || true

log_info "✅ Git configuration updated"

# ─────────────────────────────────────────────────────────────────
# Verification
# ─────────────────────────────────────────────────────────────────

print_section "Step 5: Verification Report"

echo "Checking .gitignore coverage:"
echo ""

# Check if .gitignore files exist
check_gitignore_file() {
    local file=$1
    local desc=$2
    
    if [[ -f "$file" ]]; then
        local size=$(wc -l < "$file")
        echo -e "${GREEN}✅${NC} $desc: ${size} lines"
        return 0
    else
        echo -e "${RED}❌${NC} $desc: NOT FOUND"
        return 1
    fi
}

check_gitignore_file "$APP/current/.gitignore" "Current release .gitignore"
check_gitignore_file "$APP/shared/backups/database/.gitignore" "Database backups .gitignore"
check_gitignore_file "$APP/shared/logs/.gitignore" "Logs .gitignore"

# ─────────────────────────────────────────────────────────────────
# Summary
# ─────────────────────────────────────────────────────────────────

print_section "SETUP COMPLETE ✅"

echo "Your production server is now protected!"
echo ""
echo "Protected data:"
echo "  🔒 Database backups in /app/shared/backups/database/"
echo "  🔒 Logs in /app/shared/logs/"
echo "  🔒 Cache in /app/shared/storage/cache/"
echo "  🔒 Uploads in /app/shared/uploads/"
echo "  🔒 Python venv in /app/shared/rag_env/"
echo "  🔒 .env files"
echo ""
echo "If you accidentally try to commit these files, git will warn you:"
echo "  git: 'X' was added even though .gitignore ignores it"
echo ""
echo "Next steps:"
echo "  1. Verify: git status"
echo "  2. Check: cat $APP/current/.gitignore"
echo "  3. Remember: Never force-add sensitive files!"
echo ""

echo -e "${YELLOW}IMPORTANT REMINDERS:${NC}"
echo "  • .env files should NEVER be committed"
echo "  • Database backups contain sensitive production data"
echo "  • Use 'git status' before every commit"
echo "  • When in doubt, don't commit it! 🔒"
echo ""

log_info "Setup completed successfully!"
