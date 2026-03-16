<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * EnhancedScraperService.php
 * Advanced web scraping using Guzzle HTTP Client and Symfony DomCrawler
 * Supports multiple sources, content cleaning, and image downloading
 */
class EnhancedScraperService
{
    private Client $client;
    private string $userAgent;
    private int $timeout;
    private int $maxRedirects;
    private array $proxies;

    // CSS selectors for common content extraction
    private const SELECTORS = [
        'title' => [
            'h1',
            'article h1',
            '.post-title',
            '.entry-title',
            '.article-title',
            'head title'
        ],
        'content' => [
            'article',
            '.post-content',
            '.article-content',
            '.entry-content',
            '.content',
            'main',
            '.post-body',
            'article .content'
        ],
        'image' => [
            'meta[property="og:image"]',
            'meta[name="twitter:image"]',
            'article img',
            '.post-thumbnail img',
            '.featured-image img'
        ],
        // Prothom Alo specific selectors
        'prothom_list_articles' => [
            '.wide-story-card.xkXol.HLT9m', // Main story
            '.news_with_item.xkXol._004hA'  // Other stories
        ],
        'prothom_article_title' => [
            'h1.IiRps',
            'h1[data-title-0]'
        ],
        'prothom_article_content' => [
            '.story-element.story-element-text',
            '.storyCard.eyOoS .story-element-text'
        ],
        'prothom_article_author' => [
            '.author-name',
            '.contributor-name',
            'span[data-author-0]'
        ],
        'prothom_article_date' => [
            'time[datetime]',
            '.published-at',
            'time'
        ],
        // BD News 24 Bengali specific selectors
        'bdnews_list_articles' => [
            '.SubCat-wrapper',
            '.col-md-3.col-lg-3.col-12',
            '#data-wrapper .SubCat-wrapper'
        ],
        'bdnews_article_title' => [
            '.details-title h1',
            '.details-title h1:first-child',
            'h1'
        ],
        'bdnews_article_content' => [
            '#contentDetails',
            '.details-brief.dNewsDesc',
            '.details-brief'
        ],
        'bdnews_article_image' => [
            '.details-img picture img',
            '.details-img img',
            'meta[property="og:image"]'
        ],
        'bdnews_article_excerpt' => [
            '.details-title h2',
            'h2.shoulder-text'
        ],
        'bdnews_article_author' => [
            '.author-name-wrap .author',
            '.detail-author-name .author',
            '.author-container .author-name-wrap'
        ],
        'bdnews_article_date' => [
            '.pub-up .pub:first-child',
            '.pub-up span:first-child',
            '.detail-author-name .pub'
        ],
        // List page selectors
        'bdnews_list_title' => [
            '.SubcatList-detail h5 a',
            'h5 a',
            '.SubCat-wrapper h5 a'
        ],
        'bdnews_list_date' => [
            '.publish-time',
            'span.publish-time'
        ],
        'bdnews_list_image' => [
            '.SubCat-wrapper picture img',
            '.SubCat-wrapper img.img-fluid'
        ]
    ];

    public function __construct(array $config = [])
    {
        $this->userAgent = $config['user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $this->timeout = $config['timeout'] ?? 30;
        $this->maxRedirects = $config['max_redirects'] ?? 5;
        $this->proxies = array_values($config['proxies'] ?? []);

        $this->client = new Client([
            'timeout' => $this->timeout,
            'allow_redirects' => [
                'max' => $this->maxRedirects,
                'strict' => false,
                'track_redirects' => true
            ],
            'headers' => [
                'User-Agent' => $this->userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Cache-Control' => 'max-age=0'
            ],
            'verify' => true,
            'decode_content' => true
        ]);
    }

    /**
     * Execute GET request with optional proxy rotation
     */
    private function get(string $url)
    {
        $options = [];
        if (!empty($this->proxies)) {
            $proxy = $this->proxies[array_rand($this->proxies)];
            if (!empty($proxy)) {
                $options['proxy'] = $proxy;
            }
        }

        return $this->client->get($url, $options);
    }

    /**
     * Scrape a single URL
     */
    public function scrape(string $url): array
    {
        $url = trim($url);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['error' => 'Invalid URL provided'];
        }

        try {
            $response = $this->get($url);
            $html = (string)$response->getBody();
            $finalUrl = $url; // Use original URL as final URL

            return $this->parseHtml($html, $finalUrl);
        } catch (RequestException $e) {
            return ['error' => 'Failed to fetch URL: ' . $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => 'Scraping error: ' . $e->getMessage()];
        }
    }

