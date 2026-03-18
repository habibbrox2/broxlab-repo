# Routing & Middleware — BroxBhai (Custom PHP)

## Entry point
- `public_html/index.php` bootstraps env, constants, logging, session, twig, DB, middleware and then loads controllers.

## Router
- Router class + `$router` instance: `app/Routes/Router.php`
- Dispatch happens in `public_html/index.php` via `$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);`

## Where routes live
- Historically routes were defined directly inside controller files under `app/Controllers/*.php`.
- As of the recent refactor (2026‑03‑17) all AI‑related endpoints have been **centralised** in a dedicated route file:
  - `app/Routes/AISystemRoutes.php`
  - This file registers the public‑chat, chat, model management, cache and feedback routes.
  - The controller `app/Controllers/AISystemChatController.php` has been removed.
  - The centralised routes use the new `jsonResponse()` helper for consistent JSON output and include CSRF middleware on all POST endpoints.
- `public_html/index.php` now loads the central route file via:
  ```php
  require_once BASE_PATH . 'app/Routes/AISystemRoutes.php';
  ```
  alongside the generic router.
- Non‑AI routes continue to be defined in their respective controller files.

## Middleware
- Middleware registry is created in `public_html/index.php` via `register_middleware(...)` and executed per-route in `Router::dispatch(...)`.
- Middleware definitions are loaded from `app/Middleware/Middleware.php`.

## Named routes (optional)
- Router supports named routes via options: `['name' => 'routeName']`.
- URL generation: `$router->route('group.routeName', ['id' => 123])`

## 404 handling
- A fallback `any('.*', ...)` exists in `app/Routes/Web.php`.

