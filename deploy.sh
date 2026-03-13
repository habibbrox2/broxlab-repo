#!/bin/bash

# BroxBhai Auto-Deploy Script
# Run this on your webhost server

# Configuration
REPO_URL="https://github.com/habibbrox2/broxlab-repo.git"
DEPLOY_PATH="/home/username/public_html"
BRANCH="main"

echo "========================================="
echo "BroxBhai Auto-Deploy Setup"
echo "========================================="

# Check if git is installed
if ! command -v git &> /dev/null; then
    echo "Error: Git is not installed"
    echo "Install git: yum install git or apt install git"
    exit 1
fi

# Create deploy directory if not exists
if [ ! -d "$DEPLOY_PATH" ]; then
    echo "Creating deployment directory..."
    mkdir -p "$DEPLOY_PATH"
fi

# Initialize git if not already initialized
if [ ! -d "$DEPLOY_PATH/.git" ]; then
    echo "Cloning repository..."
    git clone -b $BRANCH $REPO_URL "$DEPLOY_PATH"
    echo "Repository cloned successfully!"
else
    echo "Repository already exists. Pulling latest changes..."
    cd "$DEPLOY_PATH"
    git fetch origin
    git pull origin $BRANCH
    echo "Repository updated successfully!"
fi

# Set proper permissions
echo "Setting permissions..."
chmod -R 755 "$DEPLOY_PATH/storage" 2>/dev/null
chmod -R 755 "$DEPLOY_PATH/public_html/uploads" 2>/dev/null

echo "========================================="
echo "Deployment completed!"
echo "========================================="
