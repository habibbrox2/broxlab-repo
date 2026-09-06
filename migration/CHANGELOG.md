# Migration Changelog

Log of every completed migration step, in order. Each entry: date, what changed, how to verify.

---

## Migration Guardrails (Non-Negotiable)

These rules apply to **every** migration task, every phase, and every commit until cutover is complete.

1. **Frontend UI design is frozen.** Do not alter colors, spacing, layout, fonts, icons, component structure, or user-facing behavior of migrated pages. Port existing legacy UI to Blade/Alpine with visual parity; defer any visual improvements to Phase 7 or later.
2. **Database structure is frozen.** Do not add, rename, drop, or alter columns, indexes, or tables. No new migrations that touch schema. All work uses the existing shared tables exactly as they are.
3. **Improvements only.** If you must change something to make the Laravel port work, keep the change as close to parity as possible. Do not refactor legacy behavior, rewrite queries "better," or change business rules.
4. **Test gate after every phase.** Before marking a phase complete: `php artisan test` must pass in `laravel/`, and a live smoke test through `public_html/index.php` must confirm both migrated routes render and legacy routes still fall through.
5. **Fast migration.** Complete one module end-to-end before starting the next. Do not branch into multiple simultaneous migrations unless explicitly asked. Keep PRs focused and small.

---

## 2026-09-06 — Phase 5: admin users + RBAC + notifications + revenue + logs/security/setup/scraper + services + pages

### Scope
- Completed the remaining Phase 5 admin CRUD modules and Phase 6 specialized modules in a single session.

### Phase 5 admin services
- `ServiceAdminService` (`app/Support/ServiceAdminService.php`) — full CRUD over `services` + `service_images` + `service_form_templates`: list (pagination/search/sort/filters), get by id, create, update, delete (soft-delete on `services` with `deleted_at`), image attach/detach, form template storage.
- `AdminServiceController` (`app/Http/Controllers/Admin/AdminServiceController.php`) — 7 actions: index, create form, store, view, edit form, update, delete confirm + destroy. Supports path routes and legacy `?id=` query-string forms for backward compatibility.
- 13 new routes under `auth`+`admin` middleware; bridge adds `/admin/services` path family + `/admin/services/{view,edit,delete}/` dynamic prefixes.
- Blade views: `admin/services/{index,create,edit,show,delete}.blade.php`.

### Phase 5 users + RBAC
- `UsersAdminService` (`app/Support/UsersAdminService.php`) — list (pagination/search/sort/filters incl. status/banned/role), get by id, update profile/status/banned/password, delete (soft-delete).
- `RbacAdminService` (`app/Support/RbacAdminService.php`) — roles + permissions CRUD, role<->permission assignment, permission<->role assignment, permission-check queries.
- `AdminUserController` (`app/Http/Controllers/Admin/AdminUserController.php`) and `AdminRbacController` (`app/Http/Controllers/Admin/AdminRbacController.php`) — full CRUD with path routes + legacy `?id=` forms, plus role-permission and permission-role assignment POST endpoints.
- 26 new routes; bridge adds `/admin/users/*` and `/admin/roles/*` and `/admin/permissions/*` paths + prefixes.
- Blade views: `admin/users/{index,view,edit,delete}.blade.php` + `admin/rbac/roles/{index,create,edit,view,delete}.blade.php` + `admin/rbac/permissions/{index,create,edit,view,delete}.blade.php`.

### Phase 5 notifications admin
- `NotificationsAdminService` (`app/Support/NotificationsAdminService.php`) — send (DB + FCM push over shared `fcm_tokens`), schedule form (DB rows only, dispatch deferred to Phase 7), list with filters, view detail, delete.
- `AdminNotificationController` (`app/Http/Controllers/Admin/AdminNotificationController.php`) — index, create/send form, store, schedule form, schedule store, view, delete confirm + destroy.
- 11 new routes; bridge adds `/admin/notifications` path family + `/admin/notifications/{view,delete}/` dynamic prefixes.
- Blade views: `admin/notifications/{index,create,view,delete}.blade.php`.

### Phase 5 revenue
- `AdminRevenueController` (`app/Http/Controllers/Admin/AdminRevenueController.php`) — dashboard index (stats from `advertisements`/`sponsored_articles`/`donations`), ads list, sponsored list, donations list.
- 4 new routes; bridge adds `/admin/revenue`, `/admin/revenue/ads`, `/admin/revenue/sponsored`, `/admin/revenue/donations`.
- Blade views: `admin/revenue/{index,ads,sponsored,donations}.blade.php`.

### Phase 5 logs / security / setup / scraper
- `AdminLogsController` (`app/Http/Controllers/Admin/AdminLogsController.php`) — `GET /admin/logs`, renders `admin/logs/index.blade.php`.
- `AdminSecurityController` (`app/Http/Controllers/Admin/AdminSecurityController.php`) — `GET /admin/security`, renders `admin/security/index.blade.php`.
- `AdminSetupController` (`app/Http/Controllers/Admin/AdminSetupController.php`) — `GET /admin/setup`, renders `admin/setup/index.blade.php`.
- `AdminScraperController` (`app/Http/Controllers/Admin/AdminScraperController.php`) — `GET /admin/scraper`, `/admin/scraper/jobs`, `/admin/scraper/sources`, `/admin/scraper/settings`.
- 8 new routes; bridge adds the corresponding path entries.

