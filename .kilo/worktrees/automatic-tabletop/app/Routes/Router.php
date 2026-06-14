<?php
// app/Routes/Router.php

require_once dirname(__DIR__, 2) . '/Config/Constants.php';
require_once dirname(__DIR__, 1) . '/Helpers/ErrorLogging.php';

class Router
{
    private array $routes = [];
    private array $namedRoutes = [];

    private string $currentGroupPrefix = '';
    private array $currentGroupMiddleware = [];
    private string $currentGroupName = '';

    // ================== HTTP METHOD HELPERS ==================

    /**
     * @param string $pattern
     * @param callable|array $optionsOrCallback
     * @param callable|null $maybeCallback
     */
    public function get($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('GET', $pattern, $optionsOrCallback, $maybeCallback);
    }

    /**
     * @param string $pattern
     * @param callable|array $optionsOrCallback
     * @param callable|null $maybeCallback
     */
    public function post($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('POST', $pattern, $optionsOrCallback, $maybeCallback);
    }

    /**
     * @param string $pattern
     * @param callable|array $optionsOrCallback
     * @param callable|null $maybeCallback
     */
    public function put($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('PUT', $pattern, $optionsOrCallback, $maybeCallback);
    }

    /**
     * @param string $pattern
     * @param callable|array $optionsOrCallback
     * @param callable|null $maybeCallback
     */
    public function patch($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('PATCH', $pattern, $optionsOrCallback, $maybeCallback);
    }

    /**
     * @param string $pattern
     * @param callable|array $optionsOrCallback
     * @param callable|null $maybeCallback
     */
    public function delete($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('DELETE', $pattern, $optionsOrCallback, $maybeCallback);
    }

    /**
     * @param string $pattern
     * @param callable|array $optionsOrCallback
     * @param callable|null $maybeCallback
     */
    public function options($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('OPTIONS', $pattern, $optionsOrCallback, $maybeCallback);
    }

