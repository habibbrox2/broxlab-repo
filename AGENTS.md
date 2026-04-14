# BroxBhai - AI Agent Guardrails (Repo Root)

# Version: 2.1.5 | Auto-updated by agent loop

Default read order (keep token cost low):

1. `AGENTS.md`
2. `docs/ai/AI_QUICK_CONTEXT.md`

Consolidated standards (read when implementing):

- `docs/index.md` — Catalog of guides, integrations, editor docs, and plans
- `docs/guides/coding-standards.md` — Single source for security, DB, and code rules

Self-improvement loop (only after non-trivial work):

- `docs/ai/SELF_IMPROVEMENT_LOOP.md`

## Repo facts (do not guess)

- Entry point: `public_html/index.php`
- Routes: `app/Controllers/*.php` registers `$router->get/post/...`
- Views: Twig in `app/Views/`
- DB: `app/Models/*` (prepared statements only)
- Helpers: `app/Helpers/*` (reuse before creating new helpers)

## Hard rules (security + correctness)

→ **See [docs/guides/coding-standards.md](docs/guides/coding-standards.md)** for detailed standards on:

- CSRF validation, input sanitization, secrets management
- Prepared statements, explicit columns (`SELECT id, name, ...` not `SELECT *`)
- Error handling, logging, JSON response format
- Generated assets, code organization, naming conventions

## Generated assets (do not edit directly)

- Do not edit: `public_html/assets/**/dist/**`
- Edit sources (see `docs/GENERATED_ASSETS_AND_BUILD.md`) and run `npm run build`.

## Fast verification

- PHP syntax: `php -l path/to/file.php`
- PHP scan: `php scripts/quality_scan.php`
- JS lint (if JS changed): `npm run lint`
- Asset check (if assets changed): `npm run check:assets`

## Changelog (latest 6)

| Version | Date       | Agent    | Change                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| ------- | ---------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2.1.5   | 2026-04-14 | BroxBhai | Minified Tailwind CSS for improved performance and reduced bundle size.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| 2.1.4   | 2026-04-13 | BroxBhai | Fix: Admin panel UI/UX issues, server status fixes, and system improvements.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| 2.1.3   | 2026-04-12 | Kilo     | Enhanced web scraping system with AI-powered content enhancement and publishing: added ScraperErrorHandler class for network errors, parsing errors, rate limits, and structural changes; implemented retry logic with exponential backoff; added error monitoring dashboard and API endpoints; improved HtmlFetcher and ScraperService with robust error recovery mechanisms; created AiContentEnhancer for AI-powered content cleaning, title optimization, SEO enhancement, and taxonomy suggestion; implemented automated data collection and publishing workflow that enhances scraped content with AI before publishing to posts and mobiles tables; fixed scraping configurations for problematic sources; deployed Node.js service for enhanced HTML fetching; added web preset list with create, edit, and AI auto selector detection functionality. |
| 2.1.2   | 2026-04-09 | Codex    | Reorganized documentation into guides/integrations/project/editors/plans, added entry indexes, and updated agent references to the new structure.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| 2.1.1   | 2026-03-31 | BroxBhai | Implemented Servers Online/Offline Status indicator in admin header: added HTML dropdown in layout.twig, JavaScript health checks in admin.js, API endpoint in AISystemController.php, and CSS styling in admin.css for real-time monitoring of database, cache, API, and Node.js services.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| 2.1.0   | 2026-03-22 | BroxBhai | Context consolidation: created `docs/guides/coding-standards.md` (single source for security/DB/code rules), created `docs/editors/rules-base.md` (shared foundation for all editor instructions), simplified editor files to reference shared rules (reduced duplication).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| 2.0.9   | 2026-03-21 | BroxBhai | Added defensive guard in `AIProvider.php` to prevent fatal redeclare if included twice under release symlinks.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| 2.0.8   | 2026-03-21 | BroxBhai | AutoContent production hardening: fixed AIProvider redeclare 500, enforced CSRF on admin APIs, improved pipeline robustness, added schema health warning.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
