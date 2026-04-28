<?php
/**
 * Weather Winget Controller
 *
 * Dedicated controller for Weather Winget operations.
 *
 * Routes:
 *   GET  /weather                    → weather.home
 *   GET  /weather/details/{location} → weather.details
 *
 * @package BroxBhai
 * @version 1.0.0
 */

/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */
/** @var Router $router */

// Initialize WeatherService
try {
    $weatherService = new WeatherService($mysqli);
} catch (Throwable $e) {
    logError("WeatherService init failed: " . $e->getMessage(), 'WEATHER_ERROR');
    $weatherService = null;
}

// =============================================================================
// ROUTE: Weather Home Page
// =============================================================================
$router->get('/weather', ['middleware' => [], 'name' => 'weather.home'], function () use ($twig, $weatherService) {
    $startTime = microtime(true);
    $requestId = bin2hex(random_bytes(8));

    $city   = sanitize_input($_GET['city'] ?? '');
    $lat    = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
    $lon    = isset($_GET['lon']) ? (float)$_GET['lon'] : null;
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $cacheKey = sprintf('weather_home_%s_%s_%s',
        $city ? strtolower(str_replace(' ', '_', $city)) : 'all',
        $lat ?? 'none',
        $lon ?? 'none'
    );

    try {
        if ($weatherService === null) {
            throw new RuntimeException('Weather service unavailable');
        }

        $data = CacheHelper::remember($cacheKey, function () use ($weatherService, $city, $lat, $lon) {
            $result = $weatherService->getHomePageData($city, $lat, $lon);
            return [
                'popular_locations' => $result['popular_locations'] ?? [],
                'featured_weather'  => $result['featured_weather'] ?? null,
                'search_city'       => $city,
                'suggested_cities'  => $result['suggested_cities'] ?? [],
                'weather_trends'    => $result['trends'] ?? [],
                'last_updated'      => time()
            ];
        }, 900);

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
        weather_handleError(500, 'Weather service temporarily unavailable',
            'WEATHER_SERVICE_ERROR', $requestId, $twig, ['exception' => $e->getMessage()]);
    }
});

// =============================================================================
// ROUTE: Weather Details Page
// =============================================================================
$router->get('/weather/details/{location}', ['middleware' => [], 'name' => 'weather.details'], function ($location) use ($twig, $weatherService) {
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
        $cacheKey     = sprintf('weather_details_%s_%s_%d_%d_%d',
            $locationHash, $units, $forecastDays, $includeHourly ? 1 : 0, $includeAlerts ? 1 : 0
        );
        $cacheTtl = ($forecastDays > 3 || $includeHourly) ? 1800 : 600;

        $data = CacheHelper::remember($cacheKey, function () use (
            $weatherService, $location, $units, $forecastDays, $includeHourly, $includeAlerts
        ) {
            $result = $weatherService->getLocationWeather($location, $units, $forecastDays, $includeHourly, $includeAlerts);
            if (!isset($result['success']) || !$result['success']) {
                throw new RuntimeException($result['error'] ?? 'Failed to fetch weather data');
            }
            return $result['data'] ?? [];
        }, $cacheTtl);

        $data['request_metadata'] = [
            'request_id'      => $requestId,
            'response_time_ms'=> round((microtime(true) - $startTime) * 1000, 2),
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
// HELPER FUNCTIONS - Guarded with function_exists to avoid collisions
// =============================================================================

if (!function_exists('weather_validateUnits')) {
    function weather_validateUnits($units): string
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
        $twig,
        array $context = []
    ): void {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $ip = getClientIpAddress(); // global from Config/Functions.php

        logError("Weather Error [{$errorCode}]: {$userMessage}", 'WEATHER_ERROR',
            array_merge($context, ['request_id' => $requestId, 'ip' => $ip]));

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
