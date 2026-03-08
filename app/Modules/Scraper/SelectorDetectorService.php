<?php

namespace App\Modules\Scraper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Selector Detector Service
 * Uses Guzzle for HTTP requests and DOM cleanup before AI-based selector detection
 * Supports ProxyManager and UserAgentRotator for better scraping
 */
class SelectorDetectorService
{
    private Client $httpClient;
    private ProxyManager $proxyManager;
    private UserAgentRotator $userAgentRotator;
    private array $defaultHeaders;
    private bool $useProxy;
    private bool $rotateUserAgent;
    private array $logger;
    private string $scraperMethod;
    
    // Weight-based selector patterns
    private array $selectorPatterns = [
        'list_container' => [
            ['selector' => '.news-list', 'weight' => 10],
            ['selector' => '.articles', 'weight' => 9],
            ['selector' => '.posts', 'weight' => 8],
            ['selector' => '.stories', 'weight' => 8],
            ['selector' => '.content-list', 'weight' => 7],
            ['selector' => '.article-list', 'weight' => 9],
            ['selector' => '.grid', 'weight' => 6],
            ['selector' => 'article', 'weight' => 5],
            ['selector' => 'section', 'weight' => 4],
            ['selector' => 'main', 'weight' => 3],
        ],
        'list_item' => [
            ['selector' => '.news-item', 'weight' => 10],
            ['selector' => '.article-item', 'weight' => 9],
            ['selector' => '.post-item', 'weight' => 8],
            ['selector' => '.story-item', 'weight' => 8],
            ['selector' => '.card', 'weight' => 7],
            ['selector' => 'article', 'weight' => 6],
            ['selector' => '.item', 'weight' => 5],
            ['selector' => '.col-md-4', 'weight' => 4],
            ['selector' => '.col-md-3', 'weight' => 4],
        ],
        'list_title' => [
            ['selector' => 'h3 a', 'weight' => 10],
            ['selector' => 'h2 a', 'weight' => 9],
            ['selector' => '.title a', 'weight' => 8],
            ['selector' => '.headline a', 'weight' => 8],
            ['selector' => '.news-title a', 'weight' => 9],
            ['selector' => '.article-title a', 'weight' => 8],
            ['selector' => '.story-title a', 'weight' => 7],
            ['selector' => '.post-title a', 'weight' => 7],
        ],
        'title' => [
            ['selector' => 'h1', 'weight' => 10],
            ['selector' => '.article-title', 'weight' => 9],
            ['selector' => '.headline', 'weight' => 9],
            ['selector' => '.post-title', 'weight' => 8],
            ['selector' => '.story-title', 'weight' => 8],
            ['selector' => 'article h1', 'weight' => 7],
        ],
        'content' => [
            ['selector' => '.article-content', 'weight' => 10],
            ['selector' => '.article-body', 'weight' => 9],
            ['selector' => '.post-content', 'weight' => 8],
            ['selector' => '.content', 'weight' => 7],
            ['selector' => '.story-content', 'weight' => 8],
            ['selector' => '.entry-content', 'weight' => 8],
            ['selector' => 'article', 'weight' => 6],
            ['selector' => '.main-content', 'weight' => 7],
        ],
        'image' => [
            ['selector' => "meta[property='og:image']", 'weight' => 10, 'type' => 'xpath'],
            ['selector' => '.article-image img', 'weight' => 9],
            ['selector' => '.featured-image img', 'weight' => 9],
            ['selector' => '.post-image img', 'weight' => 8],
            ['selector' => 'article img', 'weight' => 7],
            ['selector' => '.thumbnail img', 'weight' => 7],
        ],
        'date' => [
            ['selector' => "time[@datetime]", 'weight' => 10, 'type' => 'xpath'],
            ['selector' => 'time', 'weight' => 9],
            ['selector' => '.date', 'weight' => 7],
            ['selector' => '.publish-date', 'weight' => 8],
            ['selector' => '.published', 'weight' => 7],
            ['selector' => '.timestamp', 'weight' => 6],
            ['selector' => '.meta-date', 'weight' => 7],
        ],
        'author' => [
            ['selector' => '.author', 'weight' => 10],
            ['selector' => '.byline', 'weight' => 9],
            ['selector' => '.writer', 'weight' => 8],
            ['selector' => "*[@rel='author']", 'weight' => 9, 'type' => 'xpath'],
            ['selector' => '.author-name', 'weight' => 8],
            ['selector' => '.post-author', 'weight' => 7],
        ],
        // XPath specific patterns for multi-layer detection
        'xpath_list' => [
            ['selector' => "//div[contains(@class, 'news-list')]", 'weight' => 10, 'type' => 'xpath'],
            ['selector' => "//div[contains(@class, 'article-list')]", 'weight' => 9, 'type' => 'xpath'],
            ['selector' => "//section[contains(@class, 'news')]", 'weight' => 8, 'type' => 'xpath'],
            ['selector' => "//ul[contains(@class, 'posts')]", 'weight' => 8, 'type' => 'xpath'],
            ['selector' => "//div[contains(@class, 'content')]", 'weight' => 7, 'type' => 'xpath'],
        ],
        'xpath_item' => [
            ['selector' => "//div[contains(@class, 'news-item')]", 'weight' => 10, 'type' => 'xpath'],
            ['selector' => "//article", 'weight' => 9, 'type' => 'xpath'],
            ['selector' => "//div[contains(@class, 'article-item')]", 'weight' => 9, 'type' => 'xpath'],
            ['selector' => "//div[contains(@class, 'post-item')]", 'weight' => 8, 'type' => 'xpath'],
            ['selector' => "//div[contains(@class, 'card')]", 'weight' => 7, 'type' => 'xpath'],
            ['selector' => "//li[contains(@class, 'item')]", 'weight' => 6, 'type' => 'xpath'],
        ],
        'xpath_link' => [
            ['selector' => "//a[contains(@class, 'title')]", 'weight' => 10, 'type' => 'xpath'],
            ['selector' => "//a[contains(@class, 'headline')]", 'weight' => 9, 'type' => 'xpath'],
            ['selector' => "//h3//a", 'weight' => 8, 'type' => 'xpath'],
            ['selector' => "//h2//a", 'weight' => 8, 'type' => 'xpath'],
            ['selector' => "//a[contains(@href, '/article')]", 'weight' => 7, 'type' => 'xpath'],
            ['selector' => "//a[contains(@href, '/news')]", 'weight' => 7, 'type' => 'xpath'],
        ],
    ];
    
