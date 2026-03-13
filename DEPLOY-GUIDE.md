# BroxBhai Deployment Guide

## Quick Deploy (3 Steps)

### Step 1: Upload to Webhost
Upload all project files to your webhost's public_html folder using FTP or Git.

### Step 2: Create .env File
Create `.env` file in project root:

```env
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Dhaka

DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password
DB_CHARSET=utf8mb4
```

### Step 3: Set Permissions
```bash
chmod -R 755 storage/
chmod -R 755 public_html/uploads/
```

---

## Auto-Deploy Setup (GitHub Webhook)

This is the recommended deployment method: **Localhost → git push → GitHub → Webhook → Auto Deploy**

### On Webhost:

1. **Install Git** (if not installed)
```bash
yum install git
# or
apt install git
```

2. **Clone Repository**
```bash
cd /home/username
git clone https://github.com/habibbrox2/broxlab-repo.git public_html
```

3. **Configure Webhook in Admin Panel**
- Go to: Admin Panel → Deploy Tools → GitHub Webhook
- Enable: Yes
- Deploy Path: `/home/username/public_html`
- Branch: main
- Auto-deploy: Yes
- Generate a webhook secret
- Save Settings

### On GitHub:

1. Go to your repository
2. Settings → Webhooks → Add webhook
3. Configure:
   - **Payload URL**: `https://broxlab.online/webhook/github.php`
   - **Content type**: `application/json`
   - **Secret**: Enter the secret you generated in Admin Panel
   - **Events**: Just the push event
4. Click "Add webhook"

---

## Test Deployment

From your local machine:
```bash
git add .
git commit -m "Test deployment"
git push origin main
```

GitHub will send a webhook to your server, and the code will be automatically deployed!

---

## How It Works

```
Localhost (git push) 
    ↓
GitHub (receives push)
    ↓
GitHub sends Webhook → https://yourdomain.com/webhook/github.php
    ↓
Server (webhook handler):
  1. Verifies signature
  2. Creates backup (if enabled)
  3. Runs: git pull origin main
  4. Logs deployment
```

---

## Troubleshooting

### Webhook Not Working?
1. Check if webhook is enabled in Admin Panel
2. Verify Deploy Path is correct
3. Make sure webhook secret matches in both Admin Panel and GitHub
4. Check server logs in storage/logs/

### Database Connection Error?
1. Verify .env file exists and has correct credentials
2. Check database user has proper permissions

### Permission Denied?
```bash
chmod -R 755 /home/username/public_html/storage/
```

### Need to disable auto-deploy?
Go to Admin Panel → Deploy Tools → GitHub Webhook → Disable "Auto-deploy on webhook trigger"

---

## Manual Commands (SSH)

If webhook fails, you can deploy manually:

```bash
cd /home/username/public_html
git pull origin main
chmod -R 755 storage/
```
