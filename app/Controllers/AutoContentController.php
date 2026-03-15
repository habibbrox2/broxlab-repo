<?php

<<<<<<< HEAD
declare(strict_types=1);
=======
declare(strict_types = 1)
;
>>>>>>> temp_branch

/**
 * AutoContentController.php
 * Controller for AI Auto Content - handles web scraping, AI processing, and publishing
 */

use App\Modules\Scraper\ScraperService;
use App\Modules\Scraper\ProthomAloScraperService;
use App\Modules\Scraper\EnhancedScraperService;
use App\Modules\Scraper\ContentCleanerService;
use App\Modules\Scraper\DuplicateCheckerService;
use App\Modules\Scraper\ImageDownloaderService;
use App\Modules\Scraper\MultiLayerScraperService;
use App\Modules\Scraper\SitemapCrawlerService;

// Ensure database tables exist
$autoContentModel = new AutoContentModel($mysqli);
$autoContentModel->ensureTablesExist();

// ================== DASHBOARD ==================

/**
 * Auto Content Dashboard
 * GET /admin/autocontent
 */
$router->get('/admin/autocontent', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new AutoContentModel($mysqli);
        $stats = $model->getStats();
        $recent_items = $model->getRecentArticles();
        $sources_count = count($model->getActiveSources());

        echo $twig->render('admin/autocontent/dashboard.twig', [
<<<<<<< HEAD
            'title' => 'AI Auto Content Dashboard',
            'stats' => $stats,
            'recent_items' => $recent_items,
            'sources_count' => $sources_count,
            'current_page' => 'autocontent-dashboard'
        ]);
    } catch (Throwable $e) {
=======
        'title' => 'AI Auto Content Dashboard',
        'stats' => $stats,
        'recent_items' => $recent_items,
        'sources_count' => $sources_count,
        'current_page' => 'autocontent-dashboard'
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Dashboard Error: " . $e->getMessage());
        echo "Error loading dashboard: " . $e->getMessage();
    }
});

// ================== SOURCES ==================

/**
 * Sources List
 * GET /admin/autocontent/sources
 */
$router->get('/admin/autocontent/sources', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new AutoContentModel($mysqli);
        $sources = $model->getAllSources();

        echo $twig->render('admin/autocontent/sources.twig', [
<<<<<<< HEAD
            'title' => 'AI Article Sources',
            'sources' => $sources,
            'current_page' => 'autocontent-sources'
        ]);
    } catch (Throwable $e) {
=======
        'title' => 'AI Article Sources',
        'sources' => $sources,
        'current_page' => 'autocontent-sources'
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Sources Error: " . $e->getMessage());
        echo "Error loading sources: " . $e->getMessage();
    }
});

/**
 * Source Create Form
 * GET /admin/autocontent/sources/create
 */
$router->get('/admin/autocontent/sources/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new AutoContentModel($mysqli);
        $categories = $model->getCategories();

        // Restore old form input if available (after a validation error redirect)
        $source = null;
        try {
            $sessionMgr = getSessionManager();
            $old = $sessionMgr->get('autocontent_source_old', null, 'array');
            if (!empty($old)) {
                $source = $old;
                $sessionMgr->delete('autocontent_source_old');
            }
<<<<<<< HEAD
        } catch (Throwable $e) {
            // ignore session errors
        }

        echo $twig->render('admin/autocontent/source_form.twig', [
            'title' => 'Add Article Source',
            'source' => $source,
            'categories' => $categories,
            'isCreate' => true,
            'current_page' => 'autocontent-sources'
        ]);
    } catch (Throwable $e) {
=======
        }
        catch (Throwable $e) {
        // ignore session errors
        }

        echo $twig->render('admin/autocontent/source_form.twig', [
        'title' => 'Add Article Source',
        'source' => $source,
        'categories' => $categories,
        'isCreate' => true,
        'current_page' => 'autocontent-sources'
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Source Create Form Error: " . $e->getMessage());
        echo "Error: " . $e->getMessage();
    }
});

/**
 * Source Create Handler
 * POST /admin/autocontent/sources/create
 */
$router->post('/admin/autocontent/sources/create', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    try {
        $model = new AutoContentModel($mysqli);

        $data = [
            'name' => $_POST['name'] ?? '',
            'url' => $_POST['url'] ?? '',
            'type' => $_POST['type'] ?? 'rss',
            'content_type' => $_POST['content_type'] ?? 'articles',
            'scrape_depth' => $_POST['scrape_depth'] ?? 1,
            'use_browser' => isset($_POST['use_browser']) ? 1 : 0,
            'category_id' => $_POST['category_id'] ?? null,
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
            'fetch_interval' => $_POST['fetch_interval'] ?? 3600,
            'max_pages' => $_POST['max_pages'] ?? 50,
            'delay' => $_POST['delay'] ?? 2,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        if (empty($data['name']) || empty($data['url'])) {
            // Preserve form input so the user doesn't lose their work on validation errors
            try {
                $sessionMgr = getSessionManager();
                $data['fetch_interval'] = (int)($data['fetch_interval'] / 60);
                $sessionMgr->set('autocontent_source_old', $data);
<<<<<<< HEAD
            } catch (Throwable $e) {
                // ignore session errors
=======
            }
            catch (Throwable $e) {
            // ignore session errors
>>>>>>> temp_branch
            }

            showMessage('Name and URL are required', 'error');
            header('Location: /admin/autocontent/sources/create');
            exit;
        }

        $id = $model->createSource($data);

        if ($id > 0) {
            showMessage('Source created successfully', 'success');
            logActivity("Auto Content Source Created", "autocontent", $id, ['name' => $data['name']], 'success');
<<<<<<< HEAD
        } else {
=======
        }
        else {
>>>>>>> temp_branch
            showMessage('Failed to create source', 'error');
        }

        header('Location: /admin/autocontent/sources');
        exit;
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Source Create Error: " . $e->getMessage());

        // Preserve form input on error so user doesn't lose entered values
        try {
            $sessionMgr = getSessionManager();
            $data['fetch_interval'] = (int)($data['fetch_interval'] / 60);
            $sessionMgr->set('autocontent_source_old', $data);
<<<<<<< HEAD
        } catch (Throwable $inner) {
            // ignore session errors
=======
        }
        catch (Throwable $inner) {
        // ignore session errors
>>>>>>> temp_branch
        }

        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autocontent/sources/create');
        exit;
    }
});

/**
 * Source Edit Form
 * GET /admin/autocontent/sources/edit
 */
$router->get('/admin/autocontent/sources/edit', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autocontent/sources');
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $source = $model->getSourceById($id);

        if (!$source) {
            showMessage('Source not found', 'error');
            header('Location: /admin/autocontent/sources');
            exit;
        }

        $categories = $model->getCategories();

        // Restore old form input values if present (after validation redirect)
        try {
            $sessionMgr = getSessionManager();
            $old = $sessionMgr->get('autocontent_source_old', null, 'array');
            if (!empty($old)) {
                $source = array_merge($source, $old);
                $sessionMgr->delete('autocontent_source_old');
            }
<<<<<<< HEAD
        } catch (Throwable $e) {
            // ignore session errors
        }

        echo $twig->render('admin/autocontent/source_form.twig', [
            'title' => 'Edit Article Source',
            'source' => $source,
            'categories' => $categories,
            'isCreate' => false,
            'current_page' => 'autocontent-sources'
        ]);
    } catch (Throwable $e) {
=======
        }
        catch (Throwable $e) {
        // ignore session errors
        }

        echo $twig->render('admin/autocontent/source_form.twig', [
        'title' => 'Edit Article Source',
        'source' => $source,
        'categories' => $categories,
        'isCreate' => false,
        'current_page' => 'autocontent-sources'
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Source Edit Form Error: " . $e->getMessage());
        echo "Error: " . $e->getMessage();
    }
});

/**
 * Source Edit Handler
 * POST /admin/autocontent/sources/edit
 */
$router->post('/admin/autocontent/sources/edit', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autocontent/sources');
            exit;
        }

        $model = new AutoContentModel($mysqli);

        $data = [
            'name' => $_POST['name'] ?? '',
            'url' => $_POST['url'] ?? '',
            'type' => $_POST['type'] ?? 'rss',
            'content_type' => $_POST['content_type'] ?? 'articles',
            'scrape_depth' => $_POST['scrape_depth'] ?? 1,
            'use_browser' => isset($_POST['use_browser']) ? 1 : 0,
            'category_id' => $_POST['category_id'] ?? null,
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
            'fetch_interval' => $_POST['fetch_interval'] ?? 3600,
            'max_pages' => $_POST['max_pages'] ?? 50,
            'delay' => $_POST['delay'] ?? 2,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        if (empty($data['name']) || empty($data['url'])) {
            // Preserve form input so the user doesn't lose their work on validation errors
            try {
                $sessionMgr = getSessionManager();
                $data['fetch_interval'] = (int)($data['fetch_interval'] / 60);
                $sessionMgr->set('autocontent_source_old', $data);
<<<<<<< HEAD
            } catch (Throwable $e) {
                // ignore session errors
=======
            }
            catch (Throwable $e) {
            // ignore session errors
>>>>>>> temp_branch
            }

            showMessage('Name and URL are required', 'error');
            header('Location: /admin/autocontent/sources/edit?id=' . $id);
            exit;
        }

        $success = $model->updateSource($id, $data);

        if ($success) {
            showMessage('Source updated successfully', 'success');
            logActivity("Auto Content Source Updated", "autocontent", $id, ['name' => $data['name']], 'success');
<<<<<<< HEAD
        } else {
=======
        }
        else {
>>>>>>> temp_branch
            showMessage('Failed to update source', 'error');
        }

        header('Location: /admin/autocontent/sources');
        exit;
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Source Edit Error: " . $e->getMessage());

        // Preserve form input on error so values are not lost
        try {
            $sessionMgr = getSessionManager();
            $data['fetch_interval'] = (int)($data['fetch_interval'] / 60);
            $sessionMgr->set('autocontent_source_old', $data);
<<<<<<< HEAD
        } catch (Throwable $inner) {
            // ignore session errors
=======
        }
        catch (Throwable $inner) {
        // ignore session errors
>>>>>>> temp_branch
        }

        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autocontent/sources/edit?id=' . ($_POST['id'] ?? ''));
        exit;
    }
});

/**
 * Delete Source
 * GET /admin/autocontent/sources/delete
 */
