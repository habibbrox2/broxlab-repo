<?php

declare(strict_types=1);

/**
 * BroxLab Web Scraping System - Missing Routes
 * 
 * This file contains all missing routes that need to be added to ScraperController.php
 * These routes should be appended to the end of the ScraperController before the closing PHP tag
 * 
 * Routes to add:
 * 1. GET /admin/scraper/queue - List queue items
 * 2. GET /api/v1/scraper/queue - API version of queue list
 * 3. GET /admin/scraper/logs - View scraper logs
 * 4. GET /api/v1/scraper/logs - API version of logs
 * 5. GET /admin/scraper/settings - View settings
 * 6. GET /api/v1/scraper/settings - API version of settings
 * 7. GET /admin/scraper/categories - List categories
 * 8. GET /api/v1/scraper/categories - API version of categories
 * 9. GET /admin/scraper/mobiles - List scraped mobiles
 * 10. GET /api/v1/scraper/mobiles - API version of mobiles
 * 11. GET /admin/scraper/seen-urls - List seen URLs
 * 12. GET /api/v1/scraper/seen-urls - API version of seen URLs
 * 13. GET /admin/scraper/sources/{id} - Get single source (if missing)
 * 14. GET /api/v1/scraper/sources - List all sources (API)
 * 15. GET /api/v1/scraper/sources/{id} - Get single source (API)
 */

// ================== QUEUE MANAGEMENT - MISSING ROUTES ==================

/**
 * Get Scraper Queue
 *
 * Returns list of queued scraping jobs with status and progress information.
 *
 * @route GET /admin/scraper/queue
 * @middleware auth, admin_only
 *
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 20, max: 100)
 * @query_param status string Filter by status (pending, running, completed, failed, cancelled)
 * @query_param source_id int Filter by source ID
 *
 * @return HTML Template with:
 *         - queue_items: Array of queue items
 *         - pagination: Pagination metadata
 *         - status_counts: Count of items by status
 *         - pageTitle: "Scraper Queue"
 *
 * @example GET /admin/scraper/queue?page=1&limit=20&status=pending
 */
$router->get('/admin/scraper/queue', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
        $status = $_GET['status'] ?? '';
        $sourceId = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;

        // Fetch queue items with pagination
        $result = $model->getQueueItems($page, $limit, $status, $sourceId);

        // Get status counts
        $statusCounts = [
            'all' => $result['pagination']['total'] ?? 0,
            'pending' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0
        ];

        echo $twig->render('scraper/queue/list.twig', [
            'queue_items' => $result['items'],
            'pagination' => $result['pagination'],
            'status_counts' => $statusCounts,
            'filters' => [
                'status' => $status,
                'source_id' => $sourceId
            ],
            'pageTitle' => 'Scraper Queue',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Queue list error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load queue.'
        ]);
    }
});

/**
 * Get Scraper Queue (API)
 *
 * Returns list of queued scraping jobs in JSON format.
 *
 * @route GET /api/v1/scraper/queue
 * @middleware auth, admin_only
 *
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 20, max: 100)
 * @query_param status string Filter by status
 * @query_param source_id int Filter by source ID
 *
 * @return JSON Response with:
 *         - success: true
 *         - data: Array of queue items
 *         - pagination: Pagination metadata
 *
 * @example GET /api/v1/scraper/queue?page=1&limit=20
 */
$router->get('/api/v1/scraper/queue', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
        $status = $_GET['status'] ?? '';
        $sourceId = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;

        $result = $model->getQueueItems($page, $limit, $status, $sourceId);

        return jsonResponse([
            'success' => true,
            'data' => $result['items'],
            'pagination' => $result['pagination']
        ], 200);
    } catch (Exception $e) {
        error_log("API queue list error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== LOGS MANAGEMENT - MISSING ROUTES ==================

/**
 * Get Scraper Logs
 *
 * Displays scraper activity logs and error history.
 *
 * @route GET /admin/scraper/logs
 * @middleware auth, admin_only
 *
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 50, max: 200)
 * @query_param level string Filter by log level (info, warning, error, debug)
 * @query_param event string Filter by event type
 * @query_param date_from string Filter from date (ISO 8601)
 * @query_param date_to string Filter to date (ISO 8601)
 *
 * @return HTML Template with:
 *         - logs: Array of log entries
 *         - pagination: Pagination metadata
 *         - pageTitle: "Scraper Logs"
 *
 * @example GET /admin/scraper/logs?page=1&level=error
 */
$router->get('/admin/scraper/logs', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(200, max(10, (int)$_GET['limit'])) : 50;
        $level = $_GET['level'] ?? '';
        $event = $_GET['event'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';

        // Fetch logs with pagination
        $result = $model->getLogs($page, $limit, $level, $event, $dateFrom, $dateTo);

        echo $twig->render('scraper/logs/list.twig', [
            'logs' => $result['logs'],
            'pagination' => $result['pagination'],
            'filters' => [
                'level' => $level,
                'event' => $event,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ],
            'pageTitle' => 'Scraper Logs'
        ]);
    } catch (Exception $e) {
        error_log("Logs list error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load logs.'
        ]);
    }
});