    /**
     * @param string $pattern
     * @param callable|array $optionsOrCallback
     * @param callable|null $maybeCallback
     */
    public function any($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            $this->addRoute($method, $pattern, $optionsOrCallback, $maybeCallback);
        }
        return $this;
    }

    /**
     * @param string[] $methods
     * @param string $pattern
     * @param callable|array $optionsOrCallback
     * @param callable|null $maybeCallback
     */
    public function match(array $methods, $pattern, $optionsOrCallback, $maybeCallback = null)
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $pattern, $optionsOrCallback, $maybeCallback);
        }
        return $this;
    }

    // ================== ROUTE REGISTRATION CORE ==================

    /**
     * @param string $method
     * @param string $pattern
     * @param callable|array $optionsOrCallback
     * @param callable|null $maybeCallback
     */
    private function addRoute(string $method, string $pattern, $optionsOrCallback, $maybeCallback)
    {
        $fullPattern = $this->currentGroupPrefix . $pattern;
        $regex = '#^' . preg_replace('#\{([^}]+)\}#', '([^/]+)', $fullPattern) . '$#';

        $name = null;
        $middleware = $this->currentGroupMiddleware;

        if (is_callable($optionsOrCallback) && $maybeCallback === null) {
            $callback = $optionsOrCallback;
        } elseif (is_array($optionsOrCallback) && is_callable($maybeCallback)) {
            $callback   = $maybeCallback;
            $middleware = array_merge($middleware, $optionsOrCallback['middleware'] ?? []);
            $name       = $optionsOrCallback['name'] ?? null;
        } elseif (is_callable($optionsOrCallback) && is_array($maybeCallback)) {
            $callback   = $optionsOrCallback;
            $middleware = array_merge($middleware, $maybeCallback['middleware'] ?? []);
            $name       = $maybeCallback['name'] ?? null;
        } else {
            logError(
                "Invalid route definition",
                'WARNING',
                ['method' => $method, 'pattern' => $pattern]
            );
            throw new Exception("Invalid route definition: {$method} {$pattern}");
        }

        $route = [
            'method'          => $method,
            'pattern'         => $regex,
            'originalPattern' => $fullPattern,
            'middleware'      => $middleware,
            'callback'        => $callback,
        ];

        $this->routes[$method][] = $route;

        // Named route
        if ($name !== null) {
            $fullName = $this->currentGroupName . $name;

            if (isset($this->namedRoutes[$fullName])) {
                logError(
                    "Duplicate route name",
                    'WARNING',
                    ['route_name' => $fullName]
                );
                throw new Exception("Route name '{$fullName}' already exists");
            }

            $this->namedRoutes[$fullName] = $route;
        }

        return $this;
    }

    // ================== GROUPING ==================

    public function group(string $prefix, array $options, callable $callback): void
    {
        $prevPrefix     = $this->currentGroupPrefix;
        $prevMiddleware = $this->currentGroupMiddleware;
        $prevName       = $this->currentGroupName;

        $this->currentGroupPrefix .= $prefix;
        $this->currentGroupMiddleware = array_merge(
            $this->currentGroupMiddleware,
            $options['middleware'] ?? []
        );
        $this->currentGroupName .= isset($options['name']) ? $options['name'] . '.' : '';

        $callback($this);

        $this->currentGroupPrefix     = $prevPrefix;
        $this->currentGroupMiddleware = $prevMiddleware;
        $this->currentGroupName       = $prevName;
    }

    // ================== NAMED ROUTE URL ==================

    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            logError(
                "Named route not found",
                'WARNING',
                ['route_name' => $name]
            );
            throw new Exception("Named route '{$name}' not found");
        }

        $pattern = $this->namedRoutes[$name]['originalPattern'];

        foreach ($params as $key => $value) {
            $pattern = preg_replace('#\{' . $key . '\}#', $value, $pattern, 1);
        }

        if (preg_match('#\{[^}]+\}#', $pattern)) {
            logError(
                "Missing route parameters",
                'WARNING',
                ['route_name' => $name, 'pattern' => $pattern]
            );
            throw new Exception("Missing parameters for route '{$name}'");
        }

        return $pattern;
    }

    // ================== DISPATCH ==================

    public function dispatch(string $method, string $uri)
    {
        $uri = strtok($uri, '?');
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        if (!isset($this->routes[$method])) {
            logDebug(
                "No routes for method",
                ['method' => $method]
            );
            return $this->notFound();
        }

        foreach ($this->routes[$method] as $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches);

                foreach ($route['middleware'] as $mw) {
                    if (!run_middleware($mw, compact('method', 'uri'))) {
                        logMiddlewareReject(
                            $mw,
                            'access_denied',
                            ['method' => $method, 'uri' => $uri]
                        );
                        return;
                    }
                }

                logDebug(
                    "Route matched",
                    [
                        'method' => $method,
                        'uri' => $uri,
                        'pattern' => $route['originalPattern']
                    ]
                );

                try {
                    return call_user_func_array($route['callback'], $matches);
                } catch (Throwable $e) {
                    logError(
                        "Route execution failed: " . $e->getMessage(),
                        'ERROR',
                        [
                            'method' => $method,
                            'uri' => $uri,
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]
                    );
                    throw $e;
                }
            }
        }

        logDebug(
            "Route not found",
            ['method' => $method, 'uri' => $uri]
        );

        // Try to serve static file before 404
        if ($method === 'GET' && $this->serveStaticFile($uri)) {
            return;
        }

        return $this->notFound();
    }

    // ================== STATIC FILE SERVING ==================

    private function serveStaticFile(string $uri): bool
    {
        // Get the document root (typically public_html)
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2) . '/public_html';

        // Sanitize the URI to prevent directory traversal
        $path = $documentRoot . $uri;
        $path = realpath($path);

        // Verify the path is within public_html and the file exists
        if (!$path || !str_starts_with($path, realpath($documentRoot)) || !is_file($path)) {
            return false;
        }

        // Serve the file with appropriate headers
        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'html' => 'text/html',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
        ];

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

        http_response_code(200);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=3600');

        readfile($path);
        exit;
    }

    // ================== 404 ==================

    private function notFound()
    {
        http_response_code(404);

        // Prevent caching of 404 pages
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // SEO headers for 404 pages
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex');

        if (function_exists('renderError')) {
            renderError(404, '404 Not Found');
        } else {
            echo '404 Not Found';
        }

        exit;
    }

    // ================== DEBUG HELPERS ==================

    public function getRoutes(?string $method = null): array
    {
        return $method ? ($this->routes[strtoupper($method)] ?? []) : $this->routes;
    }

    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }
}
// Instantiate router
$router = new Router();