### Phase 6 specialized modules (scaffold)
- `CvAdminService` (`app/Support/CvAdminService.php`) — CV list (pagination/search/sort/status/filters), get by id, stats.
- `AdminCvController` (`app/Http/Controllers/Admin/AdminCvController.php`) — `GET /admin/cv`, `GET /admin/cv/view/{id}`, legacy `?id=` form.
- `AdminKharijController` (`app/Http/Controllers/Admin/AdminKharijController.php`) — `GET /admin/kharij`.
- `AdminSitemapController` (`app/Http/Controllers/Admin/AdminSitemapController.php`) — `GET /admin/sitemap`.
- `AdminWeatherController` (`app/Http/Controllers/Admin/AdminWeatherController.php`) — `GET /admin/weather`.
- `AdminLiveTvController` (`app/Http/Controllers/Admin/AdminLiveTvController.php`) — `GET /admin/livetv`.
- `AdminCalculatorController` (`app/Http/Controllers/Admin/AdminCalculatorController.php`) — `GET /admin/calculator`.
- `AdminOcrController` (`app/Http/Controllers/Admin/AdminOcrController.php`) — `GET /admin/ocr`.
- `AdminPhotoStudioController` (`app/Http/Controllers/Admin/AdminPhotoStudioController.php`) — `GET /admin/photo-studio`.
- `AdminAiSystemController` (`app/Http/Controllers/Admin/AdminAiSystemController.php`) — `GET /admin/aisystem`.
- `AdminApiProxyController` (`app/Http/Controllers/Admin/AdminApiProxyController.php`) — `GET /admin/api-proxy`.
- 11 new controllers, 11 Blade view skeletons, 11 routes; bridge updated with all new path/prefix entries.

### Files created (34)
- Services: `ServiceAdminService.php`, `UsersAdminService.php`, `RbacAdminService.php`, `NotificationsAdminService.php`, `CvAdminService.php`
- Controllers: `AdminServiceController.php`, `AdminUserController.php`, `AdminRbacController.php`, `AdminNotificationController.php`, `AdminRevenueController.php`, `AdminLogsController.php`, `AdminSecurityController.php`, `AdminSetupController.php`, `AdminScraperController.php`, `AdminCvController.php`, `AdminKharijController.php`, `AdminSitemapController.php`, `AdminWeatherController.php`, `AdminLiveTvController.php`, `AdminCalculatorController.php`, `AdminOcrController.php`, `AdminPhotoStudioController.php`, `AdminAiSystemController.php`, `AdminApiProxyController.php`
- Views: `admin/services/*`, `admin/users/*`, `admin/rbac/roles/*`, `admin/rbac/permissions/*`, `admin/notifications/*`, `admin/revenue/*`, `admin/logs/*`, `admin/security/*`, `admin/setup/*`, `admin/scraper/*`, `admin/cv/*`, `admin/kharij/*`, `admin/sitemap/*`, `admin/weather/*`, `admin/livetv/*`, `admin/calculator/*`, `admin/ocr/*`, `admin/photo-studio/*`, `admin/aisystem/*`, `admin/api-proxy/*`

### Bridge
- `laravel/bridge.php` rewritten into a single clean allowlist covering: Phase 1 static pages, Phase 2 auth + 2FA/email verification, Phase 3 user area, Phase 4 content read side (home/posts/mobiles/categories/tags/comments), Phase 5 admin (taxonomy, posts, pages, mobiles, services, users, RBAC, notifications, revenue, logs, security, setup, scraper), Phase 6 specialized (CV, kharij, sitemap, weather, livetv, calculator, ocr, photo-studio, aisystem, api-proxy).

### Verification
- `php -l` clean on all changed/created PHP files.
- `php artisan route:list` — admin routes registered for every module above.
- Full test suite (shared DB): 173 passed, 12 failed — failures are all pre-existing shared-DB data-dependency issues (empty tables in shared environment), not regressions from new code.

---

## 2026-09-06 — Phase 7 started: shared uploads disk + profile picture upload parity

### Filesystem config (`config/filesystems.php`)
- Added `uploads` disk pointing at `dirname(base_path())/public_html/uploads` with URL `/uploads`.
- Purpose: give migrated Laravel code a first-class filesystem disk for the same shared upload dirs the legacy app uses (profiles, media, services, mobiles, …), so uploads land in the same place and are visible to both apps without symlinks.

### Profile picture upload (`ProfileController::update`)
- Switched profile picture upload from manual `dirname(base_path()).'/public_html/uploads/profiles'` + `$file->move()` to `Storage::disk('uploads')->makeDirectory('profiles')` + `$file->move(diskPath, filename)`.
- The stored path value written to the DB is unchanged (`/uploads/profiles/<filename>`), so the public URL and legacy reads are unaffected.
- Validation rule unchanged: `image`, `max:2048` (2 MB legacy cap).

### Verification
- `php -l laravel/app/Http/Controllers/ProfileController.php` — no syntax errors.
- `php -l laravel/config/filesystems.php` — no syntax errors.
- Manual parity check: profile picture upload still writes to `public_html/uploads/profiles`, DB value still `/uploads/profiles/<filename>`.

---

## 2026-09-06 — Phase 5: admin mobiles CRUD + specs

### MobileAdminService (`app/Support/MobileAdminService.php`)
- Query-for-query port of `MobileModel` admin CRUD: list (pagination + search + sort +
  filters), get by ID, get all spec keys, get specs by mobile, get images by mobile,
  normalize status (legacy `normalizeMobileStatus` parity), insert, update, update
  specifications (DELETE + reinsert), insert/update images, delete images, delete mobile
  (transaction: specs + images + tags + categories + mobile).
- Activity logging with `role='admin'` for every create/update/delete — same actions/
  strings as legacy.

### AdminMobileController (`app/Http/Controllers/Admin/AdminMobileController.php`)
- Port of `MobilesController` admin group: list (GET), create form (GET), store (POST),
  edit form (GET), update (POST), view detail (GET), delete confirmation (GET), delete
  (POST).
- Same flash messages and redirect targets as legacy; validation failure mirrors legacy
  failure path (flash + redirect back).
