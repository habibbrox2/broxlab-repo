<?php

declare(strict_types=1);

use App\Modules\Scraper\Diagnostics\ServiceTester;
use App\Modules\Scraper\HtmlFetcher;
use App\Modules\Scraper\Services\MonitoringService;
use App\Modules\Scraper\Services\SelectorTestingService;

global $mysqli, $router;

/**
 * Scraper Diagnostics API
 *
 * Lightweight API endpoints for the diagnostics dashboard.
 * These are registered in a small early-loading controller so the
 * dashboard does not depend on the much larger scraper controller.
 */

if (!function_exists('scraperDiagnosticsReadJson')) {
    function scraperDiagnosticsReadJson(): array
    {
        $input = json_decode((string)file_get_contents('php://input'), true);
        return is_array($input) ? $input : [];
    }
}

if (!function_exists('getScraperApiKeyConfig')) {
    function getScraperApiKeyConfig(): string
    {
        return (string)(getenv('SCRAPER_API_KEY') ?: '');
    }
}

if (!function_exists('getScraperApiKeyFromRequest')) {
    function getScraperApiKeyFromRequest(): string
    {
        return $_SERVER['HTTP_X_SCRAPER_API_KEY'] ?? $_GET['api_key'] ?? '';
    }
}

if (!function_exists('ensureScraperApiKey')) {
    function ensureScraperApiKey(): bool
    {
        $expected = getScraperApiKeyConfig();
        if ($expected === '') {
            return false;
        }

        $provided = trim(getScraperApiKeyFromRequest());
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }
}

if (!function_exists('validateRequestCsrf')) {
    function validateRequestCsrf(): bool
    {
        // Allow bypass if valid API key is provided (for external/web scraping requests)
        if (ensureScraperApiKey()) {
            return true;
        }

        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCsrfToken($token)) {
            jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
            return false;
        }
        return true;
    }
}

$router->post('/api/v1/scraper/diagnostics/css-selector', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateRequestCsrf()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = scraperDiagnosticsReadJson();
        $url = trim((string)($input['url'] ?? ''));
        $selector = trim((string)($input['selector'] ?? ''));
        $maxSamples = (int)($input['max_samples'] ?? 5);

        if ($url === '') {
            return jsonResponse(['success' => false, 'error' => 'URL is required'], 400);
        }
        if ($selector === '') {
            return jsonResponse(['success' => false, 'error' => 'Selector is required'], 400);
        }

        $service = new SelectorTestingService(HtmlFetcher::fetch($url));
        return jsonResponse($service->testCssSelector($selector, $maxSamples));
    } catch (Throwable $e) {
        error_log('Diagnostics CSS selector error: ' . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->post('/api/v1/scraper/diagnostics/service', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateRequestCsrf()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = scraperDiagnosticsReadJson();
        $url = trim((string)($input['url'] ?? ''));
        $service = strtolower(trim((string)($input['service'] ?? '')));

        if ($url === '') {
            return jsonResponse(['success' => false, 'error' => 'URL is required'], 400);
        }
        if ($service === '') {
            return jsonResponse(['success' => false, 'error' => 'Service is required'], 400);
        }

        $tester = new ServiceTester();
        $result = match ($service) {
            'php_scraper', 'php-scraper', 'phpscraper' => $tester->testPhpScraper($url),
            'panther' => $tester->testPanther($url),
            'roach' => $tester->testRoach($url),
            'php_spider', 'php-spider', 'phpspider' => $tester->testPhpSpider($url),
            default => ['success' => false, 'error' => 'Unknown service'],
        };

        return jsonResponse($result);
    } catch (Throwable $e) {
        error_log('Diagnostics service error: ' . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->post('/api/v1/scraper/diagnostics/services', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateRequestCsrf()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = scraperDiagnosticsReadJson();
        $url = trim((string)($input['url'] ?? ''));
        if ($url === '') {
            return jsonResponse(['success' => false, 'error' => 'URL is required'], 400);
        }

        $tester = new ServiceTester();
        $results = $tester->runAllTests($url);
        return jsonResponse([
            'success' => true,
            'result' => $tester->getFormattedResults(),
            'results' => $results,
        ]);
    } catch (Throwable $e) {
        error_log('Diagnostics services error: ' . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/api/v1/scraper/diagnostics/system', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $monitor = new MonitoringService($mysqli, [
            'log_path' => LOG_DIR . 'scraper.log',
        ]);
        $health = $monitor->checkHealth();

        $errorLogPath = LOG_DIR . 'errors.log';
        $errorLogSize = is_file($errorLogPath) ? filesize($errorLogPath) : 0;

        $payload = [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'database' => $health['database'] ? 'ok' : 'error',
            'disk_space_percent_free' => $health['disk_space'],
            'memory_percent_used' => $health['memory'],
            'error_log_size_mb' => round($errorLogSize / 1024 / 1024, 2),
            'overall' => $health['overall'],
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        return jsonResponse([
            'success' => true,
            'result' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'health' => $payload,
        ]);
    } catch (Throwable $e) {
        error_log('Diagnostics system error: ' . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});
