<?php

namespace App\Modules\Scraper;

use App\Modules\Scraper\HttpClientService;
use App\Modules\Scraper\HtmlParserService;

/**
 * GSMArena News Scraper Service
 * 
 * Scrapes news articles from gsmarena.com/news
 * 
 * @package BroxBhai
 * @since 2026-03-26
 */
class GSMArenaNewsScraperService
{
    private HttpClientService $httpClient;
    private HtmlParserService $parser;
    private array $config;
    private array $stats = [
        'pages_scraped' => 0,
        'news_found' => 0,
        'errors' => 0
    ];

    public function __construct(array $config = [])
    {
        $this->httpClient = new HttpClientService();
        $this->parser = new HtmlParserService();
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'base_url' => 'https://www.gsmarena.com',
            'news_url' => '/news.php3',
            'selectors' => [
                'news_container' => '.news-item',
                'news_link' => 'a',
                'news_title' => 'h3',
                'news_image' => 'img',
                'news_summary' => 'p',
                'news_date' => '.date',
                'pagination' => '.pagination a',
            ],
            'pagination' => [
                'enabled' => true,
                'max_pages' => 10,
                'delay' => 1000, // 1 second delay
            ],
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            ],
            'storage' => [
                'table' => 'gsmarena_news',
            ],
            'logging' => [
                'enabled' => true,
            ],
        ];
    }

    /**
     * Scrape news listings from a page
     */
    public function scrapeNewsListings(int $page = 1, int $limit = 20): array
    {
        $url = $this->config['base_url'] . $this->config['news_url'] . '?id=' . ($page - 1) * $limit;
        
        $response = $this->httpClient->get($url, [
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
        ]);
        
        if (!$response['success']) {
            $this->stats['errors']++;
            throw new \Exception("Failed to fetch news page: " . ($response['error'] ?? 'Unknown error'));
        }
        
        $html = $response['body'];
        $this->parser->loadHtml($html, $this->config['base_url']);
        
        $newsItems = [];
        $newsElements = $this->parser->extractAll($this->config['selectors']['news_container']);
        
        foreach ($newsElements as $element) {
            $newsItem = $this->extractNewsItem($element);
            if ($newsItem) {
                $newsItems[] = $newsItem;
            }
        }
        
        return $newsItems;
    }

    /**
     * Extract news item from element
     */
    private function extractNewsItem($element): ?array
    {
        $linkElement = $this->parser->extractFirst($this->config['selectors']['news_link'], $element);
        if (!$linkElement) {
            return null;
        }
        
        $titleElement = $this->parser->extractFirst($this->config['selectors']['news_title'], $linkElement);
        $imageElement = $this->parser->extractFirst($this->config['selectors']['news_image'], $linkElement);
        $summaryElement = $this->parser->extractFirst($this->config['selectors']['news_summary'], $linkElement);
        $dateElement = $this->parser->extractFirst($this->config['selectors']['news_date'], $linkElement);
        
        $url = $this->parser->extractAttribute('href', $linkElement);
        $imageUrl = $imageElement ? $this->parser->extractAttribute('src', $imageElement) : null;
        $title = $titleElement ? trim($this->parser->extractText($titleElement)) : '';
        $summary = $summaryElement ? trim($this->parser->extractText($summaryElement)) : '';
        $date = $dateElement ? trim($this->parser->extractText($dateElement)) : '';
        
        // Generate news ID from URL
        $newsId = $this->extractNewsId($url);
        
        if (empty($title) || empty($newsId)) {
            return null;
        }
        
        return [
            'news_id' => $newsId,
            'url' => $this->parser->resolveUrl($url),
            'title' => $title,
            'summary' => $summary,
            'image_url' => $imageUrl ? $this->parser->resolveUrl($imageUrl) : null,
            'published_at' => $this->parseDate($date),
        ];
    }

    /**
     * Extract news ID from URL
     */
    private function extractNewsId(string $url): string
    {
        // Extract ID from URL like /news.php3?id=12345
        if (preg_match('/id=(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return md5($url);
    }

    /**
     * Parse date string
     */
    private function parseDate(string $date): ?string
    {
        if (empty($date)) {
            return null;
        }
        
        // Try various date formats
        $formats = [
            'Y-m-d H:i',
            'd M Y',
            'M d, Y',
            'F j, Y',
        ];
        
        foreach ($formats as $format) {
            $parsed = date_create_from_format($format, $date);
            if ($parsed !== false) {
                return date('Y-m-d H:i:s', $parsed);
            }
        }
        
        return null;
    }

    /**
     * Scrape all pages
     */
    public function scrapeAllPages(int $maxPages = 10, ?callable $progressCallback = null): array
    {
        $allNews = [];
        $this->resetStats();
        
        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $newsItems = $this->scrapeNewsListings($page, 20);
                
                foreach ($newsItems as $newsItem) {
                    $allNews[] = $newsItem;
                    $this->stats['news_found']++;
                    
                    if ($progressCallback) {
                        $progressCallback($page, $maxPages, $newsItem);
                    }
                }
                
                $this->stats['pages_scraped']++;
                
                // Rate limiting
                if ($page < $maxPages) {
                    usleep($this->config['pagination']['delay'] * 1000);
                }
                
            } catch (\Exception $e) {
                $this->stats['errors']++;
                error_log("GSMArena News Scraper Error: " . $e->getMessage());
            }
        }
        
        return $allNews;
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
            'pages_scraped' => 0,
            'news_found' => 0,
            'errors' => 0
        ];
    }
}