/**
 * Get Scraper Logs (API)
 *
 * Returns scraper logs in JSON format.
 *
 * @route GET /api/v1/scraper/logs
 * @middleware auth, admin_only
 *
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 50, max: 200)
 * @query_param level string Filter by log level
 * @query_param event string Filter by event type
 *
 * @return JSON Response with logs array and pagination
 *
 * @example GET /api/v1/scraper/logs?level=error
 */
$router->get('/api/v1/scraper/logs', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(200, max(10, (int)$_GET['limit'])) : 50;
        $level = $_GET['level'] ?? '';
        $event = $_GET['event'] ?? '';

        $result = $model->getLogs($page, $limit, $level, $event);

        return jsonResponse([
            'success' => true,
            'data' => $result['logs'],
            'pagination' => $result['pagination']
        ], 200);
    } catch (Exception $e) {
        error_log("API logs error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== SETTINGS - MISSING ROUTES ==================

/**
 * Get Scraper Settings
 *
 * Displays scraper configuration settings.
 *
 * @route GET /admin/scraper/settings
 * @middleware auth, admin_only
 *
 * @return HTML Template with:
 *         - settings: Array of settings
 *         - pageTitle: "Scraper Settings"
 *
 * @example GET /admin/scraper/settings
 */
$router->get('/admin/scraper/settings', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $settings = $model->getSettings();

        echo $twig->render('scraper/settings/list.twig', [
            'settings' => $settings,
            'pageTitle' => 'Scraper Settings',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Settings list error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load settings.'
        ]);
    }
});

/**
 * Get Scraper Settings (API)
 *
 * Returns scraper settings in JSON format.
 *
 * @route GET /api/v1/scraper/settings
 * @middleware auth, admin_only
 *
 * @return JSON Response with settings array
 *
 * @example GET /api/v1/scraper/settings
 */
$router->get('/api/v1/scraper/settings', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $settings = $model->getSettings();

        return jsonResponse([
            'success' => true,
            'data' => $settings
        ], 200);
    } catch (Exception $e) {
        error_log("API settings error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== CATEGORIES - MISSING ROUTES ==================

/**
 * Get Categories
 *
 * Displays all scraper content categories.
 *
 * @route GET /admin/scraper/categories
 * @middleware auth, admin_only
 *
 * @return HTML Template with:
 *         - categories: Array of categories
 *         - pageTitle: "Scraper Categories"
 *
 * @example GET /admin/scraper/categories
 */
$router->get('/admin/scraper/categories', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $categories = $model->getCategories();

        echo $twig->render('scraper/categories/list.twig', [
            'categories' => $categories,
            'pageTitle' => 'Scraper Categories',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Categories list error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load categories.'
        ]);
    }
});

/**
 * Get Categories (API)
 *
 * Returns categories in JSON format.
 *
 * @route GET /api/v1/scraper/categories
 * @middleware auth, admin_only
 *
 * @return JSON Response with categories array
 *
 * @example GET /api/v1/scraper/categories
 */
$router->get('/api/v1/scraper/categories', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);
        $categories = $model->getCategories();

        return jsonResponse([
            'success' => true,
            'data' => $categories
        ], 200);
    } catch (Exception $e) {
        error_log("API categories error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== MOBILES - MISSING ROUTES ==================

/**
 * Get Scraped Mobiles
 *
 * Displays list of scraped mobile devices.
 *
 * @route GET /admin/scraper/mobiles
 * @middleware auth, admin_only
 *
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 20, max: 100)
 * @query_param search string Search term
 * @query_param source_id int Filter by source ID
 *
 * @return HTML Template with:
 *         - mobiles: Array of mobile devices
 *         - pagination: Pagination metadata
 *         - pageTitle: "Scraped Mobiles"
 *
 * @example GET /admin/scraper/mobiles?page=1&search=Samsung
 */
$router->get('/admin/scraper/mobiles', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
        $search = $_GET['search'] ?? '';
        $sourceId = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;

        $result = $model->getMobiles($page, $limit, $search, $sourceId);

        echo $twig->render('scraper/mobiles/list.twig', [
            'mobiles' => $result['mobiles'],
            'pagination' => [
                'total' => (int)$result['total'],
                'page' => (int)$result['page'],
                'limit' => (int)$result['limit'],
                'pages' => (int)$result['pages']
            ],
            'filters' => [
                'search' => $search,
                'source_id' => $sourceId
            ],
            'pageTitle' => 'Scraped Mobiles',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Mobiles list error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load mobiles.'
        ]);
    }
});

/**
 * Get Scraped Mobiles (API)
 *
 * Returns scraped mobiles in JSON format.
 *
 * @route GET /api/v1/scraper/mobiles
 * @middleware auth, admin_only
 *
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 20, max: 100)
 * @query_param search string Search term
 *
 * @return JSON Response with mobiles array and pagination
 *
 * @example GET /api/v1/scraper/mobiles?search=Samsung
 */