$router->get('/admin/autocontent/sources/delete', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autocontent/sources');
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $source = $model->getSourceById($id);

        $success = $model->deleteSource($id);

        if ($success) {
            showMessage('Source deleted successfully', 'success');
            logActivity("Auto Content Source Deleted", "autocontent", $id, ['name' => $source['name'] ?? ''], 'success');
<<<<<<< HEAD
        } else {
=======
        }
        else {
>>>>>>> temp_branch
            showMessage('Failed to delete source', 'error');
        }

        header('Location: /admin/autocontent/sources');
        exit;
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Source Delete Error: " . $e->getMessage());
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autocontent/sources');
        exit;
    }
});

/**
 * Toggle Source Status
 * GET /admin/autocontent/sources/toggle
 */
$router->get('/admin/autocontent/sources/toggle', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autocontent/sources');
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $model->toggleSourceStatus($id);

        header('Location: /admin/autocontent/sources');
        exit;
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Source Toggle Error: " . $e->getMessage());
        header('Location: /admin/autocontent/sources');
        exit;
    }
});

// ================== ARTICLE QUEUE ==================

/**
 * Article Queue
 * GET /admin/autocontent/queue
 */
$router->get('/admin/autocontent/queue', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
        $status = $_GET['status'] ?? '';
        $sourceFilter = $_GET['source'] ?? '';
        $search = $_GET['search'] ?? '';

        $model = new AutoContentModel($mysqli);
        $articles = $model->getArticles($page, $limit, $status, $sourceFilter, $search);
        $sources = $model->getAllSources();
        $statusCounts = $model->getArticleCountByStatus();

        // Calculate pagination
        $total = $statusCounts['total'];
        $totalPages = ceil($total / $limit);

        echo $twig->render('admin/autocontent/queue.twig', [
<<<<<<< HEAD
            'title' => 'Article Queue',
            'items' => $articles,
            'sources' => $sources,
            'status_counts' => $statusCounts,
            'current_status' => $status,
            'current_source' => $sourceFilter,
            'search' => $search,
            'current_page_num' => $page,
            'total_pages' => $totalPages,
            'pagination' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $limit,
                'total' => $total
            ],
            'current_page' => 'autocontent-queue'
        ]);
    } catch (Throwable $e) {
=======
        'title' => 'Article Queue',
        'items' => $articles,
        'sources' => $sources,
        'status_counts' => $statusCounts,
        'current_status' => $status,
        'current_source' => $sourceFilter,
        'search' => $search,
        'current_page_num' => $page,
        'total_pages' => $totalPages,
        'pagination' => [
        'page' => $page,
        'total_pages' => $totalPages,
        'per_page' => $limit,
        'total' => $total
        ],
        'current_page' => 'autocontent-queue'
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Queue Error: " . $e->getMessage());
        echo "Error loading queue: " . $e->getMessage();
    }
});

/**
 * View Article
 * GET /admin/autocontent/queue/view
 */
$router->get('/admin/autocontent/queue/view', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autocontent/queue');
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            showMessage('Article not found', 'error');
            header('Location: /admin/autocontent/queue');
            exit;
        }

        echo $twig->render('admin/autocontent/queue_view.twig', [
<<<<<<< HEAD
            'title' => 'View Article',
            'article' => $article,
            'current_page' => 'autocontent-queue'
        ]);
    } catch (Throwable $e) {
=======
        'title' => 'View Article',
        'article' => $article,
        'current_page' => 'autocontent-queue'
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Article View Error: " . $e->getMessage());
        echo "Error: " . $e->getMessage();
    }
});

// ================== SETTINGS ==================

/**
 * Settings Page
 * GET /admin/autocontent/settings
 */
$router->get('/admin/autocontent/settings', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new AutoContentModel($mysqli);
        $config = $model->getSettings();

        echo $twig->render('admin/autocontent/settings.twig', [
<<<<<<< HEAD
            'title' => 'AI Auto Content Settings',
            'config' => $config,
            'current_page' => 'autocontent-settings'
        ]);
    } catch (Throwable $e) {
=======
        'title' => 'AI Auto Content Settings',
        'config' => $config,
        'current_page' => 'autocontent-settings'
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Settings Error: " . $e->getMessage());
        echo "Error: " . $e->getMessage();
    }
});

/**
 * Settings Handler
 * POST /admin/autocontent/settings
 */
$router->post('/admin/autocontent/settings', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    try {
        $model = new AutoContentModel($mysqli);

        $settings = [
            'ai_endpoint' => $_POST['ai_endpoint'] ?? '',
            'ai_model' => $_POST['ai_model'] ?? 'gpt-4o-mini',
            'ai_key' => $_POST['ai_key'] ?? '',
            'autocontent_enabled' => isset($_POST['autocontent_enabled']) ? '1' : '0',
            'auto_collect' => isset($_POST['auto_collect']) ? '1' : '0',
            'auto_process' => isset($_POST['auto_process']) ? '1' : '0',
            'auto_publish' => isset($_POST['auto_publish']) ? '1' : '0',
            'max_articles_per_source' => $_POST['max_articles_per_source'] ?? '10',
            'process_batch' => $_POST['process_batch'] ?? '5',
            'publish_batch' => $_POST['publish_batch'] ?? '10',
            'max_retry_attempts' => $_POST['max_retry_attempts'] ?? '5',
            'max_daily_publish' => $_POST['max_daily_publish'] ?? '10',
            'publish_time_start' => $_POST['publish_time_start'] ?? '06:00',
            'publish_time_end' => $_POST['publish_time_end'] ?? '23:00',
            'publish_status' => $_POST['publish_status'] ?? 'published'
        ];

        // Handle custom model
        if (isset($_POST['ai_model_custom']) && !empty($_POST['ai_model_custom'])) {
            $settings['ai_model'] = $_POST['ai_model_custom'];
        }

        // Don't update key if empty (keep existing)
        if (empty($settings['ai_key'])) {
            unset($settings['ai_key']);
<<<<<<< HEAD
        } elseif ($settings['ai_key'] === ' ') {
=======
        }
        elseif ($settings['ai_key'] === ' ') {
>>>>>>> temp_branch
            // Clear key if single space
            $settings['ai_key'] = '';
        }

        $model->saveSettings($settings);

        showMessage('Settings saved successfully', 'success');
        logActivity("Auto Content Settings Updated", "autocontent", 0, [], 'success');

        header('Location: /admin/autocontent/settings');
        exit;
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Settings Save Error: " . $e->getMessage());
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autocontent/settings');
        exit;
    }
});

/**
 * Get Chart Stats (AJAX)
 * GET /admin/autocontent/stats/chart
 */
$router->get('/admin/autocontent/stats/chart', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;

        // Get daily article counts for the chart
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count
                FROM autocontent_articles
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();

        $labels = [];
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['date'];
            $data[] = (int)$row['count'];
        }

        echo json_encode([
<<<<<<< HEAD
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Articles Collected',
                'data' => $data,
                'borderColor' => '#6366f1',
                'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                'fill' => true,
                'tension' => 0.4
            ]]
        ]);
    } catch (Exception $e) {
=======
        'labels' => $labels,
        'datasets' => [[
        'label' => 'Articles Collected',
        'data' => $data,
        'borderColor' => '#6366f1',
        'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
        'fill' => true,
        'tension' => 0.4
        ]]
        ]);
    }
    catch (Exception $e) {
>>>>>>> temp_branch
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
});

// ================== API ENDPOINTS ==================

/**
 * Detect CSS Selectors using AI
 * POST /admin/autocontent/api/detect-selectors
 */
$router->post('/admin/autocontent/api/detect-selectors', [], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $url = $_POST['url'] ?? '';

        if (empty($url)) {
            echo json_encode(['success' => false, 'message' => 'URL is required']);
            exit;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
            exit;
        }

        // Fetch raw HTML directly using cURL
        $html = '';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_ENCODING => '',
        ]);
        $html = curl_exec($ch);
        curl_close($ch);

        if (empty($html)) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch URL content']);
            exit;
        }

        // Analyze HTML to guess selectors based on common patterns
        $selectors = analyzeHtmlStructure($html, $url);

        echo json_encode([
<<<<<<< HEAD
            'success' => true,
            'message' => 'Selectors detected successfully',
            'selectors' => $selectors
        ]);
        exit;
    } catch (Throwable $e) {
=======
        'success' => true,
        'message' => 'Selectors detected successfully',
        'selectors' => $selectors
        ]);
        exit;
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Detect Selectors Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
});

/**
 * AI-Powered Detect CSS Selectors using Puter AI
 * POST /admin/autocontent/api/ai-detect-selectors
 */
$router->post('/admin/autocontent/api/ai-detect-selectors', [], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $url = $_POST['url'] ?? '';

        if (empty($url)) {
            echo json_encode(['success' => false, 'message' => 'URL is required']);
            exit;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
            exit;
        }

        // Fetch raw HTML
        $html = '';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_ENCODING => '',
        ]);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (empty($html) || $httpCode !== 200) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch URL content (HTTP ' . $httpCode . ')']);
            exit;
        }

        // Use AI to detect selectors
        $selectors = detectSelectorsWithAI($html, $url);

        echo json_encode([
<<<<<<< HEAD
            'success' => true,
            'message' => 'AI selectors detected successfully',
            'selectors' => $selectors,
            'method' => 'ai'
        ]);
        exit;
    } catch (Throwable $e) {
=======
        'success' => true,
        'message' => 'AI selectors detected successfully',
        'selectors' => $selectors,
        'method' => 'ai'
        ]);
        exit;
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("AI Detect Selectors Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
});

/**
 * Use Puter AI to detect CSS selectors from HTML
 */
