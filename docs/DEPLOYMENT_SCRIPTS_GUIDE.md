# BroxBhai Deployment Scripts Guide

## Overview

The deployment automation consists of 4 production-ready bash scripts that work together to provide a robust, reliable, and recoverable deployment system.

**Location:** `/web-host/scripts/`

| Script | Purpose | Trigger |
|--------|---------|---------|
| `deploy.sh` | Main deployment orchestrator | GitHub Actions on push/release |
| `backup.sh` | Create compressed releases backup | Called by deploy.sh before symlink switch |
| `cleanup.sh` | Remove old releases, backups, logs | Called by deploy.sh post-deployment |
| `rollback.sh` | Emergency recovery to previous release | Manual (SSH command) |

---

## Architecture

### Deployment Flow

```
GitHub Actions (appleboy/ssh-action@v1)
    ↓
deploy.sh (Main Orchestrator)
    ├─ 1. Check disk space (8GB minimum)
    ├─ 2. Clean old releases (keep latest 3)
    ├─ 3. Clone repository to new release directory
    ├─ 4. Link shared persistent files (.env, storage, etc.)
    ├─ 5. Install dependencies (PHP, Node.js, Python)
    ├─ 6. Build assets (npm run build)
    ├─ 7. Setup Python/RAG environment (non-blocking)
    ├─ 8. Auto-increment semantic version
    ├─ 9. Call backup.sh (safety backup before switch)
    ├─ 10. Switch symlink (current → new release)
    ├─ 11. Call cleanup.sh (post-deployment cleanup)
    └─ 12. Display deployment summary
```

### Directory Structure

```
/home/tdhuedhn/broxlab/
├── app/
│   ├── current → releases/20240101_120000 (symlink to active)
│   ├── releases/
│   │   ├── 20240101_120000/ (kept - latest 1)
│   │   ├── 20240101_110000/ (kept - latest 2)
│   │   ├── 20240101_100000/ (kept - latest 3)
│   │   └── (older releases auto-deleted)
│   └── shared/ (persistent across releases)
│       ├── .env (environment variables)
│       ├── storage/ (user uploads, cache, logs)
│       ├── logs/ (application logs)
│       ├── version.json (semantic version tracking)
│       └── deployment.log (deployment history)
├── backups/ (release snapshots)
│   ├── backup_20240101_120000.tar.gz (kept - latest 1)
│   ├── backup_20240101_110000.tar.gz (kept - latest 2)
│   └── (older than top 10 auto-deleted)
└── logs/ (script execution logs)
    ├── backup_20240101_120000.log
    ├── cleanup.log
    └── rollback.log
```

---

## Script Details

### 1. deploy.sh (Main Orchestrator)

**Purpose:** Automated deployment triggered by GitHub Actions on each push.

**Key Features:**
- ✅ Disk space validation (8GB minimum, auto-cleanup on low space)
- ✅ Atomic release directory creation with git clone
- ✅ Dependency installation (Composer, npm with `--legacy-peer-deps`, Python)
- ✅ Asset building (esbuild, Tailwind, postcss)
- ✅ Non-blocking Python/RAG setup (deployment continues if Python fails)
- ✅ Semantic version auto-increment (v1.0.0 → v1.0.1 → ...)
- ✅ Safety backup before symlink switch
- ✅ Post-deployment cleanup integration
- ✅ Comprehensive deployment summary with next steps

**Called by:** GitHub Actions (automatic)

**Integrations:**
- Calls `backup.sh` before switching symlink
- Calls `cleanup.sh` after deployment
- Updates `version.json` with new version and timestamp

**Error Handling:**
- Exits on critical failures (disk space, git clone, dependency installation)
- Warns on non-critical failures (Python setup, RAG installation)
- Continues deployment despite optional component failures

**Exit Codes:**
- `0` = Success
- `1` = Critical failure (disk space, git clone, PHP/npm install)

---

### 2. backup.sh (Safety Backup)

**Purpose:** Creates compressed backup of current release before deployment switches traffic to new release.

**Key Features:**
- ✅ Validates current release symlink exists and is healthy
- ✅ Disk space check (requires 2x current size for compression + swap)
- ✅ Excludes unnecessary files (node_modules, vendor, cache, .git, .env)
- ✅ Gzip compression (tar czf)
- ✅ Automatic old backup cleanup (keeps latest 10 backups)
- ✅ Comprehensive logging to `$LOGS/backup_$DATE.log`
- ✅ Size reporting on successful backup

**Called by:** `deploy.sh` (before symlink switch)

**Backup Retention:** Latest 10 backups (older backups auto-deleted)

**Backup File Location:** `/home/tdhuedhn/broxlab/backups/backup_YYYYMMDD_HHMMSS.tar.gz`

**Error Handling:**
- Warns if low disk space, auto-cleans old backups
- Exits with error if current release invalid
- Exits with error if backup creation fails

**Use Cases:**
- Safety net before every deployment
- Quick restore if new release proves problematic
- Version history and audit trail

