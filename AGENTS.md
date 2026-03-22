# BroxBhai - AI Agent Guardrails (Repo Root)
# Version: 2.1.0 | Auto-updated by agent loop

Default read order (keep token cost low):
1) `AGENTS.md`
2) `docs/ai/AI_QUICK_CONTEXT.md`

Consolidated standards (read when implementing):
- `docs/CODING_STANDARDS.md` — Single source for security, DB, and code rules

Self-improvement loop (only after non-trivial work):
- `docs/ai/SELF_IMPROVEMENT_LOOP.md`

## Repo facts (do not guess)
- Entry point: `public_html/index.php`
- Routes: `app/Controllers/*.php` registers `$router->get/post/...`
- Views: Twig in `app/Views/`
- DB: `app/Models/*` (prepared statements only)
- Helpers: `app/Helpers/*` (reuse before creating new helpers)

## Hard rules (security + correctness)
→ **See [`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md)** for detailed standards on:
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
| Version | Date | Agent | Change |
|---------|------|-------|--------|
| 2.1.0 | 2026-03-22 | BroxBhai | Context consolidation: created `docs/CODING_STANDARDS.md` (single source for security/DB/code rules), created `editor/.rules-base.md` (shared foundation for all editor instructions), simplified editor files to reference shared rules (reduced duplication). |
| 2.0.9 | 2026-03-21 | BroxBhai | Added defensive guard in `AIProvider.php` to prevent fatal redeclare if included twice under release symlinks. |
| 2.0.8 | 2026-03-21 | BroxBhai | AutoContent production hardening: fixed AIProvider redeclare 500, enforced CSRF on admin APIs, improved pipeline robustness, added schema health warning. |
| 2.0.7 | 2026-03-20 | BroxBhai | Removed unused duplicate agent instruction files to reduce repo noise and editor context size. |
| 2.0.6 | 2026-03-20 | BroxBhai | Token-cost optimization: added `docs/ai/AI_QUICK_CONTEXT.md`, minimized editor rules + `SKILL.md`, migrated older AGENTS changelog to `docs/ai/AGENT_MEMORY.md`. |
| 2.0.5 | 2026-03-20 | BroxBhai | Centralized assistant/coplay API routes in `app/Routes/AISystemRoutes.php`, enforced CSRF for public chat, added SSE meta IDs for feedback, removed duplicate assistant script include, stripped production console logs. |