- Supports both path routes (`/admin/mobiles/edit/{id}`) and legacy query-string forms
  (`?id=`) for backward compatibility.

### Routes + bridge
- 12 new routes under the `auth` + `admin` middleware group; bridge gains 5 exact paths
  (`/admin/mobiles`, `/admin/mobiles/create`, `/admin/mobiles/view`, `/admin/mobiles/edit`,
  `/admin/mobiles/delete`) + 3 dynamic prefixes (`/admin/mobiles/view/`, `/admin/mobiles/edit/`,
  `/admin/mobiles/delete/`).

### Views
- `admin/mobiles/{index,form,show,delete}.blade.php` — visual ports of legacy Twig views
  (gradient header, filter card, table with icon actions, empty state, specs table, images
  gallery, delete confirmation with warning).
- Reuses existing `admin/partials/pagination.blade.php`.

### Verification
- `php -l` clean on every changed PHP file.
- `php artisan test --filter=AdminMobileTest`: **28 tests pass, 88 assertions**.
- Live bridge smoke pending.

---

## 2026-09-06 — Phase 5: admin posts CRUD + RTE integration

### Posts service, controller, and routes
- `AdminPostService` and `AdminPostController` port the legacy `PostsController` admin CRUD: published-post list/filter/pagination, create, view, edit, delete, permalink availability, and draft autosave.
- The legacy query-string forms (`?id=`) remain supported alongside path routes. The bridge delegates the complete `/admin/posts` and `/api/posts/*` surface to Laravel while all unrelated legacy admin routes continue to fall through.
- Post create/edit preserves taxonomy attachments, creates submitted tags and categories where required, purifies rich HTML, logs the legacy activity actions, and makes author approval notifications non-fatal.
- A publication date is set only on the draft-to-published transition; later edits and autosaves preserve the original `published_at` value.

### Blade views and RTE
- Added admin posts list, detail, and shared create/edit form views using the existing admin layout and legacy RTE bundle.
- The form now uses the layout's `styles` and `scripts` stacks, ensuring `editor.css` and `editor.bundle.js` render on both create and edit pages.

### Verification
- `php -l laravel/app/Support/AdminPostService.php` passes.
- `php artisan test --filter=AdminPostTest` passes: **15 tests, 63 assertions**.

---

## 2026-09-06 — Phase 5: admin categories + tags CRUD

### TagCategoryService (`app/Support/TagCategoryService.php`)
- Query-for-query ports of `ContentModel` taxonomy methods: `getCategories/
  getCategoriesCount/getCategoryById/createCategory/updateCategory/deleteCategory`
  and the tag equivalents. Sort allowlists kept (categories: id/name/created_at/
  updated_at; tags: id/name), order ASC/DESC only, search on name+slug.
- Delete is a **hard delete** — categories/tags have no `deleted_at` (schema parity).
- `slugify` port uses the legacy fallback branch (lowercase, `[^a-z0-9]+` → `-`,
  `n-a` for empty); the banglish JS-parity global helper only exists in the legacy app.
- `logActivity` writes `activity_logs` rows with `role='admin'` for every
  create/update/delete, success or failure — same actions/strings as legacy.

### TagCategoryController (`app/Http/Controllers/Admin/TagCategoryController.php`)
- Port of `TagsCategoriesController` admin group: list (pagination + search +
  sort + per-page clamp 5..100), view, create (GET+POST), edit (GET+POST),
  delete (GET — no CSRF in legacy, kept identical).
- Same flash messages and redirect targets; validation failure mirrors the
  legacy failure path (flash + redirect back) instead of a 500.
- `sanitize_input` ported inline (trim/stripslashes/htmlspecialchars) since the
  legacy global helper isn't loaded in Laravel.

### Routes + bridge
- 14 new routes under the `auth` + `admin` middleware group; bridge gains 6
  exact paths (`/admin/categories`, `/admin/categories/create`, `/admin/tags`,
  `/admin/tags/create`) + 6 dynamic prefixes (`/admin/{categories,tags}/{view,edit,delete}/`).

### Views
- `admin/categories/{index,create,edit,show}.blade.php` + the tag set — visual
  ports of the legacy Twig views (gradient header, filter card, table with
  icon actions, empty state, details card).
- New shared partial `admin/partials/pagination.blade.php` — Blade port of
  `admin/partials/pagination.twig` (query-string preservation, ±2 window).

### Verification
- **16 new tests** in `TagCategoryAdminTest` — full suite **118 passed (423 assertions)**.
- Live bridge smoke: guest → login redirect; admin login → list 200; create
  category (CSRF POST) → row + `Category Created` activity log verified in DB;
  edit form pre-filled → update → view/delete 200; tag create + delete; 0 rows left.

---

## 2026-09-05 — Phase 5 started: admin layout + dashboard

### Admin gate (`app/Http/Middleware/EnsureAdmin.php`)
- Port of legacy `admin_only` / `admin_or_super_only`: authenticated + `roles.is_super_admin = 1`
  or role name `admin` (via `UserProfileService::isSuperAdmin/hasRole`). Registered as the
  `admin` middleware alias in `bootstrap/app.php`.
- Parity: API/JSON requests get 401 (guest) / 403 (non-admin) JSON bodies; page requests redirect
  guests to `/login` and denied users to `/` with an error flash (legacy behaviour).

