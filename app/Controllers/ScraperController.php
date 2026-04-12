<?php

declare(strict_types=1);

// Ensure ?\AutoContentModel is loaded (workaround for autoloading issues)

/**
 * ScraperController.php
 * Controller for Web Scraping System - manages scraping sources, queue, logs, and statistics
 */

use App\Models\ScraperModel;
use App\Modules\Scraper\AIContentClassifier;
use App\Modules\Scraper\AIPresetGenerator;
use App\Modules\Scraper\AIScraperAnalyzer;
use App\Modules\Scraper\AIScraperOptimizer;
use App\Modules\Scraper\HtmlFetcher;
use App\Modules\Scraper\Presets\PresetRegistry;
use App\Modules\Scraper\ScraperService;
use App\Modules\Scraper\ScraperFactory;
use App\Modules\Scraper\Pipelines\GSMArenaPipeline;

global $mysqli, $router, $twig;

// Include JsonResponse helper for JSON responses
require_once __DIR__ . '/../Helpers/JsonResponse.php';

if (!function_exists('parseJsonRequest')) {
    function parseJsonRequest(): array
    {
        $input = json_decode(file_get_contents('php://input'), true);
        return is_array($input) ? $input : [];
    }
}

if (!function_exists('validateScraperInput')) {
    function validateScraperInput(array $data, array $required = []): array
    {
        $errors = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[] = "Field '{$field}' is required";
            }
        }

        // Validate URL fields
        if (isset($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors[] = "Invalid URL format";
        }

        // Validate integer fields
        $intFields = ['category_id', 'fetch_interval', 'scrape_depth', 'max_pages', 'delay'];
        foreach ($intFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                $errors[] = "Field '{$field}' must be numeric";
            }
        }

        // Validate JSON fields
        $jsonFields = ['selectors', 'advance_config', 'presets'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (is_string($data[$field])) {
                    json_decode($data[$field]);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = "Field '{$field}' contains invalid JSON";
                    }
                }
            }
        }

        return $errors;
    }
}

if (!function_exists('ensureCsrfToken')) {
    function ensureCsrfToken(): bool
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCsrfToken($token)) {
            jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
            return false;
        }
        return true;
    }

    function getScraperApiKeyConfig(): string
    {
        return (string)(getenv('SCRAPER_API_KEY') ?: '');
    }

    function getScraperApiKeyFromRequest(): string
    {
        return $_SERVER['HTTP_X_SCRAPER_API_KEY'] ?? $_GET['api_key'] ?? '';
    }

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

if (!function_exists('prepareScrapedArticlePayload')) {
    function prepareScrapedArticlePayload(array $article): array
    {
        $article['categories'] = [];
        if (!empty($article['categories_json'])) {
            $decoded = json_decode($article['categories_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $article['categories'] = $decoded;
            }
        }

        $article['tags'] = [];
        if (!empty($article['tags_json'])) {
            $decoded = json_decode($article['tags_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $article['tags'] = $decoded;
            }
        }

        $article['metadata_struct'] = [];
        if (!empty($article['metadata'])) {
            $decodedMetadata = json_decode($article['metadata'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedMetadata)) {
                $article['metadata_struct'] = $decodedMetadata;
            }
        }

        return $article;
    }
}

require_once __DIR__ . '/../Modules/Scraper/Pipelines/GSMArenaPipeline.php';

// ================== DASHBOARD ==================

/**
 * Scraper Dashboard
 *
 * Displays the main scraper dashboard with statistics, recent jobs, and active sources.
 *
 * @route GET /admin/scraper
 * @middleware auth, admin_only
 *
 * @return void Renders the dashboard template with:
 *               - stats: Overall scraping statistics (total sources, jobs, success rate)
 *               - recentJobs: Array of recent scraping jobs (max 10)
 *               - activeSources: Array of currently active scraper sources
 *               - errorStats: Recent error statistics and monitoring data
 *               - pageTitle: "Scraper Dashboard"
 *
 * @throws Exception If database queries fail or template rendering fails
 *
 * @example Response: HTML page with dashboard metrics and tables
 */
$router->get('/admin/scraper', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $stats = $model->getOverallStats();
        $recentJobs = $model->getPendingJobs(10);
        $activeSources = $model->getActiveSources();

        // Get error statistics from a recent scraper service instance
        $errorStats = [
            'total_errors' => 0,
            'by_type' => [],
            'by_severity' => [],
            'recent_errors' => []
        ];

        try {
            $scraperService = new \App\Modules\Scraper\ScraperService($model);
            $errorStats = $scraperService->getErrorStats();
        } catch (Exception $e) {
            // Ignore error stats collection errors
            error_log("Error collecting error stats: " . $e->getMessage());
        }

        echo $twig->render('scraper/dashboard.twig', [
            'stats' => $stats,
            'recentJobs' => $recentJobs,
            'activeSources' => $activeSources,
            'errorStats' => $errorStats,
            'pageTitle' => 'Scraper Dashboard'
        ]);
    } catch (Exception $e) {
        error_log("Scraper dashboard error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load scraper dashboard.'
        ]);
    }
});

$router->get('/admin/scraper/gsmarena', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $pipeline = new GSMArenaPipeline($model);
        $statuses = [
            'news' => $pipeline->getLastStatus('news'),
            'devices' => $pipeline->getLastStatus('devices'),
            'bd' => $pipeline->getLastStatus('bd'),
        ];

        echo $twig->render('scraper/gsmarena/dashboard.twig', [
            'pageTitle' => 'GSMArena Scraper',
            'statuses' => $statuses,
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("GSMArena dashboard error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load GSMArena dashboard.'
        ]);
    }
});

/**
 * Redirect /admin/scraper/dashboard to /admin/scraper for backward compatibility
 *
 * @route GET /admin/scraper/dashboard
 */
$router->get('/admin/scraper/dashboard', ['middleware' => ['auth', 'admin_only']], function () {
    header('Location: /admin/scraper', true, 302);
    exit;
});

/**
 * Data Collection Interface
 *
 * Displays the manual data collection interface for triggering scraper runs.
 *
 * @route GET /admin/scraper/collect
 * @middleware auth, admin_only
 *
 * @return void Renders collect template with sources and categories
 *
 * @throws Exception If database query fails or template rendering fails
 */
$router->get('/admin/scraper/collect', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $sources = $model->getAllSources();
        $categories = $model->getCategories();

        echo $twig->render('scraper/collect/index.twig', [
            'sources' => $sources,
            'categories' => $categories,
            'pageTitle' => 'Data Collection',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Collect page error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load collection page.'
        ]);
    }
});

$router->post('/api/v1/scraper/gsmarena/run', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    if (!ensureCsrfToken()) {
        return;
    }

    $input = parseJsonRequest();
    $type = $input['type'] ?? 'news';
    $maxPages = min(max((int)($input['max_pages'] ?? 5), 1), 50);
    $testMode = !empty($input['test']);

    try {
        $pipeline = new GSMArenaPipeline(new ScraperModel($mysqli));
        $result = $pipeline->run($type, $maxPages, $testMode);
        return jsonResponse([
            'success' => true,
            'type' => $type,
            'test_mode' => $testMode,
            'result' => $result
        ]);
    } catch (Exception $e) {
        error_log("GSMArena run error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== COLLECTED DATA ==================

/**
 * Collected Data List
 *
 * Displays all scraped articles with filtering and pagination capabilities.
 *
 * @route GET /admin/scraper/collected-data
 * @middleware auth, admin_only
 *
 * @query_param page int Page number (default: 1, min: 1)
 * @query_param limit int Items per page (default: 20, min: 10, max: 100)
 * @query_param status string Filter by article status (pending, processing, completed, failed)
 * @query_param source string Filter by source ID
 * @query_param search string Search in title and content
 * @query_param content_type string Filter by content type (article, blog, product, job, event)
 *
 * @return void Renders the collected data list template with:
 *               - articles: Array of scraped articles
 *               - pagination: Pagination metadata (total, page, limit, pages)
 *               - sources: Array of all sources for filter dropdown
 *               - statusCounts: Array of article counts by status
 *               - filters: Current filter values
 *               - pageTitle: "Collected Data"
 *
 * @throws Exception If database queries fail or template rendering fails
 *
 * @example Response: HTML page with article table, filters, and pagination
 */
$router->get('/admin/scraper/collected-data', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);

        // Get filter parameters
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
        $status = $_GET['status'] ?? '';
        $sourceFilter = $_GET['source'] ?? '';
        $search = $_GET['search'] ?? '';
        $contentType = $_GET['content_type'] ?? '';
        $categoryFilter = isset($_GET['category']) && $_GET['category'] !== '' ? max(1, (int)$_GET['category']) : null;

        // Fetch articles with pagination
        $result = $model->getArticles($page, $limit, $status, $sourceFilter, $search, $contentType, $categoryFilter);

        // Get all sources for filter dropdown
        $allSources = $model->getAllSources();
        $categories = $model->getCategories();
        $mobilesResult = $model->getMobiles($page, $limit, $sourceFilter, $search);

        // Get status counts for quick stats
        $statusCounts = [
            'all' => $result['pagination']['total'] ?? 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];

        // Count by status (we'll need to query separately for accurate counts)
        try {
            $statusResult = $mysqli->query("SELECT status, COUNT(*) as count FROM web_scraping_articles GROUP BY status");
            if ($statusResult) {
                while ($row = $statusResult->fetch_assoc()) {
                    $statusKey = strtolower($row['status']);
                    if (isset($statusCounts[$statusKey])) {
                        $statusCounts[$statusKey] = (int)$row['count'];
                    }
                }
                $statusResult->free();
            }
        } catch (Exception $e) {
            // Ignore status count errors
        }

        echo $twig->render('scraper/collected-data/list.twig', [
            'articles' => $result['articles'],
            'pagination' => $result['pagination'],
            'mobiles' => $mobilesResult['mobiles'],
            'mobilesPagination' => [
                'total' => (int)$mobilesResult['total'],
                'page' => (int)$mobilesResult['page'],
                'limit' => (int)$mobilesResult['limit'],
                'pages' => (int)$mobilesResult['pages']
            ],
            'pageTitle' => 'Collected Data',
            'filters' => [
                'status' => $status,
                'source' => $sourceFilter,
                'search' => $search,
                'content_type' => $contentType,
                'category' => $categoryFilter,
                'limit' => $limit
            ],
            'sources' => $allSources,
            'categories' => $categories,
            'statusCounts' => $statusCounts
        ]);
    } catch (Exception $e) {
        error_log("Collected data error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load collected data.'
        ]);
    }
});

/**
 * Delete Article
 *
 * Deletes a scraped article from the database.
 *
 * @route DELETE /admin/scraper/collected-data/{id}
 * @middleware auth, admin_only
 *
 * @param id int Article ID to delete (from URL path)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - error: string (error message, if failed)
 *
 * @throws Exception If database operation fails
 *
 * @example Success: {"success": true, "message": "Article deleted successfully"}
 * @example Error: {"success": false, "error": "Article not found or could not be deleted"}
 */
$router->delete('/admin/scraper/collected-data/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $result = $model->deleteArticle($id);

        if ($result) {
            logActivity('scraper_article_deleted', 'article', $id, ['user_id' => $_SESSION['user_id'] ?? 0]);
            return jsonResponse([
                'success' => true,
                'message' => 'Article deleted successfully'
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Article not found or could not be deleted'], 404);
    } catch (Exception $e) {
        error_log("Delete article error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Delete Article (legacy route)
 *
 * Handles DELETE requests previously routed to /admin/scraper/articles/{id} for compatibility with older clients.
 *
 * @route DELETE /admin/scraper/articles/{id}
 * @middleware auth, admin_only
 */
$router->delete('/admin/scraper/articles/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $result = $model->deleteArticle($id);

        if ($result) {
            logActivity('scraper_article_deleted', 'article', $id, ['user_id' => $_SESSION['user_id'] ?? 0]);
            return jsonResponse([
                'success' => true,
                'message' => 'Article deleted successfully'
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Article not found or could not be deleted'], 404);
    } catch (Exception $e) {
        error_log("Legacy delete article error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * View Article Details
 *
 * Displays detailed view of a scraped article including all extracted data.
 *
 * @route GET /admin/scraper/collected-data/{id}
 * @middleware auth, admin_only
 *
 * @param id int Article ID to view (from URL path)
 *
 * @return void Renders article details template with:
 *               - article: Article object with all fields
 *               - pageTitle: "Article Details"
 *
 * @throws Exception If database query fails or article not found
 *
 * @example Response: HTML page with article content, metadata, and actions
 */
$router->get('/admin/scraper/collected-data/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'pageTitle' => 'Article Not Found',
                'message' => 'The requested article was not found.'
            ]);
            return;
        }

        $article = prepareScrapedArticlePayload($article);

        echo $twig->render('scraper/collected-data/view.twig', [
            'article' => $article,
            'pageTitle' => 'Article Details'
        ]);
    } catch (Exception $e) {
        error_log("View article error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load article details.'
        ]);
    }
});

/**
 * Article Details API
 *
 * Returns structured article metadata for AJAX/detail views.
 *
 * @route GET /admin/scraper/articles/{id}/json
 * @middleware auth, admin_only
 */
$router->get('/admin/scraper/articles/{id}/json', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            return jsonResponse(['success' => false, 'error' => 'Article not found'], 404);
        }

        $article = prepareScrapedArticlePayload($article);

        return jsonResponse(['success' => true, 'article' => $article]);
    } catch (Exception $e) {
        error_log("Article JSON endpoint error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => 'Failed to load article details'], 500);
    }
});

// ================== ARTICLES REDIRECTS ==================

/**
 * Articles List Redirect
 *
 * Redirects /admin/scraper/articles to /admin/scraper/collected-data for backward compatibility
 *
 * @route GET /admin/scraper/articles
 * @middleware auth, admin_only
 */
$router->get('/admin/scraper/articles', ['middleware' => ['auth', 'admin_only']], function () {
    // Preserve query parameters
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $redirectUrl = '/admin/scraper/collected-data';
    if (!empty($queryString)) {
        $redirectUrl .= '?' . $queryString;
    }
    header('Location: ' . $redirectUrl, true, 302);
    exit;
});

/**
 * Article Detail Redirect
 *
 * Redirects /admin/scraper/articles/{id} to /admin/scraper/collected-data/{id} for backward compatibility
 *
 * @route GET /admin/scraper/articles/{id}
 * @middleware auth, admin_only
 */
$router->get('/admin/scraper/articles/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) {
    header('Location: /admin/scraper/collected-data/' . $id, true, 302);
    exit;
});

// ================== AI FEATURES ==================

/**
 * AI Preset Generator
 *
 * Displays the AI-powered preset generator interface for creating scraper configurations.
 *
 * @route GET /admin/scraper/ai/preset-generator
 * @middleware auth, admin_only
 *
 * @return void Renders the preset generator template with:
 *               - pageTitle: "AI Preset Generator"
 *
 * @throws Exception If template rendering fails
 *
 * @example Response: HTML page with URL input form and AI analysis interface
 */
$router->get('/admin/scraper/ai/preset-generator', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    try {
        echo $twig->render('scraper/ai/preset-generator.twig', [
            'pageTitle' => 'AI Preset Generator'
        ]);
    } catch (Exception $e) {
        error_log("AI Preset Generator UI error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load AI Preset Generator.'
        ]);
    }
});

