<?php

declare(strict_types=1);

namespace App\Modules\AutoContent;

use mysqli;
use Throwable;

/**
 * TeletalkJobCronWorker
 * 
 * Fetches government job data from Teletalk API and stores in database
 * Handles pagination, deduplication, error handling, and logging
 */
class TeletalkJobCronWorker
{
    private mysqli $mysqli;
    private \TeletalkJobModel $model;
    private array $config;
    private string $logPath;

    public function __construct(mysqli $mysqli, array $config = [])
    {
        $this->mysqli = $mysqli;
        $this->model = new \TeletalkJobModel($mysqli);

        $defaults = [
            'api_base_url' => 'https://alljobs.teletalk.com.bd/api/v1/govt-jobs/org-list',
            'page_limit' => 20,
            'max_retries' => 3,
            'retry_delay_seconds' => 2,
            'log_path' => dirname(__DIR__, 3) . '/logs/teletalk_cron.log',
        ];

        $this->config = array_replace_recursive($defaults, $config);
        $this->logPath = $this->config['log_path'];

        // Ensure log directory exists
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    /**
     * Run the Teletalk job fetcher
     * 
     * @return array{success: bool, organizations_processed: int, jobs_inserted: int, errors: array}
     */
    public function run(): array
    {
        $startTime = microtime(true);
        $summary = [
            'success' => true,
            'organizations_processed' => 0,
            'jobs_inserted' => 0,
            'errors' => [],
            'pages_fetched' => 0,
            'execution_time_ms' => 0,
        ];

        $this->log('Starting Teletalk job fetch...');

        $page = 1;
        $hasMorePages = true;

        while ($hasMorePages) {
            try {
                $result = $this->fetchPage($page);

                if ($result['success']) {
                    $organizations = $result['data'];

                    if (empty($organizations)) {
                        $this->log("No more data on page {$page}. Stopping pagination.");
                        $hasMorePages = false;
                        break;
                    }

                    $pageSummary = $this->processOrganizations($organizations);
                    $summary['organizations_processed'] += $pageSummary['organizations_processed'];
                    $summary['jobs_inserted'] += $pageSummary['jobs_inserted'];
                    $summary['pages_fetched']++;

                    $this->log("Page {$page}: Processed {$pageSummary['organizations_processed']} organizations, {$pageSummary['jobs_inserted']} new jobs");

                    $page++;
                } else {
                    $this->log("Failed to fetch page {$page}: {$result['error']}", 'ERROR');
                    $summary['errors'][] = "Page {$page}: {$result['error']}";

                    // Continue to next page even if current fails
                    $page++;
                }
            } catch (Throwable $e) {
                $this->log("Exception on page {$page}: {$e->getMessage()}", 'ERROR');
                $summary['errors'][] = "Page {$page}: {$e->getMessage()}";
                $page++;
            }
        }

        $summary['execution_time_ms'] = round((microtime(true) - $startTime) * 1000);

        $this->log("Completed: {$summary['organizations_processed']} organizations, {$summary['jobs_inserted']} new jobs in {$summary['execution_time_ms']}ms");

        // Update last run timestamp
        $this->updateLastRunTimestamp();

        return $summary;
    }

    /**
     * Fetch a single page from the API
     * 
     * @param int $page Page number
     * @return array{success: bool, data: array, error: string|null}
     */
    private function fetchPage(int $page): array
    {
        $url = $this->config['api_base_url'] . '?page=' . $page . '&limit=' . $this->config['page_limit'];

        $this->log("Fetching page {$page}: {$url}");

        $attempt = 0;
        $maxRetries = $this->config['max_retries'];

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                $ch = curl_init();

                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept: application/json',
                    ],
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($curlError) {
                    throw new \Exception("cURL error: {$curlError}");
                }

                if ($httpCode !== 200) {
                    throw new \Exception("HTTP {$httpCode} received");
                }

                $data = json_decode($response, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("Invalid JSON: " . json_last_error_msg());
                }

                // Normalize the data
                $normalizedData = $this->normalizeApiResponse($data);

                return [
                    'success' => true,
                    'data' => $normalizedData,
                    'error' => null,
                ];
            } catch (Throwable $e) {
                $this->log("Attempt {$attempt}/{$maxRetries} failed for page {$page}: {$e->getMessage()}", 'WARNING');

                if ($attempt < $maxRetries) {
                    sleep($this->config['retry_delay_seconds']);
                }
            }
        }

        return [
            'success' => false,
            'data' => [],
            'error' => "Failed after {$maxRetries} attempts",
        ];
    }

    /**
     * Normalize API response to consistent format
     * 
     * @param array $data Raw API response
     * @return array Normalized organization data
     */
    private function normalizeApiResponse(array $data): array
    {
        // Handle different possible response structures
        $organizations = [];

        if (isset($data['data']) && is_array($data['data'])) {
            $organizations = $data['data'];
        } elseif (isset($data['organizations']) && is_array($data['organizations'])) {
            $organizations = $data['organizations'];
        } elseif (isset($data[0]) && is_array($data[0])) {
            // Direct array of organizations
            $organizations = $data;
        }

        // Normalize each organization
        $normalized = [];
        foreach ($organizations as $org) {
            $normalized[] = $this->normalizeOrganization($org);
        }

        return $normalized;
    }

    /**
     * Normalize organization data
     * 
     * @param array $org Raw organization data
     * @return array Normalized organization data
     */
    private function normalizeOrganization(array $org): array
    {
        return [
            'id' => $this->normalizeValue($org['id'] ?? null),
            'name' => $this->normalizeValue($org['name'] ?? null),
            'name_bn' => $this->normalizeValue($org['name_bn'] ?? null),
            'short_name' => $this->normalizeValue($org['short_name'] ?? null),
            'website' => $this->normalizeValue($org['website'] ?? null),
            'logo' => $this->normalizeValue($org['logo'] ?? null),
            'govt_jobs' => $this->normalizeJobs($org['govt_jobs'] ?? []),
        ];
    }

    /**
     * Normalize jobs array
     * 
     * @param array $jobs Raw jobs data
     * @return array Normalized jobs data
     */
    private function normalizeJobs(array $jobs): array
    {
        $normalized = [];
        foreach ($jobs as $job) {
            $normalized[] = [
                'id' => $this->normalizeValue($job['id'] ?? null),
                'job_title' => $this->normalizeValue($job['job_title'] ?? null),
                'job_title_bn' => $this->normalizeValue($job['job_title_bn'] ?? null),
                'organization_id' => $this->normalizeValue($job['organization_id'] ?? null),
            ];
        }
        return $normalized;
    }

    /**
     * Normalize a single value
     * 
     * @param mixed $value Value to normalize
     * @return string|null Normalized value or null
     */
    private function normalizeValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        return (string)$value;
    }

    /**
     * Process organizations and save to database
     * 
     * @param array $organizations Normalized organization data
     * @return array{organizations_processed: int, jobs_inserted: int}
     */
    private function processOrganizations(array $organizations): array
    {
        $summary = [
            'organizations_processed' => 0,
            'jobs_inserted' => 0,
        ];

        foreach ($organizations as $orgData) {
            try {
                $result = $this->model->saveOrganizationWithJobs($orgData);

                if ($result['success']) {
                    $summary['organizations_processed']++;
                    $summary['jobs_inserted'] += $result['jobs_saved'];
                } else {
                    $this->log("Failed to save organization {$orgData['id']}: {$result['error']}", 'WARNING');
                }
            } catch (Throwable $e) {
                $this->log("Exception processing organization {$orgData['id']}: {$e->getMessage()}", 'ERROR');
            }
        }

        return $summary;
    }

    /**
     * Update last run timestamp in database
     */
    private function updateLastRunTimestamp(): void
    {
        try {
            $stmt = $this->mysqli->prepare(
                "INSERT INTO teletalk_cron_logs (last_run_at, status) VALUES (NOW(), 'success')
                 ON DUPLICATE KEY UPDATE last_run_at = NOW(), status = 'success'"
            );

            if ($stmt) {
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            $this->log("Failed to update last run timestamp: {$e->getMessage()}", 'WARNING');
        }
    }

    /**
     * Log message to file and optionally to database
     * 
     * @param string $message Log message
     * @param string $level Log level (INFO, WARNING, ERROR)
     */
    private function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        // Write to log file
        @file_put_contents($this->logPath, $logMessage, FILE_APPEND | LOCK_EX);

        // Also log to database if table exists
        try {
            $stmt = $this->mysqli->prepare(
                "INSERT INTO teletalk_cron_logs (last_run_at, status, message) 
                 VALUES (NOW(), ?, ?)
                 ON DUPLICATE KEY UPDATE last_run_at = NOW(), status = ?, message = ?"
            );

            if ($stmt) {
                $status = $level === 'ERROR' ? 'error' : 'success';
                $stmt->bind_param('ssss', $status, $message, $status, $message);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            // Silently fail - don't break the main process
        }
    }

    /**
     * Get last successful run timestamp
     * 
     * @return string|null Last run timestamp or null
     */
    public function getLastRunTimestamp(): ?string
    {
        try {
            $result = $this->mysqli->query(
                "SELECT last_run_at FROM teletalk_cron_logs ORDER BY last_run_at DESC LIMIT 1"
            );

            if ($result && $row = $result->fetch_assoc()) {
                return $row['last_run_at'];
            }
        } catch (Throwable $e) {
            // Table might not exist yet
        }

        return null;
    }

    /**
     * Get statistics about stored data
     * 
     * @return array Statistics
     */
    public function getStats(): array
    {
        return [
            'total_organizations' => $this->model->getOrganizationCount(),
            'total_jobs' => $this->model->getTotalCount(),
            'last_run' => $this->getLastRunTimestamp(),
        ];
    }
}