function detectSelectorsWithAI(string $html, string $url): array
{
    // Prepare HTML sample (limit to 30KB for API efficiency)
    $htmlSample = substr($html, 0, 30000);

    // Build prompt for AI
<<<<<<< HEAD
    $prompt = "Analyze this HTML from URL: {$url}\n\nHTML (first 30KB):\n{$htmlSample}\n\n";
    $prompt .= 'Return a JSON object with CSS selectors for web scraping. Use these exact field names: ';
    $prompt .= 'list_container, list_item, list_title, list_link, list_date, list_image, title, content, image, excerpt, date, author. ';
    $prompt .= 'Use class selectors (.class) not IDs (#id) for flexibility. Prefer semantic HTML5 (article, section, time). ';
    $prompt .= 'Look for card, item, post, article, story, news in class names.';
=======
    $prompt = <<<PROMPT
You are a web scraping expert. Analyze the first 30KB of HTML from URL: {$url} and identify the most accurate CSS selectors for the elements listed below.

HTML SNIPPET:
{$htmlSample}

REQUIRED SELECTORS (use these exact keys in JSON):
1. list_container: The main element wrapping all article items.
2. list_item: The repeated element for each article in the list.
3. list_title: The title element inside each list item.
4. list_link: The 'a' tag linking to the full article.
5. list_date: The date element in the list (if any).
6. list_image: The image element (img or container) in the list.
7. title: The main h1 heading on the article detail page.
8. content: The main body text/wrapper for the article.
9. image: The primary featured image on the detail page.
10. excerpt: A summary/lead paragraph if separate from content.
11. author: The author name element.
12. date: The publication date on the detail page.

RULES:
- Return ONLY a valid JSON object. No preamble, no explanation.
- Use stable class names (.class) or semantic tags (article, main, time).
- Prefer specific selectors that are unlikely to change.
- If a selector is a meta tag, use attribute notation like 'meta[property="og:image"]'.
- Ensure the selectors are relative to the parent where applicable (e.g. list_title should be relative to list_item).

RESPONSE FORMAT:
{
    "list_container": ".news-list",
    "list_item": "article.news-card",
    ...
}
PROMPT;
>>>>>>> temp_branch

    // Call Puter AI API
    $apiUrl = 'https://api.puter.com/ai/chat';

    $postData = json_encode([
<<<<<<< HEAD
        'model' => 'gpt-4.1-mini',
=======
        'model' => 'gpt-4o-mini',
>>>>>>> temp_branch
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || empty($response)) {
        // Fallback to pattern-based detection
        error_log("AI detection failed, falling back to pattern detection: " . $error);
        return analyzeHtmlStructure($html, $url);
    }

    $data = json_decode($response, true);

    // Extract content from AI response
    $content = '';
    if (isset($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
<<<<<<< HEAD
    } elseif (isset($data['text'])) {
=======
    }
    elseif (isset($data['text'])) {
>>>>>>> temp_branch
        $content = $data['text'];
    }

    if (empty($content)) {
        return analyzeHtmlStructure($html, $url);
    }

    // Parse JSON from response
    $selectors = parseAISelectorResponse($content);

    // Validate and merge with pattern-based detection as backup
    $patternSelectors = analyzeHtmlStructure($html, $url);

    foreach ($patternSelectors as $key => $value) {
        if (empty($selectors[$key]) && !empty($value)) {
            $selectors[$key] = $value;
        }
    }

    return $selectors;
}

/**
 * Parse AI selector response
 */
function parseAISelectorResponse(string $response): array
{
    $default = [
        'list_container' => '',
        'list_item' => '',
        'list_title' => '',
        'list_link' => '',
        'list_date' => '',
        'list_image' => '',
        'title' => '',
        'content' => '',
        'image' => '',
        'excerpt' => '',
        'date' => '',
        'author' => ''
    ];

    try {
        // Try to find JSON in the response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (is_array($parsed)) {
                return array_merge($default, $parsed);
            }
        }
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Parse AI selector error: " . $e->getMessage());
    }

    return $default;
}

/**
 * Analyze HTML structure to guess CSS selectors
 */
function analyzeHtmlStructure(string $html, string $url): array
{
    $selectors = [
        'list_container' => '',
        'list_item' => '',
        'list_title' => '',
        'list_date' => '',
        'title' => '',
        'content' => '',
        'image' => '',
        'excerpt' => '',
        'date' => '',
        'author' => ''
    ];

    if (empty($html)) {
        return $selectors;
    }

    // Check for known Bengali news sites
    if (strpos($url, 'prothomalo.com') !== false) {
        // List page selectors for Prothom Alo
        $selectors['list_container'] = 'body'; // The whole page contains articles
        $selectors['list_item'] = '.wide-story-card, .news_with_item';
        $selectors['list_title'] = 'h3.headline-title a.title-link';
        $selectors['list_date'] = 'time.published-at, time.published-time';

        // Article page selectors for Prothom Alo
        $selectors['title'] = 'h1.IiRps, h1[data-title-0]';
        $selectors['content'] = '.story-element.story-element-text';
        $selectors['image'] = 'meta[property="og:image"]';
        $selectors['excerpt'] = 'meta[name="description"]';
        $selectors['date'] = 'time[datetime]';
        $selectors['author'] = '.author-name, .contributor-name';
        return $selectors;
    }

    // BD News 24 Bengali
    if (strpos($url, 'bangla.bdnews24.com') !== false) {
        // List page selectors for BD News 24
        $selectors['list_container'] = '#data-wrapper';
        $selectors['list_item'] = '.SubCat-wrapper, .col-md-3';
        $selectors['list_title'] = 'h5 a, .SubcatList-detail h5 a';
        $selectors['list_date'] = '.publish-time, span.publish-time';

        // Article page selectors for BD News 24
        $selectors['title'] = '.details-title h1, h1';
        $selectors['content'] = '#contentDetails, .details-brief';
        $selectors['image'] = '.details-img img, .details-img picture img';
        $selectors['excerpt'] = '.details-title h2, h2.shoulder-text';
        $selectors['date'] = '.pub-up .pub, .pub-up span:first-child';
        $selectors['author'] = '.author-name-wrap .author, .detail-author-name .author';
        return $selectors;
    }

    if (strpos($url, 'bbc.com') !== false) {
        $selectors['list_container'] = '.content--list';
        $selectors['list_item'] = '.media-list__item';
        $selectors['list_title'] = 'h3 a, .media__title a';
        $selectors['list_date'] = 'time';

        $selectors['title'] = 'h1';
        $selectors['content'] = 'article';
        return $selectors;
    }

    // General detection for unknown sites
    try {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Detect List Page Selectors
        // Look for containers with multiple similar items
        $listContainerCandidates = [
            '//div[contains(@class, "list") or contains(@class, "feed") or contains(@class, "articles")]',
            '//section[contains(@class, "list") or contains(@class, "feed")]',
            '//ul[contains(@class, "list") or contains(@class, "articles")]',
            '//main',
            '//body'
        ];
        $selectors['list_container'] = findFirstWorkingSelector($xpath, $listContainerCandidates);

        // Look for article items within containers
        $listItemCandidates = [
            './/article',
            './/div[contains(@class, "item") or contains(@class, "post") or contains(@class, "article") or contains(@class, "story") or contains(@class, "news")]',
            './/li[contains(@class, "item") or contains(@class, "post")]',
            './/div[contains(@class, "card") or contains(@class, "block")]'
        ];
        $selectors['list_item'] = findFirstWorkingSelector($xpath, $listItemCandidates);

        // Look for title links within items
        $listTitleCandidates = [
            './/h1//a',
            './/h2//a',
            './/h3//a',
            './/h4//a',
            './/a[contains(@class, "title") or contains(@class, "headline") or contains(@href, "article") or contains(@href, "post")]',
            './/a[string-length(text()) > 10]' // Links with substantial text
        ];
        $selectors['list_title'] = findFirstWorkingSelector($xpath, $listTitleCandidates);

        // Look for dates in list
        $listDateCandidates = [
            './/time',
            './/span[contains(@class, "date") or contains(@class, "time") or contains(@class, "published")]',
            './/div[contains(@class, "date") or contains(@class, "time")]'
        ];
        $selectors['list_date'] = findFirstWorkingSelector($xpath, $listDateCandidates);

        // Detect Article Page Selectors - expanded list
        $titleCandidates = [
            '//h1',
            '//h1[contains(@class, "title")]',
            '//h1[contains(@class, "headline")]',
            '//article//h1',
            '//div[contains(@class, "headline")]/h1',
            '//div[contains(@class, "post")]/h1',
            '//div[contains(@class, "article")]/h1',
            '//div[contains(@class, "story")]/h1',
            '//meta[@property="og:title"]/@content',
            '//meta[@name="twitter:title"]/@content',
            '//head/title'
        ];
        $selectors['title'] = findFirstWorkingSelector($xpath, $titleCandidates);

        // Detect Content Selector - expanded list
        $contentCandidates = [
            '//article',
            '//article[contains(@class, "content")]',
            '//article[contains(@class, "article")]',
            '//div[contains(@class, "content")]',
            '//div[contains(@class, "post-content")]',
            '//div[contains(@class, "entry-content")]',
            '//div[contains(@class, "article-content")]',
            '//div[contains(@class, "story-content")]',
            '//div[contains(@class, "post-body")]',
            '//main',
            '//main[contains(@class, "content")]',
            '//section[contains(@class, "content")]'
        ];
        $selectors['content'] = findFirstWorkingSelector($xpath, $contentCandidates);

        // Detect Image Selector - expanded list
        $imageCandidates = [
            '//meta[@property="og:image"]/@content',
            '//meta[@property="og:image:url"]/@content',
            '//meta[@name="twitter:image"]/@content',
            '//meta[@name="twitter:image:src"]/@content',
            '//article//img[contains(@class, "featured")]/@src',
            '//article//img[contains(@class, "thumbnail")]/@src',
            '//article//img[1]/@src',
            '//div[contains(@class, "featured")]/img/@src',
            '//div[contains(@class, "thumbnail")]/img/@src',
            '//div[contains(@class, "story")]/img/@src'
        ];
        $selectors['image'] = findFirstWorkingSelector($xpath, $imageCandidates);

        // Detect Excerpt Selector
        $excerptCandidates = [
            '//meta[@name="description"]/@content',
            '//meta[@property="og:description"]/@content',
            '//div[contains(@class, "excerpt")]',
            '//div[contains(@class, "summary")]',
            '//div[contains(@class, "description")]'
        ];
        $selectors['excerpt'] = findFirstWorkingSelector($xpath, $excerptCandidates);

        // Detect Date Selector - expanded list
        $dateCandidates = [
            '//meta[@property="article:published_time"]/@content',
            '//meta[@property="og:updated_time"]/@content',
            '//meta[@name="date"]/@content',
            '//meta[@name="pubdate"]/@content',
            '//time/@datetime',
            '//time[contains(@class, "published")]',
            '//time[contains(@class, "date")]',
            '//span[contains(@class, "published")]',
            '//span[contains(@class, "date")]',
            '//span[contains(@class, "timestamp")]',
            '//span[contains(@class, "time")]',
            '//div[contains(@class, "published")]',
            '//div[contains(@class, "date")]'
        ];
        $selectors['date'] = findFirstWorkingSelector($xpath, $dateCandidates);

        // Detect Author Selector - expanded list
        $authorCandidates = [
            '//meta[@name="author"]/@content',
            '//meta[@property="article:author"]/@content',
            '//span[contains(@class, "author")]',
            '//span[contains(@class, "byline")]',
            '//span[contains(@class, "writer")]',
            '//a[contains(@class, "author")]',
            '//a[contains(@class, "byline")]',
            '//div[contains(@class, "author")]',
            '//div[contains(@class, "byline")]'
        ];
        $selectors['author'] = findFirstWorkingSelector($xpath, $authorCandidates);
<<<<<<< HEAD
    } catch (\Exception $e) {
=======
    }
    catch (\Exception $e) {
>>>>>>> temp_branch
        error_log("HTML Analysis Error: " . $e->getMessage());
    }

    return $selectors;
}

