<?php

declare(strict_types=1);

/**
 * AutoBlogController.php
 * Controller for AI AutoBlog - handles web scraping, AI processing, and publishing
 */

use App\Modules\Scraper\ScraperService;
use App\Modules\Scraper\ProthomAloScraperService;
use App\Modules\Scraper\EnhancedScraperService;
use App\Modules\Scraper\ContentCleanerService;
use App\Modules\Scraper\DuplicateCheckerService;
use App\Modules\Scraper\ImageDownloaderService;

// Ensure database tables exist
$autoBlogModel = new AutoBlogModel($mysqli);
$autoBlogModel->ensureTablesExist();

// ================== DASHBOARD ==================

/**
 * AutoBlog Dashboard
 * GET /admin/autoblog
 */
$router->get('/admin/autoblog', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new AutoBlogModel($mysqli);
        $stats = $model->getStats();
        $recent_items = $model->getRecentArticles();
        $sources_count = count($model->getActiveSources());

        echo $twig->render('admin/autoblog/dashboard.twig', [
            'title' => 'AI AutoBlog Dashboard',
            'stats' => $stats,
            'recent_items' => $recent_items,
            'sources_count' => $sources_count,
            'current_page' => 'autoblog-dashboard'
        ]);
    } catch (Throwable $e) {
        error_log("AutoBlog Dashboard Error: " . $e->getMessage());
        echo "Error loading dashboard: " . $e->getMessage();
    }
});

// ================== SOURCES ==================

/**
 * Sources List
 * GET /admin/autoblog/sources
 */
$router->get('/admin/autoblog/sources', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new AutoBlogModel($mysqli);
        $sources = $model->getAllSources();

        echo $twig->render('admin/autoblog/sources.twig', [
            'title' => 'AI Article Sources',
            'sources' => $sources,
            'current_page' => 'autoblog-sources'
        ]);
    } catch (Throwable $e) {
        error_log("AutoBlog Sources Error: " . $e->getMessage());
        echo "Error loading sources: " . $e->getMessage();
    }
});

/**
 * Source Create Form
 * GET /admin/autoblog/sources/create
 */
$router->get('/admin/autoblog/sources/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new AutoBlogModel($mysqli);
        $categories = $model->getCategories();

        echo $twig->render('admin/autoblog/source_form.twig', [
            'title' => 'Add Article Source',
            'source' => null,
            'categories' => $categories,
            'isCreate' => true,
            'current_page' => 'autoblog-sources'
        ]);
    } catch (Throwable $e) {
        error_log("AutoBlog Source Create Form Error: " . $e->getMessage());
        echo "Error: " . $e->getMessage();
    }
});

/**
 * Source Create Handler
 * POST /admin/autoblog/sources/create
 */
$router->post('/admin/autoblog/sources/create', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    try {
        $model = new AutoBlogModel($mysqli);

        $data = [
            'name' => $_POST['name'] ?? '',
            'url' => $_POST['url'] ?? '',
            'type' => $_POST['type'] ?? 'rss',
            'category_id' => $_POST['category_id'] ?? null,
            'selector_list_container' => $_POST['selector_list_container'] ?? '',
            'selector_list_item' => $_POST['selector_list_item'] ?? '',
            'selector_list_title' => $_POST['selector_list_title'] ?? '',
            'selector_list_date' => $_POST['selector_list_date'] ?? '',
            'selector_title' => $_POST['selector_title'] ?? '',
            'selector_content' => $_POST['selector_content'] ?? '',
            'selector_image' => $_POST['selector_image'] ?? '',
            'selector_excerpt' => $_POST['selector_excerpt'] ?? '',
            'selector_date' => $_POST['selector_date'] ?? '',
            'selector_author' => $_POST['selector_author'] ?? '',
            'fetch_interval' => $_POST['fetch_interval'] ?? 3600,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        if (empty($data['name']) || empty($data['url'])) {
            showMessage('Name and URL are required', 'error');
            header('Location: /admin/autoblog/sources/create');
            exit;
        }

        $id = $model->createSource($data);

        if ($id > 0) {
            showMessage('Source created successfully', 'success');
            logActivity("AutoBlog Source Created", "autoblog", $id, ['name' => $data['name']], 'success');
        } else {
            showMessage('Failed to create source', 'error');
        }

        header('Location: /admin/autoblog/sources');
        exit;
    } catch (Throwable $e) {
        error_log("AutoBlog Source Create Error: " . $e->getMessage());
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autoblog/sources/create');
        exit;
    }
});