/**
 * AI Preset Generator - Analyze URL
 *
 * Analyzes a website URL and generates a complete scraper preset configuration using AI.
 *
 * @route POST /admin/scraper/ai/preset-generator/analyze
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - url (string, required): Website URL to analyze
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - preset: Generated preset configuration (if successful)
 *               - selectors: Detected CSS/XPath selectors
 *               - confidence: AI confidence score (0-1)
 *               - content_type: Detected content type (article, blog, product, job, event)
 *               - error: string (if failed)
 *
 * @throws Exception If AI analysis fails or URL is invalid
 *
 * @example Request: {"url": "https://example.com/news"}
 * @example Success: {"success": true, "preset": {...}, "confidence": 0.95}
 * @example Error: {"success": false, "error": "Valid URL is required"}
 */
$router->post('/admin/scraper/ai/preset-generator/analyze', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        $url = trim($input['url'] ?? '');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return jsonResponse(['success' => false, 'error' => 'Valid URL is required'], 400);
        }

        $generator = AIPresetGenerator::fromMysqli($mysqli);
        $result = $generator->generatePreset($url);

        return jsonResponse($result->toArray());
    } catch (Exception $e) {
        error_log("AI Preset Generator analyze error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Displays detailed view of a specific scraper preset.
 *
 * @route GET /admin/scraper/presets/{key}
 * @middleware auth, admin_only
 *
 * @param key string Preset key to view (from URL path)
 *
 * @return void Renders preset details template with:
 *               - preset: Preset object containing:
 *                   - key: string (unique identifier)
 *                   - name: string (display name)
 *                   - description: string (description)
 *                   - category: string (category)
 *                   - icon: string (icon class)
 *                   - type: string (scraper type)
 *                   - content_type: string (content type)
 *                   - example_urls: array (example URLs)
 *                   - config: array (full configuration)
 *
 * @throws Exception If preset not found or template rendering fails
 *
 * @example Response: HTML page with preset details and configuration
 */
$router->get('/admin/scraper/presets/{key}', ['middleware' => ['auth', 'admin_only']], function ($key) use ($twig) {
    try {
        $preset = PresetRegistry::getByKey($key);

        if (!$preset) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'pageTitle' => 'Preset Not Found',
                'message' => 'The requested preset was not found.'
            ]);
            return;
        }

        echo $twig->render('scraper/presets/view.twig', [
            'preset' => [
                'key' => $preset->getKey(),
                'name' => $preset->getName(),
                'description' => $preset->getDescription(),
                'category' => $preset->getCategory(),
                'icon' => $preset->getIcon(),
                'type' => $preset->getType(),
                'content_type' => $preset->getContentType(),
                'example_urls' => $preset->getExampleUrls(),
                'config' => $preset->getConfig()
            ],
            'pageTitle' => 'Preset Details'
        ]);
    } catch (Exception $e) {
        error_log("Preset view error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load preset details.'
        ]);
    }
});

/**
 * Create Preset Form
 *
 * Displays the form for creating a new scraper preset.
 *
 * @route GET /admin/scraper/presets/create
 * @middleware auth, admin_only
 *
 * @return void Renders create preset template with:
 *               - preset: null (for new preset)
 *               - categories: Array of available categories
 *               - pageTitle: "Create Scraper Preset"
 *
 * @throws Exception If template rendering fails
 *
 * @example Response: HTML form with fields for preset configuration
 */
$router->get('/admin/scraper/presets/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    try {
        $categories = PresetRegistry::getCategories();

        echo $twig->render('scraper/presets/form.twig', [
            'preset' => null,
            'categories' => $categories,
            'pageTitle' => 'Create Scraper Preset',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Create preset form error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load create preset form.'
        ]);
    }
});

/**
 * Edit Preset Form
 *
 * Displays the form for editing an existing scraper preset.
 *
 * @route GET /admin/scraper/presets/{key}/edit
 * @middleware auth, admin_only
 *
 * @param key string Preset key to edit (from URL path)
 *
 * @return void Renders edit preset template with:
 *               - preset: Preset object with current configuration
 *               - categories: Array of available categories
 *               - pageTitle: "Edit Scraper Preset"
 *
 * @throws Exception If preset not found or template rendering fails
 *
 * @example Response: HTML form pre-filled with preset data
 */
$router->get('/admin/scraper/presets/{key}/edit', ['middleware' => ['auth', 'admin_only']], function ($key) use ($twig) {
    try {
        $preset = PresetRegistry::getByKey($key);
        $categories = PresetRegistry::getCategories();

        if (!$preset) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'pageTitle' => 'Preset Not Found',
                'message' => 'The requested preset was not found.'
            ]);
            return;
        }

        echo $twig->render('scraper/presets/form.twig', [
            'preset' => [
                'key' => $preset->getKey(),
                'name' => $preset->getName(),
                'description' => $preset->getDescription(),
                'category' => $preset->getCategory(),
                'icon' => $preset->getIcon(),
                'type' => $preset->getType(),
                'content_type' => $preset->getContentType(),
                'example_urls' => $preset->getExampleUrls(),
                'config' => $preset->getConfig()
            ],
            'categories' => $categories,
            'pageTitle' => 'Edit Scraper Preset',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Edit preset form error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load edit preset form.'
        ]);
    }
});

/**
 * Save Preset (Create/Update)
 *
 * Creates a new preset or updates an existing one.
 *
 * @route POST /admin/scraper/presets/save
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - key (string, required for updates): Preset key
 *               - name (string, required): Preset name
 *               - description (string, required): Preset description
 *               - category (string, required): Preset category
 *               - content_type (string, required): Content type
 *               - icon (string, optional): Icon class
 *               - type (string, optional): Scraper type
 *               - example_urls (string, optional): JSON array of example URLs
 *               - selectors (string, optional): JSON object of selectors
 *               - advance_config (string, optional): JSON object of advance config
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - key: string (preset key)
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails or validation fails
 *
 * @example Success: {"success": true, "message": "Preset created successfully", "key": "my-preset"}
 * @example Error: {"success": false, "error": "Failed to save preset"}
 */