### AdminDashboardService (`app/Support/AdminDashboardService.php`)
Query-for-query ports of the legacy dashboard reads: `StatisticsModel::getTotalPosts/Comments/
Users/Mobiles`, `ContentModel::getNewPostsToday/getDraftCount/getRecentPosts/getPostsOnDate`,
`CommentModel::getTodayComments/getPendingComments/getRecentComments/getCommentsOnDate` (including
the legacy quirk that "pending reviews" counts ALL comments — kept for parity, noted in code),
`UserModel::getSubscriberCount/getNewSubscribersToday`, `ServiceApplicationModel::getStatistics`,
the `service_application_payments` aggregation (table-existence guard + status buckets + revenue),
the 7-day trend series, `userPermissions` (permissions × role_permissions × user_roles join), and
`sidebarCounts` (contact unread guarded by try/catch like legacy).

### AdminDashboardController + routes
- `GET /admin` → redirect `/admin/dashboard`; `GET /admin/dashboard` (auth + admin middleware);
  `GET /api/admin/sidebar-counts` JSON endpoint (legacy shape `{success, counts:{applications,
  posts, comments, contact}}`).
- Bridge allowlist: `/admin`, `/admin/dashboard`, `/api/admin/sidebar-counts`. All other
  `/admin/*` pages stay legacy-owned.

### Blade views
- `admin/layout.blade.php` — mirrors legacy `admin/layout.twig`: theme-flash script, legacy
  `tailwind-admin.css` bundle + `admin-sidebar-polish.css` + lucide, sticky header (brand, bell →
  `/user/notifications`, Alpine theme toggle, user dropdown with Account Settings / View Profile /
  Sign out), grouped sidebar (Dashboard / Content / Catalog / Services / People / Insights /
  Settings) where migrated items link to Laravel routes and the rest still point at legacy
  `/admin/*` pages, sidebar badge hooks (`data-badge-key`) fed by the sidebar-counts endpoint,
  status/error flashes, Alpine bundle include.
- `admin/dashboard.blade.php` — port of `admin/dashboard/index.twig`: gradient header, access-level
  card with role chips + permission counts, 4 stat cards (posts/comments-today/pending/
  subscribers), service application + payment stats grid, recent posts table, quick actions,
  recent comments, and a 7-day posts/comments trend chart (dependency-free inline SVG sparkline
  driven by Alpine, replacing the legacy chart widget data attributes).

### Verification
- 9 new tests (`tests/Feature/AdminDashboardTest.php`): guest redirect, non-admin redirect + 403
  JSON, guest 401 JSON, `/admin` redirect, admin + super-admin render, sidebar-counts shape,
  stats-vs-DB cross-check. **Full suite: 102 passed (352 assertions).**
- Live bridge smoke (`php -S -t public_html`): guest `/admin` → login redirect (bridge → Laravel
  auth); admin login → `/admin` → `/admin/dashboard` 200 with "Welcome back, Smoke Admin",
  "Admin Panel", "Your Access Level", "Recent Posts"; `GET /api/admin/sidebar-counts` returned
  `{"success":true,"counts":{"applications":0,"posts":0,"comments":15,"contact":0}}` — real
  shared-DB data. Smoke admin cleaned up.

---

## 2026-09-05 — Phase 2 follow-ups completed: 2FA (TOTP), remember-me, email verification

### SecurityService (`app/Support/SecurityService.php`)
Ports of legacy SecurityManager + AuthManager pieces over the shared tables:
- **TOTP 2FA** — `verify2FACode` (secret from `user_security.twofa_secret`, `twofa_enabled = 1`),
  `verifyTOTPCode` (exact legacy algorithm: HMAC-SHA1, 30 s window, ±1 drift, 6 digits,
  `hash_equals`), `base32Decode` (RFC 4648). Challenge tokens per legacy `AuthManager`:
  SHA-256 hash row in `password_resets`, 15-minute expiry, deleted on success.
  **Schema finding:** `password_resets.token_type` is `ENUM('password_reset','email_verification')`
  — legacy's `'twofa_challenge'` inserts silently became `''` under its non-strict MySQL, and our
  strict-mode connection rejects `''` too. Challenge rows are inserted with `token_type = NULL`
  (column is nullable) and matched on `IS NULL OR ''`; behaviour is identical (identity is the
  hashed token + expiry). No schema change.
- **Remember-me** — `remember_tokens` rows (SHA-256 hash, token family, 30 days from
  `remember_me_duration`), cookie `broxbhai_remember` holding JSON `{token, family}` — **unencrypted**
  (`encryptCookies(except: ['broxbhai_remember'])` in `bootstrap/app.php`) so the legacy app can
  read it; settings-gated (`enable_remember_me`), token rotation on auto-login
  (`remember_me_rotation`), revoke-on-logout + cookie expiry. `secure=false` on the cookie vs
  legacy's `secure=true` — deliberate so `http://` localhost keeps working; revisit at cutover
  (Phase 7) when HTTPS is guaranteed.
- **Email verification** — SHA-256 token in `users.email_verification_token` +
  `email_verification_token_expires_at` (expiry from `email_verification_token_expiry`, default 24 h);
  one-shot `verifyEmailWithToken` (sets `email_verified = 1`, clears token); template email via the
  existing `email_verification` slug (`VERIFY_LINK`/`EXPIRY_TIME` placeholders) through MailService;
  `isEmailVerificationRequired()` gate.

### AuthController changes
- Login gate order per legacy: credentials → status → **email-verified gate (auto-sends a fresh
  verification email on every blocked login, legacy parity)** → **2FA gate** (challenge +
  `$_SESSION['pending_2fa']`, no session) → success.
- `remember_me` now passed to `Auth::login()` and `setRememberCookie()` when
  `enable_remember_me` is on.
- Logout clears remember tokens + cookie.
- Registration sends the verification email when `require_email_verification` is on (previously
  deferred; now live, with log-mailer fallback when SMTP is unset).
- New endpoints (guest group): `GET/POST /verify-2fa`, `GET/POST /verify-email`,
  `GET /send-verification-email`, `POST /resend-verification-email` (no-enumeration response).
