<?php

namespace App\Modules\AutoContent;

use App\Models\AutoContentModel;
use App\Models\ScraperModel;
use App\Modules\Scraper\ScraperService;
use Exception;

class CronWorker
{
    private $mysqli;
    private $config;
    private $model;
    private $scraperService;

    public function __construct($mysqli, array $config = [])
    {
        $this->mysqli = $mysqli;
        $this->config = $config;
        $this->model = new AutoContentModel($mysqli);
        $scraperModel = new ScraperModel($mysqli);
        $this->scraperService = new ScraperService($scraperModel);
    }

    /**
     * Run the collection process
     */
    public function run(): array
    {
        $result = [
            'sources_processed' => 0,
            'articles_created' => 0,
            'duplicates_skipped' => 0,
            'errors' => []
        ];

        try {
            // Ensure tables exist
            $this->model->ensureTablesExist();

            // Get active sources from database
            $sources = $this->model->getActiveSources();

            $maxSources = $this->config['max_sources_per_run'] ?? 20;
            $maxArticlesPerSource = $this->config['max_articles_per_source'] ?? 10;
            $testMode = $this->config['test_mode'] ?? false;

            $processed = 0;

            foreach ($sources as $source) {
                if ($processed >= $maxSources) {
                    break;
                }

                try {
                    $sourceResult = $this->processSource($source, $maxArticlesPerSource, $testMode);
                    $result['sources_processed']++;
                    $result['articles_created'] += $sourceResult['articles_created'];
                    $result['duplicates_skipped'] += $sourceResult['duplicates_skipped'];
                    if (!empty($sourceResult['errors'])) {
                        $result['errors'] = array_merge($result['errors'], $sourceResult['errors']);
                    }
                    $processed++;
                } catch (Exception $e) {
                    $result['errors'][] = "Source {$source['id']}: " . $e->getMessage();
                }
            }
        } catch (Exception $e) {
            $result['errors'][] = "Worker error: " . $e->getMessage();
        }

        return $result;
    }

    private function processSource(array $source, int $maxArticles, bool $testMode): array
    {
        $result = [
            'articles_created' => 0,
            'duplicates_skipped' => 0,
            'errors' => []
        ];

        if ($testMode) {
            // In test mode, create a dummy article
            $articleData = [
                'source_id' => $source['id'],
                'title' => "Test Article from {$source['name']}",
                'content' => "This is a test article collected from {$source['url']}",
                'excerpt' => "Test excerpt",
                'url' => $source['url'] . '/test-article-' . time(),
                'published_at' => date('Y-m-d H:i:s'),
                'metadata' => ['test' => true, 'source' => $source['name']]
            ];

            if (!$this->model->articleExists($articleData['url'])) {
                $this->model->insertArticle($articleData);
                $result['articles_created']++;
            } else {
                $result['duplicates_skipped']++;
            }

            // In test mode we still mark the source as fetched so scheduling behaves realistically.
            $this->model->updateSourceLastCollected((int)$source['id']);
        } else {
            // Delegate to the scraper service for production workloads
            try {
                $scrapeResult = $this->scraperService->scrapeSource((int)$source['id'], [
                    'job_type' => 'scheduled',
                    'priority' => 5,
                    'max_items' => $maxArticles,
                ]);

                if (empty($scrapeResult['success'])) {
                    $message = $scrapeResult['error'] ?? 'Unknown scraper error';
                    $result['errors'][] = "Source {$source['id']} ({$source['name']}): {$message}";
                    return $result;
                }

                $stats = is_array($scrapeResult['stats'] ?? null) ? $scrapeResult['stats'] : [];
                $itemsFound = (int)($stats['items_found'] ?? 0);
                $itemsSaved = (int)($stats['items_saved'] ?? 0);
                $itemsFailed = (int)($stats['items_failed'] ?? 0);

                $result['articles_created'] += max(0, $itemsSaved);
                $result['duplicates_skipped'] += max(0, $itemsFound - $itemsSaved - $itemsFailed);

                // Update last collected time only after a successful scrape run.
                $this->model->updateSourceLastCollected((int)$source['id']);
            } catch (Exception $e) {
                $result['errors'][] = "Source {$source['id']} ({$source['name']}): " . $e->getMessage();
            }
        }

        return $result;
    }
}