$router->post('/admin/scraper/presets/save', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $model = new ScraperModel($mysqli);
        $key = trim($_POST['key'] ?? '');

        // Validate required fields
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $contentType = trim($_POST['content_type'] ?? '');

        if (empty($name)) {
            return jsonResponse(['success' => false, 'error' => 'Preset name is required'], 400);
        }

        if (empty($description)) {
            return jsonResponse(['success' => false, 'error' => 'Preset description is required'], 400);
        }

        if (empty($category)) {
            return jsonResponse(['success' => false, 'error' => 'Preset category is required'], 400);
        }

        if (empty($contentType)) {
            return jsonResponse(['success' => false, 'error' => 'Content type is required'], 400);
        }

        // Validate and sanitize JSON fields
        $selectors = null;
        $selectorsRaw = trim($_POST['selectors'] ?? '');
        if (!empty($selectorsRaw)) {
            $decoded = json_decode($selectorsRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return jsonResponse(['success' => false, 'error' => 'Invalid selectors JSON'], 400);
            }
            $selectors = $selectorsRaw;
        }

        $advanceConfig = null;
        $advanceConfigRaw = trim($_POST['advance_config'] ?? '');
        if (!empty($advanceConfigRaw)) {
            $decoded = json_decode($advanceConfigRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return jsonResponse(['success' => false, 'error' => 'Invalid advance_config JSON'], 400);
            }
            $advanceConfig = $advanceConfigRaw;
        }

        $exampleUrls = null;
        $exampleUrlsRaw = trim($_POST['example_urls'] ?? '');
        if (!empty($exampleUrlsRaw)) {
            $decoded = json_decode($exampleUrlsRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return jsonResponse(['success' => false, 'error' => 'Invalid example_urls JSON'], 400);
            }
            $exampleUrls = $exampleUrlsRaw;
        }

        // Generate key if creating new preset
        if (empty($key)) {
            $key = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name));
            $key = preg_replace('/-+/', '-', $key);
            $key = trim($key, '-');
        }

        $data = [
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'content_type' => $contentType,
            'selectors' => $selectors,
            'advance_config' => $advanceConfig,
            'is_default' => 0,
            'is_active' => 1
        ];

        $presetId = $model->savePreset($data);

        if ($presetId) {
            logActivity('scraper_preset_saved', 'preset', $presetId, [
                'preset_key' => $key,
                'preset_name' => $name,
                'user_id' => $_SESSION['user_id'] ?? 0
            ]);

            return jsonResponse([
                'success' => true,
                'message' => empty($_POST['key']) ? 'Preset created successfully' : 'Preset updated successfully',
                'key' => $key
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to save preset'], 500);
    } catch (Exception $e) {
        error_log("Save preset error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * AI Selector Detection
 *
 * Analyzes a website URL and automatically detects CSS selectors using AI.
 *
 * @route POST /api/v1/scraper/presets/ai-detect
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - url (string, required): Website URL to analyze
 *               - content_type (string, optional): Expected content type (news, blog, product, etc.)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - selectors: Detected CSS selectors for various elements
 *               - confidence: AI confidence score (0-1)
 *               - recommendations: AI recommendations for scraper configuration
 *               - content_type: Detected content type
 *               - error: string (if failed)
 *
 * @throws Exception If AI analysis fails or URL is invalid
 *
 * @example Request: {"url": "https://example.com", "content_type": "news"}
 * @example Success: {"success": true, "selectors": {...}, "confidence": 0.85, "content_type": "news"}
 * @example Error: {"success": false, "error": "URL analysis failed"}
 */
$router->post('/api/v1/scraper/presets/ai-detect', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        $url = trim($input['url'] ?? '');
        $contentType = trim($input['content_type'] ?? '');

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return jsonResponse(['success' => false, 'error' => 'Valid URL is required'], 400);
        }

        // Use AIScraperAnalyzer to detect selectors
        $analyzer = \App\Modules\Scraper\AIScraperAnalyzer::fromMysqli($mysqli);

        try {
            // First fetch HTML
            $html = \App\Modules\Scraper\HtmlFetcher::fetch($url);

            // Then analyze it
            $result = $analyzer->analyzeHtml($html, $url);

            if (!$result['success']) {
                return jsonResponse(['success' => false, 'error' => 'AI analysis failed'], 500);
            }

            return jsonResponse([
                'success' => true,
                'selectors' => $result['selectors'] ?? [],
                'confidence' => $result['confidence'] ?? 0,
                'content_type' => $result['content_type'] ?? $contentType,
                'recommendations' => $result['recommendations'] ?? []
            ]);

        } catch (\Exception $e) {
            return jsonResponse(['success' => false, 'error' => 'Failed to analyze URL: ' . $e->getMessage()], 500);
        }

    } catch (Exception $e) {
        error_log("AI selector detection error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Delete Preset
 *
 * Deletes a scraper preset from the database.
 *
 * @route DELETE /admin/scraper/presets/{key}
 * @middleware auth, admin_only
 *
 * @param key string Preset key to delete (from URL path)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - error: string (error message, if failed)
 *
 * @throws Exception If database operation fails
 *
 * @example Success: {"success": true, "message": "Preset deleted successfully"}
 * @example Error: {"success": false, "error": "Preset not found"}
 */
$router->delete('/admin/scraper/presets/{key}', ['middleware' => ['auth', 'admin_only']], function ($key) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $model = new ScraperModel($mysqli);

        // Check if preset exists
        $preset = \App\Modules\Scraper\Presets\PresetRegistry::getByKey($key);
        if (!$preset) {
            return jsonResponse(['success' => false, 'error' => 'Preset not found'], 404);
        }

        // Delete from database
        $result = $model->deletePreset($key);

        if ($result) {
            logActivity('scraper_preset_deleted', 'preset', 0, [
                'preset_key' => $key,
                'preset_name' => $preset->getName(),
                'user_id' => $_SESSION['user_id'] ?? 0
            ]);

            return jsonResponse([
                'success' => true,
                'message' => 'Preset deleted successfully'
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to delete preset'], 500);
    } catch (Exception $e) {
        error_log("Delete preset error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * AI Scraper Analyzer
 *
 * Displays the AI-powered website structure analyzer interface.
 *
 * @route GET /admin/scraper/ai/analyzer
 * @middleware auth, admin_only
 *
 * @return void Renders the analyzer template with:
 *               - pageTitle: "AI Scraper Analyzer"
 *
 * @throws Exception If template rendering fails
 *
 * @example Response: HTML page with URL input and analysis results display
 */
$router->get('/admin/scraper/ai/analyzer', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    try {
        echo $twig->render('scraper/ai/analyzer.twig', [
            'pageTitle' => 'AI Scraper Analyzer'
        ]);
    } catch (Exception $e) {
        error_log("AI Scraper Analyzer UI error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load AI Scraper Analyzer.'
        ]);
    }
});

/**
 * AI Scraper Analyzer - Analyze HTML
 *
 * Analyzes HTML content or URL to detect website structure and suggest selectors.
 *
 * @route POST /admin/scraper/ai/analyzer/run
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - url (string, optional): Website URL to fetch and analyze
 *               - html (string, optional): Raw HTML content to analyze
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - selectors: Detected selectors for various content types
 *               - structure: Website structure analysis
 *               - recommendations: AI recommendations for scraper configuration
 *               - confidence: AI confidence score (0-1)
 *               - error: string (if failed)
 *
 * @throws Exception If AI analysis fails or input is invalid
 *
 * @example Request: {"url": "https://example.com"}
 * @example Request: {"html": "<html>...</html>"}
 * @example Success: {"success": true, "selectors": {...}, "confidence": 0.92}
 * @example Error: {"success": false, "error": "Either URL or HTML content is required"}
 */
$router->post('/admin/scraper/ai/analyzer/run', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        $url = trim($input['url'] ?? '');
        $html = trim($input['html'] ?? '');

        if ($html === '' && $url !== '') {
            try {
                $html = HtmlFetcher::fetch($url);
            } catch (\Exception $fetchError) {
                error_log('AI Scraper Analyzer fetch error: ' . $fetchError->getMessage());
                return jsonResponse(['success' => false, 'error' => 'Failed to fetch HTML for analysis'], 500);
            }
        }

        if (trim((string)$html) === '' && $url === '') {
            return jsonResponse(['success' => false, 'error' => 'Either URL or HTML content is required'], 400);
        }

        $analyzer = AIScraperAnalyzer::fromMysqli($mysqli);
        try {
            $result = $analyzer->analyzeHtml($html, $url);
        } catch (\InvalidArgumentException $ex) {
            return jsonResponse(['success' => false, 'error' => $ex->getMessage()], 400);
        }

        return jsonResponse($result);
    } catch (Exception $e) {
        error_log("AI Scraper Analyzer run error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * AI Content Classifier
 *
 * Displays AI-powered content classifier interface for analyzing scraped content.
 *
 * @route GET /admin/scraper/ai/classifier
 * @middleware auth, admin_only
 *
 * @return void Renders classifier template with:
 *               - pageTitle: "AI Content Classifier"
 *
 * @throws Exception If template rendering fails
 *
 * @example Response: HTML page with HTML input form and classification results display
 */
$router->get('/admin/scraper/ai/classifier', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    try {
        echo $twig->render('scraper/ai/classifier.twig', [
            'pageTitle' => 'AI Content Classifier'
        ]);
    } catch (Exception $e) {
        error_log("AI Content Classifier UI error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load AI Content Classifier.'
        ]);
    }
});

/**
 * AI Content Classifier - Analyze Content
 *
 * Classifies HTML content and extracts structured data using AI.
 *
 * @route POST /admin/scraper/ai/classifier/analyze
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - html (string, required): HTML content to classify
 *               - url (string, optional): Source URL for context
 *               - selectors (array, optional): Custom selectors to test
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - content_type: Detected content type (news, blog, product, job, event)
 *               - extracted_data: Structured data extracted from HTML
 *               - confidence: AI confidence score (0-1)
 *               - metadata: Additional metadata (title, description, etc.)
 *               - error: string (if failed)
 *
 * @throws Exception If AI classification fails or HTML is invalid
 *
 * @example Request: {"html": "<html>...</html>", "url": "https://example.com"}
 * @example Success: {"success": true, "content_type": "news", "extracted_data": {...}}
 * @example Error: {"success": false, "error": "HTML content is required"}
 */
$router->post('/admin/scraper/ai/classifier/analyze', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        $html = trim($input['html'] ?? '');
        $url = trim($input['url'] ?? '');
        $selectors = $input['selectors'] ?? [];

        if ($html === '') {
            return jsonResponse(['success' => false, 'error' => 'HTML content is required'], 400);
        }

        $classifier = new AIContentClassifier($mysqli);
        $result = $classifier->classifyAndExtract($html, $url, $selectors);

        return jsonResponse($result);
    } catch (Exception $e) {
        error_log("AI Content Classifier analyze error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * AI Scraper Optimizer
 *
 * Displays AI-powered scraper optimizer interface for performance analysis and optimization.
 *
 * @route GET /admin/scraper/ai/optimizer
 * @middleware auth, admin_only
 *
 * @return void Renders optimizer template with:
 *               - pageTitle: "AI Scraper Optimizer"
 *               - hasData: boolean indicating if performance data exists
 *               - metrics: Performance metrics (if available)
 *               - recommendations: AI optimization recommendations (if available)
 *
 * @throws Exception If template rendering fails
 *
 * @example Response: HTML page with performance charts and optimization suggestions
 */
$router->get('/admin/scraper/ai/optimizer', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $optimizer = new AIScraperOptimizer($mysqli);

        // Get recent performance data (you may need to implement this in ?\AutoContentModel or ScraperModel)
        // For now, show empty state
        echo $twig->render('scraper/ai/optimizer.twig', [
            'pageTitle' => 'AI Scraper Optimizer',
            'hasData' => false,
            'metrics' => null,
            'recommendations' => null
        ]);
    } catch (Exception $e) {
        error_log("AI Scraper Optimizer UI error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load AI Scraper Optimizer.'
        ]);
    }
});

/**
 * AI Scraper Optimizer - Analyze Performance
 *
 * Analyzes scraper performance data and provides AI-powered optimization recommendations.
 *
 * @route POST /admin/scraper/ai/optimizer/analyze
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - source_id (int, optional): Source ID to optimize
 *               - performance_data (array, required): Performance metrics data
 *               - current_config (array, optional): Current scraper configuration
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - recommendations: Array of optimization suggestions
 *               - optimized_config: Suggested configuration changes
 *               - expected_improvement: Expected performance improvement percentage
 *               - priority: Optimization priority (high, medium, low)
 *               - error: string (if failed)
 *
 * @throws Exception If AI optimization fails or performance data is invalid
 *
 * @example Request: {"source_id": 123, "performance_data": {...}}
 * @example Success: {"success": true, "recommendations": [...], "expected_improvement": 25}
 * @example Error: {"success": false, "error": "Performance data is required"}
 */
$router->post('/admin/scraper/ai/optimizer/analyze', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        $sourceId = isset($input['source_id']) ? (int)$input['source_id'] : null;
        $performanceData = $input['performance_data'] ?? [];
        $currentConfig = $input['current_config'] ?? [];

        if (empty($performanceData)) {
            return jsonResponse(['success' => false, 'error' => 'Performance data is required'], 400);
        }

        $optimizer = new AIScraperOptimizer($mysqli);
        $result = $optimizer->optimizeStrategy($performanceData, $currentConfig);

        // Store optimization history if source ID provided
        if ($sourceId && $result['success']) {
            $optimizer->storeOptimizationHistory((string)$sourceId, $result);
        }

        return jsonResponse($result);
    } catch (Exception $e) {
        error_log("AI Scraper Optimizer analyze error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: AI Preset Generator
 *
 * @route POST /api/v1/scraper/ai/preset-generator
 * @middleware auth, admin_only
 */
$router->post('/api/v1/scraper/ai/preset-generator', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!ensureCsrfToken()) {
        return;
    }

    try {
        $input = parseJsonRequest();
        $url = trim($input['url'] ?? '');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return jsonResponse(['success' => false, 'error' => 'Valid URL is required'], 400);
        }

        $generator = AIPresetGenerator::fromMysqli($mysqli);
        $result = $generator->generatePreset($url, $input['options'] ?? []);
        return jsonResponse($result->toArray());
    } catch (Exception $e) {
        error_log("API AI preset generator error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: AI Scraper Analyzer
 *
 * @route POST /api/v1/scraper/ai/analyzer
 * @middleware auth, admin_only
 */
$router->post('/api/v1/scraper/ai/analyzer', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!ensureCsrfToken()) {
        return;
    }

    try {
        $input = parseJsonRequest();
        $url = trim($input['url'] ?? '');
        $html = trim($input['html'] ?? '');

        if ($url === '' && $html === '') {
            return jsonResponse(['success' => false, 'error' => 'Either URL or HTML content is required'], 400);
        }

        $analyzer = AIScraperAnalyzer::fromMysqli($mysqli);
        $result = $analyzer->analyzeHtml($html, $url);

        return jsonResponse($result);
    } catch (Exception $e) {
        error_log("API AI analyzer error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: AI Content Classifier
 *
 * @route POST /api/v1/scraper/ai/classifier
 * @middleware auth, admin_only
 */
$router->post('/api/v1/scraper/ai/classifier', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!ensureCsrfToken()) {
        return;
    }

    try {
        $input = parseJsonRequest();
        $html = trim($input['html'] ?? '');
        $url = trim($input['url'] ?? '');
        $selectors = $input['selectors'] ?? [];

        if ($html === '') {
            return jsonResponse(['success' => false, 'error' => 'HTML content is required'], 400);
        }

        $classifier = new AIContentClassifier($mysqli);
        $result = $classifier->classifyAndExtract($html, $url, $selectors);

        return jsonResponse($result);
    } catch (Exception $e) {
        error_log("API AI classifier error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: AI Scraper Optimizer
 *
 * @route POST /api/v1/scraper/ai/optimizer
 * @middleware auth, admin_only
 */
$router->post('/api/v1/scraper/ai/optimizer', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!ensureCsrfToken()) {
        return;
    }

    try {
        $input = parseJsonRequest();
        $sourceId = isset($input['source_id']) ? (int)$input['source_id'] : null;
        $performanceData = $input['performance_data'] ?? [];
        $currentConfig = $input['current_config'] ?? [];

        if (empty($performanceData)) {
            return jsonResponse(['success' => false, 'error' => 'Performance data is required'], 400);
        }

        $optimizer = new AIScraperOptimizer($mysqli);
        $result = $optimizer->optimizeStrategy($performanceData, $currentConfig);
        if ($sourceId && $result['success']) {
            $optimizer->storeOptimizationHistory((string)$sourceId, $result);
        }

        return jsonResponse($result);
    } catch (Exception $e) {
        error_log("API AI optimizer error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== ERROR MONITORING ==================

/**
 * Get Error Statistics API
 *
 * Returns current error statistics and monitoring data for the scraping system.
 *
 * @route GET /api/v1/scraper/error-stats
 * @middleware auth, admin_only
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - error_stats: Error statistics object with:
 *                   - total: Total number of errors
 *                   - by_type: Errors grouped by type (network, parsing, etc.)
 *                   - by_severity: Errors grouped by severity (low, medium, high, critical)
 *                   - recent: Array of recent error entries
 *               - error: string (if failed)
 *
 * @example {"success": true, "error_stats": {"total": 15, "by_type": {...}, ...}}
 */
$router->get('/api/v1/scraper/error-stats', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $scraperService = new \App\Modules\Scraper\ScraperService($model);
        $errorStats = $scraperService->getErrorStats();

        return jsonResponse([
            'success' => true,
            'error_stats' => $errorStats
        ]);
    } catch (Exception $e) {
        error_log("Error stats API error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Clear Error Logs API
 *
 * Clears the accumulated error logs in the scraper service.
 *
 * @route POST /api/v1/scraper/clear-errors
 * @middleware auth, admin_only
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: Success message
 *               - error: string (if failed)
 *
 * @example {"success": true, "message": "Error logs cleared successfully"}
 */
$router->post('/api/v1/scraper/clear-errors', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $model = new ScraperModel($mysqli);
        $scraperService = new \App\Modules\Scraper\ScraperService($model);
        $scraperService->clearErrors();

        return jsonResponse([
            'success' => true,
            'message' => 'Error logs cleared successfully'
        ]);
    } catch (Exception $e) {
        error_log("Clear errors API error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== SELECTOR TESTER ==================

/**
 * Selector Tester UI
 *
 * Displays interactive tool for testing CSS/XPath selectors against live URLs.
 *
 * @route GET /admin/scraper/selector-tester
 * @middleware auth, admin_only
 *
 * @return void Renders selector tester template with:
 *               - pageTitle: "Selector Tester"
 *
 * @throws Exception If template rendering fails
 *
 * @example Response: HTML page with URL input, selector input, and results display
 */
$router->get('/admin/scraper/selector-tester', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    try {
        echo $twig->render('admin/scraper/selector-tester.twig', [
            'pageTitle' => 'Selector Tester'
        ]);
    } catch (Exception $e) {
        error_log("Selector tester UI error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load selector tester.'
        ]);
    }
});

// ================== SOURCES MANAGEMENT ==================

/**
 * Sources List
 *
 * Displays list of all scraper sources with their configurations and status.
 *
 * @route GET /admin/scraper/sources
 * @middleware auth, admin_only
 *
 * @return void Renders sources list template with:
 *               - sources: Array of scraper sources
 *               - pageTitle: "Scraper Sources"
 *
 * @throws Exception If database query fails or template rendering fails
 *
 * @example Response: HTML page with sources table showing name, URL, type, status
 */
$router->get('/admin/scraper/sources', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $sources = $model->getAllSources();

        echo $twig->render('scraper/sources/list.twig', [
            'sources' => $sources,
            'pageTitle' => 'Scraper Sources',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Sources list error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load sources.'
        ]);
    }
});

/**
 * Create Source Form
 *
 * Displays form for creating a new scraper source.
 *
 * @route GET /admin/scraper/sources/create
 * @middleware auth, admin_only
 *
 * @return void Renders source form template with:
 *               - source: null (for new source)
 *               - categories: Array of available categories
 *               - presets: Array of available presets
 *               - pageTitle: "Create Scraper Source"
 *
 * @throws Exception If database queries fail or template rendering fails
 *
 * @example Response: HTML form with fields for source configuration
 */
$router->get('/admin/scraper/sources/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $categories = $model->getCategories();
        $presets = PresetRegistry::toArray();

        echo $twig->render('scraper/sources/form.twig', [
            'source' => null,
            'categories' => $categories,
            'presets' => $presets,
            'pageTitle' => 'Create Scraper Source'
        ]);
    } catch (Exception $e) {
        error_log("Create source form error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load form.'
        ]);
    }
});

/**
 * Edit Source Form
 *
 * Displays form for editing an existing scraper source.
 *
 * @route GET /admin/scraper/sources/{id}/edit
 * @middleware auth, admin_only
 *
 * @param id int Source ID to edit (from URL path)
 *
 * @return void Renders source form template with:
 *               - source: Source object with current configuration
 *               - categories: Array of available categories
 *               - presets: Array of available presets
 *               - pageTitle: "Edit Scraper Source"
 *
 * @throws Exception If database query fails, source not found, or template rendering fails
 *
 * @example Response: HTML form pre-filled with source data
 */
$router->get('/admin/scraper/sources/{id}/edit', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $source = $model->getSourceById($id);
        $categories = $model->getCategories();
        $presets = PresetRegistry::toArray();

        if (!$source) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'pageTitle' => 'Source Not Found',
                'message' => 'The requested scraper source was not found.'
            ]);
            return;
        }

        echo $twig->render('scraper/sources/form.twig', [
            'source' => $source,
            'categories' => $categories,
            'presets' => $presets,
            'pageTitle' => 'Edit Scraper Source'
        ]);
    } catch (Exception $e) {
        error_log("Edit source form error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load form.'
        ]);
    }
});

/**
 * View Source Details
 *
 * Displays read-only view of a scraper source configuration.
 *
 * @route GET /admin/scraper/sources/{id}
 * @middleware auth, admin_only
 *
 * @param id int Source ID to view (from URL path)
 *
 * @return void Renders source form template with:
 *               - source: Source object with configuration
 *               - categories: Empty array (not needed for view)
 *               - presets: Empty array (not needed for view)
 *               - pageTitle: "View Scraper Source"
 *               - readonly: true (prevents editing)
 *
 * @throws Exception If database query fails, source not found, or template rendering fails
 *
 * @example Response: HTML page with read-only source details
 */
$router->get('/admin/scraper/sources/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $source = $model->getSourceById($id);

        if (!$source) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'pageTitle' => 'Source Not Found',
                'message' => 'The requested scraper source was not found.'
            ]);
            return;
        }

        echo $twig->render('scraper/sources/form.twig', [
            'source' => $source,
            'categories' => [],
            'presets' => [],
            'pageTitle' => 'View Scraper Source',
            'readonly' => true
        ]);
    } catch (Exception $e) {
        error_log("View source error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load source.'
        ]);
    }
});

/**
 * Save Source (Create/Update)
 *
 * Creates a new scraper source or updates an existing one.
 *
 * @route POST /admin/scraper/sources/save
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - id (int, optional): Source ID (for updates)
 *               - name (string, required): Source name
 *               - url (string, required): Source URL
 *               - type (string): Scraper type (static, api, rss, xml, js, advance)
 *               - category_id (int): Category ID
 *               - selector_* (string): Various CSS/XPath selectors
 *               - advance_config (string): JSON configuration for advanced scrapers
 *               - fetch_interval (int): Fetch interval in minutes
 *               - is_active (bool): Active status
 *               - content_type (string): Content type (article, blog, product, job, event)
 *               - scrape_depth (int): Scrape depth level
 *               - use_browser (bool): Use browser for scraping
 *               - max_pages (int): Maximum pages to scrape
 *               - delay (int): Delay between requests (seconds)
 *               - pagination_type (string): Pagination type (query, selector, pattern)
 *               - pagination_selector (string): Pagination selector
 *               - pagination_pattern (string): Pagination pattern
 *               - proxy_enabled (bool): Enable proxy
 *               - proxy_provider (string): Proxy provider
 *               - proxy_config (string): Proxy configuration
 *               - website_preset_key (string): Website preset key
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - id: int (created/updated source ID)
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails or validation fails
 *
 * @example Success: {"success": true, "message": "Source created successfully", "id": 123}
 * @example Error: {"success": false, "error": "Failed to save source"}
 */
$router->post('/admin/scraper/sources/save', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $model = new ScraperModel($mysqli);
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

        // Validate required fields
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');

        if (empty($name)) {
            return jsonResponse(['success' => false, 'error' => 'Source name is required'], 400);
        }

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return jsonResponse(['success' => false, 'error' => 'Valid URL is required'], 400);
        }

        // Validate and sanitize advance_config
        $advanceConfigRaw = trim($_POST['advance_config'] ?? '');
        $advanceConfig = [];
        if ($advanceConfigRaw !== '') {
            $decoded = json_decode($advanceConfigRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return jsonResponse(['success' => false, 'error' => 'Invalid advance_config JSON'], 400);
            }
            // Basic validation - ensure it's an array
            if (!is_array($decoded)) {
                return jsonResponse(['success' => false, 'error' => 'advance_config must be a JSON object'], 400);
            }
            $advanceConfig = $decoded;
        }

        $data = [
            'name' => $name,
            'url' => $url,
            'type' => $_POST['type'] ?? 'static',
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'selector_list_container' => $_POST['selector_list_container'] ?? '',
            'selector_list_item' => $_POST['selector_list_item'] ?? '',
            'selector_list_title' => $_POST['selector_list_title'] ?? '',
            'selector_list_link' => $_POST['selector_list_link'] ?? '',
            'selector_list_date' => $_POST['selector_list_date'] ?? '',
            'selector_list_image' => $_POST['selector_list_image'] ?? '',
            'selector_title' => $_POST['selector_title'] ?? '',
            'selector_content' => $_POST['selector_content'] ?? '',
            'selector_image' => $_POST['selector_image'] ?? '',
            'selector_excerpt' => $_POST['selector_excerpt'] ?? '',
            'selector_date' => $_POST['selector_date'] ?? '',
            'selector_author' => $_POST['selector_author'] ?? '',
            'selector_pagination' => $_POST['selector_pagination'] ?? '',
            'selector_read_more' => $_POST['selector_read_more'] ?? '',
            'selector_category' => $_POST['selector_category'] ?? '',
            'selector_tags' => $_POST['selector_tags'] ?? '',
            'selector_video' => $_POST['selector_video'] ?? '',
            'selector_audio' => $_POST['selector_audio'] ?? '',
            'selector_source_url' => $_POST['selector_source_url'] ?? '',
            'advance_config' => json_encode($advanceConfig),
            'fetch_interval' => (int)($_POST['fetch_interval'] ?? 60),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'content_type' => $_POST['content_type'] ?? 'article',
            'scrape_depth' => (int)($_POST['scrape_depth'] ?? 1),
            'use_browser' => isset($_POST['use_browser']) ? 1 : 0,
            'max_pages' => (int)($_POST['max_pages'] ?? 5),
            'delay' => (int)($_POST['delay'] ?? 2),
            'pagination_type' => $_POST['pagination_type'] ?? 'query',
            'pagination_selector' => $_POST['pagination_selector'] ?? '',
            'pagination_pattern' => $_POST['pagination_pattern'] ?? '',
            'proxy_enabled' => isset($_POST['proxy_enabled']) ? 1 : 0,
            'proxy_provider' => $_POST['proxy_provider'] ?? '',
            'proxy_config' => $_POST['proxy_config'] ?? '',
            'website_preset_key' => $_POST['website_preset_key'] ?? ''
        ];

        if ($id) {
            $result = $model->updateSource($id, $data);
            $message = 'Source updated successfully';
        } else {
            $result = $model->createSource($data);
            $message = 'Source created successfully';
        }

        if ($result) {
            logActivity('scraper_source_saved', 'source', $id ?? $result, ['user_id' => $_SESSION['user_id'] ?? 0]);
            return jsonResponse([
                'success' => true,
                'message' => $message,
                'id' => $id ?? $result
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to save source'], 500);
    } catch (Exception $e) {
        error_log("Save source error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Delete Source
 *
 * Deletes a scraper source from the database.
 *
 * @route POST /admin/scraper/sources/delete
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - id (int, required): Source ID to delete
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails
 *
 * @example Success: {"success": true, "message": "Source deleted successfully"}
 * @example Error: {"success": false, "error": "Failed to delete source"}
 */
$router->post('/admin/scraper/sources/delete', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)($_POST['id'] ?? 0);
        $model = new ScraperModel($mysqli);
        $result = $model->deleteSource($id);

        if ($result) {
            logActivity('scraper_source_deleted', 'source', $id, ['user_id' => $_SESSION['user_id'] ?? 0]);
            return jsonResponse([
                'success' => true,
                'message' => 'Source deleted successfully'
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to delete source'], 500);
    } catch (Exception $e) {
        error_log("Delete source error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Toggle Source Status
 *
 * Activates or deactivates a scraper source.
 *
 * @route POST /admin/scraper/sources/toggle
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - id (int, required): Source ID to toggle
 *               - is_active (bool, required): New active status
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails
 *
 * @example Success: {"success": true, "message": "Source activated"}
 * @example Success: {"success": true, "message": "Source deactivated"}
 * @example Error: {"success": false, "error": "Failed to toggle source"}
 */
$router->post('/admin/scraper/sources/toggle', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)($_POST['id'] ?? 0);
        $isActive = isset($_POST['is_active']);
        $model = new ScraperModel($mysqli);
        $result = $model->toggleSourceStatus($id, $isActive);

        if ($result) {
            logActivity('scraper_source_toggled', 'source', $id, ['user_id' => $_SESSION['user_id'] ?? 0, 'active' => $isActive]);
            return jsonResponse([
                'success' => true,
                'message' => $isActive ? 'Source activated' : 'Source deactivated'
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to toggle source'], 500);
    } catch (Exception $e) {
        error_log("Toggle source error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Toggle All Sources Status
 *
 * Activates or deactivates all scraper sources.
 *
 * @route POST /admin/scraper/sources/toggle-all
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - is_active (bool, required): New active status for all sources
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails
 *
 * @example Success: {"success": true, "message": "All sources activated"}
 * @example Error: {"success": false, "error": "Failed to toggle sources"}
 */
$router->post('/admin/scraper/sources/toggle-all', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $isActive = isset($_POST['is_active']);
        $model = new ScraperModel($mysqli);
        $result = $model->toggleAllSources($isActive);

        if ($result) {
            logActivity('scraper_sources_toggled_all', 'sources', 0, ['user_id' => $_SESSION['user_id'] ?? 0, 'active' => $isActive]);
            return jsonResponse([
                'success' => true,
                'message' => $isActive ? 'All sources activated' : 'All sources deactivated'
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to toggle sources'], 500);
    } catch (Exception $e) {
        error_log("Toggle all sources error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Test Source Page
 *
 * Displays a page for testing a scraper source configuration.
 *
 * @route GET /admin/scraper/sources/{id}/test
 * @middleware auth, admin_only
 *
 * @param id int Source ID to test (from URL path)
 *
 * @return void Renders test page template with:
 *               - source: Source object with configuration
 *               - pageTitle: "Test Scraper Source"
 *
 * @throws Exception If database query fails, source not found, or template rendering fails
 *
 * @example Response: HTML page with test interface for the source
 */
$router->get('/admin/scraper/sources/{id}/test', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $source = $model->getSourceById($id);

        if (!$source) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'pageTitle' => 'Source Not Found',
                'message' => 'The requested scraper source was not found.'
            ]);
            return;
        }

        echo $twig->render('scraper/sources/test.twig', [
            'source' => $source,
            'pageTitle' => 'Test Scraper Source'
        ]);
    } catch (Exception $e) {
        error_log("Test source page error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load test page.'
        ]);
    }
});

/**
 * Test Source
 *
 * Tests a scraper source configuration without saving changes.
 *
 * @route POST /admin/scraper/sources/{id}/test
 * @middleware auth, admin_only
 *
 * @param id int Source ID to test (from URL path)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - result: Test results including:
 *                   - items: Array of scraped items
 *                   - errors: Array of errors encountered
 *                   - warnings: Array of warnings
 *                   - performance: Performance metrics
 *               - error: string (if failed)
 *
 * @throws Exception If test execution fails
 *
 * @example Success: {"success": true, "result": {"items": [...], "errors": []}}
 * @example Error: {"success": false, "error": "Source not found"}
 */
$router->post('/admin/scraper/sources/{id}/test', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = [];
    }

    $csrfToken = $input['csrf_token'] ?? '';

    // Validate CSRF token
    if (!validateCsrfToken($csrfToken)) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $service = new ScraperService($model);
    $maxRetries = 3;
    $error = '';
    $success = false;

$maxRetries = 3;
$error = '';
$success = false;

        $maxItems = (int)($input['maxItems'] ?? 5);
        $timeout = (int)($input['timeout'] ?? 30);
        $includeErrors = (bool)($input['includeErrors'] ?? true);

        $result = $service->testSource($id);

        // Format result for the test page
        $formattedResult = [
            'summary' => [
                'items' => $result['items_found'] ?? 0,
                'errors' => count($result['errors'] ?? []),
                'warnings' => 0, // Not implemented yet
                'duration' => 0 // Not implemented yet
            ],
            'items' => [], // Would need to be populated from the actual scrape result
            'errors' => array_map(function ($error) {
                return ['message' => $error, 'details' => null];
            }, $result['errors'] ?? []),
            'warnings' => [],
            'performance' => [
                'library_used' => $result['library_used'] ?? 'unknown',
                'test_url' => $result['test_url'] ?? null
            ]
        ];

        // Note: testSource doesn't return scraped data for security/performance reasons
        // Items would need to be populated from a separate safe method if needed

        return jsonResponse([
            'success' => $result['success'],
            'result' => $formattedResult
        ]);
    } catch (Exception $e) {
        error_log("Test source error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== STATISTICS ==================

/**
 * Get Statistics (API)
 *
 * Returns scraping statistics with optional filtering
 *
 * @route GET /api/admin/scraper/stats
 * @middleware auth, admin_only
 *
 * @param int source_id (optional) - Filter by source ID
 * @param int days (optional) - Number of days to look back, default 7
 *
 * @return void JSON response with statistics data
 */
$router->get('/api/admin/scraper/stats', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $source_id = (int)($_GET['source_id'] ?? 0);
        $days = (int)($_GET['days'] ?? 7);

        $sql = "SELECT stat_type, stat_date, metrics
                FROM web_scraping_stats
                WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";

        $params = [$days];
        $types = "i";

        if ($source_id > 0) {
            $sql .= " AND source_id = ?";
            $params[] = $source_id;
            $types .= "i";
        }

        $sql .= " ORDER BY stat_date DESC";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $stats = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['metrics'] = json_decode($row['metrics'], true);
            $stats[] = $row;
        }

        return jsonResponse(['stats' => $stats]);
    } catch (Exception $e) {
        error_log("Get stats error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== SETTINGS MANAGEMENT ==================

/**
 * Get Settings (API)
 *
 * Returns all scraper settings as JSON
 *
 * @route GET /api/admin/scraper/settings
 * @middleware auth, admin_only
 *
 * @return void JSON response with settings
 */
$router->get('/api/admin/scraper/settings', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $result = $mysqli->query("SELECT setting_key, setting_value FROM web_scraping_settings ORDER BY setting_key ASC");
        $settings = [];

        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'settings' => $settings
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Exception $e) {
        error_log("Get settings API error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to load settings.'
        ]);
    }
});

/**
 * Update Setting (API)
 *
 * Updates or creates a scraper setting
 *
 * @route POST /api/admin/scraper/settings
 * @middleware auth, admin_only
 *
 * @param string key - Setting key
 * @param string value - Setting value
 *
 * @return void JSON response with success status
 */
$router->post('/api/admin/scraper/settings', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';

        if (empty($key)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Setting key required']);
            return;
        }

        $model = new ScraperModel($mysqli);
        $success = $model->setSetting($key, $value);

        echo json_encode(['success' => $success]);
    } catch (Exception $e) {
        error_log("Update setting API error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

/**
 * Delete Setting (API)
 *
 * Deletes a scraper setting
 *
 * @route DELETE /api/admin/scraper/settings/{key}
 * @middleware auth, admin_only
 *
 * @param string key - Setting key to delete
 *
 * @return void JSON response with success status
 */
$router->delete('/api/admin/scraper/settings/([^/]+)', ['middleware' => ['auth', 'admin_only']], function ($key = null) use ($mysqli) {
    try {
        $key = (string)($key ?? '');

        if (empty($key)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Setting key required']);
            return;
        }

        $model = new ScraperModel($mysqli);
        $success = $model->deleteSetting($key);

        echo json_encode(['success' => $success]);
    } catch (Exception $e) {
        error_log("Delete setting API error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ================== LOGS MANAGEMENT ==================

/**
 * Get Logs (API)
 *
 * Returns scraping logs with optional filtering
 *
 * @route GET /api/admin/scraper/logs
 * @middleware auth, admin_only
 *
 * @param int source_id (optional) - Filter by source ID
 * @param string level (optional) - Filter by log level
 * @param int limit (optional) - Maximum number of logs to return, default 100
 *
 * @return void JSON response with logs data
 */
$router->get('/api/admin/scraper/logs', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $source_id = (int)($_GET['source_id'] ?? 0);
        $level = $_GET['level'] ?? null;
        $limit = (int)($_GET['limit'] ?? 100);

        $sql = "SELECT l.*, s.name as source_name
                FROM web_scraping_logs l
                LEFT JOIN web_scraping_sources s ON l.source_id = s.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if ($source_id > 0) {
            $sql .= " AND l.source_id = ?";
            $params[] = $source_id;
            $types .= "i";
        }

        if ($level) {
            $sql .= " AND l.level = ?";
            $params[] = $level;
            $types .= "s";
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= "i";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return jsonResponse(['logs' => $logs]);
    } catch (Exception $e) {
        error_log("Get logs error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Source metadata list
 *
 * Returns the available scraper categories and presets that power the admin form dropdowns.
 *
 * @route GET /api/admin/scraper/source-lists
 * @middleware auth, admin_only
 *
 * @return JSON Response with category/preset collections
 */
$router->get('/api/admin/scraper/source-lists', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $categories = $model->getCategories();
        $presets = PresetRegistry::toArray();
        $presetCategories = PresetRegistry::getCategories();

        return jsonResponse([
            'success' => true,
            'categories' => $categories,
            'presets' => $presets,
            'preset_categories' => $presetCategories
        ]);
    } catch (Exception $e) {
        error_log("Source lists fetch error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => 'Failed to load source metadata'], 500);
    }
});

/**
 * Guess preset for source
 *
 * @route GET /api/admin/scraper/presets/guess
 * @middleware auth, admin_only
 */
$router->get('/api/admin/scraper/presets/guess', ['middleware' => ['auth', 'admin_only']], function () {
    try {
        $url = trim($_GET['url'] ?? '');
        $contentType = trim($_GET['content_type'] ?? '');

        $preset = null;
        if ($url !== '') {
            $preset = PresetRegistry::findByUrl($url);
        }
        if (!$preset && $contentType !== '') {
            $preset = PresetRegistry::findByContentType($contentType);
        }

        $response = ['success' => true, 'preset' => null];
        if ($preset) {
            $response['preset'] = [
                'key' => $preset->getKey(),
                'name' => $preset->getName(),
                'reason' => $url !== '' ? 'url' : 'content_type'
            ];
        }

        return jsonResponse($response);
    } catch (Exception $e) {
        error_log("Preset guess error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => 'Failed to guess preset'], 500);
    }
});

/**
 * Update Source via Direct Route
 *
 * Alternative endpoint for updating a scraper source configuration.
 * Redirects on success (for traditional form submission).
 *
 * @route POST /admin/scraper/sources/{id}
 * @middleware auth, admin_only
 *
 * @param id int Source ID to update (from URL path)
 *
 * @request_body Form data containing:
 *               - name (string, required): Source name
 *               - url (string, required): Source URL
 *               - type (string): Scraper type
 *               - category_id (int): Category ID
 *               - selector_* (string): Various CSS/XPath selectors
 *               - advance_config (string): JSON configuration
 *               - fetch_interval (int): Fetch interval in minutes
 *               - is_active (bool): Active status
 *               - content_type (string): Content type
 *               - scrape_depth (int): Scrape depth level
 *               - use_browser (bool): Use browser for scraping
 *               - max_pages (int): Maximum pages to scrape
 *               - delay (int): Delay between requests
 *               - pagination_type (string): Pagination type
 *               - pagination_selector (string): Pagination selector
 *               - pagination_pattern (string): Pagination pattern
 *               - proxy_enabled (bool): Enable proxy
 *               - proxy_provider (string): Proxy provider
 *               - proxy_config (string): Proxy configuration
 *               - website_preset_key (string): Website preset key
 *
 * @return void Redirects to sources list on success, or JSON error on failure
 *
 * @throws Exception If database operation fails or source not found
 *
 * @example Success: Redirects to /admin/scraper/sources with flash message
 * @example Error: {"success": false, "error": "Source not found"}
 */
$router->post('/admin/scraper/sources/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $existing = $model->getSourceById($id);

        if (!$existing) {
            http_response_code(404);
            return jsonResponse(['success' => false, 'error' => 'Source not found'], 404);
        }

        $advanceConfigRaw = trim($_POST['advance_config'] ?? '');
        $advanceConfig = [];
        if ($advanceConfigRaw !== '') {
            $decoded = json_decode($advanceConfigRaw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $advanceConfig = $decoded;
            }
        }

        $data = [
            'name' => $_POST['name'] ?? '',
            'url' => $_POST['url'] ?? '',
            'type' => $_POST['type'] ?? 'static',
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'selector_list_container' => $_POST['selector_list_container'] ?? '',
            'selector_list_item' => $_POST['selector_list_item'] ?? '',
            'selector_list_title' => $_POST['selector_list_title'] ?? '',
            'selector_list_link' => $_POST['selector_list_link'] ?? '',
            'selector_list_date' => $_POST['selector_list_date'] ?? '',
            'selector_list_image' => $_POST['selector_list_image'] ?? '',
            'selector_title' => $_POST['selector_title'] ?? '',
            'selector_content' => $_POST['selector_content'] ?? '',
            'selector_image' => $_POST['selector_image'] ?? '',
            'selector_excerpt' => $_POST['selector_excerpt'] ?? '',
            'selector_date' => $_POST['selector_date'] ?? '',
            'selector_author' => $_POST['selector_author'] ?? '',
            'selector_pagination' => $_POST['selector_pagination'] ?? '',
            'selector_read_more' => $_POST['selector_read_more'] ?? '',
            'selector_category' => $_POST['selector_category'] ?? '',
            'selector_tags' => $_POST['selector_tags'] ?? '',
            'selector_video' => $_POST['selector_video'] ?? '',
            'selector_audio' => $_POST['selector_audio'] ?? '',
            'selector_source_url' => $_POST['selector_source_url'] ?? '',
            'advance_config' => json_encode($advanceConfig),
            'fetch_interval' => (int)($_POST['fetch_interval'] ?? 60),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'content_type' => $_POST['content_type'] ?? 'article',
            'scrape_depth' => (int)($_POST['scrape_depth'] ?? 1),
            'use_browser' => isset($_POST['use_browser']) ? 1 : 0,
            'max_pages' => (int)($_POST['max_pages'] ?? 5),
            'delay' => (int)($_POST['delay'] ?? 2),
            'pagination_type' => $_POST['pagination_type'] ?? 'query',
            'pagination_selector' => $_POST['pagination_selector'] ?? '',
            'pagination_pattern' => $_POST['pagination_pattern'] ?? '',
            'proxy_enabled' => isset($_POST['proxy_enabled']) ? 1 : 0,
            'proxy_provider' => $_POST['proxy_provider'] ?? '',
            'proxy_config' => $_POST['proxy_config'] ?? '',
            'website_preset_key' => $_POST['website_preset_key'] ?? ''
        ];

        $result = $model->updateSource($id, $data);

        if ($result) {
            logActivity('scraper_source_updated', 'source', $id, ['user_id' => $_SESSION['user_id'] ?? 0]);
            showMessage('Source updated successfully', 'success');

            header('Location: /admin/scraper/sources', true, 302);
            exit;
        }

        showMessage('Failed to update source', 'error');
        header('Location: /admin/scraper/sources/' . $id . '/edit', true, 302);
        exit;
    } catch (Exception $e) {
        error_log("Update source error: " . $e->getMessage());
        showMessage($e->getMessage(), 'error');
        header('Location: /admin/scraper/sources/' . $id . '/edit', true, 302);
        exit;
    }
});


/**
 * Advance Scraper Test
 *
 * Tests the advance PHP scraper with custom configuration.
 *
 * @route POST /admin/scraper/advance/test
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - url (string, required): URL to scrape
 *               - config (array, optional): Advance scraper configuration
 *               - max_items (int, optional): Maximum items to scrape (default: 10)
 *               - extract (array, optional): Fields to force extract
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - data: Scraped data including:
 *                   - title: Page title
 *                   - links: Array of links found
 *                   - images: Array of images found
 *                   - meta: Page metadata
 *               - items: Array of scraped items
 *               - error: string (if failed)
 *
 * @throws Exception If scraping fails or URL is invalid
 *
 * @example Request: {"url": "https://example.com", "max_items": 5}
 * @example Success: {"success": true, "data": {...}, "items": [...]}
 * @example Error: {"success": false, "error": "Valid URL is required"}
 */
$router->post('/admin/scraper/advance/test', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        $url = trim($input['url'] ?? '');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return jsonResponse(['success' => false, 'error' => 'Valid URL is required'], 400);
        }

        $config = $input['config'] ?? [];
        if (!is_array($config)) {
            $config = [];
        }

        $scraper = new \App\Modules\Scraper\Scrapers\AdvanceScraper();
        $scraper->setSource([
            'id' => 0,
            'name' => 'Advance PHP Scraper Test',
            'url' => $url,
            'advance_config' => json_encode($config),
        ]);
        $scraper->setTestMode(true);
        $scraper->setMaxItems((int)($input['max_items'] ?? 10));

        $result = $scraper->scrape(['force_extract' => $input['extract'] ?? []]);

        if (!empty($result['success'])) {
            return jsonResponse([
                'success' => true,
                'data' => [
                    'title' => $result['title'] ?? '',
                    'links' => $result['links'] ?? [],
                    'images' => $result['images'] ?? [],
                    'meta' => $result['meta'] ?? [],
                ],
                'items' => $result['items'] ?? [],
            ]);
        }

        return jsonResponse(['success' => false, 'error' => $result['error'] ?? 'Advance scraper test failed'], 500);
    } catch (Exception $e) {
        error_log("Advance scraper test error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});
/**
 * Run Source
 *
 * Executes a scraper source immediately, adding jobs to the queue.
 *
 * @route POST /admin/scraper/sources/{id}/run
 * @middleware auth, admin_only
 *
 * @param id int Source ID to run (from URL path)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - result: Execution results including:
 *                   - job_id: Created job ID
 *                   - items_count: Number of items scraped
 *                   - status: Execution status
 *               - error: string (if failed)
 *
 * @throws Exception If execution fails
 *
 * @example Success: {"success": true, "result": {"job_id": 123, "items_count": 45}}
 * @example Error: {"success": false, "error": "Source not found"}
 */
$router->post('/admin/scraper/sources/{id}/run', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $service = new ScraperService($model);
    $maxRetries = 3;
    $error = '';
    $success = false;

$maxRetries = 3;
$error = '';
$success = false;

        $result = $service->scrapeSource($id);

        // Check if scraping was actually successful
        $scraperSuccess = !empty($result['success']);
        $overallSuccess = $scraperSuccess; // For now, overall success matches scraper success

        // Get collected data summary
        $collectedData = [];
        if ($scraperSuccess && !empty($result['data'])) {
            $collectedData = array_slice($result['data'], 0, 10); // Show first 10 items
        }

        // Get recent logs for this source
        $recentLogs = $model->getScrapeHistory($id, 5);

        return jsonResponse([
            'success' => $overallSuccess,
            'result' => $result,
            'collected_data' => $collectedData,
            'recent_logs' => $recentLogs,
            'query_info' => [
                'total_items' => count($collectedData),
                'source_id' => $id,
                'execution_time' => $result['stats']['duration'] ?? 0,
                'pages_scraped' => $result['stats']['pages_scraped'] ?? 0,
                'items_found' => $result['stats']['items_found'] ?? 0,
                'items_saved' => $result['stats']['items_saved'] ?? 0
            ]
        ]);
    } catch (Exception $e) {
        error_log("Run source error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== QUEUE MANAGEMENT ==================

/**
 * Queue Index
 *
 * Displays the scraper job queue with pending jobs and summary statistics.
 *
 * @route GET /admin/scraper/queue
 * @middleware auth, admin_only
 *
 * @return void Renders queue template with:
 *               - jobs: Array of pending jobs (max 50)
 *               - summary: Queue statistics including:
 *                   - pending: Number of pending jobs
 *                   - running: Number of running jobs
 *                   - completed: Number of completed jobs
 *                   - failed: Number of failed jobs
 *               - pageTitle: "Scraper Queue"
 *
 * @throws Exception If database query fails or template rendering fails
 *
 * @example Response: HTML page with job queue table and summary cards
 */
$router->get('/admin/scraper/queue', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $jobs = $model->getPendingJobs(50);

        echo $twig->render('scraper/queue/index.twig', [
            'jobs' => $jobs,
            'pageTitle' => 'Scraper Queue'
        ]);
    } catch (Exception $e) {
        error_log("Queue index error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load queue.'
        ]);
    }
});

/**
 * Queue Status API
 *
 * Retrieves the latest queue breakdown, retryable count, and worker heartbeat.
 *
 * @route GET /api/v1/scraper/queue/status
 * @middleware auth, admin_only
 */
$router->get('/api/v1/scraper/queue/status', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $queueService = new \App\Modules\Scraper\Queue\QueueService($mysqli);
        $summary = $queueService->getQueueSummary();
        $stats = $summary['stats'] ?? [];
        $completed = (int)($stats['completed'] ?? 0);
        $failed = (int)($stats['failed'] ?? 0);
        $finished = $completed + $failed;
        $summary['health'] = [
            'success_rate' => $finished === 0 ? 0 : round(($completed / $finished) * 100, 2),
            'finished' => $finished,
            'successes' => $completed,
            'failures' => $failed
        ];

        return jsonResponse([
            'success' => true,
            'data' => $summary
        ]);
    } catch (Exception $e) {
        error_log("Queue status API error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Run Pipeline (Queue Worker Trigger)
 *
 * @route POST /api/v1/scraper/queue/run
 * @middleware auth, admin_only
 */
$router->post('/api/v1/scraper/queue/run', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $args = ['--once', '--verbose'];
        $sleep = isset($_POST['sleep']) ? max(1, (int)$_POST['sleep']) : null;
        $maxJobs = isset($_POST['max_jobs']) ? max(1, (int)$_POST['max_jobs']) : null;

        if ($sleep !== null) {
            $args[] = "--sleep={$sleep}";
        }

        if ($maxJobs !== null) {
            $args[] = "--max-jobs={$maxJobs}";
        }

        $queueService = new \App\Modules\Scraper\Queue\QueueService($mysqli);
        $spawned = spawnQueueWorker($args);
        if ($spawned) {
            logActivity('scraper_queue_run_requested', null, null, ['user_id' => $_SESSION['user_id'] ?? 0]);
        }

        return jsonResponse([
            'success' => $spawned,
            'message' => $spawned ? 'Queue worker triggered' : 'Unable to start worker process',
            'data' => $queueService->getQueueSummary()
        ], $spawned ? 200 : 500);
    } catch (Exception $e) {
        error_log("Queue run API error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Queue Clear API
 *
 * @route POST /api/v1/scraper/queue/clear
 * @middleware auth, admin_only
 */
$router->post('/api/v1/scraper/queue/clear', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    $queueService = new \App\Modules\Scraper\Queue\QueueService($mysqli);
    $cleared = $queueService->clearPendingJobs();
    $message = "Cleared {$cleared} pending job" . ($cleared === 1 ? '' : 's') . ' from the queue';
    if ($cleared > 0) {
        logActivity('scraper_queue_cleared', null, null, [
            'user_id' => $_SESSION['user_id'] ?? 0,
            'cleared_count' => $cleared
        ]);
    }

    return jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $queueService->getQueueSummary()
    ]);
});

/**
 * Get Collection Status
 *
 * Returns current collection statistics and recent activity.
 *
 * @route GET /api/v1/scraper/collect/status
 * @middleware auth, admin_only
 *
 * @return array Collection status data
 */
$router->get('/api/v1/scraper/collect/status', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $stats = $model->getOverallStats();
        $recentActivity = $model->getRecentCollections(10);

        return jsonResponse([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_sources' => $stats['total_sources'] ?? 0,
                    'active_sources' => $stats['active_sources'] ?? 0,
                    'total_collections_today' => $stats['total_collections_today'] ?? 0,
                    'total_items_today' => $stats['total_items_today'] ?? 0
                ],
                'recent_activity' => $recentActivity
            ]
        ]);
    } catch (Exception $e) {
        error_log("Collection status API error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => 'Failed to get collection status'], 500);
    }
});

/**
 * Start Data Collection
 *
 * Initiates a new data collection run based on the provided parameters.
 *
 * @route POST /api/v1/scraper/collect/start
 * @middleware auth, admin_only
 *
 * @param type string Collection type ('all', 'sources', 'category')
 * @param target_ids array Array of source or category IDs
 * @param options array Additional collection options
 *
 * @return array Collection result data
 */
$router->post('/api/v1/scraper/collect/start', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $type = $input['type'] ?? 'all';
        $targetIds = $input['target_ids'] ?? [];
        $options = $input['options'] ?? [];

        $model = new ScraperModel($mysqli);
        $queueService = new \App\Modules\Scraper\Queue\QueueService($mysqli);

        // Get sources based on type
        $sourcesToRun = [];
        if ($type === 'all') {
            $sourcesToRun = $model->getActiveSources();
        } elseif ($type === 'sources') {
            $sourcesToRun = array_filter($model->getAllSources(), function($source) use ($targetIds) {
                return in_array($source['id'], $targetIds);
            });
        } elseif ($type === 'category') {
            $sourcesToRun = array_filter($model->getAllSources(), function($source) use ($targetIds) {
                return in_array($source['category_id'], $targetIds);
            });
        }

        if (empty($sourcesToRun)) {
            return jsonResponse(['success' => false, 'error' => 'No sources found for collection'], 400);
        }

        // Create jobs for each source
        $jobIds = [];
        foreach ($sourcesToRun as $source) {
            $jobId = $model->createJob([
                'source_id' => $source['id'],
                'job_type' => 'manual_collection',
                'priority' => 1
            ]);
            $jobIds[] = $jobId;
        }

        // Trigger queue processing
        spawnQueueWorker();

        logActivity('scraper_manual_collection_started', null, null, [
            'user_id' => $_SESSION['user_id'] ?? 0,
            'job_ids' => $jobIds,
            'type' => $type,
            'sources_count' => count($sourcesToRun)
        ]);

        return jsonResponse([
            'success' => true,
            'message' => 'Collection started successfully',
            'data' => [
                'job_ids' => $jobIds,
                'sources' => array_map(function($source) {
                    return ['id' => $source['id'], 'name' => $source['name']];
                }, $sourcesToRun),
                'total_items' => 0,
                'results' => []
            ]
        ]);
    } catch (Exception $e) {
        error_log("Collection start API error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => 'Failed to start collection'], 500);
    }
});

/**
 * Spawn the queue worker in the background via the cron entrypoint.
 */
function spawnQueueWorker(array $args = []): bool
{
    $scriptPath = realpath(__DIR__ . '/../..') . '/scripts/cron/scraper-worker.php';
    if (!$scriptPath || !file_exists($scriptPath)) {
        return false;
    }

    $commandParts = array_merge([PHP_BINARY, $scriptPath], $args);
    $commandLine = implode(' ', array_map('escapeshellarg', $commandParts));

    if (stripos(PHP_OS, 'WIN') === 0) {
        pclose(popen("cmd /c start \"\" /B $commandLine", 'r'));
    } else {
        exec("$commandLine > /dev/null 2>&1 &");
    }

    return true;
}

/**
 * Cancel Job
 *
 * Cancels a pending or running scraper job.
 *
 * @route POST /admin/scraper/queue/cancel
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - id (int, required): Job ID to cancel
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails
 *
 * @example Success: {"success": true, "message": "Job cancelled successfully"}
 * @example Error: {"success": false, "error": "Failed to cancel job"}
 */
$router->post('/admin/scraper/queue/cancel', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)($_POST['id'] ?? 0);
        $queueService = new \App\Modules\Scraper\Queue\QueueService($mysqli);
        $result = $queueService->cancel($id);

        if ($result) {
            logActivity('scraper_job_cancelled', 'job', $id, ['user_id' => $_SESSION['user_id'] ?? 0]);
            return jsonResponse([
                'success' => true,
                'message' => 'Job cancelled successfully'
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to cancel job'], 500);
    } catch (Exception $e) {
        error_log("Cancel job error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Retry Job
 *
 * Re-queues a failed or cancelled scraper job for retry.
 *
 * @route POST /admin/scraper/queue/retry
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - id (int, required): Job ID to retry
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails
 *
 * @example Success: {"success": true, "message": "Job queued for retry"}
 * @example Error: {"success": false, "error": "Failed to retry job"}
 */
$router->post('/admin/scraper/queue/retry', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)($_POST['id'] ?? 0);
        $queueService = new \App\Modules\Scraper\Queue\QueueService($mysqli);
        $result = $queueService->retry($id);

        if ($result) {
            logActivity('scraper_job_retried', 'job', $id, ['user_id' => $_SESSION['user_id'] ?? 0]);
            return jsonResponse([
                'success' => true,
                'message' => 'Job queued for retry'
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to retry job'], 500);
    } catch (Exception $e) {
        error_log("Retry job error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Clear Queue
 *
 * Clears all pending jobs from the scraper queue.
 *
 * @route POST /admin/scraper/queue/clear
 * @middleware auth, admin_only
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message with count)
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails
 *
 * @example Success: {"success": true, "message": "Cleared 5 pending jobs from queue"}
 * @example Error: {"success": false, "error": "Failed to clear queue"}
 */
$router->post('/admin/scraper/queue/clear', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    $queueService = new \App\Modules\Scraper\Queue\QueueService($mysqli);
    $cleared = $queueService->clearPendingJobs();
    $message = "Cleared {$cleared} pending job" . ($cleared === 1 ? '' : 's') . ' from the queue';

    return jsonResponse([
        'success' => true,
        'message' => $message
    ]);
});

/**
 * Process Queue - Get next job to process
 *
 * Dequeues the next pending job from the queue for processing.
 *
 * @route POST /admin/scraper/queue/process
 * @middleware auth, admin_only
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (status message)
 *               - job: Job object (if job found) containing:
 *                   - id: Job ID
 *                   - source_id: Source ID
 *                   - type: Job type
 *                   - status: Job status
 *               - error: string (if failed)
 *
 * @throws Exception If queue operation fails
 *
 * @example Success: {"success": true, "message": "Job dequeued and ready to process", "job": {...}}
 * @example Empty: {"success": true, "message": "No pending jobs in queue"}
 * @example Error: {"success": false, "error": "Queue processing failed"}
 */
$router->post('/admin/scraper/queue/process', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $queueService = new \App\Modules\Scraper\Queue\QueueService($mysqli);

        // Get next pending job
        $job = $queueService->dequeueNextJob();

        if (!$job) {
            return jsonResponse([
                'success' => true,
                'message' => 'No pending jobs in queue'
            ]);
        }

        logActivity('scraper_job_processed', 'job', $job['id'], ['user_id' => $_SESSION['user_id'] ?? 0]);

        return jsonResponse([
            'success' => true,
            'message' => 'Job dequeued and ready to process',
            'job' => $job
        ]);
    } catch (Exception $e) {
        error_log("Process queue error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== LOGS ==================

/**
 * Logs Index
 *
 * Displays scraper logs with filtering and pagination.
 *
 * @route GET /admin/scraper/logs
 * @middleware auth, admin_only
 *
 * @query_param source_id int Filter by source ID
 * @query_param level string Filter by log level (info, warning, error)
 * @query_param limit int Number of logs per page (default: 100)
 * @query_param page int Page number (default: 1)
 *
 * @return void Renders logs template with:
 *               - logs: Array of log entries
 *               - pagination: Pagination metadata (total, page, limit, pages)
 *               - filters: Current filter values
 *               - pageTitle: "Scraper Logs"
 *
 * @throws Exception If database query fails or template rendering fails
 *
 * @example Response: HTML page with logs table and filters
 */
$router->get('/admin/scraper/logs', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $sourceId = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;
        $level = isset($_GET['level']) ? $_GET['level'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

        $logs = $model->getLogs([
            'source_id' => $sourceId,
            'level' => $level
        ], 1, $limit);

        echo $twig->render('scraper/logs/index.twig', [
            'logs' => $logs['logs'],
            'pagination' => [
                'total' => $logs['total'],
                'page' => $logs['page'],
                'limit' => $logs['limit'],
                'pages' => $logs['pages']
            ],
            'filters' => [
                'source_id' => $sourceId,
                'level' => $level,
                'limit' => $limit
            ],
            'pageTitle' => 'Scraper Logs'
        ]);
    } catch (Exception $e) {
        error_log("Logs index error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load logs.'
        ]);
    }
});

/**
 * View Specific Log
 *
 * Displays detailed information for a specific scraper log entry.
 *
 * @route GET /admin/scraper/logs/{id}
 * @middleware auth, admin_only
 *
 * @param int id Log entry ID
 *
 * @return void Renders log detail template with:
 *               - log: Log entry object with full details
 *               - pageTitle: "Log Details"
 *
 * @throws Exception If log not found or database query fails
 *
 * @example Response: HTML page with detailed log information
 */
$router->get('/admin/scraper/logs/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);

        $log = $model->getLogById($id);

        if (!$log) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'pageTitle' => 'Log Not Found',
                'message' => 'The requested log entry was not found.'
            ]);
            return;
        }

        echo $twig->render('scraper/logs/detail.twig', [
            'log' => $log,
            'pageTitle' => 'Log Details'
        ]);
    } catch (Exception $e) {
        error_log("Log detail error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load log details.'
        ]);
    }
});

/**
 * Clear Logs
 *
 * Deletes old scraper log entries from database.
 *
 * @route POST /admin/scraper/logs/clear
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - days (int, optional): Number of days to keep (default: 30)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message with count)
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails
 *
 * @example Success: {"success": true, "message": "Cleared 1234 log entries"}
 * @example Error: {"success": false, "error": "Failed to clear logs"}
 */
$router->post('/admin/scraper/logs/clear', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
        $model = new ScraperModel($mysqli);
        $result = $model->deleteOldLogs($days);

        if ($result !== false) {
            logActivity('scraper_logs_cleared', null, null, ['user_id' => $_SESSION['user_id'] ?? 0, 'days' => $days]);
            return jsonResponse([
                'success' => true,
                'message' => "Cleared {$result} log entries"
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to clear logs'], 500);
    } catch (Exception $e) {
        error_log("Clear logs error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== STATISTICS ==================

/**
 * Statistics Index
 *
 * Displays overall scraper statistics and active sources.
 *
 * @route GET /admin/scraper/stats
 * @middleware auth, admin_only
 *
 * @return void Renders statistics template with:
 *               - stats: Overall statistics including:
 *                   - total_sources: Total number of sources
 *                   - active_sources: Number of active sources
 *                   - total_jobs: Total jobs processed
 *                   - successful_jobs: Number of successful jobs
 *                   - failed_jobs: Number of failed jobs
 *                   - success_rate: Success percentage
 *               - sources: Array of active sources
 *               - pageTitle: "Scraper Statistics"
 *
 * @throws Exception If database query fails or template rendering fails
 *
 * @example Response: HTML page with statistics cards and charts
 */
$router->get('/admin/scraper/stats', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $stats = $model->getOverallStats();
        $sources = $model->getActiveSources();

        echo $twig->render('scraper/stats/index.twig', [
            'stats' => $stats,
            'sources' => $sources,
            'pageTitle' => 'Scraper Statistics'
        ]);
    } catch (Exception $e) {
        error_log("Stats index error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load statistics.'
        ]);
    }
});

// ================== CATEGORIES ==================

/**
 * Categories Index
 *
 * Displays list of scraper categories with management options.
 *
 * @route GET /admin/scraper/categories
 * @middleware auth, admin_only
 *
 * @return void Renders categories template with:
 *               - categories: Array of categories
 *               - pageTitle: "Scraper Categories"
 *
 * @throws Exception If database query fails or template rendering fails
 *
 * @example Response: HTML page with categories table and management options
 */
$router->get('/admin/scraper/categories', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $categories = $model->getCategories();

        echo $twig->render('scraper/categories/index.twig', [
            'categories' => $categories,
            'pageTitle' => 'Scraper Categories'
        ]);
    } catch (Exception $e) {
        error_log("Categories index error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load categories.'
        ]);
    }
});

/**
 * Create Category Form
 *
 * Displays form to create a new scraper category.
 *
 * @route GET /admin/scraper/categories/create
 * @middleware auth, admin_only
 *
 * @return void Renders category form template
 */
$router->get('/admin/scraper/categories/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $categories = $model->getCategories();

        echo $twig->render('scraper/categories/form.twig', [
            'categories' => $categories,
            'pageTitle' => 'Create Category'
        ]);
    } catch (Exception $e) {
        error_log("Create category form error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load category form.'
        ]);
    }
});

/**
 * Edit Category Form
 *
 * Displays form to edit an existing scraper category.
 *
 * @route GET /admin/scraper/categories/{id}/edit
 * @middleware auth, admin_only
 *
 * @param int id Category ID
 *
 * @return void Renders category form template with existing data
 */
$router->get('/admin/scraper/categories/{id}/edit', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $category = $model->getCategoryById($id);
        $categories = $model->getCategories();

        if (!$category) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'pageTitle' => 'Category Not Found',
                'message' => 'The requested category was not found.'
            ]);
            return;
        }

        echo $twig->render('scraper/categories/form.twig', [
            'category' => $category,
            'categories' => $categories,
            'pageTitle' => 'Edit Category'
        ]);
    } catch (Exception $e) {
        error_log("Edit category form error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load category form.'
        ]);
    }
});

/**
 * Save Category
 *
 * Creates or updates a scraper category.
 *
 * @route POST /admin/scraper/categories/save
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - id (int, optional): Category ID for updates
 *               - name (string, required): Category name
 *               - description (string, optional): Category description
 *               - parent_id (int, optional): Parent category ID
 *               - is_active (int, optional): Active status (1/0)
 *
 * @return void Redirects to categories list with success/error message
 */
$router->post('/admin/scraper/categories/save', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: /admin/scraper/categories');
        exit;
    }

    try {
        $model = new ScraperModel($mysqli);
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        if (empty($data['name'])) {
            $_SESSION['error'] = 'Category name is required';
            header('Location: /admin/scraper/categories');
            exit;
        }

        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;

        if ($id) {
            $result = $model->updateCategory($id, $data);
            $action = 'updated';
        } else {
            $result = $model->createCategory($data);
            $action = 'created';
        }

        if ($result) {
            logActivity('scraper_category_' . $action, 'category', $id, ['user_id' => $_SESSION['user_id'] ?? 0]);
            $_SESSION['success'] = "Category {$action} successfully";
        } else {
            $_SESSION['error'] = "Failed to {$action} category";
        }

        header('Location: /admin/scraper/categories');
        exit;
    } catch (Exception $e) {
        error_log("Save category error: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to save category: ' . $e->getMessage();
        header('Location: /admin/scraper/categories');
        exit;
    }
});

/**
 * Delete Category
 *
 * Deletes a scraper category.
 *
 * @route POST /admin/scraper/categories/delete
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - id (int, required): Category ID to delete
 *
 * @return void Redirects to categories list with success/error message
 */
$router->post('/admin/scraper/categories/delete', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: /admin/scraper/categories');
        exit;
    }

    try {
        $id = (int)($_POST['id'] ?? 0);
        $model = new ScraperModel($mysqli);

        $result = $model->deleteCategory($id);

        if ($result) {
            logActivity('scraper_category_deleted', 'category', $id, ['user_id' => $_SESSION['user_id'] ?? 0]);
            $_SESSION['success'] = 'Category deleted successfully';
        } else {
            $_SESSION['error'] = 'Failed to delete category';
        }

        header('Location: /admin/scraper/categories');
        exit;
    } catch (Exception $e) {
        error_log("Delete category error: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to delete category: ' . $e->getMessage();
        header('Location: /admin/scraper/categories');
        exit;
    }
});

// ================== JOBS ==================

/**
 * Jobs Index
 *
 * Displays list of scraper jobs with filtering and pagination.
 *
 * @route GET /admin/scraper/jobs
 * @middleware auth, admin_only
 *
 * @query_param status string Filter by job status
 * @query_param source_id int Filter by source ID
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 50)
 *
 * @return void Renders jobs template with:
 *               - jobs: Array of job records
 *               - pagination: Pagination metadata
 *               - filters: Current filter values
 *               - pageTitle: "Scraper Jobs"
 *
 * @throws Exception If database query fails or template rendering fails
 *
 * @example Response: HTML page with jobs table and filters
 */
$router->get('/admin/scraper/jobs', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $sourceId = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = (int)($_GET['limit'] ?? 50);

        $jobs = $model->getJobs($page, $limit, $status, $sourceId);
        $sources = $model->getAllSources();

        echo $twig->render('scraper/jobs/index.twig', [
            'jobs' => $jobs['jobs'],
            'pagination' => [
                'total' => $jobs['total'],
                'page' => $jobs['page'],
                'limit' => $jobs['limit'],
                'pages' => $jobs['pages']
            ],
            'filters' => [
                'status' => $status,
                'source_id' => $sourceId,
                'limit' => $limit
            ],
            'sources' => $sources,
            'pageTitle' => 'Scraper Jobs'
        ]);
    } catch (Exception $e) {
        error_log("Jobs index error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load jobs.'
        ]);
    }
});

