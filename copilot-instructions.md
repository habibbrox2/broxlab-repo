# BroxLab Copilot Instructions

This file gives Kilo and Codex-style agents a compact, repo-specific operating guide.

## What To Read First

- `AGENTS.md`
- `README.md`
- `SKILL.md`
- `system/prompts/` when the task touches prompts or AI behavior

## Repo Rules

- Use the existing BroxLab architecture instead of inventing new patterns.
- Prefer existing helpers, models, services, and shared UI patterns.
- Use prepared statements and explicit SQL columns.
- Keep soft-delete filtering in queries where relevant.
- Validate all user input and keep CSRF protection on POST, PUT, and DELETE routes.
- Do not edit generated files under `public_html/assets/**/dist/`.
- Rebuild assets after changing frontend source files or build inputs.

## File Areas

- `app/Controllers/`: route handlers and request flow
- `app/Models/`: database access and persistence
- `app/Helpers/`: shared utilities and formatting logic
- `app/Views/`: Twig templates and page structure
- `app/Middleware/`: auth, CSRF, rate limit, and request guards
- `public_html/assets/js/`: frontend source scripts
- `public_html/assets/css/`: frontend source styles
- `src/`: Node and TypeScript services
- `build/`: bundling, linting, validation, and test tooling

## Workflow

1. Read the relevant files before editing.
2. Make the smallest change that solves the actual issue.
3. Keep UI and backend changes aligned across layers.
4. Verify syntax or lint errors as soon as possible.
5. Rebuild assets if any source file under `public_html/assets/` or `src/` changes.
6. Run the full validation gate before finishing.

## Validation

- PHP syntax: `php -l path/to/file.php`
- Lint: `npm run lint`
- Type check: `npm run type-check`
- Tests: `npm run test:run`
- Asset checks: `npm run check:assets`
- Full gate: `npm run validate`

## Style

- Be concise and direct.
- Reference exact file paths.
- Prefer concrete code over abstract explanation.
- Ask at most one short question if the task is genuinely blocked.
