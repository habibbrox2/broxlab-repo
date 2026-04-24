<?php

declare(strict_types=1);

use App\Modules\Scraper\AISourceConfigGenerator;
use App\Modules\Scraper\HtmlFetcher;

global $mysqli, $router;

/**
 * AI Source Prefill
 *
 * Lightweight fallback route registration for live source analysis.
 * Keeping this in a small controller ensures the endpoint is available
 * even if the larger scraper controller is still loading other routes.
 *
 * @route POST /admin/scraper/ai/source-prefill
 * @route POST /api/v1/scraper/ai/source-prefill
 * @middleware auth, admin_only
 */

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

$sourcePrefillHandler = function () use ($mysqli) {
    if (!validateRequestCsrf()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $url = trim((string)($_POST['url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return jsonResponse(['success' => false, 'error' => 'Valid URL is required'], 400);
        }

        try {
            $html = HtmlFetcher::fetch($url);
        } catch (Throwable $fetchError) {
            error_log('AI Source Prefill fetch error: ' . $fetchError->getMessage());
            return jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch live HTML for analysis',
            ], 500);
        }

        $generator = AISourceConfigGenerator::fromMysqli($mysqli);
        $result = $generator->generatePrefill($url, $html);

        return jsonResponse($result);
    } catch (Throwable $e) {
        error_log('AI Source Prefill error: ' . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
};

$router->post('/admin/scraper/ai/source-prefill', ['middleware' => ['auth', 'admin_only']], $sourcePrefillHandler);
$router->post('/api/v1/scraper/ai/source-prefill', ['middleware' => ['auth', 'admin_only']], $sourcePrefillHandler);
