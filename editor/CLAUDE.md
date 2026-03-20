# Claude Code Instructions - BroxBhai (Token Saver)

Read first:
- `AGENTS.md`
- `docs/ai/AI_QUICK_CONTEXT.md`

Hard rules:
- Search first (`rg`), open only relevant files, then make minimal diffs.
- Use Models + prepared statements; avoid `SELECT *`.
- Enforce CSRF for state-changing requests (`validateCsrfToken(...)` / middleware pattern).
- Never edit generated assets in `public_html/assets/**/dist/**` (edit sources, then `npm run build`).
- No secrets: never commit `.env` or paste real tokens/keys.
- Prefer Twig templates for output; avoid raw HTML echo in controllers.

Verification:
- `php -l path/to/file.php`
- `php scripts/quality_scan.php`
- `npm run lint` (if JS changed)
- `npm run check:assets` (if assets changed)

