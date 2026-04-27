# BroxLab - AI Agent Guardrails

# Version: 2.2.1 | Lean update for lower token cost

Read first:

1. `AGENTS.md`
2. `README.md`
3. `SECURITY.md`
4. `/.ai` only for coding agents, not for human instructions

## Project map

- Entry: `public_html/index.php`
- Routes: `app/Controllers/*.php` via `app/Routes/Router.php`
- Views: `app/Views/`
- DB: `app/Models/` with prepared statements only
- Helpers: `app/Helpers/`
- Middleware: `app/Middleware/`
- Prompts: `system/prompts/`
- Node service: `src/`
- Build tools: `build/`
- Frontend sources: `public_html/assets/`
- Generated assets: `public_html/assets/**/dist/`

## Rules

- Use prepared statements and explicit SQL columns.
- Validate input and keep CSRF on mutating actions.
- Never commit secrets.
- Reuse existing helpers/models before adding new ones.
- Do not edit generated `dist/` files directly.
- Rebuild assets after source changes.

## Verify

- PHP: `php -l path/to/file.php`
- JS/TS: `npm run lint` and `npm run type-check`
- Tests: `npm run test:run`
- Assets: `npm run check:assets`
- Full gate: `npm run validate`
- One-process start: `npm start`
