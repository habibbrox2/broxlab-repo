---
name: broxlab-guardrails
description: AI Agent guardrails, project structure, and guidelines for BroxLab development
version: 2.3.0
---

# BroxLab - AI Agent Guardrails

**Version:** 2.3.0 | Enhanced with patterns, gotchas, and decision trees

Read first: [README.md](README.md) → [AGENTS.md](AGENTS.md) → [copilot-instructions.md](copilot-instructions.md)

---

## Project Map

| Layer | Location | Pattern |
|-------|----------|---------|
| **Entry Point** | `public_html/index.php` | PHP app bootstrap |
| **Routing** | `app/Controllers/*.php` (embedded in each controller) | Custom regex router, middleware-aware |
| **Backend Logic** | `app/Models/`, `app/Services/`, `app/Helpers/` | MVC: Models → Services → Helpers |
| **Views** | `app/Views/` (Twig templates) | Organized by area: `public/`, `admin/`, `user/`, `auth/` |
| **Middleware** | `app/Middleware/` | Auth, CSRF, rate limiting, etc. |
| **Database** | `Database/*.sql` (schemas) | Manual SQL files per table; soft deletes universal |
| **Node Service** | `src/` (TypeScript) | Unified AI, OCR, tools APIs on Fastify |
| **Build Tools** | `build/` (esbuild, eslint, vitest, playwright) | JS/CSS bundling, linting, testing |
| **Frontend Sources** | `public_html/assets/{js,css}/` | **Never edit `dist/`** (auto-generated) |
| **AI/Prompts** | `system/prompts/` | AI model configs and prompt templates |
| **Agent Skills** | `.ai/*.skill.md` | Workflow and task-specific skills for AI agents |

---

## Decision Trees

### "I need to add a new feature"

**Q: Is it backend logic (API, DB, user interaction)?**
- **Yes** → Create `app/Controllers/FeatureController.php` with routes and middleware
- Use Models for DB queries (prepared statements, explicit columns)
- Filter soft deletes: `WHERE deleted_at IS NULL`
- Test: `php -l app/Controllers/FeatureController.php` → `npm run validate`

**Q: Is it frontend UI (HTML, CSS, JavaScript)?**
- **Yes** → Edit sources in `public_html/assets/{js,css}/` (never `dist/`)
- Use kebab-case: `my-component.js`, `form-validator.css`
- Rebuild: `npm run dev` (watch) or `npm run build:prod` (release)
- Test: Run `npm run validate` before committing

**Q: Does it span frontend + backend?**
- **Yes** → Backend first (define API routes), then frontend (consume routes)
- Test backend with `npm run validate`
- Test frontend with unit tests and E2E tests

### "I need to debug or fix something"

**Q: Is the issue in PHP/backend logic?**
- **Yes** → Check error logs, add `var_dump()` or breakpoint in controller
- Verify DB queries with explicit columns and prepared statements
- Run: `php -l path/to/file.php` to check syntax

**Q: Is the issue in JS/frontend/styling?**
- **Yes** → Open DevTools, check Network tab for bundled assets
- If old CSS persists: Clear cache or rebuild with `npm run clean && npm run build`
- Check console for type errors: `npm run type-check`

**Q: Is the issue in the build or deploy?**
- **Yes** → Run `npm run validate` locally first
- Check asset naming: `npm run check:assets` (files must be kebab-case)
- Verify generated bundles exist in `public_html/assets/**/dist/`

---

## Critical Gotchas

| Gotcha | Impact | Fix |
|--------|--------|-----|
| **Editing `public_html/assets/**/dist/`** | Changes lost on rebuild | Edit source files only (`public_html/assets/js/script.js`, etc) |
| **Forgetting soft delete filter** | Deleted data exposed | Add `WHERE deleted_at IS NULL` to every SELECT |
| **Routes scattered in controllers** | Hard to find API surface | Use `grep -r "\$router->get\|post\|put" app/Controllers/` |
| **Raw SQL instead of prepared statements** | SQL injection risk; fails review | Always: `$stmt = $mysqli->prepare("..."); $stmt->bind_param(...); $stmt->execute();` |
| **Missing `{{ withAssetVersion() }}`** | Old CSS/JS cached after deploy | Use `{{ withAssetVersion('/assets/js/dist/script.js') }}` in Twig |
| **Naming convention violations** | Build fails at validation gate | PHP: PascalCase classes; JS/CSS/dirs: kebab-case files |
| **SELECT \* instead of explicit columns** | Security review blocker; performance issue | List columns: `SELECT id, name, email FROM table` |
| **Forgetting to rebuild after code changes** | Browser sees stale bundle | Run `npm run dev` (watch) or `npm run build:prod` before deploy |

---

## Rules (Non-Negotiable)

✅ **Do:**
- Use **prepared statements** with explicit SQL columns
- **Validate** all user input; sanitize HTML with PurifierHelper
- Keep **CSRF tokens** on all POST/PUT/DELETE
- **Reuse existing** helpers/models before adding new ones
- Run **`npm run validate`** before committing
- Use **kebab-case** for JS/CSS/dir names; **PascalCase** for PHP classes
- Filter **soft deletes**: `WHERE deleted_at IS NULL`

❌ **Don't:**
- Commit secrets (`.env` or API keys)
- Edit files in `public_html/assets/**/dist/` directly
- Use `SELECT *` (always list columns)
- Use raw SQL (always use prepared statements)
- Break working functionality without tests

---

## Verify Checklist

| Check | Command | Why |
|-------|---------|-----|
| PHP Syntax | `php -l path/to/file.php` | Catch parse errors early |
| Linting | `npm run lint` | Code style and quality |
| Type Safety | `npm run type-check` | TS/JS type errors |
| Unit Tests | `npm run test:run` | Logic correctness |
| Asset Validation | `npm run check:assets` | Naming conventions, duplicates |
| **Full Gate** | **`npm run validate`** | **All checks above (run before every commit)** |

---

## Common Workflows

### Backend Task (e.g., new API endpoint)
1. Create `app/Controllers/FeatureController.php`
2. Define route with middleware: `$router->post('/api/feature', ['middleware' => ['auth']], ...)`
3. Use `$models['TableName']` for DB queries (prepared statements)
4. Return JSON or Twig
5. Verify: `npm run validate` (catches syntax, types, linting)

### Frontend Task (e.g., new UI component)
1. Create `public_html/assets/js/my-component.js`
2. Import in Twig or main app script
3. Edit CSS in `public_html/assets/css/styles.css` (or create scoped file)
4. Rebuild: `npm run dev` (watch) or `npm run build:prod`
5. Never edit `dist/` files directly
6. Verify: `npm run validate`

### Database Task (e.g., add user preferences)
1. Create new SQL file: `Database/preferences.sql` (or extend existing)
2. Add migration: `ALTER TABLE users ADD column_name TYPE;`
3. Use explicit columns in queries: `SELECT id, name, preferences FROM users`
4. Filter soft deletes: `WHERE users.deleted_at IS NULL`
5. Index frequently queried columns
6. Test: `npm run test:run`

### Deployment Task
1. Verify: `npm run validate` passes locally
2. Rebuild assets: `npm run build:prod`
3. Commit to `main` branch
4. GitHub Actions triggers deployment via `web-host/scripts/deploy.sh`
5. Secrets required: HOST, USER, SSH_KEY_BASE64, REMOTE_BASE, SSH_PORT, KEEP_RELEASES
