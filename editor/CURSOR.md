# Cursor AI Instructions - BroxBhai (Token Saver)

Read first:
- `AGENTS.md`
- `docs/ai/AI_QUICK_CONTEXT.md`

Hard rules:
- Keep context tiny: reference `path:line` instead of pasting large files.
- Search first (`rg`), then edit only the minimum needed.
- Use Models + prepared statements; avoid `SELECT *`.
- CSRF required on all state-changing endpoints.
- Do not edit `public_html/assets/**/dist/**` directly.
- No secrets: never commit `.env` or paste real tokens/keys.

Verification:
- `php -l path/to/file.php`
- `php scripts/quality_scan.php`
- `npm run lint` (if JS changed)
- `npm run check:assets` (if assets changed)