$router->get('/api/v1/scraper/mobiles', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
        $search = $_GET['search'] ?? '';

        $result = $model->getMobiles($page, $limit, $search);

        return jsonResponse([
            'success' => true,
            'data' => $result['mobiles'],
            'pagination' => [
                'total' => (int)$result['total'],
                'page' => (int)$result['page'],
                'limit' => (int)$result['limit'],
                'pages' => (int)$result['pages']
            ]
        ], 200);
    } catch (Exception $e) {
        error_log("API mobiles error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== SEEN URLS - MISSING ROUTES ==================

/**
 * Get Seen URLs
 *
 * Displays list of already-seen/scraped URLs for deduplication tracking.
 *
 * @route GET /admin/scraper/seen-urls
 * @middleware auth, admin_only
 *
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 50, max: 200)
 * @query_param source_id int Filter by source ID
 * @query_param url string Search by URL
 *
 * @return HTML Template with:
 *         - seen_urls: Array of seen URLs
 *         - pagination: Pagination metadata
 *         - pageTitle: "Seen URLs"
 *
 * @example GET /admin/scraper/seen-urls?page=1&source_id=1
 */
$router->get('/admin/scraper/seen-urls', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new ScraperModel($mysqli);

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(200, max(10, (int)$_GET['limit'])) : 50;
        $sourceId = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;
        $url = $_GET['url'] ?? '';

        $result = $model->getSeenUrls($page, $limit, $sourceId, $url);

        echo $twig->render('scraper/seen-urls/list.twig', [
            'seen_urls' => $result['urls'],
            'pagination' => $result['pagination'],
            'filters' => [
                'source_id' => $sourceId,
                'url' => $url
            ],
            'pageTitle' => 'Seen URLs',
            'csrf_token' => generateCsrfToken()
        ]);
    } catch (Exception $e) {
        error_log("Seen URLs list error: " . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig', [
            'pageTitle' => 'Error',
            'message' => 'Failed to load seen URLs.'
        ]);
    }
});

/**
 * Get Seen URLs (API)
 *
 * Returns seen URLs in JSON format.
 *
 * @route GET /api/v1/scraper/seen-urls
 * @middleware auth, admin_only
 *
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 50, max: 200)
 * @query_param source_id int Filter by source ID
 *
 * @return JSON Response with seen URLs array and pagination
 *
 * @example GET /api/v1/scraper/seen-urls?source_id=1
 */
$router->get('/api/v1/scraper/seen-urls', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(200, max(10, (int)$_GET['limit'])) : 50;
        $sourceId = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;

        $result = $model->getSeenUrls($page, $limit, $sourceId);

        return jsonResponse([
            'success' => true,
            'data' => $result['urls'],
            'pagination' => $result['pagination']
        ], 200);
    } catch (Exception $e) {
        error_log("API seen URLs error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ================== SOURCES API ROUTES - MISSING ==================

/**
 * List All Sources (API)
 *
 * Returns list of all scraper sources in JSON format.
 *
 * @route GET /api/v1/scraper/sources
 * @middleware auth
 *
 * @query_param page int Page number (default: 1)
 * @query_param limit int Items per page (default: 20, max: 100)
 * @query_param is_active bool Filter by active status
 * @query_param type string Filter by source type
 *
 * @return JSON Response with:
 *         - success: true
 *         - data: Array of source objects
 *         - pagination: Pagination metadata
 *
 * @example GET /api/v1/scraper/sources?page=1&is_active=true
 */
$router->get('/api/v1/scraper/sources', ['middleware' => ['auth']], function () use ($mysqli) {
    try {
        $model = new ScraperModel($mysqli);

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
        $isActive = isset($_GET['is_active']) ? (bool)$_GET['is_active'] : null;
        $type = $_GET['type'] ?? '';

        $sources = $model->getAllSources($isActive, $type);

        // Simple pagination
        $total = count($sources);
        $pages = ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        $paginatedSources = array_slice($sources, $offset, $limit);

        return jsonResponse([
            'success' => true,
            'data' => $paginatedSources,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => $pages,
                'has_more' => $page < $pages
            ]
        ], 200);
    } catch (Exception $e) {
        error_log("API sources list error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

/**
 * Get Single Source (API)
 *
 * Returns detailed information about a single source.
 *
 * @route GET /api/v1/scraper/sources/{id}
 * @middleware auth
 *
 * @param id int Source ID
 *
 * @return JSON Response with:
 *         - success: true
 *         - data: Source object with full configuration
 *         - statistics: Source statistics
 *
 * @example GET /api/v1/scraper/sources/1
 */
$router->get('/api/v1/scraper/sources/{id}', ['middleware' => ['auth']], function ($id) use ($mysqli) {
    try {
        $id = (int)$id;
        $model = new ScraperModel($mysqli);
        $source = $model->getSourceById($id);

        if (!$source) {
            return jsonResponse(['success' => false, 'error' => 'Source not found'], 404);
        }

        // Prepare source data
        $source = prepareScraperSourceForForm($source);

        return jsonResponse([
            'success' => true,
            'data' => $source
        ], 200);
    } catch (Exception $e) {
        error_log("API get source error: " . $e->getMessage());
        return jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// End of missing routes
