# 🔐 Sensitive Data Protection Guide

## Overview

This repository contains important instructions and code for **BroxBhai** - an enterprise-grade PHP + Node.js + Python AI-powered platform.

⚠️ **CRITICAL: This document explains what data is NEVER committed to git.**

---

## 🚫 What NEVER Goes to Git

### 1. **Secrets & Credentials** ⚠️ CRITICAL
```
❌ NEVER COMMIT:
  - .env files with passwords/credentials
  - API keys (Firebase, OpenAI, etc.)
  - SSH private keys
  - Database passwords
  - Service account files
  
✅ DO THIS INSTEAD:
  - Commit: .env.example (with dummy values)
  - Keep: Actual .env only on production server
  - Use: GitHub Secrets for CI/CD
```

### 2. **Database Backups** ⚠️ CRITICAL
```
❌ NEVER COMMIT:
  - *.sql files
  - *.sql.gz backups
  - Database dumps
  - /backups/database/ folder
  
WHY: Backups contain:
  - Real user data (emails, passwords)
  - Transaction records
  - Personal information (PII)
  - Business secrets
```

### 3. **User Uploads** ⚠️ PRIVATE
```
❌ NEVER COMMIT:
  - /uploads/
  - /public_html/uploads/
  - /app/shared/uploads/
  
WHY: User-generated content:
  - Violates user privacy
  - Copyrighted material
  - Sensitive business documents
```

### 4. **Logs & Deployment Data** ⚠️ SENSITIVE
```
❌ NEVER COMMIT:
  - *.log files
  - /logs/ folder
  - /storage/logs/
  - deployment.log
  - Database backups in /app/shared/backups/
  - Release backups (*.tar.gz)
  
WHY: Logs contain:
  - User IP addresses
  - Error stack traces (reveal system details)
  - Sometimes credentials in debug output
  - Sensitive business operations
```

---

## ✅ What DOES Go to Git

### 1. **Source Code**
```
✅ ALWAYS COMMIT:
  - /app/ (PHP code)
  - /src/ (JavaScript sources)
  - /Config/*.php (except broxlab-firebase.json)
  - /public_html/ (except uploads)
  - /rag_system/ (except venv, cache)
```

### 2. **Configuration Examples**
```
✅ ALWAYS COMMIT:
  - .env.example (with placeholder values)
  - .env.sample
  - Config file templates
```

### 3. **Documentation**
```
✅ ALWAYS COMMIT:
  - /docs/
  - README.md
  - SECURITY.md
  - This file!
```

### 4. **Build Configuration**
```
✅ ALWAYS COMMIT:
  - package.json (not package-lock.json)
  - composer.json (not vendor/)
  - build/ folder
  - .gitignore (the protection rules!)
```

---

## 🔍 How .gitignore Works

### Main `.gitignore` (Repository Root)
Located: `/broxlab/.gitignore`

**Protects these categories:**
1. Dependencies (`/vendor/`, `/node_modules/`)
2. Secrets (`.env`, `/Config/broxlab-firebase.json`)
3. Database backups (`*.sql`, `*.sql.gz`)
4. Release backups (`/backups/`, `/app/releases/`)
5. Logs (`*.log`, `/logs/`)
6. User uploads (`/uploads/`)
7. Python venv (`/rag_env/`, `/venv/`)
8. Cache & temp (`/cache/`, `/storage/cache/`)

### Server `.gitignore` (For Production)
Located: `/broxlab/.gitignore.server`

**Use on production server:**
```bash
# Copy to production releases to prevent accidental pushes
cp /home/tdhuedhn/broxlab/.gitignore.server \
   /home/tdhuedhn/broxlab/app/releases/20240101_120000/.gitignore
```

---

## 🛡️ Best Practices

### Before Each Commit
```bash
# Check what will be committed
git status

# See what you've modified
git diff

# Stage only intended files
git add app/
git add docs/
git add package.json

# Preview staged changes
git diff --staged

# Commit with descriptive message
git commit -m "feat: add new feature"

# Push to repository
git push origin feature/branch-name
```

### Never Do This
```bash
❌ WRONG: git add .   (adds everything!)
❌ WRONG: git add *   (adds all files)
❌ WRONG: Manually including .env files
❌ WRONG: Committing backup files
```

### Always Verify
```bash
# Before pushing, verify no secrets are included
git status
git diff --staged | grep -i "password\|secret\|key\|token"

# If you see any secrets, don't push!
git reset HEAD <file>  # Remove from staging
```

---

## 🔄 Deployment Flow (Sensitive Data Safe)

```
1. Development
   ✅ Work with .env.example
   ✅ Create .env locally (not committed)
   ✅ Never commit secrets

2. Push to GitHub
   ✅ .gitignore prevents secrets from being pushed
   ✅ Only safe source code is pushed
   ✅ Database backups stay on server

3. GitHub Actions Deployment
   ✅ SSH into production server
   ✅ Uses GitHub Secrets (not in code)
   ✅ Deploys only from main branch

4. Production Server
   ✅ .env exists on server only (never in git)
   ✅ Database backups stored separately
   ✅ User uploads stored in /uploads/ (not in git)
   ✅ Logs stored in /logs/ (not in git)
```

---

## 🚨 Accidental Commit? Here's How to Fix

