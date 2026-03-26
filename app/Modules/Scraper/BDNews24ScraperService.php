<?php

declare(strict_types=1);

/**
 * BDNews24ScraperService.php
 * Service for scraping BDNews24 Bangla articles
 */

use App\Modules\Scraper\HttpClientService;
use App\Modules\Scraper\HtmlParserService;

class BDNews24ScraperService
{
    private HttpClientService $httpClient;
    private HtmlParserService $parser;
    private array $config;
    private array $stats = [
        'total_scraped' => 0,
        'new_articles' => 0,
        'duplicates' => 0,
        'errors' => 0,
    ];

    public function __construct(HttpClientService $httpClient, ?array $config = null)
    {
        $this->httpClient = $httpClient;
        $this->parser = new HtmlParserService();
        
        // Load configuration
        $this->config = $config ?? $this->loadConfig();
    }

    /**
     * Load configuration from file
     */
    private function loadConfig(): array
    {
        $configFile = __DIR__ . '/config/bdnews24.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }
        
        // Default configuration
        return [
            'base_url' => 'https://bangla.bdnews24.com',
            'special_url' => '/special',
            'selectors' => [
                'article_container' => '.rm-container',
                'article_link' => 'a',
                'article_image' => 'img',
                'article_title' => 'h5',
                'cursor_input' => '#next-cursor',
            ],
            'pagination' => [
                'max_pages' => 10,
                'cursor_param' => 'cursor',
            ],
            'rate_limit' => [
                'delay_min' => 1000,
                'delay_max' => 2000,
            ],
            'http' => [
                'timeout' => 30,
                'headers' => [
                    'Accept-Language' => 'bn-BD,bn;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate',
                ],
            ],
            'storage' => [
                'log_dir' => __DIR__ . '/logs',
            ],
            'validation' => [
                'min_title_length' => 5,
                'required_fields' => ['url', 'title', 'headline'],
            ],
        ];
    }