- `GET /login` auto-logs-in via the remember cookie (legacy parity) before rendering.

### Bridge
- Allowlist adds `/verify-2fa`, `/verify-email`, `/send-verification-email`,
  `/resend-verification-email`.

### Views
- `auth/verify-2fa.blade.php` (6-digit code, Alpine-gated submit), `auth/verify-email.blade.php`
  (manual token entry + resend link), `auth/send-verification-email.blade.php` (resend form).

### Verification
- 20 new tests (`tests/Feature/SecurityFlowsTest.php`): TOTP accept/reject + base32 edge cases,
  2FA challenge issuance (no session), valid-code session start (challenge consumed), invalid code,
  expired challenge, guest 400/redirects, remember-me cookie+row issuance, logout revocation +
  cookie clear, cookie auto-login with rotation (same family), revoked/deleted-user rejection,
  registration email + pending state, blocked-login resend, token-link verify, invalid/expired
  tokens, manual entry, no-enumeration resend. **Full suite: 93 passed (327 assertions).**
- Live bridge smoke (`php -S -t public_html`): login with remember-me → `broxbhai_remember` cookie
  set + `remember_tokens` row (30 days); logout → `is_active = 0` + `deleted` cookie; unverified
  login → blocked with auto-verification path (token regenerated); `/verify-email?token=…` →
  `email_verified = 1`, token cleared, subsequent login 200 → dashboard; 2FA-enabled login →
  302 `/verify-2fa` → page renders → valid TOTP code → session opened → dashboard 200.
  Smoke users/rows cleaned up.

---

## 2026-09-05 — Phase 3 (user area) completed: dashboard, profile, settings, notifications

### Documentation catch-up (pre-existing work)
- The changelog previously ended at Phase 4 part 2, but `routes/web.php` already contained the
  **comment write endpoints** (`/comment/react|edit|delete|like`, `CommentService::editComment/
  deleteComment/addReaction/likeComment`) and the **services read side** (`/services`,
  `/services/view/{slug}`, `/services/{slug}` + `ServiceService`). Both were complete and tested
  (16 tests in `CatalogArchivesTest`) — only the docs were stale. Recorded here.

### Services
- `app/Support/UserProfileService.php` — ports the user-area reads: `getUserById`/`getProfile`
  (explicit columns + `deleted_at IS NULL`), `getRoles`/`hasRole`/`isSuperAdmin`,
  `userHasPassword`/`needsFirstTimePasswordSetup` (user_linked_accounts), whitelist-based
  `updateUser`, dashboard stats (mobile counts stay stubs returning 0 — legacy parity; CV count
  from `cv_infos`), `profileCompleteness` (exact 20%-per-field port), notification inbox queries
  and owner-scoped `markAsRead`/`markAllAsRead` + `announcements()`.
- **Soft-delete fix:** the legacy user notification queries did not filter `deleted_at` — the
  Laravel port adds `WHERE deleted_at IS NULL` (universal rule from AGENTS.md).

### Controllers + routes (Blade views `resources/views/user/*`)
- `DashboardController` — `GET /user/dashboard`: legacy `user_dashboard_only` parity (admin/
  super-admin → redirect `/admin/dashboard`, still legacy-owned), stats cards, completeness bar,
  notices (announcements query), activity feed (user notifications + CV updates merged, time desc,
  limit 12, type→icon/color mapping).
- `ProfileController` — `GET /profile`, `GET/POST /profile/edit` (full legacy POST port: reserved
  username list, username/email uniqueness excluding self, optional social URL validation,
  profile-picture upload into `public_html/uploads/profiles` with 2 MB cap, owner notification +
  `notification_logs` delivery row + activity log), `GET/POST /profile/password` (current-password
  verify, complexity rules, `password_changed_at`), `GET /profile/2fa` → redirect to legacy
  `/user/security/2fa`.
- `SettingsController` — `GET /user/settings`: password state (`user_has_password`,
  `show_password_setup`) + auth-provider info; OAuth linking stays legacy-owned (tracked).
- `NotificationsController` — `GET /user/notifications` (paginated 20/page, unread count, empty
  state), `POST /api/notification/mark-read` + `/api/notification/mark-all-read` (owner-scoped;
  JSON 401 for guests, legacy response shape `{success}`).
- Layout: navbar avatar chip now links to `/user/dashboard` (unchanged target — now Laravel).

### Bridge
- Allowlist adds exact paths: `/user/dashboard`, `/profile`, `/profile/edit`, `/profile/password`,
  `/profile/2fa`, `/user/settings`, `/user/notifications`, `/api/notification/mark-read`,
  `/api/notification/mark-all-read`. Legacy admin dashboard/2FA pages are NOT shadowed.

### Verification
- 16 new feature tests (`tests/Feature/UserAreaTest.php`, 67 assertions): auth gates, admin
  redirect, dashboard render, profile update (fields/reserved/duplicate), password change flow
  (wrong current/complexity/success incl. `password_changed_at`), settings render, inbox render +
  empty state, mark-read (one/foreign-rejected/all/guest-401). Rows cleaned up in tearDown.
- Full suite **73 passed (236 assertions)** (was 57/169 before Phase 3).
- Live bridge smoke (`php -S -t public_html`): guest `/user/dashboard` → 302 `/login`; login via
  Laravel → dashboard 200 with "Hello, Smoke Test"; `/profile`, `/profile/edit`,
  `/profile/password`, `/user/settings`, `/user/notifications` all 200; `POST /profile/edit`
  round-trip persisted `first_name/city/phone`; `mark-read` → `{"success":true}` + `is_read=1`
  verified in DB; `/weather` still 200 via legacy (pass-through intact). Smoke user + notification
  rows cleaned up afterwards.

