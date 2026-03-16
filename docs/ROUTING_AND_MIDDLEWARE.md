# Routing & Middleware — BroxBhai (Custom PHP)

## Entry point
- `public_html/index.php` bootstraps env, constants, logging, session, twig, DB, middleware and then loads controllers.

## Router
- Router class + `$router` instance: `app/Routes/Router.php`
- Dispatch happens in `public_html/index.php` via `$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);`

## Where routes live
- Routes are registered inside `app/Controllers/*.php` via:
  - `$router->get('/path', function () use (...) { ... });`
  - `$router->post('/path', ['middleware' => ['auth', 'csrf']], function () use (...) { ... });`
- `public_html/index.php` loads all controller files using `glob(BASE_PATH . 'app/Controllers/*.php')`.

## Middleware
- Middleware registry is created in `public_html/index.php` via `register_middleware(...)` and executed per-route in `Router::dispatch(...)`.
- Middleware definitions are loaded from `app/Middleware/Middleware.php`.

## Named routes (optional)
- Router supports named routes via options: `['name' => 'routeName']`.
- URL generation: `$router->route('group.routeName', ['id' => 123])`

## 404 handling
- A fallback `any('.*', ...)` exists in `app/Routes/Web.php`.

