<?php
// AutoBlog Collect Script - Run via cron to collect articles from sources
// Cron: */15 * * * * php /path/to/scripts/autoblog_collect.php

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

require_once __DIR__ . '/../Config/Db.php';
require_once __DIR__ . '/../app/Models/AutoBlogModel.php';

use App\Modules\Scraper\ScraperService;
use App\Modules\Scraper\EnhancedScraperService;
use App\Modules\Scraper\ContentCleanerService;
use App\Modules\Scraper\DuplicateCheckerService;
use App\Modules\Scraper\ImageDownloaderService;
use App\Modules\AutoBlog\TelegramNotifier;

$config = require __DIR__ . '/../Config/AutoBlog.php';

// Get database connection
global $mysqli;

$model = new AutoBlogModel($mysqli);
$settings = $model->getSettings();

// Get active sources
$sources = $model->getActiveSources();

if (empty($sources)) {
    echo "[" . date('Y-m-d H:i:s') . "] No active sources found\n";
    exit(0);
}

$collected = 0;
$duplicates = 0;
$errors = [];

// Initialize services
$scraper = new ScraperService();
$enhancedScraper = new EnhancedScraperService();
$contentCleaner = new ContentCleanerService();
$duplicateChecker = new DuplicateCheckerService($mysqli);
$imageDownloader = new ImageDownloaderService();

$maxPerSource = (int)($settings['max_articles_per_source'] ?? 10);

// Initialize Telegram if enabled
$telegramEnabled = !empty($config['telegram']['enabled']) && $config['telegram']['post_on_collect'];
$telegram = null;
if ($telegramEnabled) {
    $telegram = new TelegramNotifier($config['telegram']);
}

foreach ($sources as $source) {
    // Check if source should be fetched based on interval
    $interval = (int)($source['fetch_interval'] ?? 3600);
    $lastFetched = strtotime($source['last_fetched_at'] ?? '1970-01-01');
    
    if (time() - $lastFetched < $interval) {
        continue;
    }

    echo "Processing source: {$source['name']}\n";

    try {
        // Use specialized scraper for known sites
        if (stripos($source['url'] ?? '', 'prothomalo.com') !== false) {
            require_once __DIR__ . '/../app/Modules/Scraper/ProthomAloScraperService.php';
            $prothomScraper = new ProthomAloScraperService(
                $enhancedScraper,
                $contentCleaner,
                $duplicateChecker,
                $imageDownloader,
                $mysqli
            );
            $result = $prothomScraper->scrapeHomepage($source['url']);
            
            if ($result['success']) {
                $collected += $result['articles_saved'];
                $duplicates += $result['duplicates_found'];
            }
        } else {
            // General scraping
            $result = $scraper->scrape($source['url']);
            
            if (!isset($result['error']) && !empty($result['title'])) {
                // Check for duplicates
                if (!$duplicateChecker->urlExists($result['url'])) {
                    $articleData = [
                        'source_id' => $source['id'],
                        'title' => $result['title'],
                        'url' => $result['url'],
                        'content' => $result['description'] ?? '',
                        'excerpt' => substr($result['description'] ?? '', 0, 200),
                        'image_url' => $result['image'] ?? '',
                        'author' => '',
                        'published_at' => date('Y-m-d H:i:s'),
                        'status' => 'collected'
                    ];
                    
                    $model->createArticle($articleData);
                    $collected++;
                } else {
                    $duplicates++;
                }
            }
        }

        // Update last fetched time
        $model->updateLastFetched((int)$source['id']);

    } catch (Exception $e) {
        $errors[] = "Source {$source['name']}: " . $e->getMessage();
        error_log("Autoblog Collect Error: " . $e->getMessage());
    }
}

$output = sprintf(
    "[%s] collected=%d duplicates=%d errors=%d\n",
    date('Y-m-d H:i:s'),
    $collected,
    $duplicates,
    count($errors)
);

echo $output;

// Log to file
if (!empty($config['cron']['log_path'])) {
    file_put_contents($config['cron']['log_path'], $output, FILE_APPEND);
    if (!empty($errors)) {
        file_put_contents($config['cron']['log_path'], implode("\n", $errors) . "\n", FILE_APPEND);
    }
}

exit(0);
