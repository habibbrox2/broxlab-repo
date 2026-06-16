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

This project uses MVC with 50 Controllers, 51 Models, 25 Helpers, 244 Views, and 74 SQL schema files.

| Layer | Location | Role |
|-------|----------|------|
| **Bootstrap** | `public_html/index.php` | Static file serving, Composer autoload, app bootstrap |
| **Router** | `app/Routes/Router.php` | Custom regex router |
| **Controllers** | `app/Controllers/*.php` | 50 controllers with embedded routes + middleware |
| **Models** | `app/Models/*.php` | 51 models using Mysqli prepared statements |
| **Services** | `app/Services/` | Business logic |
| **Helpers** | `app/Helpers/*.php` | 25 shared utilities (purify, email, logging, etc.) |
| **Views** | `app/Views/` | 244 Twig templates (admin, user, public, auth, etc.) |
| **Middleware** | `app/Middleware/` | Auth, CSRF, rate limiting |
| **Modules** | `app/Modules/` | PdfTools, AISystem (multiple layers) |
| **Config** | `Config/` | Twig, DB, uploads, constants |
| **Database** | `Database/*.sql` | 74 files — one per table; soft deletes universal |
| **Frontend** | `public_html/assets/{js,css}/` | Source files — never edit `dist/` |
| **RTE** | `public_html/rtceditor/` | 16 JS source files + esbuild bundle |
| **AI Prompts** | `system/prompts/` | 8 prompt/config files |
| **Build** | `build/` | esbuild configs, scripts, tests |

## Complete Controller List

| Controller | Key Responsibility |
|------------|---------------------|
| `ActivityController.php` | User activity tracking |
| `AdminCvPersonalInfoController.php` | Admin CV personal info CRUD |
| `AdminCvTemplatesController.php` | Admin CV template management |
| `AdminLogsController.php` | Admin log viewer |
| `AdminNotificationTemplateController.php` | Notification template admin |
| `AdminSecurityController.php` | Admin security settings |
| `AdminServiceApplicationController.php` | Admin service application management |
| `AdminServicesController.php` | Admin service CRUD |
| `AdminSetupController.php` | Admin setup wizard |
| `AISystemController.php` | AI chatbot API endpoints |
| `AnalyticsController.php` | Analytics data endpoints |
| `AppSettingsController.php` | App-level settings CRUD |
| `AuthController.php` | Login, register, password reset, 2FA |
| `CalculatorController.php` | Calculator tools |
| `CommentController.php` | Comment CRUD + reactions |
| `ContentController.php` | Content management |
| `ContentRatingController.php` | Content rating system |
| `CvController.php` | CV builder user flows |
| `DashboardController.php` | User dashboard |
| `FeatureFlagController.php` | Feature flags API |
| `FirebaseController.php` | Firebase auth integration |
| `HomeController.php` | Home page data |
| `JobPositionController.php` | Job position CRUD |
| `LanguageController.php` | Language switcher |
| `MedexController.php` | MedEX: drug details, brands, companies |
| `MediaController.php` | Media file uploads/serving |
| `MixedApiController.php` | Miscellaneous API endpoints |
| `MobilesController.php` | Mobile device CRUD + specs |
| `MonetizationController.php` | Ads, sponsored, donations |
| `NotificationController.php` | Notification CRUD |
| `OCRController.php` | OCR (text extraction) |
| `PageController.php` | Single page view |
| `PagesController.php` | CMS pages CRUD |
| `PaymentController.php` | Payment processing (bKash, Nagad, Rocket) |
| `PexelsController.php` | Pexels image API proxy |
| `PhotoStudioController.php` | Photo studio / AI cutout |
| `PixabayController.php` | Pixabay image API proxy |
| `PostsController.php` | Blog posts CRUD |
| `ProfileController.php` | User profile management |
| `PuterProxyController.php` | Puter desktop proxy |
| `RbacController.php` | Role-based access control |
| `ScraperApiController.php` | Scraper pipeline API |
| `ServicesController.php` | Service listing + application |
| `SettingsController.php` | User settings |
| `SimRoutingController.php` | SIM routing for notifications |
| `SitemapController.php` | Sitemap generation |
| `TagsCategoriesController.php` | Tags + categories CRUD |
| `UserController.php` | User management (admin) |
| `UserSecurityController.php` | User 2FA, sessions, security |
| `WeatherController.php` | Weather display + API |

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
| **Editing `rtceditor/editor.bundle.js` directly** | Changes lost on rebuild | Edit source `.js` files in `rtceditor/`, then `npm run build:rte` to regenerate |
| **Loading `editor.js` instead of `editor.bundle.js`** | 11 separate HTTP requests instead of 1 | Use `editor.bundle.js?v={{ rte_version }}` in Twig templates (bundle replaces 11 separate files) |

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

---

## RTE Editor Migration Guide

The Rich Text Editor was restructured for performance. Key changes:

### Before (v2.3.0)
- **18 separate JS files** loaded at startup via `editor.js` → dynamic script injection
- 5 tiny helper files (ui, utils, keyboard, dragdrop, formatting) each required their own HTTP request
- `editor.debug.js` always loaded on the demo page
- Debug logging (`console.group`, `console.trace`, emoji-heavy `debugLog` calls) always included

### After (v2.4.0+)