    public function __construct(array $config = [])
    {
        $this->useProxy = $config['use_proxy'] ?? false;
        $this->rotateUserAgent = $config['rotate_user_agent'] ?? true;
        $this->scraperMethod = $config['scraper_method'] ?? 'guzzle-symfony';
        
        // Initialize ProxyManager
        $this->proxyManager = new ProxyManager([
            'max_retries' => 3,
            'failure_threshold' => 3
        ]);
        
        // Initialize UserAgentRotator
        $this->userAgentRotator = new UserAgentRotator();
        
        // Initialize logger
        $this->logger = [
            'enabled' => $config['enable_logging'] ?? true,
            'path' => $config['log_path'] ?? __DIR__ . '/../../../logs/selector_detector.log'
        ];
        
        $this->defaultHeaders = [
            'list_container' => '',
            'list_item' => '',
            'list_title' => '',
            'list_link' => '',
            'list_date' => '',
            'list_image' => '',
            'title' => '',
            'content' => '',
            'image' => '',
            'excerpt' => '',
            'date' => '',
            'author' => ''
        ];
        
        $this->httpClient = $this->createHttpClient();
        
        $this->log('info', 'SelectorDetectorService initialized', [
            'use_proxy' => $this->useProxy,
            'rotate_user_agent' => $this->rotateUserAgent,
            'scraper_method' => $this->scraperMethod
        ]);
    }
    
    /**
     * Log messages to file
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->logger['enabled']) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message} {$contextStr}\n";
        
        $logDir = dirname($this->logger['path']);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        @file_put_contents($this->logger['path'], $logMessage, FILE_APPEND);
    }
    
    /**
     * Create HTTP client with optional proxy and user agent
     */
    private function createHttpClient(array $options = []): Client
    {
        $config = [
            'timeout' => $options['timeout'] ?? 30,
            'connect_timeout' => $options['connect_timeout'] ?? 10,
            'allow_redirects' => [
                'max' => 5,
                'strict' => false,
                'track_redirects' => true
            ],
            'verify' => true
        ];
        
        // Set headers
        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache'
        ];
        
