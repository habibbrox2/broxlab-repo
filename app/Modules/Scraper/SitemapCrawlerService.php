<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * SitemapCrawlerService.php
 * Multi-Layer Sitemap Crawler for Mobile Phone Data
 * 
 * FLOW:
 * 1. sitemap_index.xml → child sitemaps
 * 2. child sitemaps → page URLs  
 * 3. Filter URLs (keep /product/, ignore /category/, /tag/, /page/)
 * 4. Add to crawl queue (url, status, depth, created_at)
 * 5. Crawl product pages → extract phone data
 * 6. Store in database (title, price, brand, image, spec_json, source_url, scraped_at)
 */

use App\Models\MobileModel;
use DOMDocument;
use DOMXPath;
use Exception;

class SitemapCrawlerService
{
    private $mysqli;
    private $mobileModel;
    private $requestDelay = 2;
    private $maxPages = 50;
    private $debug = false;

    // URL filters - keep only product URLs
    private $urlFilters = [
        'keep' => ['/product/', '/phone/', '/mobile/', '/device/'],
        'ignore' => ['/category/', '/tag/', '/page/', '/author/', '/search/', '/archive/']
    ];

    // Phone data selectors (can be customized per source)
    private $selectors = [
        'title' => 'h1, .product-title, .phone-name',
        'price' => '.price, .product-price, [itemprop="price"]',
        'image' => '.product-image img, .phone-image img, [itemprop="image"]',
        'specs_table' => 'table.specs, .specifications table, .specs-table',
        'brand' => '.brand, .manufacturer, [itemprop="brand"]',
        'release_date' => '.release-date, .launch-date, [itemprop="releaseDate"]'
    ];

