<?php

namespace App\Controllers;

use App\Models\GSMArenaNewsModel;
use App\Models\GSMArenaDeviceModel;
use App\Modules\Scraper\GSMArenaNewsScraperService;
use App\Modules\Scraper\GSMArenaDeviceScraperService;
use App\Helpers\JsonResponse;
use App\Helpers\ErrorLogging;

/**
 * GSMArena Controller
 * 
 * Handles admin and API endpoints for GSMArena news and device scrapers
 * 
 * @package BroxBhai
 * @since 2026-03-26
 */
class GSMArenaController
{
    private GSMArenaNewsModel $newsModel;
    private GSMArenaDeviceModel $deviceModel;
    private GSMArenaNewsScraperService $newsScraper;
    private GSMArenaDeviceScraperService $deviceScraper;

    public function __construct($mysqli = null)
    {
        global $mysqli;
        
        $this->newsModel = new GSMArenaNewsModel($mysqli);
        $this->deviceModel = new GSMArenaDeviceModel($mysqli);
        $this->newsScraper = new GSMArenaNewsScraperService();
        $this->deviceScraper = new GSMArenaDeviceScraperService();
    }

    // ==================== NEWS ENDPOINTS ====================

    /**
     * News dashboard view
     */
    public function newsDashboard()
    {
        $stats = $this->newsScraper->getStats();
        $recentNews = $this->newsModel->getRecentNews(5, 0);
        $totalNews = $this->newsModel->getTotalCount();
        
        require_once 'app/Views/admin/scraper/gsmarena-news.twig';
    }

    /**
     * Trigger news scraping
     */
    public function scrapeNews()
    {
        // CSRF validation
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'] ?? '') {
            return JsonResponse::error('Invalid CSRF token', 403);
        }
        
        $maxPages = isset($_POST['max_pages']) ? min((int)$_POST['max_pages'], 10) : 3;
        