/**
 * View Job Details
 *
 * Displays detailed information for a specific scraper job.
 *
 * @route GET /admin/scraper/jobs/{id}
 * @middleware auth, admin_only
 *
 * @param int id Job ID
 *
 * @return void Renders job detail template with:
 *               - job: Job object with full details
 *               - pageTitle: "Job Details"
 *
 * @throws Exception If job not found or database query fails
 *
 * @example Response: HTML page with detailed job information
 */
$router->get('/admin/scraper/jobs/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);

        $job = $model->getJobById($id);

        if (!$job) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'pageTitle' => 'Job Not Found',
                'message' => 'The requested job was not found.'
            ]);
            return;
        }

        echo $twig->render('scraper/jobs/detail.twig', [
            'job' => $job,
            'pageTitle' => 'Job Details'
        ]);
    } catch (Exception $e) {
        $service = new ScraperService($model);
error_log("Job detail error: " . $e->getMessage());

$service = new ScraperService($model);
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load job details.'
        ]);
    }
});

// ================== MOBILES ==================

/**
 * Mobiles Index
 *
 * Displays list of scraped mobile data with filtering and pagination.
 *
 * @route GET /admin/scraper/mobiles
 * @middleware auth, admin_only
 *
 * @query_param source_id int Filter by source ID
 * @query_param search string Search in name/brand/model
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 20)
 *
 * @return void Renders mobiles template with:
 *               - mobiles: Array of mobile records
 *               - pagination: Pagination metadata
 *               - filters: Current filter values
 *               - pageTitle: "Scraper Mobiles"
 *
 * @throws Exception If database query fails or template rendering fails
 *
 * @example Response: HTML page with mobiles table and filters
 */
