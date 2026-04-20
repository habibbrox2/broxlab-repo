# BroxLab

BroxLab is a full-stack PHP app with Twig views and one unified Node.js service for AI, OCR, tools, and related APIs.

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
2. `npm install`
3. Configure `.env` and `Config/` values
4. Import the database schema from `Database/`
5. `npm run build`

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
