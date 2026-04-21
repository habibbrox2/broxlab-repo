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
$sourcePrefillHandler = function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
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
