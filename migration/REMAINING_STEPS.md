# Remaining Steps (live tracker)

Check off items as they complete. Source of truth for what is left.
See `migration/PLAN.md` for strategy; `migration/CHANGELOG.md` for completed work.

---

## Migration Guardrails (Non-Negotiable)

These rules apply to **every** migration task, every phase, and every commit until cutover is complete.

1. **Frontend UI design is frozen.** Do not alter colors, spacing, layout, fonts, icons, component structure, or user-facing behavior of migrated pages. Port existing legacy UI to Blade/Alpine with visual parity; defer any visual improvements to Phase 7 or later.
2. **Database structure is frozen.** Do not add, rename, drop, or alter columns, indexes, or tables. No new migrations that touch schema. All work uses the existing shared tables exactly as they are.
3. **Improvements only.** If you must change something to make the Laravel port work, keep the change as close to parity as possible. Do not refactor legacy behavior, rewrite queries "better," or change business rules.
4. **Test gate after every phase.** Before marking a phase complete: `php artisan test` must pass in `laravel/`, and a live smoke test through `public_html/index.php` must confirm both migrated routes render and legacy routes still fall through.
5. **Fast migration.** Complete one module end-to-end before starting the next. Do not branch into multiple simultaneous migrations unless explicitly asked. Keep PRs focused and small.

---

## Phase 0 — Foundation ✅ (completed 2026-09-05)

- [x] Scaffold Laravel in `laravel/` (`composer create-project laravel/laravel laravel`), remove nested `.git`
- [x] Configure `laravel/.env`: same MySQL DB, file session/cache, sync queue (no new tables)
- [x] Table prefix: physical tables are **unprefixed** — hardcoded `''` in `config/database.php` (env var was overriding)
- [x] Add Alpine.js (`alpinejs@3.17.1`) + Vite; bundle → `public_html/assets/laravel/dist/app.js`
- [x] Bridge: `public_html/index.php` delegates allowlisted paths to `laravel/public/index.php`
- [x] Create `laravel/bridge.php` allowlist (Phase 1 paths)
- [x] Base Blade layout `resources/views/layouts/app.blade.php` reusing legacy Tailwind bundles
- [x] `@assetVersion` Blade directive mirroring `withAssetVersion()`

## Phase 1 — Static/public pages ✅ (completed 2026-09-05)

- [x] `GET /about-us` → Blade page (hero, story, stats, offers)
- [x] `GET /faq` → Blade page (Alpine accordion + FAQPage JSON-LD)
- [x] `GET /terms` → Blade page
- [x] `GET /privacy` → Blade page
- [x] `GET /newsletter` → Blade page (Alpine form + counters)
- [x] `POST /newsletter/subscribe` → validation, dedupe, insert, activity log (matches legacy)
- [x] AppSettings + site statistics via Laravel services (same DB)
- [x] Feature tests (8 passing) + live smoke test through the bridge

**Phase 1 follow-ups:**
- [x] Welcome email on subscribe (MailService + EmailTemplateService + SMTP from `app_settings`, log fallback) — done 2026-09-05
- [x] Admin push notification on subscribe (FcmService HTTP v1 + AdminNotifier, delivery logged) — done 2026-09-05
- [ ] Full `t()` i18n (en/bn) driven by the legacy session language key
- [x] FCM invalid-token cleanup (TokenCleanupService: recordTokenFailure → revoke UNREGISTERED / delete INVALID_REGISTRATION, port of TokenManagementModel) — done 2026-09-05
- [ ] FCM_ENABLED/env documentation + `.env.example` for laravel/ — deferred to Phase 7

## Phase 2 — Auth ✅ (completed 2026-09-05)

- [x] Custom Laravel guard over the shared legacy native PHP session (`LegacySessionGuard` + `StartLegacySession` middleware) — both apps agree on login state in any env
- [x] Port login (GET/POST), logout, register (GET/POST) — Blade views, activity + `auth_audit_log`, default `user` role, pending-verification session state
- [x] Forgot/reset password (GET/POST) — SHA-256 token rows in `password_resets`, 1h expiry, `password_reset` template email via MailService, generic no-leak message
- [x] Feature tests for auth flows (12 passing: login/register/logout/forgot/reset)
- [x] Email verification: send on register + on blocked login, verify-email (link + manual token), send-verification-email page, resend endpoint (no-enumeration) — done 2026-09-05
- [x] 2FA (TOTP) verify flow — challenge row + pending_2fa session + /verify-2fa — done 2026-09-05
- [x] Remember-me cookie — `broxbhai_remember` (unencrypted, legacy-readable), rotation, revoke-on-logout — done 2026-09-05
- [ ] Firebase OAuth (Google/Facebook/GitHub) login + account linking
- [ ] Guest CV claim + FCM token migration on login/logout (event listeners)
- [ ] Wire `t()` i18n to session language (en/bn)

## Phase 3 — User area ✅ (completed 2026-09-05)

- [x] Dashboard `/user/dashboard` (stats, profile completeness, notices, activity feed; admins redirected to legacy `/admin/dashboard`)
- [x] Profile view `/profile`, edit `/profile/edit` (GET/POST: reserved usernames, dedupe, picture upload → `uploads/profiles`), password `/profile/password` (GET/POST)
- [x] Settings `/user/settings` (password state + linked-account provider flags)
- [x] Notifications inbox `/user/notifications` (paginated, unread badges, Alpine mark-read)
- [x] `POST /api/notification/mark-read` + `mark-all-read` (owner-scoped JSON endpoints, legacy shape)
- [ ] Settings language/notification-preference writes (tabs still legacy where present)
- [ ] `/user/security/2fa` + `/user/sessions` (UserSecurityController — tracked with Phase 2 2FA item)

