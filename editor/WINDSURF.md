# Windsurf AI Instructions - BroxBhai (Token Saver)

Read first:
- `AGENTS.md`
- `docs/ai/AI_QUICK_CONTEXT.md`

Hard rules:
- Search first (`rg`), open only relevant files, then make minimal diffs.
- State-changing requests must validate CSRF.
- DB access goes in Models with prepared statements.
- Do not edit generated assets in `public_html/assets/**/dist/**`.
- No secrets: never commit `.env` or paste real tokens/keys.

Verification:
- `php -l path/to/file.php`
- `php scripts/quality_scan.php`
- `npm run lint` (if JS changed)
- `npm run check:assets` (if assets changed)