    /**
     * Scrape article listings from a page
     */
    public function scrapeArticleListings(string $url): array
    {
        try {
            // Fetch HTML
            $response = $this->httpClient->get($url, [
                'headers' => $this->config['http']['headers'],
                'timeout' => $this->config['http']['timeout'],
            ]);

            if (!$response['success']) {
                throw new \Exception('Failed to fetch page: ' . ($response['error'] ?? 'Unknown error'));
            }

            $html = $response['body'];
            
            // Load HTML into parser
            $this->parser->loadHtml($html, $this->config['base_url']);

            // Extract articles
            $articles = $this->extractArticlesFromHtml();
            
            // Extract cursor for next page
            $nextCursor = $this->extractCursorFromHtml();

            return [
                'success' => true,
                'articles' => $articles,
                'next_cursor' => $nextCursor,
            ];
        } catch (\Exception $e) {
            error_log("BDNews24ScraperService::scrapeArticleListings error: " . $e->getMessage());
            return [
                'success' => false,
                'articles' => [],
                'next_cursor' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract articles from HTML using DOM-based parsing
     */
    private function extractArticlesFromHtml(): array
    {
        $articles = [];
        $crawler = $this->parser->getCrawler();
        
        if (!$crawler) {
            return $articles;
        }

        // Find all article containers
        $containers = $crawler->filter($this->config['selectors']['article_container']);
        
        foreach ($containers as $container) {
            try {
                $article = $this->extractArticleFromContainer($container);
                if ($article) {
                    $articles[] = $article;
                }
            } catch (\Exception $e) {
                error_log("Error extracting article: " . $e->getMessage());
                $this->updateStats('errors', 1);
            }
        }

        return $articles;
    }

    /**
     * Extract article data from a container element
     */
    private function extractArticleFromContainer($container): ?array
    {
        // Find the article link
        $linkElement = $container->filter($this->config['selectors']['article_link'])->first();
        if (!$linkElement) {
            return null;
        }

        $url = $linkElement->attr('href');
        if (!$url) {
            return null;
        }

        // Make URL absolute if needed
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = $this->config['base_url'] . '/' . ltrim($url, '/');
        }

        // Extract article_id from URL
        $articleId = $this->extractArticleIdFromUrl($url);

        // Extract image
        $imageElement = $container->filter($this->config['selectors']['article_image'])->first();
        $imageUrl = $imageElement ? $imageElement->attr('src') : null;

        // Extract title from alt attribute
        $title = $imageElement ? $imageElement->attr('alt') : null;

        // Extract headline from h5
        $headlineElement = $container->filter($this->config['selectors']['article_title'])->first();
        $headline = $headlineElement ? trim($headlineElement->text()) : null;

        // Validate required fields
        if (!$url || !$title || !$headline) {
            return null;
        }

        // Validate minimum title length
        if (strlen($title) < $this->config['validation']['min_title_length']) {
            return null;
        }

        return [
            'article_id' => $articleId,
            'url' => $url,
            'title' => $title,
            'headline' => $headline,
            'image_url' => $imageUrl,
            'category' => null, // Will be extracted from detail page if needed
            'published_at' => null, // Will be extracted from detail page if needed
        ];
    }

    /**
     * Extract article ID from URL
     */
    private function extractArticleIdFromUrl(string $url): string
    {
        // Extract ID from URL path
        // Example: https://bangla.bdnews24.com/samagrabangladesh/4e0e549152f8
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', trim($path, '/'));
        return end($parts) ?: md5($url);
    }

    /**
     * Extract cursor for next page from HTML
     */
    private function extractCursorFromHtml(): ?string
    {
        $crawler = $this->parser->getCrawler();
        
        if (!$crawler) {
            return null;
        }

        // Find the cursor input element
        $cursorElement = $crawler->filter($this->config['selectors']['cursor_input'])->first();
        
        if ($cursorElement) {
            $cursor = $cursorElement->attr('value');
            if ($cursor) {
                return $cursor;
            }
        }

        return null;
    }

    /**
     * Scrape all pages with cursor-based pagination
     */
    public function scrapeAllPages(int $maxPages = 3, ?callable $progressCallback = null): array
    {
        $this->resetStats();
        $allArticles = [];
        $currentUrl = $this->config['base_url'] . $this->config['special_url'];
        $pageCount = 0;

        while ($pageCount < $maxPages) {
            $pageCount++;
            
            try {
                // Scrape current page
                $result = $this->scrapeArticleListings($currentUrl);
                
                if (!$result['success']) {
                    $this->updateStats('errors', 1);
                    if ($progressCallback) {
                        $progressCallback($pageCount, $maxPages, false, $result['error']);
                    }
                    break;
                }

                $articles = $result['articles'];
                $allArticles = array_merge($allArticles, $articles);
                $this->updateStats('total_scraped', count($articles));

                if ($progressCallback) {
                    $progressCallback($pageCount, $maxPages, true, $articles);
                }

                // Check if there's a next cursor
                $nextCursor = $result['next_cursor'];
                if (!$nextCursor) {
                    break;
                }

                // Build next URL with cursor
                $currentUrl = $this->config['base_url'] . $this->config['special_url'] . 
                              '?' . $this->config['pagination']['cursor_param'] . '=' . urlencode($nextCursor);

                // Rate limiting delay
                $delay = rand($this->config['rate_limit']['delay_min'], $this->config['rate_limit']['delay_max']);
                usleep($delay * 1000); // Convert to microseconds

            } catch (\Exception $e) {
                error_log("Error scraping page {$pageCount}: " . $e->getMessage());
                $this->updateStats('errors', 1);
                if ($progressCallback) {
                    $progressCallback($pageCount, $maxPages, false, $e->getMessage());
                }
                break;
            }
        }

        return [
            'success' => true,
            'articles' => $allArticles,
            'pages_scraped' => $pageCount,
        ];
    }

    /**
     * Scrape article detail page
     */
    public function scrapeArticleDetail(string $url): array
    {
        try {
            $response = $this->httpClient->get($url, [
                'headers' => $this->config['http']['headers'],
                'timeout' => $this->config['http']['timeout'],
            ]);

            if (!$response['success']) {
                throw new \Exception('Failed to fetch article detail: ' . ($response['error'] ?? 'Unknown error'));
            }

            $html = $response['body'];
            $this->parser->loadHtml($html, $this->config['base_url']);

            // Extract additional details
            $category = $this->extractCategoryFromHtml();
            $publishedAt = $this->extractPublishedAtFromHtml();

            return [
                'success' => true,
                'category' => $category,
                'published_at' => $publishedAt,
            ];
        } catch (\Exception $e) {
            error_log("BDNews24ScraperService::scrapeArticleDetail error: " . $e->getMessage());
            return [
                'success' => false,
                'category' => null,
                'published_at' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract category from HTML
     */
    private function extractCategoryFromHtml(): ?string
    {
        $crawler = $this->parser->getCrawler();
        
        if (!$crawler) {
            return null;
        }

        // Try to find category in breadcrumb or meta tags
        $categoryElement = $crawler->filter('.breadcrumb-item.active, .category')->first();
        if ($categoryElement) {
            return trim($categoryElement->text());
        }

        // Try meta tag
        $metaCategory = $this->parser->extractMetaContent('category');
        if ($metaCategory) {
            return $metaCategory;
        }

        return null;
    }

    /**
     * Extract published date from HTML
     */
    private function extractPublishedAtFromHtml(): ?string
    {
        $crawler = $this->parser->getCrawler();
        
        if (!$crawler) {
            return null;
        }

        // Try to find date in meta tags or article body
        $dateElement = $crawler->filter('.published-date, time[datetime], .article-date')->first();
        if ($dateElement) {
            $datetime = $dateElement->attr('datetime') ?: $dateElement->text();
            if ($datetime) {
                $timestamp = strtotime($datetime);
                if ($timestamp !== false) {
                    return date('Y-m-d H:i:s', $timestamp);
                }
            }
        }

        // Try meta tag
        $metaDate = $this->parser->extractMetaContent('published_time');
        if ($metaDate) {
            $timestamp = strtotime($metaDate);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        return null;
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
            'new_articles' => 0,
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

    /**
     * Get HTTP client
     */
    public function getHttpClient(): HttpClientService
    {
        return $this->httpClient;
    }

    /**
     * Get HTML parser
     */
    public function getParser(): HtmlParserService
    {
        return $this->parser;
    }
}
