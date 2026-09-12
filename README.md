# BroxLab

Laravel 12 application migrated from a legacy PHP/Twig codebase using the strangler-fig pattern.

## Stack

- Backend: PHP 8.2+, Laravel 12, MySQL / MariaDB
- Frontend: Tailwind CSS 4, Vite, Alpine.js
- Templating: Blade
- Testing: PHPUnit, Playwright
- Optional: Node.js tooling for legacy assets

## Layout

- `app/` — Laravel application code (Auth, Http, Jobs, Mail, Models, Providers, Support, View)
- `bootstrap/` — framework bootstrap
- `config/` — 11 configuration files
- `database/` — migrations, factories, seeders
- `public/` — Laravel web root (`index.php`) and static assets
- `resources/` — 114 Blade views and frontend source
- `routes/` — HTTP and console route definitions
- `tests/` — 15 PHPUnit feature and unit tests
- `old/` — archived legacy codebase (controllers, models, views, helpers)
- `migration/` — migration plan, changelog, and remaining steps

## Run

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm install
npm run build
php artisan serve
```

Watch mode:
```bash
npm run dev
```

Tests:
```bash
php artisan test
npm test
```

Full validation:
```bash
npm run validate
```

## Migration Notes

The legacy Twig-based application has been incrementally migrated to Laravel. The old codebase is preserved in `old/` for reference. See `migration/PLAN.md` for the migration strategy and `migration/REMAINING_STEPS.md` for the live checklist.
