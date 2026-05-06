<?php

/**
 * Weather Controller
 *
 * Unified controller for all Weather operations.
 *
 * Routes:
 *   GET  /weather/{location}         → JSON API for location weather
 *   GET  /weather/current            → JSON API for current location weather
 *   GET  /weather                    → weather.home (home page)
 *   GET  /weather/details            → weather.details (details page with query params)
 *   GET  /weather/details/{location} → weather.details.location (details page with path params)
 *
 * @package BroxBhai
 * @version 2.0.0
 */

use App\Modules\AISystem\UnifiedCache;

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

// Use proper path resolution
$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
$constantsFile = $basePath . 'Config' . DIRECTORY_SEPARATOR . 'Constants.php';
$weatherServiceFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'WeatherService.php';

if (file_exists($constantsFile)) {
    require_once $constantsFile;
}
if (file_exists($weatherServiceFile)) {
    require_once $weatherServiceFile;
}

// Initialize WeatherService (from Models - more advanced)
$weatherService = null;
$cache = null;
try {
    $weatherService = new WeatherService($mysqli);
    $cache = UnifiedCache::getInstance();
} catch (Throwable $e) {
    logError("WeatherService init failed: " . $e->getMessage(), 'WEATHER_ERROR');
}

// =============================================================================
// ROUTE: Weather Home Page (Advanced with caching and features)
// =============================================================================
$router->get('/weather', ['middleware' => [], 'name' => 'weather.home'], function () use ($twig, $weatherService, $cache) {
    $startTime = microtime(true);
    $requestId = bin2hex(random_bytes(8));

    $city = sanitize_input($_GET['city'] ?? '');
    $lat  = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
    $lon  = isset($_GET['lon']) ? (float)$_GET['lon'] : null;
    $searchCity = $city;
    if ($searchCity === '' && $lat !== null && $lon !== null) {
        $searchCity = sprintf('%.6f,%.6f', $lat, $lon);
    }
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    try {
        if ($weatherService === null) {
            throw new RuntimeException('Weather service unavailable');
        }

        $locationHash = md5($city . ($lat ?? '') . ($lon ?? ''));
        $cacheKey = sprintf('weather_home_%s', $locationHash);

        // Check cache first
        $data = $cache ? $cache->get($cacheKey, \App\Modules\AISystem\UnifiedCache::CATEGORY_WEATHER) : null;
        if ($data === null || $data === false) {
            $result = $weatherService->getHomePageData($city, $lat, $lon);
            if (!is_array($result)) {
                $result = [];
            }
            $data = [
                'popular_locations' => $result['popular_locations'] ?? [],
                'featured_weather'  => $result['featured_weather'] ?? null,
                'search_city'       => $searchCity,
                'suggested_cities'  => $result['suggested_cities'] ?? [],
                'weather_trends'    => $result['trends'] ?? [],
                'last_updated'      => time()
            ];
            if ($cache) {
                $cache->set($cacheKey, $data, \App\Modules\AISystem\UnifiedCache::CATEGORY_WEATHER, 900);
            }
        }

        $data['cache_hit']      = true;
        $data['request_id']     = $requestId;
        $data['response_time']  = round((microtime(true) - $startTime) * 1000, 2);

        logActivity("Weather Home Accessed", "weather", 0, [
            'city'             => $city ?: 'none',
            'has_coords'       => ($lat !== null && $lon !== null),
            'request_id'       => $requestId,
            'ip'               => getClientIpAddress(),
            'response_time_ms' => $data['response_time']
        ], 'success');

        if ($isAjax || ($_GET['format'] ?? '') === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $data]);
            return;
        }

        echo $twig->render('weather/home.twig', [
            'title'        => 'Weather Winget - Home',
            'weather_data' => $data,
            'page'         => 'weather_home'
        ]);
    } catch (Throwable $e) {
        weather_handleError(
            500,
            'Weather service temporarily unavailable',
            'WEATHER_SERVICE_ERROR',
            $requestId,
            $twig,
            ['exception' => $e->getMessage()]
        );
    }
});