/**
 * Find the first XPath that returns results
 */
function findFirstWorkingSelector(\DOMXPath $xpath, array $candidates): string
{
    foreach ($candidates as $xpathQuery) {
        try {
            $nodes = $xpath->query($xpathQuery);
            if ($nodes && $nodes->length > 0) {
                $node = $nodes->item(0);
                if ($node && !empty(trim($node->nodeValue ?? $node->textContent ?? ''))) {
                    return $xpathQuery;
                }
            }
<<<<<<< HEAD
        } catch (\Exception $e) {
=======
        }
        catch (\Exception $e) {
>>>>>>> temp_branch
            continue;
        }
    }
    return '';
}

/**
 * Collect Articles from Single Source (Scrape)
 * GET /admin/autocontent/api/collect-single
 */
$router->get('/admin/autocontent/api/collect-single', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $sourceId = (int)($_GET['source_id'] ?? 0);

        if ($sourceId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid source ID']);
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $source = $model->getSourceById($sourceId);

        if (!$source) {
            echo json_encode(['success' => false, 'message' => 'Source not found']);
            exit;
        }

        $settings = $model->getSettings();
        $maxPerSource = (int)($settings['max_articles_per_source'] ?? 10);

        require_once __DIR__ . '/../Modules/Scraper/ScraperService.php';
        require_once __DIR__ . '/../Modules/Scraper/ProthomAloScraperService.php';
        require_once __DIR__ . '/../Modules/Scraper/EnhancedScraperService.php';
        require_once __DIR__ . '/../Modules/Scraper/ContentCleanerService.php';
        require_once __DIR__ . '/../Modules/Scraper/DuplicateCheckerService.php';
        require_once __DIR__ . '/../Modules/Scraper/ImageDownloaderService.php';

        $scraper = new ScraperService();
        $enhancedScraper = new EnhancedScraperService();
        $contentCleaner = new ContentCleanerService();
        $duplicateChecker = new DuplicateCheckerService($mysqli);
        $imageDownloader = new ImageDownloaderService();
        $prothomAloScraper = new ProthomAloScraperService(
            $enhancedScraper,
            $contentCleaner,
            $duplicateChecker,
            $imageDownloader,
            $mysqli
<<<<<<< HEAD
        );
=======
            );
>>>>>>> temp_branch

        $collected = 0;

        // Check if it's Prothom Alo
        if (stripos($source['url'] ?? '', 'prothomalo.com') !== false) {
            $prothomResult = $prothomAloScraper->scrapeHomepage($source['url']);

            if ($prothomResult['success']) {
                $collected = $prothomResult['articles_saved'] ?? 0;
            }
<<<<<<< HEAD
        } else {
=======
        }
        else {
>>>>>>> temp_branch
            // For other sources, use basic scraper
            $result = $scraper->scrape($source['url']);

            if (!isset($result) || !is_array($result) || isset($result['error'])) {
                echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Failed to scrape']);
                exit;
            }

            $title = trim($result['title'] ?? '');
            if (empty($title) || $title === '(No title found)') {
                echo json_encode(['success' => false, 'message' => 'No valid content found']);
                exit;
            }

            $articleUrl = $result['url'] ?? '';
            if (!empty($articleUrl) && !$model->articleUrlExists($articleUrl, $sourceId)) {
                $articleData = [
                    'source_id' => $sourceId,
                    'title' => $title,
                    'url' => $articleUrl,
                    'content' => $result['description'] ?? '',
                    'excerpt' => substr($result['description'] ?? '', 0, 200),
                    'image_url' => $result['image'] ?? '',
                    'author' => '',
                    'published_at' => date('Y-m-d H:i:s'),
                    'status' => 'collected'
                ];

                $model->createArticle($articleData);
                $collected++;
            }

            // Follow links from the scraped page
            if (!empty($result['links'])) {
                foreach (array_slice($result['links'], 0, $maxPerSource) as $link) {
                    if (!$model->articleUrlExists($link, $sourceId)) {
                        $linkResult = $scraper->scrape($link);

                        if (isset($linkResult['error'])) {
                            continue;
                        }

                        $linkTitle = trim($linkResult['title'] ?? '');
                        if (empty($linkTitle) || $linkTitle === '(No title found)') {
                            continue;
                        }

                        $articleData = [
                            'source_id' => $sourceId,
                            'title' => $linkTitle,
                            'url' => $link,
                            'content' => $linkResult['description'] ?? '',
                            'excerpt' => substr($linkResult['description'] ?? '', 0, 200),
                            'image_url' => $linkResult['image'] ?? '',
                            'author' => '',
                            'published_at' => date('Y-m-d H:i:s'),
                            'status' => 'collected'
                        ];

                        $model->createArticle($articleData);
                        $collected++;
                    }
                }
            }
        }

        // Update last fetched time
        $model->updateLastFetched($sourceId);

        echo json_encode([
<<<<<<< HEAD
            'success' => true,
            'collected' => $collected,
            'message' => "Collected {$collected} article(s) from {$source['name']}"
        ]);
    } catch (Throwable $e) {
=======
        'success' => true,
        'collected' => $collected,
        'message' => "Collected {$collected} article(s) from {$source['name']}"
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Collect Single API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Collect Articles from Single Source using Multi-Layer Scraper (5-Step Pipeline)
 * GET /admin/autocontent/api/collect-multi
 * 
 * STEP 1: Fetch LIST PAGE
 * STEP 2: Extract ARTICLE LINKS  
 * STEP 3: Loop article links
 * STEP 4: Fetch ARTICLE PAGE
 * STEP 5: Extract title/content/image/date
 */
$router->get('/admin/autocontent/api/collect-multi', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $sourceId = (int)($_GET['source_id'] ?? 0);
        $delay = (int)($_GET['delay'] ?? 2);
        $maxArticles = (int)($_GET['max'] ?? 10);

        if ($sourceId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid source ID']);
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $source = $model->getSourceById($sourceId);

        if (!$source) {
            echo json_encode(['success' => false, 'message' => 'Source not found']);
            exit;
        }

        // Check if source has selectors configured
        if (empty($source['selector_list_item']) && empty($source['selector_list_title'])) {
            echo json_encode([
<<<<<<< HEAD
                'success' => false,
                'message' => 'Source needs CSS selectors configured for multi-layer scraping. Please set list item selector or list link selector in source settings.'
=======
            'success' => false,
            'message' => 'Source needs CSS selectors configured for multi-layer scraping. Please set list item selector or list link selector in source settings.'
>>>>>>> temp_branch
            ]);
            exit;
        }

        // Use the MultiLayerScraperService
        $scraper = new MultiLayerScraperService($mysqli);
        $scraper->setRequestDelay($delay)
            ->setMaxArticles($maxArticles)
            ->setDebug(true);

        $result = $scraper->runPipeline($source);
        $status = $scraper->getPipelineStatus();

        // Update last fetched time
        if ($result['articles_collected'] > 0) {
            $model->updateLastFetched($sourceId);
        }

        echo json_encode([
<<<<<<< HEAD
            'success' => $result['success'],
            'collected' => $result['articles_collected'],
            'message' => "Multi-layer scrape: {$result['articles_collected']} article(s) from {$source['name']}",
            'pipeline' => [
                'steps_completed' => $result['steps_completed'],
                'status' => $status,
                'total_links_found' => $status['total_links_found'],
                'articles_collected' => $status['articles_collected']
            ],
            'errors' => $result['errors'],
            'warnings' => $result['warnings']
        ]);
    } catch (Throwable $e) {
=======
        'success' => $result['success'],
        'collected' => $result['articles_collected'],
        'message' => "Multi-layer scrape: {$result['articles_collected']} article(s) from {$source['name']}",
        'pipeline' => [
        'steps_completed' => $result['steps_completed'],
        'status' => $status,
        'total_links_found' => $status['total_links_found'],
        'articles_collected' => $status['articles_collected']
        ],
        'errors' => $result['errors'],
        'warnings' => $result['warnings']
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Multi-Layer Collect API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Collect Articles (Scrape)
 * POST /admin/autocontent/api/collect
 */
$router->post('/admin/autocontent/api/collect', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $model = new AutoContentModel($mysqli);
        $settings = $model->getSettings();

        // Get active sources
        $sources = $model->getActiveSources();

        if (empty($sources)) {
            echo json_encode(['success' => false, 'message' => 'No active sources found. Please add and activate at least one source.']);
            exit;
        }

        $collected = 0;
        $maxPerSource = (int)($settings['max_articles_per_source'] ?? 10);
        $sourceResults = []; // Track results for each source

        require_once __DIR__ . '/../Modules/Scraper/ScraperService.php';
        require_once __DIR__ . '/../Modules/Scraper/ProthomAloScraperService.php';
        require_once __DIR__ . '/../Modules/Scraper/EnhancedScraperService.php';
        require_once __DIR__ . '/../Modules/Scraper/ContentCleanerService.php';
        require_once __DIR__ . '/../Modules/Scraper/DuplicateCheckerService.php';
        require_once __DIR__ . '/../Modules/Scraper/ImageDownloaderService.php';

        $scraper = new ScraperService();
        $enhancedScraper = new EnhancedScraperService();
        $contentCleaner = new ContentCleanerService();
        $duplicateChecker = new DuplicateCheckerService($mysqli);
        $imageDownloader = new ImageDownloaderService();
        $prothomAloScraper = new ProthomAloScraperService(
            $enhancedScraper,
            $contentCleaner,
            $duplicateChecker,
            $imageDownloader,
            $mysqli
<<<<<<< HEAD
        );
=======
            );
>>>>>>> temp_branch

        foreach ($sources as $source) {
            $sourceResult = [
                'source_id' => $source['id'],
                'source_name' => $source['name'] ?? 'Unknown',
                'source_url' => $source['url'] ?? '',
                'status' => 'pending',
                'error' => null,
                'articles_collected' => 0
            ];

            try {
                // Check if it's Prothom Alo
                if (stripos($source['url'] ?? '', 'prothomalo.com') !== false) {
                    // Use specialized Prothom Alo scraper (it handles saving internally)
                    $prothomResult = $prothomAloScraper->scrapeHomepage($source['url']);

                    if ($prothomResult['success']) {
                        $collected += $prothomResult['articles_saved'] ?? 0;
                        $sourceResult['articles_collected'] = $prothomResult['articles_saved'] ?? 0;
                        $sourceResult['status'] = 'success';
                        if (!empty($prothomResult['errors'])) {
                            $sourceResult['error'] = implode('; ', array_slice($prothomResult['errors'], 0, 3));
                        }
<<<<<<< HEAD
                    } else {
=======
                    }
                    else {
>>>>>>> temp_branch
                        $sourceResult['status'] = 'error';
                        $sourceResult['error'] = $prothomResult['errors'][0] ?? 'Failed to scrape Prothom Alo';
                    }
                    $sourceResults[] = $sourceResult;
                    $model->updateLastFetched((int)$source['id']);
                    continue;
                }

                // For other sources, use basic scraper
                $result = $scraper->scrape($source['url']);

                // Validate the result - check for errors first
                if (!isset($result) || !is_array($result)) {
                    $sourceResult['status'] = 'error';
                    $sourceResult['error'] = 'Scraper returned invalid result';
                    $sourceResults[] = $sourceResult;
                    error_log("Auto Content: Invalid scraper result for source {$source['name']}");
                    continue;
                }

                if (isset($result['error'])) {
                    $sourceResult['status'] = 'error';
                    $sourceResult['error'] = $result['error'];
                    $sourceResults[] = $sourceResult;
                    error_log("Auto Content: Error scraping source {$source['name']}: " . $result['error']);
                    continue;
                }

                // Validate title exists and is not empty or placeholder
                $title = trim($result['title'] ?? '');
                if (empty($title) || $title === '(No title found)' || $title === '(No description found)') {
                    $sourceResult['status'] = 'warning';
                    $sourceResult['error'] = 'No valid title found in scraped content. URL: ' . ($result['url'] ?? 'unknown');
                    $sourceResults[] = $sourceResult;
                    continue;
                }

                // Validate URL
                $articleUrl = $result['url'] ?? '';
                if (empty($articleUrl)) {
                    $sourceResult['status'] = 'warning';
                    $sourceResult['error'] = 'No valid URL found in scraped content';
                    $sourceResults[] = $sourceResult;
                    continue;
                }

                // Create article from scraped data
                if (!$model->articleUrlExists($articleUrl, (int)$source['id'])) {
                    $articleData = [
                        'source_id' => $source['id'],
                        'title' => $title,
                        'url' => $articleUrl,
                        'content' => $result['description'] ?? '',
                        'excerpt' => substr($result['description'] ?? '', 0, 200),
                        'image_url' => $result['image'] ?? '',
                        'author' => '',
                        'published_at' => date('Y-m-d H:i:s'),
                        'status' => 'collected'
                    ];

                    $model->createArticle($articleData);
                    $collected++;
                    $sourceResult['articles_collected']++;
                }

                // Update last fetched time
                $model->updateLastFetched((int)$source['id']);

                // Also try to follow links from the scraped page
                if (!empty($result['links'])) {
                    foreach (array_slice($result['links'], 0, $maxPerSource) as $link) {
                        if (!$model->articleUrlExists($link, (int)$source['id'])) {
                            $linkResult = $scraper->scrape($link);

                            // Validate link result
                            if (isset($linkResult['error'])) {
                                continue;
                            }

                            $linkTitle = trim($linkResult['title'] ?? '');
                            if (empty($linkTitle) || $linkTitle === '(No title found)') {
                                continue;
                            }

                            $articleData = [
                                'source_id' => $source['id'],
                                'title' => $linkTitle,
                                'url' => $link,
                                'content' => $linkResult['description'] ?? '',
                                'excerpt' => substr($linkResult['description'] ?? '', 0, 200),
                                'image_url' => $linkResult['image'] ?? '',
                                'author' => '',
                                'published_at' => date('Y-m-d H:i:s'),
                                'status' => 'collected'
                            ];

                            $model->createArticle($articleData);
                            $collected++;
                            $sourceResult['articles_collected']++;
                        }
                    }
                }

                $sourceResult['status'] = 'success';
                $sourceResults[] = $sourceResult;
<<<<<<< HEAD
            } catch (Exception $e) {
=======
            }
            catch (Exception $e) {
>>>>>>> temp_branch
                $sourceResult['status'] = 'error';
                $sourceResult['error'] = $e->getMessage();
                $sourceResults[] = $sourceResult;
                error_log("Error scraping source {$source['id']}: " . $e->getMessage());
                continue;
            }
        }

        // Build detailed message
        $errors = array_filter($sourceResults, fn($r) => $r['status'] === 'error');
        $warnings = array_filter($sourceResults, fn($r) => $r['status'] === 'warning');

        $message = "Collected {$collected} articles from " . count($sources) . " source(s)";
        if (!empty($errors)) {
            $message .= ". " . count($errors) . " source(s) had errors";
        }
        if (!empty($warnings)) {
            $message .= ". " . count($warnings) . " source(s) had warnings";
        }

        echo json_encode([
<<<<<<< HEAD
            'success' => $collected > 0 || empty($errors),
            'collected' => $collected,
            'message' => $message,
            'source_results' => $sourceResults,
            'debug' => [
                'total_sources' => count($sources),
                'errors_count' => count($errors),
                'warnings_count' => count($warnings)
            ]
        ]);
    } catch (Throwable $e) {
=======
        'success' => $collected > 0 || empty($errors),
        'collected' => $collected,
        'message' => $message,
        'source_results' => $sourceResults,
        'debug' => [
        'total_sources' => count($sources),
        'errors_count' => count($errors),
        'warnings_count' => count($warnings)
        ]
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Collect API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Process Articles with AI
 * POST /admin/autocontent/api/process
 */
$router->post('/admin/autocontent/api/process', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        // Use AI Enhancer for proper AI processing
        $enhancer = new \App\Modules\AutoContent\AiContentEnhancer($mysqli);

        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 5;
        $result = $enhancer->processBatch($limit);

        echo json_encode($result);
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Process API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Process Single Article with AI
 * POST /admin/autocontent/api/process-single
 */
$router->post('/admin/autocontent/api/process-single', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid article ID']);
            exit;
        }

        // Use AI Enhancer for single article
        $enhancer = new \App\Modules\AutoContent\AiContentEnhancer($mysqli);
        $result = $enhancer->processArticle($id);

        echo json_encode($result);
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Process Single API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Publish Articles
 * POST /admin/autocontent/api/publish
 */
$router->post('/admin/autocontent/api/publish', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $model = new AutoContentModel($mysqli);
        $settings = $model->getSettings();

        // Get processed articles
        $articles = $model->getArticlesByStatus('processed', 10);

        if (empty($articles)) {
            echo json_encode(['success' => false, 'message' => 'No processed articles to publish']);
            exit;
        }

        $contentModel = new ContentModel($mysqli);
        $published = 0;

        foreach ($articles as $article) {
            try {
                // Use AI enhanced content if available, otherwise use original
                $title = !empty($article['ai_title']) ? $article['ai_title'] : ($article['title'] ?? 'Untitled');
                $content = !empty($article['ai_content']) ? $article['ai_content'] : ($article['content'] ?? '');

                // Create a post from the article
                $slug = sanitize_input(slugify($title));
                $postId = $contentModel->createPost(
                    $title,
                    $content,
                    'AI Bot', // author
                    $slug,
                    1, // published
                    1 // reader_indexing
                );

                if ($postId > 0) {
                    $model->updateArticleStatus($article['id'], 'published');
                    $published++;
<<<<<<< HEAD
                } else {
                    $model->updateArticleStatus($article['id'], 'failed');
                }
            } catch (Exception $e) {
=======
                }
                else {
                    $model->updateArticleStatus($article['id'], 'failed');
                }
            }
            catch (Exception $e) {
>>>>>>> temp_branch
                error_log("Error publishing article {$article['id']}: " . $e->getMessage());
                $model->updateArticleStatus($article['id'], 'failed');
                continue;
            }
        }

        echo json_encode([
<<<<<<< HEAD
            'success' => true,
            'published' => $published,
            'message' => "Published {$published} articles"
        ]);
    } catch (Throwable $e) {
=======
        'success' => true,
        'published' => $published,
        'message' => "Published {$published} articles"
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Publish API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Run Full Pipeline
 * POST /admin/autocontent/api/run-pipeline
 */
$router->post('/admin/autocontent/api/run-pipeline', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $model = new AutoContentModel($mysqli);

        $result = [
            'collected' => 0,
            'processed' => 0,
            'published' => 0
        ];

        // Step 1: Collect
        $settings = $model->getSettings();
        $sources = $model->getActiveSources();

        if (!empty($sources)) {
            require_once __DIR__ . '/../Modules/Scraper/ScraperService.php';
            $scraper = new ScraperService();
            $maxPerSource = (int)($settings['max_articles_per_source'] ?? 10);

            foreach ($sources as $source) {
                try {
                    $scrapeResult = $scraper->scrape($source['url']);

                    if (!isset($scrapeResult['error']) && !empty($scrapeResult['title'])) {
                        if (!$model->articleUrlExists($scrapeResult['url'], (int)$source['id'])) {
                            // Ensure UTF-8 encoding for Bengali/Unicode characters
                            $title = mb_convert_encoding($scrapeResult['title'], 'UTF-8', 'UTF-8');
                            $content = mb_convert_encoding($scrapeResult['description'] ?? '', 'UTF-8', 'UTF-8');
                            $excerpt = mb_convert_encoding(mb_substr($scrapeResult['description'] ?? '', 0, 200), 'UTF-8', 'UTF-8');

                            $articleData = [
                                'source_id' => $source['id'],
                                'title' => $title,
                                'url' => $scrapeResult['url'],
                                'content' => $content,
                                'excerpt' => $excerpt,
                                'image_url' => $scrapeResult['image'] ?? '',
                                'author' => '',
                                'published_at' => date('Y-m-d H:i:s'),
                                'status' => 'collected'
                            ];

                            $model->createArticle($articleData);
                            $result['collected']++;
                        }
                    }

                    $model->updateLastFetched((int)$source['id']);
<<<<<<< HEAD
                } catch (Exception $e) {
=======
                }
                catch (Exception $e) {
>>>>>>> temp_branch
                    error_log("Pipeline collect error for source {$source['id']}: " . $e->getMessage());
                    continue;
                }
            }
        }

        // Step 2: Process with AI
        $enhancer = new \App\Modules\AutoContent\AiContentEnhancer($mysqli);
        $processResult = $enhancer->processBatch(5);
        $result['processed'] = $processResult['processed'];

        // Step 3: Publish
        $processedArticles = $model->getArticlesByStatus('processed', 10);
        $contentModel = new ContentModel($mysqli);

        foreach ($processedArticles as $article) {
            $postId = $contentModel->createPost(
                $article['title'],
                $article['content'],
                1,
                $article['excerpt'] ?? '',
                $article['image_url'] ?? ''
            );

            if ($postId > 0) {
                $model->updateArticleStatus($article['id'], 'published');
                $result['published']++;
            }
        }

        echo json_encode([
<<<<<<< HEAD
            'success' => true,
            'result' => $result,
            'message' => "Pipeline complete: {$result['collected']} collected, {$result['processed']} processed, {$result['published']} published"
        ]);
    } catch (Throwable $e) {
=======
        'success' => true,
        'result' => $result,
        'message' => "Pipeline complete: {$result['collected']} collected, {$result['processed']} processed, {$result['published']} published"
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Pipeline API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Retry Failed Articles
 * POST /admin/autocontent/api/retry
 */
$router->post('/admin/autocontent/api/retry', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        // Use AI Enhancer for retry
        $enhancer = new \App\Modules\AutoContent\AiContentEnhancer($mysqli);

        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $result = $enhancer->retryFailed($limit);

        echo json_encode($result);
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Retry API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Delete Article from Queue
 * POST /admin/autocontent/queue/delete
 */
$router->post('/admin/autocontent/queue/delete', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $isAjax = isset($_GET['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']);

    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid article ID']);
                exit;
            }
            header('Location: /admin/autocontent/queue');
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $model->deleteArticle($id);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Article deleted successfully']);
            exit;
        }

        showMessage('Article deleted successfully', 'success');
        header('Location: /admin/autocontent/queue');
        exit;
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Article Delete Error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autocontent/queue');
        exit;
    }
});

/**
 * Publish Single Article
 * POST /admin/autocontent/queue/publish
 */
$router->post('/admin/autocontent/queue/publish', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autocontent/queue');
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            showMessage('Article not found', 'error');
            header('Location: /admin/autocontent/queue');
            exit;
        }

        $contentModel = new ContentModel($mysqli);
        $postId = $contentModel->createPost(
            $article['title'],
            $article['content'],
            1,
            $article['excerpt'] ?? '',
            $article['image_url'] ?? ''
        );

        if ($postId > 0) {
            $model->updateArticleStatus($id, 'published');
            showMessage('Article published successfully', 'success');
            logActivity("Auto Content Article Published", "autocontent", $id, ['title' => $article['title']], 'success');
<<<<<<< HEAD
        } else {
=======
        }
        else {
>>>>>>> temp_branch
            showMessage('Failed to publish article', 'error');
        }

        header('Location: /admin/autocontent/queue');
        exit;
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Article Publish Error: " . $e->getMessage());
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autocontent/queue');
        exit;
    }
});

