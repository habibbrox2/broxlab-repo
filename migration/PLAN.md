# BroxLab → Laravel + Blade + Alpine.js Migration Plan

**Status:** Active · **Strategy:** Strangler Fig (incremental, legacy stays live) · **Started:** 2026-09-05

This document is the single source of truth for migrating the legacy BroxLab PHP application
(procedural controllers + Twig + mysqli) to **Laravel + Blade + Alpine.js**.

Companion files:

- `migration/CHANGELOG.md` — what was done, when, and how to verify it
- `migration/REMAINING_STEPS.md` — the live checklist of not-yet-done work

---

## Migration Guardrails (Non-Negotiable)

These rules apply to **every** migration task, every phase, and every commit until cutover is complete.

1. **Frontend UI design is frozen.** Do not alter colors, spacing, layout, fonts, icons, component structure, or user-facing behavior of migrated pages. Port existing legacy UI to Blade/Alpine with visual parity; defer any visual improvements to Phase 7 or later.
2. **Database structure is frozen.** Do not add, rename, drop, or alter columns, indexes, or tables. No new migrations that touch schema. All work uses the existing shared tables exactly as they are.
3. **Improvements only.** If you must change something to make the Laravel port work, keep the change as close to parity as possible. Do not refactor legacy behavior, rewrite queries "better," or change business rules.
4. **Test gate after every phase.** Before marking a phase complete: `php artisan test` must pass in `laravel/`, and a live smoke test through `public_html/index.php` must confirm both migrated routes render and legacy routes still fall through.
5. **Fast migration.** Complete one module end-to-end before starting the next. Do not branch into multiple simultaneous migrations unless explicitly asked. Keep PRs focused and small.

---

## 1. Why we are migrating

| Concern | Legacy (today) | Target (Laravel) |
|---|---|---|
| Controllers | 50 files, procedural closures, routes embedded | PSR-4 controller classes, routes in `routes/web.php` |
| Routing | Custom regex router (`app/Router/Router.php`) | Laravel Router (middleware groups, named routes, caching) |
| DB access | mysqli + hand-written prepared statements in 44 models | Eloquent ORM (relations, scopes, mass assignment, migrations) |
| Templating | 270 Twig templates, custom filters/macros | Blade (components, layouts, `@php`, slots) |
| Frontend interactivity | Vanilla JS + custom esbuild bundles | Alpine.js (declarative, minimal JS), Vite |
| Validation | Hand-rolled per route | Laravel FormRequest / `Validator` |
| Auth | Custom `AuthManager` + sessions + 2FA | Laravel Auth + Sanctum/Fortify-compatible approach (custom guards OK) |
| CSRF | Custom token in `Config/Functions.php` | `@csrf` built-in |
| Emails | PHPMailer via `EmailHelper` | Laravel Mail (keep PHPMailer transport if needed) |
| Queue/jobs | None / ad-hoc | Laravel Queues (FCM push, emails, scraping) |
| Tests | Near zero | Pest/PHPUnit feature + unit tests per module |
| Deploy | Custom web-host scripts | Same scripts; add `artisan migrate` step |

## 2. Current state (verified inventory)

- **PHP 8.2+, MySQL/MariaDB**, Composer + Node available.
- **50 controllers** in `app/Controllers/` — each file is a set of `$router->get/post/...` closures.
- **44 models** in `app/Models/` — mysqli prepared statements, explicit columns, `deleted_at IS NULL` soft-delete filters everywhere.
- **26 helpers** (`app/Helpers/`), **14 services** (`app/Services/`), **2 middleware** files, **9 config** files.
- **270 Twig views** in `app/Views/` (admin, user, auth, public, components, partials, macros).
- **Custom router** with middleware (auth, guest_only, rate_limit, CSRF, `permission:` RBAC checks).
- **Auth**: email/password + Firebase OAuth (Google/Facebook/GitHub), remember-me cookies, email verification, TOTP 2FA, RBAC roles/permissions.
- **Database**: single MySQL schema, **table prefix `bb_`**, soft deletes universal, 82 schema files (now gitignored; schema lives in DB).
- **Frontend**: Tailwind CSS (custom build via esbuild), lucide icons, vanilla JS bundles, custom RTE (`rtceditor/`).
- **i18n**: English/Bangla (`t()` helper, session-based language).
- **Deploy**: GitHub Actions → `web-host/scripts/deploy.sh`, docroot `public_html/`.

