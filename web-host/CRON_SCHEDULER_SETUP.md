# BroxLab Cron & Task Scheduler Setup

**Hosting Type**: cPanel Shared Hosting  
**Base Path**: `/home/tdhuedhn/broxlab`  
**Last Updated**: April 26, 2026

---

## 📋 Overview

This document contains exact cron lines and Task Scheduler commands for BroxLab production environment. Choose the appropriate commands based on your hosting setup.

---

## 🐧 Cron Job Setup (Linux/Unix - cPanel)

### How to Add Cron Jobs in cPanel

1. **Login to cPanel**
2. Navigate to **Cron Jobs** (under Advanced section)
3. Set **Email:** (optional, for notifications)
4. Copy and paste the exact cron line from below
5. Click **Add New Cron Job**

---

## 🔄 Recommended Cron Jobs

### 1. **Daily Storage & Logs Cleanup** (Recommended)
**Purpose**: Auto-clean old releases, backups, logs, and temp files  
**Frequency**: Once daily at 2 AM

#### Option A: Using Bash Script (Recommended for shared hosting)
```bash
0 2 * * * /home/tdhuedhn/broxlab/web-host/scripts/cleanup.sh >> /home/tdhuedhn/broxlab/logs/cron.cleanup.log 2>&1
```

**Alternative** - With aggressive retention for critical disk space:
```bash
0 2 * * * /home/tdhuedhn/broxlab/web-host/scripts/cleanup.sh --releases 2 --backups 3 --db-backups 3 --logs-days 14 >> /home/tdhuedhn/broxlab/logs/cron.cleanup.log 2>&1
```

#### Option B: Using PHP (Also cleans storage/ directory files)
```bash
```

**Note**: Option B also automatically cleans old files in `storage/logs`, `storage/cache`, `storage/temp` directories based on retention policies.

---

### 2. **Weekly Database Backup** (Recommended)
**Purpose**: Create database backup for disaster recovery  
**Frequency**: Every Sunday at 3 AM

```bash
0 3 * * 0 /home/tdhuedhn/broxlab/web-host/scripts/database-backup.sh >> /home/tdhuedhn/broxlab/logs/cron.db-backup.log 2>&1
```

**Alternative** - Keep 10 backups:
```bash
0 3 * * 0 /home/tdhuedhn/broxlab/web-host/scripts/database-backup.sh --keep 10 >> /home/tdhuedhn/broxlab/logs/cron.db-backup.log 2>&1
```

---

### 3. **Daily Code Backup** (Optional - for critical deployments)
**Purpose**: Create code snapshot for quick rollback  
**Frequency**: Every day at 4 AM

```bash
0 4 * * * /home/tdhuedhn/broxlab/web-host/scripts/backup.sh --keep 5 >> /home/tdhuedhn/broxlab/logs/cron.code-backup.log 2>&1
```

---

### 4. **Storage Status Check** (Optional - for monitoring)
**Purpose**: Log disk usage for monitoring  
**Frequency**: Every 12 hours (Noon & Midnight)

```bash
0 0,12 * * * df -h /home/tdhuedhn/ > /home/tdhuedhn/broxlab/logs/disk-usage.log 2>&1
```

---

## ⏱️ Cron Time Format Reference

```
┌───────────── minute (0 - 59)
│ ┌───────────── hour (0 - 23)
│ │ ┌───────────── day of month (1 - 31)
│ │ │ ┌───────────── month (1 - 12)
│ │ │ │ ┌───────────── day of week (0 - 6) (Sunday to Saturday)
│ │ │ │ │
│ │ │ │ │
* * * * *
```

### Common Examples:
- `0 2 * * *` - Daily at 2:00 AM
- `0 3 * * 0` - Every Sunday at 3:00 AM
- `0 0,12 * * *` - Every day at Midnight and Noon
- `*/30 * * * *` - Every 30 minutes
- `0 */6 * * *` - Every 6 hours
- `0 0 1 * *` - First day of month at Midnight

---

## 🪟 Windows Task Scheduler Setup

If you're running BroxLab on Windows (not recommended for production):

### 1. Open Task Scheduler
- Press `Win + R` → Type `taskschd.msc` → Enter

### 2. Create Basic Task
1. Right-click **Task Scheduler Library** → **Create Basic Task**
2. **Name**: `BroxLab Storage Cleanup`
3. **Description**: Auto cleanup old releases and logs
4. **Trigger**: Daily at 2:00 AM
5. **Action**: Start a program

### 3. Action Configuration

**Program/script**:
```
C:\Windows\System32\cmd.exe
```

**Add arguments**:
```
/c bash.exe -c "cd /home/tdhuedhn/broxlab && ./web-host/scripts/cleanup.sh"
```

**Note**: Requires Git Bash or WSL to run bash scripts

---

## 🔐 Important: Permissions

Ensure scripts have execute permissions:

```bash
chmod +x /home/tdhuedhn/broxlab/web-host/scripts/*.sh
```

---

## 📊 Monitoring Cron Jobs

### View Cron Execution Logs
```bash
# View cleanup log
tail -f /home/tdhuedhn/broxlab/logs/cron.cleanup.log

# View database backup log
tail -f /home/tdhuedhn/broxlab/logs/cron.db-backup.log

# View disk usage log
tail -f /home/tdhuedhn/broxlab/logs/disk-usage.log
```

### Check Cron Job History (cPanel)
1. Login to cPanel
2. Go to **Cron Jobs**
3. Check the **Standard Output** section (if enabled)

### Email Notifications
cPanel will email you execution output if you set an email address in Cron Jobs settings.

---

## 🚨 Troubleshooting

### Script Not Running
1. **Check permissions**: `ls -la /home/tdhuedhn/broxlab/web-host/scripts/`
2. **Test script manually**: `/home/tdhuedhn/broxlab/web-host/scripts/cleanup.sh --dry-run`
3. **Check cron syntax**: Use [crontab.guru](https://crontab.guru/)

### Disk Still Filling Up
1. **Run cleanup immediately**: `./cleanup.sh`
2. **Check what's using space**: `du -sh /home/tdhuedhn/broxlab/*`
3. **Use aggressive cleanup**: `./cleanup.sh --releases 2 --backups 2 --db-backups 2 --logs-days 7`

### Script Timeout
If cleanup script times out after 1 hour:
1. Run during off-peak hours
2. Reduce `KEEP_RELEASES` and `KEEP_BACKUPS`
3. Split into separate cron jobs

---

## 📝 Recommended Cron Schedule (Summary)

| Job | Time | Frequency | Command |
|-----|------|-----------|---------|
| **Storage Cleanup** | 2:00 AM | Daily | `0 2 * * *` |
| **DB Backup** | 3:00 AM | Weekly (Sun) | `0 3 * * 0` |
| **Code Backup** | 4:00 AM | Daily | `0 4 * * *` |
| **Disk Monitor** | Noon & Midnight | Twice daily | `0 0,12 * * *` |

---

## ✅ Verification Checklist

- [ ] Created cron jobs in cPanel
- [ ] Scripts have execute permissions (`chmod +x`)
- [ ] Log directory exists: `/home/tdhuedhn/broxlab/logs/`
- [ ] Tested script with `--dry-run` flag
- [ ] Verified first cron execution in logs
- [ ] Set up email notifications (optional)
- [ ] Monitored disk space weekly

---

**Last Verified**: April 26, 2026  
**Status**: Production Ready ✅
