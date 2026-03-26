<?php

declare(strict_types=1);

/**
 * BDNews24Controller.php
 * Controller for managing BDNews24 Bangla articles scraper
 */

use App\Modules\Scraper\BDNews24ScraperService;
use App\Modules\Scraper\HttpClientService;
use App\Models\BDNews24ArticleModel;
use App\Helpers\JsonResponse;
use App\Helpers\ErrorLogging;

// ================== DASHBOARD ==================

/**
 * BDNews24 Scraper Dashboard
 * GET /admin/scraper/bdnews24
 */
$router->get('/admin/scraper/bdnews24', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new BDNews24ArticleModel($mysqli);
        
        // Get statistics
        $totalArticles = $model->getTotalCount();
        $recentArticles = $model->getRecentArticles(10);
        $categories = $model->getCategories();
        
        // Get last scrape info (from a settings table or file)
        $lastScrape = null;
        $lastScrapeFile = __DIR__ . '/../Modules/Scraper/logs/bdnews24_last_scrape.json';
        if (file_exists($lastScrapeFile)) {
            $lastScrape = json_decode(file_get_contents($lastScrapeFile), true);
        }

        return $twig->render('admin/scraper/bdnews24.twig', [
            'total_articles' => $totalArticles,
            'recent_articles' => $recentArticles,
            'categories' => $categories,
            'last_scrape' => $lastScrape,
            'page_title' => 'BDNews24 Bangla Scraper',
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("BDNews24Controller: Dashboard error: " . $e->getMessage());
        return $twig->render('error.twig', [
            'error' => 'Failed to load scraper dashboard',
            'page_title' => 'Error',
        ]);
    }
});

// ================== SCRAPE ACTIONS ==================

/**
 * Trigger manual scrape
 * POST /admin/scraper/bdnews24/scrape
 */
$router->post('/admin/scraper/bdnews24/scrape', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        // Validate CSRF token
        if (!validateCsrfToken()) {
            return JsonResponse::error('Invalid CSRF token', 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $maxPages = (int)($input['max_pages'] ?? 3);
        $maxPages = min(max($maxPages, 1), 10); // Limit to 1-10 pages

        // Initialize services
        $httpClient = new HttpClientService();
        $scraper = new BDNews24ScraperService($httpClient);
        $model = new BDNews24ArticleModel($mysqli);

        // Scrape with progress tracking
        $results = $scraper->scrapeAllPages($maxPages, function ($page, $totalPages, $success, $data) use ($model, $scraper) {
            if ($success) {
                // Save articles to database
                foreach ($data as $article) {
                    $saveResult = $model->saveArticle($article);
                    if ($saveResult['success']) {
                        $scraper->updateStats('new_articles', 1);
                    } else {
                        if (strpos($saveResult['error'], 'already exists') !== false) {
                            $scraper->updateStats('duplicates', 1);
                        } else {
                            $scraper->updateStats('errors', 1);
                        }
                    }
                }
            }
        });

        // Save last scrape info
        $lastScrapeFile = __DIR__ . '/../Modules/Scraper/logs/bdnews24_last_scrape.json';
        $lastScrapeDir = dirname($lastScrapeFile);
        if (!is_dir($lastScrapeDir)) {
            mkdir($lastScrapeDir, 0755, true);
        }
        file_put_contents($lastScrapeFile, json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'pages_scraped' => $maxPages,
            'stats' => $scraper->getStats(),
        ]));

        return JsonResponse::success([
            'message' => 'Scraping completed successfully',
            'stats' => $scraper->getStats(),
            'articles_count' => count($results['articles']),
        ]);

    } catch (\Exception $e) {
        ErrorLogging::logError("BDNews24Controller: Scrape error: " . $e->getMessage());
        return JsonResponse::error('Failed to scrape articles: ' . $e->getMessage(), 500);
    }
});

// ================== ARTICLE LISTING ==================

/**
 * List scraped articles
 * GET /admin/scraper/bdnews24/articles
 */
$router->get('/admin/scraper/bdnews24/articles', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new BDNews24ArticleModel($mysqli);
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        
        if ($search) {
            $articles = $model->searchArticles($search, $limit, $offset);
            $total = count($model->searchArticles($search, 1000, 0)); // Approximate total
        } elseif ($category) {
            $articles = $model->getArticlesByCategory($category, $limit, $offset);
            $total = $model->getCountByCategory($category);
        } else {
            $articles = $model->getRecentArticles($limit, $offset);
            $total = $model->getTotalCount();
        }
        
        $categories = $model->getCategories();
        
        return $twig->render('admin/scraper/bdnews24-articles.twig', [
            'articles' => $articles,
            'categories' => $categories,
            'current_page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int)ceil($total / $limit),
            'search' => $search,
            'selected_category' => $category,
            'page_title' => 'BDNews24 Articles',
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("BDNews24Controller: Articles list error: " . $e->getMessage());
        return $twig->render('error.twig', [
            'error' => 'Failed to load articles',
            'page_title' => 'Error',
        ]);
    }
});

/**
 * View article details
 * GET /admin/scraper/bdnews24/articles/{id}
 */
$router->get('/admin/scraper/bdnews24/articles/(\d+)', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $model = new BDNews24ArticleModel($mysqli);
        $article = $model->getArticleById((int)$id);
        
        if (!$article) {
            return $twig->render('error.twig', [
                'error' => 'Article not found',
                'page_title' => 'Error',
            ]);
        }
        
        return $twig->render('admin/scraper/bdnews24-article-detail.twig', [
            'article' => $article,
            'page_title' => 'Article Details',
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("BDNews24Controller: Article detail error: " . $e->getMessage());
        return $twig->render('error.twig', [
            'error' => 'Failed to load article details',
            'page_title' => 'Error',
        ]);
    }
});

