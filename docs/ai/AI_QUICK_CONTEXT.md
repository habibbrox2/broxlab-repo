# BroxBhai AI Quick Context (Editor Token Saver)

Read order (default):
1) `AGENTS.md`
2) `docs/ai/AI_QUICK_CONTEXT.md`

Open deeper docs only when needed:
- `docs/PROJECT_CONTEXT.md` (business + integrations)
- `docs/CODING_CONVENTIONS.md` (naming + patterns)
- `docs/ai/AI_CODING_GUIDE.md` (AI workflows + helpers)
- `docs/ROUTING_AND_MIDDLEWARE.md`, `docs/GENERATED_ASSETS_AND_BUILD.md`

## Repo essentials (do not guess)
- Entry point: `public_html/index.php`
- Routing: register routes in `app/Controllers/*.php` using `$router->get/post/...`
- Views: Twig templates in `app/Views/`
- DB access: models in `app/Models/*` (prepared statements only)
- Shared utilities: `app/Helpers/*` (reuse before creating new helpers)

Request lifecycle:
`Request -> public_html/index.php -> Router -> Middleware -> Controller -> Model -> View (Twig)`

## Non-negotiables (security + correctness)
- CSRF: all state-changing requests must validate CSRF (`validateCsrfToken(...)`) and/or follow existing CSRF middleware patterns.
- Auth/roles: follow `AuthManager` patterns and existing middleware usage.
- SQL: prepared statements only. No raw concatenation. Avoid `SELECT *` (explicit columns).
- No secrets: never commit `.env` or paste real keys/tokens.
- Sanitization: rich HTML must go through `PurifierHelper::purify(...)`.

Generated assets:
- Do not edit: `public_html/assets/**/dist/**`
- Edit sources under `src/**` (or the repo's documented source folders) and run `npm run build`.

## Token-saver workflow (how to work cheaply)
1) Search first with `rg` (or repo search), then open only the 1-3 relevant files.
2) Make minimal, surgical edits matching existing patterns.
3) Avoid pasting large files into the chat. Reference paths and line numbers instead.
4) If one critical detail is missing, ask exactly one clarifying question (otherwise assume the safest default).
5) Validate locally with the smallest check that proves correctness.

## Verification shortlist
- PHP syntax: `php -l path/to/file.php`
- PHP quality scan: `php scripts/quality_scan.php`
- JS lint (if JS changed): `npm run lint`
- Asset consistency (if assets changed): `npm run check:assets`

## Quick pointers (common "where is it?" answers)
- CSRF/auth helpers: `app/Helpers/AuthAndSecurityHelper.php`, `app/Middleware/*`
- AI endpoints/tools: `app/Controllers/AISystemController.php`, `app/Helpers/ToolRegistry.php`
- Central AI routes: `app/Routes/AISystemRoutes.php`
- Twig layouts: `app/Views/layout.twig`, `app/Views/admin/layout.twig` and `app/Views/**`

