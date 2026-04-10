# Cursor Instructions - BroxLab

## Setup
- Start by reading `AGENTS.md` and `docs/ai/AI_QUICK_CONTEXT.md` for repo context.
- Use `rg` to restrict searches to the smallest set of relevant files, then open the selected files in Cursor.

## Workflow
- Keep diffs minimal: update only what the ticket asks for, and avoid refactoring unrelated sections.
- When Cursor surfaces multiple code suggestions, prefer the one that matches existing patterns and naming conventions.
- Verify PHP/JS/asset changes with `php -l`, `php scripts/quality_scan.php`, `npm run lint`, and `npm run check:assets` as needed.

## Links
- `docs/editors/rules-base.md`
- `docs/index.md`
- `docs/guides/coding-conventions.md`