// ================== ARTICLE MANAGEMENT ==================

/**
 * Delete an article
 * DELETE /admin/scraper/bdnews24/articles/{id}
 */
$router->delete('/admin/scraper/bdnews24/articles/(\d+)', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    try {
        // Validate CSRF token
        if (!validateCsrfToken()) {
            return JsonResponse::error('Invalid CSRF token', 403);
        }

        $model = new BDNews24ArticleModel($mysqli);
        $result = $model->deleteArticle((int)$id);
        
        if ($result['success']) {
            return JsonResponse::success(['message' => 'Article deleted successfully']);
        } else {
            return JsonResponse::error($result['error'] ?? 'Failed to delete article', 500);
        }
    } catch (\Exception $e) {
        ErrorLogging::logError("BDNews24Controller: Delete article error: " . $e->getMessage());
        return JsonResponse::error('Failed to delete article: ' . $e->getMessage(), 500);
    }
});

// ================== EXPORT ==================

/**
 * Export articles to JSON
 * GET /admin/scraper/bdnews24/export/json
 */
$router->get('/admin/scraper/bdnews24/export/json', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new BDNews24ArticleModel($mysqli);
        $articles = $model->getRecentArticles(1000, 0); // Get up to 1000 articles
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="bdnews24-articles-' . date('Y-m-d') . '.json"');
        
        echo json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } catch (\Exception $e) {
        ErrorLogging::logError("BDNews24Controller: Export JSON error: " . $e->getMessage());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Failed to export articles']);
        exit;
    }
});

/**
 * Export articles to CSV
 * GET /admin/scraper/bdnews24/export/csv
 */
$router->get('/admin/scraper/bdnews24/export/csv', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new BDNews24ArticleModel($mysqli);
        $articles = $model->getRecentArticles(1000, 0); // Get up to 1000 articles
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bdnews24-articles-' . date('Y-m-d') . '.csv"');
        
        // Add BOM for UTF-8 in Excel
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // CSV header
        fputcsv($output, ['ID', 'Article ID', 'Title', 'Headline', 'Category', 'URL', 'Image URL', 'Published At', 'Scraped At']);
        
        // CSV rows
        foreach ($articles as $article) {
            fputcsv($output, [
                $article['id'],
                $article['article_id'],
                $article['title'],
                $article['headline'],
                $article['category'] ?? '',
                $article['url'],
                $article['image_url'] ?? '',
                $article['published_at'] ?? '',
                $article['scraped_at'],
            ]);
        }
        
        fclose($output);
        exit;
    } catch (\Exception $e) {
        ErrorLogging::logError("BDNews24Controller: Export CSV error: " . $e->getMessage());
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Failed to export articles';
        exit;
    }
});

// ================== API ENDPOINTS ==================

/**
 * API: Get articles (JSON)
 * GET /api/bdnews24/articles
 */
$router->get('/api/bdnews24/articles', function () use ($mysqli) {
    try {
        $model = new BDNews24ArticleModel($mysqli);
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        
        if ($search) {
            $articles = $model->searchArticles($search, $limit, $offset);
        } elseif ($category) {
            $articles = $model->getArticlesByCategory($category, $limit, $offset);
        } else {
            $articles = $model->getRecentArticles($limit, $offset);
        }
        
        return JsonResponse::success([
            'articles' => $articles,
            'page' => $page,
            'limit' => $limit,
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("BDNews24Controller: API articles error: " . $e->getMessage());
        return JsonResponse::error('Failed to fetch articles', 500);
    }
});

/**
 * API: Get article by ID
 * GET /api/bdnews24/articles/{id}
 */
$router->get('/api/bdnews24/articles/(\d+)', function ($id) use ($mysqli) {
    try {
        $model = new BDNews24ArticleModel($mysqli);
        $article = $model->getArticleById((int)$id);
        
        if (!$article) {
            return JsonResponse::error('Article not found', 404);
        }
        
        return JsonResponse::success(['article' => $article]);
    } catch (\Exception $e) {
        ErrorLogging::logError("BDNews24Controller: API article detail error: " . $e->getMessage());
        return JsonResponse::error('Failed to fetch article', 500);
    }
});

/**
 * API: Get article by article_id
 * GET /api/bdnews24/articles/id/{articleId}
 */
$router->get('/api/bdnews24/articles/id/([a-zA-Z0-9-]+)', function ($articleId) use ($mysqli) {
    try {
        $model = new BDNews24ArticleModel($mysqli);
        $article = $model->getArticleByArticleId($articleId);
        
        if (!$article) {
            return JsonResponse::error('Article not found', 404);
        }
        
        return JsonResponse::success(['article' => $article]);
    } catch (\Exception $e) {
        ErrorLogging::logError("BDNews24Controller: API article by ID error: " . $e->getMessage());
        return JsonResponse::error('Failed to fetch article', 500);
    }
});

/**
 * API: Get categories
 * GET /api/bdnews24/categories
 */
$router->get('/api/bdnews24/categories', function () use ($mysqli) {
    try {
        $model = new BDNews24ArticleModel($mysqli);
        $categories = $model->getCategories();
        
        return JsonResponse::success(['categories' => $categories]);
    } catch (\Exception $e) {
        ErrorLogging::logError("BDNews24Controller: API categories error: " . $e->getMessage());
        return JsonResponse::error('Failed to fetch categories', 500);
    }
});
