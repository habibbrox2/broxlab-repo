# cPanel Deployment Framework for BroxBhai

This directory contains the complete DevOps deployment framework for cPanel shared hosting.

## Features

- ✅ Zero downtime deployment
- ✅ Instant rollback capability
- ✅ Automatic backup system
- ✅ Versioned releases (keep last 5)
- ✅ GitHub auto deploy via Actions
- ✅ CLI deployment control

## Directory Structure

```
cpanel/
├── app/
│   ├── releases/          # Versioned releases
│   ├── shared/            # Shared files (.env, storage, logs, cache)
│   └── current -> releases/latest  # Symlink to current release
├── scripts/
│   ├── deploy.sh          # Main deployment script
│   ├── rollback.sh        # Rollback to previous release
│   ├── backup.sh          # Create backup before deploy
│   └── cleanup.sh         # Remove old releases
├── backups/               # Backup archives
└── logs/                  # Deployment logs
```

## Setup Instructions

1. **On cPanel Server:**
   ```bash
   mkdir -p /home/tdhuedhn/broxlab
   cd /home/tdhuedhn/broxlab
   git clone https://github.com/habibbrox2/broxlab-repo.git .
   ```

2. **Create Directory Structure:**
   ```bash
   mkdir -p app/releases app/shared/storage app/shared/logs app/shared/cache
   mkdir -p scripts backups logs
   ```

3. **Copy Shared Files:**
   ```bash
   cp .env app/shared/.env  # Configure production .env
   ```

4. **Setup public_html Symlink:**
   ```bash
   ln -sfn /home/tdhuedhn/broxlab/app/current/public /home/tdhuedhn/broxlab/public_html
   ```

5. **Make Scripts Executable:**
   ```bash
   chmod +x scripts/*.sh
   ```

## Usage

### Manual Deploy
```bash
cd /home/tdhuedhn/broxlab
./scripts/deploy.sh
```

### Rollback
```bash
./scripts/rollback.sh
```

### Backup
```bash
./scripts/backup.sh
```

### Status
```bash
ls -la app/releases/
```

## GitHub Auto Deploy

Configure these secrets in your GitHub repository:

- `HOST`: cPanel server hostname
- `USER`: cPanel username
- `SSH_KEY`: Private SSH key for deployment

The workflow will automatically deploy on every push to `main` branch.

## Deployment Timeline

- Clone repo: ~2s
- Composer install: ~4s
- Build: ~2s
- Symlink switch: ~0.001s

**Total interruption: 0 seconds**