/**
 * Source Edit Form
 * GET /admin/autoblog/sources/edit
 */
$router->get('/admin/autoblog/sources/edit', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autoblog/sources');
            exit;
        }

        $model = new AutoBlogModel($mysqli);
        $source = $model->getSourceById($id);

        if (!$source) {
            showMessage('Source not found', 'error');
            header('Location: /admin/autoblog/sources');
            exit;
        }

        $categories = $model->getCategories();

        echo $twig->render('admin/autoblog/source_form.twig', [
            'title' => 'Edit Article Source',
            'source' => $source,
            'categories' => $categories,
            'isCreate' => false,
            'current_page' => 'autoblog-sources'
        ]);
    } catch (Throwable $e) {
        error_log("AutoBlog Source Edit Form Error: " . $e->getMessage());
        echo "Error: " . $e->getMessage();
    }
});

/**
 * Source Edit Handler
 * POST /admin/autoblog/sources/edit
 */
$router->post('/admin/autoblog/sources/edit', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autoblog/sources');
            exit;
        }

        $model = new AutoBlogModel($mysqli);

        $data = [
            'name' => $_POST['name'] ?? '',
            'url' => $_POST['url'] ?? '',
            'type' => $_POST['type'] ?? 'rss',
            'category_id' => $_POST['category_id'] ?? null,
            'selector_list_container' => $_POST['selector_list_container'] ?? '',
            'selector_list_item' => $_POST['selector_list_item'] ?? '',
            'selector_list_title' => $_POST['selector_list_title'] ?? '',
            'selector_list_date' => $_POST['selector_list_date'] ?? '',
            'selector_title' => $_POST['selector_title'] ?? '',
            'selector_content' => $_POST['selector_content'] ?? '',
            'selector_image' => $_POST['selector_image'] ?? '',
            'selector_excerpt' => $_POST['selector_excerpt'] ?? '',
            'selector_date' => $_POST['selector_date'] ?? '',
            'selector_author' => $_POST['selector_author'] ?? '',
            'fetch_interval' => $_POST['fetch_interval'] ?? 3600,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        if (empty($data['name']) || empty($data['url'])) {
            showMessage('Name and URL are required', 'error');
            header('Location: /admin/autoblog/sources/edit?id=' . $id);
            exit;
        }

        $success = $model->updateSource($id, $data);

        if ($success) {
            showMessage('Source updated successfully', 'success');
            logActivity("AutoBlog Source Updated", "autoblog", $id, ['name' => $data['name']], 'success');
        } else {
            showMessage('Failed to update source', 'error');
        }

        header('Location: /admin/autoblog/sources');
        exit;
    } catch (Throwable $e) {
        error_log("AutoBlog Source Edit Error: " . $e->getMessage());
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autoblog/sources/edit?id=' . ($_POST['id'] ?? ''));
        exit;
    }
});

/**
 * Delete Source
 * GET /admin/autoblog/sources/delete
 */
$router->get('/admin/autoblog/sources/delete', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autoblog/sources');
            exit;
        }

        $model = new AutoBlogModel($mysqli);
        $source = $model->getSourceById($id);

        $success = $model->deleteSource($id);

        if ($success) {
            showMessage('Source deleted successfully', 'success');
            logActivity("AutoBlog Source Deleted", "autoblog", $id, ['name' => $source['name'] ?? ''], 'success');
        } else {
            showMessage('Failed to delete source', 'error');
        }

        header('Location: /admin/autoblog/sources');
        exit;
    } catch (Throwable $e) {
        error_log("AutoBlog Source Delete Error: " . $e->getMessage());
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autoblog/sources');
        exit;
    }
});

/**
 * Toggle Source Status
 * GET /admin/autoblog/sources/toggle
 */
$router->get('/admin/autoblog/sources/toggle', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autoblog/sources');
            exit;
        }

        $model = new AutoBlogModel($mysqli);
        $model->toggleSourceStatus($id);

        header('Location: /admin/autoblog/sources');
        exit;
    } catch (Throwable $e) {
        error_log("AutoBlog Source Toggle Error: " . $e->getMessage());
        header('Location: /admin/autoblog/sources');
        exit;
    }
});

