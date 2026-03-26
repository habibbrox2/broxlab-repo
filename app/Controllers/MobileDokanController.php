<?php

declare(strict_types=1);

/**
 * MobileDokanController.php
 * Controller for managing MobileDokan mobile phone scraper
 */

use App\Modules\Scraper\MobileDokanScraperService;
use App\Modules\Scraper\HttpClientService;
use App\Models\MobilePhoneModel;
use App\Helpers\JsonResponse;
use App\Helpers\ErrorLogging;

// ================== DASHBOARD ==================

/**
 * MobileDokan Scraper Dashboard
 * GET /admin/scraper/mobiledokan
 */
$router->get('/admin/scraper/mobiledokan', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new MobilePhoneModel($mysqli);
        
        // Get statistics
        $totalPhones = $model->getTotalCount();
        $recentPhones = $model->getRecentPhones(10);
        $brands = $model->getBrands();
        
        // Get last scrape info (from a settings table or file)
        $lastScrape = null;
        $lastScrapeFile = __DIR__ . '/../Modules/Scraper/logs/mobiledokan_last_scrape.json';
        if (file_exists($lastScrapeFile)) {
            $lastScrape = json_decode(file_get_contents($lastScrapeFile), true);
        }

        return $twig->render('admin/scraper/mobiledokan.twig', [
            'total_phones' => $totalPhones,
            'recent_phones' => $recentPhones,
            'brands' => $brands,
            'last_scrape' => $lastScrape,
            'page_title' => 'MobileDokan Phone Scraper',
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("MobileDokanController: Dashboard error: " . $e->getMessage());
        return $twig->render('error.twig', [
            'error' => 'Failed to load scraper dashboard',
            'page_title' => 'Error',
        ]);
    }
});

// ================== SCRAPE ACTIONS ==================

/**
 * Trigger manual scrape
 * POST /admin/scraper/mobiledokan/scrape
 */
$router->post('/admin/scraper/mobiledokan/scrape', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
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
        $scraper = new MobileDokanScraperService($httpClient);
        $model = new MobilePhoneModel($mysqli);

        // Scrape with progress tracking
        $results = $scraper->scrapeAllPages($maxPages, function ($page, $totalPages, $success, $data) use ($model, $scraper) {
            if ($success) {
                // Save phones to database
                foreach ($data as $phone) {
                    $saveResult = $model->savePhone($phone);
                    if ($saveResult['success']) {
                        $scraper->updateStats('new_phones', 1);
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
        $lastScrapeFile = __DIR__ . '/../Modules/Scraper/logs/mobiledokan_last_scrape.json';
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
            'phones_count' => count($results['phones']),
        ]);

    } catch (\Exception $e) {
        ErrorLogging::logError("MobileDokanController: Scrape error: " . $e->getMessage());
        return JsonResponse::error('Failed to scrape phones: ' . $e->getMessage(), 500);
    }
});

// ================== PHONE LISTING ==================

/**
 * List scraped phones
 * GET /admin/scraper/mobiledokan/phones
 */
$router->get('/admin/scraper/mobiledokan/phones', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new MobilePhoneModel($mysqli);
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $search = $_GET['search'] ?? '';
        $brand = $_GET['brand'] ?? '';
        $minPrice = (int)($_GET['min_price'] ?? 0);
        $maxPrice = (int)($_GET['max_price'] ?? 0);
        
        if ($search) {
            $phones = $model->searchPhones($search, $limit, $offset);
            $total = count($model->searchPhones($search, 1000, 0)); // Approximate total
        } elseif ($brand) {
            $phones = $model->getPhonesByBrand($brand, $limit, $offset);
            $total = $model->getCountByBrand($brand);
        } elseif ($minPrice > 0 || $maxPrice > 0) {
            $phones = $model->getPhonesByPriceRange($minPrice, $maxPrice, $limit, $offset);
            $total = count($model->getPhonesByPriceRange($minPrice, $maxPrice, 1000, 0));
        } else {
            $phones = $model->getRecentPhones($limit, $offset);
            $total = $model->getTotalCount();
        }
        
        $brands = $model->getBrands();
        
        return $twig->render('admin/scraper/mobiledokan-phones.twig', [
            'phones' => $phones,
            'brands' => $brands,
            'current_page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int)ceil($total / $limit),
            'search' => $search,
            'selected_brand' => $brand,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'page_title' => 'MobileDokan Phones',
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("MobileDokanController: Phones list error: " . $e->getMessage());
        return $twig->render('error.twig', [
            'error' => 'Failed to load phones',
            'page_title' => 'Error',
        ]);
    }
});

/**
 * View phone details
 * GET /admin/scraper/mobiledokan/phones/{id}
 */
$router->get('/admin/scraper/mobiledokan/phones/(\d+)', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $model = new MobilePhoneModel($mysqli);
        $phone = $model->getPhoneById((int)$id);
        
        if (!$phone) {
            return $twig->render('error.twig', [
                'error' => 'Phone not found',
                'page_title' => 'Error',
            ]);
        }
        
        // Decode specs JSON for display
        $phone['specs'] = json_decode($phone['specs'], true) ?: [];
        
        return $twig->render('admin/scraper/mobiledokan-phone-detail.twig', [
            'phone' => $phone,
            'page_title' => 'Phone Details',
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("MobileDokanController: Phone detail error: " . $e->getMessage());
        return $twig->render('error.twig', [
            'error' => 'Failed to load phone details',
            'page_title' => 'Error',
        ]);
    }
});

// ================== PHONE MANAGEMENT ==================

/**
 * Delete a phone
 * DELETE /admin/scraper/mobiledokan/phones/{id}
 */
$router->delete('/admin/scraper/mobiledokan/phones/(\d+)', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    try {
        // Validate CSRF token
        if (!validateCsrfToken()) {
            return JsonResponse::error('Invalid CSRF token', 403);
        }

        $model = new MobilePhoneModel($mysqli);
        $result = $model->deletePhone((int)$id);
        
        if ($result['success']) {
            return JsonResponse::success(['message' => 'Phone deleted successfully']);
        } else {
            return JsonResponse::error($result['error'] ?? 'Failed to delete phone', 500);
        }
    } catch (\Exception $e) {
        ErrorLogging::logError("MobileDokanController: Delete phone error: " . $e->getMessage());
        return JsonResponse::error('Failed to delete phone: ' . $e->getMessage(), 500);
    }
});

