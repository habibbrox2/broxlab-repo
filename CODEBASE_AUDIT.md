# BroxLab Codebase Audit

Audit date: 2026-09-17 · Scope: `app/`, `routes/`, `resources/views/`, `public/`, config, CI/CD
Stack: Laravel 12 · PHP 8.2 · MySQL/MariaDB · Blade · Tailwind 4 · Alpine.js · esbuild/Vite

Scale: 94 PHP classes under `app/` (~16k lines), 120 Blade views (~17k lines), 470-line
`routes/web.php`, 991 tracked files, 17 test files.

---

## 1. Executive summary

The strangler-fig migration is in good shape: controllers are thin, business logic lives in
`app/Support/*Service` classes, i18n/language detection, RBAC, TOTP 2FA, sitemaps and the
admin CMS all have real implementations. The problems are concentrated in three places:

1. **A committed production database password and a public database-admin tool** (`public/_db.php`).
2. **Runtime `env()` calls combined with `config:cache` in the deploy script** — several features
   are silently disabled or unauthenticated in production.
3. **Bypassed safety rails**: GET-based deletes, a stub `t()` that makes 878 translation call
   sites inert, a test suite wired to the production database, and a dependency that is not
   declared in `composer.json`.

Nothing here is unsalvageable, and most fixes are small. The work is in sequencing them.

Priority key: **P0** = do today · **P1** = this sprint · **P2** = planned cleanup.

---

## 2. P0 — Security

### 2.1 Committed production DB credentials + public DB admin tool
`public/_db.php` is **tracked in git** and contains the live password in plaintext:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'tdhuedhn_broxbhai');
define('DB_PASS', ',EnTio1PtqI-&M&D');
define('DB_NAME', 'tdhuedhn_broxbhai');
```

It is also a 3,010-line database browser/exporter that sits in the web root and handles
`POST` actions (`exportInit`, `exportChunk`, drop-table flags, `.sql` file writes) with **the
authentication block commented out** — lines 12–16 show the session/role guard disabled. The
file is reachable at `https://<host>/_db.php`, so the entire production database can be read,
exported and modified by anyone who finds the URL.

Suggested improvements:
- Rotate the MySQL password immediately; assume it is compromised.
- Delete `public/_db.php` from the working tree **and** from git history (`git filter-repo` or
  BFG), then force-push and rotate any other secret in the same blast radius.
- Move database tooling out of the web root entirely; if it must exist, require a real auth
  guard and place it behind the `auth` + `admin` middleware or an SSH tunnel.
- Add a CI guard that fails on high-entropy/credential-shaped strings in `public/`.

### 2.2 Delete routes are reachable over GET (18 of them)
`routes/web.php` registers admin destructive actions as `GET`, e.g.:

```php
Route::get('/admin/posts/delete/{id}',   [AdminPostController::class, 'destroy']);
Route::get('/admin/tags/delete/{id}',    [TagCategoryController::class, 'tagDestroy']);
...
```

`AdminPostController::destroy()` genuinely deletes (soft delete + attachment cleanup) and is
routed for both GET and POST. GET requests are **not** CSRF-protected in Laravel, so a crafted
`<img src="https://site/admin/posts/delete/42">` on any page an admin visits can delete content;
browser prefetchers and crawlers can trigger the same effect.

Suggested improvements:
- Split `deleteConfirm` (GET, renders a confirmation view) from `destroy` (POST/DELETE only).
  The mobiles/service/user modules already do this — apply the same shape everywhere.
- Keep the legacy `?id=` forms working but route them through the POST variant.
- Add a test asserting no state-changing route is registered on `GET`.

### 2.3 Medicines API is unauthenticated in production
`MedicinesController::requireApiAuth()` reads the shared secret with `env()`:

```php
$expectedToken = trim((string) env('MEDEX_REFRESH_TOKEN', ''));
```

`web-host/scripts/deploy.sh` runs `php artisan config:cache`. After the config is cached the
`.env` file is no longer loaded, so `env()` returns `null` and this becomes `''` at runtime.
The method then falls through to its "no token configured" branch, which only requires CSRF
for `POST` — and **allows every `GET` unauthenticated**, including
`/api/medicines/refresh`, which rewrites the JSON data file and takes a backup.

Suggested improvements:
- Move `MEDEX_REFRESH_TOKEN` into `config/services.php` (or a dedicated `config/medex.php`)
  and read it with `config()`. Add a test that boots with a cached config.
- Fail closed: if no token is configured, reject writes rather than downgrading to CSRF-only.
- Never accept the refresh trigger as `GET`; make it `POST`-only.