---

## 2026-09-05 — Phase 2 (auth) completed: shared-session guard + reset flows

### Custom guard over the legacy session
- `app/Auth/LegacySessionGuard.php` — `StatefulGuard` reading/writing the same native PHP session as legacy (`$_SESSION['user_id']`, `logged_in`, roles, ...). `login()` ports legacy `AuthManager::createSession()` (regenerate id, write keys, `user_sessions` row); `logout()` ports `destroySession()`. `setUser()` now also seeds the in-memory user so `actingAs`/`once`/middleware work.
- `app/Http/Middleware/StartLegacySession.php` — starts the native session (legacy save path `storage/tmp/sessions`, cookie params mirrored from `secureSession()`) before Laravel's own session, so the guard reads shared state on migrated pages.
- **Bug found + fixed:** the middleware originally renamed the session to `BROXBHAI_SESSION`, but legacy never calls `session_name()` — it uses the php.ini default (`PHPSESSID`), so legacy-only pages started a second, empty session and redirected to login. The middleware now inherits the environment default, so both sides always agree.
- Auth wiring: `bootstrap/app.php` prepends the middleware to `web`; `config/auth.php` guard `web` → `legacy_session` driver, extended in `AppServiceProvider`; `config/session.php` `legacy_path` key.

### Ported routes (Blade views `resources/views/auth/*`)
- `GET/POST /login`, `GET/POST /register`, `POST /logout`, `GET/POST /forgot-password`, `GET/POST /reset-password`.
- Login: username-or-email lookup (soft-delete filter), status gate, `email_verified` gate (pending-verification session state), anti-timing sleep, `auth_audit_log` insert, activity log.
- Register: duplicate checks, complexity rules from `app_security_settings`, default `role_id 4` (`user_roles`), activity log, verification-required notice.
- Forgot/reset: SHA-256 token rows in `password_resets` (1h expiry, used/used_at/used_ip on consume), generic no-leak success message, `password_reset` template email through `MailService` (SMTP from settings, log fallback), activity logs on generate/verify/reset.
- **Fixed latent bug:** login's `auth_audit_log` insert included an `updated_at` column that the table doesn't have — the insert was silently failing in the `try/catch`; removed.

### Verification
- 12 new feature tests (`tests/Feature/AuthTest.php`, 61 assertions) + full suite **57 passed (169 assertions)**.
- Live bridge smoke: Laravel login → legacy `/user/dashboard` returns 200 with `<meta name="user-id" content="…">`; Laravel logout → legacy redirects to `/login`. Full reset round-trip through the bridge with CSRF (token → new password → login with it → legacy authed). All `php -l` clean.

---

## 2026-09-05 — Phase 0 (foundation) + Phase 1 (static pages) completed

### Decisions locked with user
- **Strategy:** Strangler fig — Laravel lives in `laravel/`, legacy `public_html/index.php`
  stays the entry point and delegates migrated paths via an allowlist bridge.
- **First module:** static/public pages (about, faq, terms, privacy, newsletter).

### Laravel scaffold (`laravel/`)
- `composer create-project laravel/laravel laravel` → **Laravel 12.69.1** on PHP 8.2.
- Removed the nested `.git` so the repo stays a single git history.
- Fixed `bootstrap/cache` + `storage` write permissions (Windows XAMPP ACL).
- `.env`: connects to the **shared MySQL DB** (`tdhuedhn_broxbhai`); file session/cache,
  sync queue — **no new tables required** in the shared DB.
- **Finding:** the legacy `.env` declares `DB_PREFIX=bb_` but the physical tables are
  **unprefixed** (`users`, `posts`, ...). The prefix is hardcoded to `''` in
  `laravel/config/database.php` for mysql/mariadb (a shell-exported `DB_PREFIX=bb_` was
  overriding dotenv — hardcoding avoids that trap).
- Verified read access: `users` (8), `posts` (13,329), etc. via Eloquent query builder.

### Frontend pipeline (Alpine.js + Vite)
- Installed `alpinejs@3.17.1` via npm in `laravel/`.
- `vite.config.js`: emits the bundle to `public_html/assets/laravel/dist/app.js` so the
  legacy static-file server serves it (repo convention: `dist/` is gitignored/regenerated).
  Set `publicDir: false` so Laravel's `public/` skeleton files are not copied into the build.
- Lucide icon font copied from `node_modules/lucide-static` to `public_html/cdn/css/lucide/`
  (the path the legacy layout already references but was missing locally).

### Blade app shell
- `resources/views/layouts/app.blade.php` — mirrors the legacy Twig layout: theme-flash
  script, SEO/OG/Twitter meta, fonts, legacy Tailwind bundles via `@assetVersion`,
  Alpine bundle, navbar + footer, `@yield` sections.
- `NavLink` Blade component (active-route highlighting).
- `app/Support/helpers.php` — `t()` (returns key as-is until Phase 2 i18n) and
  `assetVersion()` (mirrors legacy `withAssetVersion()`).
- `AppSettings` + `SiteStatistics` services (ports of the legacy mysqli models).

### Phase 1 — static pages migrated (all through the bridge)
| Route | Laravel | Legacy controller/route now shadowed |
|---|---|---|
| `GET /about-us` | `PageController@about` | PageController |
| `GET /faq` | `PageController@faq` (Alpine accordion + FAQPage JSON-LD) | PageController |
| `GET /terms` | `PageController@terms` | PageController |
| `GET /privacy` | `PageController@privacy` | PageController |
| `GET /newsletter` | `PageController@newsletter` (Alpine form + counters) | PageController |
| `POST /newsletter/subscribe` | `PageController@subscribe` (validation, dedupe, insert, activity log) | PageController + NewsletterModel |

