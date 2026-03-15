# Local Dev & Commands — BroxBhai

## Prerequisites
- PHP `8.2+`
- Composer
- Node.js `>= 18`
- MySQL/MariaDB

## Setup
- PHP deps: `composer install`
- JS deps: `npm install`
- Env: copy `.env.example` → `.env` and fill values

## Run (dev)
- PHP dev server: `php -S localhost:8000 -t public_html`
- JS watch build: `npm run dev`

## Build (prod-like)
- `npm run build`
- Optional checks: `npm run check:assets`

## Lint / sanity
- JS lint: `npm run lint`
- AI endpoints sanity: `npm run e2e:ai-system`
  - Env: `BROX_BASE_URL` (default `http://localhost`)
  - Optional admin: `BROX_ADMIN_COOKIE`

## Project scripts (PHP)
- Quality report: `php scripts/quality_scan.php` → `storage/logs/quality-report.*`
- Security report: `php scripts/security_scan.php` → `storage/logs/security-report.*`
- Migration runner: `php scripts/run_migration.php` (reads DB creds from `.env`)

## Debug tips
- When routes act “missing”, confirm the controller file is under `app/Controllers/` and being required by `public_html/index.php`.
- If headers/session issues appear, check output before header calls (index uses `ob_start()` to reduce “headers already sent”).