/**
 * Approve Article (Set to approved status)
 * POST /admin/autocontent/queue/approve
 */
$router->post('/admin/autocontent/queue/approve', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $isAjax = isset($_GET['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']);

    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid article ID']);
                exit;
            }
            showMessage('Invalid article ID', 'error');
            header('Location: /admin/autocontent/queue');
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Article not found']);
                exit;
            }
            showMessage('Article not found', 'error');
            header('Location: /admin/autocontent/queue');
            exit;
        }

        $model->updateArticleStatus($id, 'approved');

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Article approved successfully']);
            exit;
        }

        showMessage('Article approved successfully', 'success');
        logActivity("Auto Content Article Approved", "autocontent", $id, ['title' => $article['title']], 'success');

        header('Location: /admin/autocontent/queue/view?id=' . $id);
        exit;
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Article Approve Error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autocontent/queue');
        exit;
    }
});

/**
 * Reject Article (Set to failed status)
 * POST /admin/autocontent/queue/reject
 */
$router->post('/admin/autocontent/queue/reject', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $isAjax = isset($_GET['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']);

    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid article ID']);
                exit;
            }
            showMessage('Invalid article ID', 'error');
            header('Location: /admin/autocontent/queue');
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Article not found']);
                exit;
            }
            showMessage('Article not found', 'error');
            header('Location: /admin/autocontent/queue');
            exit;
        }

        $model->updateArticleStatus($id, 'failed');

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Article rejected']);
            exit;
        }

        showMessage('Article rejected', 'success');
        logActivity("Auto Content Article Rejected", "autocontent", $id, ['title' => $article['title']], 'success');

        header('Location: /admin/autocontent/queue');
        exit;
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Article Reject Error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autocontent/queue');
        exit;
    }
});

