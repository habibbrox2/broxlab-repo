<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use App\Models\ScraperModel;
use App\Modules\Scraper\Scrapers\AdvanceScraper;
use Exception;

/**
 * ScraperService - Main service for web scraping operations
 *
 * Handles the coordination between different scraping libraries and the database.
 * Provides methods for testing, scraping, and running sources.
 */
class ScraperService
{
    private ScraperModel $model;
    private AdvanceScraper $advanceScraper;

    public function __construct(ScraperModel $model)
    {
        $this->model = $model;
        $this->advanceScraper = new AdvanceScraper();
    }

    /**
     * Test a scraping source
     *
     * @param int $sourceId
     * @return array
     */
    public function testSource(int $sourceId): array
    {
        try {
            $source = $this->model->getSourceById($sourceId);
            if (!$source) {
                throw new Exception('Source not found');
            }

            // Use AdvanceScraper to test the source
            $this->advanceScraper->setSource($source);

            // Try to scrape a limited amount for testing
            $result = $this->advanceScraper->scrape();

            $itemsFound = 0;
            if ($result['success'] && isset($result['data'])) {
                $data = $result['data'];
                if (isset($data['links']) && is_array($data['links'])) {
                    $itemsFound = count($data['links']);
                } elseif (isset($data['content']) && !empty($data['content'])) {
                    $itemsFound = 1; // Single content item
                }
            }

            return [
                'source_id' => $sourceId,
                'source_name' => $source['name'],
                'success' => $result['success'],
                'items_found' => $itemsFound,
                'library_used' => $result['strategy_used'] ?? 'unknown',
                'errors' => $result['success'] ? [] : [$result['error'] ?? 'Unknown error'],
                'test_url' => $source['url']
            ];
        } catch (Exception $e) {
            return [
                'source_id' => $sourceId,
                'success' => false,
                'items_found' => 0,
                'errors' => [$e->getMessage()],
                'test_url' => null
            ];
        }
    }

    /**
     * Scrape a source and store results
     *
     * @param int $sourceId
     * @param array $options Additional options for scraping
     * @return array
     */
    public function scrapeSource(int $sourceId, array $options = []): array
    {
        try {
            $source = $this->model->getSourceById($sourceId);
            if (!$source) {
                throw new Exception('Source not found');
            }

            // Create a job record
            $jobId = $this->model->createJob([
                'source_id' => $sourceId,
                'job_type' => 'full', // Use 'full' instead of 'scrape' to match enum values
                'priority' => 5
            ]);

            try {
                // Start timing
                $scrapeStartTime = microtime(true);

                // Perform the scrape
                $this->advanceScraper->setSource($source);
                $result = $this->advanceScraper->scrape();

                // Calculate execution time
                $scrapeExecutionTime = round(microtime(true) - $scrapeStartTime, 2);

                if ($result['success']) {
                    // Store the scraped data and get the save result
                    $itemsSaved = $this->storeScrapedData($sourceId, $result['data'], $source) ? 1 : 0;

                    // Update job status
                    $this->model->updateJobResult($jobId, 'completed', [
                        'items_found' => count($result['data']['links'] ?? []),
                        'items_saved' => $itemsSaved,
                        'items_failed' => $itemsSaved ? 0 : 1
                    ]);

                    return [
                        'success' => true,
                        'job_id' => $jobId,
                        'stats' => [
                            'items_saved' => $itemsSaved,
                            'items_found' => count($result['data']['links'] ?? []) ?: $itemsSaved,
                            'items_failed' => $itemsSaved ? 0 : 1,
                            'duration' => $scrapeExecutionTime,
                            'pages_scraped' => 1 // Single page scrape
                        ],
                        'message' => 'Scraping completed successfully'
                    ];
                } else {
                    // Update job status to failed
                    $this->model->updateJobResult($jobId, 'failed', [
                        'error_message' => $result['error'] ?? 'Unknown error'
                    ]);

                    return [
                        'success' => false,
                        'job_id' => $jobId,
                        'stats' => [
                            'items_saved' => 0,
                            'items_found' => 0,
                            'items_failed' => 1,
                            'duration' => $scrapeExecutionTime,
                            'pages_scraped' => 0
                        ],
                        'error' => $result['error'] ?? 'Scraping failed'
                    ];
                }
            } catch (Exception $e) {
                // Update job status to failed
                $this->model->updateJobResult($jobId, 'failed', [
                    'error_message' => $e->getMessage()
                ]);
                throw $e;
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Run a source (alias for scrapeSource for backward compatibility)
     *
     * @param int $sourceId
     * @return array
     */
    public function runSource(int $sourceId): array
    {
        return $this->scrapeSource($sourceId);
    }

    /**
     * Store scraped data in the database
     *
     * @param int $sourceId
     * @param array $data
     * @param array $source
     * @return bool True if data was saved successfully
     */
    private function storeScrapedData(int $sourceId, array $data, array $source): bool
    {
        $saved = false;

        // Store articles
        if (!empty($data['content'])) {
            $imageUrl = null;
            if (!empty($data['images']) && is_array($data['images']) && isset($data['images'][0]['src'])) {
                $imageUrl = $data['images'][0]['src'];
            }

            $articleData = [
                'source_id' => $sourceId,
                'title' => $data['title'] ?? 'Untitled',
                'content' => $data['content'],
                'url' => $source['url'],
                'image_url' => $imageUrl,
                'status' => 'collected',
                'content_hash' => hash('sha256', $data['content']),
                'excerpt' => mb_substr($data['content'], 0, 200, 'UTF-8') . (mb_strlen($data['content'], 'UTF-8') > 200 ? '...' : ''),
                'categories' => [],
                'tags' => []
            ];

            $saved = $this->model->saveArticle($articleData);
        }

        // Store mobile data if applicable
        if (($source['content_type'] ?? 'article') === 'mobile' && !empty($data['title'])) {
            $mobileSaved = $this->model->saveMobile([
                'source_id' => $sourceId,
                'source_url' => $source['url'],
                'title' => $data['title'],
                'brand' => $this->extractBrandFromTitle($data['title']),
                'model' => $this->extractModelFromTitle($data['title']),
                'image_url' => $data['images'][0]['src'] ?? null,
                'specifications' => $data,
            ]);
            $saved = $saved || $mobileSaved;
        }

        return $saved;
    }

    /**
     * Extract brand from mobile title
     */
    private function extractBrandFromTitle(string $title): string
    {
        $brands = ['Samsung', 'Apple', 'Google', 'OnePlus', 'Xiaomi', 'Huawei', 'Sony', 'LG', 'Motorola', 'Nokia', 'Oppo', 'Vivo', 'Realme'];
        foreach ($brands as $brand) {
            if (stripos($title, $brand) !== false) {
                return $brand;
            }
        }
        return '';
    }

    /**
     * Extract model from mobile title
     */
    private function extractModelFromTitle(string $title): string
    {
        // Remove brand from title to get model
        $brand = $this->extractBrandFromTitle($title);
        if ($brand) {
            $title = trim(str_ireplace($brand, '', $title));
        }
        // Extract model number or name
        if (preg_match('/([A-Za-z0-9\-\s]+(?:Pro|Plus|Max|Ultra|Lite|Mini)?)/', $title, $matches)) {
            return trim($matches[1]);
        }
        return trim($title);
    }
}
