---
name: broxbhai-editor-ai-token-saver
description: Repo-specific editor AI rules to minimize token usage while matching BroxBhai conventions.
license: Internal (project)
---

Default context (keep it small):
- `AGENTS.md`
- `docs/ai/AI_QUICK_CONTEXT.md`

Optional (open only if you need external docs links):
- `docs/ai/DEV_REFERENCE_LINKS.md`

Hard rules:
- Search first (`rg`), open only the relevant files, then make minimal diffs.
- Do not paste large files into chat; reference `path:line` instead.
- Never edit generated assets in `public_html/assets/**/dist/**` (edit sources and run `npm run build`).
- Use Models + prepared statements; no raw SQL concatenation; avoid `SELECT *`.
- Enforce CSRF on all state-changing requests (`validateCsrfToken(...)` / middleware pattern).
- No secrets: never commit `.env` or paste real tokens/keys.

Verification:
- `php -l path/to/file.php`
- `php scripts/quality_scan.php`
- `npm run lint` (if JS changed)
- `npm run check:assets` (if assets changed)
