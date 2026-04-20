---
name: broxlab-full-stack-dev
description: Work on BroxLab PHP, Twig, Node, prompts, and assets with minimal context.
license: Complete terms in LICENSE.txt
---

## BroxLab Skill

Use for any repo work:

- PHP app: `app/Controllers/`, `app/Models/`, `app/Helpers/`, `app/Middleware/`, `app/Views/`
- Routing: `app/Routes/Router.php`
- Entry: `public_html/index.php`
- Prompts: `system/prompts/`
- Node: `src/`
- Build: `build/`
- Frontend sources: `public_html/assets/`
- Generated bundles: `public_html/assets/**/dist/`

## Rules

- Reuse existing helpers and models first.
- Use prepared statements, explicit SQL columns, and CSRF on mutating requests.
- Never edit generated `dist/` files directly.
- Rebuild assets after source changes.
- Keep UI changes aligned with the existing section.

## Commands

- `php -l path/to/file.php`
- `npm run lint`
- `npm run type-check`
- `npm run test:run`
- `npm run check:assets`
- `npm run validate`
- `npm run build`
- `npm run build:prod`
- `npm start`
