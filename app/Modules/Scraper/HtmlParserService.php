<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * HtmlParserService.php
 * HTML Parsing Layer using Symfony DomCrawler with custom selectors
 * Supports CSS selectors, XPath, and content extraction helpers
 */
class HtmlParserService
{
    private ?Crawler $crawler = null;
    private string $baseUrl = '';
    private array $defaultSelectors = [
        'title' => [
            'h1',
            'article h1',
            '.post-title',
            '.entry-title',
            '.article-title',
            '.headline',
            'head title',
        ],
        'content' => [
            'article',
            '.post-content',
            '.article-content',
            '.entry-content',
            '.content',
            'main',
            '.post-body',
            '.story-content',
            '.article-body',
        ],
        'image' => [
            'meta[property="og:image"]',
            'meta[name="twitter:image"]',
            'article img',
            '.post-thumbnail img',
            '.featured-image img',
            '.article-image img',
        ],
        'author' => [
            '.author-name',
            '.byline',
            '[rel="author"]',
            '.article-author',
            '.post-author',
            'meta[name="author"]',
        ],
        'date' => [
            'time[datetime]',
            '.published-at',
            '.post-date',
            '.article-date',
            'meta[property="article:published_time"]',
            '.date',
        ],
        'excerpt' => [
            'meta[name="description"]',
            'meta[property="og:description"]',
            '.excerpt',
            '.article-excerpt',
            '.post-excerpt',
            '.summary',
        ],
    ];

    public function __construct(?string $html = null, ?string $baseUrl = null)
    {
        if ($html !== null) {
            $this->loadHtml($html, $baseUrl);
        }
    }

    /**
     * Load HTML content
     */
    public function loadHtml(string $html, ?string $baseUrl = null): self
    {
        // Handle encoding issues
        $html = $this->sanitizeHtml($html);
        
        $this->baseUrl = $baseUrl ?? '';
        
        try {
            $this->crawler = new Crawler($html, $this->baseUrl);
        } catch (\Exception $e) {
            // Fallback: try with a simpler approach
            $this->crawler = new Crawler();
            $this->crawler->addHtmlContent($html, 'UTF-8', false);
        }

        return $this;
    }

    /**
     * Sanitize HTML content
     */
    private function sanitizeHtml(string $html): string
    {
        // Remove malformed characters
        $html = preg_replace('/[\x00-\x1F\x7F]/u', '', $html);
        
        // Fix common encoding issues
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        
        return $html;
    }

    /**
     * Get crawler instance
     */
    public function getCrawler(): ?Crawler
    {
        return $this->crawler;
    }