// ================== ARTICLE QUEUE ==================

/**
 * Article Queue
 * GET /admin/autoblog/queue
 */
$router->get('/admin/autoblog/queue', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
        $status = $_GET['status'] ?? '';
        $sourceFilter = $_GET['source'] ?? '';
        $search = $_GET['search'] ?? '';

        $model = new AutoBlogModel($mysqli);
        $articles = $model->getArticles($page, $limit, $status, $sourceFilter, $search);
        $sources = $model->getAllSources();
        $statusCounts = $model->getArticleCountByStatus();

        // Calculate pagination
        $total = $statusCounts['total'];
        $totalPages = ceil($total / $limit);

        echo $twig->render('admin/autoblog/queue.twig', [
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
            'current_page' => 'autoblog-queue'
        ]);
    } catch (Throwable $e) {
        error_log("AutoBlog Queue Error: " . $e->getMessage());
        echo "Error loading queue: " . $e->getMessage();
    }
});

/**
 * View Article
 * GET /admin/autoblog/queue/view
 */
$router->get('/admin/autoblog/queue/view', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autoblog/queue');
            exit;
        }

        $model = new AutoBlogModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            showMessage('Article not found', 'error');
            header('Location: /admin/autoblog/queue');
            exit;
        }

        echo $twig->render('admin/autoblog/queue_view.twig', [
            'title' => 'View Article',
            'article' => $article,
            'current_page' => 'autoblog-queue'
        ]);
    } catch (Throwable $e) {
        error_log("AutoBlog Article View Error: " . $e->getMessage());
        echo "Error: " . $e->getMessage();
    }
});

// ================== SETTINGS ==================

/**
 * Settings Page
 * GET /admin/autoblog/settings
 */
$router->get('/admin/autoblog/settings', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new AutoBlogModel($mysqli);
        $config = $model->getSettings();

        echo $twig->render('admin/autoblog/settings.twig', [
            'title' => 'AI AutoBlog Settings',
            'config' => $config,
            'current_page' => 'autoblog-settings'
        ]);
    } catch (Throwable $e) {
        error_log("AutoBlog Settings Error: " . $e->getMessage());
        echo "Error: " . $e->getMessage();
    }
});

/**
 * Settings Handler
 * POST /admin/autoblog/settings
 */
$router->post('/admin/autoblog/settings', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    try {
        $model = new AutoBlogModel($mysqli);

        $settings = [
            'ai_endpoint' => $_POST['ai_endpoint'] ?? '',
            'ai_model' => $_POST['ai_model'] ?? 'gpt-4o-mini',
            'ai_key' => $_POST['ai_key'] ?? '',
            'autoblog_enabled' => isset($_POST['autoblog_enabled']) ? '1' : '0',
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
        } elseif ($settings['ai_key'] === ' ') {
            // Clear key if single space
            $settings['ai_key'] = '';
        }

        $model->saveSettings($settings);

        showMessage('Settings saved successfully', 'success');
        logActivity("AutoBlog Settings Updated", "autoblog", 0, [], 'success');

        header('Location: /admin/autoblog/settings');
        exit;
    } catch (Throwable $e) {
        error_log("AutoBlog Settings Save Error: " . $e->getMessage());
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autoblog/settings');
        exit;
    }
});

/**
 * Get Chart Stats (AJAX)
 * GET /admin/autoblog/stats/chart
 */
$router->get('/admin/autoblog/stats/chart', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;

        // Get daily article counts for the chart
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count
                FROM autoblog_articles
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
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
});

// ================== API ENDPOINTS ==================

/**
 * Detect CSS Selectors using AI
 * POST /admin/autoblog/api/detect-selectors
 */
$router->post('/admin/autoblog/api/detect-selectors', [], function () use ($mysqli) {
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
            'success' => true,
            'message' => 'Selectors detected successfully',
            'selectors' => $selectors
        ]);
        exit;
    } catch (Throwable $e) {
        error_log("Detect Selectors Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
});

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
    } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            continue;
        }
    }
    return '';
}

/**
 * Collect Articles (Scrape)
 * POST /admin/autoblog/api/collect
 */