// ================== EXPORT ==================

/**
 * Export phones to JSON
 * GET /admin/scraper/mobiledokan/export/json
 */
$router->get('/admin/scraper/mobiledokan/export/json', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new MobilePhoneModel($mysqli);
        $phones = $model->getRecentPhones(1000, 0); // Get up to 1000 phones
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="mobiledokan-phones-' . date('Y-m-d') . '.json"');
        
        echo json_encode($phones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } catch (\Exception $e) {
        ErrorLogging::logError("MobileDokanController: Export JSON error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to export phones']);
        exit;
    }
});

/**
 * Export phones to CSV
 * GET /admin/scraper/mobiledokan/export/csv
 */
$router->get('/admin/scraper/mobiledokan/export/csv', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new MobilePhoneModel($mysqli);
        $phones = $model->getRecentPhones(1000, 0); // Get up to 1000 phones
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="mobiledokan-phones-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV header
        fputcsv($output, ['ID', 'Slug', 'Name', 'Brand', 'Price', 'Processor', 'RAM', 'Storage', 'Display', 'Battery', 'URL', 'Image URL', 'Scraped At']);
        
        // CSV rows
        foreach ($phones as $phone) {
            $specs = json_decode($phone['specs'], true) ?: [];
            fputcsv($output, [
                $phone['id'],
                $phone['slug'],
                $phone['name'],
                $phone['brand'],
                $phone['price'],
                $phone['processor'] ?? '',
                $phone['ram'] ?? '',
                $phone['storage'] ?? '',
                $phone['display'] ?? '',
                $phone['battery'] ?? '',
                $phone['url'],
                $phone['image_url'],
                $phone['scraped_at'],
            ]);
        }
        
        fclose($output);
        exit;
    } catch (\Exception $e) {
        ErrorLogging::logError("MobileDokanController: Export CSV error: " . $e->getMessage());
        header('Content-Type: text/plain');
        echo 'Failed to export phones';
        exit;
    }
});

// ================== API ENDPOINTS ==================

/**
 * API: Get phones (JSON)
 * GET /api/mobiledokan/phones
 */
$router->get('/api/mobiledokan/phones', function () use ($mysqli) {
    try {
        $model = new MobilePhoneModel($mysqli);
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $search = $_GET['search'] ?? '';
        $brand = $_GET['brand'] ?? '';
        
        if ($search) {
            $phones = $model->searchPhones($search, $limit, $offset);
        } elseif ($brand) {
            $phones = $model->getPhonesByBrand($brand, $limit, $offset);
        } else {
            $phones = $model->getRecentPhones($limit, $offset);
        }
        
        return JsonResponse::success([
            'phones' => $phones,
            'page' => $page,
            'limit' => $limit,
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("MobileDokanController: API phones error: " . $e->getMessage());
        return JsonResponse::error('Failed to fetch phones', 500);
    }
});

/**
 * API: Get phone by ID
 * GET /api/mobiledokan/phones/{id}
 */
$router->get('/api/mobiledokan/phones/(\d+)', function ($id) use ($mysqli) {
    try {
        $model = new MobilePhoneModel($mysqli);
        $phone = $model->getPhoneById((int)$id);
        
        if (!$phone) {
            return JsonResponse::error('Phone not found', 404);
        }
        
        // Decode specs JSON
        $phone['specs'] = json_decode($phone['specs'], true) ?: [];
        
        return JsonResponse::success(['phone' => $phone]);
    } catch (\Exception $e) {
        ErrorLogging::logError("MobileDokanController: API phone detail error: " . $e->getMessage());
        return JsonResponse::error('Failed to fetch phone', 500);
    }
});

/**
 * API: Get phone by slug
 * GET /api/mobiledokan/phones/slug/{slug}
 */
$router->get('/api/mobiledokan/phones/slug/([a-z0-9-]+)', function ($slug) use ($mysqli) {
    try {
        $model = new MobilePhoneModel($mysqli);
        $phone = $model->getPhoneBySlug($slug);
        
        if (!$phone) {
            return JsonResponse::error('Phone not found', 404);
        }
        
        // Decode specs JSON
        $phone['specs'] = json_decode($phone['specs'], true) ?: [];
        
        return JsonResponse::success(['phone' => $phone]);
    } catch (\Exception $e) {
        ErrorLogging::logError("MobileDokanController: API phone by slug error: " . $e->getMessage());
        return JsonResponse::error('Failed to fetch phone', 500);
    }
});

/**
 * API: Get brands
 * GET /api/mobiledokan/brands
 */
$router->get('/api/mobiledokan/brands', function () use ($mysqli) {
    try {
        $model = new MobilePhoneModel($mysqli);
        $brands = $model->getBrands();
        
        return JsonResponse::success(['brands' => $brands]);
    } catch (\Exception $e) {
        ErrorLogging::logError("MobileDokanController: API brands error: " . $e->getMessage());
        return JsonResponse::error('Failed to fetch brands', 500);
    }
});
