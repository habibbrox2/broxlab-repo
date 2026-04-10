# Editor Rules Base (Shared)

## Setup
- Read AGENTS.md first, then docs/ai/AI_QUICK_CONTEXT.md (token saver entry point).
- Keep docs/index.md and docs/guides/context-governance.md handy for navigation rules.
- Use g, g --files, or git status before editing large areas to stay efficient.

## Workflow
- **Security:** CSRF tokens, PurifierHelper::purify(), and never commit .env or real secrets.
- **Database:** Always go through pp/Models/*, use prepared statements, and select explicit columns (SELECT id, name, ...).
- **Code Organization:** Controllers route requests, models handle DB, helpers live in pp/Helpers/*, and views render with Twig ({{ ... | e }}).
- **Assets:** Edit sources under src/ or public_html/assets/ and rebuild (
pm run build). Do not touch public_html/assets/**/dist/** directly.
- **Responses & Logging:** Catch exceptions near controllers, log via logError/logActivity, and return { "success": true/false, ... } with the right HTTP code.
- **Verification:** Run php -l, php scripts/quality_scan.php, 
pm run lint (if JS changed), 
pm run check:assets (if assets changed).

## Links
- docs/guides/coding-standards.md (single source for security, DB, architecture)
- docs/guides/coding-conventions.md (naming, routes, autoloading)
- docs/index.md (catalog of guides, integrations, editors, and plans)
- docs/editors/index.md (list of editor-specific documents)
- docs/ai/AI_QUICK_CONTEXT.md (token-saving quick context)