/**
 * Edit Article in Queue
 * POST /admin/autocontent/queue/edit
 */
$router->post('/admin/autocontent/queue/edit', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $isAjax = isset($_GET['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']);

    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid article ID']);
                exit;
            }
            showMessage('Invalid article ID', 'error');
            header('Location: /admin/autocontent/queue');
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Article not found']);
                exit;
            }
            showMessage('Article not found', 'error');
            header('Location: /admin/autocontent/queue');
            exit;
        }

        // Update article content
        $processedContent = $_POST['ai_content'] ?? $article['content'] ?? '';
        $aiSummary = $_POST['ai_summary'] ?? '';

        $model->updateArticleContent($id, $processedContent, $aiSummary);

        // Update title if provided
        if (!empty($_POST['ai_title'])) {
            $mysqli->query("UPDATE autocontent_articles SET title = '" . $mysqli->real_escape_string($_POST['ai_title']) . "' WHERE id = " . $id);
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Article updated successfully']);
            exit;
        }

        showMessage('Article updated successfully', 'success');
        logActivity("Auto Content Article Edited", "autocontent", $id, ['title' => $_POST['ai_title'] ?? $article['title']], 'success');

        header('Location: /admin/autocontent/queue/view?id=' . $id);
        exit;
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Article Edit Error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autocontent/queue');
        exit;
    }
});

/**
 * Bulk Action on Queue Items
 * POST /admin/autocontent/queue/bulk-action
 */
$router->post('/admin/autocontent/queue/bulk-action', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $isAjax = isset($_GET['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']);

    try {
        $action = $_POST['action'] ?? '';
        $idsJson = $_POST['ids'] ?? '[]';
        $ids = json_decode($idsJson, true);

        if (empty($ids) || !is_array($ids)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No items selected']);
                exit;
            }
            showMessage('No items selected', 'error');
            header('Location: /admin/autocontent/queue');
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $successCount = 0;
        $errors = [];

        foreach ($ids as $id) {
            $id = (int)$id;
<<<<<<< HEAD
            if ($id <= 0) continue;
=======
            if ($id <= 0)
                continue;
>>>>>>> temp_branch

            try {
                switch ($action) {
                    case 'approve':
                        $model->updateArticleStatus($id, 'approved');
                        $successCount++;
                        break;
                    case 'reject':
                        $model->updateArticleStatus($id, 'failed');
                        $successCount++;
                        break;
                    case 'delete':
                        $model->deleteArticle($id);
                        $successCount++;
                        break;
                    case 'publish':
                        $article = $model->getArticleById($id);
                        if ($article) {
                            $contentModel = new ContentModel($mysqli);
                            $postId = $contentModel->createPost(
                                $article['title'],
                                $article['content'],
                                1,
                                $article['excerpt'] ?? '',
                                $article['image_url'] ?? ''
                            );
                            if ($postId > 0) {
                                $model->updateArticleStatus($id, 'published');
                                $successCount++;
                            }
                        }
                        break;
                    default:
                        $errors[] = "Unknown action: $action";
                }
<<<<<<< HEAD
            } catch (Throwable $e) {
=======
            }
            catch (Throwable $e) {
>>>>>>> temp_branch
                $errors[] = "Error on ID $id: " . $e->getMessage();
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            if ($successCount > 0) {
                echo json_encode(['success' => true, 'message' => "Successfully processed $successCount item(s)"]);
<<<<<<< HEAD
            } else {
=======
            }
            else {
>>>>>>> temp_branch
                echo json_encode(['success' => false, 'message' => empty($errors) ? 'No items processed' : implode(', ', $errors)]);
            }
            exit;
        }

        showMessage("Processed $successCount item(s)", 'success');
        header('Location: /admin/autocontent/queue');
        exit;
<<<<<<< HEAD
    } catch (Throwable $e) {
=======
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Auto Content Bulk Action Error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autocontent/queue');
        exit;
    }
});

/**
 * Sitemap Crawler - Multi-Layer Sitemap Crawling for Mobile Phones
 * POST /admin/autocontent/api/crawl-sitemap
 * 
 * This endpoint implements the full sitemap crawling pipeline:
 * 1. Parse sitemap index XML to get child sitemaps
 * 2. Parse child sitemaps to get page URLs
 * 3. Filter URLs (keep /product/, /phone/, /mobile/, /device/)
 * 4. Crawl product pages and extract phone data
 * 5. Store in database (mobiles table)
 * 
 * Parameters:
 * - sitemap_url: The main sitemap index URL (e.g., https://www.mobiledokan.co/sitemap.xml)
 * - delay: Request delay in seconds (default: 2)
 * - max_pages: Maximum number of pages to crawl (default: 50)
 * - debug: Enable debug logging (default: false)
 */
