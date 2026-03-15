<?php
// app/Routes/Router.php

<<<<<<< HEAD
=======
require_once __DIR__ . '/../../Config/Constants.php';
>>>>>>> temp_branch
require_once __DIR__ . '/../Helpers/ErrorLogging.php';

class Router
{
    private array $routes = [];
    private array $namedRoutes = [];

    private string $currentGroupPrefix = '';
    private array $currentGroupMiddleware = [];
    private string $currentGroupName = '';

    // ================== HTTP METHOD HELPERS ==================

<<<<<<< HEAD
    public function get($pattern, $optionsOrCallback, $maybeCallback = null) {
        return $this->addRoute('GET', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function post($pattern, $optionsOrCallback, $maybeCallback = null) {
        return $this->addRoute('POST', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function put($pattern, $optionsOrCallback, $maybeCallback = null) {
        return $this->addRoute('PUT', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function patch($pattern, $optionsOrCallback, $maybeCallback = null) {
        return $this->addRoute('PATCH', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function delete($pattern, $optionsOrCallback, $maybeCallback = null) {
        return $this->addRoute('DELETE', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function options($pattern, $optionsOrCallback, $maybeCallback = null) {
        return $this->addRoute('OPTIONS', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function any($pattern, $optionsOrCallback, $maybeCallback = null) {
        foreach (['GET','POST','PUT','PATCH','DELETE','OPTIONS'] as $method) {
=======
    public function get($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('GET', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function post($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('POST', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function put($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('PUT', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function patch($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('PATCH', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function delete($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('DELETE', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function options($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        return $this->addRoute('OPTIONS', $pattern, $optionsOrCallback, $maybeCallback);
    }

    public function any($pattern, $optionsOrCallback, $maybeCallback = null)
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
>>>>>>> temp_branch
            $this->addRoute($method, $pattern, $optionsOrCallback, $maybeCallback);
        }
        return $this;
    }

<<<<<<< HEAD
    public function match(array $methods, $pattern, $optionsOrCallback, $maybeCallback = null) {
=======
    public function match(array $methods, $pattern, $optionsOrCallback, $maybeCallback = null)
    {
>>>>>>> temp_branch
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $pattern, $optionsOrCallback, $maybeCallback);
        }
        return $this;
    }

    // ================== ROUTE REGISTRATION CORE ==================

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
<<<<<<< HEAD
                    if (!run_middleware($mw, compact('method','uri'))) {
=======
                    if (!run_middleware($mw, compact('method', 'uri'))) {
>>>>>>> temp_branch
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

        return $this->notFound();
    }

    // ================== 404 ==================

    private function notFound()
    {
        http_response_code(404);

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