    /**
     * Extract text using CSS selector
     */
    public function extractText(string $selector): ?string
    {
        if (!$this->crawler) {
            return null;
        }

        try {
            $elements = $this->crawler->filter($selector);
            
            if ($elements->count() === 0) {
                return null;
            }

            $text = $elements->first()->text();
            return trim($text) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract text using multiple selectors (tries each until found)
     */
    public function extractTextMultiple(array $selectors): ?string
    {
        foreach ($selectors as $selector) {
            $text = $this->extractText($selector);
            if ($text !== null) {
                return $text;
            }
        }
        return null;
    }

    /**
     * Extract attribute from element
     */
    public function extractAttribute(string $selector, string $attribute): ?string
    {
        if (!$this->crawler) {
            return null;
        }

        try {
            $elements = $this->crawler->filter($selector);
            
            if ($elements->count() === 0) {
                return null;
            }

            return $elements->first()->attr($attribute) ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract all matching elements as array
     */
    public function extractAll(string $selector, callable $callback = null): array
    {
        if (!$this->crawler) {
            return [];
        }

        try {
            $elements = $this->crawler->filter($selector);
            $results = [];

            $elements->each(function (Crawler $node) use (&$results, $callback) {
                if ($callback) {
                    $results[] = $callback($node);
                } else {
                    $results[] = [
                        'html' => $node->html(),
                        'text' => trim($node->text()),
                    ];
                }
            });

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extract content using default selectors
     */
    public function extractWithDefaults(string $type): ?string
    {
        $selectors = $this->defaultSelectors[$type] ?? [];
        return $this->extractTextMultiple($selectors);
    }

    /**
     * Extract article metadata
     */
    public function extractMetadata(): array
    {
        if (!$this->crawler) {
            return [];
        }

        $metadata = [
            'title' => $this->extractWithDefaults('title'),
            'description' => $this->extractWithDefaults('excerpt'),
            'image' => $this->extractMetaContent('og:image') ?? $this->extractMetaContent('twitter:image'),
            'author' => $this->extractWithDefaults('author'),
            'published_date' => $this->extractDate(),
            'keywords' => $this->extractMetaContent('keywords'),
        ];

        return array_filter($metadata);
    }

    /**
     * Extract meta tag content
     */
    public function extractMetaContent(string $nameOrProperty): ?string
    {
        if (!$this->crawler) {
            return null;
        }

        // Try property (Open Graph)
        $element = $this->crawler->filter("meta[property=\"{$nameOrProperty}\"]");
        if ($element->count() > 0) {
            return trim($element->attr('content') ?? '');
        }

        // Try name
        $element = $this->crawler->filter("meta[name=\"{$nameOrProperty}\"]");
        if ($element->count() > 0) {
            return trim($element->attr('content') ?? '');
        }

        return null;
    }

    /**
     * Extract date from various formats
     */
    private function extractDate(): ?string
    {
        if (!$this->crawler) {
            return null;
        }

        // Try datetime attribute
        $datetime = $this->crawler->filter('time[datetime]')->first()->attr('datetime');
        if ($datetime) {
            return $datetime;
        }

        // Try meta tags
        $date = $this->extractMetaContent('article:published_time');
        if ($date) {
            return $date;
        }

        // Try date selectors
        return $this->extractWithDefaults('date');
    }

    /**
     * Extract all images from content
     */
    public function extractImages(?string $contextSelector = null): array
    {
        if (!$this->crawler) {
            return [];
        }

        $images = [];
        $crawler = $this->crawler;

        // Filter to specific context if provided
        if ($contextSelector) {
            $crawler = $crawler->filter($contextSelector);
        }

        $crawler->filter('img')->each(function (Crawler $img) use (&$images) {
            $src = $img->attr('src') ?? $img->attr('data-src') ?? $img->attr('data-lazy-src');
            if ($src) {
                $images[] = [
                    'src' => $this->resolveUrl($src),
                    'alt' => $img->attr('alt') ?? '',
                    'title' => $img->attr('title') ?? '',
                ];
            }
        });

        // Also get Open Graph image
        $ogImage = $this->extractMetaContent('og:image');
        if ($ogImage && !in_array($ogImage, array_column($images, 'src'))) {
            array_unshift($images, [
                'src' => $this->resolveUrl($ogImage),
                'alt' => '',
                'title' => '',
                'is_og' => true,
            ]);
        }

        return $images;
    }

    /**
     * Extract all links
     */
    public function extractLinks(?string $contextSelector = null): array
    {
        if (!$this->crawler) {
            return [];
        }

        $links = [];
        $crawler = $this->crawler;

        if ($contextSelector) {
            $crawler = $crawler->filter($contextSelector);
        }

        $crawler->filter('a[href]')->each(function (Crawler $anchor) use (&$links) {
            $href = $anchor->attr('href');
            if ($href && !str_starts_with($href, '#') && !str_starts_with($href, 'javascript:')) {
                $links[] = [
                    'url' => $this->resolveUrl($href),
                    'text' => trim($anchor->text()),
                    'title' => $anchor->attr('title') ?? '',
                ];
            }
        });

        return $links;
    }

    /**
     * Extract article content with cleanup
     */
    public function extractArticleContent(?string $selector = null): array
    {
        $selector = $selector ?? implode(',', $this->defaultSelectors['content']);
        
        if (!$this->crawler) {
            return [
                'html' => '',
                'text' => '',
                'images' => [],
                'links' => [],
            ];
        }

        $content = $this->crawler->filter($selector);
        
        if ($content->count() === 0) {
            return [
                'html' => '',
                'text' => '',
                'images' => [],
                'links' => [],
            ];
        }

        $html = $content->first()->html();
        
        // Create a temp crawler for the content
        $tempCrawler = new Crawler($html, $this->baseUrl);
        
        // Remove unwanted elements
        $this->removeUnwantedElements($tempCrawler);
        
        return [
            'html' => $tempCrawler->html(),
            'text' => trim($tempCrawler->text()),
            'images' => $this->extractImagesFromCrawler($tempCrawler),
            'links' => $this->extractLinksFromCrawler($tempCrawler),
        ];
    }

    /**
     * Remove unwanted elements from content
     */
    private function removeUnwantedElements(Crawler $crawler): void
    {
        $unwantedSelectors = [
            'script',
            'style',
            'nav',
            'header',
            'footer',
            'aside',
            '.ad',
            '.advertisement',
            '.social-share',
            '.related-posts',
            '.comments',
            '.sidebar',
            '.navigation',
            '.menu',
        ];

        foreach ($unwantedSelectors as $selector) {
            $crawler->filter($selector)->each(function (Crawler $node) {
                foreach ($node as $n) {
                    $n->parentNode?->removeChild($n);
                }
            });
        }
    }

    /**
     * Extract images from crawler
     */
    private function extractImagesFromCrawler(Crawler $crawler): array
    {
        $images = [];
        
        $crawler->filter('img')->each(function (Crawler $img) use (&$images) {
            $src = $img->attr('src') ?? $img->attr('data-src');
            if ($src) {
                $images[] = $this->resolveUrl($src);
            }
        });
        
        return array_filter($images);
    }

    /**
     * Extract links from crawler
     */
    private function extractLinksFromCrawler(Crawler $crawler): array
    {
        $links = [];
        
        $crawler->filter('a[href]')->each(function (Crawler $anchor) use (&$links) {
            $href = $anchor->attr('href');
            if ($href && !str_starts_with($href, '#') && !str_starts_with($href, 'javascript:')) {
                $links[] = $this->resolveUrl($href);
            }
        });
        
        return array_filter($links);
    }

    /**
     * Extract list items (for article listings)
     */
    public function extractListItems(string $containerSelector, string $itemSelector): array
    {
        if (!$this->crawler) {
            return [];
        }

        $items = [];

        try {
            $container = $this->crawler->filter($containerSelector);
            
            if ($container->count() === 0) {
                return [];
            }

            $container->filter($itemSelector)->each(function (Crawler $item) use (&$items) {
                $items[] = [
                    'html' => $item->html(),
                    'text' => trim($item->text()),
                ];
            });
        } catch (\Exception $e) {
            return [];
        }

        return $items;
    }

    /**
     * Resolve relative URL to absolute
     */
    public function resolveUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        // Already absolute
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Protocol-relative
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (empty($this->baseUrl)) {
            return $url;
        }

        // Parse base URL
        $parsed = parse_url($this->baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';

        // Absolute path
        if (str_starts_with($url, '/')) {
            return $scheme . '://' . $host . $url;
        }

        // Relative path
        $dir = dirname($path);
        return $scheme . '://' . $host . '/' . ltrim($dir . '/' . $url, '/');
    }

    /**
     * Extract using custom CSS selector configuration
     */
    public function extractWithConfig(array $config): array
    {
        $result = [];

        foreach ($config as $field => $selectors) {
            if (is_array($selectors)) {
                $result[$field] = $this->extractTextMultiple($selectors);
            } else {
                $result[$field] = $this->extractText($selectors);
            }
        }

        return $result;
    }

    /**
     * Check if element exists
     */
    public function has(string $selector): bool
    {
        if (!$this->crawler) {
            return false;
        }

        return $this->crawler->filter($selector)->count() > 0;
    }

    /**
     * Get base URL
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Set default selectors
     */
    public function setDefaultSelectors(array $selectors): self
    {
        $this->defaultSelectors = array_merge($this->defaultSelectors, $selectors);
        return $this;
    }

    /**
     * Get default selectors
     */
    public function getDefaultSelectors(): array
    {
        return $this->defaultSelectors;
    }

    /**
     * Convert CSS selector to XPath (helper)
     */
    public static function cssToXPath(string $css): string
    {
        // Simple CSS to XPath conversion for basic selectors
        $css = trim($css);
        
        // Handle element selectors
        if (preg_match('/^([a-z0-9]+)$/i', $css)) {
            return "//{$css}";
        }
        
        // Handle class selectors
        if (preg_match('/^\.([a-z0-9_-]+)$/i', $css, $match)) {
            return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$match[1]} ')]";
        }
        
        // Handle ID selectors
        if (preg_match('/^#([a-z0-9_-]+)$/i', $css, $match)) {
            return "//*[@id='{$match[1]}']";
        }
        
        // Handle attribute selectors
        if (preg_match('/^\[([^\]]+)\]$/', $css, $match)) {
            return "//*[@{$match[1]}]";
        }
        
        // Default: return as is
        return $css;
    }
}