$router->get('/admin/scraper/mobiles', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $sourceId = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;
        $search = isset($_GET['search']) ? trim($_GET['search']) : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = (int)($_GET['limit'] ?? 20);

        $mobiles = $model->getMobiles($page, $limit, $sourceId, $search);
        $sources = $model->getAllSources();

        echo $twig->render('scraper/mobiles/index.twig', [
            'mobiles' => $mobiles['mobiles'],
            'pagination' => [
                'total' => $mobiles['total'],
                'page' => $mobiles['page'],
                'limit' => $mobiles['limit'],
                'pages' => $mobiles['pages']
            ],
            'filters' => [
                'source_id' => $sourceId,
                'search' => $search,
                'limit' => $limit
            ],
            'sources' => $sources,
            'pageTitle' => 'Scraper Mobiles',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Mobiles index error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load mobiles.'
        ]);
    }
});

/**
 * View Mobile Details
 *
 * Displays detailed information for a specific mobile entry.
 *
 * @route GET /admin/scraper/mobiles/{id}
 * @middleware auth, admin_only
 *
 * @param int id Mobile ID
 *
 * @return void Renders mobile detail template with:
 *               - mobile: Mobile object with full details
 *               - pageTitle: "Mobile Details"
 *
 * @throws Exception If mobile not found or database query fails
 *
 * @example Response: HTML page with detailed mobile information
 */
