# GitHub Copilot Instructions - BroxBhai (Token Saver)

Read first:
- `AGENTS.md`
- `docs/ai/AI_QUICK_CONTEXT.md`

Hard rules:
- Search first (`rg`), then make minimal diffs.
- Use Models + prepared statements; avoid `SELECT *`.
- Enforce CSRF for state-changing endpoints.
- Do not edit generated assets in `public_html/assets/**/dist/**`.
- No secrets: never commit `.env` or paste real tokens/keys.

Verification:
- `php -l path/to/file.php`
- `php scripts/quality_scan.php`
