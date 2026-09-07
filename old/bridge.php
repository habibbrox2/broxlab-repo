<?php

/**
 * Docroot allowlist for the migrated Laravel app.
 *
 * public_html/index.php now serves laravel/public as the primary front door.
 * This allowlist is consulted for each request; matches are handled by
 * laravel/public/index.php, and everything else falls through to the legacy
 * router in public_html/index.php.
 *
 * - `paths`     : exact path matches (query string ignored)
 * - `prefixes`  : URI prefixes for dynamic routes (/posts/view/{slug}, ...)
 *
 * Only add paths/prefixes here once the equivalent Laravel routes + Blade
 * views exist and are smoke-tested (see migration/CHANGELOG.md).
 *
 * When the web server document root is pointed at laravel/public, this
 * allowlist can be retired.
 */

return [
    'paths' => [
        // Phase 1 — static/public pages
        '/about-us',
        '/faq',
        '/terms',
        '/privacy',
        '/newsletter',
        '/newsletter/subscribe',
        // Phase 4 — home + posts (read side)
        '/',
        '/posts',
        // Comments — write endpoints (read tree renders inside post view)
        '/comment/add',
        '/comment/edit',
        '/comment/delete',
        '/comment/react',
        '/comment/like',
        // Mobiles catalog
        '/mobiles',
        // Categories / tags — archives
        '/categories',
        '/tags',
        // Services — public read side
        '/services',
        // Phase 2 — auth (login/logout/register/reset over the shared legacy session)
        '/login',
        '/logout',
        '/register',
        '/forgot-password',
        '/reset-password',
        // Phase 2 follow-ups — 2FA verify + email verification
        '/verify-2fa',
        '/verify-email',
        '/send-verification-email',
        '/resend-verification-email',
        // Phase 3 — user area (dashboard, profile, settings, notifications)
        '/user/dashboard',
        '/profile',
        '/profile/edit',
        '/profile/password',
        '/profile/2fa',
        '/user/settings',
        '/user/notifications',
        '/api/notification/mark-read',
        '/api/notification/mark-all-read',
        // Phase 5 — admin area (layout + dashboard + taxonomy CRUD)
        '/admin',
        '/admin/dashboard',
        '/api/admin/sidebar-counts',
        '/admin/categories',
        '/admin/categories/create',
        '/admin/tags',
        '/admin/tags/create',
        '/admin/pages',
        '/admin/pages/create',
        '/api/pages/check_url',
        '/admin/posts',
        '/admin/posts/create',
        '/admin/posts/view',
        '/admin/posts/edit',
        '/admin/posts/delete',
        '/api/posts/check_permalink',
        // Admin mobiles CRUD
        '/admin/mobiles',
        '/admin/mobiles/create',
        '/admin/mobiles/view',
        '/admin/mobiles/edit',
        '/admin/mobiles/delete',
        // Admin services CRUD
        '/admin/services',
        '/admin/services/create',
        '/admin/services/view',
        '/admin/services/edit',
        '/admin/services/delete',
        // Users admin
        '/admin/users',
        '/admin/users/view',
        '/admin/users/edit',
        '/admin/users/delete',
        // RBAC — roles
        '/admin/roles',
        '/admin/roles/create',
        '/admin/roles/view',
        '/admin/roles/edit',
        '/admin/roles/delete',
        // RBAC — permissions
        '/admin/permissions',
        '/admin/permissions/create',
        '/admin/permissions/view',
        '/admin/permissions/edit',
        '/admin/permissions/delete',
        // Notifications admin
        '/admin/notifications',
        '/admin/notifications/create',
        '/admin/notifications/schedule',
        '/admin/notifications/view',
        '/admin/notifications/delete',
        // Revenue
        '/admin/revenue',
        '/admin/revenue/ads',
        '/admin/revenue/sponsored',
        '/admin/revenue/donations',
        // Logs
        '/admin/logs',
        // Security
        '/admin/security',
        // Setup
        '/admin/setup',
        // Scraper
        '/admin/scraper',
        '/admin/scraper/jobs',
        '/admin/scraper/sources',
        '/admin/scraper/settings',
        // CV Builder
        '/admin/cv',
        '/admin/cv/view',
        // Phase 6 — Medicines catalog (companies/brands JSON-file driven)
        '/medicines',
        '/medicines/details',
        '/medicines/companies',
        '/api/medicines/companies',
        '/api/medicines/refresh',
        '/api/medicines/proxy',
        '/api/medicines/fetch-page',
        '/api/medicines/save-data',
        // Phase 6 — Specialized modules
        '/admin/kharij',
        '/admin/sitemap',
        '/admin/weather',
        '/admin/livetv',
        '/admin/calculator',
        '/admin/ocr',
        '/admin/photo-studio',
        '/admin/aisystem',
        '/admin/api-proxy',
    ],
    'prefixes' => [
        '/posts/view/',
        '/posts/',
        '/mobiles/view/',
        '/category/',
        '/tag/',
        // Services: delegate the canonical detail URL. Plain /services/{slug}
        // is deliberately NOT a bridge prefix — it would shadow legacy
        // /services/new-application, /services/my-applications, /services/apply.
        '/services/view/',
        // Admin taxonomy CRUD — dynamic id routes (view/edit/delete)
        '/admin/categories/view/',
        '/admin/categories/edit/',
        '/admin/categories/delete/',
        '/admin/tags/view/',
        '/admin/tags/edit/',
        '/admin/tags/delete/',
        // Admin pages — dynamic routes
        '/admin/pages/view/',
        '/admin/pages/edit/',
        '/admin/pages/delete/',
        // Admin posts — dynamic id routes
        '/admin/posts/view/',
        '/admin/posts/edit/',
        '/admin/posts/delete/',
        // Posts autosave (POST; exact path above for GET-less safety)
        '/api/posts/autosave',
        // Admin mobiles CRUD - dynamic id routes
        '/admin/mobiles/view/',
        '/admin/mobiles/edit/',
        '/admin/mobiles/delete/',
        // Admin services - dynamic id routes
        '/admin/services/view/',
        '/admin/services/edit/',
        '/admin/services/delete/',
        // Admin users — dynamic id routes
        '/admin/users/view/',
        '/admin/users/edit/',
        '/admin/users/delete/',
        // RBAC — dynamic id routes (roles)
        '/admin/roles/view/',
        '/admin/roles/edit/',
        '/admin/roles/delete/',
        // RBAC — dynamic id routes (permissions)
        '/admin/permissions/view/',
        '/admin/permissions/edit/',
        '/admin/permissions/delete/',
        // Admin notifications — dynamic id routes
        '/admin/notifications/view/',
        '/admin/notifications/delete/',
        // CV Builder — dynamic id routes
        '/admin/cv/view/',
        // Medicines catalog — dynamic id routes
        '/medicines/company/',
        '/medicines/brand/',
        '/api/medicines/company/',
        '/api/medicines/brand/',
    ],
];