$router->get('/admin/scraper/mobiles/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);

        $mobile = $model->getMobileById($id);

        if (!$mobile) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'pageTitle' => 'Mobile Not Found',
                'message' => 'The requested mobile was not found.'
            ]);
            return;
        }

        echo $twig->render('scraper/mobiles/detail.twig', [
            'mobile' => $mobile,
            'pageTitle' => 'Mobile Details',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Mobile detail error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load mobile details.'
        ]);
    }
});

/**
 * Delete Mobile
 *
 * Deletes a mobile entry from the database.
 *
 * @route POST /admin/scraper/mobiles/delete
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - id (int, required): Mobile ID to delete
 *
 * @return void Redirects to mobiles list with success/error message
 */
$router->post('/admin/scraper/mobiles/delete', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: /admin/scraper/mobiles');
        exit;
    }

    try {
        $id = (int)($_POST['id'] ?? 0);
        $model = new ScraperModel($mysqli);

        $result = $model->deleteMobile($id);

        if ($result) {
            logActivity('scraper_mobile_deleted', 'mobile', $id, ['user_id' => $_SESSION['user_id'] ?? 0]);
            $_SESSION['success'] = 'Mobile deleted successfully';
        } else {
            $_SESSION['error'] = 'Failed to delete mobile';
        }

        header('Location: /admin/scraper/mobiles');
        exit;
    } catch (Exception $e) {
        error_log("Delete mobile error: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to delete mobile: ' . $e->getMessage();
        header('Location: /admin/scraper/mobiles');
        exit;
    }
});

