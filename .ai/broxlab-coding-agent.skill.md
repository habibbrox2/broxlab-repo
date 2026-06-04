---
name: broxlab-coding-agent
description: End-to-end BroxLab coding workflow for PHP, Twig, JS, TS, MySQL, prompts, and build assets
---

# BroxLab Coding Agent

Use this skill for end-to-end BroxLab development tasks, including planning, implementation, validation, review, and delivery.

## When To Use

- Backend features and bug fixes
- Frontend UI, CSS, and JavaScript changes
- Database queries, schema updates, and migrations
- Prompt, skill, or agent instruction updates
- Build, lint, test, and validation work

## Operating Rules

- Read the relevant repo docs first: `AGENTS.md`, `README.md`, `SKILL.md`, and `copilot-instructions.md`.
- Inspect the actual files before changing them.
- Use existing helpers, models, and services before adding new ones.
- Use prepared statements, explicit columns, and soft-delete filters in SQL.
- Keep CSRF on mutating requests and validate all user input.
- Never edit generated `dist/` assets directly.
- Rebuild assets after editing frontend sources.
- Run `npm run validate` before finishing.

## Task Flow

1. Identify the affected layers.
2. Read the relevant code paths.
3. Make the smallest safe change.
4. Verify the result with targeted checks.
5. Run the full validation gate when the task is complete.

## Layer Guide

- Controllers, routes, middleware: `app/Controllers/`, `app/Middleware/`
- Data access: `app/Models/`
- Shared utilities: `app/Helpers/`
- Views: `app/Views/`
- Frontend source: `public_html/assets/js/`, `public_html/assets/css/`
- Node and build tooling: `src/`, `build/`
- AI prompts and agent assets: `system/prompts/`, `.kilo/`, `.ai/`

## Validation Checklist

- `php -l path/to/file.php`
- `npm run lint`
- `npm run type-check`
- `npm run test:run`
- `npm run check:assets`
- `npm run validate`

## Output Style

- Be concise and technical.
- Show exact file paths.
- Prefer code changes over long explanation.
- Ask one short question only if the task is blocked by missing information.
