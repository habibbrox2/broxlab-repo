<?php

namespace App\Modules\Scraper;

use App\Modules\Scraper\HttpClientService;
use App\Modules\Scraper\HtmlParserService;
use App\Modules\Scraper\RawHtmlStorageService;

/**
 * GSMArena Bangladesh Scraper Service
 * 
 * Scrapes mobile device specifications and prices from gsmarena.com.bd
 * 
 * @package BroxBhai
 * @since 2026-03-27
 */
class GSMArenaBDScraperService
{
    private HttpClientService $httpClient;
    private HtmlParserService $parser;
    private RawHtmlStorageService $htmlStorage;
    private array $config;
    private array $stats = [
        'pages_scraped' => 0,
        'devices_found' => 0,
        'new_devices' => 0,
        'duplicates' => 0,
        'errors' => 0,
        'total_scraped' => 0,
    ];

    public function __construct(array $config = [], ?RawHtmlStorageService $htmlStorage = null)
    {
        $this->httpClient = new HttpClientService();
        $this->parser = new HtmlParserService();
        $this->htmlStorage = $htmlStorage ?? new RawHtmlStorageService();
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Get configuration (public for testing)
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        $configPath = dirname(__DIR__, 2) . '/Modules/Scraper/config/gsmarena_bd.php';

        if (file_exists($configPath)) {
            return require $configPath;
        }

        // Fallback configuration
        return [
            'base_url' => 'https://www.gsmarena.com.bd',
            'phones_url' => '/phones.php',
            'selectors' => [
                'phone_container' => '.product-item',
                'phone_link' => 'a',
                'phone_name' => 'h3',
                'phone_image' => 'img',
                'phone_price' => '.price',
                'phone_specs_link' => 'a[data-specs]',
                'pagination' => '.pagination a',
                'next_page' => 'a.next, a[rel="next"]',
            ],
            'pagination' => [
                'enabled' => true,
                'max_pages' => 10,
                'delay' => 3000,
            ],
            'http' => [
                'timeout' => 30,
                'user_agents' => [
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ],
            ],
        ];
    }

    /**
     * Parse price text to numeric value (public for testing)
     */
    public function parsePrice(string $priceText): ?float
    {
        if (empty($priceText)) {
            return null;
        }

        // Remove currency symbols and text
        $clean = $priceText;
        $clean = str_replace($this->config['price_parsing']['currency_symbol'] ?? '৳', '', $clean);

        foreach ($this->config['price_parsing']['remove_text'] ?? [] as $text) {
            $clean = str_replace($text, '', $clean);
        }

        // Remove commas and spaces
        $clean = preg_replace('/[^\d.]/', '', $clean);

        return $clean ? (float) $clean : null;
    }

    /**
     * Get next page URL from HTML (public for testing)
     */
    public function getNextPageUrl(string $html): ?string
    {
        $this->parser->loadHtml($html, $this->config['base_url']);

        // Try next page selector first - extract href attribute directly
        $nextUrl = $this->parser->extractAttribute($this->config['selectors']['next_page'], 'href');
        if ($nextUrl) {
            return $this->parser->resolveUrl($nextUrl);
        }

        // Try pagination links
        $paginationElements = $this->parser->extractAll($this->config['selectors']['pagination']);
        $currentPage = $this->stats['pages_scraped'];

        foreach ($paginationElements as $element) {
            $text = $element['text'] ?? '';
            if (preg_match('/next|>\s*$/i', $text)) {
                $url = $element['attributes']['href'] ?? null;
                if ($url) {
                    return $this->parser->resolveUrl($url);
                }
            }
        }

        return null;
    }