// ================== SEEN URLS ==================

/**
 * Seen URLs Index
 *
 * Displays list of URLs that have been seen/processed by the scraper.
 *
 * @route GET /admin/scraper/seen-urls
 * @middleware auth, admin_only
 *
 * @query_param source_id int Filter by source ID
 * @query_param search string Search in URL
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 50)
 *
 * @return void Renders seen URLs template with:
 *               - urls: Array of seen URL records
 *               - pagination: Pagination metadata
 *               - filters: Current filter values
 *               - pageTitle: "Seen URLs"
 *
 * @throws Exception If database query fails or template rendering fails
 *
 * @example Response: HTML page with seen URLs table and filters
 */
$router->get('/admin/scraper/seen-urls', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $sourceId = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;
        $search = isset($_GET['search']) ? trim($_GET['search']) : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = (int)($_GET['limit'] ?? 50);

        $urls = $model->getSeenUrls($page, $limit, $sourceId, $search);
        $sources = $model->getAllSources();

        echo $twig->render('scraper/seen-urls/index.twig', [
            'urls' => $urls['urls'],
            'pagination' => [
                'total' => $urls['total'],
                'page' => $urls['page'],
                'limit' => $urls['limit'],
                'pages' => $urls['pages']
            ],
            'filters' => [
                'source_id' => $sourceId,
                'search' => $search,
                'limit' => $limit
            ],
            'sources' => $sources,
            'pageTitle' => 'Seen URLs'
        ]);
    } catch (Exception $e) {
        error_log("Seen URLs index error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load seen URLs.'
        ]);
    }
});

/**
 * Delete Seen URL
 *
 * Deletes a seen URL entry from the database.
 *
 * @route POST /admin/scraper/seen-urls/delete
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - id (int, required): Seen URL ID to delete
 *
 * @return void Redirects to seen URLs list with success/error message
 */
$router->post('/admin/scraper/seen-urls/delete', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: /admin/scraper/seen-urls');
        exit;
    }

    try {
        $id = (int)($_POST['id'] ?? 0);
        $model = new ScraperModel($mysqli);

        $result = $model->deleteSeenUrl($id);

        if ($result) {
            logActivity('scraper_seen_url_deleted', 'seen_url', $id, ['user_id' => $_SESSION['user_id'] ?? 0]);
            $_SESSION['success'] = 'Seen URL deleted successfully';
        } else {
            $_SESSION['error'] = 'Failed to delete seen URL';
        }

        header('Location: /admin/scraper/seen-urls');
        exit;
    } catch (Exception $e) {
        error_log("Delete seen URL error: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to delete seen URL: ' . $e->getMessage();
        header('Location: /admin/scraper/seen-urls');
        exit;
    }
});

// ================== SETTINGS ==================

/**
 * Settings Index
 *
 * Displays scraper settings with management options.
 *
 * @route GET /admin/scraper/settings
 * @middleware auth, admin_only
 *
 * @return void Renders settings template with:
 *               - settings: Array of setting records
 *               - pageTitle: "Scraper Settings"
 *
 * @throws Exception If database query fails or template rendering fails
 *
 * @example Response: HTML page with settings table and management options
 */
$router->get('/admin/scraper/settings', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);

        // Pagination
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $perPage = 20;
        $totalSettings = $model->getSettingsCount();
        $totalPages = ceil($totalSettings / $perPage);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;

        $settings = $model->getSettingsPaginated($offset, $perPage);

        echo $twig->render('scraper/settings/index.twig', [
            'settings' => $settings,
            'pageTitle' => 'Scraper Settings',
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalSettings' => $totalSettings,
            'perPage' => $perPage,
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Settings index error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load settings.'
        ]);
    }
});

/**
 * Create Setting
 *
 * Creates a new scraper setting.
 *
 * @route POST /admin/scraper/settings/create
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - setting_key (string, required): Setting key
 *               - setting_value (string, required): Setting value
 *               - description (string, optional): Setting description
 *
 * @return void Redirects to settings list with success/error message
 */
$router->post('/admin/scraper/settings/create', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: /admin/scraper/settings');
        exit;
    }

    try {
        $key = trim($_POST['setting_key'] ?? '');
        $value = trim($_POST['setting_value'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($key)) {
            $_SESSION['error'] = 'Setting key is required';
            header('Location: /admin/scraper/settings');
            exit;
        }

        if (strlen($key) > 100) {
            $_SESSION['error'] = 'Setting key must be 100 characters or less';
            header('Location: /admin/scraper/settings');
            exit;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
            $_SESSION['error'] = 'Setting key must contain only letters, numbers, and underscores';
            header('Location: /admin/scraper/settings');
            exit;
        }

        if (empty($value)) {
            $_SESSION['error'] = 'Setting value is required';
            header('Location: /admin/scraper/settings');
            exit;
        }

        if (strlen($value) > 1000) {
            $_SESSION['error'] = 'Setting value must be 1000 characters or less';
            header('Location: /admin/scraper/settings');
            exit;
        }

        $model = new ScraperModel($mysqli);
        $result = $model->createSetting($key, $value);

        if ($result) {
            logActivity('scraper_setting_created', null, null, ['user_id' => $_SESSION['user_id'] ?? 0, 'key' => $key]);
            $_SESSION['success'] = 'Setting created successfully';
        } else {
            $_SESSION['error'] = 'Failed to create setting';
        }

        header('Location: /admin/scraper/settings');
        exit;
    } catch (Exception $e) {
        error_log("Create setting error: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to create setting: ' . $e->getMessage();
        header('Location: /admin/scraper/settings');
        exit;
    }
});

/**
 * Update Setting
 *
 * Updates a scraper setting value.
 *
 * @route POST /admin/scraper/settings/update
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - id (int, required): Setting ID
 *               - setting_key (string, required): Setting key
 *               - setting_value (string, required): Setting value
 *               - description (string, optional): Setting description
 *
 * @return void Redirects to settings list with success/error message
 */
$router->post('/admin/scraper/settings/update', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: /admin/scraper/settings');
        exit;
    }

    try {
        $id = trim($_POST['id'] ?? ''); // This is actually the setting_key
        $key = trim($_POST['setting_key'] ?? '');
        $value = trim($_POST['setting_value'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($id)) {
            $_SESSION['error'] = 'Setting key is required';
            header('Location: /admin/scraper/settings');
            exit;
        }

        if (empty($key)) {
            $_SESSION['error'] = 'Setting key is required';
            header('Location: /admin/scraper/settings');
            exit;
        }

        if (strlen($key) > 100) {
            $_SESSION['error'] = 'Setting key must be 100 characters or less';
            header('Location: /admin/scraper/settings');
            exit;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
            $_SESSION['error'] = 'Setting key must contain only letters, numbers, and underscores';
            header('Location: /admin/scraper/settings');
            exit;
        }

        if (empty($value)) {
            $_SESSION['error'] = 'Setting value is required';
            header('Location: /admin/scraper/settings');
            exit;
        }

        if (strlen($value) > 1000) {
            $_SESSION['error'] = 'Setting value must be 1000 characters or less';
            header('Location: /admin/scraper/settings');
            exit;
        }

        $model = new ScraperModel($mysqli);
        $result = $model->updateSetting($key, $value);

        if ($result) {
            logActivity('scraper_setting_updated', null, null, ['user_id' => $_SESSION['user_id'] ?? 0, 'key' => $key]);
            $_SESSION['success'] = 'Setting updated successfully';
        } else {
            $_SESSION['error'] = 'Failed to update setting';
        }

        header('Location: /admin/scraper/settings');
        exit;
    } catch (Exception $e) {
        error_log("Update setting error: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update setting: ' . $e->getMessage();
        header('Location: /admin/scraper/settings');
        exit;
    }
});

/**
 * Delete Setting
 *
 * Deletes a scraper setting.
 *
 * @route POST /admin/scraper/settings/delete
 * @middleware auth, admin_only
 *
 * @request_body Form data containing:
 *               - id (int, required): Setting ID to delete
 *
 * @return void Redirects to settings list with success/error message
 */
$router->post('/admin/scraper/settings/delete', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: /admin/scraper/settings');
        exit;
    }

    try {
        $id = trim($_POST['id'] ?? ''); // This is actually the setting_key
        $model = new ScraperModel($mysqli);

        if (empty($id)) {
            $_SESSION['error'] = 'Setting key is required';
            header('Location: /admin/scraper/settings');
            exit;
        }

        $result = $model->deleteSetting($id);

        if ($result) {
            logActivity('scraper_setting_deleted', null, null, ['user_id' => $_SESSION['user_id'] ?? 0, 'key' => $id]);
            $_SESSION['success'] = 'Setting deleted successfully';
        } else {
            $_SESSION['error'] = 'Failed to delete setting';
        }

        header('Location: /admin/scraper/settings');
        exit;
    } catch (Exception $e) {
        error_log("Delete setting error: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to delete setting: ' . $e->getMessage();
        header('Location: /admin/scraper/settings');
        exit;
    }
});

/**
 * Diagnostics Dashboard
 *
 * Displays scraper diagnostics and health check information.
 *
 * @route GET /admin/scraper/diagnostics
 * @middleware auth, admin_only
 *
 * @return void Renders diagnostics template with system health data
 */
$router->get('/admin/scraper/diagnostics', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $jobs = $model->getJobs(1, 1); // Get first job to check if any exist
        $diagnostics = [
            'database_status' => 'ok', // Placeholder - could implement actual checks
            'queue_status' => 'ok',
            'sources_count' => count($model->getAllSources()),
            'jobs_count' => $jobs['total'] ?? 0,
            'last_job_time' => 'N/A', // Could implement later
            'system_info' => [
                'php_version' => PHP_VERSION,
                'server_time' => date('Y-m-d H:i:s')
            ]
        ];

        echo $twig->render('scraper/diagnostics/index.twig', [
            'diagnostics' => $diagnostics,
            'pageTitle' => 'Scraper Diagnostics'
        ]);
    } catch (Exception $e) {
        error_log("Diagnostics page error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load diagnostics page.'
        ]);
    }
});