### 2.4 Outstanding unauthenticated write endpoints
- `/comment/add|edit|delete|react|like` are public `POST` routes with no `throttle`
  middleware. Comment bodies are purified, but there is no rate limit, so the endpoints are a
  spam and write-amplification vector (each comment also inserts notification + activity rows).
- `/donate` and `/newsletter/subscribe` are public `POST` routes without throttling.

Suggested improvements: add named rate limiters (e.g. `comments: 10/min per IP+user`,
`donate: 5/min per IP`) and apply them in `AppServiceProvider::configureRateLimiting()`
alongside the existing `login`/`register` limiters.

### 2.5 TLS verification disabled on a payment-gateway call
`DonationController::queryBkashPayment()` sets `CURLOPT_SSL_VERIFYPEER, false`. The gateway
response decides whether a donation is confirmed, so a MITM can forge a "completed" status.

Suggested improvement: enable peer + host verification; if the host's CA bundle is the problem,
point `CURLOPT_CAINFO` at a valid bundle rather than disabling the check.

---

## 3. P1 — Correctness bugs

### 3.1 `$_SESSION['role']` is always `'user'` (dead code overwrites the lookup)
In `LegacySessionGuard::createNativeSession()` the roles are queried…

```php
$roles = DB::table('user_roles as ur')->join('roles as r', ...)->pluck('r.name')->all();
```

…then immediately discarded:

```php
$roles = array_values(array_filter(explode(',', (string) ($row->roles ?? ''))));
```

`$row` comes from a `select('id','username','email','first_name','last_name')`, so `$row->roles`
does not exist and `explode(',', '')` yields `[]`. Every Laravel login therefore writes
`$_SESSION['role'] = 'user'` and `$_SESSION['roles'] = []` into the **shared legacy session**,
which the legacy app reads for permission checks.

Suggested improvement: delete the second assignment, keep the query result, and add a
regression test asserting a user with the `admin` role gets `$_SESSION['role'] === 'admin'`.

### 3.2 Draft posts are publicly readable by slug
`PostsService::postBySlug()` intentionally omits the `published` filter ("legacy parity") and is
what `PostController::view()` uses for `/posts/view/{slug}`. Unpublished drafts are reachable
by anyone who knows or guesses the slug — and slugs are surfaced in the admin permalink UI.

Suggested improvement: keep the legacy method for compatibility but add an explicit
`WHERE published = 1` on the public route (or gate on `auth()->check() && isAdmin`).

### 3.3 The dashboard's "Pending Reviews" card counts all comments
`AdminDashboardService::pendingReviews()` returns `totalComments()`; the comment in the code
calls this a legacy query bug "kept as-is for parity". A card labelled *Pending Reviews* that
shows total comments is misleading to admins.

Suggested improvement: implement the real count (e.g. `status = 'pending'`) and drop the parity note.

### 3.4 Duplicate aggregate queries per dashboard render
`AdminDashboardController::index()` calls `$this->dashboard->serviceStats()` and `paymentStats()`
twice each — once inside `serviceAndPaymentStats()` and again directly below. That is 4 duplicate
query groups on the heaviest admin page.

Suggested improvement: compute both once, pass the arrays into `serviceAndPaymentStats()`, or
memoise the two methods on the service with `??=`.

### 3.5 `isAdmin` view global relies on assignment-in-expression
```php
$view->with('isAdmin', auth()->check()
    && (($authUser = auth()->user()) && ($authUser->is_super_admin ?? false)
        || ($authUser->role === 'admin')));
```
Precedence, an inline assignment inside a boolean expression, and a `role` column the codebase
elsewhere documents as unused all combine here. The result is effectively "super admin only".

Suggested improvement: extract a single `isAdmin()`/`isSuperAdmin()` helper on the `User` model
(or `UserProfileService`, which already has `hasRole`) and call it from both the composer and
`EnsureAdmin`, so the two definitions cannot drift.

---

## 4. P1 — Production configuration and deploy

### 4.1 Runtime `env()` calls are dead once `config:cache` runs
Eleven call sites read `env()` outside `config/`, and the deploy script caches config:

| File | Impact in production |
|---|---|
| `MedicinesController.php:399` | API auth silently degrades (see 2.3) |
| `FcmService.php:24` | `FIREBASE_SERVICE_ACCOUNT` ignored; falls back to a path |
| `MailService.php:71` | from-address host becomes `localhost` |
| `MedicinesDataService.php:748` | refresh TTL always the hardcoded default |
| `DonationController.php:231-239` | bKash mode/key/token env overrides never apply |

Suggested improvement: move all of these into `config/` files (`config/services.php`,
`config/medex.php`, `config/firebase.php`) and reference them with `config()`. This is the
canonical Laravel rule and it is currently broken in exactly the environment that matters.

