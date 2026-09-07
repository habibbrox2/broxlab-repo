# Legacy routes not yet ported to Laravel (Phase 8 backlog)

Phase 8 retired the legacy front controller (`old/index.php.legacy`), so these
legacy routes are **no longer served**. They are grouped by module with their
legacy controller source, in suggested port order (user-facing first).

Counts: 91 public/user paths + ~290 admin paths + ~120 api paths.
Source of the raw diff: `comm -23 legacy-routes laravel-routes` at cutover time
(2026-09-08). Verify against `php artisan route:list` when porting each module.

## High-traffic public pages (port first)

| Path | Legacy source |
|---|---|
| `/about` | PageController (`/about-us` already ported — add alias) |
| `/contact` | PageController |
| `/advertise` | PageController |
| `/donate`, `/donate/bkash/callback` | PageController / PaymentController |
| `/all` | HomeController (all-content feed) |
| `/lang/{code}` | LanguageController (session language switch) |
| `/logout` | AuthController (Laravel has `/logout`? verify alias) |
| `/robots.txt` | static (served via public_html symlink — verify) |
| `/sitemap*.xml` (10 files) | SitemapController |
| `/pages/view/{slug}` | PagesController (CMS pages) |
| `/category`, `/tag` | TagsCategoriesController (index aliases) |
| `/view/{id}`, `/update/{id}` | ContentController (legacy shortlinks) |

## Specialized modules (public side)

| Module | Paths | Legacy source |
|---|---|---|
| Weather | `/weather`, `/weather/current`, `/weather/details`, `/weather/details/{location}`, `/weather/{location}` | WeatherController |
| Calculators | `/calculators`, `/calculators/{slug}` | CalculatorController |
| Live TV | `/live-tv`, `/live-tv/{channel}`, `/live/tv`, `/live=tv`, `/live-tv/proxy/*` (HLS proxy, 7 route shapes) | LiveTvController |
| CV builder | `/cv-builder`, `/cv-builder/guest`, `/cv-builder/infos`, `/cv-builder/live`, `/cv-builder/live/{id}`, `/cv-builder/new`, `/cv-builder/templates`, `/cv-builder/view`, `/cv-builder/{id}/export/pdf`, `/cv-builder/guest/{id}/export/pdf` | CvController |
| Photo studio | `/studio`, `/studio/image`, `/studio/print-sheet`, `/studio/remove-background`, `/studio/save`, `/studio/upload` | PhotoStudioController |
| OCR | `/test-ocr` | OCRController |
| AI chat | `/ai/chat` | AISystemController |
| Bangla converter | `/bangla-converter`, `/translate` | (helpers) |
| Kharij / land | `/ldtax-gov-bd/dakhila-print/{hash}`, `/mutation-land-gov-bd/*` (3) | KharijController |
| Ramadan | `/ramadan`, `/ramadan-2026` | PageController |

## User area gaps

| Path | Legacy source |
|---|---|
| `/user/profile`, `/user/update`, `/user/change-password`, `/user/set-password` | UserController/ProfileController (Laravel uses `/profile*` — add aliases or port) |
| `/user/linked-emails`, `/user/linked-emails/{email}` | UserController |
| `/services/apply`, `/services/new-application`, `/services/my-applications`, `/services/applications/{id}`, `/services/applications/{id}/copy`, `/services/receipt/{id}/download`, `/services/view/{slug}` | ServicesController |
| `/upload`, `/editor`, `/insert` | MediaController |

## Payments / push / misc

| Path | Legacy source |
|---|---|
| `/payments/bkash/callback` | PaymentController |
| `/push-endpoints` | NotificationController (web-push endpoints) |
| `/public/info`, `/public/time` | MixedApiController (public info endpoints) |
| `/ramadan` pages | see above |

## Dev/debug (decide: port or drop)

`/_dev/firebase-test`, `/_dev/oauth-diagnostics`, `/debug/firebase/*` (3),
`/test-ocr` — diagnostic-only. Recommend dropping unless still used.

## Admin side (grouped, ~290 paths — port per module)

- AI system admin: `/admin/ai-system/*` (8), `/admin/ai/*` (3), `/admin/ai-chats`, `/admin/ai-knowledge` + `/api/admin/ai*` (~25)
- Scrap control center: `/admin/scrap-control-center/*` (14) + `/admin/api/scrap-control-center/*` (13)
- Push notifications: `/admin/push-logs/*` (10), `/admin/notifications/*` extras (7), `/admin/notification-templates/*` (6), `/admin/notification-subscribers`, `/api/notification/*` (10), `/api/topics/*` (3)
- Applications/receipts: `/admin/applications/*` (11 incl. CSV exports, receipts)
- CV admin: `/admin/cv-builder*`, `/admin/cv-infos/*` (6), `/admin/cv-templates/*` (10), `/admin/cv-purchases/*` (3)
- Analytics/monitoring: `/admin/analytics`, `/admin/realtime-monitoring`, `/api/admin/analytics/*` (13)
- Users/settings extras: `/admin/users/create`, `/admin/account-settings`, `/admin/app-settings/*` (3), `/admin/settings/*` (3), `/admin/setup-linked-emails`, `/admin/feature-flags/*` (2)
- Contact: `/admin/contact/*` (4)
- Revenue extras: `/admin/revenue/ads/*` (3), `/admin/revenue/donations/{id}/confirm|reject`, `/admin/revenue/settings`
- Services extras: `/admin/services/details/{id}`, `/admin/services/update`, `/admin/services/{id}/edit`, image delete (2)
- Kharij admin: `/admin/kharij/*` (7)
- Logs: `/admin/log-activity`, `/api/admin/logs/*` (4), `/api/log-activity/*` (6)
- Misc APIs: `/api/firebase/*` (6), `/api/oauth/*` (8), `/api/ocr/*` (5), `/api/pexels/*` (2), `/api/pixabay/*` (2), `/api/ads/*` (2), `/api/cv/*` (~18), `/api/settings*` (3), `/api/statistics`, `/api/categories*` (4), `/api/tags*` (4), `/api/ratings/*` (2), `/api/feed/load-more`, `/api/user/*` (2), `/api/user-notifications`, `/api/calculator/compute/{type}`, `/api/home/services`, `/api/analytics/ingest`, `/api/system/health`, `/api/auth/skip-password-setup`, `/api/pages/autosave`, `/api/services/check-slug`, `/api/ai*` (~18)

## Notes

- The Laravel `route:list` names shown in this repo are the source of truth for
  what IS ported; this file lists only what is NOT.
- Some paths may be intentionally dropped (dev/debug, legacy shortlinks).
  Mark decisions here as each is triaged.
- Priority order for porting: high-traffic public pages → specialized public
  modules → user area gaps → admin/api extras.