---

### 3. cleanup.sh (Maintenance & Maintenance)

**Purpose:** Removes old releases, backups, and log files to manage disk space.

**Key Features:**
- ✅ **Releases cleanup:** Keeps latest 3 releases (consistent with deploy.sh)
- ✅ **Backups cleanup:** Keeps latest 10 releases backups
- ✅ **Logs cleanup:** Deletes logs older than 30 days
- ✅ Current disk usage reporting (releases, backups, shared)
- ✅ Comprehensive logging to `$LOGS/cleanup.log`
- ✅ Safe to run multiple times (idempotent)

**Called by:** `deploy.sh` (post-deployment)

**Retention Policy:**
- **Releases:** 3 (disk-efficient while enabling 3-step rollback history)
- **Backups:** 10 (6-8 days of backup history at typical deployment frequency)
- **Logs:** 30 days (1 month of historical data)

**Cleanup Schedule:**
- Automatically after each deployment (via deploy.sh)
- Can be manually run: `bash /home/tdhuedhn/broxlab/scripts/cleanup.sh`
- Optional: Add to cron for daily maintenance `0 2 * * * /home/tdhuedhn/broxlab/scripts/cleanup.sh`

**Error Handling:**
- Non-blocking (warns if directories don't exist)
- Continues cleanup even if individual operations fail
- Logs all operations for audit trail

---

### 4. rollback.sh (Emergency Recovery)

**Purpose:** Safely switch traffic back to previous release in case of issues with current deployment.

**Key Features:**
- ✅ Comprehensive validations (directories exist, symlink valid, etc.)
- ✅ Finds and validates previous release (2nd most recent)
- ✅ Health check on previous release (checks for public_html)
- ✅ Auto-backup of current state before rollback (safety net)
- ✅ Atomic symlink switch
- ✅ Verification that symlink switch succeeded
- ✅ Automatic version.json update with rollback info
- ✅ Clear instructions and logging to `$LOGS/rollback.log`

**Called by:** Manual SSH command (emergency)

**Usage:**
```bash
# SSH into server
ssh tdhuedhn@65.21.174.100

# Run rollback script
/home/tdhuedhn/broxlab/scripts/rollback.sh
```

**Rollback Process:**
1. Find previous release (2nd most recent date in releases/)
2. Validate it's healthy (public_html directory exists)
3. Create safety backup of current release before rolling back
4. Switch current symlink to previous release
5. Verify symlink switch succeeded
6. Update version.json with rollback timestamp
7. Output confirmation with next-step instructions

**Error Handling:**
- Validates requirements before any modifications
- Creates backup before switching (safety net)
- Verifies symlink switch succeeded
- Logs all operations with timestamps
- Returns meaningful error codes

**Rollback Limitations:**
- Can only rollback to previous release (2nd most recent)
- Requires previous release still exists (not cleaned up)
- May need to run multiple times for multi-version rollback

**Recovery from Multiple Versions Back:**
```bash
# Manual recovery (if 2 rollbacks needed):
/home/tdhuedhn/broxlab/scripts/rollback.sh   # Back to (n-1)
/home/tdhuedhn/broxlab/scripts/rollback.sh   # Back to (n-2)
```

---

## Deployment Workflow

### Automatic Deployment (GitHub Actions)

**Trigger:** Push to `feature/ai-rag-system` branch

**GitHub Actions Workflow:**
```yaml
- uses: appleboy/ssh-action@v1
  with:
    host: 65.21.174.100
    username: tdhuedhn
    key: ${{ secrets.SSH_PRIVATE_KEY }}
    script: |
      cd /home/tdhuedhn/broxlab
      /home/tdhuedhn/broxlab/scripts/deploy.sh
```

**Output:**
- Detailed logs in GitHub Actions UI
- Deployment summary with version and symlink info
- Script execution logs on server: `/home/tdhuedhn/broxlab/logs/`

### Manual Emergency Rollback (SSH)

**Steps:**
```bash
# 1. SSH into production server
ssh tdhuedhn@65.21.174.100

# 2. Go to deployment directory
cd /home/tdhuedhn/broxlab

# 3. Run rollback script
./scripts/rollback.sh

# 4. Monitor application (check logs, status endpoints)
tail -f app/shared/logs/error.log
tail -f app/shared/logs/access.log

# 5. Verify application is healthy
# - Check website loads
# - Verify API endpoints respond
# - Check for error patterns in logs
```

---

## Monitoring & Troubleshooting

### Check Deployment Status

```bash
# SSH into server
ssh tdhuedhn@65.21.174.100

# Check current release
readlink /home/tdhuedhn/broxlab/app/current

# Check version
cat /home/tdhuedhn/broxlab/app/shared/version.json

# View recent deployments
tail -20 /home/tdhuedhn/broxlab/app/shared/deployment.log
```

### View Deployment Logs

```bash
# Latest deployment log
cat /home/tdhuedhn/broxlab/logs/deploy_*.log

# Latest backup log
cat /home/tdhuedhn/broxlab/logs/backup_*.log

# Latest cleanup log
tail -50 /home/tdhuedhn/broxlab/logs/cleanup.log

# Rollback operations log (if applicable)
tail -50 /home/tdhuedhn/broxlab/logs/rollback.log
```

### Disk Space Management

```bash
# Check disk usage
du -sh /home/tdhuedhn/broxlab/app/releases/
du -sh /home/tdhuedhn/broxlab/backups/
du -sh /home/tdhuedhn/broxlab/app/shared/

# Manual cleanup if needed (runs automatically after deploy)
/home/tdhuedhn/broxlab/scripts/cleanup.sh

# Check available disk space
df -h /home/tdhuedhn/broxlab/
```

### Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| Deployment fails early | Low disk space | Run cleanup.sh, then retry deploy |
| Deployment fails on npm | Peer dependency mismatch | Already handled via `--legacy-peer-deps` flag |
| Deployment fails on Python | Python/RAG system issue | Non-blocking - deployment continues anyway |
| Rollback shows broken links | Old releases cleaned up | Restore from backup or retry from previous version |
| Symlink pointing to wrong release | Manual corruption | Use rollback.sh to fix |

---

## Version Tracking

### version.json Format

Located: `/home/tdhuedhn/broxlab/app/shared/version.json`

```json
{
    "version": "v1.0.5",
    "deployed_at": "20240101_120000",
    "last_action": "Rolled back from v1.0.4 to v1.0.3 at 2024-01-01 12:05:00",
    "backup": "",
    "migrations": [],
    "history": []
}
```

**Fields:**
- `version`: Current semantic version (auto-incremented by deploy.sh)
- `deployed_at`: Timestamp of latest deployment
- `last_action`: Recent action (deployment, rollback, etc.)
- `history`: (Optional) Array of past deployments for audit trail

### Semantic Versioning

Deploy.sh auto-increments the patch version:
- v1.0.0 → v1.0.1 → v1.0.2 → ...

**To manually change major/minor:**
```bash
# SSH into server
ssh tdhuedhn@65.21.174.100

# Edit version.json
nano /home/tdhuedhn/broxlab/app/shared/version.json

# Update version field manually, then next deploy will increment from there
```

---

## Production Readiness Checklist

Before running on production, verify:

- [ ] All scripts have executable permissions: `chmod +x *.sh`
- [ ] Directory structure created: `/home/tdhuedhn/broxlab/{app,backups,logs}/`
- [ ] Shared directory initialized: `/home/tdhuedhn/broxlab/app/shared/` (with .env, etc.)
- [ ] SSH key configured for GitHub SSH access
- [ ] NVM installed on server (for Node.js 20)
- [ ] Composer available globally or in path
- [ ] git update-index --chmod done for all .sh files (Unix line endings)
- [ ] First deployment run successfully
- [ ] Rollback tested (verify it switches to previous release)
- [ ] Backup/restore tested (verify backup can be restored if needed)
- [ ] Monitoring/alerting configured (optional but recommended)

---

## Maintenance Tasks

### Daily (Optional, via cron)

```bash
# 0 2 * * * /home/tdhuedhn/broxlab/scripts/cleanup.sh
```

Run cleanup daily to remove old logs and keep disk health.

### Weekly

- Monitor deployment logs for errors/warnings
- Check disk usage trends
- Verify backups are working

### Monthly

- Review rollback needs (if any)
- Verify version history tracking
- Check for any stale releases directories

---

## Integration with GitHub Actions

Deploy.sh is automatically called by GitHub Actions via `appleboy/ssh-action@v1`:

**Workflow File:** `.github/workflows/deploy.yml` (or similar)

**Key Environment Variables:**
- `SSH_HOST`: 65.21.174.100
- `SSH_USER`: tdhuedhn
- `SSH_PRIVATE_KEY`: GitHub secret

**Deployment Trigger:**
- On push to main branch (or configured branch)
- Optional: Manual trigger via workflow dispatch

---

## Future Enhancements

Potential improvements for future iterations:

1. **Blue-Green Testing**: Run health checks before switching symlink
2. **Smoke Tests**: Run basic API tests post-deployment to catch issues early
3. **Slack/Discord Notifications**: Alert team of deployments and issues
4. **Canary Deployments**: Route 5-10% traffic to new release before full switch
5. **Automatic Rollback**: Rollback if health checks fail
6. **Database Migrations**: Integrate run-once migration scripts
7. **Analytics**: Track deployment frequency, success rate, rollback rate

---

## Support & Questions

For issues or questions:

1. Check logs in `/home/tdhuedhn/broxlab/logs/`
2. Review deployment history in `app/shared/deployment.log`
3. Check GitHub Actions logs for SSH command output
4. SSH into server and manually run scripts with debug output

---

**Last Updated:** 2024-01-17
**Deployment Script Version:** 2.0 (Production Ready)
