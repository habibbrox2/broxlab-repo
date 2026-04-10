# Cursor Quick Rules

## Setup
- Read `AGENTS.md` and `docs/ai/AI_QUICK_CONTEXT.md` before editing.
- Search with `rg` and inspect only the files you need.

## Rules
- Use prepared statements in `app/Models/*` and avoid `SELECT *` when querying.
- Always protect state-changing operations with CSRF tokens and include `X-CSRF-Token` headers while issuing AJAX calls.
- Never edit generated assets (`public_html/assets/**/dist/**`) directly; change the source files and run `npm run build`.
- Never expose secrets; keep API keys and credentials in `.env` (which is gitignored).

## Verification
- `php -l path/to/file.php`
- `php scripts/quality_scan.php`

## Links
- `docs/editors/rules-base.md`
- `docs/guides/coding-standards.md`
