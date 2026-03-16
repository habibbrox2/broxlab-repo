# Claude Code Instructions — BroxBhai

Primary context:
- `AGENTS.md`
- `docs/PROJECT_CONTEXT.md`
- `docs/CODING_CONVENTIONS.md`
- `docs/ai/AI_CODING_GUIDE.md`

Repo realities:
- Entry point is `public_html/index.php`.
- Routes are registered in `app/Controllers/*.php` using `$router->get/post/...`.
- Generated assets live in `public_html/assets/**/dist/**` — edit sources, then run `npm run build`.

Safety:
- Enforce CSRF validation for state-changing routes.
- Use Models + prepared statements for DB.
- Don’t add secrets; `.env` must never be committed.

Suggested verification:
- `php -l <changed.php>` (syntax)
- `php scripts/quality_scan.php`
- `npm run lint` (if JS changed)
- `npm run check:assets` (if assets changed)

