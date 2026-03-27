<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use App\Modules\Scraper\HttpClientService;

/**
 * BDJobsScraperService.php
 * Service for scraping BDJobs job listings and details
 * Uses BDJobs API endpoints
 */
class BDJobsScraperService
{
    private HttpClientService $httpClient;
    private ?\mysqli $mysqli = null;
    private string $listingApiUrl;
    private string $detailsApiUrl;
    private array $config;
    private array $stats = [
        'total_scraped' => 0,
        'new_jobs' => 0,
        'duplicates' => 0,
        'errors' => 0,
        'listings_saved' => 0,
        'details_saved' => 0,
    ];

    public function __construct(
        HttpClientService $httpClient,
        ?array $config = null,
        ?\mysqli $mysqli = null
    ) {
        $this->httpClient = $httpClient;
        $this->config = $config ?? $this->getDefaultConfig();
        $this->listingApiUrl = $this->config['listing_api_url'];
        $this->detailsApiUrl = $this->config['details_api_url'];
        $this->mysqli = $mysqli;
    }

    /**
     * Set database connection
     */
    public function setDatabase(\mysqli $mysqli): self
    {
        $this->mysqli = $mysqli;
        return $this;
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'listing_api_url' => 'https://api.bdjobs.com/Jobs/api/JobSearch/GetJobSearch',
            'details_api_url' => 'https://gateway.bdjobs.com/ActtivejobsTest/api/JobSubsystem/JobDetailsExistingInfo',
            'default_params' => [
                'Icat' => '',
                'industry' => '',
                'category' => '',
                'org' => '',
                'jobNature' => '',
                'Fcat' => '',
                'location' => '',
                'Qot' => '',
                'jobType' => '',
                'jobLevel' => '',
                'postedWithin' => '1',
                'deadline' => '',
                'keyword' => '',
                'pg' => '1',
                'qAge' => '',
                'Salary' => '',
                'experience' => '',
                'gender' => '',
                'MExp' => '',
                'genderB' => '',
                'MPostings' => '',
                'MCat' => '',
                'version' => '',
                'rpp' => '100',
                'Newspaper' => '',
                'armyp' => '',
                'QDisablePerson' => '',
                'pwd' => '',
                'workplace' => '',
                'facilitiesForPWD' => '',
                'SaveFilterList' => '',
                'UserFilterName' => '',
                'HUserFilterName' => '',
                'earlyJobAccess' => '',
                'isPro' => '0',
                'ToggleJobs' => 'true',
                'isFresher' => 'false',
            ],
            'details_params' => [
                'deviceType' => 'web',
                'formValue' => '',
            ],
            'rate_limit' => [
                'delay_ms' => 1000,
                'max_retries' => 3,
            ],
        ];
    }

    /**
     * Scrape job listings from BDJobs API
     *
     * @param int $page Page number (1-based)
     * @param int $limit Maximum number of jobs to return
     * @param array $filters Additional filters
     * @return array{success: bool, jobs: array, page: int, total_pages: int, error: string|null}
     */
    public function scrapeJobListings(int $page = 1, int $limit = 100, array $filters = []): array
    {
        $url = $this->buildListingsUrl($page, $limit, $filters);

        try {
            $response = $this->httpClient->get($url);

            if (!$response['success']) {
                logError("BDJobsScraper: Failed to fetch page {$page}: " . ($response['error'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'jobs' => [],
                    'page' => $page,
                    'total_pages' => 0,
                    'error' => $response['error'] ?? 'Failed to fetch page'
                ];
            }

            $data = json_decode($response['body'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                logError("BDJobsScraper: Invalid JSON response on page {$page}: " . json_last_error_msg());
                return [
                    'success' => false,
                    'jobs' => [],
                    'page' => $page,
                    'total_pages' => 0,
                    'error' => 'Invalid JSON response'
                ];
            }

            $jobs = $this->parseJobListings($data, $limit);
            $totalPages = $this->calculateTotalPages($data, $limit);

            return [
                'success' => true,
                'jobs' => $jobs,
                'page' => $page,
                'total_pages' => $totalPages,
                'error' => null,
            ];
        } catch (\Exception $e) {
            logError("BDJobsScraper: Exception on page {$page}: " . $e->getMessage());
            return [
                'success' => false,
                'jobs' => [],
                'page' => $page,
                'total_pages' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Scrape job details from BDJobs API
     *
     * @param string $jobId BDJobs job ID
     * @param string $companyId Company ID
     * @param string $companyName Company name (URL encoded)
     * @return array{success: bool, details: array|null, error: string|null}
     */
    public function scrapeJobDetails(string $jobId, string $companyId, string $companyName): array
    {
        $url = $this->buildDetailsUrl($jobId, $companyId, $companyName);

        try {
            $response = $this->httpClient->get($url);

            if (!$response['success']) {
                logError("BDJobsScraper: Failed to fetch details for job {$jobId}: " . ($response['error'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'details' => null,
                    'error' => $response['error'] ?? 'Failed to fetch job details'
                ];
            }

            $data = json_decode($response['body'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                logError("BDJobsScraper: Invalid JSON response for job {$jobId}: " . json_last_error_msg());
                return [
                    'success' => false,
                    'details' => null,
                    'error' => 'Invalid JSON response'
                ];
            }

            $details = $this->parseJobDetails($data, $jobId, $companyId);

            return [
                'success' => true,
                'details' => $details,
                'error' => null,
            ];
        } catch (\Exception $e) {
            logError("BDJobsScraper: Exception fetching details for job {$jobId}: " . $e->getMessage());
            return [
                'success' => false,
                'details' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Scrape all pages up to max_pages
     *
     * @param int $maxPages Maximum number of pages to scrape
     * @param array $filters Additional filters
     * @param callable|null $progressCallback Callback for progress updates
     * @return array{success: bool, jobs: array, stats: array, error: string|null}
     */
    public function scrapeAllPages(int $maxPages = 10, array $filters = [], ?callable $progressCallback = null): array
    {
        $allJobs = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $result = $this->scrapeJobListings($page, 100, $filters);

            if (!$result['success']) {
                $this->stats['errors']++;
                if ($progressCallback) {
                    $progressCallback($page, $maxPages, false, $result['error']);
                }
                continue;
            }

            $allJobs = array_merge($allJobs, $result['jobs']);
            $this->stats['total_scraped'] += count($result['jobs']);

            if ($progressCallback) {
                $progressCallback($page, $maxPages, true, count($result['jobs']));
            }

            // Stop if no jobs found on this page
            if (empty($result['jobs'])) {
                break;
            }

            // Rate limiting delay
            if ($page < $maxPages) {
                usleep($this->config['rate_limit']['delay_ms'] * 1000);
            }
        }

        return [
            'success' => true,
            'jobs' => $allJobs,
            'stats' => $this->stats,
            'error' => null
        ];
    }

    /**
     * Build URL for job listings API
     */
    private function buildListingsUrl(int $page, int $limit, array $filters = []): string
    {
        $params = array_merge($this->config['default_params'], $filters, [
            'pg' => (string)$page,
            'rpp' => (string)$limit,
        ]);

        return $this->listingApiUrl . '?' . http_build_query($params);
    }

    /**
     * Build URL for job details API
     */
    private function buildDetailsUrl(string $jobId, string $companyId, string $companyName): string
    {
        $params = array_merge($this->config['details_params'], [
            'JobId' => $jobId,
            'CompanyId' => $companyId,
            'CompanyName' => $companyName,
        ]);

        return $this->detailsApiUrl . '?' . http_build_query($params);
    }

    /**
     * Parse job listings from API response
     */
    private function parseJobListings(array $data, int $limit): array
    {
        $jobs = [];

        // BDJobs API structure may vary, handle common formats
        $jobList = $data['data'] ?? $data['jobs'] ?? $data['JobSearch'] ?? [];

        if (!is_array($jobList)) {
            return $jobs;
        }

        foreach ($jobList as $index => $job) {
            if ($index >= $limit) {
                break;
            }

            $parsedJob = $this->parseJobItem($job);
            if ($parsedJob) {
                $jobs[] = $parsedJob;
            }
        }

        return $jobs;
    }

    /**
     * Parse a single job item from API response
     */
    private function parseJobItem(array $job): ?array
    {
        // Handle different possible field names
        $jobId = $job['JobId'] ?? $job['job_id'] ?? $job['id'] ?? null;
        $companyId = $job['CompanyId'] ?? $job['company_id'] ?? null;
        $jobTitle = $job['JobTitle'] ?? $job['job_title'] ?? $job['title'] ?? null;
        $companyName = $job['CompanyName'] ?? $job['company_name'] ?? null;

        if (!$jobId || !$companyId || !$jobTitle || !$companyName) {
            return null;
        }

        return [
            'job_id' => (string)$jobId,
            'company_id' => (string)$companyId,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'job_location' => $job['JobLocation'] ?? $job['job_location'] ?? $job['location'] ?? null,
            'job_nature' => $job['JobNature'] ?? $job['job_nature'] ?? null,
            'job_category' => $job['JobCategory'] ?? $job['job_category'] ?? $job['category'] ?? null,
            'job_level' => $job['JobLevel'] ?? $job['job_level'] ?? $job['level'] ?? null,
            'salary' => $job['Salary'] ?? $job['salary'] ?? null,
            'experience' => $job['Experience'] ?? $job['experience'] ?? null,
            'deadline' => $job['Deadline'] ?? $job['deadline'] ?? null,
            'posted_date' => $job['PostedDate'] ?? $job['posted_date'] ?? $job['posted'] ?? null,
            'job_url' => $this->buildJobUrl((string)$jobId, (string)$companyId, $companyName),
            'company_logo' => $job['CompanyLogo'] ?? $job['company_logo'] ?? $job['logo'] ?? null,
        ];
    }

    /**
     * Parse job details from API response
     */
    private function parseJobDetails(array $data, string $jobId, string $companyId): array
    {
        // Handle different possible response structures
        $details = $data['data'] ?? $data['JobDetails'] ?? $data['job'] ?? $data;

        return [
            'job_id' => $jobId,
            'company_id' => $companyId,
            'job_title' => $details['JobTitle'] ?? $details['job_title'] ?? $details['title'] ?? null,
            'company_name' => $details['CompanyName'] ?? $details['company_name'] ?? null,
            'job_description' => $details['JobDescription'] ?? $details['job_description'] ?? $details['description'] ?? null,
            'job_requirements' => $details['JobRequirements'] ?? $details['job_requirements'] ?? $details['requirements'] ?? null,
            'job_responsibilities' => $details['JobResponsibilities'] ?? $details['job_responsibilities'] ?? $details['responsibilities'] ?? null,
            'benefits' => $details['Benefits'] ?? $details['benefits'] ?? null,
            'application_instructions' => $details['ApplicationInstructions'] ?? $details['application_instructions'] ?? $details['how_to_apply'] ?? null,
            'contact_email' => $details['ContactEmail'] ?? $details['contact_email'] ?? $details['email'] ?? null,
            'contact_phone' => $details['ContactPhone'] ?? $details['contact_phone'] ?? $details['phone'] ?? null,
            'contact_address' => $details['ContactAddress'] ?? $details['contact_address'] ?? $details['address'] ?? null,
            'job_location' => $details['JobLocation'] ?? $details['job_location'] ?? $details['location'] ?? null,
            'job_nature' => $details['JobNature'] ?? $details['job_nature'] ?? null,
            'job_category' => $details['JobCategory'] ?? $details['job_category'] ?? $details['category'] ?? null,
            'job_level' => $details['JobLevel'] ?? $details['job_level'] ?? $details['level'] ?? null,
            'salary' => $details['Salary'] ?? $details['salary'] ?? null,
            'experience' => $details['Experience'] ?? $details['experience'] ?? null,
            'deadline' => $details['Deadline'] ?? $details['deadline'] ?? null,
            'posted_date' => $details['PostedDate'] ?? $details['posted_date'] ?? $details['posted'] ?? null,
            'job_url' => $this->buildJobUrl($jobId, $companyId, $details['CompanyName'] ?? ''),
            'company_logo' => $details['CompanyLogo'] ?? $details['company_logo'] ?? $details['logo'] ?? null,
            'company_website' => $details['CompanyWebsite'] ?? $details['company_website'] ?? $details['website'] ?? null,
        ];
    }

    /**
     * Build job URL
     */
    private function buildJobUrl(string $jobId, string $companyId, string $companyName): string
    {
        $encodedCompanyName = urlencode($companyName);
        return "https://gateway.bdjobs.com/ActtivejobsTest/api/JobSubsystem/JobDetailsExistingInfo?deviceType=web&formValue=&JobId={$jobId}&CompanyId={$companyId}&CompanyName={$encodedCompanyName}";
    }

    /**
     * Calculate total pages from API response
     */
    private function calculateTotalPages(array $data, int $limit): int
    {
        $totalJobs = $data['total'] ?? $data['TotalJobs'] ?? $data['count'] ?? 0;

        if ($totalJobs > 0) {
            return (int)ceil($totalJobs / $limit);
        }

        // If no total count, estimate based on current page
        $currentPageJobs = count($data['data'] ?? $data['jobs'] ?? $data['JobSearch'] ?? []);
        return $currentPageJobs < $limit ? 1 : 10; // Assume at least 10 pages if we got a full page
    }

    /**
     * Get scraping statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'total_scraped' => 0,
            'new_jobs' => 0,
            'duplicates' => 0,
            'errors' => 0,
            'listings_saved' => 0,
            'details_saved' => 0,
        ];
    }

    /**
     * Update statistics
     */
    public function updateStats(string $key, int $value): void
    {
        if (isset($this->stats[$key])) {
            $this->stats[$key] += $value;
        }
    }
}
