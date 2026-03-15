# Kilo Code AI Instructions — BroxBhai

Primary context:
- `AGENTS.md`
- `docs/PROJECT_CONTEXT.md`
- `docs/CODING_CONVENTIONS.md`
- `docs/AI_CODING_GUIDE.md`

Architecture notes:
- DB: MySQL via Models (no raw SQL).
- Middleware: Use existing patterns for auth/roles.
- Scraping: Via `app/Modules/Scraper/` and `AutoContentModel`.
- Never commit `.env`; use env vars.

Kilo Code-specific:
- For debugging, prioritize `ErrorLogging.php` for logs.
- When generating unit tests, target Models/Controllers (e.g., for `AuthManager`).
- Feature flags: Check `app/FeatureFlags/` before adding new features.
- Telegram integration: Use `app/Telegram/` modules.

Validation:
- PHP syntax: `php -l <file>`
- Lint JS: `npm run lint`
- AI sanity: `npm run e2e:ai-system`