        try {
            $newsItems = $this->newsScraper->scrapeAllPages($maxPages, function($page, $totalPages, $newsItem) {
                // Save to database
                if (!$this->newsModel->existsByNewsId($newsItem['news_id'])) {
                    $this->newsModel->saveNews($newsItem);
                }
            });
            
            return JsonResponse::success([
                'pages_scraped' => $this->newsScraper->getStats()['pages_scraped'],
                'news_found' => $this->newsScraper->getStats()['news_found'],
                'message' => 'News scraping completed successfully'
            ]);
            
        } catch (\Exception $e) {
            ErrorLogging::logError('GSMArena News Scraper', $e->getMessage());
            return JsonResponse::error('Failed to scrape news: ' . $e->getMessage(), 500);
        }
    }

    /**
     * News articles list view
     */
    public function newsArticles()
    {
        $page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
        $offset = ($page - 1) * $limit;
        
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        
        if (!empty($search)) {
            $news = $this->newsModel->searchNews($search, $limit, $offset);
            $total = $this->newsModel->getTotalCount(); // Simplified for search
        } else {
            $news = $this->newsModel->getRecentNews($limit, $offset);
            $total = $this->newsModel->getTotalCount();
        }
        
        require_once 'app/Views/admin/scraper/gsmarena-news-articles.twig';
    }

    /**
     * News article detail view
     */
    public function newsArticleDetail($id)
    {
        $article = $this->newsModel->getNewsById((int)$id);
        
        if (!$article) {
            return JsonResponse::error('Article not found', 404);
        }
        
        require_once 'app/Views/admin/scraper/gsmarena-news-article-detail.twig';
    }

    /**
     * Delete news article
     */
    public function deleteNews($id)
    {
        // CSRF validation
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'] ?? '') {
            return JsonResponse::error('Invalid CSRF token', 403);
        }
        
        try {
            $result = $this->newsModel->deleteNews((int)$id);
            
            if ($result) {
                return JsonResponse::success(['message' => 'Article deleted successfully']);
            } else {
                return JsonResponse::error('Failed to delete article', 500);
            }
            
        } catch (\Exception $e) {
            ErrorLogging::logError('GSMArena News Delete', $e->getMessage());
            return JsonResponse::error('Failed to delete article: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export news as JSON
     */
    public function exportNewsJson()
    {
        $news = $this->newsModel->getRecentNews(1000, 0);
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="gsmarena-news-' . date('Y-m-d') . '.json"');
        
        echo json_encode($news, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Export news as CSV
     */
    public function exportNewsCsv()
    {
        $news = $this->newsModel->getRecentNews(1000, 0);
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="gsmarena-news-' . date('Y-m-d') . '.csv"');
        
        // BOM for UTF-8
        echo "\xEF\xBB\xBF";
        
        // CSV header
        echo "ID,News ID,URL,Title,Summary,Image URL,Published Date,Scraped At,Updated At\n";
        
        foreach ($news as $item) {
            $escapedId = $this->escapeCsv($item['id']);
            $escapedNewsId = $this->escapeCsv($item['news_id']);
            $escapedUrl = $this->escapeCsv($item['url']);
            $escapedTitle = $this->escapeCsv($item['title']);
            $escapedSummary = $this->escapeCsv($item['summary'] ?? '');
            $escapedImageUrl = $this->escapeCsv($item['image_url'] ?? '');
            $escapedPublishedAt = $this->escapeCsv($item['published_at'] ?? '');
            $escapedScrapedAt = $this->escapeCsv($item['scraped_at']);
            $escapedUpdatedAt = $this->escapeCsv($item['updated_at']);
            
            echo "{$escapedId},{$escapedNewsId},{$escapedUrl},{$escapedTitle},{$escapedSummary},{$escapedImageUrl},{$escapedPublishedAt},{$escapedScrapedAt},{$escapedUpdatedAt}\n";
        }
        
        exit;
    }

    // ==================== DEVICE ENDPOINTS ====================

    /**
     * Device dashboard view
     */
    public function deviceDashboard()
    {
        $stats = $this->deviceScraper->getStats();
        $recentDevices = $this->deviceModel->getRecentDevices(5, 0);
        $totalDevices = $this->deviceModel->getTotalCount();
        $brands = $this->deviceModel->getBrands();
        
        require_once 'app/Views/admin/scraper/gsmarena-devices.twig';
    }

    /**
     * Trigger device scraping
     */
    public function scrapeDevices()
    {
        // CSRF validation
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'] ?? '') {
            return JsonResponse::error('Invalid CSRF token', 403);
        }
        
        $maxPages = isset($_POST['max_pages']) ? min((int)$_POST['max_pages'], 10) : 3;
        
        try {
            $devices = $this->deviceScraper->scrapeAllPages($maxPages, function($page, $totalPages, $device) {
                // Save to database
                if (!$this->deviceModel->existsBySlug($device['slug'])) {
                    $this->deviceModel->saveDevice($device);
                }
            });
            
            return JsonResponse::success([
                'pages_scraped' => $this->deviceScraper->getStats()['pages_scraped'],
                'devices_found' => $this->deviceScraper->getStats()['devices_found'],
                'message' => 'Device scraping completed successfully'
            ]);
            
        } catch (\Exception $e) {
            ErrorLogging::logError('GSMArena Device Scraper', $e->getMessage());
            return JsonResponse::error('Failed to scrape devices: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Devices list view
     */
    public function devices()
    {
        $page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
        $offset = ($page - 1) * $limit;
        
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $brand = isset($_GET['brand']) ? trim($_GET['brand']) : '';
        
        if (!empty($search)) {
            $devices = $this->deviceModel->searchDevices($search, $limit, $offset);
            $total = $this->deviceModel->getTotalCount(); // Simplified for search
        } elseif (!empty($brand)) {
            $devices = $this->deviceModel->getDevicesByBrand($brand, $limit, $offset);
            $total = $this->deviceModel->getCountByBrand($brand);
        } else {
            $devices = $this->deviceModel->getRecentDevices($limit, $offset);
            $total = $this->deviceModel->getTotalCount();
        }
        
        require_once 'app/Views/admin/scraper/gsmarena-devices-list.twig';
    }

    /**
     * Device detail view
     */
    public function deviceDetail($id)
    {
        $device = $this->deviceModel->getDeviceById((int)$id);
        
        if (!$device) {
            return JsonResponse::error('Device not found', 404);
        }
        
        require_once 'app/Views/admin/scraper/gsmarena-device-detail.twig';
    }

    /**
     * Delete device
     */
    public function deleteDevice($id)
    {
        // CSRF validation
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'] ?? '') {
            return JsonResponse::error('Invalid CSRF token', 403);
        }
        
        try {
            $result = $this->deviceModel->deleteDevice((int)$id);
            
            if ($result) {
                return JsonResponse::success(['message' => 'Device deleted successfully']);
            } else {
                return JsonResponse::error('Failed to delete device', 500);
            }
            
        } catch (\Exception $e) {
            ErrorLogging::logError('GSMArena Device Delete', $e->getMessage());
            return JsonResponse::error('Failed to delete device: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export devices as JSON
     */
    public function exportDevicesJson()
    {
        $devices = $this->deviceModel->getRecentDevices(1000, 0);
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="gsmarena-devices-' . date('Y-m-d') . '.json"');
        
        echo json_encode($devices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Export devices as CSV
     */
    public function exportDevicesCsv()
    {
        $devices = $this->deviceModel->getRecentDevices(1000, 0);
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="gsmarena-devices-' . date('Y-m-d') . '.csv"');
        
        // BOM for UTF-8
        echo "\xEF\xBB\xBF";
        
        // CSV header
        echo "ID,Slug,Name,Brand,URL,Image URL,Released,Body,SIM,OS,Display Size,Display Resolution,Display Type,CPU,RAM,Storage,Main Camera,Battery Capacity,Scraped At,Updated At\n";
        
        foreach ($devices as $item) {
            $specs = $item['specs'] ?? [];
            $escapedId = $this->escapeCsv($item['id']);
            $escapedSlug = $this->escapeCsv($item['slug']);
            $escapedName = $this->escapeCsv($item['name']);
            $escapedBrand = $this->escapeCsv($item['brand']);
            $escapedUrl = $this->escapeCsv($item['url']);
            $escapedImageUrl = $this->escapeCsv($item['image_url'] ?? '');
            $escapedReleased = $this->escapeCsv($item['released'] ?? '');
            $escapedBody = $this->escapeCsv($item['body'] ?? '');
            $escapedSim = $this->escapeCsv($item['sim'] ?? '');
            $escapedOs = $this->escapeCsv($item['os'] ?? '');
            $escapedDisplaySize = $this->escapeCsv($item['display_size'] ?? '');
            $escapedDisplayResolution = $this->escapeCsv($item['display_resolution'] ?? '');
            $escapedDisplayType = $this->escapeCsv($item['display_type'] ?? '');
            $escapedCpu = $this->escapeCsv($item['cpu'] ?? '');
            $escapedRam = $this->escapeCsv($item['ram'] ?? '');
            $escapedStorage = $this->escapeCsv($item['storage'] ?? '');
            $escapedMainCamera = $this->escapeCsv($item['main_camera'] ?? '');
            $escapedBatteryCapacity = $this->escapeCsv($item['battery_capacity'] ?? '');
            $escapedScrapedAt = $this->escapeCsv($item['scraped_at']);
            $escapedUpdatedAt = $this->escapeCsv($item['updated_at']);
            
            echo "{$escapedId},{$escapedSlug},{$escapedName},{$escapedBrand},{$escapedUrl},{$escapedImageUrl},{$escapedReleased},{$escapedBody},{$escapedSim},{$escapedOs},{$escapedDisplaySize},{$escapedDisplayResolution},{$escapedDisplayType},{$escapedCpu},{$escapedRam},{$escapedStorage},{$escapedMainCamera},{$escapedBatteryCapacity},{$escapedScrapedAt},{$escapedUpdatedAt}\n";
        }
        
        exit;
    }

    // ==================== PUBLIC API ENDPOINTS ====================

    /**
     * Get all news articles (API)
     */
    public function apiNews()
    {
        $page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
        $offset = ($page - 1) * $limit;
        
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        
        if (!empty($search)) {
            $news = $this->newsModel->searchNews($search, $limit, $offset);
            $total = $this->newsModel->getTotalCount();
        } else {
            $news = $this->newsModel->getRecentNews($limit, $offset);
            $total = $this->newsModel->getTotalCount();
        }
        
        return JsonResponse::success([
            'data' => $news,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Get news article by ID (API)
     */
    public function apiNewsById($id)
    {
        $article = $this->newsModel->getNewsById((int)$id);
        
        if (!$article) {
            return JsonResponse::error('Article not found', 404);
        }
        
        return JsonResponse::success($article);
    }

    /**
     * Get news article by news ID (API)
     */
    public function apiNewsByNewsId($newsId)
    {
        $article = $this->newsModel->getNewsByNewsId($newsId);
        
        if (!$article) {
            return JsonResponse::error('Article not found', 404);
        }
        
        return JsonResponse::success($article);
    }

    /**
     * Get all devices (API)
     */
    public function apiDevices()
    {
        $page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
        $offset = ($page - 1) * $limit;
        
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $brand = isset($_GET['brand']) ? trim($_GET['brand']) : '';
        
        if (!empty($search)) {
            $devices = $this->deviceModel->searchDevices($search, $limit, $offset);
            $total = $this->deviceModel->getTotalCount();
        } elseif (!empty($brand)) {
            $devices = $this->deviceModel->getDevicesByBrand($brand, $limit, $offset);
            $total = $this->deviceModel->getCountByBrand($brand);
        } else {
            $devices = $this->deviceModel->getRecentDevices($limit, $offset);
            $total = $this->deviceModel->getTotalCount();
        }
        
        return JsonResponse::success([
            'data' => $devices,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Get device by ID (API)
     */
    public function apiDeviceById($id)
    {
        $device = $this->deviceModel->getDeviceById((int)$id);
        
        if (!$device) {
            return JsonResponse::error('Device not found', 404);
        }
        
        return JsonResponse::success($device);
    }

    /**
     * Get device by slug (API)
     */
    public function apiDeviceBySlug($slug)
    {
        $device = $this->deviceModel->getDeviceBySlug($slug);
        
        if (!$device) {
            return JsonResponse::error('Device not found', 404);
        }
        
        return JsonResponse::success($device);
    }

    /**
     * Get all brands (API)
     */
    public function apiBrands()
    {
        $brands = $this->deviceModel->getBrands();
        
        return JsonResponse::success([
            'data' => $brands,
            'total' => count($brands)
        ]);
    }

    /**
     * Escape CSV value
     */
    private function escapeCsv($value): string
    {
        $value = str_replace('"', '""', $value);
        return '"' . $value . '"';
    }
}