// =============================================================================
// ROUTE: Weather Details Page (with query params)
// =============================================================================
$router->get('/weather/details', ['middleware' => [], 'name' => 'weather.details'], function () use ($twig, $weatherService, $cache) {
    $startTime = microtime(true);
    $requestId = bin2hex(random_bytes(8));

    $location = sanitize_input($_GET['location'] ?? '');
    if (empty($location)) {
        return weather_handleError(400, 'Location required', 'WEATHER_LOCATION_MISSING', $requestId, $twig);
    }

    $units = weather_validateUnits($_GET['units'] ?? 'default');
    $forecastDays = weather_validateForecastDays((int)($_GET['forecast_days'] ?? 3));
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    try {
        if ($weatherService === null) {
            throw new RuntimeException('Weather service unavailable');
        }

        $locationHash = md5($location);
        $cacheKey = sprintf('weather_details_%s_%s_%d', $locationHash, $units, $forecastDays);
        $cacheTtl = ($forecastDays > 3) ? 1800 : 600;

        // Check cache first
        $data = $cache ? $cache->get($cacheKey, \App\Modules\AISystem\UnifiedCache::CATEGORY_WEATHER) : null;
        if ($data === null || $data === false) {
            $result = $weatherService->getLocationWeather($location, $units, $forecastDays);
            if (!is_array($result) || !isset($result['success']) || !$result['success']) {
                throw new RuntimeException(is_array($result) ? ($result['error'] ?? 'Failed to fetch weather data') : 'Failed to fetch weather data');
            }
            $data = $result['data'] ?? [];
            if ($cache) {
                $cache->set($cacheKey, $data, \App\Modules\AISystem\UnifiedCache::CATEGORY_WEATHER, $cacheTtl);
            }
        }

        $data['request_metadata'] = [
            'request_id' => $requestId,
            'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'cache_hit' => true,
            'timestamp' => time()
        ];

        logActivity("Weather Details Viewed", "weather", 0, [
            'location' => $location,
            'units' => $units,
            'forecast_days' => $forecastDays,
            'request_id' => $requestId,
            'ip' => getClientIpAddress()
        ], 'success');

        if ($isAjax || ($_GET['format'] ?? '') === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        echo $twig->render('weather/details.twig', [
            'title' => "Weather in " . htmlspecialchars($data['current']['location_name'] ?? $location),
            'weather' => $data,
            'page' => 'weather_details',
            'units' => $units,
            'forecast_days' => $forecastDays,
            'location' => $location
        ]);
    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
        $errorCode = 'WEATHER_API_ERROR';
        $statusCode = 500;

        if (strpos($errorMsg, 'not found') !== false) {
            $errorCode = 'WEATHER_LOCATION_NOT_FOUND';
            $statusCode = 404;
        } elseif (strpos($errorMsg, 'quota') !== false) {
            $errorCode = 'WEATHER_API_QUOTA_EXCEEDED';
            $statusCode = 429;
        } elseif (strpos($errorMsg, 'timeout') !== false) {
            $errorCode = 'WEATHER_API_TIMEOUT';
            $statusCode = 504;
        }

        logError("Weather details error: {$errorMsg}", 'WEATHER_' . strtoupper($errorCode), [
            'request_id' => $requestId,
            'location' => $location
        ]);

        weather_handleError($statusCode, $e->getMessage(), $errorCode, $requestId, $twig, ['location' => $location]);
    }
});

// =============================================================================
// ROUTE: Weather Details Page (with path params)
// =============================================================================
$router->get('/weather/details/{location}', ['middleware' => [], 'name' => 'weather.details.location'], function ($location) use ($twig, $weatherService, $cache) {
    $startTime = microtime(true);
    $requestId = bin2hex(random_bytes(8));

    $location = trim(sanitize_input($location));
    if (empty($location)) {
        return weather_handleError(400, 'Location required', 'WEATHER_LOCATION_MISSING', $requestId, $twig);
    }

    $location = weather_validateLocationFormat($location);
    if ($location === null) {
        return weather_handleError(400, 'Invalid location format', 'WEATHER_LOCATION_INVALID', $requestId, $twig);
    }

    $units          = weather_validateUnits($_GET['units'] ?? 'default');
    $forecastDays   = weather_validateForecastDays((int)($_GET['forecast_days'] ?? 3));
    $includeHourly  = filter_var($_GET['include_hourly'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $includeAlerts  = filter_var($_GET['include_alerts'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $isAjax         = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    try {
        if ($weatherService === null) {
            throw new RuntimeException('Weather service unavailable');
        }

        $locationHash = md5($location);
        $cacheKey     = sprintf(
            'weather_details_%s_%s_%d_%d_%d',
            $locationHash,
            $units,
            $forecastDays,
            $includeHourly ? 1 : 0,
            $includeAlerts ? 1 : 0
        );
        $cacheTtl = ($forecastDays > 3 || $includeHourly) ? 1800 : 600;

        // Check cache first
        $data = $cache ? $cache->get($cacheKey, \App\Modules\AISystem\UnifiedCache::CATEGORY_WEATHER) : null;
        if ($data === null || $data === false) {
            $result = $weatherService->getLocationWeather($location, $units, $forecastDays, $includeHourly, $includeAlerts);
            if (!is_array($result) || !isset($result['success']) || !$result['success']) {
                throw new RuntimeException(is_array($result) ? ($result['error'] ?? 'Failed to fetch weather data') : 'Failed to fetch weather data');
            }
            $data = $result['data'] ?? [];
            if ($cache) {
                $cache->set($cacheKey, $data, \App\Modules\AISystem\UnifiedCache::CATEGORY_WEATHER, $cacheTtl);
            }
        }

        $data['request_metadata'] = [
            'request_id'      => $requestId,
            'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'cache_hit'       => true,
            'timestamp'       => time()
        ];

        logActivity("Weather Details Viewed", "weather", 0, [
            'location'      => $location,
            'units'         => $units,
            'forecast_days' => $forecastDays,
            'request_id'    => $requestId,
            'ip'            => getClientIpAddress()
        ], 'success');

        if ($isAjax || ($_GET['format'] ?? '') === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        echo $twig->render('weather/details.twig', [
            'title'           => "Weather in " . htmlspecialchars($data['current']['location_name'] ?? $location),
            'weather'         => $data,
            'page'            => 'weather_details',
            'units'           => $units,
            'forecast_days'   => $forecastDays,
            'location'        => $location
        ]);
    } catch (Throwable $e) {
        $errorMsg  = $e->getMessage();
        $errorCode = 'WEATHER_API_ERROR';
        $statusCode = 500;

        if (strpos($errorMsg, 'not found') !== false) {
            $errorCode = 'WEATHER_LOCATION_NOT_FOUND';
            $statusCode = 404;
        } elseif (strpos($errorMsg, 'quota') !== false) {
            $errorCode = 'WEATHER_API_QUOTA_EXCEEDED';
            $statusCode = 429;
        } elseif (strpos($errorMsg, 'timeout') !== false) {
            $errorCode = 'WEATHER_API_TIMEOUT';
            $statusCode = 504;
        }

        logError("Weather details error: {$errorMsg}", 'WEATHER_' . strtoupper($errorCode), [
            'request_id' => $requestId,
            'location'   => $location
        ]);

        weather_handleError($statusCode, $e->getMessage(), $errorCode, $requestId, $twig, ['location' => $location]);
    }
});

// =============================================================================
// ROUTE: Weather API by Location
// =============================================================================
$router->get('/weather/{location}', ['middleware' => [], 'name' => 'weather.api.location'], function ($location) use ($weatherService) {
    try {
        $location = sanitize_input($location);
        if (empty($location)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Location required']);
            return;
        }

        if (in_array(strtolower($location), ['current', 'details'], true)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Weather location not found']);
            return;
        }

        $units = $_GET['units'] ?? 'metric';
        $forecastDays = (int)($_GET['forecast_days'] ?? 0);

        if ($weatherService === null) {
            throw new RuntimeException('Weather service unavailable');
        }

        $result = $weatherService->getLocationWeather($location, $units, $forecastDays);

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

        echo json_encode($result);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error: ' . $e->getMessage()
        ]);
    }
});

// =============================================================================
// ROUTE: Current Location Weather API
// =============================================================================
$router->get('/weather/current', ['middleware' => [], 'name' => 'weather.api.current'], function () use ($weatherService) {
    try {
        // Get IP address
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '8.8.8.8';

        // For now, use a default location (Dhaka, Bangladesh) since geolocation service is not implemented
        $location = '23.8103,90.4125'; // Dhaka, Bangladesh coordinates

        if ($weatherService === null) {
            throw new RuntimeException('Weather service unavailable');
        }

        $result = $weatherService->getLocationWeather($location, 'metric', 1);
        if (!is_array($result) || !isset($result['success']) || !$result['success']) {
            throw new RuntimeException(is_array($result) ? ($result['error'] ?? 'Failed to fetch weather data') : 'Failed to fetch weather data');
        }

        // Add IP info for debugging
        $result['ip_address'] = $ipAddress;

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

        echo json_encode($result);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error: ' . $e->getMessage()
        ]);
    }
});

// =============================================================================
// HELPER FUNCTIONS - Guarded with function_exists to avoid collisions
// =============================================================================

if (!function_exists('weather_validateUnits')) {
    function weather_validateUnits(string $units): string
    {
        $allowed = ['metric', 'imperial', 'kelvin', 'default'];
        $unit    = strtolower(trim((string)$units));
        return in_array($unit, $allowed, true) ? $unit : 'default';
    }
}

if (!function_exists('weather_validateForecastDays')) {
    function weather_validateForecastDays(int $days): int
    {
        return max(1, min(7, $days));
    }
}

if (!function_exists('weather_validateLocationFormat')) {
    function weather_validateLocationFormat(string $location): ?string
    {
        $location = trim($location);
        if (empty($location)) return null;

        if (preg_match('/^[-+]?[0-9]*\.?[0-9]+,[-+]?[0-9]*\.?[0-9]+$/', $location)) {
            [$lat, $lon] = array_map('trim', explode(',', $location));
            if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
                return sprintf('%.6f,%.6f', $lat, $lon);
            }
            return null;
        }

        if (preg_match('/^[a-zA-Z0-9\s\-\.\,]{1,100}$/', $location)) {
            return $location;
        }

        return null;
    }
}

if (!function_exists('weather_handleError')) {
    function weather_handleError(
        int $httpCode,
        string $userMessage,
        string $errorCode,
        string $requestId,
        \Twig\Environment $twig,
        array $context = []
    ): void {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $ip = getClientIpAddress(); // global from Config/Functions.php

        logError(
            "Weather Error [{$errorCode}]: {$userMessage}",
            'WEATHER_ERROR',
            array_merge($context, ['request_id' => $requestId, 'ip' => $ip])
        );

        logActivity("Weather Operation Failed", "weather", 0, [
            'error_code' => $errorCode,
            'request_id' => $requestId,
            'http_code'  => $httpCode
        ], 'failure');

        http_response_code($httpCode);

        if ($isAjax || ($_GET['format'] ?? '') === 'json') {
            header('Content-Type: application/json');
            echo json_encode([
                'success'    => false,
                'error'      => $userMessage,
                'error_code' => $errorCode,
                'request_id' => $requestId
            ], JSON_PRETTY_PRINT);
            exit;
        }

        $template = __DIR__ . '/../Views/weather/error.twig';
        if (file_exists($template)) {
            echo $twig->render('weather/error.twig', [
                'title'      => 'Weather Error',
                'error_code' => $errorCode,
                'message'    => $userMessage,
                'request_id' => $requestId
            ]);
        } else {
            echo "<h1>Error: {$userMessage}</h1><p>Request ID: {$requestId}</p>";
        }
        exit;
    }
}
