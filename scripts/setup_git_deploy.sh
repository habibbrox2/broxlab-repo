#!/bin/bash

# Setup bare Git repository on server for deployment
# Run this once on the server to initialize Git-based deployment

set -e

DEPLOY_USER="tdhuedhn"
DEPLOY_HOME="/home/${DEPLOY_USER}"
BARE_REPO="${DEPLOY_HOME}/broxbhai.git"
WORK_DIR="${DEPLOY_HOME}/broxbhai"

echo "=== Setting up Git-based deployment ==="
echo "Bare repo: $BARE_REPO"
echo "Work dir: $WORK_DIR"

# Create bare repository if it doesn't exist
if [ ! -d "$BARE_REPO" ]; then
    echo "Creating bare repository..."
    mkdir -p "$BARE_REPO"
    cd "$BARE_REPO"
    git init --bare
    echo "Bare repository created."
else
    echo "Bare repository already exists."
fi

# Create post-receive hook
echo "Setting up post-receive hook..."
cat > "$BARE_REPO/hooks/post-receive" << 'HOOK_EOF'
#!/bin/bash
# Post-receive hook: Auto-deploy on push

DEPLOY_USER="tdhuedhn"
DEPLOY_HOME="/home/${DEPLOY_USER}"
BARE_REPO="${DEPLOY_HOME}/broxbhai.git"
WORK_DIR="${DEPLOY_HOME}/broxbhai"

# Create work directory if it doesn't exist
if [ ! -d "$WORK_DIR" ]; then
    echo "Creating deployment directory: $WORK_DIR"
    mkdir -p "$WORK_DIR"
fi

# Check out latest code from bare repository
echo "Deploying code to $WORK_DIR..."
git --work-tree="$WORK_DIR" --git-dir="$BARE_REPO" checkout -f master

echo "Deployment successful!"
echo "Branch: master"
echo "Deployment directory: $WORK_DIR"
HOOK_EOF

chmod +x "$BARE_REPO/hooks/post-receive"
echo "Post-receive hook installed."

# If old working directory exists, migrate it
if [ -d "$WORK_DIR/.git" ] && [ ! -L "$WORK_DIR/.git" ]; then
    echo "Migrating existing working directory..."
    # Backup old repo
    mv "$WORK_DIR" "${WORK_DIR}.bak"
    mkdir -p "$WORK_DIR"
    cd "$WORK_DIR"
    git clone "$BARE_REPO" .
    echo "Migration complete. Old repo backed up to ${WORK_DIR}.bak"
fi

echo ""
echo "=== Setup complete ==="
echo "You can now push to: ssh://${DEPLOY_USER}@dgtts.org${BARE_REPO}"
echo "Code will be deployed to: $WORK_DIR"
