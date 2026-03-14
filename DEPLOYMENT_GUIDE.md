# cPanel Deployment Solution - Complete Setup Guide

This guide covers setting up automated deployment for your cPanel shared hosting using GitHub Actions with SSH access.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [GitHub Repository Setup](#github-repository-setup)
3. [GitHub Secrets Configuration](#github-secrets-configuration)
4. [cPanel Server Setup](#cpanel-server-setup)
5. [Webhook Configuration](#webhook-configuration)
6. [Deployment Scripts](#deployment-scripts)
7. [Testing the Deployment](#testing-the-deployment)
8. [Rollback Procedures](#rollback-procedures)
9. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before starting, ensure you have:

- ✅ GitHub repository with your project
- ✅ cPanel hosting with SSH access enabled
- ✅ SSH key authentication configured
- ✅ Git installed locally and on server

---

## GitHub Repository Setup

### 1. Create GitHub Actions Workflow

The workflow file is located at [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml).

### 2. Add GitHub Secrets

Go to your repository → Settings → Secrets and variables → Actions, then add:

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `SSH_HOST` | Your cPanel server hostname | `server.example.com` |
| `SSH_PORT` | SSH port (usually 22) | `22` |
| `SSH_USERNAME` | Your cPanel username | `username` |
| `SSH_PRIVATE_KEY` | SSH private key | `-----BEGIN OPENSSH...` |
| `KNOWN_HOSTS` | SSH known hosts entry | `server.example.com ssh-rsa AAAA...` |
| `DEPLOY_SECRET` | Webhook secret | `your_webhook_secret` |

### 3. Generate SSH Key Pair

On your local machine:

```bash
# Generate SSH key (no passphrase for automation)
ssh-keygen -t ed25519 -C "deploy@github-actions"

# Copy public key to server
ssh-copy-id -i ~/.ssh/id_ed25519.pub username@server.example.com

# Test connection
ssh -i ~/.ssh/id_ed25519 username@server.example.com
```

---

## cPanel Server Setup

### 1. Create Deployment Directory Structure

SSH into your cPanel server and run:

```bash
# Create deployment directories
mkdir -p ~/deploys/backups
mkdir -p ~/deploys/logs

# Your project directory (adjust as needed)
ls -la ~/broxbhai  # Verify your project exists
```

### 2. Upload Deployment Scripts

Upload the scripts from the `scripts/` folder to your server:

```bash
# Upload via scp (from your local machine)
scp scripts/deploy.sh username@server:~/
scp scripts/backup-rotate.sh username@server:~/
scp scripts/.deploy-config username@server:~/.deploy-config
```

Or create them directly on the server.

### 3. Configure .deploy-config

Edit the `.deploy-config` file on your server:

```bash
nano ~/.deploy-config
```

Set your paths:

```bash
DEPLOY_ROOT="$HOME/deploys"
PROJECT_DIR="$HOME/broxlab"
BACKUP_DIR="$DEPLOY_ROOT/backups"
VERSION_FILE="$DEPLOY_ROOT/version.json"
LOG_FILE="$DEPLOY_ROOT/deploy.log"
KEEP_BACKUPS=5

# Optional: PM2 for Node.js apps
PM2_APP_NAME="broxbhai"
```

### 4. Make Scripts Executable

```bash
chmod +x ~/deploy.sh
chmod +x ~/backup-rotate.sh
```

---

## Webhook Configuration

### 1. Configure GitHub Webhook

In your GitHub repository:

1. Go to Settings → Webhooks → Add webhook
2. Set Payload URL: `https://yourdomain.com/webhook/github.php`
3. Set Content type: `application/json`
4. Set Secret: Use the same value as `DEPLOY_SECRET` in your GitHub secrets
5. Select events: Just the `push` event
6. Click "Add webhook"

### 2. Configure deploy_config.php

Edit `public_html/deploy_config.php` on your server:

```php
return [
    // Security - must match GitHub webhook secret
    'secret' => 'your_webhook_secret_here',
    
    // Branch to deploy
    'branch' => 'main',
    
    // Paths
    'project_dir' => '/home/username/broxlab',
    'backup_dir' => '/home/username/deploys/backups',
    'version_file' => '/home/username/deploys/version.json',
    'log_file' => '/home/username/deploys/deploy.log',
    
    // Database credentials
    'db_host' => 'localhost',
    'db_name' => 'username_dbname',
    'db_user' => 'username_dbuser',
    'db_pass' => 'your_db_password',
    
    // Backup settings
    'keep_backups' => 5,
    
    // Admin API key (for management endpoints)
    'admin_api_key' => 'generate_a_strong_random_key',
    
    // Debug mode (disable in production)
    'debug' => false,
];
```

### 3. Generate Admin API Key

```bash
# Generate a secure API key
openssl rand -hex 32
```

---

## Deployment Scripts

### Using deploy.sh

```bash
# Basic deployment
./deploy.sh

# With specific version
./deploy.sh --version=v1.2.3

# Skip backup
./deploy.sh --skip-backup=yes

# Force backup
./deploy.sh --create-backup=yes

# Patch version bump
./deploy.sh --version-type=patch

# Minor version bump
./deploy.sh --version-type=minor

# Major version bump
./deploy.sh --version-type=major
```

### Using backup-rotate.sh

```bash
# Create a backup
./backup-rotate.sh backup
./backup-rotate.sh backup pre-deploy

# List all backups
./backup-rotate.sh list

# Show backup info
./backup-rotate.sh info backup_20240315_143022

# Rollback to a backup
./backup-rotate.sh rollback backup_20240315_143022

# Clean old backups (keep last 3)
./backup-rotate.sh clean 3
```

---

## Admin API Endpoints

After setting up the admin API key, you can use these endpoints:

### Get Status
```
GET https://yourdomain.com/webhook/github.php?action=status&api_key=YOUR_API_KEY
```

### List Versions/Backups
```
GET https://yourdomain.com/webhook/github.php?action=versions&api_key=YOUR_API_KEY
```

### Create Manual Backup
```
GET https://yourdomain.com/webhook/github.php?action=backup&api_key=YOUR_API_KEY
```

### List Backups
```
GET https://yourdomain.com/webhook/github.php?action=list&api_key=YOUR_API_KEY
```

### Rollback
```
GET https://yourdomain.com/webhook/github.php?action=rollback&version=v1.0.0&api_key=YOUR_API_KEY
```

---

## Testing the Deployment

### Test 1: Manual Deployment

```bash
# SSH into your server
ssh username@server

# Run deploy script
cd ~
./deploy.sh
```

### Test 2: Trigger via Git Push

```bash
# Make a small change
echo "test" >> test.txt

# Commit and push
git add .
git commit -m "Test deployment"
git push origin main
```

### Test 3: Check Deployment Logs

```bash
# View deployment log
cat ~/deploys/deploy.log

# Or via API
curl "https://yourdomain.com/webhook/github.php?action=status&api_key=YOUR_API_KEY"
```

---

## Rollback Procedures

### Option 1: Using backup-rotate.sh

```bash
# List available backups
./backup-rotate.sh list

# Rollback to specific backup
./backup-rotate.sh rollback backup_20240315_143022
```

### Option 2: Using GitHub Actions

1. Go to GitHub Actions
2. Select "Deploy to cPanel"
3. Click "Run workflow"
4. Check "Rollback"
5. Optionally specify version

### Option 3: Using Web API

```bash
# Get available versions
curl "https://yourdomain.com/webhook/github.php?action=versions&api_key=YOUR_API_KEY"

# Rollback
curl "https://yourdomain.com/webhook/github.php?action=rollback&version=v1.0.0&api_key=YOUR_API_KEY"
```

---

## Troubleshooting

### Common Issues

#### 1. SSH Connection Failed

**Problem**: GitHub Actions can't connect to server

**Solution**:
```bash
# Verify SSH key works
ssh -i ~/.ssh/id_ed25519 username@server

# Check if key is added to SSH agent
ssh-add -l

# Add key to agent if needed
ssh-add ~/.ssh/id_ed25519
```

#### 2. Permission Denied

**Problem**: Permission denied errors during deployment

**Solution**:
```bash
# On server, check permissions
ls -la ~/deploys
ls -la ~/broxbhai

# Fix permissions
chmod -R 755 ~/deploys
chmod -R 755 ~/broxbhai
```

#### 3. Webhook Signature Mismatch

**Problem**: 401 Unauthorized - signature mismatch

**Solution**:
```bash
# Enable debug mode in deploy_config.php
'debug' => true

# Check debug log
cat ~/deploys/sig-debug.log

# Verify webhook secret matches GitHub and server
# GitHub webhook secret = deploy_config.php secret
```

#### 4. Database Backup Failed

**Problem**: Database backup not working

**Solution**:
```bash
# Test mysqldump manually
mysqldump -h localhost -u username -p dbname > test.sql

# Check credentials in .env or deploy_config.php
```

#### 5. Dependencies Not Installing

**Problem**: Composer/NPM not running

**Solution**:
```bash
# Check if files exist
ls -la ~/broxbhai/composer.json
ls -la ~/broxbhai/package.json

# Install manually
cd ~/broxbhai
composer install
npm install
```

### Debug Mode

Enable debug mode in `deploy_config.php`:

```php
'debug' => true,
```

This creates a debug log at `~/deploys/sig-debug.log` with detailed signature verification info.

---

## Security Best Practices

1. **Keep secrets secure**: Never commit `deploy_config.php` or `.env` to git
2. **Rotate API keys regularly**: Generate new admin API keys periodically
3. **Limit SSH access**: Use IP restrictions if possible
4. **Monitor deployments**: Check logs after each deployment
5. **Test rollback**: Regularly test rollback procedures

---

## File Structure

After setup, your server should have:

```
~/
├── broxbhai/              # Your project
├── deploy.sh              # Deployment script
├── backup-rotate.sh       # Backup/rollback script
├── .deploy-config         # Deployment configuration
└── deploys/
    ├── backups/          # Backup storage
    │   ├── v1.0.0_2024...
    │   └── v1.0.1_2024...
    ├── version.json      # Version tracking
    └── deploy.log        # Deployment logs
```

---

## Quick Reference

| Command | Description |
|---------|-------------|
| `./deploy.sh` | Deploy latest code |
| `./deploy.sh --version=v1.0.0` | Deploy specific version |
| `./backup-rotate.sh backup` | Create manual backup |
| `./backup-rotate.sh list` | List backups |
| `./backup-rotate.sh rollback <backup>` | Rollback |

---

For additional help, check the deployment logs at `~/deploys/deploy.log` or enable debug mode in `deploy_config.php`.
