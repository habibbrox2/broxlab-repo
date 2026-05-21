# BroxLab

BroxLab is a full-stack PHP app with Twig views. This repository has been converted to a PHP-only deployment: the Node.js unified service and build tooling are removed from the default branch. If you need to re-enable Node.js tooling later, restore `package.json` and `src/` and follow the original build steps.

## Stack

- Backend: PHP 8.2+
- DB: MySQL / MariaDB
- Frontend: Tailwind CSS, vanilla JS
- Node: `src/` unified service and `build/` tooling

## Layout

- `public_html/index.php` entry point
- `app/Controllers/` route definitions
- `app/Models/` database access
- `app/Helpers/` shared utilities
- `app/Views/` Twig templates
- `app/Middleware/` request middleware
- `system/prompts/` AI prompts and prompt config
- `src/` Node/TS services
- `build/` bundling, lint, test, and asset scripts
- `public_html/assets/` frontend source and generated assets

## Install

1. `composer install`
2. Configure `.env` and `Config/` values
4. Import the database schema from `Database/`
5. (Optional) If you restore Node.js and frontend tooling, run `npm install` and `npm run build` to rebuild assets.

## Run

- App/API: `php -S localhost:8000 -t public_html`
- Node service: `npm start`
- Frontend watch: `npm run dev`

## Shared Hosting

- Set `NODE_ENV=production`
- Set `HOST=0.0.0.0`
- Set `APP_URL` and `NODE_SERVICE_URL` to the public domain or proxy URL
- Point `DB_HOST` to the actual MySQL host provided by the host, not `localhost` unless MySQL is local
- Use the same single Node process for AI, OCR, and tool routes

## Remote Deploy

GitHub Actions can deploy directly to the remote server using SSH and the remote `deploy.sh` helper.

Required secrets for remote deploy:
- `HOST` – remote server host or IP
- `USER` – SSH username
- `SSH_KEY_BASE64` – base64-encoded SSH private key
- `REMOTE_BASE` – remote deployment base path (default `/home/$USER/broxlab`)
- `SSH_PORT` – SSH port (default `22`)
- `KEEP_RELEASES` – number of releases to keep on the server

Remote server checklist:
- `app/releases/` exists for release timestamps
- `app/current` is a symlink to the active release
- `app/shared/.env` exists
- `app/shared/storage/uploads` exists
- `public_html` symlinks to `app/current/public_html`
- `logs/` is writable and stores `node-server_<timestamp>.log`
- `deploy.sh` and `rollback.sh` are present and executable

## Verify

- `npm run lint`
- `npm run type-check`
- `npm run test:run`
- `npm run check:assets`
- `npm run validate`

## Notes

- Use `public_html/` as the web root.
- Do not edit generated files in `public_html/assets/**/dist/`.
- Follow `AGENTS.md` for repo rules and `SECURITY.md` for security reporting.