$router->post('/admin/autoblog/api/collect', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $model = new AutoBlogModel($mysqli);
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
        );

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
                    } else {
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
                    error_log("AutoBlog: Invalid scraper result for source {$source['name']}");
                    continue;
                }

                if (isset($result['error'])) {
                    $sourceResult['status'] = 'error';
                    $sourceResult['error'] = $result['error'];
                    $sourceResults[] = $sourceResult;
                    error_log("AutoBlog: Error scraping source {$source['name']}: " . $result['error']);
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
                
            } catch (Exception $e) {
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
        error_log("AutoBlog Collect API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Process Articles with AI
 * POST /admin/autoblog/api/process
 */
$router->post('/admin/autoblog/api/process', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        // Use AI Enhancer for proper AI processing
        $enhancer = new \App\Modules\AutoBlog\AiContentEnhancer($mysqli);

        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 5;
        $result = $enhancer->processBatch($limit);

        echo json_encode($result);
    } catch (Throwable $e) {
        error_log("AutoBlog Process API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Process Single Article with AI
 * POST /admin/autoblog/api/process-single
 */
$router->post('/admin/autoblog/api/process-single', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid article ID']);
            exit;
        }

        // Use AI Enhancer for single article
        $enhancer = new \App\Modules\AutoBlog\AiContentEnhancer($mysqli);
        $result = $enhancer->processArticle($id);

        echo json_encode($result);
    } catch (Throwable $e) {
        error_log("AutoBlog Process Single API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Publish Articles
 * POST /admin/autoblog/api/publish
 */
$router->post('/admin/autoblog/api/publish', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $model = new AutoBlogModel($mysqli);
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
                } else {
                    $model->updateArticleStatus($article['id'], 'failed');
                }
            } catch (Exception $e) {
                error_log("Error publishing article {$article['id']}: " . $e->getMessage());
                $model->updateArticleStatus($article['id'], 'failed');
                continue;
            }
        }

        echo json_encode([
            'success' => true,
            'published' => $published,
            'message' => "Published {$published} articles"
        ]);
    } catch (Throwable $e) {
        error_log("AutoBlog Publish API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Run Full Pipeline
 * POST /admin/autoblog/api/run-pipeline
 */
$router->post('/admin/autoblog/api/run-pipeline', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $model = new AutoBlogModel($mysqli);

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
                } catch (Exception $e) {
                    error_log("Pipeline collect error for source {$source['id']}: " . $e->getMessage());
                    continue;
                }
            }
        }

        // Step 2: Process with AI
        $enhancer = new \App\Modules\AutoBlog\AiContentEnhancer($mysqli);
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
            'success' => true,
            'result' => $result,
            'message' => "Pipeline complete: {$result['collected']} collected, {$result['processed']} processed, {$result['published']} published"
        ]);
    } catch (Throwable $e) {
        error_log("AutoBlog Pipeline API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Retry Failed Articles
 * POST /admin/autoblog/api/retry
 */
$router->post('/admin/autoblog/api/retry', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        // Use AI Enhancer for retry
        $enhancer = new \App\Modules\AutoBlog\AiContentEnhancer($mysqli);

        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $result = $enhancer->retryFailed($limit);

        echo json_encode($result);
    } catch (Throwable $e) {
        error_log("AutoBlog Retry API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
});

/**
 * Delete Article from Queue
 * POST /admin/autoblog/queue/delete
 */
$router->post('/admin/autoblog/queue/delete', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $isAjax = isset($_GET['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']);

    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid article ID']);
                exit;
            }
            header('Location: /admin/autoblog/queue');
            exit;
        }

        $model = new AutoBlogModel($mysqli);
        $model->deleteArticle($id);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Article deleted successfully']);
            exit;
        }

        showMessage('Article deleted successfully', 'success');
        header('Location: /admin/autoblog/queue');
        exit;
    } catch (Throwable $e) {
        error_log("AutoBlog Article Delete Error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autoblog/queue');
        exit;
    }
});

/**
 * Publish Single Article
 * POST /admin/autoblog/queue/publish
 */
$router->post('/admin/autoblog/queue/publish', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/autoblog/queue');
            exit;
        }

        $model = new AutoBlogModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            showMessage('Article not found', 'error');
            header('Location: /admin/autoblog/queue');
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
            logActivity("AutoBlog Article Published", "autoblog", $id, ['title' => $article['title']], 'success');
        } else {
            showMessage('Failed to publish article', 'error');
        }

        header('Location: /admin/autoblog/queue');
        exit;
    } catch (Throwable $e) {
        error_log("AutoBlog Article Publish Error: " . $e->getMessage());
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autoblog/queue');
        exit;
    }
});

