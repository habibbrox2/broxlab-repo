<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * ConcurrentScraper
 * Multi-threaded scraping with worker pools and queue management
 */
class ConcurrentScraper
{
    private IntelligentScraperService $scraper;
    private array $workers = [];
    private int $maxWorkers;
    private array $queue = [];
    private array $results = [];
    private array $activeJobs = [];
    private bool $isRunning = false;

    public function __construct(IntelligentScraperService $scraper, int $maxWorkers = 5)
    {
        $this->scraper = $scraper;
        $this->maxWorkers = $maxWorkers;
    }

    /**
     * Add scraping job to queue
     */
    public function addJob(string $url, array $selectors = [], array $options = []): string
    {
        $jobId = uniqid('scrape_', true);
        $this->queue[$jobId] = [
            'url' => $url,
            'selectors' => $selectors,
            'options' => $options,
            'created_at' => time(),
            'status' => 'queued'
        ];

        return $jobId;
    }

    /**
     * Add multiple jobs at once
     */
    public function addJobs(array $jobs): array
    {
        $jobIds = [];
        foreach ($jobs as $job) {
            $jobIds[] = $this->addJob(
                $job['url'],
                $job['selectors'] ?? [],
                $job['options'] ?? []
            );
        }
        return $jobIds;
    }

    /**
     * Start processing queue
     */
    public function start(): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;

        while ($this->isRunning && (!empty($this->queue) || !empty($this->activeJobs))) {
            $this->processQueue();
            $this->checkCompletedJobs();
            $this->manageWorkers();

            // Small delay to prevent tight loop
            usleep(100000); // 100ms
        }
    }

    /**
     * Stop processing
     */
    public function stop(): void
    {
        $this->isRunning = false;

        // Wait for active jobs to complete
        while (!empty($this->activeJobs)) {
            $this->checkCompletedJobs();
            usleep(500000); // 500ms
        }
    }

    /**
     * Process queued jobs
     */
    private function processQueue(): void
    {
        $availableSlots = $this->maxWorkers - count($this->activeJobs);

        if ($availableSlots <= 0 || empty($this->queue)) {
            return;
        }

        $jobsToStart = array_slice($this->queue, 0, $availableSlots, true);

        foreach ($jobsToStart as $jobId => $job) {
            $this->startJob($jobId, $job);
        }
    }

    /**
     * Start a scraping job
     */
    private function startJob(string $jobId, array $job): void
    {
        $this->queue[$jobId]['status'] = 'running';
        $this->activeJobs[$jobId] = $job + ['started_at' => time()];

        // In a real implementation, this would spawn a separate process or use a job queue
        // For now, we'll simulate with a callback
        $this->workers[$jobId] = $this->createWorker($jobId, $job);
    }

    /**
     * Create worker for job (simplified - would use actual async processing)
     */
    private function createWorker(string $jobId, array $job): callable
    {
        return function () use ($jobId, $job) {
            try {
                $result = $this->scraper->scrapeWithIntelligence(
                    $job['url'],
                    $job['selectors'],
                    $job['options']
                );

                $this->results[$jobId] = [
                    'job' => $job,
                    'result' => $result,
                    'completed_at' => time(),
                    'duration' => time() - ($job['started_at'] ?? time())
                ];
            } catch (\Exception $e) {
                $this->results[$jobId] = [
                    'job' => $job,
                    'result' => ['success' => false, 'error' => $e->getMessage()],
                    'completed_at' => time(),
                    'duration' => time() - ($job['started_at'] ?? time())
                ];
            }
        };
    }

    /**
     * Check for completed jobs
     */
    private function checkCompletedJobs(): void
    {
        foreach ($this->activeJobs as $jobId => $job) {
            if (isset($this->results[$jobId])) {
                unset($this->activeJobs[$jobId]);
                unset($this->queue[$jobId]);
                unset($this->workers[$jobId]);
            }
        }
    }

    /**
     * Manage worker pool
     */
    private function manageWorkers(): void
    {
        // In a real implementation, this would monitor worker health
        // and restart failed workers

        foreach ($this->workers as $jobId => $worker) {
            // Simulate worker execution
            if (isset($this->activeJobs[$jobId])) {
                // Check if job should be timed out
                $elapsed = time() - ($this->activeJobs[$jobId]['started_at'] ?? time());
                if ($elapsed > 300) { // 5 minute timeout
                    $this->results[$jobId] = [
                        'job' => $this->activeJobs[$jobId],
                        'result' => ['success' => false, 'error' => 'Job timed out'],
                        'completed_at' => time(),
                        'duration' => $elapsed
                    ];
                    unset($this->activeJobs[$jobId]);
                    unset($this->workers[$jobId]);
                }
            }
        }
    }

    /**
     * Get job status
     */
    public function getJobStatus(string $jobId): array
    {
        if (isset($this->results[$jobId])) {
            return [
                'status' => 'completed',
                'result' => $this->results[$jobId]
            ];
        }

        if (isset($this->activeJobs[$jobId])) {
            return [
                'status' => 'running',
                'started_at' => $this->activeJobs[$jobId]['started_at'],
                'elapsed' => time() - $this->activeJobs[$jobId]['started_at']
            ];
        }

        if (isset($this->queue[$jobId])) {
            return [
                'status' => 'queued',
                'created_at' => $this->queue[$jobId]['created_at']
            ];
        }

        return ['status' => 'not_found'];
    }

    /**
     * Get all results
     */
    public function getAllResults(): array
    {
        return $this->results;
    }

    /**
     * Get queue statistics
     */
    public function getStats(): array
    {
        return [
            'queued' => count($this->queue),
            'active' => count($this->activeJobs),
            'completed' => count($this->results),
            'total' => count($this->queue) + count($this->activeJobs) + count($this->results),
            'is_running' => $this->isRunning
        ];
    }

    /**
     * Wait for all jobs to complete
     */
    public function waitForCompletion(int $timeoutSeconds = 300): bool
    {
        $start = time();

        while (($this->getStats()['queued'] + $this->getStats()['active']) > 0) {
            if ((time() - $start) > $timeoutSeconds) {
                return false; // Timeout
            }

            sleep(1);
        }

        return true;
    }
}
