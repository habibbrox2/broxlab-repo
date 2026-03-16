<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * PaginationHandler.php
 * Handles pagination for multi-page articles and list pages
 * Supports various pagination types: next button, page numbers, infinite scroll, etc.
 */
class PaginationHandler
{
    private HttpClientService $httpClient;
    private HtmlParserService $parser;
    private int $maxPages;
    private array $visitedUrls = [];

    public const TYPE_NEXT_BUTTON = 'next_button';
    public const TYPE_PAGE_NUMBERS = 'page_numbers';
    public const TYPE_LINK = 'link';
    public const TYPE_SCROLL = 'scroll';
    public const TYPE_LOAD_MORE = 'load_more';
    public const TYPE_JSON = 'json';
    public const TYPE_NONE = 'none';

    public function __construct(HttpClientService $httpClient, int $maxPages = 10)
    {
        $this->httpClient = $httpClient;
        $this->maxPages = $maxPages;
        $this->parser = new HtmlParserService();
    }

    /**
     * Set maximum pages to fetch
     */
    public function setMaxPages(int $maxPages): self
    {
        $this->maxPages = max(1, $maxPages);
        return $this;
    }

    /**
     * Get maximum pages
     */
    public function getMaxPages(): int
    {
        return $this->maxPages;
    }

    /**
     * Check if URL is already visited
     */
    public function isVisited(string $url): bool
    {
        return in_array($url, $this->visitedUrls);
    }

    /**
     * Mark URL as visited
     */
    public function markVisited(string $url): void
    {
        $this->visitedUrls[] = $url;
    }

    /**
     * Reset visited URLs
     */
    public function reset(): void
    {
        $this->visitedUrls = [];
    }

    /**
     * Find next page URL from current page
     */
    public function findNextPage(string $url, array $config): ?string
    {
        $type = $config['type'] ?? self::TYPE_LINK;
        
        // Fetch the page
        $response = $this->httpClient->get($url);
        
        if (!$response['success']) {
            return null;
        }
        
        $this->parser->loadHtml($response['body'], $url);
        
        switch ($type) {
            case self::TYPE_NEXT_BUTTON:
                return $this->findNextButton($config['selector'] ?? '.next a, .pagination .next a, a[rel="next"]');
                
            case self::TYPE_PAGE_NUMBERS:
                return $this->findNextPageNumber($url, $config['selector'] ?? '.pagination a, .page-numbers a', $config['pattern'] ?? '');
                
            case self::TYPE_LINK:
                return $this->findNextLink($config['selector'] ?? 'a.next, a[rel="next"]');
                
            case self::TYPE_LOAD_MORE:
                return $this->findLoadMoreUrl($url, $config['selector'] ?? 'button.load-more, .load-more button, a.load-more');
                
            default:
                return null;
        }
    }

    /**
     * Find "next" button link
     */
    private function findNextButton(string $selector): ?string
    {
        $link = $this->parser->extractAttribute($selector, 'href');
        
        return $link ? $this->parser->resolveUrl($link) : null;
    }

    /**
     * Find next page number URL
     */
    private function findNextPageNumber(string $baseUrl, string $selector, string $pattern): ?string
    {
        // Extract current page number from URL
        $currentPage = $this->extractPageNumber($baseUrl);
        
        // Find next page number
        $nextPage = $currentPage + 1;
        
        // Generate next page URL using pattern
        if ($pattern) {
            return $this->generatePageUrl($baseUrl, $nextPage, $pattern);
        }
        
        // Try to find link to next page
        $links = $this->parser->extractAll($selector, function ($node) {
            return [
                'href' => $node->attr('href'),
                'text' => trim($node->text()),
            ];
        });
        
        foreach ($links as $link) {
            $text = $link['text'] ?? '';
            $href = $link['href'] ?? '';
            
            // Look for "next" or page number
            if (stripos($text, 'next') !== false && $href) {
                return $this->parser->resolveUrl($href);
            }
            
            // Look for next page number
            $linkPage = $this->extractPageNumber($href);
            if ($linkPage && $linkPage === $nextPage) {
                return $this->parser->resolveUrl($href);
            }
        }
        
        // Generate URL if pattern available
        return $this->generatePageUrl($baseUrl, $nextPage, '/page/{page}');
    }

    /**
     * Find next link
     */
    private function findNextLink(string $selector): ?string
    {
        $link = $this->parser->extractAttribute($selector, 'href');
        
        return $link ? $this->parser->resolveUrl($link) : null;
    }