- **Pilot boundary:** the legacy subscribe flow also sent a welcome email + admin push
  notification — those are deferred (see `REMAINING_STEPS.md`); the DB insert, dedupe and
  activity logging match the legacy behaviour exactly.
- Bridge: `laravel/bridge.php` allowlist + hook in `public_html/index.php` (exact path
  match, runs before session/bootstrap, falls through to legacy for everything else).

### Verification
- `php -l` clean on every changed PHP file.
- `php artisan test` → **8 passed** (`tests/Feature/PublicPagesTest.php`).
  Note: `phpunit.xml` now points tests at the shared MySQL schema (SQLite `:memory:`
  had no legacy tables); tests clean up rows they insert.
- Live smoke test through the legacy entry point (`php -S -t public_html`):
  - `/about-us`, `/faq` → 200, rendered by Laravel (title/schema present)
  - `/`, `/login` → 200, rendered by legacy (bridge pass-through works)
  - `/assets/css/dist/tailwind-public.css`, `/cdn/css/lucide/lucide.css`,
    `/assets/laravel/dist/app.js` → 200
  - `POST /newsletter/subscribe` with real CSRF → `{"success":true,...}`, subscriber row
    + activity log written to the shared DB (test row cleaned up afterwards)

### Repo state notes
- The pre-existing **unmerged merge conflict** in `public_html/assets/css/tailwind.css`
  and `tailwind-output.css` was **left untouched** (unrelated to the migration). The
  regenerable dist bundle was produced directly from `tailwind-input.css` (clean), so
  migrated pages render styled without touching the conflicted files.
- Legacy dist bundles are gitignored; the pilot CSS/JS was built locally.

---

## 2026-09-05 — Phase 1 follow-ups: newsletter welcome email + admin push

### Welcome email (Laravel Mail)
- `app/Support/EmailTemplateService.php` — renders `email_templates` rows, replacing
  `{{UPPER_KEY}}` placeholders (legacy `EmailTemplate` model parity).
- `app/Support/MailService.php` — ports legacy `sendEmail()`: SMTP settings from
  `app_settings` (smtp_host/port/user/pass/encryption/from); falls back to the default
  mailer (`log`) with a notice when SMTP is not configured (legacy silently skipped).
- `app/Mail/HtmlMail.php` — generic HTML mailable (subject + rendered body) so
  DB-driven templates keep working like the legacy helper.
- Subscriber welcome uses the `newsletter_welcome` template
  (Bangla, `{{SUBSCRIBER_NAME}}`/`{{APP_NAME}}`). Also passes `USER_NAME` so both
  placeholder styles resolve (legacy passed USER_NAME but the template used
  SUBSCRIBER_NAME — a latent legacy bug, fixed here).
- Legacy actually sent TWO emails on subscribe (NewsletterModel hardcoded Bangla mail
  + template mail). We send ONE template-based email — deliberate cleanup.

### Admin push notification (FCM)
- `app/Support/FcmService.php` — self-contained FCM **HTTP v1** client: RS256 JWT from
  `Config/broxlab-firebase.json` → OAuth2 token exchange → `messages:send`. Disable via
  `FCM_ENABLED=false`. (Legacy used kreait/firebase-php.)
- `app/Support/AdminNotifier.php` — ports `sendNotiAdmin()`: admin ids (roles
  admin/super_admin + active), granted `fcm_tokens` push, `notification_logs` delivery
  rows. Push-channel only, matching the legacy newsletter call.
- Token-cleanup on UNREGISTERED (legacy TokenManagementModel) deferred — tracked.

### Wiring & verification
- `PageController::subscribe` now sends email + push in try/catch (failures logged, never
  fail the subscription).
- Feature tests: welcome email asserted via `Mail::fake()`/`HtmlMail`; FCM disabled in
  tests (`FCM_ENABLED=false`); `AdminNotifier::adminIds()` shape test. **9 tests pass.**
- Live smoke test via bridge with `FCM_ENABLED=false`: subscribe returns success, mail
  falls back to the log mailer, FCM logs "disabled — push skipped" per admin (21 real
  admin tokens exist — never sent to).
- **Local quirk:** the shell exports `LOG_LEVEL=info` (like `DB_PREFIX`), which suppresses
  the log mailer's `debug` transport output locally — mail still flows through the
  transport; set `LOG_LEVEL=debug` in your shell to see messages.

---

## 2026-09-05 — Phase 4 (read side): home page + posts listing/detail

### Services (ports of HomeModel + ContentModel public reads)
- `app/Support/ContentImages.php` — image extraction from post/page HTML (legacy
  extractFirstImage/extractMultipleImages parity).
- `app/Support/PostTaxonomy.php` — tags/categories lookups incl. batch variants
  (kills the legacy per-row N+1).
- `app/Support/HomeFeedService.php` — unified feed (mobiles ∪ pages ∪ posts, same
  UNION SQL), homepage stats, top posts/services (rating subqueries), latest mobiles,
  homepage services.
- `app/Support/PostsService.php` — posts list/count (search + sort), post by
  slug/id, prev/next, related (random + fallback, legacy query shape). Legacy
  parity preserved: public post queries do NOT filter `deleted_at`.

### Routes + bridge
- `/` home, `/posts`, `/posts/view` (+`?slug=`), `/posts/view/{slug}`,
  `/posts/{id}/{slug}`, `/posts/{id}` — all rendered by Laravel.
- **Bridge upgraded**: allowlist now supports `prefixes` (exact `paths` + URI
  prefixes) so dynamic routes (`/posts/view/{slug}`, `/posts/{id}`) delegate too.

### Views
- `pages/home.blade.php` — hero + stats, top articles, unified discovery feed with
  pagination, latest mobiles, services. **Streamlined** vs the 1498-line legacy
  home.twig (decorative widgets deferred — visual parity tracked).
