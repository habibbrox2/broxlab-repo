# GitHub Copilot Instructions — BroxBhai

Follow `AGENTS.md` first.

Key constraints:
- Don’t edit generated assets: `public_html/assets/**/dist/**` (edit sources and run `npm run build`).
- Register routes in `app/Controllers/*.php` using `$router->get/post/...` (bootstrapped by `public_html/index.php`).
- Use Models + prepared statements for DB access; avoid raw SQL concatenation.
- Enforce CSRF validation for state-changing endpoints.
- No secrets in code; never commit `.env`.

Preferred checks:
- `php scripts/quality_scan.php`
- `php scripts/security_scan.php`
- `npm run lint` (when JS changes)

