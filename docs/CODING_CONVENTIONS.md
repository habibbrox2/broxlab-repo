# Coding Conventions — BroxBhai

This document captures repo-specific conventions discovered over time. Keep it short, practical, and append new gotchas as they appear.

## Backend (PHP)
- Controllers live in `app/Controllers/*.php` and register routes via `$router->get/post(...)` closures.
- All state-changing routes must use CSRF protection via existing middleware patterns (typically `['middleware' => ['auth', 'admin_only', 'csrf']]`).
- Database access stays in models (`app/Models/*`) and uses prepared statements; avoid raw SQL interpolation.
- Avoid `SELECT *` in new queries; select explicit columns.
- Prefer catching `Throwable` (not only `Exception`) around route handlers and per-item loops to prevent fatal errors from bubbling to 500s.

## Includes / Autoloading (Production releases)
- When `require_once`-ing shared files that may be included from multiple locations (notably `app/Models/AIProvider.php`), normalize the path to avoid double-includes in symlinked release deployments:
  - Use: `require_once (realpath($path) ?: $path);`
  - Why: `require_once` can treat symlink-path vs real-path as different files, triggering “Cannot declare class … already in use”.

## Views (Twig)
- Render via Twig templates (`app/Views/**`). Avoid raw HTML `echo` in controllers.
- JS-triggered admin actions should send `X-CSRF-Token` (and/or `csrf_token` body param) and `X-Requested-With: XMLHttpRequest`.