    /**
     * Scrape multiple URLs
     */
    public function scrapeMultiple(array $urls, int $maxConcurrent = 3): array
    {
        $results = [];

        foreach (array_slice($urls, 0, $maxConcurrent * 2) as $url) {
            $results[] = $this->scrape($url);
        }

        return $results;
    }

    /**
     * Extract content using multiple selector strategies
     */
    private function extractBySelectors(Crawler $crawler, array $selectors): ?string
    {
        foreach ($selectors as $selector) {
            try {
                $element = $crawler->filter($selector);
                if ($element->count() > 0) {
                    $text = $element->first()->text();
                    if (!empty(trim($text))) {
                        return trim($text);
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return null;
    }

    /**
     * Extract all images from content
     */
    private function extractImages(Crawler $crawler, string $baseUrl): array
    {
        $images = [];

        // Extract from meta tags
        $crawler->filter('meta[property="og:image"]')->each(function ($node) use (&$images, $baseUrl) {
            $content = $node->attr('content');
            if ($content) {
                $images[] = $this->resolveUrl($content, $baseUrl);
            }
        });

        // Extract from content images
        $crawler->filter('article img, .post-content img, .entry-content img')->each(function ($node) use (&$images, $baseUrl) {
            $src = $node->attr('src') ?: $node->attr('data-src');
            if ($src) {
                $images[] = $this->resolveUrl($src, $baseUrl);
            }
        });

        return array_unique(array_filter($images));
    }

    /**
     * Parse HTML and extract content
     */
    private function parseHtml(string $html, string $baseUrl): array
    {
        $crawler = new Crawler($html, $baseUrl);

        // Title
        $title = $this->extractBySelectors($crawler, self::SELECTORS['title']) ?? '(No title found)';

        // Description
        $description = '';
        $crawler->filter('meta[name="description"], meta[property="og:description"]')->each(function ($node) use (&$description) {
            $content = $node->attr('content');
            if ($content && empty($description)) {
                $description = $content;
            }
        });

        // Main image
        $image = '';
        $crawler->filter(implode(',', self::SELECTORS['image']))->each(function ($node) use (&$image) {
            $content = $node->attr('content') ?: $node->attr('src');
            if ($content && empty($image)) {
                $image = $content;
            }
        });

        // Content body
        $content = '';
        $crawler->filter(implode(',', self::SELECTORS['content']))->each(function ($node) use (&$content) {
            if (empty($content)) {
                $content = $node->html();
            }
        });

        // Extract links
        $links = [];
        $crawler->filter('a[href]')->slice(0, 10)->each(function ($node) use (&$links, $baseUrl) {
            $href = $node->attr('href');
            if ($href && !str_starts_with($href, '#') && !str_starts_with($href, 'javascript:')) {
                $links[] = $this->resolveUrl($href, $baseUrl);
            }
        });

        // Extract all images
        $images = $this->extractImages($crawler, $baseUrl);

        return [
            'url' => $baseUrl,
            'title' => $title,
            'description' => trim($description),
            'content' => $content,
            'image' => $this->resolveUrl($image, $baseUrl),
            'images' => $images,
            'links' => $links,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Resolve relative URLs to absolute
     */
    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (empty($url)) {
            return '';
        }

        // Already absolute
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Protocol-relative URL
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        // Parse base URL
        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';

        // Absolute path
        if (str_starts_with($url, '/')) {
            return $scheme . '://' . $host . $url;
        }

        // Relative path
        $dir = dirname($path);
        return $scheme . '://' . $host . '/' . ltrim($dir . '/' . $url, '/');
    }

    /**
     * Scrape Prothom Alo article list page
     */
    public function scrapeProthomAloList(string $url): array
    {
        try {
            $response = $this->get($url);
            $html = (string)$response->getBody();
            $finalUrl = $url;

            $crawler = new Crawler($html, $finalUrl);

            $articles = [];

            // Scrape main story
            $mainStory = $crawler->filter('.wide-story-card');
            if ($mainStory->count() > 0) {
                $title = $mainStory->filter('h3 a')->text();
                $href = $mainStory->filter('h3 a')->attr('href');
                if ($title && $href) {
                    $articles[] = [
                        'title' => trim($title),
                        'url' => $this->resolveUrl($href, $finalUrl),
                        'is_main' => true
                    ];
                }
            }

            // Scrape other stories
            $crawler->filter('.news_with_item')->each(function ($node) use (&$articles, $finalUrl) {
                $title = $node->filter('h3 a')->text();
                $href = $node->filter('h3 a')->attr('href');
                if ($title && $href) {
                    $articles[] = [
                        'title' => trim($title),
                        'url' => $this->resolveUrl($href, $finalUrl),
                        'is_main' => false
                    ];
                }
            });

            return [
                'success' => true,
                'articles' => $articles,
                'count' => count($articles),
                'timestamp' => date('Y-m-d H:i:s'),
                'debug_html' => $html // For debugging
            ];
        } catch (RequestException $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch list: ' . $e->getMessage(),
                'articles' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Scrape individual Prothom Alo article
     */
    public function scrapeProthomAloArticle(string $url): array
    {
        try {
            $response = $this->get($url);
            $html = (string)$response->getBody();
            $finalUrl = $url;

            $crawler = new Crawler($html, $finalUrl);

            // Title
            $title = '';
            $crawler->filter(implode(',', self::SELECTORS['prothom_article_title']))->each(function ($node) use (&$title) {
                if (empty($title)) {
                    $title = trim($node->text());
                }
            });

            // Content
            $content = '';
            $crawler->filter(implode(',', self::SELECTORS['prothom_article_content']))->each(function ($node) use (&$content) {
                if (empty($content)) {
                    $content .= $node->html() . "\n";
                }
            });

            // Author
            $author = '';
            $crawler->filter(implode(',', self::SELECTORS['prothom_article_author']))->each(function ($node) use (&$author) {
                if (empty($author)) {
                    $author = trim($node->text());
                }
            });

            // Date
            $date = '';
            $crawler->filter(implode(',', self::SELECTORS['prothom_article_date']))->each(function ($node) use (&$date) {
                if (empty($date)) {
                    $datetime = $node->attr('datetime') ?: $node->text();
                    $date = trim($datetime);
                }
            });

            // Images
            $images = $this->extractImages($crawler, $finalUrl);

            // Featured image
            $featuredImage = '';
            $crawler->filter('.Td4Ec img.qt-image')->each(function ($node) use (&$featuredImage, $finalUrl) {
                if (empty($featuredImage)) {
                    $src = $node->attr('src') ?: $node->attr('data-src');
                    if ($src) {
                        $featuredImage = $this->resolveUrl($src, $finalUrl);
                    }
                }
            });

            return [
                'success' => true,
                'url' => $finalUrl,
                'title' => $title ?: '(No title found)',
                'content' => $content,
                'author' => $author,
                'date' => $date,
                'featured_image' => $featuredImage,
                'images' => $images,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (RequestException $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch article: ' . $e->getMessage(),
                'url' => $url
            ];
        }
    }

    /**
     * Scrape Prothom Alo list and all articles
     */
    public function scrapeProthomAloComplete(string $listUrl, int $maxArticles = 10): array
    {
        $result = [
            'list_scraped' => false,
            'articles_scraped' => 0,
            'articles' => [],
            'errors' => []
        ];

        // Scrape list
        $listData = $this->scrapeProthomAloList($listUrl);
        if (!$listData['success']) {
            $result['errors'][] = 'Failed to scrape list: ' . ($listData['error'] ?? 'Unknown error');
            return $result;
        }
        
        try {
            $response = $this->get($url);
            $html = (string)$response->getBody();
            $finalUrl = $url;

            $crawler = new Crawler($html, $finalUrl);

            $articles = [];

            // Try different selectors for article items
            $selectors = [
                '.SubCat-wrapper',
                '#data-wrapper .col-md-3',
                '#data-wrapper .SubCat-wrapper',
                '.col-md-3.col-lg-3.col-12'
            ];

            $itemsFound = false;
            foreach ($selectors as $selector) {
                $items = $crawler->filter($selector);
                if ($items->count() > 0) {
                    $items->each(function ($node) use (&$articles, $finalUrl) {
                        $nodeCrawler = new Crawler($node);
                        
                        // Try to find the article link
                        $linkNode = $nodeCrawler->filter('a');
                        if ($linkNode->count() === 0) {
                            return;
                        }

                        $href = $linkNode->first()->attr('href');
                        if (empty($href) || !filter_var($href, FILTER_VALIDATE_URL)) {
                            // Try to resolve relative URL
                            $href = $this->resolveUrl($href, $finalUrl);
                        }
                        if (empty($href)) {
                            return;
                        }

                        // Extract title - try h5 first
                        $title = '';
                        $titleNode = $nodeCrawler->filter('h5');
                        if ($titleNode->count() > 0) {
                            $title = trim($titleNode->first()->text());
                        }
                        
                        // Try SubcatList-detail h5 if not found
                        if (empty($title)) {
                            $titleNode = $nodeCrawler->filter('.SubcatList-detail h5');
                            if ($titleNode->count() > 0) {
                                $title = trim($titleNode->first()->text());
                            }
                        }

                        if (empty($title)) {
                            return;
                        }

                        // Extract date
                        $date = '';
                        $dateNode = $nodeCrawler->filter('.publish-time, span.publish-time');
                        if ($dateNode->count() > 0) {
                            $date = trim($dateNode->first()->text());
                        }

                        // Extract image
                        $image = '';
                        $imgNode = $nodeCrawler->filter('picture img, img.img-fluid');
                        if ($imgNode->count() > 0) {
                            $image = $imgNode->first()->attr('src') ?: $imgNode->first()->attr('data-src');
                            if ($image) {
                                $image = $this->resolveUrl($image, $finalUrl);
                            }
                        }

                        // Extract category
                        $category = '';
                        $catNode = $nodeCrawler->filter('.category-arch, span.category-arch');
                        if ($catNode->count() > 0) {
                            $category = trim($catNode->first()->text());
                        }

                        $articles[] = [
                            'title' => $title,
                            'url' => $href,
                            'date' => $date,
                            'image' => $image,
                            'category' => $category
                        ];
                    });
                    
                    if (!empty($articles)) {
                        $itemsFound = true;
                        break;
                    }
                }
            }

            return [
                'success' => true,
                'articles' => $articles,
                'count' => count($articles),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (RequestException $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch list: ' . $e->getMessage(),
                'articles' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Scrape BD News 24 Bengali article list page
     */
    public function scrapeBdnewsList(string $url): array
    {
        try {
            $response = $this->get($url);
            $html = (string)$response->getBody();
            $finalUrl = $url;

            $crawler = new Crawler($html, $finalUrl);

            $articles = [];

            // Try different selectors for article items
            $selectors = [
                '.SubCat-wrapper',
                '#data-wrapper .col-md-3',
                '#data-wrapper .SubCat-wrapper',
                '.col-md-3.col-lg-3.col-12'
            ];

            $itemsFound = false;
            foreach ($selectors as $selector) {
                $items = $crawler->filter($selector);
                if ($items->count() > 0) {
                    $items->each(function ($node) use (&$articles, $finalUrl) {
                        $nodeCrawler = new Crawler($node);
                        
                        // Try to find the article link
                        $linkNode = $nodeCrawler->filter('a');
                        if ($linkNode->count() === 0) {
                            return;
                        }

                        $href = $linkNode->first()->attr('href');
                        if (empty($href) || !filter_var($href, FILTER_VALIDATE_URL)) {
                            // Try to resolve relative URL
                            $href = $this->resolveUrl($href, $finalUrl);
                        }
                        if (empty($href)) {
                            return;
                        }

                        // Extract title - try h5 first
                        $title = '';
                        $titleNode = $nodeCrawler->filter('h5');
                        if ($titleNode->count() > 0) {
                            $title = trim($titleNode->first()->text());
                        }
                        
                        // Try SubcatList-detail h5 if not found
                        if (empty($title)) {
                            $titleNode = $nodeCrawler->filter('.SubcatList-detail h5');
                            if ($titleNode->count() > 0) {
                                $title = trim($titleNode->first()->text());
                            }
                        }

                        if (empty($title)) {
                            return;
                        }

                        // Extract date
                        $date = '';
                        $dateNode = $nodeCrawler->filter('.publish-time, span.publish-time');
                        if ($dateNode->count() > 0) {
                            $date = trim($dateNode->first()->text());
                        }

                        // Extract image
                        $image = '';
                        $imgNode = $nodeCrawler->filter('picture img, img.img-fluid');
                        if ($imgNode->count() > 0) {
                            $image = $imgNode->first()->attr('src') ?: $imgNode->first()->attr('data-src');
                            if ($image) {
                                $image = $this->resolveUrl($image, $finalUrl);
                            }
                        }

                        // Extract category
                        $category = '';
                        $catNode = $nodeCrawler->filter('.category-arch, span.category-arch');
                        if ($catNode->count() > 0) {
                            $category = trim($catNode->first()->text());
                        }

                        $articles[] = [
                            'title' => $title,
                            'url' => $href,
                            'date' => $date,
                            'image' => $image,
                            'category' => $category
                        ];
                    });
                    
                    if (!empty($articles)) {
                        $itemsFound = true;
                        break;
                    }
                }
            }

            return [
                'success' => true,
                'articles' => $articles,
                'count' => count($articles),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (RequestException $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch list: ' . $e->getMessage(),
                'articles' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Scrape individual BD News 24 article
     */
    public function scrapeBdnewsArticle(string $url): array
    {
        try {
            $response = $this->get($url);
            $html = (string)$response->getBody();
            $finalUrl = $url;

            $crawler = new Crawler($html, $finalUrl);

            // Title
            $title = '';
            $titleSelectors = ['.details-title h1', 'h1'];
            foreach ($titleSelectors as $selector) {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    $title = trim($nodes->first()->text());
                    if (!empty($title)) break;
                }
            }

            // Description/Excerpt
            $excerpt = '';
            $excerptSelectors = ['.details-title h2', 'h2.shoulder-text', 'meta[name="description"]'];
            foreach ($excerptSelectors as $selector) {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    if ($selector === 'meta[name="description"]') {
                        $excerpt = trim($nodes->first()->attr('content'));
                    } else {
                        $excerpt = trim($nodes->first()->text());
                    }
                    if (!empty($excerpt)) break;
                }
            }

            // Content
            $content = '';
            $contentSelectors = ['#contentDetails', '.details-brief.dNewsDesc', '.details-brief'];
            foreach ($contentSelectors as $selector) {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    $content = $nodes->first()->html();
                    if (!empty($content)) break;
                }
            }

            // Author
            $author = '';
            $authorSelectors = ['.author-name-wrap .author', '.detail-author-name .author', '.author-container .author-name-wrap'];
            foreach ($authorSelectors as $selector) {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    $author = trim($nodes->first()->text());
                    if (!empty($author)) break;
                }
            }

            // Date
            $date = '';
            $dateSelectors = ['.pub-up .pub', '.pub-up span:first-child', '.pub-up p span:first-child'];
            foreach ($dateSelectors as $selector) {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    $date = trim($nodes->first()->text());
                    // Remove "Published :" prefix if exists
                    $date = str_replace(['Published :', 'Updated :', 'Published', 'Updated'], '', $date);
                    $date = trim($date);
                    if (!empty($date)) break;
                }
            }

            // Featured Image
            $featuredImage = '';
            $imageSelectors = ['.details-img picture img', '.details-img img'];
            foreach ($imageSelectors as $selector) {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    $featuredImage = $nodes->first()->attr('src') ?: $nodes->first()->attr('data-src');
                    if ($featuredImage) {
                        $featuredImage = $this->resolveUrl($featuredImage, $finalUrl);
                        if (!empty($featuredImage)) break;
                    }
                }
            }

            // If no image found, try og:image
            if (empty($featuredImage)) {
                $ogImage = $crawler->filter('meta[property="og:image"]');
                if ($ogImage->count() > 0) {
                    $featuredImage = $ogImage->first()->attr('content');
                }
            }

            // Extract all images from content
            $images = $this->extractImages($crawler, $finalUrl);

            // Category from breadcrumb
            $category = '';
            $catNodes = $crawler->filter('.breadcrump ul li a');
            if ($catNodes->count() > 1) {
                // Second li is usually the category
                $category = trim($catNodes->eq(1)->text());
            }

            return [
                'success' => true,
                'url' => $finalUrl,
                'title' => $title ?: '(No title found)',
                'excerpt' => $excerpt,
                'content' => $content,
                'author' => $author,
                'date' => $date,
                'featured_image' => $featuredImage,
                'images' => $images,
                'category' => $category,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (RequestException $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch article: ' . $e->getMessage(),
                'url' => $url
            ];
        }
    }
}