## 3. Target architecture

```
laravel/                          # New Laravel 12 application (this repo, subdirectory)
├── app/
│   ├── Http/Controllers/         # One class per legacy controller file
│   ├── Http/Middleware/          # auth, guest, rate_limit, permission, csrf
│   ├── Models/                   # Eloquent models (users, contents, services, ...)
│   ├── Services/                 # Ported business logic from app/Services
│   ├── View/Components/          # Blade components (navbar, footer, cards, ...)
│   └── Support/                  # Facades/helpers that replace legacy app/Helpers
├── routes/
│   ├── web.php                   # Public + user routes (migrated)
│   └── admin.php                 # Admin routes (migrated)
├── resources/views/              # Blade templates (1:1 with legacy Twig tree)
│   ├── layouts/
│   ├── public/  auth/  user/  admin/  components/  partials/
├── database/migrations/          # Eloquent migrations matching legacy schema
├── public/                       # Laravel public (symlinked assets to ../public_html)
└── bridge.php                    # Route-delegation allowlist for legacy front controller
```

**Runtime model (strangler fig):**

1. `public_html/index.php` stays the entry point.
2. A small **bridge** at the top of `public_html/index.php` checks the request URI against the
   allowlist in `laravel/bridge.php`. If matched → `require laravel/public/index.php` and exit.
3. Every request that is **not yet migrated** continues through the legacy router unchanged.
4. As modules migrate, their paths are added to the allowlist; legacy controller files shrink
   or are deleted.

## 4. Guiding principles

1. **Never break production.** Every migration step must leave the app runnable; legacy routes keep working.
2. **Same database.** Eloquent reads/writes the same MySQL tables (with `bb_` prefix). No data migration.
3. **One module at a time**, each fully working end-to-end (route → controller → model → view → JS) with a smoke test before moving on.
4. **Reuse, don't rewrite.** Port helper logic into Laravel services/components; keep Tailwind class usage and design language identical.
5. **i18n preserved.** `t()` → a `__()`-style Bangla/English translation service backed by the same language session.
6. **Soft deletes preserved.** Every Eloquent model uses `SoftDeletes`; queries default to `deleted_at IS NULL`.
7. **Tests before big modules.** Each migrated module ships a Pest/PHPUnit feature test.

## 5. Directory & concept mapping (legacy → Laravel)

| Legacy | Laravel |
|---|---|
| `public_html/index.php` | front controller + `laravel/bridge.php` delegation |
| `app/Router/Router.php` | `routes/web.php`, `routes/admin.php` |
| `app/Controllers/XxxController.php` (closures) | `app/Http/Controllers/XxxController.php` (classes) |
| `$router->get('/x', ['middleware' => ['auth']], ...)` | `Route::get('/x', ...)->middleware('auth')` |
| `app/Models/XxxModel.php` (mysqli) | `app/Models/Xxx.php` (Eloquent + `SoftDeletes`) |
| `app/Helpers/` global functions | `app/Support/*` classes / Blade components / Laravel services |
| `app/Views/*.twig` | `resources/views/*.blade.php` |
| `app/Views/layout.twig` | `resources/views/layouts/app.blade.php` |
| Twig `{% extends %}` / `{% block %}` | Blade `@extends` / `@section` or components |
| Twig `{{ }}` auto-escape | Blade `{{ }}` auto-escape |
| Twig `|raw` | Blade `{!! !!}` |
| Twig macros | Blade components |
| `csrf_token` + hidden input | `@csrf` / `@method` |
| `$_SESSION['flash_message']` | `session()->flash()` / `with('success', ...)` |
| `AuthManager::getCurrentUserId()` | `auth()->id()` (custom guard until auth module is ported) |
| `app_settings` Twig global | `AppSettings` service / `@php` view composer |
| `t('string')` Bangla/English | `trans()` with `lang/{en,bn}.json` or a `LanguageService` |
| `withAssetVersion('/assets/...')` | Vite `asset()` / custom `AssetVersion` blade directive |
| `sendEmail(...)` | `Mail::to(...)->send(...)` or retained `MailHelper` service |
| `logActivity(...)` | `ActivityLog` service (same `activity_logs` table) |
| `build/esbuild-*.mjs` | Vite (`vite.config.js`) — legacy bundles stay untouched |
| `rtceditor/` | Keep as-is; load via script tag (out of migration scope) |

