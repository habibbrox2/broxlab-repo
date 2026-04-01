<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use App\Models\ScraperModel;
use App\Modules\Scraper\Scrapers;

/**
 * ScraperFactory - Factory for creating scraper instances
 *
 * Provides a centralized way to create and configure scraper services
 * based on source configuration and requirements.
 */
class ScraperFactory
{
    /**
     * Create a scraper service instance
     *
     * @param ScraperModel $model
     * @return ScraperService
     */
    public static function createService(ScraperModel $model): ScraperService
    {
        return new ScraperService($model);
    }

    /**
     * Create an advance scraper instance
     *
     * @return AdvanceScraper
     */
    public static function createAdvanceScraper(): Scrapers\AdvanceScraper
    {
        return new Scrapers\AdvanceScraper();
    }

    /**
     * Create a specific scraper service based on library type
     *
     * @param string $library
     * @return object
     */
    public static function createLibraryService(string $library): object
    {
        return match (strtolower($library)) {
            'php-scraper' => new Services\PhpScraperService(),
            'roach' => new Services\RoachService(),
            'php-spider' => new Services\PhpSpiderService(),
            'panther' => new Services\PantherService(),
            default => throw new \InvalidArgumentException("Unknown scraper library: {$library}")
        };
    }

    /**
     * Get available scraper libraries
     *
     * @return array
     */
    public static function getAvailableLibraries(): array
    {
        return [
            'php-scraper' => [
                'name' => 'PHP Scraper',
                'description' => 'High-level web scraping utility for meta data and content extraction',
                'best_for' => 'Simple scraping tasks, meta data extraction'
            ],
            'roach' => [
                'name' => 'Roach PHP',
                'description' => 'Full crawling framework with spiders and pipelines',
                'best_for' => 'Complex crawling tasks and large-scale scraping'
            ],
            'php-spider' => [
                'name' => 'PHP Spider',
                'description' => 'Configurable web crawler with depth limits',
                'best_for' => 'Site-wide crawling with robots.txt compliance'
            ],
            'panther' => [
                'name' => 'Symfony Panther',
                'description' => 'Browser automation for JavaScript-heavy sites',
                'best_for' => 'Dynamic content and SPAs requiring real browser'
            ]
        ];
    }

    /**
     * Auto-select the best scraper library for a given source
     *
     * @param array $source
     * @return string
     */
    public static function autoSelectLibrary(array $source): string
    {
        // If browser automation is required
        if (!empty($source['use_browser'])) {
            return 'panther';
        }

        // If deep crawling is needed
        if (!empty($source['scrape_depth']) && $source['scrape_depth'] > 1) {
            return 'php-spider';
        }

        // If complex processing is needed
        if (!empty($source['advance_config'])) {
            return 'roach';
        }

        // Default to PHP Scraper for simple tasks
        return 'php-scraper';
    }
}
