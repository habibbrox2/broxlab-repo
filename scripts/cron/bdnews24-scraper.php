#!/usr/bin/env php
<?php
/**
 * BDNews24 Article Scraper - Cron Job Script
 * 
 * This script runs the BDNews24 scraper to collect articles from bangla.bdnews24.com/special
 * It can be scheduled via cPanel cron jobs or Linux crontab.
 * 
 * Usage:
 *   php scripts/cron/bdnews24-scraper.php [--max-pages=N] [--verbose]
 * 
 * Examples:
 *   php scripts/cron/bdnews24-scraper.php                    # Scrape with default settings
 *   php scripts/cron/bdnews24-scraper.php --max-pages=5       # Scrape max 5 pages
 *   php scripts/cron/bdnews24-scraper.php --verbose           # Show detailed output
 * 
 * @package BroxBhai
 * @since 2026-03-26
 */

// Change to project root directory
chdir(__DIR__ . '/../..');

// Load dependencies
require_once 'vendor/autoload.php';
require_once 'public_html/_db.php';

use App\Modules\Scraper\BDNews24ScraperService;
use App\Models\BDNews24ArticleModel;

// Parse command line arguments
$options = getopt('', ['max-pages:', 'verbose']);
$maxPages = isset($options['max-pages']) ? (int)$options['max-pages'] : 10;
$verbose = isset($options['verbose']);

// Configuration
$lastScrapeFile = __DIR__ . '/../../logs/bdnews24-last-scrape.json';
$logFile = __DIR__ . '/../../logs/bdnews24-scraper.log';
$adminEmails = ['admin@broxlab.com']; // Update with actual admin emails

/**
 * Log message to file and optionally to console
 */
function logMessage(string $message, bool $verbose = false): void
{
    global $logFile, $verbose;
    
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$message}\n";
    
    // Write to log file
    file_put_contents($logFile, $logLine, FILE_APPEND);
    
    // Output to console if verbose mode
    if ($verbose) {
        echo $logLine;
    }
}

/**
 * Send email notification to admins
 */
function sendNotification(string $subject, string $message): void
{
    global $adminEmails;
    
    $headers = [
        'From: BDNews24 Scraper <noreply@broxlab.com>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    foreach ($adminEmails as $email) {
        mail($email, $subject, $message, implode("\r\n", $headers));
    }
}

/**
 * Get last scrape information
 */
function getLastScrapeInfo(): array
{
    global $lastScrapeFile;
    
    if (!file_exists($lastScrapeFile)) {
        return [
            'last_scrape' => null,
            'total_articles' => 0,
            'last_article_id' => null
        ];
    }
    
    $data = file_get_contents($lastScrapeFile);
    return json_decode($data, true) ?: [];
}

/**
 * Save last scrape information
 */
function saveLastScrapeInfo(array $info): void
{
    global $lastScrapeFile;
    
    $info['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents($lastScrapeFile, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Start scraping
logMessage("=== BDNews24 Scraper Started ===", true);
logMessage("Max pages: {$maxPages}", true);

try {
    // Initialize database connection
    $mysqli = new mysqli(
        getenv('DB_HOST') ?: 'localhost',
        getenv('DB_USER') ?: 'root',
        getenv('DB_PASS') ?: '',
        getenv('DB_NAME') ?: 'broxlab'
    );
    
    if ($mysqli->connect_error) {
        throw new Exception("Database connection failed: " . $mysqli->connect_error);
    }
    
    logMessage("Database connected successfully", true);
    
    // Initialize model and service
    $model = new BDNews24ArticleModel($mysqli);
    $service = new BDNews24ScraperService();
    
    // Get last scrape info
    $lastScrapeInfo = getLastScrapeInfo();
    $lastScrapeDate = $lastScrapeInfo['last_scrape'] ?? null;
    
    logMessage("Last scrape: " . ($lastScrapeDate ?? 'Never'), true);
    
    // Track statistics
    $stats = [
        'pages_scraped' => 0,
        'articles_found' => 0,
        'articles_new' => 0,
        'articles_updated' => 0,
        'errors' => 0
    ];
    
    // Progress callback
    $progressCallback = function($page, $totalPages, $article) use (&$stats, $model, $verbose) {
        $stats['pages_scraped'] = $page;
        $stats['articles_found']++;
        
        if ($verbose) {
            echo "Page {$page}/{$totalPages}: Found article - {$article['title']}\n";
        }
        
        // Check if article already exists
        $existing = $model->getArticleByArticleId($article['article_id']);
        
        if ($existing) {
            // Update existing article
            $article['id'] = $existing['id'];
            $model->updateArticle($article);
            $stats['articles_updated']++;
            logMessage("Updated article: {$article['article_id']} - {$article['title']}", $verbose);
        } else {
            // Save new article
            $model->saveArticle($article);
            $stats['articles_new']++;
            logMessage("New article: {$article['article_id']} - {$article['title']}", $verbose);
        }
    };
    
    // Run scraper
    logMessage("Starting scrape...", true);
    $result = $service->scrapeAllPages($maxPages, $progressCallback);
    
    // Update service stats
    $serviceStats = $service->getStats();
    $stats['pages_scraped'] = $serviceStats['pages_scraped'] ?? 0;
    
    logMessage("Scrape completed", true);
    logMessage("Pages scraped: {$stats['pages_scraped']}", true);
    logMessage("Articles found: {$stats['articles_found']}", true);
    logMessage("New articles: {$stats['articles_new']}", true);
    logMessage("Updated articles: {$stats['articles_updated']}", true);
    
    // Save last scrape info
    saveLastScrapeInfo([
        'last_scrape' => date('Y-m-d H:i:s'),
        'total_articles' => $model->getTotalCount(),
        'last_article_id' => $result['last_article_id'] ?? null
    ]);
    
    // Send notification if new articles found
    if ($stats['articles_new'] > 0) {
        $subject = "BDNews24 Scraper: {$stats['articles_new']} New Articles Found";
        $message = "BDNews24 scraper found {$stats['articles_new']} new articles.\n\n";
        $message .= "Summary:\n";
        $message .= "- Pages scraped: {$stats['pages_scraped']}\n";
        $message .= "- Total articles found: {$stats['articles_found']}\n";
        $message .= "- New articles: {$stats['articles_new']}\n";
        $message .= "- Updated articles: {$stats['articles_updated']}\n";
        $message .= "- Total articles in database: {$model->getTotalCount()}\n\n";
        $message .= "Scrape time: " . date('Y-m-d H:i:s') . "\n";
        $message .= "View articles: https://broxlab.com/admin/scraper/bdnews24/articles\n";
        
        sendNotification($subject, $message);
        logMessage("Notification sent to admins", true);
    }
    
    logMessage("=== BDNews24 Scraper Completed Successfully ===", true);
    
    // Exit with success code
    exit(0);
    
} catch (Exception $e) {
    $errorMsg = "Error: " . $e->getMessage();
    logMessage($errorMsg, true);
    logMessage("=== BDNews24 Scraper Failed ===", true);
    
    // Send error notification
    $subject = "BDNews24 Scraper Error";
    $message = "BDNews24 scraper encountered an error:\n\n";
    $message .= "Error: " . $e->getMessage() . "\n";
    $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $message .= "Script: " . __FILE__ . "\n";
    
    sendNotification($subject, $message);
    
    // Exit with error code
    exit(1);
}