### If you accidentally committed a secret:

```bash
# IMMEDIATE ACTION - Remove from history
# ⚠️  THIS REWRITES HISTORY - Use carefully!

# Option 1: Remove from last commit (if not pushed)
git reset HEAD~1
git restore --staged <secret-file>
git commit --amend

# Option 2: Remove from history entirely (uses BFG tool)
brew install bfg  # Install BFG Repo-Cleaner
bfg --delete-files .env broxlab/
git reflog expire --expire=now --all && git gc --prune=now

# Option 3: Force push (if absolutely necessary)
git push --force origin <branch>
⚠️  WARNING: This affects collaborators!
```

### After removal:
```bash
# Regenerate credentials (because they were exposed)
# 1. Update .env on production server
# 2. Regenerate API keys
# 3. Rotate database passwords
# 4. Update Firebase credential files
```

---

## 📋 Server Directory Structure (Not in Git)

```
/home/tdhuedhn/broxlab/
├── app/
│   ├── current/ → (symlink, not in git)
│   ├── shared/ (persistent, not in git)
│   │   ├── .env (💔 NEVER COMMIT)
│   │   ├── storage/ (not in git)
│   │   ├── logs/ (not in git)
│   │   ├── backups/
│   │   │   ├── database/ (💔 NEVER COMMIT)
│   │   │   └── release/ (not in git)
│   │   └── rag_env/ (not in git)
│   └── releases/ (not in git)
│       ├── 20240101_120000/
│       └── (cloned from git with secrets excluded)
│
├── backups/ (not in git)
│   ├── database/ (💔 NEVER COMMIT)
│   └── release/ (not in git)
│
└── logs/ (not in git)
    ├── deploy.log
    ├── backup.log
    └── rollback.log
```

---

## ✅ Secure Workflow Checklist

- [ ] `.env` file is in `.gitignore` (not committed)
- [ ] `.env.example` exists with placeholder values
- [ ] Database credentials are only in `.env` (server only)
- [ ] `.gitignore` covers all sensitive directories
- [ ] GitHub Secrets are set for CI/CD credentials
- [ ] No *.sql files in repository
- [ ] No backup files (*.tar.gz) committed
- [ ] No log files committed
- [ ] User uploads are in `/uploads/` (ignored)
- [ ] `git status` shows no `.env` or `*.sql` files

---

## 🔐 Environment Variables Reference

### `.env.example` (Safe to commit)
```env
# This file serves as documentation of required variables
# Values are placeholder/dummy values

DB_HOST=localhost
DB_USER=broxlab_user
DB_PASS=changeme_in_production
DB_NAME=broxlab

FIREBASE_PROJECT_ID=your-firebase-project
FIREBASE_PRIVATE_KEY=your-private-key-here

API_KEY_OPENAI=sk-placeholder-do-not-use
API_KEY_GOOGLE=gcp-placeholder-do-not-use

APP_NAME=BroxBhai
APP_ENV=production
APP_DEBUG=false
```

### `.env` (On server only, NEVER committed)
```env
# Real production credentials
# This file is created on server only and read by deployment scripts

DB_HOST=localhost
DB_USER=broxlab_prod_user
DB_PASS=actual_secure_password_here
DB_NAME=broxlab_production

FIREBASE_PROJECT_ID=broxlab-firebase-prod
FIREBASE_PRIVATE_KEY=-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----

API_KEY_OPENAI=sk-actual-openai-key-here
API_KEY_GOOGLE=actual-gcp-key-here

APP_NAME=BroxBhai
APP_ENV=production
APP_DEBUG=false
```

---

## 📞 Quick Commands

```bash
# View what's being ignored
cat .gitignore

# Check if a file would be ignored
git check-ignore -v <filename>

# See all untracked files (these won't be committed)
git status --short

# Show what's staged to be committed
git diff --staged

# Show what would be pushed
git log --oneline -n 10 origin/main..HEAD

# See if any secrets are staged
git diff --staged | grep -i "password\|secret\|api"
```

---

## 🎯 In Summary

| Item | Commit? | Why |
|------|---------|-----|
| `.env` | ❌ NO | Contains production credentials |
| `.env.example` | ✅ YES | Shows what variables are needed |
| `*.sql` | ❌ NO | Database contains user/business data |
| `/uploads/` | ❌ NO | User-generated private content |
| `/logs/` | ❌ NO | Contains sensitive information |
| `/vendor/` | ❌ NO | Generated by composer install |
| `/node_modules/` | ❌ NO | Generated by npm install |
| `/rag_env/` | ❌ NO | Production venv, created each deploy |
| `app/` (code) | ✅ YES | Source code - safe to share |
| `docs/` | ✅ YES | Documentation - safe to share |
| `package.json` | ✅ YES | Dependency list - safe to share |

---

## 🔗 Related Documentation

- [Security Policy](..\SECURITY.md)
- [Deployment Guide](docs/DEPLOYMENT_SCRIPTS_GUIDE.md)
- [GitHub Actions Secrets](https://docs.github.com/en/actions/security-guides/encrypted-secrets)
- [Git .gitignore Documentation](https://git-scm.com/docs/gitignore)

---

**Last Updated:** 2024-03-18
**Version:** 1.0

**Remember: When in doubt about what to commit, don't commit it! 🔒**
