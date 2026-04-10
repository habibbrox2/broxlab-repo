# BroxBhai AI Quick Context (Token Saver)

## Overview
Single-page reference that keeps token usage low while summarizing the repo facts and security expectations needed for AI-assisted edits.

## Purpose
Point every agent to `AGENTS.md` first, then this file, so they can escalate to deeper guides (`docs/index.md`, `docs/guides/`, `docs/plans/`) only when necessary.

## Repo Essentials
- Entry point: `public_html/index.php`
- Routing: declare `$router->get/post/...` handlers inside `app/Controllers/*.php`
- Views: `app/Views/` Twig templates
- Database: use `app/Models/*` with prepared statements (no raw concatenation)
- Helpers: reuse `app/Helpers/*` before creating new ones
- Request lifecycle: `public_html/index.php -> Router -> Middleware -> Controller -> Model -> View (Twig)`

## Non-Negotiables
- Validate CSRF tokens on all state-changing requests (or use middleware) and include `X-CSRF-Token` in AJAX headers.
- Never commit secrets; keep API keys in `.env` (gitignored) only and rotate if exposed.
- Purify rich HTML via `PurifierHelper::purify(...)` before rendering.
- Always list explicit columns (`SELECT id, name, ...`), use prepared statements, and avoid `SELECT *`.
- Never edit `public_html/assets/**/dist/**`; modify source files (`src/`, `public_html/assets/`) and run `npm run build`.

## Token-Saver Workflow
1. Search with `rg`/repo search before opening files.
2. Open a maximum of 1-3 files that are truly relevant.
3. Make surgical edits that match existing patterns.
4. Ask at most one clarifying question (if critical information is missing; otherwise assume the safest default).
5. Validate locally with the smallest check that proves correctness.

## Verification Checklist
- `php -l path/to/file.php`
- `php scripts/quality_scan.php`
- `npm run lint` (if JavaScript changed)
- `npm run check:assets` (if frontend assets changed)

## Quick References
- CSRF/auth helpers: `app/Helpers/AuthAndSecurityHelper.php`, `app/Middleware/*`
- AI controllers/tools: `app/Controllers/AISystemController.php`, `app/Helpers/ToolRegistry.php`
- AI routes: `app/Routes/AISystemRoutes.php`
- Layouts: `app/Views/layout.twig`, `app/Views/admin/layout.twig`

## References
- `AGENTS.md`
- `docs/index.md`
- `docs/guides/coding-standards.md`
- `docs/guides/context-governance.md`
