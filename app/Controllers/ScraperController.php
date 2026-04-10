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
use App\Modules\Scraper\Presets\PresetRegistry;
use App\Modules\Scraper\ScraperService;
use App\Modules\Scraper\ScraperFactory;
use App\Modules\Scraper\Pipelines\GSMArenaPipeline;

global $mysqli, $router, $twig;

// Alias for json_response to jsonResponse for compatibility
if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $statusCode = 200): void
    {
        json_response($data, $statusCode);
    }
}

if (!function_exists('parseJsonRequest')) {
    function parseJsonRequest(): array
    {
        $input = json_decode(file_get_contents('php://input'), true);
        return is_array($input) ? $input : [];
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

        echo $twig->render('scraper/dashboard.twig', [
            'stats' => $stats,
            'recentJobs' => $recentJobs,
            'activeSources' => $activeSources,
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
$router->delete('/admin/scraper/collected-data/{id}', ['middleware' => ['auth', 'admin_only']], function ($params) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$params['id'];
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
$router->get('/admin/scraper/collected-data/{id}', ['middleware' => ['auth', 'admin_only']], function ($params) use ($twig, $mysqli) {
    try {
        $id = (int)$params['id'];
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

        $article['categories'] = [];
        if (!empty($article['categories_json'])) {
            $decoded = json_decode($article['categories_json'], true);
            if (is_array($decoded)) {
                $article['categories'] = $decoded;
            }
        }
        $article['tags'] = [];
        if (!empty($article['tags_json'])) {
            $decoded = json_decode($article['tags_json'], true);
            if (is_array($decoded)) {
                $article['tags'] = $decoded;
            }
        }
        $article['metadata_struct'] = [];
        if (!empty($article['metadata'])) {
            $decodedMetadata = json_decode($article['metadata'], true);
            if (is_array($decodedMetadata)) {
                $article['metadata_struct'] = $decodedMetadata;
            }
        }

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
    if (!validateCsrfToken()) {
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
 * Get All Presets
 *
 * Returns a list of all available scraper presets for selection.
 *
 * @route GET /admin/scraper/presets/list
 * @middleware auth, admin_only
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - presets: Array of preset objects with:
 *                   - key: string (unique identifier)
 *                   - name: string (display name)
 *                   - description: string (description)
 *                   - category: string (category)
 *                   - icon: string (icon class)
 *                   - type: string (scraper type)
 *                   - content_type: string (content type)
 *                   - example_urls: array (example URLs)
 *
 * @example {"success": true, "presets": [...]}
 */
$router->get('/admin/scraper/presets/list', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $presets = \App\Modules\Scraper\Presets\PresetRegistry::toArray();

        return jsonResponse([
            'success' => true,
            'presets' => $presets
        ]);
    } catch (Exception $e) {
        error_log("Get presets list error: " . $e->getMessage());
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
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        $url = trim($input['url'] ?? '');
        $html = trim($input['html'] ?? '');

        if ($url === '' && $html === '') {
            return jsonResponse(['success' => false, 'error' => 'Either URL or HTML content is required'], 400);
        }

        $analyzer = AIScraperAnalyzer::fromMysqli($mysqli);
        $result = $analyzer->analyzeHtml($html, $url);

        return jsonResponse($result->toArray());
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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

        return jsonResponse($result->toArray());
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
            'pageTitle' => 'Scraper Sources'
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
$router->get('/admin/scraper/sources/{id}/edit', ['middleware' => ['auth', 'admin_only']], function ($params) use ($twig, $mysqli) {
    try {
        $id = (int)$params['id'];
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
$router->get('/admin/scraper/sources/{id}', ['middleware' => ['auth', 'admin_only']], function ($params) use ($twig, $mysqli) {
    try {
        $id = (int)$params['id'];
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
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $model = new ScraperModel($mysqli);
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
$router->get('/admin/scraper/sources/{id}/test', ['middleware' => ['auth', 'admin_only']], function ($params) use ($twig, $mysqli) {
    try {
        $id = (int)$params['id'];
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
$router->post('/admin/scraper/sources/{id}/test', ['middleware' => ['auth', 'admin_only']], function ($params) use ($mysqli) {
    // Parse JSON input first to get CSRF token
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $csrfToken = $input['csrf_token'] ?? '';

    // Validate CSRF token
    if (!validateCsrfToken($csrfToken)) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$params['id'];
        $model = new ScraperModel($mysqli);
        $service = new ScraperService($model);

        // Parse JSON input for test options
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
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

        // If successful, try to get some sample items
        if ($result['success'] && isset($result['data'])) {
            // This would need to be implemented based on the actual data structure
            $formattedResult['items'] = []; // Placeholder
        }

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
$router->delete('/api/admin/scraper/settings/([^/]+)', ['middleware' => ['auth', 'admin_only']], function ($params) use ($mysqli) {
    try {
        $key = $params[1] ?? '';

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
$router->post('/admin/scraper/sources/{id}', ['middleware' => ['auth', 'admin_only']], function ($params) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken()) {
        http_response_code(403);
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$params['id'];
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
    if (!validateCsrfToken()) {
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
$router->post('/admin/scraper/sources/{id}/run', ['middleware' => ['auth', 'admin_only']], function ($params) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$params['id'];
        $model = new ScraperModel($mysqli);
        $service = new ScraperService($model);

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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
$router->get('/admin/scraper/logs/{id}', ['middleware' => ['auth', 'admin_only']], function ($params) use ($twig, $mysqli) {
    try {
        $id = (int)$params['id'];
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
    if (!validateCsrfToken()) {
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
$router->get('/admin/scraper/categories/{id}/edit', ['middleware' => ['auth', 'admin_only']], function ($params) use ($twig, $mysqli) {
    try {
        $id = (int)$params['id'];
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
$router->get('/admin/scraper/jobs/{id}', ['middleware' => ['auth', 'admin_only']], function ($params) use ($twig, $mysqli) {
    try {
        $id = (int)$params['id'];
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
        error_log("Job detail error: " . $e->getMessage());
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
$router->get('/admin/scraper/mobiles/{id}', ['middleware' => ['auth', 'admin_only']], function ($params) use ($twig, $mysqli) {
    try {
        $id = (int)$params['id'];
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
    if (!validateCsrfToken()) {
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
$router->post('/admin/scraper/presets/{key}/apply', ['middleware' => ['auth', 'admin_only']], function ($params) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $key = $params['key'];
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
$router->get('/admin/scraper/presets/{key}', ['middleware' => ['auth', 'admin_only']], function ($params) use ($twig) {
    try {
        $key = $params['key'];
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
$router->get('/api/v1/scraper/sources/{id}', ['middleware' => ['auth']], function ($params) use ($mysqli) {
    try {
        $id = (int)$params['id'];
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
    if (!validateCsrfToken()) {
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
$router->put('/api/v1/scraper/sources/{id}', ['middleware' => ['auth']], function ($params) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$params['id'];
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
$router->delete('/api/v1/scraper/sources/{id}', ['middleware' => ['auth']], function ($params) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$params['id'];
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
    if (!validateCsrfToken()) {
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

        // Fetch the URL content
        $httpClient = new \App\Modules\Scraper\HttpClientService();
        $response = $httpClient->get($url);

        if (!$httpClient->isSuccess($response)) {
            return jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch URL (HTTP ' . $httpClient->getStatusCode($response) . ')'
            ], 400);
        }

        $html = $httpClient->getBody($response);

        // Test the selector
        $testingService = new \App\Modules\Scraper\Services\SelectorTestingService($html);
        $result = $testingService->testCssSelector($selector, $maxSamples);

        return jsonResponse($result);
    } catch (Exception $e) {
        error_log("Test CSS selector error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Test XPath Selector
 *
 * Tests an XPath selector against a URL to verify it works correctly.
 *
 * @route POST /admin/scraper/selectors/test-xpath
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - selector (string, required): XPath selector to test
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
 * @example Success: {"success": true, "selector": "//div[@class='title']", "count": 10, "samples": [...]}
 * @example Error: {"success": false, "error": "No selector provided"}
 */
$router->post('/admin/scraper/selectors/test-xpath', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken()) {
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

        // Fetch the URL
        $httpClient = new \App\Modules\Scraper\HttpClientService();
        $response = $httpClient->get($url);

        if (!$httpClient->isSuccess($response)) {
            return jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch URL (HTTP ' . $httpClient->getStatusCode($response) . ')'
            ], 400);
        }

        $html = $httpClient->getBody($response);

        // Test the XPath selector
        $testingService = new \App\Modules\Scraper\Services\SelectorTestingService($html);
        $result = $testingService->testXPathSelector($selector, $maxSamples);

        return jsonResponse($result);
    } catch (Exception $e) {
        error_log("Test XPath selector error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->post('/api/v1/scraper/presets/test-selectors', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    if (!ensureCsrfToken()) {
        return;
    }

    try {
        $input = parseJsonRequest();
        $url = trim($input['url'] ?? '');
        $selectors = $input['selectors'] ?? [];
        $maxSamples = min(max((int)($input['max_samples'] ?? 5), 1), 10);

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return jsonResponse(['success' => false, 'error' => 'Valid URL is required'], 400);
        }
        if (!is_array($selectors) || empty($selectors)) {
            return jsonResponse(['success' => false, 'error' => 'Selectors object is required'], 400);
        }

        $httpClient = new \App\Modules\Scraper\HttpClientService();
        $response = $httpClient->get($url);

        if (!$httpClient->isSuccess($response)) {
            return jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch the URL (HTTP ' . $httpClient->getStatusCode($response) . ')'
            ], 400);
        }

        $html = $httpClient->getBody($response);
        $testingService = new \App\Modules\Scraper\Services\SelectorTestingService($html);

        $results = [];
        foreach ($selectors as $name => $value) {
            $selector = '';
            $type = 'css';
            if (is_array($value)) {
                $selector = trim($value['selector'] ?? '');
                $type = strtolower($value['type'] ?? 'css');
            } else {
                $selector = trim((string)$value);
            }

            if ($selector === '') {
                $results[$name] = [
                    'success' => false,
                    'error' => 'Selector is empty'
                ];
                continue;
            }

            if ($type === 'xpath') {
                $results[$name] = $testingService->testXPathSelector($selector, $maxSamples);
            } else {
                $results[$name] = $testingService->testCssSelector($selector, $maxSamples);
            }
        }

        return jsonResponse([
            'success' => true,
            'url' => $url,
            'results' => $results
        ]);
    } catch (Exception $e) {
        error_log("Preset selector test error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Test Attribute Extraction
 *
 * Tests extracting a specific attribute from elements matched by a selector.
 * Useful for extracting URLs from href attributes, image sources from src attributes, etc.
 *
 * @route POST /admin/scraper/selectors/test-attribute
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - selector (string, required): CSS or XPath selector to match elements
 *               - attribute (string, required): Attribute name to extract (e.g., 'href', 'src', 'data-id')
 *               - url (string, required): URL to test against
 *               - max_samples (int, optional): Maximum samples to return (default: 5)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - selector: Tested selector
 *               - attribute: Extracted attribute name
 *               - matches: Array of extracted attribute values
 *               - count: Number of matches
 *               - samples: Array of sample matches with element context
 *               - error: string (if failed)
 *
 * @throws Exception If URL fetch fails or attribute extraction fails
 *
 * @example Success: {"success": true, "selector": "a.link", "attribute": "href", "count": 10, "samples": [...]}
 * @example Error: {"success": false, "error": "No attribute specified"}
 */
$router->post('/admin/scraper/selectors/test-attribute', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $selector = trim($input['selector'] ?? '');
        $attribute = trim($input['attribute'] ?? '');
        $url = trim($input['url'] ?? '');
        $maxSamples = (int)($input['max_samples'] ?? 5);

        if (empty($selector)) {
            return jsonResponse(['success' => false, 'error' => 'No selector provided'], 400);
        }

        if (empty($attribute)) {
            return jsonResponse(['success' => false, 'error' => 'No attribute specified'], 400);
        }

        if (empty($url)) {
            return jsonResponse(['success' => false, 'error' => 'No URL provided'], 400);
        }

        // Fetch the URL
        $httpClient = new \App\Modules\Scraper\HttpClientService();
        $response = $httpClient->get($url);

        if (!$httpClient->isSuccess($response)) {
            return jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch URL (HTTP ' . $httpClient->getStatusCode($response) . ')'
            ], 400);
        }

        $html = $httpClient->getBody($response);

        // Test attribute extraction
        $testingService = new \App\Modules\Scraper\Services\SelectorTestingService($html);
        $result = $testingService->testAttributeExtraction($selector, $attribute, $maxSamples);

        return jsonResponse($result);
    } catch (Exception $e) {
        error_log("Test attribute extraction error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Test Nested Selectors
 *
 * Tests extracting multiple fields from container elements using nested selectors.
 * This is useful for scraping structured data like product listings, job postings, or article cards.
 *
 * @route POST /admin/scraper/selectors/test-nested
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - container_selector (string, required): Selector for container elements
 *               - field_mappings (array, required): Array of field name to selector mappings
 *                 Example: {"title": "h2.title", "price": ".price", "link": "a@href"}
 *               - url (string, required): URL to test against
 *               - max_samples (int, optional): Maximum samples to return (default: 5)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - container_selector: Tested container selector
 *               - field_mappings: Field mappings used
 *               - containers: Number of container elements found
 *               - samples: Array of extracted data samples
 *               - error: string (if failed)
 *
 * @throws Exception If URL fetch fails or nested extraction fails
 *
 * @example Success: {"success": true, "containers": 5, "samples": [{"title": "Product 1", "price": "$10"}, ...]}
 * @example Error: {"success": false, "error": "No field mappings provided"}
 */
$router->post('/admin/scraper/selectors/test-nested', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $containerSelector = trim($input['container_selector'] ?? '');
        $fieldMappings = $input['field_mappings'] ?? [];
        $url = trim($input['url'] ?? '');
        $maxSamples = (int)($input['max_samples'] ?? 5);

        if (empty($containerSelector)) {
            return jsonResponse(['success' => false, 'error' => 'No container selector provided'], 400);
        }

        if (empty($fieldMappings)) {
            return jsonResponse(['success' => false, 'error' => 'No field mappings provided'], 400);
        }

        if (empty($url)) {
            return jsonResponse(['success' => false, 'error' => 'No URL provided'], 400);
        }

        // Fetch the URL
        $httpClient = new \App\Modules\Scraper\HttpClientService();
        $response = $httpClient->get($url);

        if (!$httpClient->isSuccess($response)) {
            return jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch URL (HTTP ' . $httpClient->getStatusCode($response) . ')'
            ], 400);
        }

        $html = $httpClient->getBody($response);

        // Test nested selection
        $testingService = new \App\Modules\Scraper\Services\SelectorTestingService($html);
        $fieldMappingsJson = is_array($fieldMappings) ? json_encode($fieldMappings) : $fieldMappings;
        $result = $testingService->testNestedSelection($containerSelector, $fieldMappingsJson, $maxSamples);

        return jsonResponse($result);
    } catch (Exception $e) {
        error_log("Test nested selectors error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Validate Multiple Selectors
 *
 * Validates a batch of selectors against a URL to check if they work correctly.
 * Useful for validating all selectors in a scraper configuration before deployment.
 *
 * @route POST /admin/scraper/selectors/validate-batch
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - selectors (array, required): Array of selectors to validate
 *                 Each selector can be a string or object with 'selector' and 'type' properties
 *               - url (string, required): URL to test against
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - selectors_count: Total number of selectors tested
 *               - valid_count: Number of valid selectors
 *               - results: Array of validation results for each selector
 *                 - selector: The selector tested
 *                 - valid: boolean indicating if selector works
 *                 - matches: Number of matches found
 *                 - error: Error message if validation failed
 *
 * @throws Exception If URL fetch fails or validation fails
 *
 * @example Success: {"success": true, "selectors_count": 5, "valid_count": 4, "results": [...]}
 * @example Error: {"success": false, "error": "No selectors provided"}
 */
$router->post('/admin/scraper/selectors/validate-batch', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $selectors = $input['selectors'] ?? [];
        $url = trim($input['url'] ?? '');

        if (empty($selectors) || !is_array($selectors)) {
            return jsonResponse(['success' => false, 'error' => 'No selectors provided'], 400);
        }

        if (empty($url)) {
            return jsonResponse(['success' => false, 'error' => 'No URL provided'], 400);
        }

        // Fetch the URL
        $httpClient = new \App\Modules\Scraper\HttpClientService();
        $response = $httpClient->get($url);

        if (!$httpClient->isSuccess($response)) {
            return jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch URL (HTTP ' . $httpClient->getStatusCode($response) . ')'
            ], 400);
        }

        $html = $httpClient->getBody($response);

        // Validate batch
        $testingService = new \App\Modules\Scraper\Services\SelectorTestingService($html);
        $results = $testingService->validateSelectors($selectors);

        return jsonResponse([
            'success' => true,
            'selectors_count' => count($selectors),
            'valid_count' => count(array_filter($results, fn($r) => $r['valid'])),
            'results' => $results
        ]);
    } catch (Exception $e) {
        error_log("Validate batch selectors error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Get Page Metadata
 *
 * Retrieves metadata from a webpage including title, description, canonical URL, and HTML size.
 * Useful for understanding page structure before creating scraper configurations.
 *
 * @route POST /admin/scraper/selectors/page-info
 * @middleware auth (optional - can be used without auth for testing)
 *
 * @request_body JSON object containing:
 *               - url (string, required): URL to fetch metadata from
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - url: The URL that was fetched
 *               - title: Page title from <title> tag
 *               - description: Meta description from <meta name="description">
 *               - canonical_url: Canonical URL from <link rel="canonical">
 *               - html_size: Size of HTML content in bytes
 *               - message: Status message
 *
 * @throws Exception If URL fetch fails or metadata extraction fails
 *
 * @example Success: {"success": true, "url": "https://example.com", "title": "Example", "description": "...", "canonical_url": "...", "html_size": 12345}
 * @example Error: {"success": false, "error": "No URL provided"}
 */
$router->post('/admin/scraper/selectors/page-info', function () use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $url = trim($input['url'] ?? '');

        if (empty($url)) {
            return jsonResponse(['success' => false, 'error' => 'No URL provided'], 400);
        }

        // Fetch the URL
        $httpClient = new \App\Modules\Scraper\HttpClientService();
        $response = $httpClient->get($url);

        if (!$httpClient->isSuccess($response)) {
            return jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch URL (HTTP ' . $httpClient->getStatusCode($response) . ')'
            ], 400);
        }

        $html = $httpClient->getBody($response);
        $testingService = new \App\Modules\Scraper\Services\SelectorTestingService($html);

        return jsonResponse([
            'success' => true,
            'url' => $url,
            'title' => $testingService->getPageTitle(),
            'description' => $testingService->getPageDescription(),
            'canonical_url' => $testingService->detectPageUrl(),
            'html_size' => strlen($html),
            'message' => 'Page metadata retrieved'
        ]);
    } catch (Exception $e) {
        error_log("Get page info error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * AI-Powered Selector Suggestions
 *
 * Uses AI to analyze a webpage and suggest optimal CSS/XPath selectors for common content types.
 *
 * @route POST /admin/scraper/selectors/ai-suggest
 * @middleware auth, admin_only
 *
 * @request_body JSON object containing:
 *               - url (string, required): URL to analyze
 *               - content_type (string, optional): Type of content to extract ('articles', 'products', 'news', etc.)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - suggestions: Array of selector suggestions
 *                 - type: 'css' or 'xpath'
 *                 - selector: The suggested selector
 *                 - confidence: Confidence score (0-1)
 *                 - description: What this selector targets
 *                 - sample_matches: Number of matches found
 *               - page_analysis: Basic page structure analysis
 *
 * @example {"success": true, "suggestions": [...], "page_analysis": {...}}
 */
$router->post('/admin/scraper/selectors/ai-suggest', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $url = trim($input['url'] ?? '');
        $contentType = trim($input['content_type'] ?? 'articles');

        if (empty($url)) {
            return jsonResponse(['success' => false, 'error' => 'No URL provided'], 400);
        }

        // Fetch the URL
        $httpClient = new \App\Modules\Scraper\HttpClientService();
        $response = $httpClient->get($url);

        if (!$httpClient->isSuccess($response)) {
            return jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch URL (HTTP ' . $httpClient->getStatusCode($response) . ')'
            ], 400);
        }

        $html = $httpClient->getBody($response);
        $testingService = new \App\Modules\Scraper\Services\SelectorTestingService($html);

        // Use AI analyzer to analyze HTML and get selector suggestions
        $aiAnalyzer = new \App\Modules\Scraper\AIScraperAnalyzer($mysqli);
        $analysis = $aiAnalyzer->analyzeHtml($html, $url);

        // Convert analysis results to selector suggestions
        $suggestions = [];
        if ($analysis->success) {
            // Use recommended selectors from AI analysis
            foreach ($analysis->recommendedSelectors as $type => $selectors) {
                foreach ($selectors as $selector) {
                    if (!empty($selector)) {
                        // Test the selector
                        $testResult = $testingService->testCssSelector($selector, 3);

                        $suggestions[] = [
                            'type' => 'css',
                            'selector' => $selector,
                            'confidence' => $analysis->confidence,
                            'description' => ucfirst($type) . ' selector (AI recommended)',
                            'sample_matches' => $testResult['count'] ?? 0
                        ];
                    }
                }
            }

            // Also include validated results with their scores
            foreach ($analysis->validatedResults as $type => $results) {
                foreach ($results as $result) {
                    if ($result->isValid()) {
                        $suggestions[] = [
                            'type' => 'css',
                            'selector' => $result->selector,
                            'confidence' => $result->score,
                            'description' => ucfirst($type) . ' selector (validated)',
                            'sample_matches' => $result->found
                        ];
                    }
                }
            }
        }

        // If no AI suggestions, provide basic fallback suggestions
        if (empty($suggestions)) {
            $basicSelectors = [
                ['type' => 'css', 'selector' => 'article', 'description' => 'Article elements'],
                ['type' => 'css', 'selector' => '.post', 'description' => 'Post containers'],
                ['type' => 'css', 'selector' => '.entry', 'description' => 'Entry containers'],
                ['type' => 'css', 'selector' => '.content', 'description' => 'Content containers'],
                ['type' => 'css', 'selector' => 'h1, h2, h3', 'description' => 'Headings'],
            ];

            foreach ($basicSelectors as $basic) {
                $testResult = $testingService->testCssSelector($basic['selector'], 3);
                if ($testResult['count'] > 0) {
                    $suggestions[] = [
                        'type' => $basic['type'],
                        'selector' => $basic['selector'],
                        'confidence' => 0.3,
                        'description' => $basic['description'],
                        'sample_matches' => $testResult['count']
                    ];
                }
            }
        }

        // Basic page analysis
        $pageAnalysis = [
            'title' => $testingService->getPageTitle(),
            'description' => $testingService->getPageDescription(),
            'canonical_url' => $testingService->detectPageUrl(),
            'has_structured_data' => strpos($html, 'application/ld+json') !== false,
            'estimated_content_blocks' => substr_count($html, '<article') + substr_count($html, '<div') + substr_count($html, '<section'),
            'html_size' => strlen($html)
        ];

        return jsonResponse([
            'success' => true,
            'suggestions' => $suggestions,
            'page_analysis' => $pageAnalysis,
            'message' => 'AI selector suggestions generated'
        ]);
    } catch (Exception $e) {
        error_log("AI selector suggestions error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Run Source
 *
 * Triggers a scraping job for a specific source configuration.
 * This is a programmatic API endpoint for external systems to trigger scraping.
 *
 * @route POST /api/v1/scraper/sources/{id}/run
 * @middleware auth
 *
 * @param id (int, required): Source ID to run
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - data: Job information including job_id, status, and estimated completion time
 *
 * @throws Exception If source not found or scraping fails to start
 *
 * @example Success: {"success": true, "data": {"job_id": 123, "status": "queued", "eta": "5 minutes"}}
 * @example Error: {"success": false, "error": "Source not found"}
 */
$router->post('/api/v1/scraper/sources/{id}/run', ['middleware' => ['auth']], function ($params) use ($mysqli) {
    // Validate CSRF token
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $id = (int)$params['id'];
        $model = new ScraperModel($mysqli);
        $service = new \App\Modules\Scraper\ScraperService($model);

        $result = $service->runSource($id);

        return jsonResponse([
            'success' => true,
            'data' => $result
        ]);
    } catch (Exception $e) {
        error_log("API run source error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Get Queue
 *
 * Retrieves pending scraping jobs from the queue.
 * Useful for monitoring queue status and job progress.
 *
 * @route GET /api/v1/scraper/queue
 * @middleware auth
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - data: Array of pending jobs
 *                 - id: Job ID
 *                 - source_id: Source configuration ID
 *                 - source_name: Source name
 *                 - status: Job status (pending, running, completed, failed)
 *                 - created_at: Job creation timestamp
 *                 - priority: Job priority level
 *
 * @throws Exception If queue retrieval fails
 *
 * @example Success: {"success": true, "data": [{"id": 1, "source_id": 5, "status": "pending", ...}]}
 */
$router->get('/api/v1/scraper/queue', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $jobs = $model->getPendingJobs(50);

        return jsonResponse([
            'success' => true,
            'data' => $jobs
        ]);
    } catch (Exception $e) {
        error_log("API get queue error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Get Logs
 *
 * Retrieves scraper logs with optional filtering by source and log level.
 * Useful for debugging and monitoring scraper performance.
 *
 * @route GET /api/v1/scraper/logs
 * @middleware auth
 *
 * @query_param source_id (int, optional): Filter logs by source ID
 * @query_param level (string, optional): Filter logs by level (info, warning, error)
 * @query_param limit (int, optional): Maximum number of logs to return (default: 100)
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - data: Array of log entries
 *                 - id: Log ID
 *                 - source_id: Source ID
 *                 - level: Log level
 *                 - message: Log message
 *                 - context: Additional context data
 *                 - created_at: Log timestamp
 *               - pagination: Pagination metadata
 *
 * @throws Exception If log retrieval fails
 *
 * @example Success: {"success": true, "data": [{"id": 1, "level": "info", "message": "...", ...}], "pagination": {...}}
 */
$router->get('/api/v1/scraper/logs', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $sourceId = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;
        $level = isset($_GET['level']) ? $_GET['level'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

        $logs = $model->getLogs([
            'source_id' => $sourceId,
            'level' => $level
        ], 1, $limit);

        return jsonResponse([
            'success' => true,
            'data' => $logs['logs'],
            'pagination' => [
                'total' => $logs['total'],
                'page' => $logs['page'],
                'limit' => $logs['limit'],
                'pages' => $logs['pages']
            ]
        ]);
    } catch (Exception $e) {
        error_log("API get logs error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Get Statistics
 *
 * Retrieves overall scraper statistics including job counts, success rates, and performance metrics.
 * Useful for monitoring scraper health and performance.
 *
 * @route GET /api/v1/scraper/stats
 * @middleware auth
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - data: Statistics object containing:
 *                 - total_sources: Total number of scraper sources
 *                 - active_sources: Number of active sources
 *                 - total_jobs: Total number of jobs
 *                 - completed_jobs: Number of completed jobs
 *                 - failed_jobs: Number of failed jobs
 *                 - success_rate: Success rate percentage
 *                 - avg_duration: Average job duration in seconds
 *                 - total_articles: Total articles collected
 *                 - recent_activity: Recent activity summary
 *
 * @throws Exception If statistics retrieval fails
 *
 * @example Success: {"success": true, "data": {"total_sources": 10, "active_sources": 8, "success_rate": 95.5, ...}}
 */
$router->get('/api/v1/scraper/stats', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $stats = $model->getOverallStats();

        return jsonResponse([
            'success' => true,
            'data' => $stats
        ]);
    } catch (Exception $e) {
        error_log("API get stats error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/admin/scraper/presets/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    try {
        echo $twig->render('scraper/presets/create.twig', [
            'pageTitle' => 'Create Scraper Preset',
            'contentTypes' => ['articles', 'blog', 'news', 'product', 'service', 'mobiles']
        ]);
    } catch (Exception $e) {
        error_log("Preset create page error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load preset creation form.'
        ]);
    }
});

$router->post('/admin/scraper/presets/create', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken()) {
        showMessage('Invalid CSRF token', 'error');
        header('Location: /admin/scraper/presets/create');
        exit;
    }

    try {
        $key = trim($_POST['preset_key'] ?? '');
        $name = trim($_POST['preset_name'] ?? '');
        $contentType = trim($_POST['content_type'] ?? 'articles');
        $description = trim($_POST['description'] ?? '');
        $selectors = trim($_POST['selectors'] ?? '');
        $advance = trim($_POST['advance_config'] ?? '');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        if ($key === '' || $name === '') {
            showMessage('Key and name are required', 'error');
            header('Location: /admin/scraper/presets/create');
            exit;
        }

        $model = new ScraperModel($mysqli);
        $created = $model->createPreset([
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'content_type' => $contentType,
            'selectors' => $selectors !== '' ? $selectors : null,
            'advance_config' => $advance !== '' ? $advance : null,
            'is_default' => $isDefault
        ]);

        if (!$created) {
            showMessage('Failed to create preset', 'error');
            header('Location: /admin/scraper/presets/create');
            exit;
        }

        showMessage('Preset created successfully', 'success');
        header('Location: /admin/scraper/presets', true, 302);
        exit;
    } catch (Exception $e) {
        error_log("Create preset error: " . $e->getMessage());
        showMessage('Failed to create preset: ' . $e->getMessage(), 'error');
        header('Location: /admin/scraper/presets/create');
        exit;
    }
});

/**
 * API: Job Health Summary
 *
 * Provides success/failure counts and success rate derived from recent jobs.
 *
 * @route GET /api/v1/scraper/jobs/health
 * @middleware auth
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - data: Object containing stats and health signals
 */
$router->get('/api/v1/scraper/jobs/health', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $stats = $model->getOverallStats();
        $jobs = $stats['jobs'] ?? [];
        $completed = (int)($jobs['completed'] ?? 0);
        $failed = (int)($jobs['failed'] ?? 0);
        $finished = $completed + $failed;
        $successRate = $finished === 0 ? 0 : round(($completed / $finished) * 100, 2);
        $lastCompletedResult = $mysqli->query("SELECT MAX(completed_at) as last_completed FROM web_scraping_jobs WHERE completed_at IS NOT NULL");
        $lastCompleted = $lastCompletedResult ? $lastCompletedResult->fetch_assoc()['last_completed'] : null;

        return jsonResponse([
            'success' => true,
            'data' => [
                'stats' => $jobs,
                'completed_jobs' => $completed,
                'failed_jobs' => $failed,
                'success_rate' => $successRate,
                'finished_jobs' => $finished,
                'last_completed_at' => $lastCompleted,
                'timestamp' => date('c')
            ]
        ]);
    } catch (Exception $e) {
        error_log("API job health error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Collected Data Summary
 *
 * Returns aggregated article counts, published timestamps, and category breakdowns.
 *
 * @route GET /api/v1/scraper/collected-data/summary
 * @middleware auth
 */
$router->get('/api/v1/scraper/collected-data/summary', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $summary = $model->getCollectedDataSummary();

        return jsonResponse([
            'success' => true,
            'data' => $summary
        ]);
    } catch (Exception $e) {
        error_log("API collected data summary error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Logs Summary
 *
 * Returns log-level counts and latest timestamp for the current filters.
 *
 * @route GET /api/v1/scraper/logs/summary
 * @middleware auth
 */
$router->get('/api/v1/scraper/logs/summary', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $filters = [
            'source_id' => isset($_GET['source_id']) ? (int)$_GET['source_id'] : null,
            'level' => $_GET['level'] ?? null
        ];
        $summary = $model->getLogSummary($filters);

        return jsonResponse([
            'success' => true,
            'data' => $summary
        ]);
    } catch (Exception $e) {
        error_log("API logs summary error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

 // ================== DIAGNOSTICS ==================

/**
 * API: CSS Selector Test
 * 
 * Tests CSS selectors against a URL
 * 
 * @route POST /api/v1/scraper/diagnostics/css-selector
 * @middleware auth
 */
$router->post('/api/v1/scraper/diagnostics/css-selector', ['middleware' => ['auth']], function () use ($mysqli) {
    // Validate CSRF token for POST requests
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            return jsonResponse(['success' => false, 'error' => 'Invalid JSON input'], 400);
        }

        $url = $input['url'] ?? '';
        $selectors = $input['selectors'] ?? [];

        if (empty($url)) {
            return jsonResponse(['success' => false, 'error' => 'URL is required'], 400);
        }

        if (!is_array($selectors) || empty($selectors)) {
            return jsonResponse(['success' => false, 'error' => 'Selectors must be a non-empty array'], 400);
        }

        // Import and use the CSS Selector Tester
        require_once __DIR__ . '/../../Modules/Scraper/Diagnostics/CssSelectorTester.php';
        $tester = new \App\Modules\Scraper\Diagnostics\CssSelectorTester();
        $results = $tester->testSelectors($url, $selectors);

        return jsonResponse([
            'success' => true,
            'result' => $tester->getFormattedResults()
        ]);
    } catch (Exception $e) {
        error_log("CSS selector test error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Service Test
 * 
 * Tests individual scraping services
 * 
 * @route POST /api/v1/scraper/diagnostics/service
 * @middleware auth
 */
$router->post('/api/v1/scraper/diagnostics/service', ['middleware' => ['auth']], function () use ($mysqli) {
    // Validate CSRF token for POST requests
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            return jsonResponse(['success' => false, 'error' => 'Invalid JSON input'], 400);
        }

        $url = $input['url'] ?? '';
        $service = $input['service'] ?? '';

        if (empty($url)) {
            return jsonResponse(['success' => false, 'error' => 'URL is required'], 400);
        }

        if (empty($service)) {
            return jsonResponse(['success' => false, 'error' => 'Service is required'], 400);
        }

        // Import and use the Service Tester
        require_once __DIR__ . '/../../Modules/Scraper/Diagnostics/ServiceTester.php';
        $tester = new \App\Modules\Scraper\Diagnostics\ServiceTester();

        $result = null;
        switch ($service) {
            case 'php_scraper':
                $result = $tester->testPhpScraper($url);
                break;
            case 'panther':
                $result = $tester->testPanther($url);
                break;
            case 'roach':
                $result = $tester->testRoach($url);
                break;
            case 'php_spider':
                $result = $tester->testPhpSpider($url);
                break;
            default:
                return jsonResponse(['success' => false, 'error' => 'Invalid service specified'], 400);
        }

        if ($result === null) {
            return jsonResponse(['success' => false, 'error' => 'Failed to run test'], 500);
        }

        return jsonResponse([
            'success' => true,
            'result' => $tester->getFormattedResults()
        ]);
    } catch (Exception $e) {
        error_log("Service test error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: All Services Test
 * 
 * Tests all scraping services against a URL
 * 
 * @route POST /api/v1/scraper/diagnostics/services
 * @middleware auth
 */
$router->post('/api/v1/scraper/diagnostics/services', ['middleware' => ['auth']], function () use ($mysqli) {
    // Validate CSRF token for POST requests
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            return jsonResponse(['success' => false, 'error' => 'Invalid JSON input'], 400);
        }

        $url = $input['url'] ?? '';

        if (empty($url)) {
            return jsonResponse(['success' => false, 'error' => 'URL is required'], 400);
        }

        // Import and use the Service Tester
        require_once __DIR__ . '/../../Modules/Scraper/Diagnostics/ServiceTester.php';
        $tester = new \App\Modules\Scraper\Diagnostics\ServiceTester();
        $results = $tester->runAllTests($url);

        return jsonResponse([
            'success' => true,
            'result' => $tester->getFormattedResults()
        ]);
    } catch (Exception $e) {
        error_log("All services test error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: System Diagnostics
 * 
 * Runs system diagnostics on the scraper system
 * 
 * @route GET /api/v1/scraper/diagnostics/system
 * @middleware auth
 */
$router->get('/api/v1/scraper/diagnostics/system', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        // Import ScraperModel for diagnostics
        require_once __DIR__ . '/../../Models/ScraperModel.php';
        $model = new ScraperModel($mysqli);

        // Run diagnostics similar to our diagnostic script
        $output = [];
        $output[] = "SCRAPER SYSTEM DIAGNOSTICS";
        $output[] = "==========================";
        $output[] = "Timestamp: " . date('Y-m-d H:i:s');
        $output[] = "";

        // 1. Get all active sources
        $activeSources = $model->getActiveSources();
        $output[] = "1. ACTIVE SCRAPER SOURCES";
        $output[] = "-------------------------";
        $output[] = "Total active sources: " . count($activeSources);
        $output[] = "";

        if (!empty($activeSources)) {
            foreach ($activeSources as $source) {
                $output[] = "Source ID: {$source['id']}";
                $output[] = "Name: {$source['name']}";
                $output[] = "URL: {$source['url']}";
                $output[] = "Type: {$source['type']}";
                $output[] = "Content Type: {$source['content_type']}";
                $output[] = "Last Fetched: " . ($source['last_fetched_at'] ?? 'Never');
                $output[] = "Fetch Interval: {$source['fetch_interval']} seconds";
                $output[] = "Selectors: " . ($source['selectors'] ? 'Set' : 'Not Set');
                $output[] = "Advance Config: " . ($source['advance_config'] ? 'Set' : 'Not Set');
                $output[] = "Preset: " . ($source['presets'] ? $source['presets'] : 'None');
                $output[] = "";
            }
        } else {
            $output[] = "No active sources found.";
            $output[] = "";
        }

        // 2. Get overall stats
        $stats = $model->getOverallStats();
        $output[] = "2. OVERALL STATISTICS";
        $output[] = "---------------------";
        $output[] = "Total Sources: {$stats['total_sources']}";
        $output[] = "Active Sources: {$stats['active_sources']}";
        $output[] = "Total Articles: {$stats['total_articles']}";
        $output[] = "";
        $output[] = "Jobs Stats:";
        if (!empty($stats['jobs'])) {
            foreach ($stats['jobs'] as $status => $count) {
                $output[] = "  {$status}: {$count}";
            }
        }
        $output[] = "";
        $output[] = "Queue Stats:";
        if (!empty($stats['queue'])) {
            foreach ($stats['queue'] as $status => $count) {
                $output[] = "  {$status}: {$count}";
            }
        }
        $output[] = "";

        // 3. Get recent failed jobs
        $failedJobs = $model->getJobs(1, 10, 'failed');
        $output[] = "3. RECENT FAILED JOBS (LAST 10)";
        $output[] = "--------------------------------";
        if (!empty($failedJobs['jobs'])) {
            $output[] = "Found " . count($failedJobs['jobs']) . " failed jobs:";
            $output[] = "";
            foreach ($failedJobs['jobs'] as $job) {
                $output[] = "Job ID: {$job['id']}";
                $output[] = "Source: {$job['source_name']}";
                $output[] = "Job Type: {$job['job_type']}";
                $output[] = "Created: {$job['created_at']}";
                if (!empty($job['error_message'])) {
                    $output[] = "Error: {$job['error_message']}";
                }
                $output[] = "";
            }
        } else {
            $output[] = "No failed jobs found.";
            $output[] = "";
        }

        // 4. Sources that haven't been fetched recently
        $allSources = $model->getAllSources();
        $staleSources = [];
        $now = new DateTime();
        foreach ($allSources as $source) {
            if ($source['is_active']) {
                $lastFetched = $source['last_fetched_at'] ? new DateTime($source['last_fetched_at']) : null;
                if (!$lastFetched || $now->diff($lastFetched)->h >= 24) {
                    $staleSources[] = $source;
                }
            }
        }

        $output[] = "4. SOURCES NOT FETCHED IN LAST 24 HOURS";
        $output[] = "----------------------------------------";
        if (!empty($staleSources)) {
            $output[] = "Found " . count($staleSources) . " stale sources:";
            $output[] = "";
            foreach ($staleSources as $source) {
                $output[] = "Source ID: {$source['id']}";
                $output[] = "Name: {$source['name']}";
                $output[] = "URL: {$source['url']}";
                $output[] = "Last Fetched: " . ($source['last_fetched_at'] ?? 'Never');
                $output[] = "";
            }
        } else {
            $output[] = "All active sources have been fetched within the last 24 hours.";
            $output[] = "";
        }

        $output[] = "=== END OF DIAGNOSTICS ===";

        return jsonResponse([
            'success' => true,
            'result' => implode("\n", $output)
        ]);
    } catch (Exception $e) {
        error_log("System diagnostics error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Categories
 *
 * Returns the list of scraper categories with metadata.
 *
 * @route GET /api/v1/scraper/categories
 * @middleware auth
 */
$router->get('/api/v1/scraper/categories', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $categories = $model->getCategories();

        return jsonResponse([
            'success' => true,
            'data' => $categories
        ]);
    } catch (Exception $e) {
        error_log("API categories error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/api/v1/scraper/categories/table', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $rows = $model->getCategoryTableData();
        return jsonResponse([
            'success' => true,
            'data' => $rows
        ]);
    } catch (Exception $e) {
        error_log("API categories table error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

$router->get('/api/v1/scraper/external-data', function () use ($mysqli) {
    if (!ensureScraperApiKey()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid API key'], 401);
    }

    $type = $_GET['data_type'] ?? 'articles';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(max((int)($_GET['limit'] ?? 25), 1), 100);
    $sourceId = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;
    $status = $_GET['status'] ?? null;
    $contentType = $_GET['content_type'] ?? null;
    $search = $_GET['search'] ?? null;

    $model = new ScraperModel($mysqli);
    if ($type === 'mobiles') {
        $payload = $model->getMobiles($page, $limit, $sourceId, $search);
        return jsonResponse([
            'success' => true,
            'type' => 'mobiles',
            'data' => $payload['mobiles'] ?? [],
            'total' => $payload['total'] ?? 0,
            'page' => $payload['page'] ?? $page,
            'limit' => $payload['limit'] ?? $limit
        ]);
    }

    $articles = $model->getArticles($page, $limit, $status, $sourceId, $search, $contentType);
    return jsonResponse([
        'success' => true,
        'type' => 'articles',
        'data' => $articles['articles'] ?? [],
        'pagination' => $articles['pagination'] ?? []
    ]);
});

$router->post('/api/v1/scraper/external-data', function () use ($mysqli) {
    if (!ensureScraperApiKey()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid API key'], 401);
    }

    $input = parseJsonRequest();
    $type = $input['type'] ?? 'article';
    $sourceId = isset($input['source_id']) ? (int)$input['source_id'] : 0;
    $url = trim($input['url'] ?? '');

    if (!$sourceId || $url === '') {
        return jsonResponse(['success' => false, 'error' => 'source_id and url are required'], 400);
    }

    $model = new ScraperModel($mysqli);

    if ($type === 'mobile') {
        $payload = [
            'source_id' => $sourceId,
            'source_url' => $url,
            'title' => $input['title'] ?? ($input['name'] ?? 'Device'),
            'price' => $input['price'] ?? 0,
            'brand' => $input['brand'] ?? null,
            'model' => $input['model'] ?? null,
            'image_url' => $input['image_url'] ?? '',
            'specifications' => $input['specifications'] ?? [],
            'release_date' => $input['release_date'] ?? null,
            'status' => $input['status'] ?? 'active',
        ];

        $saved = $model->saveMobile($payload);
        $record = $model->getMobileByUrl($url);
        return jsonResponse([
            'success' => (bool)$saved,
            'id' => $record['id'] ?? null
        ]);
    }

    $content = $input['content'] ?? '';
    $title = $input['title'] ?? '';

    $payload = [
        'source_id' => $sourceId,
        'url' => $url,
        'title' => $title ?: 'Untitled',
        'content' => $content,
        'excerpt' => $input['excerpt'] ?? substr($content, 0, 200),
        'author' => $input['author'] ?? '',
        'image_url' => $input['image_url'] ?? '',
        'published_at' => $input['published_at'] ?? null,
        'status' => $input['status'] ?? 'completed',
        'content_hash' => hash('sha256', $url . $title),
        'categories' => $input['categories'] ?? [],
        'tags' => $input['tags'] ?? []
    ];

    $saved = $model->saveArticle($payload);
    $record = $model->getArticleByUrl($url);
    return jsonResponse([
        'success' => (bool)$saved,
        'id' => $record['id'] ?? null
    ]);
});

$router->post('/api/v1/scraper/categories', ['middleware' => ['auth']], function () use ($mysqli) {
    if (!validateCsrfToken()) {
        return jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    try {
        $input = json_decode((string)file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            return jsonResponse(['success' => false, 'error' => 'Category name is required'], 400);
        }

        $model = new ScraperModel($mysqli);
        $newId = $model->createCategory([
            'name' => $name,
            'description' => trim($input['description'] ?? ''),
            'parent_id' => isset($input['parent_id']) ? (int)$input['parent_id'] : null,
            'is_active' => !empty($input['is_active']) ? 1 : 0
        ]);

        if (!$newId) {
            return jsonResponse(['success' => false, 'error' => 'Unable to create category'], 500);
        }

        $category = $model->getCategoryById($newId);

        return jsonResponse([
            'success' => true,
            'category' => $category
        ]);
    } catch (Exception $e) {
        error_log("API create category error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Settings List
 *
 * Returns settings metadata for the admin UI summary strip.
 *
 * @route GET /api/v1/scraper/settings
 * @middleware auth
 */
$router->get('/api/v1/scraper/settings', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        $limit = isset($_GET['limit']) ? min(200, max(5, (int)$_GET['limit'])) : 50;
        $model = new ScraperModel($mysqli);
        $payload = $model->getSettingsForApi($limit);

        return jsonResponse([
            'success' => true,
            'data' => $payload
        ]);
    } catch (Exception $e) {
        error_log("API settings error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * API: Get Presets
 *
 * Retrieves all available scraper presets for different website types.
 * Presets provide pre-configured selectors for common website structures.
 *
 * @route GET /api/v1/scraper/presets
 * @middleware auth
 *
 * @return JSON Response with:
 *               - success: boolean
 *               - data: Array of preset configurations
 *                 - key: Preset identifier (e.g., 'bdnews24', 'prothom_alo', 'wordpress_blog')
 *                 - name: Human-readable preset name
 *                 - description: Preset description
 *                 - selectors: Pre-configured selectors for this preset
 *                 - site_type: Type of website (news, blog, job, etc.)
 *
 * @throws Exception If preset retrieval fails
 *
 * @example Success: {"success": true, "data": [{"key": "bdnews24", "name": "BDNews24", "selectors": {...}}, ...]}
 */
$router->get('/api/v1/scraper/presets', ['middleware' => ['auth']], function () {
    try {
        $presets = PresetRegistry::toArray();

        return jsonResponse([
            'success' => true,
            'data' => $presets
        ]);
    } catch (Exception $e) {
        error_log("API get presets error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});