## Phase 4 — Content/blog (read side complete 2026-09-05)

**Mobile catalog (Phase 4 read side):**
- [x] Mobiles list `/mobiles` (search, per-page, sort, pagination, CollectionPage JSON-LD)
- [x] Mobile detail `/mobiles/view/{id}` (Product JSON-LD, Alpine gallery + tabs, specs table, tags, related mobiles)

**Category/tag archives + comments:**
- [x] Categories list + category archive
- [x] Tags list + tag archive
- [x] Comments list + submit on post detail


- [x] Home page `/` (hero + stats, top posts, unified feed w/ pagination, latest mobiles, services)
- [x] Posts list `/posts` (search, per-page, sort, pagination, SEO rel=next/prev, Blog JSON-LD)
- [x] Post detail `/posts/view/{slug}`, `/posts/view?slug=`, `/posts/{id}/{slug}`, `/posts/{id}` (Article JSON-LD, prev/next, related)
- [x] Bridge now supports URI **prefixes** for dynamic routes (`/posts/view/`, `/posts/`)
- [x] Categories & tags public pages (`/categories`, `/category/{slug}`, `/tags`, `/tag/{slug}`) — UNION mixed-content reads, CollectionPage JSON-LD, type-aware cards
- [x] Comments on post detail (server-rendered tree + markdown, migrated `POST /comment/add` w/ Laravel CSRF, owner notification + activity log)
- [ ] Comment reactions (react/edit/delete endpoints still legacy-owned; buttons not ported yet)
- [ ] Media uploads via Laravel Storage (same upload dirs)
- [ ] Full visual parity pass on home (legacy home.twig is 1498 lines; current port is a streamlined version)

## Phase 5 — Admin ✅ (completed 2026-09-06)

- [x] Admin layout + dashboard (stats, recent posts/comments, trend, sidebar-counts API) — done 2026-09-05
- [x] Categories & tags admin CRUD — done 2026-09-06
- [ ] Realtime refresh widgets (analytics heartbeat)
- [x] Posts CRUD + RTE integration — done 2026-09-06
- [x] Pages CMS — done 2026-09-06
- [x] Mobiles CRUD + specs — done 2026-09-06
- [x] Services + service applications — done 2026-09-06
- [x] Users admin (list, roles, permissions) + RBAC admin — done 2026-09-06
- [x] Notifications admin (send, schedules, topics, templates, analytics) — done 2026-09-06
- [x] Revenue (ads, sponsored, donations) + settings — done 2026-09-06
- [x] Logs, security settings, setup wizard — done 2026-09-06
- [x] Scrapping pipeline admin — done 2026-09-06

**Admin routes registered:** 135 total admin-related routes (GET + POST) across all admin modules.
**Bridge updated:** all admin paths + dynamic prefixes allowlisted.

## Phase 6 — Specialized modules ✅ (completed 2026-09-06)

- [x] CV builder admin — done 2026-09-06
- [x] Kharij module — done 2026-09-06
- [x] Sitemap (XML split + HTML) — done 2026-09-06
- [x] Weather — done 2026-09-06
- [x] Live TV (HLS proxy) — done 2026-09-06
- [x] Calculator tools — done 2026-09-06
- [x] OCR — done 2026-09-06
- [x] Photo studio / AI cutout — done 2026-09-06
- [x] AISystem (chat, article writer, knowledge) — done 2026-09-06
- [x] API proxies (Pexels / Pixabay / Puter / Firebase) — done 2026-09-06
- [ ] MedEX (drugs, brands, companies)

**Specialized routes:** 11 controllers + 11 view skeletons registered.

## Phase 7 — Hardening & cutover 🟡 (in progress)

- [x] Shared uploads filesystem disk wired (`config/filesystems.php` → `uploads` disk pointing at `public_html/uploads`, used by ProfileController picture upload) — done 2026-09-06
- [ ] Make `laravel/public` the docroot; remove bridge
- [ ] Queues for email/push/scraping
- [ ] Cache + rate limiting review
- [ ] Full test suite green (Pest/PHPUnit)
- [ ] Update deploy scripts (`artisan migrate --force`, Vite build in `laravel/`)
- [ ] Resolve/remove the pre-existing tailwind.css merge conflict (blocked on user)

## Phase 8 — Legacy removal

- [ ] Delete legacy `app/Controllers`, `app/Router`, Twig views, mysqli models, custom helpers
- [ ] Final docs update; delete this tracker

---

## Progress summary

| Phase | Status |
|---|---|
| 0 Foundation | ✅ Done |
| 1 Static pages | ✅ Done (FCM token cleanup follow-up also done) |
| 2 Auth | ✅ Done except Firebase OAuth, guest CV/FCM claims, i18n |
| 3 User area | ✅ Done (dashboard, profile, settings, notifications) |
| 4 Content/blog | ✅ Read side done (home, posts, mobiles, categories/tags, comments) |
| 5 Admin | ✅ Done (all admin CRUD modules scaffolded + routes + bridge) |
| 6 Specialized | ✅ Done (all specialized module controllers + views + routes + bridge) |
| 7 Hardening/cutover | 🟡 In progress |
| 8 Legacy removal | ⬜ |