    private $crawlStatus = [
        'step' => 0,
        'step_name' => '',
        'sitemap_urls_found' => 0,
        'page_urls_found' => 0,
        'pages_crawled' => 0,
        'phones_saved' => 0,
        'errors' => [],
        'warnings' => []
    ];

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
        $this->mobileModel = new MobileModel($mysqli);
    }

    public function setRequestDelay(int $seconds): self
    {
        $this->requestDelay = max(1, min(10, $seconds));
        return $this;
    }

    public function setMaxPages(int $max): self
    {
        $this->maxPages = max(1, $max);
        return $this;
    }

    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    public function setUrlFilters(array $keep, array $ignore): self
    {
        $this->urlFilters['keep'] = $keep;
        $this->urlFilters['ignore'] = $ignore;
        return $this;
    }

    public function setSelectors(array $selectors): self
    {
        $this->selectors = array_merge($this->selectors, $selectors);
        return $this;
    }

    public function getCrawlStatus(): array
    {
        return $this->crawlStatus;
    }

    /**
     * Main entry point - Run full sitemap crawling pipeline
     */
    public function run(string $sitemapIndexUrl): array
    {
        $this->resetStatus();

        $result = [
            'success' => false,
            'sitemap_url' => $sitemapIndexUrl,
            'steps_completed' => [],
            'phones_saved' => 0,
            'errors' => [],
            'warnings' => []
        ];

        try {
            // STEP 1: Parse Sitemap Index
            $this->updateStatus(1, 'Parsing Sitemap Index');
            $childSitemaps = $this->parseSitemapIndex($sitemapIndexUrl);
            $this->crawlStatus['sitemap_urls_found'] = count($childSitemaps);

            if (empty($childSitemaps)) {
                // Try direct sitemap (not index)
                $childSitemaps = [$sitemapIndexUrl];
            }
            $result['steps_completed'][] = 'parse_sitemap_index';

            // STEP 2: Parse Child Sitemaps & Extract URLs
            $this->updateStatus(2, 'Parsing Child Sitemaps');
            $pageUrls = $this->parseChildSitemaps($childSitemaps);
            $this->crawlStatus['page_urls_found'] = count($pageUrls);

            if (empty($pageUrls)) {
                $result['warnings'][] = 'No page URLs found in sitemaps';
                return $result;
            }
            $result['steps_completed'][] = 'extract_page_urls';

            // STEP 3: Filter URLs
            $this->updateStatus(3, 'Filtering URLs');
            $filteredUrls = $this->filterUrls($pageUrls);

            if (empty($filteredUrls)) {
                $result['warnings'][] = 'No URLs passed filters';
                return $result;
            }
            $result['steps_completed'][] = 'filter_urls';

            // STEP 4: Crawl Product Pages & Extract Data
            $this->updateStatus(4, 'Crawling Product Pages');
            $saved = $this->crawlProductPages($filteredUrls);
            $this->crawlStatus['phones_saved'] = $saved;
            $result['phones_saved'] = $saved;
            $result['steps_completed'][] = 'crawl_and_save';

            $result['success'] = $saved > 0;
            $result['errors'] = $this->crawlStatus['errors'];
            $result['warnings'] = $this->crawlStatus['warnings'];
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $this->crawlStatus['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * STEP 1: Parse Sitemap Index XML
     * Extract all <loc> values inside <sitemap> nodes
     */
    public function parseSitemapIndex(string $url): array
    {
        $this->log("Fetching sitemap index: $url");

        $xml = $this->fetchXml($url);
        if (empty($xml)) {
            throw new Exception('Failed to fetch sitemap index');
        }

        $sitemaps = [];

        try {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadXML($xml);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);

            // Check if it's a sitemap index
            $sitemapIndex = $xpath->query('//sitemapindex/sitemap/loc');

            if ($sitemapIndex->length > 0) {
                // It's a sitemap index
                foreach ($sitemapIndex as $node) {
                    $loc = trim($node->textContent);
                    if (!empty($loc)) {
                        $sitemaps[] = $loc;
                    }
                }
            } else {
                // Check if it's a urlset (direct sitemap)
                $urlset = $xpath->query('//urlset/url/loc');
                if ($urlset->length > 0) {
                    // Return the URL itself as a single "child" sitemap
                    return [$url];
                }
            }

            $sitemaps = array_unique($sitemaps);
            $this->log("Found " . count($sitemaps) . " child sitemaps");
        } catch (Exception $e) {
            $this->log("Error parsing sitemap index: " . $e->getMessage());
            throw new Exception('Failed to parse sitemap index: ' . $e->getMessage());
        }

        return $sitemaps;
    }

    /**
     * STEP 2: Parse Child Sitemaps
     * Extract all <loc> values inside <url> nodes
     */
    public function parseChildSitemaps(array $sitemapUrls): array
    {
        $allUrls = [];

        foreach ($sitemapUrls as $sitemapUrl) {
            $this->log("Parsing child sitemap: $sitemapUrl");

            try {
                $xml = $this->fetchXml($sitemapUrl);
                if (empty($xml)) {
                    $this->crawlStatus['warnings'][] = "Empty response from: $sitemapUrl";
                    continue;
                }

                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadXML($xml);
                libxml_clear_errors();

                $xpath = new DOMXPath($dom);
                $urlNodes = $xpath->query('//urlset/url/loc');

                foreach ($urlNodes as $node) {
                    $loc = trim($node->textContent);
                    if (!empty($loc)) {
                        $allUrls[] = $loc;
                    }
                }

                // Rate limiting between sitemaps
                sleep($this->requestDelay);
            } catch (Exception $e) {
                $this->crawlStatus['warnings'][] = "Error parsing $sitemapUrl: " . $e->getMessage();
                continue;
            }
        }

        $allUrls = array_unique($allUrls);
        $this->log("Found " . count($allUrls) . " page URLs");

        return $allUrls;
    }

    /**
     * STEP 3: Filter URLs
     * Keep only product URLs, ignore category/tag/page URLs
     */
    public function filterUrls(array $urls): array
    {
        $filtered = [];

        foreach ($urls as $url) {
            $shouldKeep = false;

            // Check if URL matches any "keep" patterns
            foreach ($this->urlFilters['keep'] as $pattern) {
                if (stripos($url, $pattern) !== false) {
                    $shouldKeep = true;
                    break;
                }
            }

            // If it matched a keep pattern, check if it also matches ignore patterns
            if ($shouldKeep) {
                foreach ($this->urlFilters['ignore'] as $pattern) {
                    if (stripos($url, $pattern) !== false) {
                        $shouldKeep = false;
                        $this->crawlStatus['warnings'][] = "URL filtered out: $url";
                        break;
                    }
                }
            }

            if ($shouldKeep) {
                $filtered[] = $url;
            }
        }

        $this->log("Filtered to " . count($filtered) . " URLs from " . count($urls));
        return array_slice($filtered, 0, $this->maxPages);
    }

    /**
     * STEP 4: Crawl Product Pages & Extract Phone Data
     */
    public function crawlProductPages(array $urls): int
    {
        $saved = 0;
        $total = count($urls);

        $this->log("Starting to crawl $total product pages");

        foreach ($urls as $index => $url) {
            $this->updateStatus(4, "Crawling page " . ($index + 1) . " of $total");

            try {
                // Check for duplicates
                if ($this->urlExistsInQueue($url)) {
                    $this->crawlStatus['warnings'][] = "Duplicate URL skipped: $url";
                    continue;
                }

                // Fetch page HTML
                $html = $this->fetchHtml($url);

                if (empty($html)) {
                    $this->log("Empty response from: $url");
                    continue;
                }

                // Extract phone data
                $phoneData = $this->extractPhoneData($html, $url);

                if (empty($phoneData['model_name'])) {
                    $this->crawlStatus['warnings'][] = "No phone data found for: $url";
                    continue;
                }

                // Check if phone already exists
                if ($this->phoneExists($phoneData['brand_name'], $phoneData['model_name'])) {
                    $this->crawlStatus['warnings'][] = "Phone already exists: {$phoneData['brand_name']} {$phoneData['model_name']}";
                    continue;
                }

                // Save phone to database
                $phoneId = $this->savePhone($phoneData);

                if ($phoneId > 0) {
                    $saved++;
                    $this->log("Saved phone: {$phoneData['brand_name']} {$phoneData['model_name']}");
                }
            } catch (Exception $e) {
                $this->crawlStatus['errors'][] = "Error crawling $url: " . $e->getMessage();
                $this->log("Error: " . $e->getMessage());
            }

            // Rate limiting
            if ($index < $total - 1) {
                sleep($this->requestDelay);
            }
        }

        $this->crawlStatus['pages_crawled'] = $total;
        $this->log("Crawled $total pages, saved $saved phones");

        return $saved;
    }

    /**
     * Extract phone data from HTML
     */
    public function extractPhoneData(string $html, string $url): array
    {
        $data = [
            'brand_name' => '',
            'model_name' => '',
            'official_price' => 0,
            'unofficial_price' => 0,
            'status' => 'official',
            'release_date' => date('Y-m-d'),
            'image_url' => '',
            'specifications' => []
        ];

        try {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);

            // Extract title/phone name
            $titleSelectors = ['//h1', '//h1[@class="product-title"]', '//h1[@class="phone-name"]', '//meta[@property="og:title"]/@content'];
            foreach ($titleSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes && $nodes->length > 0) {
                    $title = trim($nodes->item(0)->textContent);
                    if (!empty($title)) {
                        $data['model_name'] = $title;
                        // Try to extract brand from title
                        $data['brand_name'] = $this->extractBrandFromTitle($title);
                        break;
                    }
                }
            }

            // Extract price
            $priceSelectors = ['//span[@class="price"]', '//span[@class="product-price"]', '//meta[@property="product:price:amount"]/@content', '//*[contains(@class, "price")]'];
            foreach ($priceSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes && $nodes->length > 0) {
                    $priceText = $nodes->item(0)->textContent;
                    $data['official_price'] = $this->parsePrice($priceText);
                    if ($data['official_price'] > 0) break;
                }
            }

            // Extract image
            $imageSelectors = ['//meta[@property="og:image"]/@content', '//div[@class="product-image"]//img/@src', '//div[@class="phone-image"]//img/@src', '//img[@class="product-img"]/@src'];
            foreach ($imageSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes && $nodes->length > 0) {
                    $data['image_url'] = $nodes->item(0)->textContent;
                    if (!empty($data['image_url'])) break;
                }
            }

            // Extract release date
            $dateSelectors = ['//span[@class="release-date"]', '//span[@class="launch-date"]', '//meta[@property="product:release_date"]/@content'];
            foreach ($dateSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes && $nodes->length > 0) {
                    $dateText = $nodes->item(0)->textContent;
                    $data['release_date'] = $this->parseDate($dateText);
                    break;
                }
            }

            // Extract specifications table
            $specTables = $xpath->query('//table[contains(@class, "specs")] | //table[contains(@class, "specifications")]');
            if ($specTables->length > 0) {
                $data['specifications'] = $this->extractSpecsFromTable($specTables->item(0));
            }

            // If brand is still empty, try to extract from page
            if (empty($data['brand_name'])) {
                $brandSelectors = ['//span[@class="brand"]', '//a[@class="brand-link"]', '//meta[@property="product:brand"]/@content'];
                foreach ($brandSelectors as $selector) {
                    $nodes = $xpath->query($selector);
                    if ($nodes && $nodes->length > 0) {
                        $data['brand_name'] = trim($nodes->item(0)->textContent);
                        if (!empty($data['brand_name'])) break;
                    }
                }
            }

            // Set default status
            $data['status'] = 'official';
        } catch (Exception $e) {
            $this->log("Error extracting phone data: " . $e->getMessage());
        }

        return $data;
    }

    /**
     * Extract brand from title
     */
    private function extractBrandFromTitle(string $title): string
    {
        $knownBrands = ['Samsung', 'Apple', 'Xiaomi', 'Redmi', 'Realme', 'Oppo', 'Vivo', 'Huawei', 'OnePlus', 'Motorola', 'Nokia', 'Tecno', 'Infinix', 'Itel', 'Walton', 'Symphony'];

        $titleLower = strtolower($title);

        foreach ($knownBrands as $brand) {
            if (stripos($titleLower, strtolower($brand)) !== false) {
                // Special case for Redmi (it's a Xiaomi sub-brand)
                if (strtolower($brand) === 'redmi') {
                    return 'Xiaomi';
                }
                return $brand;
            }
        }

        // Try to get first word as brand
        $words = explode(' ', $title);
        return !empty($words[0]) ? $words[0] : '';
    }

    /**
     * Parse price from text
     */
    private function parsePrice(string $priceText): float
    {
        // Remove currency symbols and formatting
        $cleaned = preg_replace('/[^\d.,]/', '', $priceText);
        // Handle comma as thousand separator
        $cleaned = str_replace(',', '', $cleaned);
        // Get numeric value
        preg_match('/[\d.]+/', $cleaned, $matches);

        return !empty($matches[0]) ? (float)$matches[0] : 0;
    }

    /**
     * Parse date string
     */
    private function parseDate(string $dateText): string
    {
        $timestamp = strtotime($dateText);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        return date('Y-m-d');
    }

    /**
     * Extract specifications from table
     */
    private function extractSpecsFromTable(\DOMNode $table): array
    {
        $specs = [];

        try {
            $xpath = new DOMXPath($table->ownerDocument);
            $rows = $xpath->query('.//tr', $table);

            foreach ($rows as $row) {
                $cells = $xpath->query('.//td', $row);
                if ($cells->length >= 2) {
                    $key = trim($cells->item(0)->textContent);
                    $value = trim($cells->item(1)->textContent);
                    if (!empty($key) && !empty($value)) {
                        $specs[$key] = $value;
                    }
                }
            }
        } catch (Exception $e) {
            $this->log("Error extracting specs: " . $e->getMessage());
        }

        return $specs;
    }

    /**
     * Fetch XML content
     */
    private function fetchXml(string $url): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/xml, text/xml, application/xhtml+xml',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            throw new Exception("HTTP $httpCode - $error");
        }

        return $content;
    }

    /**
     * Fetch HTML content
     */
    private function fetchHtml(string $url): string
    {
        $this->log("Fetching: $url");

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate'
            ],
            CURLOPT_ENCODING => ''
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: $error");
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP error: $httpCode");
        }

        return $content;
    }

    /**
     * Check if URL exists in queue (deduplication)
     */
    private function urlExistsInQueue(string $url): bool
    {
        // Normalize URL - remove query parameters
        $normalizedUrl = $this->normalizeUrl($url);

        // Check in mobiles table by source_url if column exists
        $result = $this->mysqli->query("SHOW COLUMNS FROM mobiles LIKE 'source_url'");
        if ($result && $result->num_rows > 0) {
            $stmt = $this->mysqli->prepare("SELECT COUNT(*) as cnt FROM mobiles WHERE source_url = ?");
            $stmt->bind_param("s", $normalizedUrl);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return (int)$row['cnt'] > 0;
        }

        return false;
    }

    /**
     * Check if phone already exists
     */
    private function phoneExists(string $brand, string $model): bool
    {
        if (empty($brand) || empty($model)) {
            return false;
        }

        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as cnt FROM mobiles WHERE brand_name = ? AND model_name = ?");
        $stmt->bind_param("ss", $brand, $model);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int)$row['cnt'] > 0;
    }

    /**
     * Save phone to database
     */
    private function savePhone(array $data): int
    {
        try {
            // Insert main phone record
            $stmt = $this->mysqli->prepare(
                "INSERT INTO mobiles (brand_name, model_name, official_price, unofficial_price, status, release_date, is_official, source_url) 
                VALUES (?, ?, ?, ?, ?, ?, 1, ?)"
            );

            $sourceUrl = $data['source_url'] ?? '';
            $stmt->bind_param(
                "ssddsss",
                $data['brand_name'],
                $data['model_name'],
                $data['official_price'],
                $data['unofficial_price'],
                $data['status'],
                $data['release_date'],
                $sourceUrl
            );

            $stmt->execute();
            $mobileId = (int)$stmt->insert_id;
            $stmt->close();

            // Insert specifications if any
            if (!empty($data['specifications']) && $mobileId > 0) {
                foreach ($data['specifications'] as $key => $value) {
                    $specStmt = $this->mysqli->prepare("INSERT INTO mobile_specs (mobile_id, spec_key, spec_value) VALUES (?, ?, ?)");
                    $specStmt->bind_param("iss", $mobileId, $key, $value);
                    $specStmt->execute();
                    $specStmt->close();
                }
            }

            // Insert image if available
            if (!empty($data['image_url']) && $mobileId > 0) {
                $imgStmt = $this->mysqli->prepare("INSERT INTO mobile_images (mobile_id, image_url) VALUES (?, ?)");
                $imgStmt->bind_param("is", $mobileId, $data['image_url']);
                $imgStmt->execute();
                $imgStmt->close();
            }

            return $mobileId;
        } catch (Exception $e) {
            $this->log("Error saving phone: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Normalize URL - remove query parameters
     */
    private function normalizeUrl(string $url): string
    {
        $parsed = parse_url($url);
        $normalized = $parsed['scheme'] . '://' . $parsed['host'];

        if (isset($parsed['path'])) {
            $normalized .= $parsed['path'];
        }

        return $normalized;
    }

    /**
     * Update crawl status
     */
    private function updateStatus(int $step, string $stepName): void
    {
        $this->crawlStatus['step'] = $step;
        $this->crawlStatus['step_name'] = $stepName;
        $this->log("Step $step: $stepName");
    }

    /**
     * Reset status
     */
    private function resetStatus(): void
    {
        $this->crawlStatus = [
            'step' => 0,
            'step_name' => '',
            'sitemap_urls_found' => 0,
            'page_urls_found' => 0,
            'pages_crawled' => 0,
            'phones_saved' => 0,
            'errors' => [],
            'warnings' => []
        ];
    }

    /**
     * Log message
     */
    private function log(string $message): void
    {
        if ($this->debug) {
            error_log("[SitemapCrawler] $message");
        }
    }

    /**
     * Create required database tables if not exist
     */
    public function ensureTablesExist(): bool
    {
        try {
            // Add source_url column to mobiles if not exists
            $result = $this->mysqli->query("SHOW COLUMNS FROM mobiles LIKE 'source_url'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE mobiles ADD COLUMN source_url VARCHAR(2048) DEFAULT ''");
            }

            return true;
        } catch (Exception $e) {
            error_log("Error ensuring tables exist: " . $e->getMessage());
            return false;
        }
    }
}
