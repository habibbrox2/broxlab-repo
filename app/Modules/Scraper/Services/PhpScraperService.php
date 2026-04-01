<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Services;

use Spekulatius\PHPScraper\PHPScraper;
use Exception;

/**
 * PHP Scraper Service
 * High-level web scraping utility using spekulatius/phpscraper
 * Best for: Simple scraping tasks, meta tags, links, content extraction
 */
class PhpScraperService
{
    private PHPScraper $scraper;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'user_agent' => 'BroxLab Scraper/1.0',
            'timeout' => 30,
            'allow_redirects' => true,
            'verify_ssl' => true,
        ], $config);

        $this->scraper = new PHPScraper();
        $this->configureScraper();
    }

    /**
     * Configure the scraper with default settings
     */
    private function configureScraper(): void
    {
        $this->scraper->setConfig([
            'timeout' => $this->config['timeout'],
            'allow_redirects' => $this->config['allow_redirects'],
            'verify' => $this->config['verify_ssl'],
            'headers' => [
                'User-Agent' => $this->config['user_agent']
            ]
        ]);
    }

    /**
     * Scrape a URL and return comprehensive data
     */
    public function scrape(string $url): array
    {
        try {
            $this->scraper->go($url);

            return [
                'success' => true,
                'url' => $url,
                'title' => $this->scraper->title ?? '',
                'description' => $this->scraper->description ?? '',
                'keywords' => $this->scraper->keywordString ?? '',
                'content' => implode("\n\n", $this->scraper->cleanParagraphs() ?? []),
                'headings' => $this->scraper->outline() ?? [],
                'links' => $this->extractLinks(),
                'images' => $this->extractImages(),
                'meta' => $this->extractMetaTags(),
                'open_graph' => $this->extractOpenGraph(),
                'twitter_cards' => $this->extractTwitterCards(),
                'library' => 'PHP Scraper',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'url' => $url,
                'error' => $e->getMessage(),
                'library' => 'PHP Scraper',
            ];
        }
    }

    /**
     * Extract links with detailed information
     */
    private function extractLinks(): array
    {
        $links = [];
        try {
            // Extract links using XPath
            $linkData = $this->scraper->filterExtractAttributes('//a[@href]', ['href', '_text']);
            foreach (array_slice($linkData, 0, 10) as $link) {
                $links[] = [
                    'url' => $link[0] ?? '',
                    'text' => trim($link[1] ?? ''),
                    'nofollow' => false, // Would need more complex parsing
                    'external' => false, // Would need URL comparison
                ];
            }
        } catch (Exception $e) {
            // Fallback if XPath fails
        }
        return $links;
    }

    /**
     * Extract images with alt text and dimensions
     */
    private function extractImages(): array
    {
        $images = [];
        try {
            // Extract images using XPath
            $imageData = $this->scraper->filterExtractAttributes('//img[@src]', ['src', 'alt', 'title', 'width', 'height']);
            foreach (array_slice($imageData, 0, 10) as $image) {
                $images[] = [
                    'src' => $image[0] ?? '',
                    'alt' => $image[1] ?? '',
                    'title' => $image[2] ?? '',
                    'width' => $image[3] ?? null,
                    'height' => $image[4] ?? null,
                ];
            }
        } catch (Exception $e) {
            // Fallback if XPath fails
        }
        return $images;
    }

    /**
     * Extract meta tags
     */
    private function extractMetaTags(): array
    {
        return $this->scraper->metaTags ?? [];
    }

    /**
     * Extract Open Graph data
     */
    private function extractOpenGraph(): array
    {
        return $this->scraper->openGraph ?? [];
    }

    /**
     * Extract Twitter Card data
     */
    private function extractTwitterCards(): array
    {
        return $this->scraper->twitterCard ?? [];
    }

    /**
     * Get page content as clean text
     */
    public function getContent(string $url): string
    {
        $result = $this->scrape($url);
        return $result['success'] ? $result['content'] : '';
    }

    /**
     * Get page title
     */
    public function getTitle(string $url): string
    {
        $result = $this->scrape($url);
        return $result['success'] ? $result['title'] : '';
    }

    /**
     * Get all links from a page
     */
    public function getLinks(string $url): array
    {
        $result = $this->scrape($url);
        return $result['success'] ? $result['links'] : [];
    }

    /**
     * Get meta description
     */
    public function getDescription(string $url): string
    {
        $result = $this->scrape($url);
        return $result['success'] ? $result['description'] : '';
    }
}
