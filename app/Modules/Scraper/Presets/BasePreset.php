<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Presets;

/**
 * BasePreset.php
 * Base class for all scraper presets
 */
abstract class BasePreset
{
    /**
     * Get preset unique key
     * 
     * @return string
     */
    abstract public function getKey(): string;

    /**
     * Get preset display name
     * 
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Get preset description
     * 
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Get preset category (News, Jobs, Products, etc.)
     * 
     * @return string
     */
    abstract public function getCategory(): string;

    /**
     * Get preset icon (emoji or icon class)
     * 
     * @return string
     */
    abstract public function getIcon(): string;

    /**
     * Get preset type (static, dynamic, api, etc.)
     * 
     * @return string
     */
    abstract public function getType(): string;

    /**
     * Get content type (article, job, product, etc.)
     * 
     * @return string
     */
    abstract public function getContentType(): string;

    /**
     * Get fetch interval in minutes
     * 
     * @return int
     */
    abstract public function getFetchInterval(): int;

    /**
     * Get delay between requests in seconds
     * 
     * @return int
     */
    abstract public function getDelay(): int;

    /**
     * Get maximum pages to scrape
     * 
     * @return int
     */
    abstract public function getMaxPages(): int;

    /**
     * Get pagination type (query, path, etc.)
     * 
     * @return string
     */
    abstract public function getPaginationType(): string;

    /**
     * Get pagination selector (CSS selector for next page link)
     * 
     * @return string|null
     */
    abstract public function getPaginationSelector(): ?string;

    /**
     * Get pagination pattern (URL pattern for pagination)
     * 
     * @return string|null
     */
    abstract public function getPaginationPattern(): ?string;

    /**
     * Get scraper configuration (selectors, etc.)
     * 
     * @return array
     */
    abstract public function getConfig(): array;

    /**
     * Get example URLs for this preset
     * 
     * @return array
     */
    abstract public function getExampleUrls(): array;

    /**
     * Check if this preset matches a given URL
     * 
     * @param string $url URL to check
     * @return bool
     */
    public function matchesUrl(string $url): bool
    {
        // Default implementation - can be overridden
        return false;
    }

    /**
     * Helper method to check domain matching
     * 
     * @param string $url URL to check
     * @param string $domain Domain to match against
     * @return bool
     */
    protected function matchesDomain(string $url, string $domain): bool
    {
        return str_contains(strtolower(parse_url($url, PHP_URL_HOST) ?? ''), strtolower($domain));
    }
}
