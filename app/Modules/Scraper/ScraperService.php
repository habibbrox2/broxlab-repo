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

            return [
                'source_id' => $sourceId,
                'source_name' => $source['name'],
                'success' => $result['success'],
                'items_found' => $result['success'] ? count($result['data']['links'] ?? []) : 0,
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
     * @return array
     */
    public function scrapeSource(int $sourceId): array
    {
        try {
            $source = $this->model->getSourceById($sourceId);
            if (!$source) {
                throw new Exception('Source not found');
            }

            // Create a job record
            $jobId = $this->model->createJob([
                'source_id' => $sourceId,
                'job_type' => 'scrape',
                'priority' => 5
            ]);

            try {
                // Perform the scrape
                $this->advanceScraper->setSource($source);
                $result = $this->advanceScraper->scrape();

                if ($result['success']) {
                    // Store the scraped data
                    $this->storeScrapedData($sourceId, $result['data'], $source);

                    // Update job status
                    $this->model->updateJobResult($jobId, 'completed', [
                        'items_found' => count($result['data']['links'] ?? []),
                        'items_saved' => 1, // Assuming we saved one article
                        'items_failed' => 0
                    ]);

                    return [
                        'success' => true,
                        'job_id' => $jobId,
                        'items_processed' => count($result['data']['links'] ?? []),
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
     */
    private function storeScrapedData(int $sourceId, array $data, array $source): void
    {
        // Store articles
        if (!empty($data['content'])) {
            $this->model->saveArticle([
                'source_id' => $sourceId,
                'title' => $data['title'] ?? 'Untitled',
                'content' => $data['content'],
                'url' => $source['url'],
                'image_url' => $data['images'][0]['src'] ?? null,
                'status' => 'completed',
                'content_hash' => hash('sha256', $data['content']),
                'excerpt' => substr($data['content'], 0, 200) . (strlen($data['content']) > 200 ? '...' : ''),
                'categories' => [],
                'tags' => []
            ]);
        }

        // Store mobile data if applicable
        if (($source['content_type'] ?? 'article') === 'mobile' && !empty($data['title'])) {
            $this->model->saveMobile([
                'source_id' => $sourceId,
                'source_url' => $source['url'],
                'title' => $data['title'],
                'brand' => $this->extractBrandFromTitle($data['title']),
                'model' => $this->extractModelFromTitle($data['title']),
                'image_url' => $data['images'][0]['src'] ?? null,
                'specifications' => $data,
                'status' => 'active'
            ]);
        }
    }

    /**
     * Extract brand from mobile title
     */
    private function extractBrandFromTitle(string $title): string
    {
        $brands = ['Samsung', 'Apple', 'Google', 'OnePlus', 'Xiaomi', 'Huawei', 'Sony', 'LG', 'Motorola', 'Nokia'];
        foreach ($brands as $brand) {
            if (stripos($title, $brand) !== false) {
                return $brand;
            }
        }
        return 'Unknown';
    }

    /**
     * Extract model from mobile title
     */
    private function extractModelFromTitle(string $title): string
    {
        // Simple extraction - can be improved with regex patterns
        $parts = explode(' ', $title);
        return end($parts);
    }
}
