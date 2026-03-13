# GitHub Webhook Server Setup Guide

This guide explains how to set up your web server for GitHub webhook deployments.

## Prerequisites

- Web hosting with cPanel or SSH access
- Git installed on the server
- GitHub repository

---

## Option 1: cPanel Git Version Control (Recommended for Shared Hosting)

### Step 1: Enable Git in cPanel

1. Log in to cPanel
2. Look for **"Git Version Control"** or **"Git™"** in the Files section
3. Click on it

### Step 2: Create Git Repository

1. Click **"Create"** button
2. **Repository Path**: Enter a path like `/home/username/broxlab.git`
3. **Repository Name**: `broxbhai` or your preferred name
4. Click **"Create"**

### Step 3: Clone Your Repository

1. In cPanel Git interface, find your repository
2. Click **"Clone"** button
3. Copy the clone command:
   ```
   git clone /home/username/broxlab.git /home/username/public_html
   ```
   Or use the HTTPS URL:
   ```
   git clone https://github.com/username/broxlab-repo.git /home/username/public_html
   ```

### Step 4: Configure Deploy Path in Admin Panel

1. Go to your site: **Admin Panel → Deploy Tools → GitHub Webhook**
2. Set **Deploy Path**: `/home/username/public_html`
3. Enable webhook and save

---

## Option 2: Manual SSH Setup (For VPS/Dedicated Servers)

### Step 1: SSH into Your Server

```bash
ssh username@your-server.com
```

### Step 2: Navigate to Home Directory

```bash
cd ~
```

### Step 3: Create Bare Git Repository

```bash
mkdir -p ~/broxbhai.git
cd ~/broxbhai.git
git init --bare
```

### Step 4: Create Deploy Directory

```bash
mkdir -p ~/public_html
```

### Step 5: Create Post-Receive Hook

```bash
cat > ~/broxbhai.git/hooks/post-receive << 'EOF'
#!/bin/bash

# Configuration
GIT_DIR="$HOME/broxbai.git"
WORK_DIR="$HOME/public_html"
BRANCH="main"

# Deploy code
while read oldrev newrev refname; do
    if [ "$refname" = "refs/heads/$BRANCH" ]; then
        echo "Deploying $BRANCH branch..."
        git --work-tree="$WORK_DIR" --git-dir="$GIT_DIR" checkout -f $BRANCH
        echo "✓ Deployed to $WORK_DIR"
    fi
done
EOF

chmod +x ~/broxbhai.git/hooks/post-receive
```

### Step 6: Add Remote on Local Machine

On your local machine:

```bash
git remote add production ssh://username@your-server.com/~/broxbhai.git
```

### Step 7: Push to Production

```bash
git push production main
```

---

## Option 3: GitHub Webhook (Automatic Deploy)

This method uses GitHub to notify your server when code is pushed.

### Step 1: Server Setup (Already Implemented)

The webhook handler is already at: `/webhook/github.php`

### Step 2: Configure in Admin Panel

1. Go to **Admin Panel → Deploy Tools → GitHub Webhook**
2. Enable GitHub Webhook
3. Set **Deploy Path**: `/home/username/public_html`
4. Set **Branch**: `main`
5. Enable **Auto-deploy** if desired
6. Save Settings

### Step 3: Configure GitHub Webhook

1. Go to your GitHub repository
2. Go to **Settings → Webhooks**
3. Click **Add webhook**:
   - **Payload URL**: `https://yourdomain.com/webhook/github.php`
   - **Content type**: `application/json`
   - **Events**: Select "Just the push event"
4. Click **Add webhook**

### Step 4: Test

Make a commit and push to trigger the webhook!

---

## Common Issues & Solutions

### Issue: "Permission denied" on Git Push

**Solution**: 
```bash
# On server, set proper permissions
chmod 755 ~
chmod 755 ~/broxbhai.git
```

### Issue: Webhook Returns 404

**Solution**: 
- Make sure webhook is enabled in Admin Panel
- Check that the deploy path exists

### Issue: Webhook Returns 500

**Solution**:
- Check PHP error logs
- Make sure storage/backups folder is writable
- Check that database connection works

### Issue: Auto-deploy Not Working

**Solution**:
- Make sure Auto-deploy is enabled in Admin Panel
- Check that the branch matches (usually `main` or `master`)
- Verify the deploy path is correct

---

## File Structure After Setup

```
/home/username/
├── broxbhai.git/          (bare git repository)
│   └── hooks/
│       └── post-receive
├── public_html/           (deployed code - document root)
│   ├── index.php
│   ├── app/
│   └── ...
└── storage/
    └── backups/
```

---

## Security Notes

1. **Disable signature verification only for testing!**
2. In production, always use webhook secret
3. Keep your deploy path secure
4. Regularly backup your database

---

## For BroxBhai Specific Setup

The webhook is already configured. You just need to:

1. ✅ Ensure `/webhook/github.php` exists (already there)
2. ✅ Configure in Admin Panel (settings stored in database)
3. ✅ Set up GitHub webhook in GitHub repository settings

Your webhook URL: `https://broxlab.online/webhook/github.php`
