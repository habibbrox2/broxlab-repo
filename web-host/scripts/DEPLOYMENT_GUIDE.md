# BroxLab Production Deployment Guide

**Last Updated**: April 21, 2026  
**Status**: Production Ready ✅

---

## 📋 Overview

All deployment scripts have been enhanced for production use with comprehensive error handling, validation, locking mechanisms, and safety checks.

## 🔧 Updated Scripts

### 1. **backup.sh** - Code Backup Script
**Purpose**: Creates compressed backups of current release  
**Enhancements**:
- ✅ Lock file mechanism to prevent concurrent backups
- ✅ Disk space validation before backup
- ✅ Improved error handling and logging
- ✅ Auto-cleanup of old backups with `--keep` option
- ✅ Dry-run mode for testing

**Usage**:
```bash
# Standard backup
./backup.sh

# Backup with specific retention
./backup.sh --keep 15

# Dry-run (preview what would happen)
./backup.sh --dry-run

# Custom base path
./backup.sh --base /path/to/broxlab
```

---

### 2. **cleanup.sh** - Release & Backup Cleanup
**Purpose**: Safely removes old releases, code backups, database backups, and logs  
**Enhancements**:
- ✅ Separate retention policies for each artifact type
- ✅ Enhanced logging with deletion tracking
- ✅ Improved error handling for permission issues
- ✅ Dry-run mode for safety testing
- ✅ Better debug output for troubleshooting

**Usage**:
```bash
# Standard cleanup
./cleanup.sh

# Custom retention policies
./cleanup.sh --releases 5 --backups 15 --db-backups 10 --logs-days 60

# Dry-run test
./cleanup.sh --dry-run

# View what would be deleted
./cleanup.sh --dry-run --releases 3
```

**Retention Defaults**:
- Releases: 3
- Code backups: 10
- Database backups: 10
- Log files: 30 days

---

### 3. **database-backup.sh** - Database Backup
**Purpose**: Creates MySQL database backup with compression  
**Enhancements**:
- ✅ Timeout protection (1 hour max)
- ✅ Temporary file handling with cleanup on exit
- ✅ Atomic file operations (temp → final)
- ✅ Backup integrity validation
- ✅ Enhanced environment variable parsing

**Usage**:
```bash
# Standard database backup
./database-backup.sh

# Custom keep count
./database-backup.sh --keep 20

# Dry-run mode
./database-backup.sh --dry-run
```

**Requirements**:
- `mysqldump` command available
- `.env` file with DB_HOST, DB_USER, DB_PASS, DB_NAME
- 2GB free disk space (for temp operations)

---

### 4. **database-restore.sh** - Database Restore
**Purpose**: Restores MySQL database from compressed backup  
**Enhancements**:
- ✅ Automatic safety backup before restore
- ✅ Backup file integrity validation
- ✅ Compression verification
- ✅ Interactive confirmation with specific format
- ✅ Detailed restore logging

**Usage**:
```bash
# Restore latest backup automatically
./database-restore.sh

# Restore specific backup
./database-restore.sh /path/to/backup.sql.gz

# Just verify backup integrity (no restore)
gzip -t /path/to/backup.sql.gz
```

**Safety Features**:
- Creates pre-restore safety backup
- Validates backup format before restore
- Requires interactive confirmation
- Logs all restore operations

---

### 5. **deploy.sh** - Main Deployment Script
**Purpose**: Complete deployment workflow with validation and rollback safety  
**Enhancements**:
- ✅ Deployment lock mechanism (prevents concurrent deploys)
- ✅ Pre-deployment validation (disk space, required commands)
- ✅ Environment variable validation
- ✅ Comprehensive section logging
- ✅ Health check integration
- ✅ Atomic operations with cleanup
- ✅ Version tracking and management
- ✅ Node server health verification

**Usage**:
```bash
# Standard deployment
./deploy.sh

# Skip backups for faster deployment
./deploy.sh --skip-backup --skip-db-backup

# Skip asset build
./deploy.sh --skip-build

# Custom release retention
./deploy.sh --keep 10

# Start with custom Node environment
NODE_ENV=staging ./deploy.sh
```

**Deployment Workflow**:
1. Acquire deployment lock
2. Pre-deployment validation
3. Database backup (optional)
4. Code backup (optional)
5. Clone repository
6. Link shared resources
7. Install dependencies
8. Build assets
9. Validate PHP syntax
10. Update version info
11. Stop old Node server
12. Update symlink to new release
13. Start Node server
14. Verify health check
15. Release deployment lock

**Deployment Stages** (logged with timestamps):
- Pre-deployment validation
- Release fetching
- Shared resource linking
- Dependency installation
- Asset building
- PHP validation
- Version management
- Server restart
- Health checks

---

### 6. **rollback.sh** - Rollback Script
**Purpose**: Safely rollback to previous release  
**Enhancements**:
- ✅ Rollback lock mechanism
- ✅ Previous release detection
- ✅ Interactive confirmation (type 'ROLLBACK')
- ✅ Automatic safety backup
- ✅ Database restore integration
- ✅ Node server restart with health check
- ✅ Version file updates

**Usage**:
```bash
# Interactive rollback
./rollback.sh

# Skip database restore
./rollback.sh --skip-db-restore

# Skip safety backup
./rollback.sh --no-backup

# Don't restart Node server
./rollback.sh --no-node-start
```

**Rollback Workflow**:
1. Acquire rollback lock
2. Find current and previous releases
3. Interactive confirmation
4. Create safety backup of current release
5. Restore database (if applicable)
6. Stop current Node server
7. Update symlink to previous release
8. Link shared resources
9. Start Node server
10. Verify health check
11. Update version info
12. Release rollback lock

