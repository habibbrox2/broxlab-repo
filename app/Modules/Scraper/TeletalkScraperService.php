<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use App\Modules\Scraper\HttpClientService;
use App\Modules\Scraper\HtmlParserService;
use App\Helpers\ErrorLogging;

/**
 * TeletalkScraperService.php
 * Service for scraping Teletalk government jobs
 * Uses DOM-based parsing with Symfony DomCrawler
 */
class TeletalkScraperService
{
    private HttpClientService $httpClient;
    private HtmlParserService $parser;
    private string $baseUrl;
    private array $config;
    private array $stats = [
        'total_scraped' => 0,
        'new_jobs' => 0,
        'duplicates' => 0,
        'errors' => 0,
    ];

    public function __construct(
        HttpClientService $httpClient,
        ?HtmlParserService $parser = null,
        ?array $config = null
    ) {
        $this->httpClient = $httpClient;
        $this->parser = $parser ?? new HtmlParserService();
        $this->config = $config ?? $this->getDefaultConfig();
        $this->baseUrl = $this->config['base_url'];
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'base_url' => 'https://alljobs.teletalk.com.bd',
            'selectors' => [
                'job_card' => '.job-wrapper',
                'job_link' => '.job-card',
                'job_title' => '.job-title h3',
                'job_image' => '.job-card-img-wrapper img',
                'job_openings' => '.total-openings',
            ],
            'pagination' => [
                'enabled' => true,
                'max_pages' => 10,
                'page_param' => 'page',
            ],
            'rate_limit' => [
                'delay_ms' => 1000,
                'max_retries' => 3,
            ],
        ];
    }

    /**
     * Scrape job listings from a specific page
     *
     * @param int $page Page number (1-based)
     * @param int $limit Maximum number of jobs to return
     * @return array{success: bool, jobs: array, page: int, total_pages: int, error: string|null}
     */
    public function scrapeJobListings(int $page = 1, int $limit = 20): array
    {
        $url = $this->buildListingsUrl($page);

        try {
            $response = $this->httpClient->get($url);

            if (!$response['success']) {
                ErrorLogging::logError("TeletalkScraper: Failed to fetch page {$page}: " . ($response['error'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'jobs' => [],
                    'page' => $page,
                    'total_pages' => 0,
                    'error' => $response['error'] ?? 'Failed to fetch page'
                ];
            }

            $this->parser->loadHtml($response['body'], $this->baseUrl);
            $jobs = $this->extractJobCards($limit);

            return [
                'success' => true,
                'jobs' => $jobs,
                'page' => $page,
                'total_pages' => $this->estimateTotalPages(),
                'error' => null
            ];

        } catch (\Exception $e) {
            ErrorLogging::logError("TeletalkScraper: Exception on page {$page}: " . $e->getMessage());
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
     * Scrape job detail page
     *
     * @param string $jobUrl Full URL to job detail page
     * @return array{success: bool, job: array|null, error: string|null}
     */
    public function scrapeJobDetail(string $jobUrl): array
    {
        try {
            $response = $this->httpClient->get($jobUrl);

            if (!$response['success']) {
                ErrorLogging::logError("TeletalkScraper: Failed to fetch job detail: " . ($response['error'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'job' => null,
                    'error' => $response['error'] ?? 'Failed to fetch job detail'
                ];
            }

            $this->parser->loadHtml($response['body'], $this->baseUrl);
            $job = $this->extractJobDetail();

            return [
                'success' => true,
                'job' => $job,
                'error' => null
            ];

        } catch (\Exception $e) {
            ErrorLogging::logError("TeletalkScraper: Exception on job detail: " . $e->getMessage());
            return [
                'success' => false,
                'job' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Scrape all pages up to max_pages
     *
     * @param int $maxPages Maximum number of pages to scrape
     * @param callable|null $progressCallback Callback for progress updates
     * @return array{success: bool, jobs: array, stats: array, error: string|null}
     */
    public function scrapeAllPages(int $maxPages = 10, ?callable $progressCallback = null): array
    {
        $allJobs = [];
        $maxPages = min($maxPages, $this->config['pagination']['max_pages']);

        for ($page = 1; $page <= $maxPages; $page++) {
            $result = $this->scrapeJobListings($page);

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
     * Build URL for job listings page
     */
    private function buildListingsUrl(int $page): string
    {
        $url = $this->baseUrl . '/jobs/government';
        
        if ($page > 1) {
            $url .= '?' . http_build_query([$this->config['pagination']['page_param'] => $page]);
        }

        return $url;
    }

    /**
     * Extract job cards from current page
     */
    private function extractJobCards(int $limit): array
    {
        $jobs = [];
        $jobCards = $this->parser->extractAll($this->config['selectors']['job_card']);

        foreach ($jobCards as $index => $cardHtml) {
            if ($index >= $limit) {
                break;
            }

            $job = $this->parseJobCard($cardHtml);
            if ($job) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    /**
     * Parse a single job card HTML
     */
    private function parseJobCard(string $cardHtml): ?array
    {
        // Create a temporary parser for this card
        $cardParser = new HtmlParserService($cardHtml, $this->baseUrl);

        // Extract job ID from URL
        $linkElement = $cardParser->extractAttribute($this->config['selectors']['job_link'], 'href');
        if (!$linkElement) {
            return null;
        }

        $jobId = $this->extractJobIdFromUrl($linkElement);
        if (!$jobId) {
            return null;
        }

        // Extract title/organization
        $title = $cardParser->extractText($this->config['selectors']['job_title']);
        if (!$title) {
            return null;
        }

        // Extract image URL
        $imageUrl = $cardParser->extractAttribute($this->config['selectors']['job_image'], 'src');
        
        // Extract openings count
        $openingsText = $cardParser->extractText($this->config['selectors']['job_openings']);
        $openings = $this->parseOpenings($openingsText);

        // Build full URL
        $fullUrl = $this->baseUrl . $linkElement;

        return [
            'job_id' => $jobId,
            'title' => $title,
            'organization' => $title, // Title and organization are the same in this structure
            'openings' => $openings,
            'url' => $fullUrl,
            'image_url' => $imageUrl,
        ];
    }

    /**
     * Extract job ID from URL
     */
    private function extractJobIdFromUrl(string $url): ?string
    {
        // URL format: /jobs/government/1126?jobId=13431
        if (preg_match('/jobId=(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        // Alternative format: /jobs/government/1126
        if (preg_match('/\/(\d+)(?:\?|$)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Parse openings count from text
     */
    private function parseOpenings(?string $text): int
    {
        if (!$text) {
            return 0;
        }

        // Extract number from "Openings: 2" format
        if (preg_match('/(\d+)/', $text, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    /**
     * Extract job detail from detail page
     */
    private function extractJobDetail(): ?array
    {
        // This can be expanded to extract more detailed information
        // For now, return basic structure
        return [
            'title' => $this->parser->extractText('h1, .job-title'),
            'description' => $this->parser->extractText('.job-description, .description'),
            'requirements' => $this->parser->extractText('.requirements, .qualifications'),
        ];
    }

    /**
     * Estimate total number of pages
     */
    private function estimateTotalPages(): int
    {
        // Try to find pagination elements
        $pagination = $this->parser->extractAll('.pagination a, .page-link');
        
        if (empty($pagination)) {
            return 1;
        }

        $maxPage = 1;
        foreach ($pagination as $pageText) {
            if (preg_match('/(\d+)/', $pageText, $matches)) {
                $maxPage = max($maxPage, (int)$matches[1]);
            }
        }

        return $maxPage;
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