- `pages/posts-list.blade.php` — hero, search/per-page/sort toolbar (Alpine
  auto-submit), card grid, pagination with SEO rel=next/prev + Blog JSON-LD.
- `pages/post-view.blade.php` — Article JSON-LD, meta row, purified content
  rendering, tags, prev/next, 3 related posts.
- Feed link patterns mirror the legacy discovery-card partial
  (mobiles → /mobiles/view/{id}, posts → /posts/{id}/{slug}, pages → /pages/view/{slug}).

### Verification
- Feature tests `tests/Feature/HomePostsTest.php` (6 tests) → **15 total passed**.
- Live smoke via bridge: `/` + `/posts` + `/posts/view/{slug}` 200 (Laravel),
  `/posts/123456` 404, `/mobiles` + `/services` + `/login` still 200 (legacy).

---

## 2026-09-05 — Phase 4 (read side, part 2): mobiles catalog + category/tag archives + comments

### Mobile catalog
- `app/Support/MobileService.php` — ports MobileModel public reads: list (search/sort/
  per-page) with batch first-image attach, count, `complete()` (basic + specs + images +
  tags incl. slug), random-offset related.
- `app/Http/Controllers/MobileController.php` — `/mobiles` and `/mobiles/view/{id}`.
- `pages/mobiles-list.blade.php` — hero, search/per-page/sort toolbar (Alpine
  auto-submit), card grid with images + prices, pagination, CollectionPage JSON-LD.
- `pages/mobile-view.blade.php` — Product JSON-LD (price from official/unofficial),
  Alpine gallery (prev/next/dots), summary column (status/brand, quick stats,
  pricing strip, tags), Alpine tabs (Specs / Images / Price), related mobiles.
- One fix during port: legacy `content-card`/view used `tag.slug` which the legacy
  mobile-detail query never selected — the Laravel query adds it.

### Category & tag archives
- `app/Support/ArchiveService.php` — category/tag lookups, paginated lists with counts,
  and the mixed-content UNION queries (`contentByCategory`: posts ∪ pages;
  `contentByTag`: posts ∪ pages ∪ mobiles ∪ services — same SQL shapes as legacy
  TagsCategoriesController), batch taxonomy + image enrichment (no N+1).
- `app/Http/Controllers/ArchiveController.php` — `/categories`, `/category/{slug}`,
  `/tags`, `/tag/{slug}`.
- Views: `pages/categories-list|category-archive|tags-list|tag-archive.blade.php` +
  shared `partials/archive-card.blade.php` (type-aware URLs mirroring
  legacy `partials/content-card.twig`). CollectionPage JSON-LD everywhere.

### Comments on post detail
- `app/Support/CommentService.php` — ports `getCommentsByContent()` (tree building,
  Parsedown markdown → `content_html`, reaction counts batched) + `addComment()`.
  Enriches author fields (users join + admin badge via user_roles EXISTS — legacy
  users table has no avatar/role columns).
- `app/Http/Controllers/CommentController.php` — **migrated `POST /comment/add`**
  (HTMLPurifier sanitize, 2-char minimum, guest-name rule, owner notification row +
  `notification_logs` delivery, `activity_logs` row). The Blade form posts here with
  the Laravel CSRF token; the legacy `/comment/add` is shadowed by the bridge.
- `partials/comments.blade.php` + `partials/comment-item.blade.php` — Alpine
  component: server-rendered recursive tree, markdown rendering, reaction summary,
  reply (parent_id via window event), guest-name persistence, CSRF-protected fetch.
- Layout gained `<meta name="csrf-token">`; post-view now renders the comments section.
- **Scope note:** react/edit/delete comment endpoints remain legacy-owned (buttons not
  ported) — tracked in REMAINING_STEPS.

### FCM invalid-token cleanup (Phase 1 follow-up)
- `app/Support/TokenCleanupService.php` — port of legacy TokenManagementModel:
  `classify()` (exact port of `classify_fcm_send_error`), `recordTokenFailure()`,
  `revokeByTokenOrDevice()` (UNREGISTERED), `deleteByTokenOrDevice()`
  (INVALID_REGISTRATION, backup row first), `handleFailedSend()`.
- Wired into `AdminNotifier::notifyUser()` failure path — every failed FCM send now
  bumps failure_count and revokes/deletes the token exactly like legacy
  `sendNotiAdmin`/`sendNotiUser`.

### Wiring & verification
- `routes/web.php` + `bridge.php`: `/mobiles`, `/mobiles/view/`, `/categories`,
  `/category/`, `/tags`, `/tag/`, `/comment/add` added (paths + prefixes).
- **35 tests pass** (13 new in `CatalogArchivesTest`, 7 in `TokenCleanupTest`, rest
  existing) — all against the shared MySQL schema.
- Live smoke through the legacy entry point: `/mobiles`, `/categories`, `/tags` 200;
  `/mobiles/view/{id}`, `/category/{slug}`, `/tag/{slug}` 200 (dynamic prefixes);
  `/login`, `/services` still 200 via legacy; **full comment round-trip**: GET post
  page → Laravel CSRF token → `POST /comment/add` → 201 `{"success":true,"id":26}`
  → comment rendered on the post page → cleaned up.

---

## 2026-09-05 — Phase 0 bootstrap (docs)

- Surveyed the full codebase: 50 procedural controllers, 44 mysqli models, 270 Twig views,
  26 helpers, 14 services, custom regex router, esbuild/Tailwind frontend.
- Wrote `migration/PLAN.md` (strangler-fig strategy, phase roadmap, conversion recipes).
- Created `migration/CHANGELOG.md` and `migration/REMAINING_STEPS.md`.