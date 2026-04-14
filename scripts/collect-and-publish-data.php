<?php

/**
 * Collect and Publish Data Script
 * Collects data from active scraper sources and publishes the collected data
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Include necessary files
require_once __DIR__ . '/../app/Models/ScraperModel.php';
require_once __DIR__ . '/../app/Modules/Scraper/ScraperService.php';
require_once __DIR__ . '/../app/Models/ContentModel.php';
require_once __DIR__ . '/../app/Models/MobileModel.php';
require_once __DIR__ . '/../Config/Functions.php'; // For logActivity function

use App\Models\ScraperModel;

// Database connection - using same credentials as other scripts
define('DB_HOST', 'localhost');
define('DB_USER', 'tdhuedhn_broxbhai');
define('DB_PASS', ',EnTio1PtqI-&M&D');
define('DB_NAME', 'tdhuedhn_broxbhai');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

echo "Starting data collection and publishing process...\n";
echo "==================================================\n\n";

try {
    $model = new ScraperModel($mysqli);
    $scraperService = new \App\Modules\Scraper\ScraperService($model);

    // Get all active sources
    $activeSources = $model->getActiveSources();
    echo "Found " . count($activeSources) . " active sources\n\n";

    $totalCollected = 0;
    $totalPublished = 0;
    $errors = [];



    foreach ($activeSources as $source) {
        echo "Processing source: {$source['name']} (ID: {$source['id']})\n";
        echo "URL: {$source['url']}\n";

        try {
            // Scrape data from this source
            $result = $scraperService->scrapeSource($source['id']);

            if ($result['success']) {
                echo "✓ Scraping successful\n";
                echo "  - Items found: {$result['stats']['items_found']}\n";
                echo "  - Items saved: {$result['stats']['items_saved']}\n";

                $totalCollected += $result['stats']['items_found'];

                // AI Enhancement step
                echo "  -> AI enhancing collected articles...\n";
                try {
                    $enhancer = new \App\Modules\AutoContent\AiContentEnhancer($mysqli);
                    $enhancementResult = $enhancer->processBatch(10); // Process up to 10 articles

                    if ($enhancementResult['success']) {
                        echo "    ✓ Enhanced {$enhancementResult['processed']} articles (Avg SEO: {$enhancementResult['avg_seo_score']})\n";
                    } else {
                        echo "    ⚠ AI enhancement failed: {$enhancementResult['message']}\n";
                    }
                } catch (Exception $e) {
                    echo "    ⚠ AI enhancement error: " . $e->getMessage() . "\n";
                }

                // Now publish the enhanced data
                $published = publishCollectedData($model, $mysqli, $source['id'], $result);
                $publishedCount = $published['total'] ?? 0;
                echo "  - Items published: {$publishedCount} (Posts: {$published['posts']}, Mobiles: {$published['mobiles']})\n";

                $totalPublished += $publishedCount;

            } else {
                echo "✗ Scraping failed: {$result['error']}\n";
                $errors[] = [
                    'source' => $source['name'],
                    'error' => $result['error'],
                    'type' => 'scraping'
                ];
            }

        } catch (Exception $e) {
            echo "✗ Error processing source: " . $e->getMessage() . "\n";
            $errors[] = [
                'source' => $source['name'],
                'error' => $e->getMessage(),
                'type' => 'exception'
            ];
        }

        echo "\n";
        // Small delay between sources to be respectful
        sleep(1);
    }

    // Summary
    echo "==================================================\n";
    echo "COLLECTION SUMMARY\n";
    echo "==================================================\n";
    echo "Total items collected: $totalCollected\n";
    echo "Total items published: $totalPublished\n";
    echo "Sources processed: " . count($activeSources) . "\n";

    if (!empty($errors)) {
        echo "\nErrors encountered:\n";
        foreach ($errors as $error) {
            echo "- {$error['source']}: {$error['error']} ({$error['type']})\n";
        }
    }

    // Error statistics
    if (isset($result['stats']['errors'])) {
        echo "\nError Statistics:\n";
        $errorStats = $result['stats']['errors'];
        echo "- Total errors: {$errorStats['total']}\n";
        echo "- By type: " . json_encode($errorStats['by_type']) . "\n";
        echo "- By severity: " . json_encode($errorStats['by_severity']) . "\n";
    }

} catch (Exception $e) {
    echo "Critical error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} finally {
    $mysqli->close();
}

/**
 * Publish collected data by creating posts and mobiles in main content tables
 */