    /**
     * Find load more URL (for AJAX pagination)
     */
    private function findLoadMoreUrl(string $baseUrl, string $selector): ?string
    {
        // For load more, we typically need to find the API endpoint
        // This is a simplified implementation
        
        $button = $this->parser->extractAll($selector, function ($node) {
            return [
                'data-url' => $node->attr('data-url'),
                'data-page' => $node->attr('data-page'),
                'data-load-url' => $node->attr('data-load-url'),
            ];
        });
        
        if (!empty($button)) {
            $attrs = $button[0];
            
            if (!empty($attrs['data-url'])) {
                return $attrs['data-url'];
            }
            if (!empty($attrs['data-page'])) {
                return $this->generatePageUrl($baseUrl, (int)$attrs['data-page'] + 1);
            }
        }
        
        return null;
    }

    /**
     * Extract page number from URL
     */
    public function extractPageNumber(string $url): ?int
    {
        // Try common patterns
        $patterns = [
            '/\/page[_\-\/]?(\d+)/i',
            '/\?page=(\d+)/i',
            '/paged=(\d+)/i',
            '/\/(\d+)\/$/',
            '/page-(\d+)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return (int)$matches[1];
            }
        }
        
        return null;
    }

    /**
     * Generate page URL from pattern
     */
    public function generatePageUrl(string $baseUrl, int $page, ?string $pattern = null): string
    {
        if ($page <= 1) {
            return $baseUrl;
        }
        
        // Use provided pattern
        if ($pattern) {
            return str_replace('{page}', (string)$page, $pattern);
        }
        
        // Auto-generate pattern based on URL
        $parsed = parse_url($baseUrl);
        $path = $parsed['path'] ?? '/';
        $query = $parsed['query'] ?? '';
        
        // Try to find existing page number
        $pageNum = $this->extractPageNumber($path);
        
        if ($pageNum) {
            // Replace existing page number
            $path = preg_replace('/(\d+)/', (string)$page, $path, 1);
        } else {
            // Try common patterns
            if (preg_match('/^(.*\/)(\d+)(\/.*)$/', $path, $matches)) {
                $path = $matches[1] . $page . $matches[3];
            } else {
                // Append page number
                $path = rtrim($path, '/') . '/page/' . $page;
            }
        }
        
        $url = $parsed['scheme'] . '://' . $parsed['host'] . $path;
        
        if ($query) {
            $url .= '?' . $query;
        }
        
        return $url;
    }

    /**
     * Paginate through all pages of a list
     */
    public function paginateList(string $startUrl, array $config, callable $callback): array
    {
        $this->reset();
        
        $currentUrl = $startUrl;
        $page = 1;
        $results = [];
        
        while ($currentUrl && $page <= $this->maxPages) {
            // Skip if already visited
            if ($this->isVisited($currentUrl)) {
                break;
            }
            
            $this->markVisited($currentUrl);
            
            // Fetch page
            $response = $this->httpClient->get($currentUrl);
            
            if (!$response['success']) {
                break;
            }
            
            // Process page with callback
            $pageResult = $callback($response['body'], $currentUrl, $page);
            
            if ($pageResult) {
                $results = array_merge($results, $pageResult);
            }
            
            // Find next page
            $nextUrl = $this->findNextPage($currentUrl, $config);
            
            if (!$nextUrl || $nextUrl === $currentUrl) {
                break;
            }
            
            $currentUrl = $nextUrl;
            $page++;
            
            // Add delay between pages
            usleep(500000); // 0.5 second
        }
        
        return $results;
    }

    /**
     * Scrape multi-page article
     */
    public function scrapeMultiPageArticle(string $startUrl, array $config = []): array
    {
        $this->reset();
        
        $currentUrl = $startUrl;
        $parts = [];
        $page = 1;
        
        // Find pagination config
        $paginationType = $config['pagination_type'] ?? self::TYPE_LINK;
        $selector = $config['pagination_selector'] ?? 'article a.next, .pagination a';
        
        while ($currentUrl && $page <= $this->maxPages) {
            if ($this->isVisited($currentUrl)) {
                break;
            }
            
            $this->markVisited($currentUrl);
            
            // Fetch page
            $response = $this->httpClient->get($currentUrl);
            
            if (!$response['success']) {
                break;
            }
            
            // Extract content from this page
            $this->parser->loadHtml($response['body'], $currentUrl);
            $content = $this->parser->extractArticleContent($config['content_selector'] ?? null);
            
            $parts[] = [
                'page' => $page,
                'url' => $currentUrl,
                'html' => $content['html'],
                'text' => $content['text'],
            ];
            
            // Find next page
            $nextUrl = $this->findNextPage($currentUrl, [
                'type' => $paginationType,
                'selector' => $selector,
            ]);
            
            if (!$nextUrl || $nextUrl === $currentUrl) {
                break;
            }
            
            $currentUrl = $nextUrl;
            $page++;
        }
        
        // Combine all parts
        return [
            'success' => true,
            'url' => $startUrl,
            'pages' => $count = count($parts),
            'content' => implode("\n\n", array_column($parts, 'html')),
            'text' => implode("\n\n", array_column($parts, 'text')),
            'parts' => $parts,
        ];
    }

    /**
     * Handle JSON-based pagination (for APIs)
     */
    public function handleJsonPagination(string $apiUrl, array $config): array
    {
        $results = [];
        $page = 1;
        $hasMore = true;
        
        while ($hasMore && $page <= $this->maxPages) {
            // Build page URL
            $url = $this->buildJsonPageUrl($apiUrl, $page, $config);
            
            // Fetch
            $response = $this->httpClient->get($url);
            
            if (!$response['success']) {
                break;
            }
            
            $data = json_decode($response['body'], true);
            
            if (!$data) {
                break;
            }
            
            // Extract items using path
            $items = $this->extractJsonItems($data, $config['items_path'] ?? '');
            
            if (empty($items)) {
                break;
            }
            
            $results = array_merge($results, $items);
            
            // Check for more pages
            $hasMore = $this->checkJsonHasMore($data, $config, $page);
            
            $page++;
        }
        
        return $results;
    }

    /**
     * Build JSON API page URL
     */
    private function buildJsonPageUrl(string $baseUrl, int $page, array $config): string
    {
        $parsed = parse_url($baseUrl);
        $path = $parsed['path'] ?? '/';
        $query = [];
        
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }
        
        // Add pagination params
        $pageParam = $config['page_param'] ?? 'page';
        $query[$pageParam] = $page;
        
        // Add limit if specified
        if (isset($config['limit'])) {
            $query[$config['limit_param'] ?? 'limit'] = $config['limit'];
        }
        
        return $parsed['scheme'] . '://' . $parsed['host'] . $path . '?' . http_build_query($query);
    }

    /**
     * Extract items from JSON
     */
    private function extractJsonItems(array $data, string $path): array
    {
        if (empty($path)) {
            return is_array($data) ? $data : [];
        }
        
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (isset($current[$key])) {
                $current = $current[$key];
            } else {
                return [];
            }
        }
        
        return is_array($current) ? $current : [];
    }

    /**
     * Check if JSON response has more pages
     */
    private function checkJsonHasMore(array $data, array $config, int $currentPage): bool
    {
        // Check pagination info in response
        $totalPath = $config['total_path'] ?? 'meta.total';
        $perPagePath = $config['per_page_path'] ?? 'meta.per_page';
        
        $total = $this->getJsonValue($data, $totalPath);
        $perPage = $this->getJsonValue($data, $perPagePath) ?: 20;
        
        if ($total && $perPage) {
            $totalPages = ceil($total / $perPage);
            return $currentPage < $totalPages;
        }
        
        // Check next_cursor
        $nextCursor = $this->getJsonValue($data, $config['next_cursor_path'] ?? 'pagination.next_cursor');
        
        return !empty($nextCursor);
    }

    /**
     * Get value from JSON using dot notation
     */
    private function getJsonValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (isset($current[$key])) {
                $current = $current[$key];
            } else {
                return null;
            }
        }
        
        return $current;
    }

    /**
     * Get visited URLs count
     */
    public function getVisitedCount(): int
    {
        return count($this->visitedUrls);
    }

    /**
     * Auto-detect pagination type from page
     */
    public function autoDetectPagination(string $url): string
    {
        $response = $this->httpClient->get($url);
        
        if (!$response['success']) {
            return self::TYPE_NONE;
        }
        
        $this->parser->loadHtml($response['body'], $url);
        
        // Check for next button
        if ($this->parser->has('a[rel="next"], a.next, .next a, .pagination .next a')) {
            return self::TYPE_NEXT_BUTTON;
        }
        
        // Check for page numbers
        if ($this->parser->has('.pagination a, .page-numbers a, .pager a')) {
            return self::TYPE_PAGE_NUMBERS;
        }
        
        // Check for load more
        if ($this->parser->has('.load-more, button.load-more, [data-load-more]')) {
            return self::TYPE_LOAD_MORE;
        }
        
        return self::TYPE_NONE;
    }
}