### 4.2 Every push to `main` deploys to production with no gates
`.github/workflows/deploy.yml` triggers on `push: branches: [main]` and goes straight to
`appleboy/ssh-action`. There is no `composer validate`, no test run, no Pint/ESLint gate, and no
environment approval — a commit that passes PHP lint is live.

Suggested improvements:
- Add a `verify` job (`composer validate --strict`, PHP syntax lint, `npm run lint`,
  `npm run type-check`, `php artisan test`) and make `deploy` depend on it.
- Gate production behind a GitHub Environment with required reviewers.
- Keep `--skip-build`/rollback flags documented; the script already has good locking and
  rollback scaffolding to build on.

### 4.3 Deploy script mutates `.env` with `sed`
`ensure_env_secret` / `ensure_env_setting` append and `sed -i` the shared `.env` on every
deploy. It works, but it makes the production environment non-reproducible and hard to diff.

Suggested improvement: generate a fully-formed `.env` from a template plus a secret store, or at
minimum log a unified diff of the change.

---

## 5. P1 — Feature gaps

### 5.1 `t()` is a stub, so the i18n layer is inert
`app/Support/helpers.php:13`:

```php
function t(string $key): string { return $key; }
```

That helper is called **878 times** across the Blade views. Meanwhile `LanguageService`
(cookie + session + `Accept-Language` detection), `TranslationService` (JSON dictionaries +
cached translation), `/lang/{code}` and `/api/translate` are all fully built. The result: a
user can switch to Bengali, the cookie is set, the URL changes — and every string still renders
in English. Given the Bengali-speaking audience this is the largest user-visible gap.

Suggested improvements:
- Wire `t()` to `App\Support\TranslationService::translate()` (memoised per request, with the
  JSON dictionary as the first tier so the common case never hits the network).
- Seed the `en`/`bn` dictionaries from the existing `old/` translation files under
  `old/system/translations/`.
- Have the view composer pass a real `siteTranslations` map instead of `[]` (see 5.3).

### 5.2 `stichoza/google-translate-php` is used but not declared
`TranslationService` imports `Stichoza\GoogleTranslate\GoogleTranslate`. The package exists in
the local `vendor/`, but **it appears nowhere in `composer.json` or `composer.lock`**. Deploy
runs `composer install` in a fresh clone, so in production the class does not exist;
`new GoogleTranslate(...)` throws, the `catch (Throwable)` swallows it and `report()`s an
exception on every uncached translation.

Suggested improvements: either `composer require stichoza/google-translate-php` (and commit the
lock update), or remove the dependency and rely on the JSON dictionaries. Then let an error
surfacing path (not a silent `catch`) handle translator outages.

### 5.3 View-composer globals are hardcoded placeholders
`AppServiceProvider::boot()` shares `siteTranslations => []`,
`availableLanguages => ['en','bn']` and `publicNavItems => []` for every view. The header falls
back to a hardcoded `$navItems` array, so nav renders — but any DB/legacy-driven menu or CMS
translation never reaches the template.

Suggested improvement: back these with `AppSettings` (or a small `NavigationService`) and cache
them, then drop the duplicated hardcoded fallbacks.

### 5.4 Thirteen admin controllers are 23-line `app_settings` boilerplate
`AdminAiSystemController`, `AdminScraperController` (4 actions), `AdminRevenueController`,
`AdminCalculatorController`, `AdminCvController`, `AdminKharijController`, `AdminLiveTvController`,
`AdminLogsController`, `AdminOcrController`, `AdminPhotoStudioController`, `AdminSecurityController`,
`AdminSetupController`, `AdminWeatherController` all repeat:

```php
$appSettings = DB::table('app_settings')->first()?->toArray() ?? [];
return view('...', ['title' => ..., 'header_title' => ..., 'appSettings' => $appSettings]);
```

This also duplicates a **query per render** that the `View::composer('*')` already performs.

Suggested improvements:
- Extract a `RendersAdminPage` trait or `AdminPageController` base with a `staticPaged($view, $title)`
  helper, or route these directly to a single controller with a view-map.
- Drop the per-controller `app_settings` query; rely on the shared `appSettings` view global.

---

## 6. P2 — Architecture and maintainability

- **`MedicinesDataService` is 1,333 lines** and mixes HTTP fetching, JSON file IO, backups,
  validation and response shaping. Split into a fetcher, a store, and a presenter.
- **`AuthController` is 778 lines** with 16 actions — extract login/2FA/verification flows into
  smaller invokable controllers or service methods.
