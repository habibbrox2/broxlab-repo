<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Scrapers;

use App\Modules\Scraper\Services\PhpScraperService;
use App\Modules\Scraper\Services\RoachService;
use App\Modules\Scraper\Services\PhpSpiderService;
use App\Modules\Scraper\Services\PantherService;
use Exception;

/**
 * AdvanceScraper.php
 * Advanced scraper implementation using multiple scraping libraries
 * Provides unified interface for different scraping strategies
 */
class AdvanceScraper
{
    private array $source = [];
    private array $config = [];
    private ?PhpScraperService $phpScraper = null;
    private ?RoachService $roachService = null;
    private ?PhpSpiderService $phpSpiderService = null;
    private ?PantherService $pantherService = null;

    /**
     * Set source configuration
     *
     * @param array $source Source configuration
     * @return $this
     */
    public function setSource(array $source): self
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Get source configuration
     *
     * @return array
     */
    public function getSource(): array
    {
        return $this->source;
    }

    /**
     * Set scraper configuration
     *
     * @param array $config Configuration options
     * @return $this
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        return $this;
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'strategy' => 'auto', // 'auto', 'php-scraper', 'roach', 'php-spider', 'panther'
            'user_agent' => 'BroxLab AdvanceScraper/1.0',
            'timeout' => 30,
            'max_depth' => 2,
            'follow_links' => false,
            'extract_dynamic' => false,
            'use_cache' => true,
        ];
    }

    /**
     * Execute advanced scraping operation
     *
     * @return array Scraping results
     */
    public function scrape(): array
    {
        try {
            $url = $this->source['url'] ?? '';
            if (empty($url)) {
                return [
                    'success' => false,
                    'error' => 'No URL provided in source configuration',
                ];
            }

            $strategy = $this->config['strategy'] ?? 'auto';
            $result = $this->executeStrategy($strategy, $url);

            return array_merge($result, [
                'strategy_used' => $strategy,
                'timestamp' => date('Y-m-d H:i:s'),
                'config' => $this->config,
            ]);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'strategy_used' => $this->config['strategy'] ?? 'auto',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Execute the appropriate scraping strategy
     */
    private function executeStrategy(string $strategy, string $url): array
    {
        switch ($strategy) {
            case 'php-scraper':
                return $this->scrapeWithPhpScraper($url);

            case 'roach':
                return $this->scrapeWithRoach($url);

            case 'php-spider':
                return $this->scrapeWithPhpSpider($url);

            case 'panther':
                return $this->scrapeWithPanther($url);

            case 'auto':
            default:
                return $this->autoSelectStrategy($url);
        }
    }

    /**
     * Automatically select the best strategy based on URL and requirements
     */
    private function autoSelectStrategy(string $url): array
    {
        // Check if dynamic content is needed
        if ($this->config['extract_dynamic']) {
            return $this->scrapeWithPanther($url);
        }

        // Check if crawling is needed
        if ($this->config['follow_links'] || $this->config['max_depth'] > 1) {
            return $this->scrapeWithRoach($url);
        }

        // Default to PHP Scraper for simple tasks
        return $this->scrapeWithPhpScraper($url);
    }

    /**
     * Scrape using PHP Scraper
     */
    private function scrapeWithPhpScraper(string $url): array
    {
        $service = $this->getPhpScraperService();
        $result = $service->scrape($url);

        return [
            'success' => $result['success'],
            'data' => $result['success'] ? [
                'title' => $result['title'],
                'description' => $result['description'],
                'content' => $result['content'],
                'links' => $result['links'],
                'images' => $result['images'],
                'meta' => $result['meta'],
                'word_count' => $result['word_count'] ?? str_word_count($result['content']),
            ] : [],
            'library' => $result['library'] ?? 'PHP Scraper',
            'raw_result' => $result,
        ];
    }

    /**
     * Scrape using Roach
     */
    private function scrapeWithRoach(string $url): array
    {
        $service = $this->getRoachService();
        $result = $service->crawl($url, [
            'max_depth' => $this->config['max_depth'],
            'follow_links' => $this->config['follow_links'],
            'extract_data' => true,
        ]);

        return [
            'success' => $result['success'],
            'data' => $result['success'] ? ($result['data'] ?? $result['results'] ?? []) : [],
            'library' => 'Roach PHP',
            'stats' => $result['success'] ? ($result['stats'] ?? []) : [],
            'raw_result' => $result,
        ];
    }

    /**
     * Scrape using PHP Spider
     */
    private function scrapeWithPhpSpider(string $url): array
    {
        $service = $this->getPhpSpiderService();
        $result = $service->crawl($url, [
            'discoverer' => 'css',
            'selector' => 'a',
            'extract_data' => true,
        ]);

        return [
            'success' => $result['success'],
            'data' => $result['success'] ? $result['resources'] : [],
            'library' => 'PHP Spider',
            'stats' => $result['success'] ? $result['stats'] : [],
            'raw_result' => $result,
        ];
    }

    /**
     * Scrape using Symfony Panther
     */
    private function scrapeWithPanther(string $url): array
    {
        $service = $this->getPantherService();
        $result = $service->visit($url, [
            'wait_for_element' => $this->config['wait_for_element'] ?? null,
            'wait_timeout' => $this->config['wait_timeout'] ?? 10,
            'take_screenshot' => $this->config['take_screenshot'] ?? false,
            'extract_data' => true,
        ]);

        return [
            'success' => $result['success'],
            'data' => $result['success'] ? [
                'title' => $result['title'],
                'content' => $result['content'],
                'links' => $result['links'],
                'images' => $result['images'],
                'forms' => $result['forms'],
                'screenshot' => $result['screenshot'] ?? null,
            ] : [],
            'library' => 'Symfony Panther',
            'raw_result' => $result,
        ];
    }

    /**
     * Get PHP Scraper service instance
     */
    private function getPhpScraperService(): PhpScraperService
    {
        if (!$this->phpScraper) {
            $this->phpScraper = new PhpScraperService([
                'user_agent' => $this->config['user_agent'],
                'timeout' => $this->config['timeout'],
            ]);
        }
        return $this->phpScraper;
    }

    /**
     * Get Roach service instance
     */
    private function getRoachService(): RoachService
    {
        if (!$this->roachService) {
            $this->roachService = new RoachService([
                'user_agent' => $this->config['user_agent'],
                'timeout' => $this->config['timeout'],
                'max_requests' => 50, // Limit for safety
            ]);
        }
        return $this->roachService;
    }

    /**
     * Get PHP Spider service instance
     */
    private function getPhpSpiderService(): PhpSpiderService
    {
        if (!$this->phpSpiderService) {
            $this->phpSpiderService = new PhpSpiderService([
                'user_agent' => $this->config['user_agent'],
                'max_depth' => $this->config['max_depth'],
                'cache_enabled' => $this->config['use_cache'],
            ]);
        }
        return $this->phpSpiderService;
    }

    /**
     * Get Panther service instance
     */
    private function getPantherService(): PantherService
    {
        if (!$this->pantherService) {
            $this->pantherService = new PantherService([
                'user_agent' => $this->config['user_agent'],
                'timeout' => $this->config['timeout'],
                'headless' => true, // Always use headless for server environment
            ]);
        }
        return $this->pantherService;
    }

    /**
     * Clean up resources
     */
    public function cleanup(): void
    {
        if ($this->pantherService) {
            $this->pantherService->close();
        }
    }

    /**
     * Destructor - ensure cleanup
     */
    public function __destruct()
    {
        $this->cleanup();
    }
}
