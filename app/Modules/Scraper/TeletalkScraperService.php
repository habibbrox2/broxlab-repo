<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use App\Models\TeletalkJobModel;
use Throwable;

class TeletalkScraperService
{
    private TeletalkJobModel $model;
    private array $stats = [
        'total_scraped' => 0,
        'new_jobs' => 0,
        'duplicates' => 0,
        'errors' => 0,
    ];

    private string $apiUrl;
    private int $timeout;
    private int $connectTimeout;
    private int $maxAttempts;
    private int $retryDelay;

    public function __construct(\mysqli $mysqli, array $config = [])
    {
        $this->model = new TeletalkJobModel($mysqli);
        $this->apiUrl = (string)($config['api_url'] ?? getenv('TELETALK_API_URL') ?: 'https://alljobs.teletalk.com.bd/api/v1/govt-jobs/org-list');
        $this->timeout = (int)($config['timeout'] ?? getenv('TELETALK_API_TIMEOUT') ?: 30);
        $this->connectTimeout = (int)($config['connect_timeout'] ?? getenv('TELETALK_CONNECT_TIMEOUT') ?: 10);
        $this->maxAttempts = max(1, (int)($config['max_attempts'] ?? getenv('TELETALK_MAX_RETRIES') ?: 3));
        $this->retryDelay = max(0, (int)($config['retry_delay'] ?? getenv('TELETALK_RETRY_DELAY') ?: 2));
        $this->model->ensureTablesExist();
    }

    public function scrapeAllPages(int $maxPages = 1, ?callable $progressCallback = null): array
    {
        $response = $this->fetchApiPayload();
        $orgs = $response['govtOrgJobs'] ?? [];
        if (!is_array($orgs)) {
            $orgs = [];
        }

        $processedOrgs = 0;
        $flattenedJobs = [];
        $errors = [];
        $orgLimit = $maxPages > 0 ? min($maxPages, count($orgs)) : count($orgs);

        foreach ($orgs as $org) {
            if ($processedOrgs >= $orgLimit) {
                break;
            }

            if (!is_array($org)) {
                continue;
            }

            try {
                $this->model->saveOrganization($org);
                $jobs = $org['govt_jobs'] ?? [];
                if (!is_array($jobs)) {
                    $jobs = [];
                }

                foreach ($jobs as $job) {
                    if (!is_array($job)) {
                        continue;
                    }

                    $flattenedJobs[] = $job + [
                        'organization_id' => (int)($org['id'] ?? 0),
                        'organization_name' => (string)($org['name'] ?? ''),
                    ];
                }

                $processedOrgs++;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        $saved = 0;
        $duplicates = 0;
        foreach ($flattenedJobs as $job) {
            $jobId = (int)($job['id'] ?? 0);
            if ($jobId <= 0) {
                $errors[] = 'Missing job id for ' . ($job['job_title'] ?? 'unknown job');
                continue;
            }

            $isDuplicate = $this->model->jobExists($jobId);
            if ($this->model->saveJob($job, [
                'id' => (int)($job['organization_id'] ?? 0),
                'name' => (string)($job['organization_name'] ?? ''),
            ])) {
                $saved++;
                if ($isDuplicate) {
                    $duplicates++;
                }
            } else {
                $errors[] = 'Failed to save job #' . $jobId;
            }
        }

        $this->stats['total_scraped'] += count($flattenedJobs);
        $this->stats['new_jobs'] += max(0, $saved - $duplicates);
        $this->stats['duplicates'] += $duplicates;
        $this->stats['errors'] += count($errors);

        if ($progressCallback) {
            $progressCallback(
                1,
                1,
                true,
                $flattenedJobs,
                $this->apiUrl,
                null
            );
        }

        return [
            'success' => empty($errors),
            'status' => [
                'source_id' => $orgs[0]['id'] ?? null,
                'stats' => $this->stats,
            ],
            'data' => $flattenedJobs,
            'errors' => $errors,
            'meta' => [
                'organizations_seen' => count($orgs),
                'organizations_processed' => $processedOrgs,
                'jobs_flattened' => count($flattenedJobs),
            ],
        ];
    }

    public function updateStats(string $key, int $amount = 1): void
    {
        if (!array_key_exists($key, $this->stats)) {
            $this->stats[$key] = 0;
        }

        $this->stats[$key] += $amount;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    private function fetchApiPayload(): array
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $ch = curl_init($this->apiUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'User-Agent: BroxLab TeletalkScraper/1.0',
                    ],
                ]);

                $response = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($response === false || $httpCode >= 400) {
                    throw new \RuntimeException($curlError ?: 'HTTP ' . $httpCode);
                }

                $decoded = json_decode((string)$response, true);
                if (!is_array($decoded)) {
                    throw new \RuntimeException('Invalid Teletalk API response');
                }

                return $decoded;
            } catch (Throwable $e) {
                $lastError = $e;
                if ($attempt < $this->maxAttempts && $this->retryDelay > 0) {
                    sleep($this->retryDelay);
                }
            }
        }

        throw new \RuntimeException('Failed to fetch Teletalk API: ' . ($lastError?->getMessage() ?? 'unknown error'));
    }
}
