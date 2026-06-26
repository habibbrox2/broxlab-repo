# BroxLab

Full-stack PHP (Twig) application. PHP-only deployment; Node.js tooling retained in repo but optional.

## Stack

- Backend: PHP 8.2+, MySQL / MariaDB
- Frontend: Tailwind CSS, vanilla JS
- Templating: Twig
- Optional: Node.js services (`src/`) and build tooling (`build/`)

## Layout

- `public_html/index.php` — bootstrap, static file serving, routing
- `app/Controllers/` — 50 controllers with procedural route definitions (closures, not classes)
- `app/Models/` — 44 data models (Mysqli, prepared statements)
- `app/Helpers/` — 26 shared helpers
- `app/Views/` — 261 Twig templates
- `app/Middleware/` — auth, CSRF, rate limiting
- `app/Services/` — 14 business logic services
- `app/Modules/` — specialized modules (PdfTools, AISystem)
- `app/Router/` — custom regex router with middleware support
- `Config/` — 9 app configuration files (Twig, DB, uploads, constants)
- `Database/` — 82 SQL schema files (one per table, soft deletes universal)
- `public_html/assets/` — frontend source (never edit `dist/`)
- `public_html/rtceditor/` — Rich Text Editor (esbuild bundle)
- `system/prompts/` — AI prompts and config

## Run

- App: `php -S localhost:8000 -t public_html`
- Frontend watch: `npm run dev`
- Full verify: `npm run validate`
