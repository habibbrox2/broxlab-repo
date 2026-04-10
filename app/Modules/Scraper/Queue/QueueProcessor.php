<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Queue;

use App\Modules\Scraper\ScraperService;
use Exception;

class QueueProcessor
{
    private ScraperService $scraperService;
    private ?callable $logger;

    public function __construct(ScraperService $scraperService, ?callable $logger = null)
    {
        $this->scraperService = $scraperService;
        $this->logger = $logger;
    }

    /**
     * Execute a job and return aggregated stats.
     */
    public function processJob(array $job): array
    {
        try {
            $this->log(sprintf('Processing job #%s (source=%s)', $job['id'], $job['source_id']));

            $options = [
                'job_id' => $job['id'],
                'job_type' => $job['job_type'] ?? 'full'
            ];

            if (($job['job_type'] ?? 'full') === 'test') {
                $result = $this->scraperService->testSource((int)$job['source_id']);
            } else {
                $result = $this->scraperService->scrapeSource((int)$job['source_id'], $options);
            }

            $itemsFound = $this->normalizeCount($result, 'items_found');
            $itemsSaved = $this->normalizeCount($result, 'items_saved');
            $itemsFailed = $this->normalizeCount($result, 'items_failed', max(0, $itemsFound - $itemsSaved));

            $stats = [
                'items_found' => $itemsFound,
                'items_saved' => $itemsSaved,
                'items_failed' => $itemsFailed,
                'result_data' => $result
            ];

            $this->log(sprintf('Job #%s completed (found=%d saved=%d failed=%d)', $job['id'], $itemsFound, $itemsSaved, $itemsFailed));

            return [
                'success' => !empty($result['success']),
                'stats' => $stats,
                'result' => $result
            ];
        } catch (Exception $e) {
            $message = sprintf('Job #%s failed: %s', $job['id'] ?? 'unknown', $e->getMessage());
            $this->log($message);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => [
                    'items_found' => 0,
                    'items_saved' => 0,
                    'items_failed' => 0
                ]
            ];
        }
    }

    private function normalizeCount(array $result, string $key, int $fallback = 0): int
    {
        if (isset($result['stats'][$key])) {
            return (int)$result['stats'][$key];
        }

        if (isset($result[$key])) {
            return (int)$result[$key];
        }

        return $fallback;
    }

    private function log(string $message): void
    {
        if ($this->logger) {
            call_user_func($this->logger, $message);
        }
    }
}
