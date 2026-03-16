<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * ArticleScraper.php
 * Extracts article content from various sources
 * Supports RSS feeds, HTML pages, and JSON APIs
 */
class ArticleScraper
{
    private HttpClientService $httpClient;
    private HtmlParserService $parser;
    private SourceConfigManager $sourceManager;

    public function __construct(
        HttpClientService $httpClient,
        ?SourceConfigManager $sourceManager = null
    ) {
        $this->httpClient = $httpClient;
        $this->sourceManager = $sourceManager ?? new SourceConfigManager();
        $this->parser = new HtmlParserService();
    }

    /**
     * Scrape article from URL
     */
    public function scrape(string $url, ?array $selectors = null): array
    {
        // Get selectors from source config or use provided
        $selectors = $selectors ?? $this->sourceManager->getSelectors($url);
        
        // Fetch the page
        $response = $this->httpClient->get($url);
        
        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'],
                'url' => $url,
            ];
        }

        // Parse HTML
        $this->parser->loadHtml($response['body'], $url);
        
        // Extract article data
        $article = $this->extractArticleData($url, $selectors);
        
        return [
            'success' => true,
            'url' => $url,
            'final_url' => $response['url'] ?? $url,
            'title' => $article['title'],
            'content' => $article['content'],
            'excerpt' => $article['excerpt'],
            'author' => $article['author'],
            'date' => $article['date'],
            'images' => $article['images'],
            'featured_image' => $article['featured_image'],
            'metadata' => $article['metadata'],
            'raw_html' => $response['body'],
        ];
    }

    /**
     * Extract article data using selectors
     */
    private function extractArticleData(string $url, array $selectors): array
    {
        // Extract title
        $title = $this->extractWithSelectors($selectors['title'] ?? [], 'title');
        
        // Extract content
        $contentResult = $this->extractContentWithSelectors($selectors['content'] ?? []);
        
        // Extract metadata
        $metadata = $this->parser->extractMetadata();
        
        // Override with selector-based values
        if (!$title && isset($metadata['title'])) {
            $title = $metadata['title'];
        }
        
        // Extract author
        $author = $this->extractWithSelectors($selectors['author'] ?? [], 'author');
        if (!$author && isset($metadata['author'])) {
            $author = $metadata['author'];
        }
        
        // Extract date
        $date = $this->extractWithSelectors($selectors['date'] ?? [], 'date');
        if (!$date && isset($metadata['published_date'])) {
            $date = $metadata['published_date'];
        }
        
        // Extract excerpt
        $excerpt = $this->extractWithSelectors($selectors['excerpt'] ?? []);
        if (!$excerpt && isset($metadata['description'])) {
            $excerpt = $metadata['description'];
        }
        
        // Extract featured image
        $featuredImage = $this->extractWithSelectors($selectors['image'] ?? []);
        
        return [
            'title' => $title ?? 'Untitled',
            'content' => $contentResult['html'],
            'content_text' => $contentResult['text'],
            'excerpt' => $excerpt,
            'author' => $author,
            'date' => $date,
            'images' => $contentResult['images'],
            'featured_image' => $featuredImage,
            'metadata' => $metadata,
        ];
    }

    /**
     * Extract using multiple selector options
     */
    private function extractWithSelectors(array $selectorOptions, string $defaultType = null): ?string
    {
        // If selectors is a simple array of strings
        if (!empty($selectorOptions) && is_string($selectorOptions[0] ?? '')) {
            return $this->parser->extractTextMultiple($selectorOptions);
        }
        
        // If it's keyed by type
        if (is_array($selectorOptions)) {
            // Try to find content key
            foreach ($selectorOptions as $key => $selector) {
                if (is_string($selector)) {
                    $result = $this->parser->extractText($selector);
                    if ($result) {
                        return $result;
                    }
                } elseif (is_array($selector)) {
                    $result = $this->parser->extractTextMultiple($selector);
                    if ($result) {
                        return $result;
                    }
                }
            }
        }
        
        // Fallback to default type
        if ($defaultType) {
            return $this->parser->extractWithDefaults($defaultType);
        }
        
        return null;
    }

    /**
     * Extract content with multiple selector options
     */
    private function extractContentWithSelectors(array $selectorOptions): array
    {
        // Try each selector option
        if (!empty($selectorOptions) && is_string($selectorOptions[0] ?? '')) {
            return $this->parser->extractArticleContent($selectorOptions[0]);
        }
        
        if (is_array($selectorOptions)) {
            foreach ($selectorOptions as $selector) {
                if (is_string($selector)) {
                    $result = $this->parser->extractArticleContent($selector);
                    if (!empty($result['html'])) {
                        return $result;
                    }
                }
            }
        }
        
        // Fallback to default content extraction
        return $this->parser->extractArticleContent();
    }

    /**
     * Scrape article list from a page
     */
    public function scrapeList(string $url, ?array $selectors = null): array
    {
        $selectors = $selectors ?? $this->sourceManager->getSelectors($url);
        
        // Fetch the page
        $response = $this->httpClient->get($url);
        
        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'],
                'url' => $url,
                'articles' => [],
            ];
        }

        // Parse HTML
        $this->parser->loadHtml($response['body'], $url);
        
        // Extract list container and items
        $containerSelector = $selectors['list_container'] ?? '';
        $itemSelector = $selectors['list_item'] ?? '';
        
        if (empty($containerSelector) || empty($itemSelector)) {
            // Try to auto-detect article links
            return $this->extractLinksFromPage($url, $response['body']);
        }
        
        $articles = $this->extractListItems($containerSelector, $itemSelector, $selectors);
        
        return [
            'success' => true,
            'url' => $url,
            'articles' => $articles,
            'count' => count($articles),
        ];
    }

    /**
     * Extract list items from container
     */
    private function extractListItems(string $containerSelector, string $itemSelector, array $selectors): array
    {
        $items = $this->parser->extractListItems($containerSelector, $itemSelector);
        
        $articles = [];
        
        foreach ($items as $item) {
            // Create temp parser for item
            $itemParser = new HtmlParserService($item['html'], $this->parser->getBaseUrl());
            
            // Extract article info
            $title = $itemParser->extractTextMultiple($selectors['list_title'] ?? ['h2', 'h3', 'a']);
            $url = $itemParser->extractAttribute('a', 'href');
            $date = $itemParser->extractTextMultiple($selectors['list_date'] ?? ['time', '.date', '.published']);
            $image = $itemParser->extractAttribute('img', 'src');
            
            if ($title && $url) {
                $articles[] = [
                    'title' => trim($title),
                    'url' => $this->parser->resolveUrl($url),
                    'date' => $date,
                    'image' => $image ? $this->parser->resolveUrl($image) : null,
                ];
            }
        }
        
        return $articles;
    }

    /**
     * Extract links from page (fallback)
     */
    private function extractLinksFromPage(string $url, string $html): array
    {
        $this->parser->loadHtml($html, $url);
        
        // Try to find common article link patterns
        $links = $this->parser->extractLinks('article a, .post-title a, .entry-title a, h2 a, h3 a');
        
        $articles = [];
        
        foreach ($links as $link) {
            $articles[] = [
                'title' => $link['text'] ?: 'Untitled',
                'url' => $link['url'],
                'date' => null,
                'image' => null,
            ];
        }
        
        return $articles;
    }

    /**
     * Scrape RSS feed
     */
    public function scrapeRss(string $feedUrl, ?array $selectors = null): array
    {
        $response = $this->httpClient->get($feedUrl);
        
        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'],
                'articles' => [],
            ];
        }
        
        // Parse RSS/Atom
        $articles = $this->parseRssFeed($response['body'], $feedUrl);
        
        return [
            'success' => true,
            'feed_url' => $feedUrl,
            'articles' => $articles,
            'count' => count($articles),
        ];
    }

    /**
     * Parse RSS/Atom feed
     */
    private function parseRssFeed(string $xml, string $baseUrl): array
    {
        libxml_use_internal_errors(true);
        
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        
        $articles = [];
        
        // Try RSS 2.0
        $items = $doc->getElementsByTagName('item');
        
        if ($items->length === 0) {
            // Try Atom
            $items = $doc->getElementsByTagName('entry');
        }
        
        foreach ($items as $item) {
            $title = '';
            $link = '';
            $description = '';
            $pubDate = '';
            $author = '';
            $image = '';
            
            // Get title
            $titleNode = $item->getElementsByTagName('title')->item(0);
            if ($titleNode) {
                $title = trim($titleNode->textContent);
            }
            
            // Get link (RSS)
            $linkNode = $item->getElementsByTagName('link')->item(0);
            if ($linkNode && $linkNode->textContent) {
                $link = trim($linkNode->textContent);
            } elseif ($linkNode && $linkNode->getAttribute('href')) {
                // Atom format
                $link = trim($linkNode->getAttribute('href'));
            }
            
            // Get description
            $descNode = $item->getElementsByTagName('description')->item(0);
            if (!$descNode) {
                $descNode = $item->getElementsByTagName('summary')->item(0);
            }
            if (!$descNode) {
                $descNode = $item->getElementsByTagName('content')->item(0);
            }
            if ($descNode) {
                $description = trim($descNode->textContent);
            }
            
            // Get date
            $dateNode = $item->getElementsByTagName('pubDate')->item(0);
            if (!$dateNode) {
                $dateNode = $item->getElementsByTagName('published')->item(0);
            }
            if (!$dateNode) {
                $dateNode = $item->getElementsByTagName('updated')->item(0);
            }
            if ($dateNode) {
                $pubDate = trim($dateNode->textContent);
            }
            
            // Get author
            $authorNode = $item->getElementsByTagName('author')->item(0);
            if (!$authorNode) {
                $authorNode = $item->getElementsByTagName('dc:creator')->item(0);
            }
            if ($authorNode) {
                $author = trim($authorNode->textContent);
            }
            
            // Get image from enclosure or media:content
            $enclosure = $item->getElementsByTagName('enclosure')->item(0);
            if ($enclosure && $enclosure->getAttribute('type') && str_starts_with($enclosure->getAttribute('type'), 'image')) {
                $image = $enclosure->getAttribute('url');
            }
            
            if ($link) {
                $articles[] = [
                    'title' => $title ?: 'Untitled',
                    'url' => $link,
                    'description' => strip_tags($description),
                    'date' => $pubDate,
                    'author' => $author,
                    'image' => $image,
                ];
            }
        }
        
        libxml_clear_errors();
        
        return $articles;
    }

    /**
     * Scrape JSON API
     */
    public function scrapeJson(string $apiUrl, array $config = []): array
    {
        $response = $this->httpClient->get($apiUrl);
        
        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'],
                'articles' => [],
            ];
        }
        
        $data = json_decode($response['body'], true);
        
        if (!$data) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response',
                'articles' => [],
            ];
        }
        
        // Extract articles using path config
        $articles = $this->extractFromJson($data, $config);
        
        return [
            'success' => true,
            'api_url' => $apiUrl,
            'articles' => $articles,
            'count' => count($articles),
        ];
    }

    /**
     * Extract articles from JSON using path configuration
     */
    private function extractFromJson(array $data, array $config): array
    {
        $path = $config['path'] ?? '';
        $titlePath = $config['title'] ?? 'title';
        $urlPath = $config['url'] ?? 'url';
        $contentPath = $config['content'] ?? 'content';
        $datePath = $config['date'] ?? 'date';
        
        // Navigate to array using path
        $items = $this->getJsonPath($data, $path);
        
        if (!is_array($items)) {
            return [];
        }
        
        $articles = [];
        
        foreach ($items as $item) {
            $article = [
                'title' => $this->getJsonPath($item, $titlePath) ?? 'Untitled',
                'url' => $this->getJsonPath($item, $urlPath) ?? '',
                'content' => $this->getJsonPath($item, $contentPath) ?? '',
                'date' => $this->getJsonPath($item, $datePath) ?? '',
                'author' => $this->getJsonPath($item, $config['author'] ?? 'author') ?? '',
                'image' => $this->getJsonPath($item, $config['image'] ?? 'image') ?? '',
            ];
            
            if ($article['url']) {
                $articles[] = $article;
            }
        }
        
        return $articles;
    }

    /**
     * Get value from JSON using dot notation path
     */
    private function getJsonPath(array $data, string $path): mixed
    {
        if (empty($path)) {
            return $data;
        }
        
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (is_array($current) && isset($current[$key])) {
                $current = $current[$key];
            } else {
                return null;
            }
        }
        
        return $current;
    }

    /**
     * Get HTTP client
     */
    public function getHttpClient(): HttpClientService
    {
        return $this->httpClient;
    }

    /**
     * Get parser
     */
    public function getParser(): HtmlParserService
    {
        return $this->parser;
    }

    /**
     * Get source manager
     */
    public function getSourceManager(): SourceConfigManager
    {
        return $this->sourceManager;
    }
}
