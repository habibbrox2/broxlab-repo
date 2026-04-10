# Windsurf Quick Rules

## Setup
- Review `AGENTS.md`, `docs/ai/AI_QUICK_CONTEXT.md`, and `docs/guides/context-governance.md` before diving into tasks.
- Restrict your workspace to the files most relevant to the ticket.

## Rules
- Keep controllers thin: move SQL logic to models, use Twig templates for rendering, and centralize helpers in `app/Helpers/*`.
- Enforce CSRF and auth guards on every state-changing request, and never let raw HTML escape to the browser without purification.
- Avoid touching `public_html/assets/**/dist/**`; edit source bundles and run `npm run build` afterwards.
- Never commit `.env` or secrets, and sanitize user input via `PurifierHelper::purify()`.

## Verification
- `php -l path/to/file.php`
- `php scripts/quality_scan.php`

## Links
- `docs/editors/rules-base.md`
- `docs/guides/coding-standards.md`