## 6. Phase roadmap

### Phase 0 — Foundation ✅ (completed 2026-09-05)
- [x] Survey codebase, write plan, changelog, remaining-steps
- [x] Scaffold Laravel in `laravel/` (composer create-project → Laravel 12.69.1)
- [x] `.env` pointing at the same MySQL DB (tables are unprefixed — see §9)
- [x] Alpine.js via npm + Vite (bundle → `public_html/assets/laravel/dist/app.js`)
- [x] Bridge in `public_html/index.php` delegating migrated paths (`laravel/bridge.php`)
- [x] Base Blade layout reusing legacy Tailwind bundles

### Phase 1 — Static/public pages ✅ (completed 2026-09-05)
- [x] `about-us`, `faq`, `terms`, `privacy`, `newsletter` (+ `POST /newsletter/subscribe`)
- [x] AppSettings + stats read via Eloquent/query builder
- [x] Smoke test through legacy entry point; CHANGELOG/REMAINING_STEPS updated
- [x] Welcome email + admin push on subscribe (2026-09-05)
- [x] FCM invalid-token cleanup on UNREGISTERED (TokenCleanupService, 2026-09-05)

### Phase 2 — Auth (login, register, logout, forgot/reset password) ✅ (2026-09-05)
- [x] `LegacySessionGuard` reading the shared legacy native PHP session + `StartLegacySession` middleware — login via Laravel is a login in legacy and vice versa (verified live both ways)
- [x] Login/register/logout + forgot/reset password (Blade views, `password_resets` SHA-256 tokens, template email)
- [x] Feature tests (12 passing)
- [x] Port 2FA (TOTP), email verification send/verify, remember-me (2026-09-05 — see CHANGELOG)
- [ ] Firebase OAuth (Google/Facebook/GitHub) login + account linking
- [ ] Session claims (guest CVs, FCM token migration) as event listeners

### Phase 3 — User dashboard & profile ✅ (completed 2026-09-05)
- [x] Dashboard, settings, profile edit/password, notifications inbox

### Phase 4 — Content/blog (read side ✅, write side remaining)
- [x] Home `/`, posts list/detail, categories/tags archives, comments list+submit
- [x] Mobiles catalog (list + detail with specs/gallery)
- [ ] Comment reactions/edit/delete endpoints (legacy-owned for now)
- [ ] Media uploads (MediaController) via Laravel Storage
- [ ] Content CRUD (admin write side lives in Phase 5)

### Phase 5 — Admin (largest chunk)
- [x] Admin layout + dashboard (2026-09-05 — see CHANGELOG)
- [x] Categories & tags admin CRUD (2026-09-06 — see CHANGELOG)
- [ ] Content, pages, mobiles, services, applications, users, RBAC, notifications, revenue
- [ ] Scrapping pipeline, analytics, logs

### Phase 6 — Specialized modules
- [ ] MedEX, Weather, Live TV, Calculator, OCR, Photo studio, CV builder, Kharij, Sitemap
- [ ] AI system (AISystem module), RTE integration

### Phase 7 — Hardening & cutover
- [ ] Remove bridge; make `laravel/public` the docroot
- [ ] Queues for email/push/scraping; cache; rate limiting
- [ ] Full test suite green; performance pass
- [ ] Update deploy scripts (`artisan migrate --force`, build Vite)

### Phase 8 — Legacy removal
- [ ] Delete `app/Controllers`, `app/Router`, Twig views, `app/Models` (mysqli), custom helpers
- [ ] Final cleanup, docs update

## 7. Module migration order (priority queue)

