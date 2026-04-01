<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Diagnostics;

use App\Modules\Scraper\Services\PhpScraperService;
use App\Modules\Scraper\Services\PantherService;
use App\Modules\Scraper\Services\RoachService;
use App\Modules\Scraper\Services\PhpSpiderService;
use Exception;

/**
 * Scraper Services Tester
 * Tests individual scraping services to verify their functionality
 */
class ServiceTester
{
    private array $testResults = [];

    /**
     * Test PhpScraper service
     * 
     * @param string $url URL to test against
     * @return array Test results
     */
    public function testPhpScraper(string $url): array
    {
        $result = [
            'service' => 'PhpScraper',
            'url' => $url,
            'timestamp' => date('Y-m-d H:i:s'),
            'success' => false,
            'data' => [],
            'error' => null,
            'response_time' => null
        ];

        try {
            $start = microtime(true);
            $service = new PhpScraperService([
                'timeout' => 30,
                'user_agent' => 'BroxLab Service Tester/1.0'
            ]);
            $scrapeResult = $service->scrape($url);
            $end = microtime(true);

            $result['success'] = $scrapeResult['success'];
            $result['response_time'] = $end - $start;

            if ($scrapeResult['success']) {
                $result['data'] = [
                    'title' => $scrapeResult['title'] ?? '',
                    'content_length' => strlen($scrapeResult['content'] ?? ''),
                    'links_count' => count($scrapeResult['links'] ?? []),
                    'images_count' => count($scrapeResult['images'] ?? []),
                    'has_meta' => !empty($scrapeResult['meta'] ?? []),
                ];
            } else {
                $result['error'] = $scrapeResult['error'] ?? 'Unknown error';
            }
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Test Panther service (for JavaScript-heavy sites)
     * 
     * @param string $url URL to test against
     * @return array Test results
     */
    public function testPanther(string $url): array
    {
        $result = [
            'service' => 'Panther',
            'url' => $url,
            'timestamp' => date('Y-m-d H:i:s'),
            'success' => false,
            'data' => [],
            'error' => null,
            'response_time' => null
        ];

        try {
            $start = microtime(true);
            $service = new PantherService([
                'timeout' => 30,
                'user_agent' => 'BroxLab Service Tester/1.0',
                'headless' => true
            ]);
            $scrapeResult = $service->visit($url, [
                'extract_data' => true,
                'wait_for_element' => 'body',
                'wait_timeout' => 10
            ]);
            $end = microtime(true);

            $result['success'] = $scrapeResult['success'];
            $result['response_time'] = $end - $start;

            if ($scrapeResult['success']) {
                $result['data'] = [
                    'title' => $scrapeResult['title'] ?? '',
                    'content_length' => strlen($scrapeResult['content'] ?? ''),
                    'links_count' => count($scrapeResult['links'] ?? []),
                    'images_count' => count($scrapeResult['images'] ?? []),
                    'has_forms' => !empty($scrapeResult['forms'] ?? []),
                    'has_screenshot' => !empty($scrapeResult['screenshot'] ?? null),
                ];
            } else {
                $result['error'] = $scrapeResult['error'] ?? 'Unknown error';
            }
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Test Roach service (for crawling)
     * 
     * @param string $url URL to test against
     * @return array Test results
     */
    public function testRoach(string $url): array
    {
        $result = [
            'service' => 'Roach',
            'url' => $url,
            'timestamp' => date('Y-m-d H:i:s'),
            'success' => false,
            'data' => [],
            'error' => null,
            'response_time' => null
        ];

        try {
            $start = microtime(true);
            $service = new RoachService([
                'user_agent' => 'BroxLab Service Tester/1.0',
                'timeout' => 30,
                'max_requests' => 5 // Limit for testing
            ]);
            $scrapeResult = $service->crawl($url, [
                'max_depth' => 1,
                'follow_links' => true,
                'extract_data' => true
            ]);
            $end = microtime(true);

            $result['success'] = $scrapeResult['success'];
            $result['response_time'] = $end - $start;

            if ($scrapeResult['success']) {
                $data = $scrapeResult['data'] ?? $scrapeResult['results'] ?? [];
                $result['data'] = [
                    'pages_crawled' => is_array($data) ? count($data) : 0,
                    'has_data' => !empty($data),
                    'stats' => $scrapeResult['stats'] ?? [],
                ];
            } else {
                $result['error'] = $scrapeResult['error'] ?? 'Unknown error';
            }
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Test PHP Spider service (for resource discovery)
     * 
     * @param string $url URL to test against
     * @return array Test results
     */
    public function testPhpSpider(string $url): array
    {
        $result = [
            'service' => 'PHP Spider',
            'url' => $url,
            'timestamp' => date('Y-m-d H:i:s'),
            'success' => false,
            'data' => [],
            'error' => null,
            'response_time' => null
        ];

        try {
            $start = microtime(true);
            $service = new PhpSpiderService([
                'user_agent' => 'BroxLab Service Tester/1.0',
                'max_depth' => 1,
                'cache_enabled' => true
            ]);
            $scrapeResult = $service->crawl($url, [
                'discoverer' => 'css',
                'selector' => 'a',
                'extract_data' => true
            ]);
            $end = microtime(true);

            $result['success'] = $scrapeResult['success'];
            $result['response_time'] = $end - $start;

            if ($scrapeResult['success']) {
                $resources = $scrapeResult['resources'] ?? [];
                $result['data'] = [
                    'resources_found' => is_array($resources) ? count($resources) : 0,
                    'has_resources' => !empty($resources),
                    'stats' => $scrapeResult['stats'] ?? [],
                ];
            } else {
                $result['error'] = $scrapeResult['error'] ?? 'Unknown error';
            }
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Run all service tests on a URL
     * 
     * @param string $url URL to test against
     * @return array All test results
     */
    public function runAllTests(string $url): array
    {
        $this->testResults = [
            'url' => $url,
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => [
                'php_scraper' => $this->testPhpScraper($url),
                'panther' => $this->testPanther($url),
                'roach' => $this->testRoach($url),
                'php_spider' => $this->testPhpSpider($url)
            ],
            'summary' => [
                'total_tests' => 4,
                'passed_tests' => 0,
                'failed_tests' => 0
            ]
        ];

        // Count passed/failed tests
        foreach ($this->testResults['tests'] as $test) {
            if ($test['success']) {
                $this->testResults['summary']['passed_tests']++;
            } else {
                $this->testResults['summary']['failed_tests']++;
            }
        }

        return $this->testResults;
    }

    /**
     * Get formatted test results for display
     * 
     * @return string Formatted results
     */
    public function getFormattedResults(): string
    {
        $output = [];
        $output[] = "Scraper Services Test Results";
        $output[] = "==============================";
        $output[] = "URL: {$this->testResults['url']}";
        $output[] = "Timestamp: {$this->testResults['timestamp']}";
        $output[] = "";
        $output[] = "SUMMARY:";
        $output[] = "  Total Tests: {$this->testResults['summary']['total_tests']}";
        $output[] = "  Passed: {$this->testResults['summary']['passed_tests']}";
        $output[] = "  Failed: {$this->testResults['summary']['failed_tests']}";
        $output[] = "";

        foreach ($this->testResults['tests'] as $serviceName => $test) {
            $output[] = strtoupper($serviceName) . " TEST:";
            $output[] = "  URL: {$test['url']}";
            $output[] = "  Success: " . ($test['success'] ? 'YES' : 'NO');
            $output[] = "  Response Time: " . round($test['response_time'] ?? 0, 2) . "s";

            if ($test['success']) {
                $output[] = "  Data:";
                foreach ($test['data'] as $key => $value) {
                    if (is_array($value)) {
                        $output[] = "    {$key}: " . (is_array($value) ? count($value) . ' items' : print_r($value, true));
                    } else {
                        $output[] = "    {$key}: {$value}";
                    }
                }
            } else {
                $output[] = "  Error: {$test['error']}";
            }
            $output[] = "";
        }

        return implode("\n", $output);
    }
}
