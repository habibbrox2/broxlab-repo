# Cursor AI Instructions — BroxBhai

Primary context:
- `AGENTS.md`
- `docs/PROJECT_CONTEXT.md`
- `docs/CODING_CONVENTIONS.md`
- `docs/ai/AI_CODING_GUIDE.md`

Project realities:
- Entry point: `public_html/index.php` (routes via `app/Controllers/*.php`).
- Avoid editing `public_html/assets/**/dist/**` (build with `npm run build`).
- Use Models for DB (prepared statements only); enforce CSRF for state changes.
- Auth via `AuthManager`; sanitize with `PurifierHelper::purify(...)`.

Cursor-specific tips:
- When generating code, prefer surgical edits matching existing patterns (e.g., camelCase vars, kebab-case URLs).
- For refactoring, run `php scripts/quality_scan.php` post-changes.
- If adding JS, ensure it bundles via esbuild (edit sources in `public_html/assets/js/src/`).
- Log activities/errors via `logActivity(...)` / `logError(...)`.

Verification:
- Syntax: `php -l <file>`
- Lint: `npm run lint`
- Assets: `npm run check:assets`