/**
 * Approve Article (Set to approved status)
 * POST /admin/autoblog/queue/approve
 */
$router->post('/admin/autoblog/queue/approve', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
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
            header('Location: /admin/autoblog/queue');
            exit;
        }

        $model = new AutoBlogModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Article not found']);
                exit;
            }
            showMessage('Article not found', 'error');
            header('Location: /admin/autoblog/queue');
            exit;
        }

        $model->updateArticleStatus($id, 'approved');

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Article approved successfully']);
            exit;
        }

        showMessage('Article approved successfully', 'success');
        logActivity("AutoBlog Article Approved", "autoblog", $id, ['title' => $article['title']], 'success');

        header('Location: /admin/autoblog/queue/view?id=' . $id);
        exit;
    } catch (Throwable $e) {
        error_log("AutoBlog Article Approve Error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autoblog/queue');
        exit;
    }
});

/**
 * Reject Article (Set to failed status)
 * POST /admin/autoblog/queue/reject
 */
$router->post('/admin/autoblog/queue/reject', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
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
            header('Location: /admin/autoblog/queue');
            exit;
        }

        $model = new AutoBlogModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Article not found']);
                exit;
            }
            showMessage('Article not found', 'error');
            header('Location: /admin/autoblog/queue');
            exit;
        }

        $model->updateArticleStatus($id, 'failed');

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Article rejected']);
            exit;
        }

        showMessage('Article rejected', 'success');
        logActivity("AutoBlog Article Rejected", "autoblog", $id, ['title' => $article['title']], 'success');

        header('Location: /admin/autoblog/queue');
        exit;
    } catch (Throwable $e) {
        error_log("AutoBlog Article Reject Error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autoblog/queue');
        exit;
    }
});

/**
 * Edit Article in Queue
 * POST /admin/autoblog/queue/edit
 */
$router->post('/admin/autoblog/queue/edit', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
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
            header('Location: /admin/autoblog/queue');
            exit;
        }

        $model = new AutoBlogModel($mysqli);
        $article = $model->getArticleById($id);

        if (!$article) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Article not found']);
                exit;
            }
            showMessage('Article not found', 'error');
            header('Location: /admin/autoblog/queue');
            exit;
        }

        // Update article content
        $processedContent = $_POST['ai_content'] ?? $article['content'] ?? '';
        $aiSummary = $_POST['ai_summary'] ?? '';

        $model->updateArticleContent($id, $processedContent, $aiSummary);

        // Update title if provided
        if (!empty($_POST['ai_title'])) {
            $mysqli->query("UPDATE autoblog_articles SET title = '" . $mysqli->real_escape_string($_POST['ai_title']) . "' WHERE id = " . $id);
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Article updated successfully']);
            exit;
        }

        showMessage('Article updated successfully', 'success');
        logActivity("AutoBlog Article Edited", "autoblog", $id, ['title' => $_POST['ai_title'] ?? $article['title']], 'success');

        header('Location: /admin/autoblog/queue/view?id=' . $id);
        exit;
    } catch (Throwable $e) {
        error_log("AutoBlog Article Edit Error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autoblog/queue');
        exit;
    }
});

/**
 * Bulk Action on Queue Items
 * POST /admin/autoblog/queue/bulk-action
 */
$router->post('/admin/autoblog/queue/bulk-action', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
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
            header('Location: /admin/autoblog/queue');
            exit;
        }

        $model = new AutoBlogModel($mysqli);
        $successCount = 0;
        $errors = [];

        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;

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
            } catch (Throwable $e) {
                $errors[] = "Error on ID $id: " . $e->getMessage();
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            if ($successCount > 0) {
                echo json_encode(['success' => true, 'message' => "Successfully processed $successCount item(s)"]);
            } else {
                echo json_encode(['success' => false, 'message' => empty($errors) ? 'No items processed' : implode(', ', $errors)]);
            }
            exit;
        }

        showMessage("Processed $successCount item(s)", 'success');
        header('Location: /admin/autoblog/queue');
        exit;
    } catch (Throwable $e) {
        error_log("AutoBlog Bulk Action Error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        showMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: /admin/autoblog/queue');
        exit;
    }
});
