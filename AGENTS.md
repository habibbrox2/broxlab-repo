# BroxBhai — Agent Instructions (Repo Root)

এই রিপোতে কাজ করার সময় যে কোনো AI coding agent (Copilot/Cursor/Claude/Windsurf/Kilo Code/Codex) এই ফাইলকে **প্রাথমিক নির্দেশনা** হিসেবে ধরবে।

## Must-Read Context
- `docs/PROJECT_CONTEXT.md`
- `docs/CODING_CONVENTIONS.md`
- `docs/ai/AI_CODING_GUIDE.md`

## Architecture Truths (don’t guess)
- Entry point: `public_html/index.php`
- Routing: `$router` is instantiated in `app/Routes/Router.php` and routes are registered from files in `app/Controllers/*.php` (loaded via `public_html/index.php`).
- Views: Twig templates in `app/Views/`
- DB access: Use Models (`app/Models/*`) and prepared statements.

## Generated / Built Files (avoid editing directly)
Edit source files, then run build:
- JS build outputs: `public_html/assets/js/dist/**` (generated)
- Firebase v2 build outputs: `public_html/assets/firebase/v2/dist/**` (generated)
- CSS bundle outputs: `public_html/assets/css/dist/**` (generated)

If you must touch a generated file (rare), explain why and confirm there is no source counterpart.

## Security & Data Safety
- All state-changing requests must have CSRF protection (`validateCsrfToken(...)`).
- Auth/roles: use `AuthManager` checks and middleware patterns already in use.
- Never add secrets to code or commit `.env`. Use `.env.example` and env vars.
- Sanitize user input and purify rich HTML (`PurifierHelper::purify(...)`) where applicable.
- Log errors via `logError(...)` / activities via `logActivity(...)`.

## Working Style
- Prefer minimal, surgical changes that match existing patterns.
- Reuse helpers/models before creating new ones.
- Don’t introduce new frameworks (keep it vanilla JS + Tailwind + custom PHP).
- Keep URLs in kebab-case; PHP vars in camelCase; DB columns in snake_case.

## Useful Commands (local)
- Build assets: `npm run build` (or `npm run dev` for watch)
- Lint JS: `npm run lint`
- Check asset consistency: `npm run check:assets`
- AI e2e sanity: `npm run e2e:ai-system` (uses `BROX_BASE_URL`, `BROX_ADMIN_COOKIE`)
- PHP quality scan: `php scripts/quality_scan.php`
- PHP security scan: `php scripts/security_scan.php`

