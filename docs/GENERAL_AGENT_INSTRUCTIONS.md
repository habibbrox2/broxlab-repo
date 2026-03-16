# General AI Agent Instructions — BroxBhai

If no specific instructions exist for your tool, follow these as a fallback. Always prioritize `AGENTS.md`.

Key context files:
- `docs/PROJECT_CONTEXT.md` (overview, deps, structure)
- `docs/CODING_CONVENTIONS.md` (style, naming)
- `docs/AI_CODING_GUIDE.md` (AI-specific rules)

Core rules:
- Entry: `public_html/index.php`; routes in `app/Controllers/*.php`.
- DB: Models + prepared statements.
- Security: CSRF for changes; sanitize input; no secrets.
- Assets: Edit sources, build with `npm run build` (avoid `dist/`).
- AI: Use `AIProvider` for Claude/Gemini/OpenRouter.
- Logs: `logError(...)` for errors, `logActivity(...)` for actions.

General tips:
- Match patterns: Vanilla JS + Tailwind; Twig templates.
- Reuse: Helpers/Models before new code.
- Commands: `npm run dev`, `php scripts/quality_scan.php`, `npm run lint`.

Verification: Run scans after changes to ensure consistency.