- **`routes/web.php` is 470 lines of flat registration** with heavy `?id=` legacy duplication.
  Group by module into `routes/admin.php` + `routes/public.php`, and consider a
  helper that registers the path-form and query-form variants together.
- **Models are near-empty**: `Post` and `Comment` set `protected $guarded = [];` (fully open
  mass assignment) with no relations or casts, while `PostsService`/`CommentService` hand-write
  joins. `$guarded = []` is dangerous the moment a model is fed `$request->all()`.
  Prefer explicit `$fillable`, then move reads onto Eloquent scopes as modules stabilise.
- **`DB::raw()` fragments are repeated** across `ArchiveService`, `HomeFeedService`,
  `PostsService` (the same `0 AS views, 0 AS impressions` and `type` literals). Centralise the
  feed projection in one place.
- **`PostsService::relatedPosts()`** uses `ORDER BY RAND()`-style selection
  (`FLOOR(RAND() * MAX(id))`) plus a second fallback query. Fine at current volume, but it is a
  full-scan pattern that will not age well — consider a `WHERE id > :random` keyset pick.
- **`User` model is still the Laravel skeleton** (`$fillable = ['name','email','password']`,
  `email_verified_at` cast) while the app actually uses `first_name`, `last_name`, `username`,
  `is_super_admin`, `status`. Add the real attributes, casts and relations so static analysis
  and IDE completion stop failing.

## 7. P2 — Testing

- **The test suite points at the production database.** `phpunit.xml` hardcodes
  `DB_DATABASE=tdhuedhn_broxbhai` with the production credentials, and no test uses
  `RefreshDatabase`/`DatabaseMigrations` — the comments state tests "read the SHARED legacy
  MySQL schema… Tests must clean up any rows they insert." Running `php artisan test` from a
  laptop writes to live data.
  - Suggested: stand up a disposable MySQL/SQLite schema for tests (seeded from a schema dump),
    use `RefreshDatabase`, and move credentials to CI secrets only.
- **No coverage for the highest-risk paths**: no tests for GET-delete routes, for `$_SESSION`
  role propagation (3.1), for the medicines API auth branches, or for the bKash callback
  signature verification. `SecurityFlowsTest` exists — extend it.
- **17 test files for 94 classes / 470 route lines** is thin; the admin CRUD modules (posts,
  pages, mobiles, services, users, RBAC) have partial coverage via `AdminLegacyQueryRoutesTest`.

## 8. P2 — Frontend and repo hygiene

- **`npm run lint` currently fails**: 124 problems (64 errors, 60 warnings) in
  `public/assets/js/`, dominated by `no-var` and `prefer-template`. 58 are auto-fixable with
  `--fix`. Because `npm run validate` chains lint, the whole validation script is red today.
- **The frontend is split across three build systems** — esbuild scripts (`build/esbuild-*.mjs`),
  Vite (`vite.config.js`, `build:laravel`) and Tailwind CLI. Worth documenting which output each
  Blade template expects; `TASK_DOCUMENTATION.md` already records a 404/MIME-type incident
  caused by exactly this ambiguity.
- **`README.md` documents `old/` as part of the repo** ("The legacy Twig-based application…
  preserved in `old/`"), but `old/` is gitignored and not tracked — 0 of 991 tracked files.
  A fresh clone has no reference codebase. Correct the README or track the migration docs.
- **`.tmp.driveupload/` (~30 MB) is present in the working tree** as an untracked Google Drive
  sync artifact; add it to `.gitignore`.
- **README claims counts that have drifted**: "15 PHPUnit tests" (now 17), "114 Blade views"
  (now 120), "11 configuration files" (now 12).

---

## 9. Suggested sequencing

| Order | Work | Rationale |
|---|---|---|
| 1 | Rotate DB password, purge `public/_db.php` from git history | Credential is live and public |
| 2 | Move `env()` → `config()`, fix medicines auth to fail closed | Silently broken in production |
| 3 | Convert GET deletes to confirm-GET + POST-delete | CSRF hole on admin data |
| 4 | Add CI verify job + environment gate for deploys | Prevents regressions reaching prod |
| 5 | Fix `$_SESSION['role']` dead code, draft-post exposure, double dashboard queries | Real user-facing bugs |
| 6 | Wire `t()` to `TranslationService`; seed en/bn dictionaries | Largest visible feature gap |
| 7 | Isolate the test database | Protects production data |
| 8 | Add throttles to comment/donate/newsletter writes; enable TLS verification | Hardening |
| 9 | Collapse the 13 stub admin controllers; split `MedicinesDataService`/`AuthController` | Maintainability |
| 10 | `eslint --fix`, README/count corrections, `.gitignore` cleanup | Cheap wins |
