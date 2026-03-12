# Deploy + DB Backup/Transfer + Cron Jobs (Shared Hosting)

This project uses a config-only deploy system driven by `.env`.  
No admin UI is added; all settings live in your local and server `.env`.

## Table of Contents
- [Prerequisites](#prerequisites)
- [Environment Configuration](#environment-configuration)
- [Deploy Scripts](#deploy-scripts)
- [Database Management](#database-management)
- [Cron Jobs for Background Workers](#cron-jobs-for-background-workers)
- [Home/Office Workflow](#homeoffice-workflow)
- [Local Deploy UI](#local-deploy-ui)
- [Quality/Security/Performance Reports](#qualitysecurityperformance-reports)
- [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before deploying, ensure the following tools are available in your PATH:

| Tool | Purpose | Required |
|------|---------|----------|
| `ssh` | Secure remote connection | Yes |
| `scp` | Secure file transfer | Yes |
| `mysqldump` | Database backup | Yes |
| `mysql` | Database import | Yes |
| `git` | Version control | Recommended |
| `php` | CLI script execution | Yes |

### Verify Prerequisites (Windows)
```powershell
where ssh
where scp
where mysqldump
where mysql
where php
```

### Verify Prerequisites (Linux/macOS)
```bash
which ssh
which scp
which mysqldump
which mysql
which php
```

---

## Environment Configuration

### Required .env keys (local)
```
DEPLOY_SSH_HOST=example.com
DEPLOY_SSH_USER=username
DEPLOY_SSH_PORT=22
DEPLOY_SSH_KEY=PATH_TO_PRIVATE_KEY   # optional
DEPLOY_REMOTE_PATH=public_html/broxbhai

DEPLOY_REMOTE_DB_HOST=localhost
DEPLOY_REMOTE_DB_PORT=3306
DEPLOY_REMOTE_DB_NAME=your_db
DEPLOY_REMOTE_DB_USER=your_user
DEPLOY_REMOTE_DB_PASS=your_pass
```

Local DB keys already exist in `.env`:
```
DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_CHARSET
DB_BACKUP_DIR=storage/backups   # override if you prefer
```

### Server .env
Keep a separate `.env` on the server. It is NOT synced by deploy scripts.

### AutoContent Worker Configuration
Add these to your `.env` for background workers:
```
# AutoContent Worker Settings
AUTOCONTENT_MAX_ARTICLES_PER_SOURCE=10
AUTOCONTENT_MAX_SOURCES_PER_RUN=20
AUTOCONTENT_DEDUP_SIMILARITY=0.8
AUTOCONTENT_LOG_PATH=storage/logs/autocontent_worker.log

# Telegram Notifications (optional)
TELEGRAM_ENABLED=false
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_chat_id
```

---

## Deploy Scripts

### Deploy (Windows)
Dry run:
```
powershell -ExecutionPolicy Bypass -File scripts/deploy.ps1 --dry-run
```

Deploy:
```
powershell -ExecutionPolicy Bypass -File scripts/deploy.ps1
```

Include vendor:
```
powershell -ExecutionPolicy Bypass -File scripts/deploy.ps1 --with-vendor
```

### One-click (cross-platform)
Windows:
```
powershell -ExecutionPolicy Bypass -File scripts/deploy_all.ps1
```

Linux/macOS:
```
chmod +x scripts/deploy_all.sh
./scripts/deploy_all.sh
```

Options:
- `--dry-run` (deploy only; DB transfer will be dry-run)
- `--with-vendor` (include vendor/)
- `--skip-db` (skip backup + transfer)
- `--dump-file=PATH` (use specific dump)

### Deploy (Linux/macOS)
```
chmod +x scripts/deploy.sh
./scripts/deploy.sh --dry-run
./scripts/deploy.sh
```

### Deploy Notes
- **Uploads and storage are excluded by default** - Add custom patterns to deploy script if needed
- **Atomic swap** - Deploy uses renaming the target folder on the server for zero-downtime deployment
- **SSH key authentication** - Recommended for automated deployments without password prompts

---

## Database Management

### DB Backup (local)
```
powershell -ExecutionPolicy Bypass -File scripts/db_backup.ps1
```

Allow DROP statements in dump:
```
powershell -ExecutionPolicy Bypass -File scripts/db_backup.ps1 -AllowDrop
```

### DB Transfer + Import (safe default)
Uses latest `.sql` from `DB_BACKUP_DIR` unless `-DumpFile` is provided.
```
powershell -ExecutionPolicy Bypass -File scripts/db_transfer.ps1
```

Allow DROP statements:
```
powershell -ExecutionPolicy Bypass -File scripts/db_transfer.ps1 -AllowDrop
```

Dry run (no upload/import):
```
powershell -ExecutionPolicy Bypass -File scripts/db_transfer.ps1 -DryRun
```

---

## Cron Jobs for Background Workers

This section provides detailed cron job examples for all background workers in the BroxLab system.

### 1. AutoContent Worker (Web Scraping)

The main worker that collects articles from RSS feeds, JSON APIs, and HTML sources.

**PHP Script Location:** `scripts/autocontent_worker.php`

**What it does:**
- Collects articles from active sources (RSS, API, HTML)
- Performs duplicate checking by URL and title
- Stores collected articles in `autocontent_articles` table
- Supports Telegram notifications for new articles

#### cPanel Cron Job (every 15 minutes):
```
*/15 * * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/autocontent_worker.php >> /home/username/storage/logs/autocontent_worker.log 2>&1
```

#### Linux/macOS Cron (crontab -e):
```
# Every 15 minutes
*/15 * * * * /usr/bin/php /var/www/broxbhai/scripts/autocontent_worker.php >> /var/www/broxbhai/storage/logs/autocontent_worker.log 2>&1

# Or every hour (less resource intensive)
0 * * * * /usr/bin/php /var/www/broxbhai/scripts/autocontent_worker.php >> /var/www/broxbhai/storage/logs/autocontent_worker.log 2>&1
```

#### Windows Task Scheduler:
```xml
<!-- Run every 15 minutes -->
<trigger>
  <startBoundary>2024-01-01T00:00:00</startBoundary>
  <scheduleByMinute>
    <interval>15</interval>
  </scheduleByMinute>
</trigger>
<action>
  <exec>
    <command>php</command>
    <arguments>h:\Web\broxbhai\scripts\autocontent_worker.php</arguments>
  </exec>
</action>
```

### 2. AI Content Enhancement Worker

Enhances collected articles using AI providers (OpenRouter, OpenAI, etc.).

**PHP Script Location:** `scripts/ai_enhance_worker.php`

**What it does:**
- Processes collected articles with AI
- Applies style profiles (Professional, Viral, Formal, Friendly, Minimal)
- Generates AI-driven metadata (categories, tags)
- Updates article content in database

#### cPanel Cron Job (every 30 minutes):
```
*/30 * * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/ai_enhance_worker.php >> /home/username/storage/logs/ai_enhance_worker.log 2>&1
```

### 3. AutoPublisher Worker

Automatically schedules and publishes AI-enhanced content.

**PHP Script Location:** `scripts/autopublisher_worker.php`

**What it does:**
- Checks for scheduled articles ready to publish
- Applies AI-suggested taxonomy
- Publishes content to the site
- Sends notifications (Telegram, email)

#### cPanel Cron Job (every hour):
```
0 * * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/autopublisher_worker.php >> /home/username/storage/logs/autopublisher_worker.log 2>&1
```

### 4. Deploy Worker (Queue-based Deployment)

Processes deployment queue from the local UI.

**PHP Script Location:** `scripts/deploy_worker.php`

**What it does:**
- Reads queued deployment actions
- Executes file transfers
- Runs database migrations
- Logs deployment status

#### cPanel Cron Job (every 5 minutes):
```
*/5 * * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/deploy_worker.php >> /home/username/storage/logs/deploy_worker.log 2>&1
```

#### Windows Task Scheduler:
```cmd
php h:\Web\broxbhai\scripts\deploy_worker.php
```

### 5. Database Backup Worker

Automated database backups with retention policy.

**PHP Script Location:** `scripts/db_backup_worker.php`

**What it does:**
- Creates MySQL dumps
- Applies retention policy (keep last N backups)
- Optionally transfers backups to remote storage

#### cPanel Cron Job (daily at 2 AM):
```
0 2 * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/db_backup_worker.php >> /home/username/storage/logs/db_backup_worker.log 2>&1
```

### 6. Quality Scanner

Code quality analysis and reporting.

**PHP Script Location:** `scripts/quality_scan.php`

#### cPanel Cron Job (daily at 3 AM):
```
0 3 * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/quality_scan.php >> /home/username/storage/logs/quality_scan.log 2>&1
```

### 7. Security Scanner

Security vulnerability detection.

**PHP Script Location:** `scripts/security_scan.php`

#### cPanel Cron Job (weekly on Sunday at 4 AM):
```
0 4 * * 0 /usr/local/bin/php /home/username/public_html/broxbhai/scripts/security_scan.php >> /home/username/storage/logs/security_scan.log 2>&1
```

### 8. Performance Reporter

Performance metrics and reporting.

**PHP Script Location:** `scripts/perf_report.php`

#### cPanel Cron Job (daily at 5 AM):
```
0 5 * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/perf_report.php >> /home/username/storage/logs/perf_report.log 2>&1
```

### Complete Cron Schedule Example

Here's a comprehensive cron setup for a production environment:

```
# ========================
# BROXBHAI CRON SCHEDULE
# ========================

# AutoContent - Collect articles every 15 minutes
*/15 * * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/autocontent_worker.php >> /home/username/storage/logs/autocontent_worker.log 2>&1

# AI Enhancement - Process articles every 30 minutes
*/30 * * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/ai_enhance_worker.php >> /home/username/storage/logs/ai_enhance_worker.log 2>&1

# AutoPublisher - Publish scheduled content every hour
0 * * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/autopublisher_worker.php >> /home/username/storage/logs/autopublisher_worker.log 2>&1

# Deploy Worker - Check queue every 5 minutes
*/5 * * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/deploy_worker.php >> /home/username/storage/logs/deploy_worker.log 2>&1

# Database Backup - Daily at 2 AM
0 2 * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/db_backup_worker.php >> /home/username/storage/logs/db_backup_worker.log 2>&1

# Quality Scan - Daily at 3 AM
0 3 * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/quality_scan.php >> /home/username/storage/logs/quality_scan.log 2>&1

# Security Scan - Weekly on Sunday at 4 AM
0 4 * * 0 /usr/local/bin/php /home/username/public_html/broxbhai/scripts/security_scan.php >> /home/username/storage/logs/security_scan.log 2>&1

# Performance Report - Daily at 5 AM
0 5 * * * /usr/local/bin/php /home/username/public_html/broxbhai/scripts/perf_report.php >> /home/username/storage/logs/perf_report.log 2>&1
```

### Monitoring Cron Jobs

Check if cron jobs are running:
```bash
# View recent cron logs
tail -f /home/username/storage/logs/autocontent_worker.log

# Check running processes
ps aux | grep php

# Verify cron is running
service cron status   # Linux
```

---

## Home/Office Workflow

1. Work on either machine.
2. Push to the central Git remote (origin).
3. On the deploy machine, `git pull` then run deploy script.
4. See `docs/git.md` for safe terminal push/pull steps.

---

## Local Deploy UI (Queue + Status)

The deploy UI is **local-only** and requires `APP_ENV=development`.
Open:
```
/admin/deploy-tools
```

### Features
- **Queue Jobs** - Queue deploy and DB backup jobs
- **Recent Jobs** - View job status and logs
- **Live File Tree** - Real-time view of project files

### Live File Tree
The File Tree tab shows a live view of your project directory structure:

- **Refresh** - Manually refresh the file tree
- **Auto Refresh** - Toggle auto-refresh (updates every 5 seconds)
- **Lazy Loading** - Folders load on-demand when clicked
- **File Info** - Shows file sizes and colors by type

Security: Hidden folders (.git, node_modules, vendor, etc.) are automatically excluded.

Queue actions and run the worker locally:
```
php scripts/deploy_worker.php
```

### Cron / Task Scheduler example
Windows Task Scheduler (every 5 minutes):
```
php h:\Web\broxbhai\scripts\deploy_worker.php
```

---

## Quality/Security/Performance Reports (Phase 2)

Generate reports locally or on server (shared hosting compatible):
```
php scripts/quality_scan.php
php scripts/security_scan.php
php scripts/perf_report.php
```
Reports are written to `storage/logs/*.json` and `storage/logs/*.md`.

### Cron example (shared hosting)
```
php /path/to/broxbhai/scripts/quality_scan.php
php /path/to/broxbhai/scripts/security_scan.php
php /path/to/broxbhai/scripts/perf_report.php
```

---

## Troubleshooting

### Common Deployment Issues

#### SSH Connection Failed
```
Error: Permission denied (publickey)
```
**Solution:**
1. Verify SSH key is added to agent: `ssh-add ~/.ssh/id_rsa`
2. Check key permissions: `chmod 600 ~/.ssh/id_rsa`
3. Verify public key is on server: `cat ~/.ssh/id_rsa.pub`

#### Database Import Failed
```
Error: Unknown database 'your_db'
```
**Solution:**
1. Create the database first in cPanel MySQL Databases
2. Ensure DB_USER has privileges: `GRANT ALL ON your_db.* TO 'your_user'@'localhost';`
3. Run: `mysql -u your_user -p your_db < backup.sql`

#### Deploy Script Timeout
```
Error: Operation timed out
```
**Solution:**
1. Increase PHP max_execution_time in php.ini
2. Use `--skip-db` flag to deploy files only, then transfer DB separately
3. Split large uploads into smaller batches

#### Cron Jobs Not Running
```
Logs show no new entries
```
**Solution:**
1. Verify cron is enabled in cPanel
2. Check full path to PHP: `which php` or `which php80`
3. Test script directly: `/usr/local/bin/php /home/username/script.php`
4. Check error logs in cPanel > Errors

#### File Permission Issues
```
Error: Permission denied
```
**Solution:**
1. Set correct ownership: `chown -R username:username /home/username/public_html/broxbhai`
2. Set correct permissions: `find /home/username/public_html/broxbhai -type f -exec chmod 644 {} \;`
3. For directories: `find /home/username/public_html/broxbhai -type d -exec chmod 755 {} \;`

#### Out of Memory Errors
```
Error: Allowed memory size of X bytes exhausted
```
**Solution:**
1. Increase memory_limit in php.ini: `memory_limit = 512M`
2. Or add to script: `ini_set('memory_limit', '512M');`
3. For large DB imports, use command line instead of web UI

#### Duplicate Articles Not Being Filtered
```
Database has duplicate URLs or titles
```
**Solution:**
1. Check AUTOCONTENT_DEDUP_SIMILARITY setting (default: 0.8)
2. Run duplicate cleanup: Check `autocontent_articles` table for duplicates
3. Manually delete duplicates or adjust similarity threshold

### Debug Mode

Enable debug logging for workers:
```php
// In your PHP script or .env
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
```

Check logs:
```bash
# AutoContent logs
tail -f storage/logs/autocontent_worker.log

# AI Enhancement logs
tail -f storage/logs/ai_enhance_worker.log

# Deploy logs
tail -f storage/logs/deploy_worker.log
```

### Health Check Endpoints

After deployment, verify system health:

```
# Check database connection
/admin/db-tools

# Check scraper status
SELECT * FROM autocontent_sources WHERE status = 'active';

# Check recent articles
SELECT * FROM autocontent_articles ORDER BY created_at DESC LIMIT 10;

# Check AI usage
SELECT * FROM ai_usage_logs ORDER BY created_at DESC LIMIT 10;
```
