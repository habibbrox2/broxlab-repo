<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use App\Models\ScraperModel;
use App\Modules\Scraper\Scrapers\AdvanceScraper;
use App\Modules\Scraper\ScraperErrorHandler;
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
    private ScraperErrorHandler $errorHandler;

    public function __construct(ScraperModel $model)
    {
        $this->model = $model;
        $this->advanceScraper = new AdvanceScraper();
        $this->errorHandler = new ScraperErrorHandler();
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
        $startTime = microtime(true);

        try {
            $source = $this->model->getSourceById($sourceId);
            if (!$source) {
                $this->errorHandler->handleError(
                    new Exception('Source not found'),
                    ['source_id' => $sourceId, 'operation' => 'get_source']
                );
                throw new Exception('Source not found');
            }

            // Create a job record
            $jobId = $this->model->createJob([
                'source_id' => $sourceId,
                'job_type' => 'full',
                'priority' => 5
            ]);

            try {
                // Perform the scrape with error handling
                $scrapeResult = $this->performScrapeWithErrorHandling($source, $options);

                // Calculate total execution time
                $totalExecutionTime = round(microtime(true) - $startTime, 2);

                if ($scrapeResult['success']) {
                    // Store the scraped data
                    $storeResult = $this->storeScrapedDataWithErrorHandling($sourceId, $scrapeResult['data'], $source);

                    // Update job status
                    $this->model->updateJobResult($jobId, 'completed', [
                        'items_found' => $scrapeResult['items_found'],
                        'items_saved' => $storeResult['items_saved'],
                        'items_failed' => $storeResult['items_failed'],
                        'duration' => $totalExecutionTime,
                        'error_stats' => $this->errorHandler->getErrorStats()
                    ]);

                    return [
                        'success' => true,
                        'job_id' => $jobId,
                        'stats' => [
                            'items_saved' => $storeResult['items_saved'],
                            'items_found' => $scrapeResult['items_found'],
                            'items_failed' => $storeResult['items_failed'],
                            'duration' => $totalExecutionTime,
                            'pages_scraped' => $scrapeResult['pages_scraped'],
                            'errors' => $this->errorHandler->getErrorStats()
                        ],
                        'message' => 'Scraping completed successfully'
                    ];
                } else {
                    // Update job status to failed
                    $this->model->updateJobResult($jobId, 'failed', [
                        'error_message' => $scrapeResult['error'],
                        'duration' => $totalExecutionTime,
                        'error_stats' => $this->errorHandler->getErrorStats()
                    ]);

                    return [
                        'success' => false,
                        'job_id' => $jobId,
                        'stats' => [
                            'items_saved' => 0,
                            'items_found' => 0,
                            'items_failed' => 1,
                            'duration' => $totalExecutionTime,
                            'pages_scraped' => 0,
                            'errors' => $this->errorHandler->getErrorStats()
                        ],
                        'error' => $scrapeResult['error']
                    ];
                }
            } catch (Exception $e) {
                // Handle scraping exception
                $errorData = $this->errorHandler->handleError($e, [
                    'source_id' => $sourceId,
                    'job_id' => $jobId,
                    'operation' => 'scrape_source'
                ]);

                // Update job status to failed
                $this->model->updateJobResult($jobId, 'failed', [
                    'error_message' => $e->getMessage(),
                    'error_type' => $errorData['type'],
                    'error_severity' => $errorData['severity']
                ]);
                throw $e;
            }
        } catch (Exception $e) {
            $this->errorHandler->handleError($e, [
                'source_id' => $sourceId,
                'operation' => 'scrape_source_wrapper'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_stats' => $this->errorHandler->getErrorStats()
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

    /**
     * Perform scrape operation with comprehensive error handling
     */
    private function performScrapeWithErrorHandling(array $source, array $options = []): array
    {
        $scrapeStartTime = microtime(true);

        try {
            // Set up the scraper
            $this->advanceScraper->setSource($source);

            // Check for structural changes if selectors are provided
            if (!empty($source['selectors'])) {
                $selectors = json_decode($source['selectors'], true) ?? [];
                if (!empty($selectors)) {
                    // Try to fetch a sample HTML to check selectors
                    try {
                        $sampleUrl = $source['url'];
                        $sampleHtml = HtmlFetcher::fetch($sampleUrl);
                        $structuralIssues = $this->errorHandler->detectStructuralChanges($sampleHtml, $selectors);

                        if (!empty($structuralIssues)) {
                            // Log structural issues but continue scraping
                            error_log('Structural changes detected for source ' . $source['id'] . ': ' . count($structuralIssues) . ' issues');
                        }
                    } catch (Exception $e) {
                        // Log but don't fail the entire operation
                        $this->errorHandler->handleError($e, [
                            'source_id' => $source['id'],
                            'operation' => 'structural_check'
                        ]);
                    }
                }
            }

            // Perform the actual scrape
            $result = $this->advanceScraper->scrape();

            $scrapeExecutionTime = round(microtime(true) - $scrapeStartTime, 2);

            if ($result['success']) {
                $itemsFound = count($result['data']['links'] ?? []) ?: 1; // At least 1 if content exists

                return [
                    'success' => true,
                    'data' => $result['data'],
                    'items_found' => $itemsFound,
                    'pages_scraped' => 1,
                    'duration' => $scrapeExecutionTime,
                    'strategy_used' => $result['strategy_used'] ?? 'unknown'
                ];
            } else {
                // Handle scraping failure
                $scrapeError = new Exception($result['error'] ?? 'Scraping operation failed');
                $this->errorHandler->handleError($scrapeError, [
                    'source_id' => $source['id'],
                    'strategy_used' => $result['strategy_used'] ?? 'unknown',
                    'operation' => 'scrape_execution'
                ]);

                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Scraping failed',
                    'items_found' => 0,
                    'pages_scraped' => 0,
                    'duration' => $scrapeExecutionTime
                ];
            }
        } catch (Exception $e) {
            $this->errorHandler->handleError($e, [
                'source_id' => $source['id'],
                'operation' => 'scrape_with_error_handling'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'items_found' => 0,
                'pages_scraped' => 0,
                'duration' => round(microtime(true) - $scrapeStartTime, 2)
            ];
        }
    }

    /**
     * Store scraped data with error handling
     */
    private function storeScrapedDataWithErrorHandling(int $sourceId, array $data, array $source): array
    {
        $itemsSaved = 0;
        $itemsFailed = 0;

        try {
            $saved = $this->storeScrapedData($sourceId, $data, $source);
            if ($saved) {
                $itemsSaved = 1;
            } else {
                $itemsFailed = 1;
                $this->errorHandler->handleError(
                    new Exception('Failed to save scraped data to database'),
                    ['source_id' => $sourceId, 'operation' => 'store_data']
                );
            }
        } catch (Exception $e) {
            $itemsFailed = 1;
            $this->errorHandler->handleError($e, [
                'source_id' => $sourceId,
                'operation' => 'store_data_exception'
            ]);
        }

        return [
            'items_saved' => $itemsSaved,
            'items_failed' => $itemsFailed
        ];
    }

    /**
     * Get error statistics for monitoring
     */
    public function getErrorStats(): array
    {
        return $this->errorHandler->getErrorStats();
    }

    /**
     * Clear error logs
     */
    public function clearErrors(): void
    {
        $this->errorHandler->clearErrors();
    }
}