        // Rotate user agent if enabled
        if ($this->rotateUserAgent) {
            $headers['User-Agent'] = $this->userAgentRotator->getRandom();
        } else {
            $headers['User-Agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        }
        
        $config['headers'] = $headers;
        
        // Use proxy if enabled
        if ($this->useProxy) {
            $proxy = $this->proxyManager->getProxyForProvider(ProxyManager::PROVIDER_CUSTOM);
            if ($proxy) {
                $proxyUrl = $this->buildProxyUrl($proxy);
                if ($proxyUrl) {
                    $config['proxy'] = $proxyUrl;
                    $this->log('info', 'Using proxy', ['proxy' => $proxyUrl]);
                }
            }
        }
        
        return new Client($config);
    }
    
    /**
     * Build proxy URL from proxy configuration
     */
    private function buildProxyUrl(array $proxy): ?string
    {
        if (empty($proxy['host']) || empty($proxy['port'])) {
            return null;
        }
        
        $url = $proxy['type'] . '://' . $proxy['host'] . ':' . $proxy['port'];
        
        if (!empty($proxy['username']) && !empty($proxy['password'])) {
            $url = $proxy['username'] . ':' . $proxy['password'] . '@' . $url;
        }
        
        return $url;
    }
    
    /**
     * Fetch URL and return cleaned HTML only.
     */
    public function prepareCleanHtml(string $url, array $options = []): string
    {
        $prepared = $this->prepareCleanHtmlPayload($url, $options);
        return (string) ($prepared['html'] ?? '');
    }

    /**
     * Fetch URL and return cleaned HTML with metadata for callers that need it.
     */
    public function prepareCleanHtmlPayload(string $url, array $options = []): array
    {
        $this->applyRuntimeOptions($options);

        $pageLayer = $options['page_layer'] ?? 'category';

        $cacheKey = md5('clean-html:' . $url . json_encode($options));
        $cached = $this->getCached($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $this->log('info', 'Starting clean HTML preparation', [
            'url' => $url,
            'page_layer' => $pageLayer
        ]);

        try {
            $html = $this->fetchUrl($url);

            if (empty($html)) {
                throw new \Exception('Failed to fetch URL content');
            }

            $cleanHtml = $this->cleanHtmlForAi($html);
            $prepared = [
                'html' => $cleanHtml,
                'chunks' => $this->chunkHtml($cleanHtml, (int) ($options['chunk_size'] ?? 50000)),
                'page_layer' => $pageLayer,
                'source_url' => $url,
                'original_length' => strlen($html),
                'clean_length' => strlen($cleanHtml),
            ];

            $this->log('info', 'Clean HTML preparation completed', [
                'url' => $url,
                'original_length' => $prepared['original_length'],
                'clean_length' => $prepared['clean_length']
            ]);

            $this->setCache($cacheKey, $prepared);

            return $prepared;
        } catch (RequestException $e) {
            $this->log('error', 'HTTP error during clean HTML preparation', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            if (!$this->useProxy && strpos($e->getMessage(), 'timed out') !== false) {
                $this->useProxy = true;
                $retryOptions = $options;
                $retryOptions['use_proxy'] = true;
                $this->log('info', 'Retrying clean HTML preparation with proxy', ['url' => $url]);
                return $this->prepareCleanHtmlPayload($url, $retryOptions);
            }

            throw new \Exception('Failed to fetch URL: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->log('error', 'Error during clean HTML preparation', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Deprecated compatibility wrapper.
     * Returns cleaned HTML code instead of selector guesses.
     */
    public function detectSelectors(string $url, array $options = []): string
    {
        return $this->prepareCleanHtml($url, $options);
    }

    /**
     * Apply runtime options for the current request.
     */
    private function applyRuntimeOptions(array $options): void
    {
        if (isset($options['use_proxy'])) {
            $this->useProxy = (bool) $options['use_proxy'];
        }
        if (isset($options['rotate_user_agent'])) {
            $this->rotateUserAgent = (bool) $options['rotate_user_agent'];
        }
        if (isset($options['cache_expiry'])) {
            $this->cacheExpiry = (int) $options['cache_expiry'];
        }
        if (isset($options['scraper_method'])) {
            $this->scraperMethod = $options['scraper_method'];
        }
    }
    
    /**
     * Fetch URL using the configured scraper method
     */
    private function fetchUrl(string $url): string
    {
        $this->log('debug', 'Fetching URL', ['url' => $url, 'method' => $this->scraperMethod]);
        
        // Validate scraper method
        $validMethods = ['guzzle-symfony', 'native-php'];
        if (!in_array($this->scraperMethod, $validMethods)) {
            $this->scraperMethod = 'guzzle-symfony'; // Default fallback
        }
        
        // Use native PHP method if specified
        if ($this->scraperMethod === 'native-php') {
            return $this->fetchUrlNative($url);
        }
        
        // Default: use Guzzle
        return $this->fetchUrlGuzzle($url);
    }
    
    /**
     * Fetch URL using native PHP (file_get_contents)
     */
    private function fetchUrlNative(string $url): string
    {
        $userAgent = $this->userAgentRotator->getRandom();
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'ignore_errors' => true,
                'header' => "User-Agent: " . $userAgent . "\r\n"
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        
        if ($html === false) {
            throw new \Exception('Failed to fetch URL using native PHP');
        }
        
        $this->log('debug', 'URL fetched using native PHP', [
            'url' => $url,
            'length' => strlen($html)
        ]);
        
        return $html;
    }
    
    /**
     * Fetch URL using Guzzle with proxy support
     */
    private function fetchUrlGuzzle(string $url): string
    {
        $this->log('debug', 'Fetching URL with Guzzle', ['url' => $url]);
        
        // Try without Accept-Encoding first (some servers send invalid encoding)
        try {
            $client = $this->createHttpClientNoEncoding();
            $response = $client->get($url);
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}");
            }
            
            $html = (string) $response->getBody();
            $this->log('debug', 'URL fetched successfully', [
                'url' => $url,
                'length' => strlen($html)
            ]);
            
            return $html;
        } catch (\Exception $e) {
            // If first attempt fails, try with encoding
            $this->log('debug', 'Retrying with default encoding', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            $client = $this->createHttpClient();
            $response = $client->get($url);
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}");
            }
            
            return (string) $response->getBody();
        }
    }
    
    /**
     * Create HTTP client without Accept-Encoding (for problematic servers)
     */
    private function createHttpClientNoEncoding(): Client
    {
        $config = [
            'timeout' => 30,
            'connect_timeout' => 10,
            'allow_redirects' => [
                'max' => 5,
                'strict' => false,
                'track_redirects' => true
            ],
            'verify' => true
        ];
        
        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9,bn;q=0.8',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache'
        ];
        
        if ($this->rotateUserAgent) {
            $headers['User-Agent'] = $this->userAgentRotator->getRandom();
        } else {
            $headers['User-Agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        }
        
        $config['headers'] = $headers;
        
        if ($this->useProxy) {
            $proxy = $this->proxyManager->getProxyForProvider(ProxyManager::PROVIDER_CUSTOM);
            if ($proxy) {
                $proxyUrl = $this->buildProxyUrl($proxy);
                if ($proxyUrl) {
                    $config['proxy'] = $proxyUrl;
                }
            }
        }
        
        return new Client($config);
    }
    
    /**
     * Analyze HTML and detect CSS selectors using weight-based detection
     */
    private function analyzeHtml(string $html, string $url, string $pageLayer = 'category'): array
    {
        $crawler = new Crawler($html);
        $selectors = $this->defaultHeaders;
        
        // Adjust selectors based on page layer
        $selectors = $this->adjustSelectorsForPageLayer($selectors, $pageLayer);
        
        // Check for known sites first
        $selectors = $this->checkKnownSites($url, $selectors);
        
        // If no known site, try auto-detection with weights
        if (empty($selectors['list_item']) && empty($selectors['content'])) {
            $selectors = $this->weightBasedDetection($crawler, $url, $selectors);
        }
        
        return $selectors;
    }
    
    /**
     * Adjust selectors based on page layer type
     */
    private function adjustSelectorsForPageLayer(array $selectors, string $pageLayer): array
    {
        // Modify weight-based patterns based on page type
        switch ($pageLayer) {
            case 'home':
                // Home page - focus on listing/grid items
                $this->selectorPatterns['list_item'] = array_merge(
                    [
                        ['selector' => '.hero-news', 'weight' => 10],
                        ['selector' => '.featured-news', 'weight' => 10],
                        ['selector' => '.breaking-news', 'weight' => 9],
                        ['selector' => '.top-stories', 'weight' => 9],
                    ],
                    $this->selectorPatterns['list_item']
                );
                break;
                
            case 'category':
                // Category page - focus on list items
                $this->selectorPatterns['list_item'] = array_merge(
                    [
                        ['selector' => '.category-item', 'weight' => 10],
                        ['selector' => '.news-list-item', 'weight' => 9],
                        ['selector' => '.article-card', 'weight' => 9],
                    ],
                    $this->selectorPatterns['list_item']
                );
                break;
                
            case 'article':
                // Article page - focus on content and title
                $this->selectorPatterns['title'] = array_merge(
                    [
                        ['selector' => 'h1.article-headline', 'weight' => 10],
                        ['selector' => '.article-header h1', 'weight' => 10],
                        ['selector' => '.post-header h1', 'weight' => 9],
                    ],
                    $this->selectorPatterns['title']
                );
                $this->selectorPatterns['content'] = array_merge(
                    [
                        ['selector' => '.article-body', 'weight' => 10],
                        ['selector' => '.article-content', 'weight' => 10],
                        ['selector' => '.post-body', 'weight' => 9],
                    ],
                    $this->selectorPatterns['content']
                );
                break;
                
            case 'search':
                // Search results - focus on result items
                $this->selectorPatterns['list_item'] = array_merge(
                    [
                        ['selector' => '.search-result', 'weight' => 10],
                        ['selector' => '.result-item', 'weight' => 10],
                        ['selector' => '.search-item', 'weight' => 9],
                    ],
                    $this->selectorPatterns['list_item']
                );
                break;
                
            case 'archive':
                // Archive/pagination - focus on older posts
                $this->selectorPatterns['list_item'] = array_merge(
                    [
                        ['selector' => '.archive-item', 'weight' => 10],
                        ['selector' => '.older-post', 'weight' => 9],
                        ['selector' => '.pagination-item', 'weight' => 8],
                    ],
                    $this->selectorPatterns['list_item']
                );
                break;
        }
        
        $this->log('info', 'Selectors adjusted for page layer', [
            'page_layer' => $pageLayer
        ]);
        
        return $selectors;
    }
    
    /**
     * Weight-based selector detection
     */
    private function weightBasedDetection(Crawler $crawler, string $url, array $selectors): array
    {
        // Use XPath and CSS selectors with weights
        $converter = new CssSelectorConverter();
        
        foreach ($this->selectorPatterns as $field => $patterns) {
            $candidates = [];
            
            foreach ($patterns as $pattern) {
                $selector = $pattern['selector'];
                $weight = $pattern['weight'];
                $type = $pattern['type'] ?? 'css';
                
                try {
                    $found = null;
                    
                    if ($type === 'xpath') {
                        // Handle XPath selectors
                        $found = $crawler->filterXPath($selector);
                    } else {
                        // Handle CSS selectors
                        $found = $crawler->filter($selector);
                    }
                    
                    if ($found && count($found) > 0) {
                        $candidates[] = [
                            'selector' => $selector,
                            'weight' => $weight,
                            'count' => count($found),
                            'type' => $type
                        ];
                        
                        $this->log('debug', 'Found candidate selector', [
                            'field' => $field,
                            'selector' => $selector,
                            'count' => count($found),
                            'type' => $type
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->log('debug', 'Selector filter failed', [
                        'field' => $field,
                        'selector' => $selector,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // Select best candidate based on weight and count
            if (!empty($candidates)) {
                usort($candidates, function($a, $b) {
                    // Higher weight first, then higher count
                    if ($a['weight'] === $b['weight']) {
                        return $b['count'] - $a['count'];
                    }
                    return $b['weight'] - $a['weight'];
                });
                
                $bestCandidate = $candidates[0];
                $selectors[$field] = $bestCandidate['selector'];
                
                $this->log('info', 'Selected best selector', [
                    'field' => $field,
                    'selector' => $bestCandidate['selector'],
                    'weight' => $bestCandidate['weight'],
                    'count' => $bestCandidate['count']
                ]);
            }
        }
        
        return $selectors;
    }
    
    /**
     * Check for known websites
     */
    private function checkKnownSites(string $url, array $selectors): array
    {
        // Prothom Alo
        if (strpos($url, 'prothomalo.com') !== false) {
            $selectors['list_item'] = '.wide-story-card, .news_with_item, .story-card';
            $selectors['list_title'] = 'h3.headline-title a.title-link, .story-title a';
            $selectors['list_date'] = 'time.published-at, time.published-time';
            $selectors['title'] = 'h1.headline, .article-title';
            $selectors['content'] = '.article-content, .article_body, .story-content';
            $selectors['image'] = 'meta[property="og:image"], .article-image img';
            $selectors['date'] = 'time[datetime], .publish-date';
            $this->log('info', 'Matched known site: Prothom Alo', ['url' => $url]);
            return $selectors;
        }
        
        // BBC Bangla
        if (strpos($url, 'bbc.com/bengali') !== false || strpos($url, 'bbc.co.uk/bengali') !== false) {
            $selectors['list_item'] = '.bbc-uk8os5, .article, .news-item';
            $selectors['list_title'] = 'h3 a, .article-title a';
            $selectors['list_date'] = 'time, .date';
            $selectors['title'] = 'h1, .article-title';
            $selectors['content'] = '.article-body, .article__body-content';
            $selectors['image'] = 'meta[property="og:image"], .article-image img';
            $selectors['date'] = 'time[datetime]';
            $this->log('info', 'Matched known site: BBC Bangla', ['url' => $url]);
            return $selectors;
        }
        
        // Daily Star
        if (strpos($url, 'thedailystar.net') !== false) {
            $selectors['list_item'] = '.news-item, .card, .story';
            $selectors['list_title'] = 'h3 a, .headline a';
            $selectors['list_date'] = '.date, time';
            $selectors['title'] = 'h1.headline';
            $selectors['content'] = '.content, .article-content, .body-content';
            $selectors['image'] = '.featured-image img, meta[property="og:image"]';
            $this->log('info', 'Matched known site: Daily Star', ['url' => $url]);
            return $selectors;
        }
        
        // Ittefaq
        if (strpos($url, 'ittefaq.com.bd') !== false) {
            $selectors['list_item'] = '.news-box, .news-item, .col-md-4';
            $selectors['list_title'] = 'h3 a, .news-title a';
            $selectors['title'] = 'h1.headline';
            $selectors['content'] = '.details, .article-content';
            $this->log('info', 'Matched known site: Ittefaq', ['url' => $url]);
            return $selectors;
        }
        
        // Jugantor
        if (strpos($url, 'jugantor.com') !== false) {
            $selectors['list_item'] = '.news-item, .col-md-3';
            $selectors['list_title'] = 'h3 a, .news-title a';
            $selectors['title'] = 'h1.headline';
            $selectors['content'] = '.details, .news-details';
            $this->log('info', 'Matched known site: Jugantor', ['url' => $url]);
            return $selectors;
        }
        
        // Kaler Kantho
        if (strpos($url, 'kalerkantho.com') !== false) {
            $selectors['list_item'] = '.news-item, .col-md-3';
            $selectors['list_title'] = 'h3 a, .news-title a';
            $selectors['title'] = 'h1.headline';
            $selectors['content'] = '.details, .article-details';
            $this->log('info', 'Matched known site: Kaler Kantho', ['url' => $url]);
            return $selectors;
        }
        
        // News24
        if (strpos($url, 'news24.com') !== false) {
            $selectors['list_item'] = '.news-item, .card';
            $selectors['list_title'] = 'h3 a, .title a';
            $selectors['title'] = 'h1.headline';
            $selectors['content'] = '.content, .article-body';
            $this->log('info', 'Matched known site: News24', ['url' => $url]);
            return $selectors;
        }
        
        // Generic news patterns (fallback)
        return $this->detectGenericNewsSelectors($selectors);
    }
    
    /**
     * Generic news site detection
     */
    private function detectGenericNewsSelectors(array $selectors): array
    {
        // Common patterns for news sites - will be handled by weight-based detection
        $selectors['list_item'] = '.news-item, .article, .post, .story, .card';
        $selectors['list_title'] = 'h3 a, h2 a, .title a, .headline a';
        $selectors['list_date'] = 'time, .date, .timestamp';
        $selectors['title'] = 'h1, .article-title, .headline';
        $selectors['content'] = '.article-content, .article-body, .content, .post-content';
        $selectors['image'] = 'meta[property="og:image"], .featured-image img, .article-image img';
        $selectors['date'] = 'time[datetime], .publish-date';
        $selectors['author'] = '.author, .byline, .writer';
        
        $this->log('info', 'Using generic news selectors');
        
        return $selectors;
    }
    
    /**
     * Prune DOM - Remove unwanted elements from HTML
     * Removes scripts, styles, ads, navigation, footer, etc.
     */
    private function pruneDom(string $html): string
    {
        $dom = new \DOMDocument();
        $converter = new CssSelectorConverter();
        $previousLibxmlSetting = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadHTML($html, LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING | LIBXML_NOERROR);
            if ($loaded === false) {
                return $html;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlSetting);
        }

        $xpath = new \DOMXPath($dom);
        $removeSelectors = [
            'script', 'style', 'noscript', 'iframe',
            'nav', 'footer', 'header', 'aside',
            '.advertisement', '.ad', '.ads', '.advert',
            '.sidebar', '.widget', '.banner', '.popup',
            '.modal', '.overlay', '.cookie-notice',
            '.social-share', '.share-buttons', '.comments',
            '.related-posts', '.recommended', '.newsletter',
            '[role="banner"]', '[role="navigation"]', '[role="complementary"]',
            '.nav', '.menu', '.breadcrumb'
        ];

        foreach ($removeSelectors as $selector) {
            try {
                $selectorXpath = $converter->toXPath($selector);
                foreach ($xpath->query($selectorXpath) as $node) {
                    if ($node->parentNode !== null) {
                        $node->parentNode->removeChild($node);
                    }
                }
            } catch (\Exception $e) {
                // Ignore selector errors
            }
        }

        $bodyNodes = $dom->getElementsByTagName('body');
        if ($bodyNodes->length > 0) {
            $bodyHtml = '';
            foreach ($bodyNodes->item(0)->childNodes as $childNode) {
                $bodyHtml .= $dom->saveHTML($childNode);
            }

            return $bodyHtml !== '' ? $bodyHtml : $html;
        }

        return $dom->saveHTML() ?: $html;
    }

    /**
     * Normalize and clean fetched HTML before sending it to AI.
     */
    private function cleanHtmlForAi(string $html): string
    {
        $cleanHtml = preg_replace('/<!--[\s\S]*?-->/', '', $html) ?? $html;
        $cleanHtml = $this->pruneDom($cleanHtml);
        $cleanHtml = preg_replace('/>\s+</', '><', $cleanHtml) ?? $cleanHtml;
        $cleanHtml = preg_replace('/\s{2,}/', ' ', $cleanHtml) ?? $cleanHtml;

        return trim($cleanHtml);
    }
    
    /**
     * Chunk HTML into smaller parts for processing
     * Useful for large pages with multiple content sections
     */
    public function chunkHtml(string $html, int $chunkSize = 50000): array
    {
        $chunks = [];
        $length = strlen($html);
        
        if ($length <= $chunkSize) {
            return [$html];
        }
        
        // Split by common HTML tags
        $pattern = '/(<\/div>|<\/section>|<\/article>|<\/li>|<\/p>)/i';
        $parts = preg_split($pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $currentChunk = '';
        foreach ($parts as $part) {
            if (strlen($currentChunk) + strlen($part) > $chunkSize) {
                if (!empty(trim($currentChunk))) {
                    $chunks[] = $currentChunk;
                }
                $currentChunk = $part;
            } else {
                $currentChunk .= $part;
            }
        }
        
        if (!empty(trim($currentChunk))) {
            $chunks[] = $currentChunk;
        }
        
        $this->log('info', 'HTML chunked into parts', ['count' => count($chunks)]);
        
        return $chunks;
    }
    
    /**
     * Extract content using detected selectors
     */
    public function extractContent(string $html, array $selectors): array
    {
        $crawler = new Crawler($html);
        $content = [];
        
        // Extract each field using corresponding selector
        $fieldMappings = [
            'title' => ['title', 'list_title'],
            'content' => ['content', 'list_item'],
            'image' => ['image', 'list_image'],
            'date' => ['date', 'list_date'],
            'author' => ['author']
        ];
        
        foreach ($fieldMappings as $field => $selectorKeys) {
            foreach ($selectorKeys as $selectorKey) {
                if (!empty($selectors[$selectorKey])) {
                    $selector = $selectors[$selectorKey];
                    
                    try {
                        // Try XPath first
                        if (strpos($selector, '//') === 0) {
                            $nodes = $crawler->filterXPath($selector);
                        } else {
                            $nodes = $crawler->filter($selector);
                        }
                        
                        if (count($nodes) > 0) {
                            if ($field === 'image') {
                                // Special handling for images
                                $content[$field] = $this->extractImage($nodes);
                            } else {
                                $content[$field] = trim($nodes->first()->text());
                            }
                            break;
                        }
                    } catch (\Exception $e) {
                        $this->log('debug', 'Content extraction failed', [
                            'field' => $field,
                            'selector' => $selector,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Extract image URL from nodes
     */
    private function extractImage(Crawler $nodes): ?string
    {
        // Use Symfony Crawler's attr() method directly on the Crawler object
        try {
            // Try og:image meta tag first
            $ogImage = $nodes->filter('meta[property="og:image"]')->attr('content');
            if ($ogImage) {
                return $ogImage;
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        try {
            // Try img src
            $src = $nodes->filter('img')->first()->attr('src');
            if ($src) {
                return $src;
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        try {
            // Try data-src
            $dataSrc = $nodes->filter('img')->first()->attr('data-src');
            if ($dataSrc) {
                return $dataSrc;
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return null;
    }
    
    /**
     * Cache parsed results
     */
    private array $cache = [];
    private int $cacheExpiry = 3600; // 1 hour default
    
    /**
     * Get cached result if available and not expired
     */
    private function getCached(string $key)
    {
        if (!isset($this->cache[$key])) {
            return null;
        }
        
        $cached = $this->cache[$key];
        if (time() - $cached['time'] > $this->cacheExpiry) {
            unset($this->cache[$key]);
            return null;
        }
        
        $this->log('debug', 'Cache hit', ['key' => $key]);
        return $cached['data'];
    }
    
    /**
     * Store result in cache
     */
    private function setCache(string $key, $data): void
    {
        $this->cache[$key] = [
            'time' => time(),
            'data' => $data
        ];
        
        $this->log('debug', 'Cache stored', ['key' => $key]);
    }
    
    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
        $this->log('info', 'Cache cleared');
    }
    
    /**
     * Set cache expiry time
     */
    public function setCacheExpiry(int $seconds): void
    {
        $this->cacheExpiry = $seconds;
    }
    
    /**
     * Get selector patterns (for testing/debugging)
     */
    public function getSelectorPatterns(): array
    {
        return $this->selectorPatterns;
    }
    
    /**
     * Add custom selector pattern
     */
    public function addSelectorPattern(string $field, string $selector, int $weight = 5, string $type = 'css'): void
    {
        if (!isset($this->selectorPatterns[$field])) {
            $this->selectorPatterns[$field] = [];
        }
        
        $this->selectorPatterns[$field][] = [
            'selector' => $selector,
            'weight' => $weight,
            'type' => $type
        ];
        
        $this->log('info', 'Added custom selector pattern', [
            'field' => $field,
            'selector' => $selector,
            'weight' => $weight,
            'type' => $type
        ]);
    }
}