    /**
     * Update statistics
     */
    public function updateStats(string $key, int $value = 1): void
    {
        if (isset($this->stats[$key])) {
            $this->stats[$key] += $value;
        }
        $this->stats['total_scraped'] = array_sum([
            $this->stats['new_devices'],
            $this->stats['duplicates'],
        ]);
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Scrape all pages with progress callback
     */
    public function scrapeAllPages(int $maxPages = 10, ?callable $progressCallback = null): array
    {
        $allDevices = [];
        $currentPage = 1;
        $pageUrl = $this->config['base_url'] . $this->config['phones_url'];

        while ($currentPage <= $maxPages && $pageUrl) {
            $this->stats['pages_scraped']++;

            // Fetch page
            $response = $this->httpClient->get($pageUrl, [
                'headers' => $this->config['http']['headers'] ?? [],
                'user_agent' => $this->getRandomUserAgent(),
            ]);

            if (!$response['success']) {
                $this->updateStats('errors');
                if ($progressCallback) {
                    $progressCallback($currentPage, $maxPages, false, $response['error'] ?? 'Failed to fetch page');
                }
                break;
            }

            $html = $response['body'];

            // Save raw HTML to file before parsing
            $saveResult = $this->htmlStorage->save($pageUrl, $html, 'gsmarena_bd', 'listing');
            if (!$saveResult['success']) {
                error_log("GSMArenaBDScraperService: Failed to save raw HTML: " . ($saveResult['error'] ?? 'Unknown error'));
            }

            // Parse from saved file if available, otherwise use response body
            $htmlToParse = $html;
            if ($saveResult['success'] && file_exists($saveResult['file_path'])) {
                $loadResult = $this->htmlStorage->load($pageUrl, 'gsmarena_bd', 'listing');
                if ($loadResult['success']) {
                    $htmlToParse = $loadResult['html'];
                }
            }

            // Extract devices from page
            $devices = $this->scrapePhoneListings($htmlToParse);
            $this->stats['devices_found'] += count($devices);

            if ($progressCallback) {
                $progressCallback($currentPage, $maxPages, true, $devices);
            }

            $allDevices = array_merge($allDevices, $devices);

            // Get next page URL
            $pageUrl = $this->getNextPageUrl($htmlToParse);

            // Apply delay between pages
            if ($pageUrl && $currentPage < $maxPages) {
                $delay = $this->config['pagination']['delay'] ?? 3000;
                usleep($delay * 1000); // Convert to microseconds
            }

            $currentPage++;
        }

        return $allDevices;
    }

    /**
     * Cache HTML content to storage
     */
    private function cacheHtml(string $html, string $url, int $pageNumber): void
    {
        // Define cache directory
        $cacheDir = dirname(__DIR__, 3) . '/storage/scraper_cache/gsmarena_bd';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $filename = sprintf(
            'page_%03d_%s.html',
            $pageNumber,
            md5($url)
        );

        $filepath = $cacheDir . '/' . $filename;

        // Add metadata header
        $metadata = [
            'url' => $url,
            'page' => $pageNumber,
            'scraped_at' => date('Y-m-d H:i:s'),
            'size_bytes' => strlen($html)
        ];

        $content = "<!-- GSMArena BD Scraper Cache\n";
        foreach ($metadata as $key => $value) {
            $content .= "  {$key}: {$value}\n";
        }
        $content .= "-->\n" . $html;

        file_put_contents($filepath, $content);
    }

    /**
     * Scrape phone listings from HTML
     */
    public function scrapePhoneListings(string $html): array
    {
        $this->parser->loadHtml($html, $this->config['base_url']);

        $devices = [];
        $phoneElements = $this->parser->extractAll($this->config['selectors']['phone_container']);

        foreach ($phoneElements as $element) {
            $device = $this->extractPhoneItem($element);
            if ($device) {
                $devices[] = $device;
            }
        }

        return $devices;
    }

    /**
     * Extract phone item from element
     */
    private function extractPhoneItem($element): ?array
    {
        // Since HtmlParserService doesn't have extractFirst with context,
        // we need to work with the element differently.
        // For now, we'll extract from the full HTML using selectors
        // This is a simplified approach - in a real implementation,
        // we would need to parse the element HTML

        // Get the HTML of the element
        $elementHtml = $element['html'] ?? '';
        if (empty($elementHtml)) {
            return null;
        }

        // Create a temporary parser for this element
        $tempParser = new HtmlParserService($elementHtml, $this->config['base_url']);

        // Extract name
        $name = $tempParser->extractText($this->config['selectors']['phone_name']);
        if (empty($name)) {
            return null;
        }

        // Extract price
        $priceText = $tempParser->extractText($this->config['selectors']['phone_price']);
        $priceValue = $this->parsePrice($priceText ?? '');

        // Extract image URL
        $imageUrl = $tempParser->extractAttribute($this->config['selectors']['phone_image'], 'src');

        // Extract link URL
        $url = $tempParser->extractAttribute($this->config['selectors']['phone_link'], 'href');

        // Extract specs URL from data-specs attribute
        $specsUrl = $tempParser->extractAttribute($this->config['selectors']['phone_specs_link'], 'data-specs');

        // Generate slug
        $slug = $this->generateSlug($name);

        return [
            'slug' => $slug,
            'name' => $name,
            'price_text' => $priceText ?? '',
            'price_value' => $priceValue,
            'price_currency' => 'BDT',
            'url' => $url ? $tempParser->resolveUrl($url) : null,
            'image_url' => $imageUrl ? $tempParser->resolveUrl($imageUrl) : null,
            'specs_url' => $specsUrl ? $tempParser->resolveUrl($specsUrl) : null,
            'scraped_at' => date('Y-m-d H:i:s'),
        ];
    }


    /**
     * Get random user agent
     */
    private function getRandomUserAgent(): string
    {
        $userAgents = $this->config['http']['user_agents'] ?? [];
        if (empty($userAgents)) {
            return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
        }

        return $userAgents[array_rand($userAgents)];
    }

    /**
     * Generate slug from name
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug .= '-' . substr(md5($name), 0, 8);

        return $slug;
    }

    /**
     * Scrape device details from detail page
     */
    public function scrapeDeviceDetails(string $url): ?array
    {
        $response = $this->httpClient->get($url, [
            'headers' => $this->config['http']['headers'] ?? [],
            'user_agent' => $this->getRandomUserAgent(),
        ]);

        if (!$response['success']) {
            $this->updateStats('errors');
            return null;
        }

        $html = $response['body'];

        // Save raw HTML to file before parsing
        $saveResult = $this->htmlStorage->save($url, $html, 'gsmarena_bd', 'detail');
        if (!$saveResult['success']) {
            error_log("GSMArenaBDScraperService: Failed to save raw HTML: " . ($saveResult['error'] ?? 'Unknown error'));
        }

        // Parse from saved file if available, otherwise use response body
        $htmlToParse = $html;
        if ($saveResult['success'] && file_exists($saveResult['file_path'])) {
            $loadResult = $this->htmlStorage->load($url, 'gsmarena_bd', 'detail');
            if ($loadResult['success']) {
                $htmlToParse = $loadResult['html'];
            }
        }

        $this->parser->loadHtml($htmlToParse, $this->config['base_url']);

        // Extract title
        $title = $this->parser->extractText($this->config['selectors']['detail_title']);

        // Extract price from detail page
        $priceText = $this->parser->extractText($this->config['selectors']['detail_price']);
        $priceValue = $this->parsePrice($priceText ?? '');

        // Extract image
        $imageUrl = $this->parser->extractAttribute($this->config['selectors']['detail_image'], 'src');

        // Extract specifications
        $specs = $this->extractSpecifications($htmlToParse);

        return [
            'title' => $title ?? '',
            'price_text' => $priceText ?? '',
            'price_value' => $priceValue,
            'image_url' => $imageUrl ? $this->parser->resolveUrl($imageUrl) : null,
            'specifications' => $specs,
            'detail_url' => $url,
            'scraped_at' => date('Y-m-d H:i:s'),
            'raw_html_file' => $saveResult['file_path'] ?? null,
        ];
    }

    /**
     * Extract specifications from detail page HTML
     */
    private function extractSpecifications(string $html): array
    {
        $this->parser->loadHtml($html, $this->config['base_url']);

        $specs = [];
        $specTables = $this->parser->extractAll($this->config['selectors']['specs_table']);

        foreach ($specTables as $table) {
            // For each table, we need to extract all spec rows
            // Since extractAll doesn't support context, we'll need a different approach
            // For simplicity, we'll extract from the full HTML with more specific selectors

            // Try to extract specs using a combined selector approach
            $specNames = $this->parser->extractAll($this->config['selectors']['specs_name']);
            $specValues = $this->parser->extractAll($this->config['selectors']['specs_value']);

            // Pair names and values (assuming they appear in the same order)
            $count = min(count($specNames), count($specValues));
            for ($i = 0; $i < $count; $i++) {
                $name = $specNames[$i]['text'] ?? '';
                $value = $specValues[$i]['text'] ?? '';

                if ($name && $value) {
                    $specs[$this->normalizeSpecName($name)] = $value;
                }
            }

            break; // Only process first table for now
        }

        return $specs;
    }

    /**
     * Normalize specification name
     */
    private function normalizeSpecName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);
        $name = trim($name, '_');

        // Common abbreviations
        $abbreviations = [
            'processor' => 'cpu',
            'ram' => 'memory',
            'storage' => 'storage',
            'display' => 'screen',
            'camera' => 'camera',
            'battery' => 'battery',
            'os' => 'os',
            'network' => 'network',
            'body' => 'dimensions',
            'sound' => 'audio',
        ];

        foreach ($abbreviations as $full => $abbr) {
            if (strpos($name, $full) !== false) {
                return $abbr;
            }
        }

        return $name;
    }
}
