---
name: broxlab-full-stack-dev
description: Full multi-step BroxLab development workflow for PHP, Twig, Node, prompts, and frontend assets
license: Complete terms in LICENSE.txt
---

## BroxLab Full Development Workflow

**For complete guidance:** Read [`CORE_RULES.md`](CORE_RULES.md) → [`AGENTS.md`](AGENTS.md) → [`README.md`](README.md)

### Quick Start

1. **Understand** the task scope and affected layers
2. **Read** the relevant code first
3. **Make** the smallest change that solves the issue
4. **Verify** syntax and lint immediately
5. **Rebuild** assets if frontend changed: `npm run build:prod`
6. **Validate** with `npm run validate` before finishing

### Layers & Locations

| Layer | Path | When To Edit |
|-------|------|--------------|
| Routes | `app/Controllers/` | New endpoints, HTTP logic |
| Data | `app/Models/` | Database access, queries |
| Utils | `app/Helpers/` | Reusable functions |
| Views | `app/Views/` | HTML/Twig templates |
| Frontend | `public_html/assets/` | UI, CSS, JavaScript |
| Node/TS | `src/` | Backend services |
| Prompts | `system/prompts/` | AI behavior, templates |

### Rules (Non-Negotiable)

✅ **Always:**
- Use prepared SQL statements (never raw SQL)
- List explicit columns in SELECT (never `SELECT *`)
- Filter soft deletes: `WHERE deleted_at IS NULL`
- Keep CSRF tokens on POST/PUT/DELETE
- Validate and sanitize user input
- Use kebab-case for JS/CSS file names
- Use `{{ withAssetVersion() }}` for asset links

❌ **Never:**
- Edit files in `public_html/assets/**/dist/` directly
- Use raw SQL or raw string concatenation
- Forget to rebuild: `npm run build:prod`
- Skip validation: `npm run validate`

### Prompts/Agents
- Edit files in `system/prompts/`
- Reference `CORE_RULES.md` instead of duplicating content
- Keep files concise and linked to core documentation

### Validation Checklist

```bash
npm run validate  # Runs all checks below
```

Individual checks:
- `php -l path/to/file.php` — PHP syntax
- `npm run lint` — Code style
- `npm run type-check` — TypeScript types
- `npm run test:run` — Unit tests
- `npm run check:assets` — Asset naming

### Decision Tree

**Is it backend logic?** → Focus on `app/Controllers/`, `app/Models/`, `app/Helpers/`

**Is it frontend UI?** → Focus on `app/Views/`, `public_html/assets/{js,css}/`

**Does it change the database?** → Use prepared statements, explicit columns, soft deletes

**Is it a prompt/agent update?** → Reference CORE_RULES.md, keep files thin

### Workflow Example (Backend API)

1. Read existing Model patterns
2. Create `app/Models/FeatureModel.php` with prepared statements
3. Create `app/Controllers/FeatureController.php` with routes + middleware
4. Add Controller route: `$router->post('/api/feature', ['middleware' => ['auth', 'csrf']], ...)`
5. Create view if needed: `app/Views/features/list.twig`
6. Add DB schema: `Database/feature_table.sql`
7. Validate: `npm run validate`

### Key Gotchas

| Issue | Fix |
|-------|-----|
| CSS not updating | Run `npm run clean && npm run build` |
| Routes not found | Use `grep -r "\$router->" app/Controllers/` to list all |
| Old JS cached | Hard refresh browser (Ctrl+Shift+R) |
| SELECT * in queries | Always list columns: `SELECT id, name FROM table` |
| Forgot soft delete filter | Add `WHERE deleted_at IS NULL` to WHERE clause |
| Building fails | Check kebab-case file names in `public_html/assets/` |

### Essential References

- **Minimal rules:** [`CORE_RULES.md`](CORE_RULES.md)
- **Architecture & patterns:** [`AGENTS.md`](AGENTS.md)
- **Project overview:** [`README.md`](README.md)

- **Prompt templates:** [`system/prompts/`](system/prompts/)

### Quick Commands

```bash
npm run dev              # Watch + rebuild (development)
npm run build:prod      # Production build (minified)
npm run lint            # Check code style
npm run type-check      # TypeScript validation
npm run test:run        # Run tests
npm run validate        # Full validation gate
php -l file.php         # Check PHP syntax
```