Ranked by (risk ↓, coupling ↓, value ↑). The queue is maintained in `migration/REMAINING_STEPS.md`.

| # | Module | Controllers | Routes (approx.) | Priority |
|---|---|---|---|---|
| 1 | Static pages (pilot) | PageController (subset) | 5–6 | ✅ done (2026-09-05) |
| 2 | Auth | AuthController | ~25 | high (enables admin/user) |
| 3 | User dashboard/profile | Dashboard, Profile, Settings, Notifications | ~20 | ✅ done (2026-09-05) |
| 4 | Content/posts (public read) | PostsController, HomeController | ~30 | ✅ done (home + list + detail + archives, 2026-09-05) |
| 5 | Comments & reactions | CommentController | ~12 | 🟡 list + add migrated (react/edit/delete remain legacy) |
| 6 | Categories/tags/pages CMS | TagsCategories, Pages | ~20 | 🟡 public archives done (CRUD remains) |
| 7 | Mobiles + specs | MobilesController | ~15 | 🟡 public catalog done (CRUD remains) |
| 8 | Services + applications | Services, AdminServices | ~18 | medium |
| 9 | Media/upload | MediaController | ~12 | medium |
| 10 | Notifications (admin) | NotificationController | ~25 | medium |
| 11 | Users & RBAC (admin) | UserController, RbacController | ~30 | medium |
| 5a | Admin layout + dashboard | DashboardController (admin) | 3 | ✅ done (2026-09-05) |
| 12 | Revenue/monetization | MonetizationController | ~12 | medium |
| 13 | Admin logs/security/setup | AdminLogs, AdminSecurity, AdminSetup, AppSettings | ~15 | low |
| 14 | Scraper pipeline | ScraperApiController | ~12 | low |
| 15 | Specialized (MedEX, Weather, LiveTV, OCR, PhotoStudio, CV, Kharij, AI, Sitemap) | ~15 controllers | ~60 | low |
| 16 | API endpoints (mixed, analytics, pexels, pixabay, puter, firebase) | ~8 | ~30 | low |

## 8. Conversion recipes

### 8.1 Route
```php
// Legacy
$router->get('/about-us', function () use ($twig) {
    echo $twig->render('public/about-us.twig', ['title' => 'About Us']);
});
// Laravel
Route::get('/about-us', [PageController::class, 'about']);
```

### 8.2 Controller
```php
// Legacy: closure with use() globals
// Laravel
class PageController extends Controller
{
    public function about(): View
    {
        return view('public.about-us', ['title' => 'About Us']);
    }
}
```

### 8.3 Model (mysqli → Eloquent)
```php
// Legacy
$stmt = $this->mysqli->prepare("SELECT id, name, email FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param('i', $id); $stmt->execute();
return $stmt->get_result()->fetch_assoc() ?: null;
// Laravel
class User extends Authenticatable { use SoftDeletes; }
User::where('id', $id)->first();
```
Table prefix `bb_` is configured in `config/database.php` (`'prefix' => env('DB_PREFIX', '')`),
so Eloquent and raw queries target the same physical tables.

### 8.4 Twig → Blade
| Twig | Blade |
|---|---|
| `{% extends 'layout.twig' %}` | `@extends('layouts.app')` |
| `{% block content %}` / `{% endblock %}` | `@section('content')` / `@endsection` |
| `{{ var }}` | `{{ $var }}` |
| `{% if x %}` / `{% for y in z %}` | `@if($x)` / `@foreach($z as $y)` |
| `{{ t('Hello') }}` | `{{ __('Hello') }}` (or `@lang`) |
| `{{ item.body\|raw }}` | `{!! $item->body !!}` (only after Purifier) |
| `{% include 'x.twig' %}` | `@include('x')` |
| macros | `@component` / blade components |
| `{{ app_settings.site_name }}` | `$appSettings['site_name']` via view composer |

### 8.5 Middleware mapping
| Legacy | Laravel |
|---|---|
| `auth` | `auth` (custom guard over legacy session until Phase 2) |
| `guest_only` | `guest` |
| `rate_limit` | custom `RateLimit` middleware / `throttle` |
| `csrf` (per-route) | `web` group default |
| `permission:module.action` | custom `CheckPermission` middleware (`can:` alternative) |
| `admin` role check | `role:admin` middleware |