**Safety Features**:
- Requires interactive confirmation
- Creates safety backup before rollback
- Optional database restore
- Health verification after restart

---

## 🔒 Lock Mechanisms

All scripts use lock files to prevent concurrent operations:

| Script | Lock File | Location | Timeout |
|--------|-----------|----------|---------|
| backup.sh | `.backup.lock` | `$SHARED` | None |
| deploy.sh | `.deploy.lock` | `$SHARED` | 2 hours |
| rollback.sh | `.rollback.lock` | `$SHARED` | 1 hour |

**Lock Format**: `$PID:$TIMESTAMP`

---

## 📊 Logging

All scripts produce detailed logs in `$BASE/logs/`:

```
logs/
├── backup_20260421_120000.log
├── cleanup.log
├── database-backup_20260421_120000.log
├── database-restore_20260421_120000.log
├── deploy_20260421_120000.log
├── node-server_20260421_120000.log
└── rollback.log
```

### Log Levels

- `[INFO]` - Green: Informational messages
- `[WARN]` - Yellow: Warnings that don't stop execution
- `[ERROR]` - Red: Critical errors
- `[DEBUG]` - Blue: Debug information (detailed for troubleshooting)

### Log Format
```
[LEVEL] YYYY-MM-DD HH:MM:SS - Message
```

---

## 🚀 Typical Deployment Sequence

### Full Deployment (Production)
```bash
# 1. Create backups
./database-backup.sh
./backup.sh

# 2. Deploy new release
./deploy.sh

# 3. Verify deployment
curl http://localhost:3000/health

# 4. Cleanup old releases
./cleanup.sh --releases 5
```

### Quick Deployment (Hotfix)
```bash
# Skip backups for speed
./deploy.sh --skip-backup --skip-db-backup

# Verify
curl http://localhost:3000/health
```

### Rollback If Issues
```bash
# Interactive rollback (requires confirmation)
./rollback.sh

# Verify
curl http://localhost:3000/health
```

---

## ⚠️ Pre-Deployment Checklist

- [ ] All changes committed to Git
- [ ] `.env` file updated in shared storage
- [ ] Database connection credentials correct
- [ ] At least 2GB free disk space
- [ ] Git, Node.js, npm, PHP installed
- [ ] Composer installed or available
- [ ] MySQL server accessible
- [ ] Log directory writable
- [ ] Release directory permissions correct

---

## 🔍 Environment Variables

### Deployment Configuration
```bash
# Base deployment path
BASE_PATH=/home/tdhuedhn/broxlab

# Git repository
GIT_REPO=git@github.com:habibbrox2/broxlab-repo.git

# Node environment
NODE_ENV=production

# Node health check URL
NODE_HEALTH_URL=http://127.0.0.1:3000/health

# Keep counts
BACKUP_KEEP=10
KEEP_RELEASES=3
KEEP_BACKUPS=10
KEEP_DB_BACKUPS=10
KEEP_LOGS_DAYS=30
```

### Database Configuration (.env)
```
DB_HOST=localhost
DB_USER=root
DB_PASS=password
DB_NAME=broxlab
```

---

## 🛠️ Troubleshooting

### Deployment Stuck (Lock File Issue)
```bash
# Check active lock
cat $SHARED/.deploy.lock

# Remove stale lock (only if you're sure process isn't running)
rm -f $SHARED/.deploy.lock
```

### Node Server Won't Start
```bash
# Check latest Node logs
tail -100 logs/node-server_*.log

# Check if port 3000 is in use
lsof -i :3000

# Check health manually
curl http://127.0.0.1:3000/health
```

### Database Backup Failed
```bash
# Verify MySQL connectivity
mysql -h $DB_HOST -u $DB_USER -p -e "SELECT 1"

# Check .env file
cat $SHARED/.env | grep DB_

# Verify disk space
df -h $SHARED
```

### Insufficient Disk Space
```bash
# Check disk usage
df -h

# Clean old releases
./cleanup.sh --releases 2 --backups 5

# Remove old logs
find logs/ -name "*.log" -mtime +30 -delete
```

---

## 📝 Version Management

Versions are tracked in `$SHARED/version.json`:

```json
{
  "version": "v1.0.5",
  "release": "20260421_120000",
  "deployed_at": "2026-04-21T12:00:00Z",
  "previous": "v1.0.4",
  "git_commit": "abc123def456"
}
```

Auto-incremented on each deployment:
- **Patch**: Hotfixes
- **Minor**: Feature releases
- **Major**: Major version changes

---

## 🔐 Security Considerations

1. **Secrets Management**
   - JWT_SECRET, CSRF_SECRET, NODE_SERVICE_API_KEY auto-generated if empty
   - Never commit `.env` files
   - Restrict file permissions: `chmod 600 .env`

2. **Backup Security**
   - Database backups may contain sensitive data
   - Restrict backup directory access
   - Consider offsite backup storage

3. **Lock Files**
   - Automatically cleaned on exit
   - Checked for stale processes
   - Timeout-based lock expiration

4. **Rollback Safety**
   - Requires interactive confirmation
   - Creates safety backup before rollback
   - Tracks deployment history

---

## 📞 Support

For deployment issues:
1. Check logs in `$BASE/logs/`
2. Verify environment configuration
3. Run scripts with `--dry-run` flag first
4. Check disk space and permissions
5. Verify Git repository access

---

**Status**: ✅ Production Ready  
**Last Tested**: April 21, 2026  
**Tested Scenarios**: Full deploy, hotfix deploy, rollback, cleanup
