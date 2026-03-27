<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use App\Modules\Scraper\HttpClientService;
use App\Modules\Scraper\HtmlParserService;
use App\Modules\Scraper\RawHtmlStorageService;

/**
 * MobileDokanScraperService.php
 * Service for scraping mobile phone data from MobileDokan
 * Handles Cloudflare protection, JavaScript embedded data, and Bengali text encoding
 */
class MobileDokanScraperService
{
    private HttpClientService $httpClient;
    private HtmlParserService $parser;
    private RawHtmlStorageService $htmlStorage;
    private string $baseUrl;
    private array $config;
    private array $stats = [
        'total_scraped' => 0,
        'new_phones' => 0,
        'duplicates' => 0,
        'errors' => 0,
    ];

    public function __construct(
        HttpClientService $httpClient,
        ?HtmlParserService $parser = null,
        ?array $config = null,
        ?RawHtmlStorageService $htmlStorage = null
    ) {
        $this->httpClient = $httpClient;
        $this->parser = $parser ?? new HtmlParserService();
        $this->htmlStorage = $htmlStorage ?? new RawHtmlStorageService();
        $this->config = $config ?? $this->getDefaultConfig();
        $this->baseUrl = $this->config['base_url'];
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'base_url' => 'https://www.mobiledokan.com',
            'selectors' => [
                'phone_card' => '.phone-card, .product-card, .item',
                'phone_name' => 'h1, h2, .product-title, .phone-name',
                'phone_brand' => '.brand, .manufacturer',
                'phone_price' => '.price, .product-price',
                'phone_image' => 'img, .product-image',
                'phone_specs' => '.specs, .specifications, .details',
            ],
            'pagination' => [
                'enabled' => true,
                'max_pages' => 10,
                'page_param' => 'page',
            ],
            'rate_limit' => [
                'delay_ms' => 2000,
                'max_retries' => 3,
            ],
            'http' => [
                'timeout' => 30,
                'connect_timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9,bn;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                ],
            ],
        ];
    }

    /**
     * Scrape phone listings from a specific page
     *
     * @param int $page Page number (1-based)
     * @param int $limit Maximum number of phones to return
     * @return array{success: bool, phones: array, page: int, total_pages: int, error: string|null}
     */
    public function scrapePhoneListings(int $page = 1, int $limit = 20): array
    {
        $url = $this->buildListingsUrl($page);

        try {
            $response = $this->httpClient->get($url);

            if (!$response['success']) {
                logError("MobileDokanScraper: Failed to fetch page {$page}: " . ($response['error'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'phones' => [],
                    'page' => $page,
                    'total_pages' => 0,
                    'error' => $response['error'] ?? 'Failed to fetch page'
                ];
            }

            // Save raw HTML to file before parsing
            $saveResult = $this->htmlStorage->save($url, $response['body'], 'mobiledokan', 'listing');
            if (!$saveResult['success']) {
                logError("MobileDokanScraper: Failed to save raw HTML: " . ($saveResult['error'] ?? 'Unknown error'));
            }

            // Parse from saved file if available, otherwise use response body
            $htmlToParse = $response['body'];
            if ($saveResult['success'] && file_exists($saveResult['file_path'])) {
                $loadResult = $this->htmlStorage->load($url, 'mobiledokan', 'listing');
                if ($loadResult['success']) {
                    $htmlToParse = $loadResult['html'];
                }
            }

            $this->parser->loadHtml($htmlToParse, $this->baseUrl);
            $phones = $this->extractPhoneCards($limit);

            return [
                'success' => true,
                'phones' => $phones,
                'page' => $page,
                'total_pages' => $this->estimateTotalPages(),
                'error' => null,
                'raw_html_file' => $saveResult['file_path'] ?? null,
            ];
        } catch (\Exception $e) {
            logError("MobileDokanScraper: Exception on page {$page}: " . $e->getMessage());
            return [
                'success' => false,
                'phones' => [],
                'page' => $page,
                'total_pages' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Scrape phone detail page
     *
     * @param string $phoneUrl Full URL to phone detail page
     * @return array{success: bool, phone: array|null, error: string|null}
     */
    public function scrapePhoneDetail(string $phoneUrl): array
    {
        try {
            $response = $this->httpClient->get($phoneUrl);

            if (!$response['success']) {
                logError("MobileDokanScraper: Failed to fetch phone detail: " . ($response['error'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'phone' => null,
                    'error' => $response['error'] ?? 'Failed to fetch phone detail'
                ];
            }

            // Save raw HTML to file before parsing
            $saveResult = $this->htmlStorage->save($phoneUrl, $response['body'], 'mobiledokan', 'detail');
            if (!$saveResult['success']) {
                logError("MobileDokanScraper: Failed to save raw HTML: " . ($saveResult['error'] ?? 'Unknown error'));
            }

            // Parse from saved file if available, otherwise use response body
            $htmlToParse = $response['body'];
            if ($saveResult['success'] && file_exists($saveResult['file_path'])) {
                $loadResult = $this->htmlStorage->load($phoneUrl, 'mobiledokan', 'detail');
                if ($loadResult['success']) {
                    $htmlToParse = $loadResult['html'];
                }
            }

            $this->parser->loadHtml($htmlToParse, $this->baseUrl);
            $phone = $this->extractPhoneDetail();

            return [
                'success' => true,
                'phone' => $phone,
                'error' => null,
                'raw_html_file' => $saveResult['file_path'] ?? null,
            ];
        } catch (\Exception $e) {
            logError("MobileDokanScraper: Exception on phone detail: " . $e->getMessage());
            return [
                'success' => false,
                'phone' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Scrape all pages up to max_pages
     *
     * @param int $maxPages Maximum number of pages to scrape
     * @param callable|null $progressCallback Callback for progress updates
     * @return array{success: bool, phones: array, stats: array, error: string|null}
     */
    public function scrapeAllPages(int $maxPages = 10, ?callable $progressCallback = null): array
    {
        $allPhones = [];
        $maxPages = min($maxPages, $this->config['pagination']['max_pages']);

        for ($page = 1; $page <= $maxPages; $page++) {
            $result = $this->scrapePhoneListings($page, 100); // Get up to 100 phones per page

            if (!$result['success']) {
                $this->stats['errors']++;
                if ($progressCallback) {
                    $progressCallback($page, $maxPages, false, $result['error']);
                }
                continue;
            }

            $allPhones = array_merge($allPhones, $result['phones']);
            $this->stats['total_scraped'] += count($result['phones']);

            if ($progressCallback) {
                $progressCallback($page, $maxPages, true, count($result['phones']));
            }

            // Stop if no phones found on this page
            if (empty($result['phones'])) {
                break;
            }

            // Rate limiting delay
            if ($page < $maxPages) {
                usleep($this->config['rate_limit']['delay_ms'] * 1000);
            }
        }

        return [
            'success' => true,
            'phones' => $allPhones,
            'stats' => $this->stats,
            'error' => null
        ];
    }

    /**
     * Build URL for phone listings page
     */
    private function buildListingsUrl(int $page): string
    {
        $url = $this->baseUrl;

        if ($page > 1) {
            $url .= '?' . http_build_query([$this->config['pagination']['page_param'] => $page]);
        }

        return $url;
    }

    /**
     * Extract phone cards from current page
     */
    private function extractPhoneCards(int $limit): array
    {
        $phones = [];
        $phoneCards = $this->parser->extractAll($this->config['selectors']['phone_card']);

        foreach ($phoneCards as $index => $cardHtml) {
            if ($index >= $limit) {
                break;
            }

            $phone = $this->parsePhoneCard($cardHtml);
            if ($phone) {
                $phones[] = $phone;
            }
        }

        return $phones;
    }

    /**
     * Parse a single phone card HTML
     */
    private function parsePhoneCard(string $cardHtml): ?array
    {
        // Create a temporary parser for this card
        $cardParser = new HtmlParserService($cardHtml, $this->baseUrl);

        // Extract name
        $name = $cardParser->extractTextMultiple($this->config['selectors']['phone_name']);
        if (!$name) {
            return null;
        }

        // Extract brand
        $brand = $cardParser->extractTextMultiple($this->config['selectors']['phone_brand']);
        if (!$brand) {
            $brand = $this->extractBrandFromName($name);
        }

        // Extract price
        $priceText = $cardParser->extractTextMultiple($this->config['selectors']['phone_price']);
        $price = $this->parsePrice($priceText);
        $priceValue = $this->extractPriceValue($priceText);

        // Extract image URL
        $imageUrl = $cardParser->extractAttribute($this->config['selectors']['phone_image'], 'src');
        if (!$imageUrl) {
            $imageUrl = $cardParser->extractAttribute($this->config['selectors']['phone_image'], 'data-src');
        }

        // Extract link to detail page
        $linkElement = $cardParser->extractAttribute('a', 'href');
        if (!$linkElement) {
            return null;
        }

        // Build full URL
        $fullUrl = $this->resolveUrl($linkElement);

        // Generate slug from URL
        $slug = $this->generateSlug($fullUrl);

        return [
            'slug' => $slug,
            'name' => $name,
            'brand' => $brand,
            'price' => $price,
            'price_value' => $priceValue,
            'url' => $fullUrl,
            'image_url' => $imageUrl,
        ];
    }

    /**
     * Extract phone detail from detail page
     */
    private function extractPhoneDetail(): ?array
    {
        // Try to extract from JavaScript embedded data first
        $specs = $this->extractJavaScriptData();

        // If no JS data, try to extract from HTML
        if (empty($specs)) {
            $specs = $this->extractSpecsFromHtml();
        }

        // Extract additional details
        $name = $this->parser->extractTextMultiple($this->config['selectors']['phone_name']);
        $brand = $this->parser->extractTextMultiple($this->config['selectors']['phone_brand']);
        $priceText = $this->parser->extractTextMultiple($this->config['selectors']['phone_price']);
        $price = $this->parsePrice($priceText);
        $priceValue = $this->extractPriceValue($priceText);
        $imageUrl = $this->parser->extractAttribute($this->config['selectors']['phone_image'], 'src');

        return [
            'name' => $name,
            'brand' => $brand ?: $this->extractBrandFromName($name),
            'price' => $price,
            'price_value' => $priceValue,
            'url' => $this->baseUrl,
            'image_url' => $imageUrl,
            'specs' => $specs,
        ];
    }

    /**
     * Extract data from JavaScript embedded in HTML
     */
    private function extractJavaScriptData(): array
    {
        $html = $this->parser->getCrawler()->html();
        $specs = [];

        // Try to find window.__INITIAL_STATE__ pattern
        if (preg_match('/window\.__INITIAL_STATE__\s*=\s*({.*?});/s', $html, $matches)) {
            try {
                $data = json_decode($matches[1], true);

                if (is_array($data)) {
                    // Flatten nested data structure
                    $specs = $this->flattenJsData($data);
                }
            } catch (\Exception $e) {
                logError("MobileDokanScraper: Failed to parse JS data: " . $e->getMessage());
            }
        }

        return $specs;
    }

    /**
     * Flatten nested JavaScript data structure
     */
    private function flattenJsData(array $data, string $prefix = ''): array
    {
        $specs = [];
        $specKeys = [
            'প্রসেসর',
            'র‍্যাম',
            'স্টোরেজ',
            'ডিসপ্লে',
            'ব্যাটারি',
            'ক্যামেরা',
            'সেলফি ক্যামেরা',
            'অপারেটিং সিস্টেম',
            'প্রসেসর',
            'র‍্যাম',
            'স্টোরেজ',
            'ডিসপ্লে',
            'ব্যাটারি',
            'ক্যামেরা',
            'সেলফি ক্যামেরা',
            'অপারেটিং সিস্টেম',
        ];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $specs = array_merge($specs, $this->flattenJsData($value, $prefix . $key . '.'));
            } elseif (is_string($value) && in_array($key, $specKeys, true)) {
                $specs[$prefix . $key] = trim($value);
            }
        }

        return $specs;
    }

    /**
     * Extract specifications from HTML (fallback)
     */
    private function extractSpecsFromHtml(): array
    {
        $specs = [];
        $specElements = $this->parser->extractAll($this->config['selectors']['phone_specs']);

        foreach ($specElements as $element) {
            $text = trim($element);
            if (!empty($text)) {
                // Try to parse key-value pairs
                if (preg_match('/^([^:]+):\s*(.+)$/', $text, $matches)) {
                    $key = trim($matches[1]);
                    $value = trim($matches[2]);
                    $specs[$key] = $value;
                }
            }
        }

        return $specs;
    }

    /**
     * Parse price text to extract numeric value
     */
    private function parsePrice(?string $priceText): ?string
    {
        if (!$priceText) {
            return null;
        }

        // Remove Bengali currency symbol and commas
        $cleanPrice = preg_replace('/[৳,\s]/', '', $priceText);

        return $cleanPrice ?: null;
    }

    /**
     * Extract numeric price value
     */
    private function extractPriceValue(?string $priceText): ?int
    {
        if (!$priceText) {
            return null;
        }

        // Extract numbers from price text
        if (preg_match('/(\d+)/', $priceText, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * Extract brand from phone name
     */
    private function extractBrandFromName(string $name): string
    {
        // Common brands
        $brands = [
            'Samsung',
            'Apple',
            'Xiaomi',
            'Realme',
            'Oppo',
            'Vivo',
            'OnePlus',
            'Huawei',
            'Nokia',
            'Motorola',
            'Sony',
            'LG',
            'HTC',
            'BlackBerry',
            'Alcatel',
            'ZTE',
            'Lenovo',
            'Asus',
            'Acer',
            'Microsoft',
            'Google',
            'Tecno',
            'Infinix',
            'Itel',
            'Symphony',
            'Walton',
        ];

        foreach ($brands as $brand) {
            if (stripos($name, $brand) !== false) {
                return $brand;
            }
        }

        return 'Unknown';
    }

    /**
     * Generate URL-friendly slug
     */
    private function generateSlug(string $url): string
    {
        // Extract last part of URL path
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', trim($path, '/'));
        $lastPart = end($parts);

        // Remove extension if present
        $slug = preg_replace('/\.[^.]+$/', '', $lastPart);

        // Convert to lowercase and replace spaces with hyphens
        $slug = strtolower(str_replace(' ', '-', $slug));

        // Remove special characters
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);

        return $slug ?: 'phone-' . time();
    }

    /**
     * Resolve relative URLs to absolute
     */
    private function resolveUrl(string $url): string
    {
        if (empty($url)) {
            return $this->baseUrl;
        }

        // Already absolute URL
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        // Relative URL
        return rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');
    }

    /**
     * Estimate total number of pages
     */
    private function estimateTotalPages(): int
    {
        // Try to find pagination elements
        $pagination = $this->parser->extractAll('.pagination a, .page-link, [class*="page"]');

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
            'new_phones' => 0,
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