function publishCollectedData(ScraperModel $scraperModel, $mysqli, int $sourceId, array $scrapeResult): array
{
    $publishedPosts = 0;
    $publishedMobiles = 0;

    try {
        $contentModel = new \ContentModel($mysqli);
        $mobileModel = new \MobileModel($mysqli);

        // Get recently collected/enhanced articles for this source (don't filter by status)
        $articles = $scraperModel->getArticles(1, 50, null, (string)$sourceId, '', '', null)['articles'] ?? [];
        echo "Found " . count($articles) . " articles for source $sourceId\n";

        // Publish articles as posts (accept any status for recently processed articles)
        foreach ($articles as $article) {
            echo "  - Article {$article['id']}: status='{$article['status']}', title=" . substr($article['title'] ?? 'No title', 0, 50) . "\n";

            // Publish if status is collected, enhanced, or empty (recently processed)
            $status = $article['status'] ?? '';
            if (in_array($status, ['enhanced', 'collected']) || empty($status)) {
                echo "  -> Publishing article {$article['id']} as post\n";

                // Use enhanced content if available, otherwise use original
                $title = $article['title'] ?: 'Untitled Article';
                $content = $article['content'] ?: '';
                $isEnhanced = $article['status'] === 'enhanced';

                if ($isEnhanced) {
                    // Use enhanced versions if available
                    $title = $article['title'] ?? $title;
                    $content = $article['content'] ?? $content;
                    echo "    → Using AI-enhanced content\n";
                }

                // Create post in main posts table
                $postId = $contentModel->createPost(
                    $title,
                    $content,
                    'Auto Scraper', // Default author
                    $contentModel->generateUniquePermalink($title),
                    1, // published = 1
                    null // reader_indexing
                );

                if ($postId) {
                    // Mark the scraped article as published
                    $scraperModel->updateArticleStatus($article['id'], 'published');

                    // Log the publishing activity
                    error_log("Article published as post: Scraped ID {$article['id']} -> Post ID $postId, Source $sourceId, Title: {$article['title']}");

                    $publishedPosts++;
                    echo "    ✓ Created post ID: $postId\n";
                } else {
                    echo "    ✗ Failed to create post for article {$article['id']}\n";
                }
            }
        }

        // Get recently collected mobiles for this source
        $mobiles = $scraperModel->getMobiles(1, 50, (string)$sourceId, '')['mobiles'] ?? [];
        echo "Found " . count($mobiles) . " mobiles for source $sourceId\n";

        // Publish mobiles as mobiles
        foreach ($mobiles as $mobile) {
            // Mobiles don't have a status field like articles, so we'll publish all recent ones
            // Check if collected recently (within last hour to avoid duplicates)
            $collectedTime = strtotime($mobile['created_at']);
            $oneHourAgo = time() - 3600;

            if ($collectedTime >= $oneHourAgo) {
                echo "  -> Publishing mobile {$mobile['id']} as mobile entry\n";

                // Extract specifications from JSON
                $specs = json_decode($mobile['specifications'] ?? '{}', true) ?: [];

                // Create mobile in main mobiles table
                $mobileId = $mobileModel->insertMobile(
                    $mobile['brand'] ?: 'Unknown Brand',
                    $mobile['model'] ?: 'Unknown Model',
                    0, // official_price - will be updated later
                    0, // unofficial_price - will be updated later
                    'official', // default status
                    date('Y-m-d'), // release_date - today
                    1 // is_official
                );

                if ($mobileId) {
                    // Log the publishing activity
                    error_log("Mobile published from scraping: Scraped ID {$mobile['id']} -> Mobile ID $mobileId, Source $sourceId, Brand: {$mobile['brand']}, Model: {$mobile['model']}");

                    $publishedMobiles++;
                    echo "    ✓ Created mobile ID: $mobileId\n";
                } else {
                    echo "    ✗ Failed to create mobile for scraped mobile {$mobile['id']}\n";
                }
            }
        }

    } catch (Exception $e) {
        echo "Error publishing data for source $sourceId: " . $e->getMessage() . "\n";
        error_log("Error publishing data for source $sourceId: " . $e->getMessage());
    }

    return [
        'posts' => $publishedPosts,
        'mobiles' => $publishedMobiles,
        'total' => $publishedPosts + $publishedMobiles
    ];
}



echo "\nProcess completed!\n";

?>