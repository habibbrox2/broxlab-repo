# web-host/ - cPanel Server & Deployment Folder

⚠️ **IMPORTANT**: This folder contains **both** deployment scripts AND server-specific files.

---

## 📁 Folder Structure

```
web-host/
├── .gitignore ← Protects cPanel files from git
├── .github/
│   └── workflows/ → GitHub Actions CI/CD (✅ COMMITTED)
├── scripts/
│   ├── backup.sh → Release backup (✅ COMMITTED)
│   ├── cleanup.sh → Old release cleanup (✅ COMMITTED)
│   ├── deploy.sh → Main deployment (✅ COMMITTED)
│   ├── rollback.sh → Emergency recovery (✅ COMMITTED)
│   ├── database-backup.sh → DB snapshot (✅ COMMITTED)
│   ├── database-restore.sh → DB recovery (✅ COMMITTED)
│   └── setup-gitignore.sh → Server protection (✅ COMMITTED)
├── cpanel/
│   └── setup.sh → cPanel server setup (❌ NOT COMMITTED)
├── composer → PHP dependency manager binary (❌ NOT COMMITTED)
├── .htaccess → Apache config (❌ NOT COMMITTED)
├── index.php → Server routing (❌ NOT COMMITTED)
├── DEPLOYMENT_SCRIPTS_GUIDE.md → Documentation (✅ COMMITTED)
└── readme.md → Folder info (❌ May contain local config)
```

---

## ✅ What's COMMITTED to Git

### 1. **Deployment Scripts** (`scripts/`)
```bash
✅ backup.sh → Creates release backups
✅ cleanup.sh → Removes old releases/backups
✅ deploy.sh → Main deployment orchestrator
✅ rollback.sh → Emergency code recovery
✅ database-backup.sh → Pre-deployment DB snapshot
✅ database-restore.sh → Database point-in-time recovery
✅ setup-gitignore.sh → Server protection setup
```

These scripts are essential for automated deployment and are committed so they can be deployed to the server and executed by GitHub Actions.

### 2. **GitHub Actions** (`.github/workflows/`)
```yaml
✅ deploy.yml (CI/CD workflow)
```

Workflow files define how GitHub Actions automatically deploys your code to production.

### 3. **Documentation**
```
✅ DEPLOYMENT_SCRIPTS_GUIDE.md → Complete deployment guide
```

---

## ❌ What's NOT COMMITTED (Server-Only)

### 1. **cPanel Setup** (`cpanel/`)
```
❌ cpanel/setup.sh → Server initialization (one-time, local only)
```

This script is run once on the production server during initial setup. Contains server-specific paths and configs.

### 2. **Server Binaries**
```
❌ composer → PHP dependency manager
```

Server has its own version of composer installed; no need to commit binary to git.

### 3. **Server Configuration Files**
```
❌ .htaccess → Apache rewrite rules (server-specific)
❌ index.php → Production routing (server-specific)
```

These are environment-specific and configured on the server.

---

## 🔄 Git Protection via .gitignore

The `.gitignore` file in this folder:
- ✅ **Allows commits**: `scripts/`, `.github/`, documentation
- ❌ **Blocks commits**: `cpanel/`, `composer`, `.htaccess`, `index.php`

This prevents server-specific files from accidentally being pushed to GitHub while deployment scripts are properly committed.

---

## ✅ Quick Verification

### Check what will be committed
```bash
git status web-host/
```

**You should see:**
```
✅ web-host/scripts/backup.sh
✅ web-host/scripts/cleanup.sh
✅ web-host/scripts/deploy.sh
✅ web-host/scripts/rollback.sh
✅ web-host/scripts/database-backup.sh
✅ web-host/scripts/database-restore.sh
✅ web-host/scripts/setup-gitignore.sh
✅ web-host/.github/workflows/
✅ web-host/DEPLOYMENT_SCRIPTS_GUIDE.md
```

**You should NOT see:**
```
❌ web-host/cpanel/
❌ web-host/composer
❌ web-host/.htaccess
❌ web-host/index.php
```

---

## 🚀 How It's Used

### GitHub Actions Deployment
```yaml
# Automatically runs deployment script from git
- name: Deploy to Production
  run: bash web-host/scripts/deploy.sh
```

### One-Time Server Setup (Local Only)
```bash
# Run on server once during initialization
bash /home/tdhuedhn/broxlab/web-host/cpanel/setup.sh
```

---

## 📋 Summary Table

| File/Folder | Committed? | Purpose | Location |
|-------------|-----------|---------|----------|
| `scripts/` | ✅ YES | Deployment automation | In git, deployed to server |
| `.github/` | ✅ YES | GitHub Actions CI/CD | In git |
| `DEPLOYMENT_SCRIPTS_GUIDE.md` | ✅ YES | Documentation | In git |
| `cpanel/` | ❌ NO | Server setup | Server only, local config |
| `composer` | ❌ NO | PHP binary | Server only |
| `.htaccess` | ❌ NO | Server config | Server only |
| `index.php` | ❌ NO | Server routing | Server only |

---

## 🔐 Security Rules

✅ **COMMIT These**:
- Deployment automation scripts
- GitHub Actions workflows
- Documentation

❌ **NEVER COMMIT These**:
- Server configuration files
- Server binaries
- cPanel-specific setup scripts
- Database credentials (use `.env.example` instead)

---

**Remember**: This folder bridges your git repository and your cPanel server. Keep deployment scripts versioned in git while keeping server-specific files local. 🔒