$router->post('/admin/autocontent/api/crawl-sitemap', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $sitemapUrl = $_POST['sitemap_url'] ?? '';
        $delay = (int)($_POST['delay'] ?? 2);
        $maxPages = (int)($_POST['max_pages'] ?? 50);
        $debug = isset($_POST['debug']) && $_POST['debug'] !== 'false';

        // Validate required parameter
        if (empty($sitemapUrl)) {
            echo json_encode([
<<<<<<< HEAD
                'success' => false,
                'message' => 'Sitemap URL is required. Please provide a valid sitemap index URL.',
                'example' => 'https://www.mobiledokan.co/sitemap.xml'
=======
            'success' => false,
            'message' => 'Sitemap URL is required. Please provide a valid sitemap index URL.',
            'example' => 'https://www.mobiledokan.co/sitemap.xml'
>>>>>>> temp_branch
            ]);
            exit;
        }

        // Validate URL format
        if (!filter_var($sitemapUrl, FILTER_VALIDATE_URL)) {
            echo json_encode([
<<<<<<< HEAD
                'success' => false,
                'message' => 'Invalid URL format'
=======
            'success' => false,
            'message' => 'Invalid URL format'
>>>>>>> temp_branch
            ]);
            exit;
        }

        // Ensure delay is within reasonable limits
        $delay = max(1, min(10, $delay));
        $maxPages = max(1, min(500, $maxPages));

        // Initialize MobileModel for saving phones
        $mobileModel = new MobileModel($mysqli);

        // Initialize SitemapCrawlerService
        $crawler = new SitemapCrawlerService($mysqli);
        $crawler->setRequestDelay($delay)
            ->setMaxPages($maxPages)
            ->setDebug($debug);

        // Run the sitemap crawler
        $result = $crawler->run($sitemapUrl);

        // Build response
        $response = [
            'success' => $result['success'],
            'message' => $result['message'],
            'stats' => [
                'child_sitemaps_found' => $result['child_sitemaps_found'] ?? 0,
                'urls_found' => $result['urls_found'] ?? 0,
                'urls_filtered' => $result['urls_filtered'] ?? 0,
                'pages_crawled' => $result['pages_crawled'] ?? 0,
                'phones_saved' => $result['phones_saved'] ?? 0,
                'duplicates_skipped' => $result['duplicates_skipped'] ?? 0,
                'errors' => $result['errors_count'] ?? 0
            ]
        ];

        // Add debug info if enabled
        if ($debug && !empty($result['debug_log'])) {
            $response['debug_log'] = array_slice($result['debug_log'], -50); // Last 50 debug messages
        }

        // Add sample of crawled URLs if available
        if (!empty($result['sample_urls'])) {
            $response['sample_urls'] = array_slice($result['sample_urls'], 0, 10);
        }

        echo json_encode($response);
<<<<<<< HEAD
    } catch (Throwable $e) {
        error_log("Sitemap Crawler Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
=======
    }
    catch (Throwable $e) {
        error_log("Sitemap Crawler Error: " . $e->getMessage());
        echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
>>>>>>> temp_branch
        ]);
    }
    exit;
});

/**
 * Test Sitemap URL - Quick validation endpoint
 * POST /admin/autocontent/api/test-sitemap
 */
$router->post('/admin/autocontent/api/test-sitemap', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $sitemapUrl = $_POST['sitemap_url'] ?? '';

        if (empty($sitemapUrl)) {
            echo json_encode([
<<<<<<< HEAD
                'success' => false,
                'message' => 'Sitemap URL is required'
=======
            'success' => false,
            'message' => 'Sitemap URL is required'
>>>>>>> temp_branch
            ]);
            exit;
        }

        if (!filter_var($sitemapUrl, FILTER_VALIDATE_URL)) {
            echo json_encode([
<<<<<<< HEAD
                'success' => false,
                'message' => 'Invalid URL format'
=======
            'success' => false,
            'message' => 'Invalid URL format'
>>>>>>> temp_branch
            ]);
            exit;
        }

        // Fetch and parse sitemap index
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $sitemapUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_ENCODING => '',
        ]);
        $xml = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || empty($xml)) {
            echo json_encode([
<<<<<<< HEAD
                'success' => false,
                'message' => 'Failed to fetch sitemap: ' . $error
=======
            'success' => false,
            'message' => 'Failed to fetch sitemap: ' . $error
>>>>>>> temp_branch
            ]);
            exit;
        }

        if ($httpCode !== 200) {
            echo json_encode([
<<<<<<< HEAD
                'success' => false,
                'message' => "HTTP Error: $httpCode"
=======
            'success' => false,
            'message' => "HTTP Error: $httpCode"
>>>>>>> temp_branch
            ]);
            exit;
        }

        // Parse XML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        // Check if it's a sitemap index or regular sitemap
        $sitemapIndex = $xpath->query('//sm:sitemapindex');
        $urlset = $xpath->query('//sm:urlset');

        $result = [
            'success' => true,
            'sitemap_type' => $sitemapIndex->length > 0 ? 'sitemap_index' : ($urlset->length > 0 ? 'urlset' : 'unknown'),
            'http_code' => $httpCode,
            'urls' => []
        ];

        if ($sitemapIndex->length > 0) {
            // It's a sitemap index - get child sitemaps
            $locNodes = $xpath->query('//sm:sitemap/sm:loc');
            $childSitemaps = [];
            foreach ($locNodes as $node) {
                $childSitemaps[] = trim($node->nodeValue);
            }
            $result['child_sitemaps'] = $childSitemaps;
            $result['child_sitemap_count'] = count($childSitemaps);
<<<<<<< HEAD
        } elseif ($urlset->length > 0) {
=======
        }
        elseif ($urlset->length > 0) {
>>>>>>> temp_branch
            // It's a regular sitemap with URLs
            $locNodes = $xpath->query('//sm:url/sm:loc');
            $urls = [];
            foreach ($locNodes as $node) {
                $urls[] = trim($node->nodeValue);
            }
            $result['urls'] = array_slice($urls, 0, 20); // First 20 URLs
            $result['total_urls'] = count($urls);
        }

        echo json_encode($result);
<<<<<<< HEAD
    } catch (Throwable $e) {
        error_log("Test Sitemap Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
=======
    }
    catch (Throwable $e) {
        error_log("Test Sitemap Error: " . $e->getMessage());
        echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
>>>>>>> temp_branch
        ]);
    }
    exit;
});

/**
 * Test CSS Selectors - Preview what selectors find
 * POST /admin/autocontent/api/test-selectors
 */
$router->post('/admin/autocontent/api/test-selectors', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $url = $_POST['url'] ?? '';
        $selectorsJson = $_POST['selectors'] ?? '{}';
        $selectors = json_decode($selectorsJson, true);

        if (empty($url)) {
            echo json_encode(['success' => false, 'message' => 'URL is required']);
            exit;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
            exit;
        }

        // Fetch the page
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_ENCODING => '',
        ]);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || empty($html)) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch URL: ' . $error]);
            exit;
        }

        if ($httpCode !== 200) {
            echo json_encode(['success' => false, 'message' => "HTTP Error: $httpCode"]);
            exit;
        }

        // Parse HTML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $results = [];

        // Test each selector
        foreach ($selectors as $name => $selector) {
<<<<<<< HEAD
            if (empty($selector)) continue;
=======
            if (empty($selector))
                continue;
>>>>>>> temp_branch

            // Support multiple selectors (comma-separated) - try each until one works
            $selectorParts = array_map('trim', explode(',', $selector));
            $found = false;

            foreach ($selectorParts as $singleSelector) {
                try {
                    $nodes = $xpath->query($singleSelector);
                    if ($nodes && $nodes->length > 0) {
                        $values = [];
                        foreach ($nodes as $node) {
                            $val = trim($node->nodeValue ?? $node->textContent ?? '');
                            if (!empty($val)) {
                                $values[] = $val;
                            }
                            // Also try to get src attribute for images
                            if ($node->hasAttribute('src')) {
                                $values[] = $node->getAttribute('src');
                            }
                            if ($node->hasAttribute('href')) {
                                $values[] = $node->getAttribute('href');
                            }
                            if ($node->hasAttribute('content')) {
                                $values[] = $node->getAttribute('content');
                            }
                        }
                        if (!empty($values)) {
                            $results[$name] = array_values(array_unique($values));
                            $found = true;
                            break;
                        }
                    }
<<<<<<< HEAD
                } catch (Exception $e) {
=======
                }
                catch (Exception $e) {
>>>>>>> temp_branch
                    continue;
                }
            }

            if (!$found) {
                $results[$name] = null;
            }
        }

        echo json_encode([
<<<<<<< HEAD
            'success' => true,
            'message' => 'Selectors tested successfully',
            'results' => $results
        ]);
    } catch (Throwable $e) {
        error_log("Test Selectors Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
=======
        'success' => true,
        'message' => 'Selectors tested successfully',
        'results' => $results
        ]);
    }
    catch (Throwable $e) {
        error_log("Test Selectors Error: " . $e->getMessage());
        echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
>>>>>>> temp_branch
        ]);
    }
    exit;
});

/**
 * Get Website Presets - Load presets from database
 * GET /admin/autocontent/api/website-presets
 */
$router->get('/admin/autocontent/api/website-presets', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $model = new AutoContentModel($mysqli);
        // Ensure tables exist before fetching presets (creates table and default presets if needed)
        $model->ensureTablesExist();
        $presets = $model->getWebsitePresets();

        echo json_encode([
<<<<<<< HEAD
            'success' => true,
            'presets' => $presets
        ]);
    } catch (Throwable $e) {
        error_log("Get Website Presets Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
=======
        'success' => true,
        'presets' => $presets
        ]);
    }
    catch (Throwable $e) {
        error_log("Get Website Presets Error: " . $e->getMessage());
        echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
>>>>>>> temp_branch
        ]);
    }
    exit;
});

/**
 * Save Website Preset - Add/Update preset
 * POST /admin/autocontent/api/save-preset
 */
$router->post('/admin/autocontent/api/save-preset', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $data = [
            'id' => isset($_POST['id']) ? (int)$_POST['id'] : 0,
            'preset_key' => $_POST['preset_key'] ?? '',
            'name' => $_POST['name'] ?? '',
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
            'selector_tags' => $_POST['selector_tags'] ?? ''
        ];

        if (empty($data['preset_key']) || empty($data['name'])) {
            echo json_encode([
<<<<<<< HEAD
                'success' => false,
                'message' => 'Preset key and name are required'
=======
            'success' => false,
            'message' => 'Preset key and name are required'
>>>>>>> temp_branch
            ]);
            exit;
        }

        // Validate preset_key format (lowercase alphanumeric with hyphens)
        if (!preg_match('/^[a-z0-9-]+$/', $data['preset_key'])) {
            echo json_encode([
<<<<<<< HEAD
                'success' => false,
                'message' => 'Preset key must contain only lowercase letters, numbers, and hyphens'
=======
            'success' => false,
            'message' => 'Preset key must contain only lowercase letters, numbers, and hyphens'
>>>>>>> temp_branch
            ]);
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $id = $model->saveWebsitePreset($data);

        echo json_encode([
<<<<<<< HEAD
            'success' => true,
            'id' => $id,
            'message' => $data['id'] > 0 ? 'Preset updated successfully' : 'Preset created successfully'
        ]);
    } catch (Throwable $e) {
        error_log("Save Website Preset Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
=======
        'success' => true,
        'id' => $id,
        'message' => $data['id'] > 0 ? 'Preset updated successfully' : 'Preset created successfully'
        ]);
    }
    catch (Throwable $e) {
        error_log("Save Website Preset Error: " . $e->getMessage());
        echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
>>>>>>> temp_branch
        ]);
    }
    exit;
});

