# BroxLab

Full-stack PHP (Twig) application. PHP-only deployment; Node.js tooling retained in repo but optional.

## Stack

- Backend: PHP 8.2+, MySQL / MariaDB
- Frontend: Tailwind CSS, vanilla JS
- Templating: Twig
- Optional: Node.js services (`src/`) and build tooling (`build/`)

## Layout

- `public_html/index.php` — bootstrap, static file serving, routing
- `app/Controllers/` — 50 controllers with embedded routes
- `app/Models/` — 51 data models
- `app/Helpers/` — 25 shared helpers
- `app/Views/` — 244 Twig templates
- `app/Middleware/` — auth, CSRF, rate limiting
- `app/Services/` — business logic
- `app/Modules/` — specialized modules (PdfTools, AISystem)
- `app/Routes/` — router implementation
- `Config/` — app configuration (Twig, DB, uploads, constants)
- `Database/` — 74 SQL schema files
- `public_html/assets/` — frontend source
- `public_html/rtceditor/` — Rich Text Editor (esbuild bundle)
- `system/prompts/` — AI prompts and config

## Run

- App: `php -S localhost:8000 -t public_html`
- Frontend watch: `npm run dev`
- Full verify: `npm run validate`