// ================== PRESETS ==================

/**
 * Presets Index
 *
 * Displays list of available scraper presets organized by category.
 *
 * @route GET /admin/scraper/presets
 * @middleware auth, admin_only
 *
 * @return void Renders presets template with:
 *               - presets: Array of available presets
 *               - categories: Array of preset categories
 *               - pageTitle: "Scraper Presets"
 *
 * @throws Exception If template rendering fails
 *
 * @example Response: HTML page with preset cards organized by category
 */
$router->get('/admin/scraper/presets', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    try {
        $presets = PresetRegistry::toArray();
        $categories = PresetRegistry::getCategories();

        echo $twig->render('scraper/presets/index.twig', [
            'presets' => $presets,
            'categories' => $categories,
            'pageTitle' => 'Scraper Presets'
        ]);
    } catch (Exception $e) {
        error_log("Presets index error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load presets.'
        ]);
    }
});

/**
 * Apply Preset - Create scraper source from preset
 *
 * Creates a new scraper source from a preset configuration.
 *
 * @route POST /admin/scraper/presets/{key}/apply
 * @middleware auth, admin_only
 *
 * @param key string Preset key to apply (from URL path)
 *
 * @request_body JSON object containing:
 *               - name (string, optional): Custom source name (defaults to preset name)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - source_id: int (created source ID)
 *               - source_name: string (created source name)
 *               - error: string (if failed)
 *
 * @throws Exception If preset not found or database operation fails
 *
 * @example Request: {"name": "My News Scraper"}
 * @example Success: {"success": true, "message": "Preset applied successfully", "source_id": 123}
 * @example Error: {"success": false, "error": "Preset not found"}
 */
$router->post('/admin/scraper/presets/{key}/apply', ['middleware' => ['auth', 'admin_only']], function ($key) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $key = (string)$key;
        $preset = PresetRegistry::getByKey($key);

        if (!$preset) {
            return jsonResponse(['success' => false, 'error' => 'Preset not found'], 404);
        }

        // Get request data
        $input = json_decode((string)file_get_contents('php://input'), true) ?? [];
        $sourceName = trim($input['name'] ?? $preset->getName());

        if (empty($sourceName)) {
            return jsonResponse(['success' => false, 'error' => 'Source name is required'], 400);
        }

        // Create source from preset
        $model = new ScraperModel($mysqli);
        $config = $preset->getConfig();
        $exampleUrls = $preset->getExampleUrls();

        $sourceData = [
            'name' => $sourceName,
            'url' => $exampleUrls[0] ?? '',
            'category' => $preset->getCategory(),
            'type' => $preset->getType(),
            'fetch_interval' => $preset->getFetchInterval(),
            'enabled' => 1,
            'preset_key' => $key,
            // Copy selectors from preset config
            'selector_list_page_container' => $config['list_container'] ?? '',
            'selector_list_page_item' => $config['list_item'] ?? '',
            'selector_list_page_title' => $config['list_title'] ?? '',
            'selector_list_page_link' => $config['list_link'] ?? '',
            'selector_list_page_date' => $config['list_date'] ?? '',
            'selector_list_page_image' => $config['list_image'] ?? '',
            'selector_detail_page_title' => $config['title'] ?? '',
            'selector_detail_page_content' => $config['content'] ?? '',
            'selector_detail_page_image' => $config['image'] ?? '',
            'selector_detail_page_date' => $config['date'] ?? '',
            'selector_detail_page_author' => $config['author'] ?? '',
            'selector_pagination' => $config['pagination'] ?? ''
        ];

        $sourceId = $model->createSource($sourceData);

        if (!$sourceId) {
            return jsonResponse(['success' => false, 'error' => 'Failed to create source'], 500);
        }

        logActivity('scraper_preset_applied', 'source', $sourceId, [
            'user_id' => $_SESSION['user_id'] ?? 0,
            'preset_key' => $key
        ]);

        return jsonResponse([
            'success' => true,
            'message' => 'Preset applied successfully',
            'source_id' => $sourceId,
            'source_name' => $sourceName
        ]);
    } catch (Exception $e) {
        error_log("Apply preset error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Get Preset Config
 *
 * Displays detailed view of a specific scraper preset.
 *
 * @route GET /admin/scraper/presets/{key}
 * @middleware auth, admin_only
 *
 * @param key string Preset key to view (from URL path)
 *
 * @return void Renders preset details template with:
 *               - preset: Preset object containing:
 *                   - key: Preset key
 *                   - name: Preset name
 *                   - description: Preset description
 *                   - url: Example URL
 *                   - category: Preset category
 *                   - config: Preset configuration
 *               - pageTitle: "Preset Details"
 *
 * @throws Exception If preset not found or template rendering fails
 *
 * @example Response: HTML page with preset details and configuration
 */
$router->get('/admin/scraper/presets/{key}', ['middleware' => ['auth', 'admin_only']], function ($key) use ($twig) {
    try {
        $key = (string)$key;
        $preset = PresetRegistry::getByKey($key);

        if (!$preset) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'pageTitle' => 'Preset Not Found',
                'message' => 'The requested preset does not exist.'
            ]);
            return;
        }

        echo $twig->render('scraper/presets/show.twig', [
            'pageTitle' => 'Preset Details',
            'preset' => [
                'key' => $key,
                'name' => $preset->getName(),
                'description' => $preset->getDescription(),
                'url' => !empty($preset->getExampleUrls()) ? $preset->getExampleUrls()[0] : 'N/A',
                'category' => $preset->getCategory(),
                'config' => $preset->getConfig()
            ]
        ]);
    } catch (Exception $e) {
        error_log("Get preset error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load preset details.'
        ]);
    }
});

/**
 * Create Preset Form
 *
 * Displays form for creating a new scraper preset.
 *
 * @route GET /admin/scraper/presets/create
 * @middleware auth, admin_only
 *
 * @return void Renders create preset template
 */
$router->get('/admin/scraper/presets/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    try {
        echo $twig->render('scraper/presets/create.twig', [
            'pageTitle' => 'Create Scraper Preset',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Create preset form error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load create preset form.'
        ]);
    }
});

// ================== API ENDPOINTS ==================

/**
 * API: Get All Sources
 *
 * Retrieves all scraper sources from database.
 *
 * @route GET /api/v1/scraper/sources
 * @middleware auth
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - data: Array of all scraper sources
 *               - error: string (if failed)
 *
 * @throws Exception If database query fails
 *
 * @example Success: {"success": true, "data": [...]}
 * @example Error: {"success": false, "error": "Database error"}
 */
$router->get('/api/v1/scraper/sources', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $sources = $model->getAllSources();

        return jsonResponse([
            'success' => true,
            'data' => $sources
        ]);
    } catch (Exception $e) {
        error_log("API get sources error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Get Source by ID
 *
 * Retrieves a specific scraper source by ID.
 *
 * @route GET /api/v1/scraper/sources/{id}
 * @middleware auth
 *
 * @param id int Source ID to retrieve (from URL path)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - data: Source object (if found)
 *               - error: string (if failed)
 *
 * @throws Exception If database query fails
 *
 * @example Success: {"success": true, "data": {...}}
 * @example Error: {"success": false, "error": "Source not found"}
 */
$router->get('/api/v1/scraper/sources/{id}', ['middleware' => ['auth']], function ($id) use ($mysqli) {
    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $source = $model->getSourceById($id);

        if (!$source) {
            return jsonResponse(['success' => false, 'error' => 'Source not found'], 404);
        }

        return jsonResponse([
            'success' => true,
            'data' => $source
        ]);
    } catch (Exception $e) {
        error_log("API get source error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Create Source
 *
 * Creates a new scraper source.
 *
 * @route POST /api/v1/scraper/sources
 * @middleware auth
 *
 * @request_body JSON object containing source configuration fields
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - data: Object with created source ID
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails or validation fails
 *
 * @example Success: {"success": true, "message": "Source created successfully", "data": {"id": 123}}
 * @example Error: {"success": false, "error": "Invalid JSON"}
 */
$router->post('/api/v1/scraper/sources', ['middleware' => ['auth']], function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            return jsonResponse(['success' => false, 'error' => 'Invalid JSON'], 400);
        }

        $model = new ScraperModel($mysqli);
        $id = $model->createSource($input);

        if ($id) {
            return jsonResponse([
                'success' => true,
                'message' => 'Source created successfully',
                'data' => ['id' => $id]
            ], 201);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to create source'], 500);
    } catch (Exception $e) {
        error_log("API create source error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Update Source
 *
 * Updates an existing scraper source.
 *
 * @route PUT /api/v1/scraper/sources/{id}
 * @middleware auth
 *
 * @param id int Source ID to update (from URL path)
 *
 * @request_body JSON object containing source configuration fields
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails or validation fails
 *
 * @example Success: {"success": true, "message": "Source updated successfully"}
 * @example Error: {"success": false, "error": "Failed to update source"}
 */
$router->put('/api/v1/scraper/sources/{id}', ['middleware' => ['auth']], function ($id) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$id;
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            return jsonResponse(['success' => false, 'error' => 'Invalid JSON'], 400);
        }

        $model = new ScraperModel($mysqli);
        $result = $model->updateSource($id, $input);

        if ($result) {
            return jsonResponse([
                'success' => true,
                'message' => 'Source updated successfully'
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to update source'], 500);
    } catch (Exception $e) {
        error_log("API update source error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Delete Source
 *
 * Deletes a scraper source from database.
 *
 * @route DELETE /api/v1/scraper/sources/{id}
 * @middleware auth
 *
 * @param id int Source ID to delete (from URL path)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - message: string (success message)
 *               - error: string (if failed)
 *
 * @throws Exception If database operation fails
 *
 * @example Success: {"success": true, "message": "Source deleted successfully"}
 * @example Error: {"success": false, "error": "Failed to delete source"}
 */
$router->delete('/api/v1/scraper/sources/{id}', ['middleware' => ['auth']], function ($id) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $result = $model->deleteSource($id);

        if ($result) {
            return jsonResponse([
                'success' => true,
                'message' => 'Source deleted successfully'
            ]);
        }

        return jsonResponse(['success' => false, 'error' => 'Failed to delete source'], 500);
    } catch (Exception $e) {
        error_log("API delete source error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== SELECTOR TESTING ==================

/**
 * Test CSS Selector
 *
 * Tests a CSS selector against a URL to verify it works correctly.
 *
 * @route POST /admin/scraper/selectors/test-css
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - selector (string, required): CSS selector to test
 *               - url (string, required): URL to test against
 *               - max_samples (int, optional): Maximum samples to return (default: 5)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - selector: Tested selector
 *               - matches: Array of matched elements
 *               - count: Number of matches
 *               - samples: Array of sample matches
 *               - error: string (if failed)
 *
 * @throws Exception If URL fetch fails or selector testing fails
 *
 * @example Success: {"success": true, "selector": ".title", "count": 10, "samples": [...]}
 * @example Error: {"success": false, "error": "No selector provided"}
 */
$router->post('/admin/scraper/selectors/test-css', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $selector = trim($input['selector'] ?? '');
        $url = trim($input['url'] ?? '');
        $maxSamples = (int)($input['max_samples'] ?? 5);

        if (empty($selector)) {
            return jsonResponse(['success' => false, 'error' => 'No selector provided'], 400);
        }

        if (empty($url)) {
            return jsonResponse(['success' => false, 'error' => 'No URL provided'], 400);
        }

        $service = new ScraperService($model);
// Fetch the URL content
// Create and initialize dependencies
$model = new ScraperModel($mysqli);
$service = new ScraperService($model);

// Start the collection process
$service = new ScraperService($model);
$maxRetries = 3;
$error = '';
$success = false;

$maxRetries = 3;
$error = '';
$success = false;

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        $result = $service->scrapeSource((int)$source['id']);
        $success = !empty($result['success']);
        break; // Exit loop on success
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Attempt $attempt failed for source ID {$source['id']}: $error");
        // Optionally add a delay before retrying
        if ($attempt < $maxRetries) {
            sleep(1); // Sleep for 1 second before retrying
        }
    }
}
        $startTime = microtime(true);
        $collectionData = [
            'job_id' => $jobId,
            'sources' => array_map(function ($s) {
                return ['id' => $s['id'], 'name' => $s['name']];
            }, $sourcesToRun),
            'total_sources' => count($sourcesToRun),
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
            '_start_microtime' => $startTime // Store precise start time
        ];

        // For now, run synchronously (can be made async later)
        $service = new ScraperService($model);
    $maxRetries = 3;
    $error = '';
    $success = false;

$maxRetries = 3;
$error = '';
$success = false;
        $results = [];
        $totalItems = 0;

        foreach ($sourcesToRun as $source) {
            try {
                $maxRetries = 3;
$error = '';
$success = false;

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        $result = $service->scrapeSource((int)$source['id']);
        $success = !empty($result['success']);
        break; // Exit loop on success
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Attempt $attempt failed for source ID {$source['id']}: $error");
        // Optionally add a delay before retrying
        if ($attempt < $maxRetries) {
            sleep(1); // Sleep for 1 second before retrying
        }
    }
}
                $success = !empty($result['success']);
                $itemsCollected = $result['stats']['items_saved'] ?? 0;

                $results[] = [
                    'source_id' => $source['id'],
                    'source_name' => $source['name'],
                    'success' => $success,
                    'items_collected' => $itemsCollected,
                    'error' => $success ? null : ($result['error'] ?? 'Unknown error')
                ];

                if ($success) {
                    $totalItems += $itemsCollected;
                }
            } catch (Exception $e) {
                $results[] = [
                    'source_id' => $source['id'],
                    'source_name' => $source['name'],
                    'success' => false,
                    'items_collected' => 0,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Calculate execution time
        $completedAt = date('Y-m-d H:i:s');
        $executionTime = round(microtime(true) - ($collectionData['_start_microtime'] ?? microtime(true)), 2);

        // Update job status
        $model->updateCollectionJob($jobId, [
            'status' => 'completed',
            'completed_at' => $completedAt,
            'execution_time' => $executionTime,
            'results' => json_encode($results),
            'total_items' => $totalItems
        ]);

        $collectionData['completed_at'] = date('Y-m-d H:i:s');
        $collectionData['results'] = $results;
        $collectionData['total_items'] = $totalItems;
        $collectionData['status'] = 'completed';
        $collectionData['execution_time'] = $executionTime;

        // Remove internal field
        unset($collectionData['_start_microtime']);

        // Log activity
        logActivity('scraper_manual_collection', null, null, [
            'user_id' => $_SESSION['user_id'] ?? 0,
            'job_id' => $jobId,
            'sources_count' => count($sourcesToRun),
            'total_items' => $totalItems
        ]);

        return jsonResponse([
            'success' => true,
            'message' => "Collection completed successfully. Collected {$totalItems} items from " . count($sourcesToRun) . " sources.",
            'data' => $collectionData
        ]);
    } catch (Exception $e) {
        error_log("Collection start error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});