### 8.6 Auth/session compatibility
Until Phase 2 ports auth, the bridge should **not** delegate authenticated-only routes.
`AuthManager::getCurrentUserId()` reads the legacy session; the Laravel custom guard can read the
same session key so both apps agree on "who is logged in" during the transition.

## 9. Database strategy

- **Single shared MySQL DB**, unchanged schema.
- **Verified 2026-09-05:** although the legacy `.env` declares `DB_PREFIX=bb_`, the physical
  tables are **unprefixed** (`users`, `posts`, ...). The Laravel mysql/mariadb config
  therefore hardcodes `'prefix' => ''` — do not rely on the env var (a shell-exported
  `DB_PREFIX=bb_` silently overrides dotenv).
- Eloquent migrations are written to be **idempotent and additive** — never alter/drop legacy
  columns until cutover. No migrations have been run so far (file session/cache, sync queue:
  no new tables needed).
- All Eloquent models: explicit `protected $table`, `SoftDeletes` where the table has
  `deleted_at`, explicit `$fillable`, casts for booleans/dates.
- Legacy tables keep their names/columns; new tables (if any) use standard Laravel naming.

## 10. i18n strategy

- Keep the legacy session key (`current_lang`, `en`/`bn`) so the bridge and legacy agree.
- Provide `app/Support/LanguageService` exposing `t(string $key): string` used by a `@t('...')`
  Blade directive and `t()` helper in PHP for ported code.
- Public static pages currently hardcode English strings with `t()` wrappers — port as-is.

## 11. Assets & frontend

- Legacy esbuild bundles (`tailwind-public.css`, JS dists, RTE) are **left untouched** and
  referenced from Blade with a `@assetVersion('/assets/css/dist/tailwind-public.css')` directive
  that mirrors `withAssetVersion()`.
- Alpine.js is added via npm (`alpinejs`) and bundled with Vite inside `laravel/` for new
  interactivity; no legacy JS is deleted until its page is migrated.
- Lucide icons stay (same class names `lucide lucide-*`).

## 12. Testing & verification

- `migration/CHANGELOG.md` records each step + how to verify.
- Per module: Pest/PHPUnit feature test hitting the route through the **legacy entry point**
  (i.e., the bridge path) to prove delegation works.
- Local run: `php -S localhost:8000 -t public_html` — migrated routes must render via Laravel,
  non-migrated routes must render via legacy.
- Gate before merging a module: `php artisan test` in `laravel/` + `npm run validate` at root.

## 13. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Bridge breaks legacy routes | Bridge only matches explicit allowlist; default pass-through |
| Session conflict legacy vs Laravel | Delegation for guest pages first; auth pages only after guard port |
| Twig→Blade escaping differences | Audit `\|raw` usages; only port to `{!! !!}` when Purifier applied |
| Eloquent vs legacy SQL behaviour | Keep raw queries in Eloquent via `DB::select` where semantics differ; compare with legacy in smoke tests |
| Asset versioning drift | Centralize `@assetVersion` directive reading `app_settings.asset_version` |
| Deploy complexity | Bridge is additive; deploy script gains an `artisan migrate --force` step only at cutover |
| Merge conflict state in repo (tailwind files) | Resolve/leave untouched — unrelated to migration; do not touch until user asks |

## 14. Rollback

- Every migration step is additive. To roll back a module: remove its paths from
  `laravel/bridge.php` allowlist → legacy routes handle them again (legacy controllers/views are
  **not deleted** until Phase 8).
- `git revert` of the module commit restores full legacy behaviour.

## 15. Definition of done (per module)

1. Routes registered in `routes/*.php`; legacy route for the same path removed from allowlist? No —
   **added** to allowlist.
2. Controller class, Eloquent models, Blade views, Alpine behavior complete.
3. Feature test green; smoke test through `public_html/index.php` passes.
4. CHANGELOG entry + REMAINING_STEPS checkboxes updated.
5. No legacy files deleted (until Phase 8).