| Change | Detail |
|--------|--------|
| **esbuild bundle** | 11 eager files → 1 minified `editor.bundle.js` (91.7 KB, 58.8% smaller) |
| **Lazy loading** | modals (723 lines), color (517), images (257) loaded **on first interaction**, not at startup |
| **Consolidated helpers** | 5 small helpers merged into `editor-core-essentials.js`; original files deleted |
| **Gated debug.js** | `editor.debug.js` only loads when `rte_debug` flag is `true` |
| **Gated logging** | Verbose `debugGroup`/`debugGroupEnd` removed; remaining debug calls gated behind `RTE_DEBUG=false` |

### File Structure

```
public_html/rtceditor/
├── editor.bundle.js          # 🏁 PRODUCTION BUNDLE (minified, 91 KB) — load this
├── editor.css                # Stylesheet (unchanged)
├── editor.js                 # Core class (included in bundle; standalone kept for backward compat)
├── editor-core-essentials.js # Consolidated from 5 deleted helpers
├── editor.toolbar.js         # Eager helpers (all bundled)
├── editor.selection.js       #
├── editor.block-formatting.js#
├── editor.normalization.js   #
├── editor.history.js         #
├── editor.views.js           #
├── editor.sanitize.js        #
├── editor.input.js           #
├── editor.figures.js         #
├── editor.debug.js           # Debug module — only loads when rte_debug=true
├── editor.modals.js          # 🔄 Lazy-loaded on first modal click
├── editor.color.js           # 🔄 Lazy-loaded on first color interaction
└── editor.images.js          # 🔄 Lazy-loaded on first image interaction
```

### How It Works

1. `editor.bundle.js` is loaded via `<script defer>` in the Twig template
2. The bundle contains all 11 eager source files concatenated and minified
3. When `RichTextEditor.loadHelpers()` runs, it discovers that all install functions are already defined on `window` (because the bundle defined them at load time), so it calls them **synchronously** without injecting any `<script>` tags
4. The 3 lazy modules (`modals`, `color`, `images`) are NOT in the bundle — they are loaded dynamically via `_loadLazyModule()` on first click/interaction

### How to Work With RTE

#### Edit source files
Always edit the individual `.js` files in `public_html/rtceditor/`, never edit `editor.bundle.js` directly.

#### Rebuild the bundle
```bash
# Production build (minified)
npm run build:rte

# Includes RTE in full production build
npm run build:prod
```

#### Update a Twig template that uses the editor
```twig
{% set rte_version = getRTEVersion() %}
<link href="/rtceditor/editor.css?v={{ rte_version }}" rel="stylesheet">
<script src="/rtceditor/editor.bundle.js?v={{ rte_version }}" defer></script>
{% if rte_debug is defined and rte_debug %}
<script src="/rtceditor/editor.debug.js?v={{ rte_version }}" defer></script>
{% endif %}
```

#### Enable debug logging
- Set `window.RTE_DEBUG = true` in the browser console
- Or pass `rte_debug: true` to the Twig template to load `editor.debug.js`
- Debug log output is tagged with categories: `[RTE:category:timestamp]`

### Deprecated / Removed

| File | Status | Replacement |
|------|--------|------------|
| `editor.ui.js` | 🗑 Deleted | `editor-core-essentials.js` |
| `editor.utils.js` | 🗑 Deleted | `editor-core-essentials.js` |
| `editor.keyboard.js` | 🗑 Deleted | `editor-core-essentials.js` |
| `editor.dragdrop.js` | 🗑 Deleted | `editor-core-essentials.js` |
| `editor.formatting.js` | 🗑 Deleted | `editor-core-essentials.js` |
| `editor.js` standalone | ⚠️ Deprecated | `editor.bundle.js` (still works but not recommended) |

### RTE Debug Gotchas

| Issue | Cause | Fix |
|-------|-------|-----|
| `debugGroup is not a function` | Old `editor.selection.js` still loaded | Rebuild bundle: `npm run build:rte` |
| Editor loads but toolbar buttons don't work | Old helper files still cached | Clear browser cache + hard reload (Ctrl+Shift+R) |
| Bundle not reflecting source changes | Forgot to rebuild | Run `npm run build:rte` after editing source files |
| Lazy module fails to load | Wrong `baseDir` detection (script lookup relies on `editor.js` substring match) | Ensure the `<script>` tag src includes `editor` in the filename |

### Troubleshooting

**Q: The bundle is too big (~91 KB). Can we make it smaller?**

The remaining debug logging calls (`RTE_debugLog` guarded by `RTE_DEBUG=false`) add ~2-3 KB of dead code. A future build-time strip step (e.g., esbuild `drop: ['console']` with a custom plugin) could remove them entirely for production.

**Q: Can I add a new helper module?**

Yes. Add a new `editor.new-module.js` file following the IIFE + `window.installXxx` pattern, then:
1. Add it to the `modules` array in `editor.js`'s `loadHelpers()` static method
2. Add the file path to the `EAGER_MODULES` array in `build/esbuild-rte.mjs`
3. Rebuild: `npm run build:rte`

**Q: How do I add a new lazy-loaded module?**

Add it to the `_lazyModules` map in `editor.js`'s `loadHelpers()` (key: path, value: install function name). Do NOT add it to the bundle's `EAGER_MODULES` array. It will be loaded dynamically on first access.
