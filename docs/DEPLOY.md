# Deploy + DB Backup/Transfer (Shared Hosting)

This project uses a config-only deploy system driven by `.env`.  
No admin UI is added; all settings live in your local and server `.env`.

## Required .env keys (local)
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

## Server .env
Keep a separate `.env` on the server. It is NOT synced by deploy scripts.

## Deploy (Windows)
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

## One-click (cross-platform)
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

## Deploy (Linux/macOS)
```
chmod +x scripts/deploy.sh
./scripts/deploy.sh --dry-run
./scripts/deploy.sh
```

## DB Backup (local)
```
powershell -ExecutionPolicy Bypass -File scripts/db_backup.ps1
```

Allow DROP statements in dump:
```
powershell -ExecutionPolicy Bypass -File scripts/db_backup.ps1 -AllowDrop
```

## DB Transfer + Import (safe default)
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

## Home/Office Workflow
1. Work on either machine.
2. Push to the central Git remote (origin).
3. On the deploy machine, `git pull` then run deploy script.
4. See `docs/git.md` for safe terminal push/pull steps.

## Local Deploy UI (Queue + Status)
The deploy UI is **local-only** and requires `APP_ENV=development`.
Open:
```
/admin/deploy-tools
```

Queue actions and run the worker locally:
```
php scripts/deploy_worker.php
```

### Cron / Task Scheduler example
Windows Task Scheduler (every 5 minutes):
```
php h:\Web\broxbhai\scripts\deploy_worker.php
```

## Notes
- Uploads and storage are excluded by default.
- Deploy uses an atomic swap by renaming the target folder on the server.
- Make sure `ssh`, `scp`, `mysqldump`, and `mysql` are available in PATH.

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
