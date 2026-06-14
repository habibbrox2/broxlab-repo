---
name: broxlab-full-stack-dev
description: Full multi-step BroxLab development workflow for PHP, Twig, Node, prompts, and frontend assets.
license: Complete terms in LICENSE.txt
---

## BroxLab Full Development Workflow

Use this skill for end-to-end BroxLab development: planning, implementation, validation, review, and delivery.

### 1. Understand the task

- Confirm the feature, bug, or enhancement scope.
- Identify the affected layers: backend logic, routes, Twig templates, Node services, frontend assets, or deployment.
- Review project docs and conventions: `AGENTS.md`, `README.md`, `copilot-instructions.md`, `system/prompts/`, and existing root `SKILL.md`.
- If the task changes user-facing flows, locate the relevant route and template before editing.

### 2. Locate the right files

- Backend controllers, services, and middleware: `app/Controllers/`, `app/Models/`, `app/Helpers/`, `app/Middleware/`
- Routing: `app/Routes/Router.php`
- Views and UI templates: `app/Views/`
- Entry and app bootstrap: `public_html/index.php`
- Node service logic: `src/`
- Build tooling and scripts: `build/`
- Frontend source assets: `public_html/assets/`
- System prompts and agent assets: `system/prompts/`

### 3. Apply BroxLab development rules

- Prefer existing helpers and models instead of adding new duplicates.
- Use prepared SQL statements with explicit column lists.
- Validate and sanitize user input everywhere.
- Preserve CSRF protection on all mutating actions.
- Do not modify generated bundles under `public_html/assets/**/dist/` directly.
- Keep UI/UX changes consistent with the current app design.

### 4. Implement incrementally

- Make focused changes with a single purpose per commit.
- Separate concerns: backend business logic in PHP, presentation in Twig, asset logic in JS/CSS.
- Add tests or update existing ones when appropriate.
- Document any non-obvious behavior in code comments or README notes.

### 5. Validate continuously

- PHP syntax check: `php -l path/to/file.php`
- Lint JS/TS: `npm run lint`
- Type check: `npm run type-check`
- Run tests: `npm run test:run`
- Check assets: `npm run check:assets`
- Full validation gate: `npm run validate`

### 6. Rebuild assets when needed

- After changing frontend sources in `public_html/assets/` or Node code in `src/`, rebuild using the project’s build tools.
- Verify generated output and ensure no direct edits were made in `public_html/assets/**/dist/`.

### 7. Review and finalize

- Review the full code path and user flow.
- Confirm no security, validation, or UI regressions were introduced.
- Keep the diff small and relevant to the task.
- Use the validation commands above before marking the work complete.

## Decision checkpoints

- Backend-only task? Focus on `app/Controllers/`, `app/Models/`, `app/Helpers/`, and `app/Middleware/`.
- Template/UI task? Focus on `app/Views/`, `public_html/assets/`, and the build pipeline.
- Database or query change? Use explicit columns and prepared statements.
- Prompt/agent update? Use `system/prompts/` and preserve existing prompt style.
- Release readiness? Run `npm run validate` and verify that assets are rebuilt.

## Example prompts

- "Use the BroxLab full development workflow to implement a backend bug fix and validate the changes."
- "Apply the BroxLab skill to review a UI enhancement across Twig, JS, and build assets."
- "Help me follow the BroxLab dev workflow for a feature that spans PHP, routes, and frontend assets."