/**
 * Delete Website Preset
 * POST /admin/autocontent/api/delete-preset
 */
$router->post('/admin/autocontent/api/delete-preset', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode([
<<<<<<< HEAD
                'success' => false,
                'message' => 'Invalid preset ID'
=======
            'success' => false,
            'message' => 'Invalid preset ID'
>>>>>>> temp_branch
            ]);
            exit;
        }

        $model = new AutoContentModel($mysqli);
        $model->deleteWebsitePreset($id);

        echo json_encode([
<<<<<<< HEAD
            'success' => true,
            'message' => 'Preset deleted successfully'
        ]);
    } catch (Throwable $e) {
        error_log("Delete Website Preset Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
=======
        'success' => true,
        'message' => 'Preset deleted successfully'
        ]);
    }
    catch (Throwable $e) {
        error_log("Delete Website Preset Error: " . $e->getMessage());
        echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
>>>>>>> temp_branch
        ]);
    }
    exit;
});

/**
 * Live Preview Selectors - Test selectors on a URL
 * POST /admin/autocontent/api/preview-selectors
 */
$router->post('/admin/autocontent/api/preview-selectors', [], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $url = $_POST['url'] ?? '';
        $type = $_POST['type'] ?? 'list';
        $selectorsJson = $_POST['selectors'] ?? '{}';
        $selectors = json_decode($selectorsJson, true);

        if (empty($url)) {
            echo json_encode(['success' => false, 'message' => 'URL is required']);
            exit;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
            exit;
        }

        // Fetch the page
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_ENCODING => '',
        ]);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || empty($html)) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch URL: ' . $error]);
            exit;
        }

        if ($httpCode !== 200) {
            echo json_encode(['success' => false, 'message' => "HTTP Error: $httpCode"]);
            exit;
        }

        // Parse HTML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $result = [];
        $matches = [];

        if ($type === 'list') {
            // Extract list items
            $containerSelector = $selectors['container'] ?? '';
            $itemSelector = $selectors['item'] ?? '';
            $titleSelector = $selectors['title'] ?? '';
            $linkSelector = $selectors['link'] ?? '';
            $dateSelector = $selectors['date'] ?? '';
            $imageSelector = $selectors['image'] ?? '';

            // Check if selectors found matches
            $matches = [
                'container' => !empty($containerSelector),
                'item' => !empty($itemSelector),
                'title' => !empty($titleSelector),
                'link' => !empty($linkSelector),
                'date' => !empty($dateSelector),
                'image' => !empty($imageSelector)
            ];

            // Find container
            $container = null;
            if (!empty($containerSelector)) {
                $containers = $xpath->query($containerSelector);
                if ($containers && $containers->length > 0) {
                    $container = $containers->item(0);
                    $matches['container'] = true;
                }
            }

            // Find items
            $items = [];
            if ($container && !empty($itemSelector)) {
                $itemNodes = $xpath->query($itemSelector, $container);
                if ($itemNodes && $itemNodes->length > 0) {
                    $matches['item'] = true;
                    foreach ($itemNodes as $idx => $itemNode) {
<<<<<<< HEAD
                        if ($idx >= 10) break; // Limit to 10 items
=======
                        if ($idx >= 10)
                            break; // Limit to 10 items
>>>>>>> temp_branch

                        $item = [];

                        // Get title
                        if (!empty($titleSelector)) {
                            $titleNodes = $xpath->query($titleSelector, $itemNode);
                            if ($titleNodes && $titleNodes->length > 0) {
                                $item['title'] = trim($titleNodes->item(0)->nodeValue ?? '');
                                $matches['title'] = true;
                            }
                        }

                        // Get link
                        if (!empty($linkSelector)) {
                            $linkNodes = $xpath->query($linkSelector, $itemNode);
<<<<<<< HEAD
                        } else {
=======
                        }
                        else {
>>>>>>> temp_branch
                            $linkNodes = $xpath->query('.//a', $itemNode);
                        }
                        if ($linkNodes && $linkNodes->length > 0) {
                            $link = $linkNodes->item(0)->getAttribute('href');
                            // Make absolute URL if needed
                            if (!empty($link) && strpos($link, 'http') !== 0) {
                                $parsedUrl = parse_url($url);
                                $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                                $link = $baseUrl . ($link[0] === '/' ? '' : '/') . $link;
                            }
                            $item['link'] = $link;
                            $matches['link'] = true;
                        }

                        // Get date
                        if (!empty($dateSelector)) {
                            $dateNodes = $xpath->query($dateSelector, $itemNode);
                            if ($dateNodes && $dateNodes->length > 0) {
                                $item['date'] = trim($dateNodes->item(0)->nodeValue ?? '');
                                $matches['date'] = true;
                            }
                        }

                        // Get image
                        if (!empty($imageSelector)) {
                            $imageNodes = $xpath->query($imageSelector, $itemNode);
                            if ($imageNodes && $imageNodes->length > 0) {
                                $img = $imageNodes->item(0);
                                $item['image'] = $img->getAttribute('src') ?: ($img->getAttribute('data-src') ?: '');
                                $matches['image'] = true;
                            }
                        }

                        $items[] = $item;
                    }
                }
            }

            $result = [
                'items' => $items,
                'count' => count($items)
            ];
<<<<<<< HEAD
        } else {
=======
        }
        else {
>>>>>>> temp_branch
            // Detail page extraction
            $titleSelector = $selectors['title'] ?? '';
            $contentSelector = $selectors['content'] ?? '';
            $imageSelector = $selectors['image'] ?? '';
            $excerptSelector = $selectors['excerpt'] ?? '';
            $dateSelector = $selectors['date'] ?? '';
            $authorSelector = $selectors['author'] ?? '';

            // Check matches
            $matches = [
                'title' => !empty($titleSelector),
                'content' => !empty($contentSelector),
                'image' => !empty($imageSelector),
                'excerpt' => !empty($excerptSelector),
                'date' => !empty($dateSelector),
                'author' => !empty($authorSelector)
            ];

            $content = [];

            // Get title
            if (!empty($titleSelector)) {
                $titleNodes = $xpath->query($titleSelector);
                if ($titleNodes && $titleNodes->length > 0) {
                    $content['title'] = trim($titleNodes->item(0)->nodeValue ?? '');
                    $matches['title'] = true;
                }
            }

            // Get content
            if (!empty($contentSelector)) {
                $contentNodes = $xpath->query($contentSelector);
                if ($contentNodes && $contentNodes->length > 0) {
                    $contentNode = $contentNodes->item(0);
                    // Get text content only (strip HTML)
                    $content['content'] = trim($contentNode->nodeValue ?? '');
                    $matches['content'] = true;
                }
            }

            // Get image
            if (!empty($imageSelector)) {
                $imageNodes = $xpath->query($imageSelector);
                if ($imageNodes && $imageNodes->length > 0) {
                    $img = $imageNodes->item(0);
                    $content['image'] = $img->getAttribute('content') ?: ($img->getAttribute('src') ?: ($img->getAttribute('data-src') ?: ''));
                    $matches['image'] = true;
                }
            }

            // Get excerpt
            if (!empty($excerptSelector)) {
                $excerptNodes = $xpath->query($excerptSelector);
                if ($excerptNodes && $excerptNodes->length > 0) {
                    $content['excerpt'] = trim($excerptNodes->item(0)->nodeValue ?? $excerptNodes->item(0)->getAttribute('content'));
                    $matches['excerpt'] = true;
                }
            }

            // Get date
            if (!empty($dateSelector)) {
                $dateNodes = $xpath->query($dateSelector);
                if ($dateNodes && $dateNodes->length > 0) {
                    $dateNode = $dateNodes->item(0);
                    $content['date'] = $dateNode->getAttribute('datetime') ?: trim($dateNode->nodeValue ?? '');
                    $matches['date'] = true;
                }
            }

            // Get author
            if (!empty($authorSelector)) {
                $authorNodes = $xpath->query($authorSelector);
                if ($authorNodes && $authorNodes->length > 0) {
                    $content['author'] = trim($authorNodes->item(0)->nodeValue ?? '');
                    $matches['author'] = true;
                }
            }

            $result = [
                'content' => $content
            ];
        }

        echo json_encode([
<<<<<<< HEAD
            'success' => true,
            'message' => 'Preview generated successfully',
            'type' => $type,
            'url' => $url,
            'selectors' => $selectors,
            'matches' => $matches,
            'rawHtml' => substr($html, 0, 5000), // First 5KB of HTML for debugging
            'items' => $result['items'] ?? null,
            'content' => $result['content'] ?? null
        ]);
    } catch (Throwable $e) {
        error_log("Preview Selectors Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
=======
        'success' => true,
        'message' => 'Preview generated successfully',
        'type' => $type,
        'url' => $url,
        'selectors' => $selectors,
        'matches' => $matches,
        'rawHtml' => substr($html, 0, 5000), // First 5KB of HTML for debugging
        'items' => $result['items'] ?? null,
        'content' => $result['content'] ?? null
        ]);
    }
    catch (Throwable $e) {
        error_log("Preview Selectors Error: " . $e->getMessage());
        echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
>>>>>>> temp_branch
        ]);
    }
    exit;
});

/**
 * Website Presets Management Page
 * GET /admin/autocontent/presets
 */
$router->get('/admin/autocontent/presets', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new AutoContentModel($mysqli);
        $presets = $model->getWebsitePresets();

        echo $twig->render('admin/autocontent/presets.twig', [
<<<<<<< HEAD
            'title' => 'Website Presets',
            'presets' => $presets,
            'current_page' => 'autocontent-presets'
        ]);
    } catch (Throwable $e) {
=======
        'title' => 'Website Presets',
        'presets' => $presets,
        'current_page' => 'autocontent-presets'
        ]);
    }
    catch (Throwable $e) {
>>>>>>> temp_branch
        error_log("Website Presets Page Error: " . $e->getMessage());
        echo "Error loading presets: " . $e->getMessage();
    }
});
