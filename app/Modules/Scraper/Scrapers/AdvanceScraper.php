<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Scrapers;

use App\Modules\Scraper\HtmlFetcher;
use App\Modules\Scraper\Services\PhpScraperService;
use App\Modules\Scraper\Services\RoachService;
use App\Modules\Scraper\Services\PhpSpiderService;
use App\Modules\Scraper\Services\PantherService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use Symfony\Component\CssSelector\CssSelectorConverter;

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
    private ?CssSelectorConverter $converter = null;
    private bool $testMode = false;
    private int $maxItems = 10;

    /**
     * Set source configuration
     *
     * @param array $source Source configuration
     * @return $this
     */
    public function setSource(array $source): self
    {
        $this->source = $source;
        $this->config = $this->getDefaultConfig();
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
     * Set test mode
     */
    public function setTestMode(bool $testMode): self
    {
        $this->testMode = $testMode;
        return $this;
    }

    /**
     * Set max items to scrape
     */
    public function setMaxItems(int $maxItems): self
    {
        $this->maxItems = $maxItems;
        return $this;
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        $config = [
            'strategy' => (($this->source['type'] ?? '') === 'api') ? 'api' : 'auto', // 'auto', 'api', 'php-scraper', 'roach', 'php-spider', 'panther'
            'user_agent' => 'BroxLab AdvanceScraper/1.0',
            'timeout' => 30,
            'max_depth' => min(2, $this->source['scrape_depth'] ?? 2), // Respect source limits
            'follow_links' => false,
            'extract_dynamic' => $this->source['use_browser'] ?? false,
            'use_cache' => true,
            'max_requests' => 50, // Safety limit
        ];

        // Merge source selectors if available
        if (!empty($this->source['selectors'])) {
            $selectors = json_decode($this->source['selectors'], true);
            if (is_array($selectors)) {
                $config['selectors'] = $selectors;
            }
        }

        // Merge advance config if available
        if (!empty($this->source['advance_config'])) {
            $advanceConfig = json_decode($this->source['advance_config'], true);
            if (is_array($advanceConfig)) {
                $config = array_merge($config, $advanceConfig);
            }
        }

        return $config;
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

            if (empty($this->config)) {
                $this->config = $this->getDefaultConfig();
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
        if (($this->source['type'] ?? '') !== 'api' && $this->hasSelectorConfiguration()) {
            $selectorResult = $this->scrapeWithSelectors($url);
            if (($selectorResult['success'] ?? false) || !empty($selectorResult['data'])) {
                return $selectorResult;
            }
        }

        switch ($strategy) {
            case 'api':
                return $this->scrapeWithApi($url);

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
        if ($this->hasSelectorConfiguration()) {
            return $this->scrapeWithSelectors($url);
        }

        // Check if dynamic content is needed
        if (($this->config['extract_dynamic'] ?? false)) {
            return $this->scrapeWithPanther($url);
        }

        // Check if crawling is needed
        if (($this->config['follow_links'] ?? false) || ($this->config['max_depth'] ?? 1) > 1) {
            return $this->scrapeWithRoach($url);
        }

        // Default to PHP Scraper for simple tasks
        return $this->scrapeWithPhpScraper($url);
    }

    /**
     * Scrape using saved HTML selectors.
     */
    private function scrapeWithSelectors(string $url): array
    {
        try {
            $fetchOptions = [];
            if (!empty($this->config['extract_dynamic']) || !empty($this->source['use_browser'])) {
                $fetchOptions['render_js'] = true;
                $fetchOptions['timeout_ms'] = isset($this->config['timeout']) ? max(5000, (int)$this->config['timeout'] * 1000) : 30000;
                if (!empty($this->config['user_agent'])) {
                    $fetchOptions['user_agent'] = (string)$this->config['user_agent'];
                }
                if (!empty($this->config['wait_for_element'])) {
                    $fetchOptions['wait_for_element'] = (string)$this->config['wait_for_element'];
                }
            }

            $html = HtmlFetcher::fetch($url, $fetchOptions);
            if (trim($html) === '') {
                throw new Exception('Empty HTML response');
            }

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            @$dom->loadHTML($html);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $selectors = is_array($this->config['selectors'] ?? null) ? $this->config['selectors'] : [];
            $containerNodes = $this->resolveItemNodes($xpath, $selectors);

            $items = [];
            foreach (array_slice($containerNodes, 0, max(1, $this->maxItems * 3)) as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                $item = $this->extractHtmlSelectorItem($xpath, $node, $selectors, $url);
                if ($item === []) {
                    continue;
                }

                $items[] = $item;
                if (count($items) >= $this->maxItems) {
                    break;
                }
            }

            $items = $this->dedupeScrapedItems($items);

            if ($items === []) {
                return [
                    'success' => false,
                    'data' => [],
                    'library' => 'HTML Selectors',
                    'error' => 'No items matched the configured selectors on the live page.',
                    'raw_result' => [
                        'html_length' => strlen($html),
                        'container_count' => count($containerNodes),
                        'selector_keys' => array_keys($selectors),
                    ],
                ];
            }

            return [
                'success' => true,
                'data' => array_slice($items, 0, $this->maxItems),
                'library' => 'HTML Selectors',
                'raw_result' => [
                    'html_length' => strlen($html),
                    'container_count' => count($containerNodes),
                    'item_count' => count($items),
                    'selector_keys' => array_keys($selectors),
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'data' => [],
                'library' => 'HTML Selectors',
                'error' => $e->getMessage(),
                'raw_result' => [
                    'selector_keys' => array_keys(is_array($this->config['selectors'] ?? null) ? $this->config['selectors'] : []),
                ],
            ];
        }
    }

    /**
     * Determine whether the source has any selector configuration to drive HTML extraction.
     */
    private function hasSelectorConfiguration(): bool
    {
        $selectors = $this->config['selectors'] ?? [];
        if (!is_array($selectors)) {
            return false;
        }

        foreach ($selectors as $selector) {
            if (trim((string)$selector) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve item container nodes from configured selectors.
     *
     * @return array<int, DOMElement>
     */
    private function resolveItemNodes(DOMXPath $xpath, array $selectors): array
    {
        $candidates = [
            $selectors['list_container'] ?? null,
            $selectors['list_item'] ?? null,
            $selectors['article'] ?? null,
            $selectors['item'] ?? null,
            $selectors['card'] ?? null,
            $selectors['post'] ?? null,
            $selectors['entry'] ?? null,
            $selectors['news_item'] ?? null,
            $selectors['result'] ?? null,
            $selectors['container'] ?? null,
            $selectors['row'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate === '') {
                continue;
            }

            $nodes = $this->querySelector($xpath, $candidate);
            if ($nodes && $nodes->length > 0) {
                return $this->collectElementNodes($nodes);
            }
        }

        foreach (['title', 'content', 'excerpt', 'image'] as $field) {
            $selector = trim((string)($selectors[$field] ?? ''));
            if ($selector === '') {
                continue;
            }

            $nodes = $this->querySelector($xpath, $selector);
            if ($nodes && $nodes->length > 0) {
                return $this->collectAncestorsForNodes($nodes);
            }
        }

        $fallbackSelectors = ['article', '.article', '.post', '.news-item', '.card', 'li', '.item', '.listing-item'];
        foreach ($fallbackSelectors as $selector) {
            $nodes = $this->querySelector($xpath, $selector);
            if ($nodes && $nodes->length > 0) {
                return $this->collectElementNodes($nodes);
            }
        }

        return [];
    }

    /**
     * Extract a single item from a container node.
     */
    private function extractHtmlSelectorItem(DOMXPath $xpath, DOMElement $container, array $selectors, string $sourceUrl): array
    {
        $title = $this->extractTextBySelectors($xpath, $container, [
            $selectors['list_title'] ?? '',
            $selectors['title'] ?? '',
            $selectors['headline'] ?? '',
            $selectors['name'] ?? '',
            'h1',
            'h2',
            'h3',
            'h4',
            '.title',
            '.headline',
            '.entry-title',
            '.post-title',
        ]);

        $url = $this->extractHrefBySelectors($xpath, $container, [
            $selectors['list_link'] ?? '',
            $selectors['title'] ?? '',
            $selectors['link'] ?? '',
            $selectors['url'] ?? '',
            $selectors['article'] ?? '',
            'a[href]',
        ], $sourceUrl);

        $content = $this->extractTextBySelectors($xpath, $container, [
            $selectors['content'] ?? '',
            $selectors['excerpt'] ?? '',
            '.content',
            '.summary',
            '.description',
            'p',
        ]);

        $excerpt = $this->extractTextBySelectors($xpath, $container, [
            $selectors['list_excerpt'] ?? '',
            $selectors['excerpt'] ?? '',
            '.excerpt',
            '.summary',
            '.description',
            'p',
        ]);

        $imageUrl = $this->extractAttributeBySelectors($xpath, $container, [
            $selectors['list_image'] ?? '',
            $selectors['image'] ?? '',
            'img',
            'picture img',
        ], 'src');

        $author = $this->extractTextBySelectors($xpath, $container, [
            $selectors['list_author'] ?? '',
            $selectors['author'] ?? '',
            '.author',
            '.byline',
        ]);

        $date = $this->extractTextBySelectors($xpath, $container, [
            $selectors['list_date'] ?? '',
            $selectors['date'] ?? '',
            'time',
            '.date',
            '.published',
        ]);

        $category = $this->extractTextBySelectors($xpath, $container, [
            $selectors['list_category'] ?? '',
            $selectors['category'] ?? '',
            '.category',
            '.breadcrumb a',
        ]);

        $tags = $this->extractTextBySelectors($xpath, $container, [
            $selectors['list_tags'] ?? '',
            $selectors['tags'] ?? '',
            '.tags a',
            '[rel="tag"]',
        ]);

        $title = trim($title);
        $url = trim($url);
        $content = trim($content);
        $excerpt = trim($excerpt);
        $imageUrl = trim($imageUrl);
        $author = trim($author);
        $date = trim($date);
        $category = trim($category);
        $tags = trim($tags);

        if ($title === '' && $content === '' && $excerpt === '' && $url === '') {
            return [];
        }

        if ($title === '') {
            $containerText = trim(preg_replace('/\s+/', ' ', $container->textContent ?? '') ?? '');
            if ($containerText !== '') {
                $title = $containerText;
            }
        }

        if ($title === '') {
            $title = $excerpt !== '' ? mb_substr($excerpt, 0, 140) : ($content !== '' ? mb_substr($content, 0, 140) : $url);
        }

        return [
            'title' => $title,
            'url' => $url !== '' ? $url : $sourceUrl,
            'content' => $content !== '' ? $content : $excerpt,
            'excerpt' => $excerpt !== '' ? $excerpt : $content,
            'image_url' => $imageUrl,
            'author' => $author,
            'published_at' => $date,
            'category' => $category,
            'tags' => $tags !== '' ? preg_split('/\s*,\s*/', $tags) : [],
        ];
    }

    /**
     * Extract readable text from the first matching selector in the container.
     */
    private function extractTextBySelectors(DOMXPath $xpath, DOMElement $container, array $selectors): string
    {
        foreach ($selectors as $selector) {
            $selector = trim((string)$selector);
            if ($selector === '') {
                continue;
            }

            $nodes = $this->querySelector($xpath, $selector, $container);
            if (!$nodes || $nodes->length <= 0) {
                continue;
            }

            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? '') ?? '');
                    if ($text !== '') {
                        return $text;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Extract an attribute from the first matching selector in the container.
     */
    private function extractAttributeBySelectors(DOMXPath $xpath, DOMElement $container, array $selectors, string $attribute): string
    {
        foreach ($selectors as $selector) {
            $selector = trim((string)$selector);
            if ($selector === '') {
                continue;
            }

            $nodes = $this->querySelector($xpath, $selector, $container);
            if (!$nodes || $nodes->length <= 0) {
                continue;
            }

            foreach ($nodes as $node) {
                if ($node instanceof DOMElement && $node->hasAttribute($attribute)) {
                    $value = trim((string)$node->getAttribute($attribute));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Extract href from the first matching selector in the container.
     */
    private function extractHrefBySelectors(DOMXPath $xpath, DOMElement $container, array $selectors, string $sourceUrl): string
    {
        foreach ($selectors as $selector) {
            $selector = trim((string)$selector);
            if ($selector === '') {
                continue;
            }

            $nodes = $this->querySelector($xpath, $selector, $container);
            if (!$nodes || $nodes->length <= 0) {
                continue;
            }

            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                if ($node->hasAttribute('href')) {
                    $href = trim((string)$node->getAttribute('href'));
                    if ($href !== '') {
                        return $this->resolveUrl($href, $sourceUrl);
                    }
                }

                $anchors = $this->querySelector($xpath, 'a[href]', $node);
                if ($anchors && $anchors->length > 0) {
                    foreach ($anchors as $anchor) {
                        if ($anchor instanceof DOMElement && $anchor->hasAttribute('href')) {
                            $href = trim((string)$anchor->getAttribute('href'));
                            if ($href !== '') {
                                return $this->resolveUrl($href, $sourceUrl);
                            }
                        }
                    }
                }
            }
        }

        return '';
    }

    /**
     * Query a selector optionally relative to a context node.
     */
    private function querySelector(DOMXPath $xpath, string $selector, ?DOMElement $context = null)
    {
        $query = $this->convertCssToXPath($selector);
        if ($context) {
            $result = $xpath->query($query, $context);
            return $result ?: false;
        }

        return $xpath->query($query) ?: false;
    }

    /**
     * Convert a CSS selector to XPath.
     */
    private function convertCssToXPath(string $selector): string
    {
        if ($this->converter === null) {
            $this->converter = new CssSelectorConverter();
        }

        try {
            return $this->converter->toXPath($selector);
        } catch (\Throwable $e) {
            return '//' . ltrim($selector, '/');
        }
    }

    /**
     * Resolve a relative URL against the source URL.
     */
    private function resolveUrl(string $href, string $sourceUrl): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }

        $base = parse_url($sourceUrl);
        if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return $href;
        }

        $root = $base['scheme'] . '://' . $base['host'];
        if (!empty($base['port'])) {
            $root .= ':' . $base['port'];
        }

        if (str_starts_with($href, '//')) {
            return $base['scheme'] . ':' . $href;
        }

        if (str_starts_with($href, '/')) {
            return $root . $href;
        }

        $path = $base['path'] ?? '';
        $path = preg_replace('#/[^/]*$#', '/', $path) ?: '/';
        return $root . rtrim($path, '/') . '/' . ltrim($href, '/');
    }

    /**
     * Collect DOMElement nodes from a DOMNodeList.
     *
     * @param \DOMNodeList<DOMElement> $nodes
     * @return array<int, DOMElement>
     */
    private function collectElementNodes($nodes): array
    {
        $items = [];
        if (!$nodes || !is_object($nodes) || !property_exists($nodes, 'length')) {
            return $items;
        }

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $items[] = $node;
            }
        }

        return $items;
    }

    /**
     * Collect ancestor nodes for a set of matching nodes.
     *
     * @param \DOMNodeList<DOMElement> $nodes
     * @return array<int, DOMElement>
     */
    private function collectAncestorsForNodes($nodes): array
    {
        $items = [];
        if (!$nodes || !is_object($nodes) || !property_exists($nodes, 'length')) {
            return $items;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $container = $this->findBestContainer($node);
            if ($container) {
                $items[] = $container;
            }
        }

        return $items;
    }

    /**
     * Find a sensible container element for a matched node.
     */
    private function findBestContainer(DOMElement $node): ?DOMElement
    {
        $current = $node;
        for ($i = 0; $i < 3; $i++) {
            $parent = $current->parentNode;
            if (!$parent instanceof DOMElement) {
                break;
            }

            $tag = strtolower($parent->tagName ?? '');
            if (in_array($tag, ['article', 'li', 'div', 'section', 'main'], true)) {
                return $parent;
            }

            $current = $parent;
        }

        return $node;
    }

    /**
     * Remove duplicate scraped items based on url/title.
     */
    private function dedupeScrapedItems(array $items): array
    {
        $seen = [];
        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = trim((string)($item['url'] ?? '')) . '|' . trim((string)($item['title'] ?? ''));
            $key = strtolower($key);
            if ($key === '|') {
                continue;
            }

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
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
     * Scrape structured data from a JSON API.
     */
    private function scrapeWithApi(string $url): array
    {
        try {
            $apiUrl = trim((string)($this->config['api_url'] ?? ''));
            if ($apiUrl === '') {
                $apiUrl = $this->inferApiUrlFromSourceUrl($url);
            }

            $response = $this->fetchStructuredPayload($apiUrl);
            $items = [];
            $format = 'unknown';
            $decoded = null;

            if ($this->looksLikeJsonResponse($response['content_type'], $response['body'])) {
                $decoded = json_decode((string)$response['body'], true);
                if (!is_array($decoded)) {
                    throw new Exception('Invalid JSON API response');
                }

                $items = array_slice($this->extractApiItems($decoded, $url, $apiUrl), 0, $this->maxItems);
                $format = 'json';
            } elseif ($this->looksLikeXmlResponse($response['content_type'], $response['body'])) {
                $items = array_slice($this->extractXmlFeedItems((string)$response['body'], $url, $apiUrl), 0, $this->maxItems);
                $format = 'xml';
            } else {
                throw new Exception('Unsupported API response format');
            }

            return [
                'success' => $items !== [],
                'data' => $items,
                'library' => 'JSON API',
                'raw_result' => [
                    'api_url' => $apiUrl,
                    'http_code' => $response['http_code'],
                    'content_type' => $response['content_type'],
                    'format' => $format,
                    'decoded_keys' => is_array($decoded) ? array_keys($decoded) : [],
                    'item_count' => count($items),
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'data' => [],
                'library' => 'JSON API',
                'error' => $e->getMessage(),
                'raw_result' => [
                    'api_url' => $this->config['api_url'] ?? null,
                ],
            ];
        }
    }

    /**
     * Fetch structured payload from an API endpoint.
     */
    private function fetchStructuredPayload(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => (int)($this->config['connect_timeout'] ?? 10),
            CURLOPT_TIMEOUT => (int)($this->config['timeout'] ?? 30),
            CURLOPT_SSL_VERIFYPEER => !empty($this->config['ssl_verify']),
            CURLOPT_SSL_VERIFYHOST => !empty($this->config['ssl_verify']) ? 2 : 0,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . ($this->config['user_agent'] ?? 'BroxLab AdvanceScraper/1.0'),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new Exception($curlError ?: 'HTTP ' . $httpCode);
        }

        return [
            'body' => (string)$response,
            'http_code' => $httpCode,
            'content_type' => $contentType,
        ];
    }

    /**
     * Check whether the response is JSON.
     */
    private function looksLikeJsonResponse(string $contentType, string $body): bool
    {
        return str_contains(strtolower($contentType), 'json')
            || preg_match('/^\s*[\{\[]/', $body) === 1;
    }

    /**
     * Check whether the response is XML/RSS/Atom.
     */
    private function looksLikeXmlResponse(string $contentType, string $body): bool
    {
        $lowerType = strtolower($contentType);
        return str_contains($lowerType, 'xml')
            || str_contains($lowerType, 'rss')
            || str_contains($lowerType, 'atom')
            || preg_match('/^\s*<\?xml|^\s*<rss|^\s*<feed/i', $body) === 1;
    }

    /**
     * Extract items from RSS/Atom XML feed responses.
     */
    private function extractXmlFeedItems(string $body, string $sourceUrl, string $apiUrl): array
    {
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            throw new Exception('Unable to decode XML feed payload');
        }

        $items = [];
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $entry) {
                $item = $this->normalizeXmlFeedItem($entry, $sourceUrl, $apiUrl, 'rss');
                if ($item !== []) {
                    $items[] = $item;
                }
            }
        } elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $item = $this->normalizeXmlFeedItem($entry, $sourceUrl, $apiUrl, 'atom');
                if ($item !== []) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Normalize RSS/Atom feed entries.
     */
    private function normalizeXmlFeedItem(\SimpleXMLElement $entry, string $sourceUrl, string $apiUrl, string $format): array
    {
        $title = trim((string)($entry->title ?? ''));
        $excerpt = trim((string)($entry->description ?? $entry->summary ?? ''));
        $content = trim((string)($entry->children('content', true)->encoded ?? $entry->content ?? $excerpt));
        $link = trim((string)($entry->link ?? ''));
        $guid = trim((string)($entry->guid ?? ''));
        $pubDate = trim((string)($entry->pubDate ?? $entry->published ?? $entry->updated ?? ''));

        if ($format === 'atom' && $link === '') {
            foreach ($entry->link as $maybeLink) {
                $attrs = $maybeLink->attributes();
                if ($attrs && isset($attrs['href'])) {
                    $link = trim((string)$attrs['href']);
                    break;
                }
            }
        }

        if ($link === '' && $guid !== '') {
            $link = $guid;
        }

        if ($link === '') {
            $link = $sourceUrl;
        }

        if ($title === '' && $excerpt === '' && $content === '') {
            return [];
        }

        return [
            'title' => $title !== '' ? $title : ($excerpt !== '' ? mb_substr($excerpt, 0, 140) : $link),
            'url' => $link,
            'content' => $content !== '' ? $content : $excerpt,
            'excerpt' => $excerpt !== '' ? $excerpt : $content,
            'published_at' => $pubDate,
            'source_url' => $sourceUrl,
            'api_url' => $apiUrl,
        ];
    }

    /**
     * Infer the Teletalk API URL from the public page URL when needed.
     */
    private function inferApiUrlFromSourceUrl(string $url): string
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (str_contains($host, 'alljobs.teletalk.com.bd')) {
            return 'https://alljobs.teletalk.com.bd/api/v1/govt-jobs/org-list';
        }

        if (str_contains($host, 'jugantor.com')) {
            return 'https://www.jugantor.com/ajax/load/latestnews/10/0/0';
        }

        return $url;
    }

    /**
     * Normalize API data into a list of item arrays.
     */
    private function extractApiItems(array $decoded, string $sourceUrl, string $apiUrl): array
    {
        if (!empty($decoded['govtOrgJobs']) && is_array($decoded['govtOrgJobs'])) {
            $items = [];

            foreach ($decoded['govtOrgJobs'] as $organization) {
                if (!is_array($organization)) {
                    continue;
                }

                $orgId = (int)($organization['id'] ?? 0);
                $orgName = trim((string)($organization['name'] ?? ''));
                $jobs = $organization['govt_jobs'] ?? [];

                if (!is_array($jobs)) {
                    continue;
                }

                foreach ($jobs as $job) {
                    if (!is_array($job)) {
                        continue;
                    }

                    $jobId = (int)($job['id'] ?? 0);
                    $jobTitle = trim((string)($job['job_title'] ?? ''));

                    if ($jobId <= 0 || $jobTitle === '') {
                        continue;
                    }

                    $jobTitleBn = trim((string)($job['job_title_bn'] ?? ''));
                    $items[] = [
                        'id' => $jobId,
                        'url' => $this->buildTeletalkJobUrl($orgId, $jobId),
                        'title' => $jobTitle,
                        'title_bn' => $jobTitleBn,
                        'content' => trim(($orgName !== '' ? $orgName . ' - ' : '') . $jobTitle),
                        'excerpt' => $jobTitleBn !== '' ? $jobTitleBn : $jobTitle,
                        'organization_id' => $orgId,
                        'organization_name' => $orgName,
                        'source_url' => $sourceUrl,
                        'api_url' => $apiUrl,
                    ];
                }
            }

            return $items;
        }

        if (!empty($decoded['data']['children']) && is_array($decoded['data']['children'])) {
            $items = [];

            foreach ($decoded['data']['children'] as $child) {
                if (!is_array($child) || empty($child['data']) || !is_array($child['data'])) {
                    continue;
                }

                $normalized = $this->normalizeRedditApiItem($child['data'], $sourceUrl, $apiUrl);
                if ($normalized !== []) {
                    $items[] = $normalized;
                }
            }

            return $items;
        }

        if ($this->isListOfItems($decoded)) {
            return array_values(array_filter(array_map(function ($item) use ($sourceUrl, $apiUrl) {
                if (!is_array($item)) {
                    return null;
                }

                return $this->normalizeApiItem($item, $sourceUrl, $apiUrl);
            }, $decoded), static fn ($item) => is_array($item)));
        }

        $items = [];
        foreach (['items', 'data', 'results'] as $key) {
            if (!empty($decoded[$key]) && is_array($decoded[$key])) {
                $items = array_values(array_filter(array_map(function ($item) use ($sourceUrl, $apiUrl) {
                    if (!is_array($item)) {
                        return null;
                    }

                    return $this->normalizeApiItem($item, $sourceUrl, $apiUrl);
                }, $decoded[$key]), static fn ($item) => is_array($item)));
                if ($items !== []) {
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * Determine whether a decoded payload is a plain list of API items.
     */
    private function isListOfItems(array $decoded): bool
    {
        if ($decoded === []) {
            return false;
        }

        $keys = array_keys($decoded);
        return $keys === range(0, count($keys) - 1);
    }

    /**
     * Normalize a generic API item into the scraper's common shape.
     */
    private function normalizeApiItem(array $item, string $sourceUrl, string $apiUrl): array
    {
        $title = trim((string)($item['title'] ?? $item['headline'] ?? $item['fullheadline'] ?? $item['name'] ?? $item['job_title'] ?? ''));
        $excerpt = trim((string)($item['excerpt'] ?? $item['summary'] ?? $item['description'] ?? $item['short_description'] ?? $item['job_title_bn'] ?? ''));
        $url = trim((string)($item['url'] ?? $item['link'] ?? $item['permalink'] ?? $item['news_url'] ?? $item['detail_url'] ?? ''));
        $imageUrl = trim((string)($item['image_url'] ?? $item['thumbSmall'] ?? $item['thumbMedium'] ?? $item['thumb'] ?? $item['logo'] ?? $item['image'] ?? ''));

        if ($title === '' && $excerpt === '' && $url === '') {
            return [];
        }

        return $item + [
            'title' => $title !== '' ? $title : $excerpt,
            'excerpt' => $excerpt,
            'content' => trim((string)($item['content'] ?? $item['description'] ?? $item['summary'] ?? '')),
            'url' => $url !== '' ? $url : $sourceUrl,
            'image_url' => $imageUrl,
            'source_url' => $sourceUrl,
            'api_url' => $apiUrl,
        ];
    }

    /**
     * Normalize Reddit listing API items.
     */
    private function normalizeRedditApiItem(array $item, string $sourceUrl, string $apiUrl): array
    {
        $title = trim((string)($item['title'] ?? ''));
        $excerpt = trim((string)($item['selftext'] ?? ''));
        $permalink = trim((string)($item['permalink'] ?? ''));
        $url = trim((string)($item['url'] ?? ''));
        $thumbnail = trim((string)($item['thumbnail'] ?? ''));
        $author = trim((string)($item['author'] ?? ''));

        if ($title === '' && $excerpt === '' && $permalink === '' && $url === '') {
            return [];
        }

        if ($permalink !== '' && !str_starts_with($permalink, 'http')) {
            $permalink = 'https://www.reddit.com' . $permalink;
        }

        if ($url === '') {
            $url = $permalink !== '' ? $permalink : $sourceUrl;
        }

        return $item + [
            'title' => $title !== '' ? $title : ($excerpt !== '' ? mb_substr($excerpt, 0, 120) : $url),
            'excerpt' => $excerpt,
            'content' => $excerpt,
            'url' => $url,
            'permalink' => $permalink,
            'image_url' => in_array($thumbnail, ['self', 'default', 'nsfw', 'image', 'spoiler'], true) ? '' : $thumbnail,
            'author' => $author,
            'source_url' => $sourceUrl,
            'api_url' => $apiUrl,
        ];
    }

    /**
     * Build a Teletalk detail URL for a job item.
     */
    private function buildTeletalkJobUrl(int $organizationId, int $jobId): string
    {
        if ($organizationId > 0 && $jobId > 0) {
            return sprintf('https://alljobs.teletalk.com.bd/jobs/government/%d?jobId=%d', $organizationId, $jobId);
        }

        return 'https://alljobs.teletalk.com.bd/jobs/government';
    }

    /**
     * Get PHP Scraper service instance
     */
    private function getPhpScraperService(): PhpScraperService
    {
        if (!$this->phpScraper) {
            $this->phpScraper = new PhpScraperService([
                'user_agent' => $this->config['user_agent'] ?? 'BroxLab Scraper/1.0',
                'timeout' => $this->config['timeout'] ?? 30,
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
