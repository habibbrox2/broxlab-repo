# Windsurf Instructions - BroxLab

## Setup
- Begin with `AGENTS.md`, `docs/ai/AI_QUICK_CONTEXT.md`, and `docs/index.md` to understand the repo facts and where Windsurf sits.
- Keep the Editor context small: search first (`rg`), open only the files that matter, and keep the working copy limited to 1-3 files.

## Workflow
- Edit source files (`src/`, `app/`, `public_html/assets/`); do not touch `public_html/assets/**/dist/**` directly.
- Keep controllers focused on routing, push database logic into models, and render HTML through Twig templates with proper escaping.
- Run CSRF/auth checks on every state-changing route, and verify changes with `php -l`, `php scripts/quality_scan.php`, and the relevant JS/asset checks (lint/check:assets).

## Links
- `docs/editors/rules-base.md`
- `docs/index.md`
- `docs/guides/coding-standards.md`
