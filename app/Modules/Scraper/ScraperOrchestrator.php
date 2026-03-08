<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * ScraperOrchestrator.php
 * Main orchestrator that coordinates all scraper components
 * Handles the complete scraping workflow
 */
class ScraperOrchestrator
{
    private HttpClientService $httpClient;
    private SourceConfigManager $sourceManager;
    private ArticleScraper $articleScraper;
    private PaginationHandler $paginationHandler;
    private ?EnhancedDuplicateChecker $duplicateChecker = null;
    private ?EnhancedImageDownloader $imageDownloader = null;
    private ?ContentCleanerService $contentCleaner = null;

    private ?\mysqli $mysqli = null;
    private array $config = [];
    private array $stats = [
        'articles_scraped' => 0,
        'articles_saved' => 0,
        'duplicates_skipped' => 0,
        'errors' => 0,
        'images_downloaded' => 0,
    ];

    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'timeout' => 30,
            'max_pages' => 10,
            'max_retries' => 3,
            'check_duplicates' => true,
            'download_images' => true,
            'clean_content' => true,
            'auto_publish' => false,
            'proxy_enabled' => false,
            'proxy_config' => [],
        ];

        // Initialize HTTP client
        $this->httpClient = new HttpClientService($this->config);

        // Initialize source manager
        $this->sourceManager = new SourceConfigManager($this->config['source_config'] ?? []);

        // Initialize article scraper
        $this->articleScraper = new ArticleScraper($this->httpClient, $this->sourceManager);

        // Initialize pagination handler
        $this->paginationHandler = new PaginationHandler($this->httpClient, $this->config['max_pages']);
    }

    /**
     * Set database connection
     */
    public function setDatabase(\mysqli $mysqli): self
    {
        $this->mysqli = $mysqli;
        $this->sourceManager->setDatabase($mysqli);

        if ($this->config['check_duplicates']) {
            $this->duplicateChecker = new EnhancedDuplicateChecker($mysqli);
        }

        return $this;
    }

    /**
     * Set image downloader
     */
    public function setImageDownloader(EnhancedImageDownloader $downloader): self
    {
        $this->imageDownloader = $downloader;
        return $this;
    }

    /**
     * Set content cleaner
     */
    public function setContentCleaner(ContentCleanerService $cleaner): self
    {
        $this->contentCleaner = $cleaner;
        return $this;
    }

    /**
     * Load sources from database
     */
    public function loadSources(): array
    {
        return $this->sourceManager->loadFromDatabase();
    }

    /**
     * Scrape articles from a source
     */
    public function scrapeSource(int $sourceId, ?array $options = []): array
    {
        $options += [
            'max_articles' => 10,
            'scrape_content' => true,
            'check_duplicates' => $this->config['check_duplicates'],
            'download_images' => $this->config['download_images'],
        ];

        $source = $this->sourceManager->getSourceById($sourceId);

        if (!$source) {
            return [
                'success' => false,
                'error' => 'Source not found',
            ];
        }

        $results = [
            'source_id' => $sourceId,
            'source_name' => $source['name'],
            'source_url' => $source['url'],
            'articles' => [],
            'saved' => 0,
            'duplicates' => 0,
            'errors' => 0,
        ];

        try {
            // Get article list
            $type = $source['type'] ?? 'html';

            // Extract custom selectors from source
            $selectors = [];
            if (!empty($source['selector_list_container'])) {
                $selectors['list_container'] = $source['selector_list_container'];
            }
            if (!empty($source['selector_list_item'])) {
                $selectors['list_item'] = $source['selector_list_item'];
            }
            if (!empty($source['selector_list_title'])) {
                $selectors['list_title'] = $source['selector_list_title'];
            }
            if (!empty($source['selector_list_link'])) {
                $selectors['list_link'] = $source['selector_list_link'];
            }
            if (!empty($source['selector_list_date'])) {
                $selectors['list_date'] = $source['selector_list_date'];
            }
            if (!empty($source['selector_list_image'])) {
                $selectors['list_image'] = $source['selector_list_image'];
            }
            if (!empty($source['selector_title'])) {
                $selectors['title'] = $source['selector_title'];
            }
            if (!empty($source['selector_content'])) {
                $selectors['content'] = $source['selector_content'];
            }
            if (!empty($source['selector_image'])) {
                $selectors['image'] = $source['selector_image'];
            }
            if (!empty($source['selector_excerpt'])) {
                $selectors['excerpt'] = $source['selector_excerpt'];
            }
            if (!empty($source['selector_date'])) {
                $selectors['date'] = $source['selector_date'];
            }
            if (!empty($source['selector_author'])) {
                $selectors['author'] = $source['selector_author'];
            }

            if ($type === 'rss') {
                $listResult = $this->articleScraper->scrapeRss($source['url']);
            } else {
                $listResult = $this->articleScraper->scrapeList($source['url'], $selectors);
            }

            if (!$listResult['success']) {
                return array_merge($results, [
                    'success' => false,
                    'error' => $listResult['error'],
                ]);
            }

            // Process each article
            $articles = $listResult['articles'] ?? [];
            $maxArticles = min($options['max_articles'], count($articles));

            for ($i = 0; $i < $maxArticles; $i++) {
                $articleUrl = $articles[$i]['url'] ?? '';

                if (empty($articleUrl)) {
                    continue;
                }

                // Scrape individual article
                $articleResult = $this->processArticle($articleUrl, $source, $options);

                $results['articles'][] = $articleResult;

                if ($articleResult['saved']) {
                    $results['saved']++;
                } elseif ($articleResult['duplicate']) {
                    $results['duplicates']++;
                } elseif (!$articleResult['success']) {
                    $results['errors']++;
                }

                // Respect rate limits
                usleep(500000); // 0.5 second delay between articles
            }

            // Update source last fetch time
            $this->sourceManager->updateLastFetch($source['url']);

            return array_merge($results, ['success' => true]);
        } catch (\Exception $e) {
            return array_merge($results, [
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process a single article
     */
    private function processArticle(string $url, array $source, array $options): array
    {
        $result = [
            'url' => $url,
            'success' => false,
            'saved' => false,
            'duplicate' => false,
            'error' => null,
        ];

        try {
            // Build selectors array from source for detail page
            $detailSelectors = [];
            if (!empty($source['selector_title'])) {
                $detailSelectors['title'] = $source['selector_title'];
            }
            if (!empty($source['selector_content'])) {
                $detailSelectors['content'] = $source['selector_content'];
            }
            if (!empty($source['selector_image'])) {
                $detailSelectors['image'] = $source['selector_image'];
            }
            if (!empty($source['selector_excerpt'])) {
                $detailSelectors['excerpt'] = $source['selector_excerpt'];
            }
            if (!empty($source['selector_date'])) {
                $detailSelectors['date'] = $source['selector_date'];
            }
            if (!empty($source['selector_author'])) {
                $detailSelectors['author'] = $source['selector_author'];
            }

            // Scrape article content with custom selectors
            $articleData = $this->articleScraper->scrape($url, $detailSelectors);

            if (!$articleData['success']) {
                $result['error'] = $articleData['error'];
                return $result;
            }

            // Prepare article data
            $article = $this->prepareArticleData($articleData, $source);

            // Check for duplicates
            if ($options['check_duplicates'] && $this->duplicateChecker) {
                $duplicateCheck = $this->duplicateChecker->checkDuplicate([
                    'url' => $url,
                    'title' => $article['title'],
                    'content' => $article['content'],
                ]);

                if ($duplicateCheck['is_duplicate']) {
                    $result['duplicate'] = true;
                    $result['reason'] = $duplicateCheck['reason'];
                    $this->stats['duplicates_skipped']++;
                    return $result;
                }
            }

            // Download images
            if ($options['download_images'] && $this->imageDownloader) {
                $article = $this->downloadArticleImages($article, $url);
            }

            // Clean content
            if ($this->contentCleaner) {
                $article['content'] = $this->contentCleaner->clean($article['content']);
            }

            // Save to database
            $articleId = $this->saveArticle($article, $source);

            if ($articleId) {
                $result['success'] = true;
                $result['saved'] = true;
                $result['article_id'] = $articleId;
                $this->stats['articles_saved']++;

                // Save hash for duplicate detection
                if ($this->duplicateChecker) {
                    $this->duplicateChecker->saveHash($articleId, $article['content']);
                }
            }

            $this->stats['articles_scraped']++;
            return $result;
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $this->stats['errors']++;
            return $result;
        }
    }

    /**
     * Prepare article data for saving
     */
    private function prepareArticleData(array $data, array $source): array
    {
        return [
            'title' => $data['title'] ?? 'Untitled',
            'content' => $data['content'] ?? '',
            'excerpt' => $data['excerpt'] ?? substr(strip_tags($data['content'] ?? ''), 0, 200),
            'author' => $data['author'] ?? '',
            'url' => $data['url'] ?? '',
            'original_url' => $data['url'] ?? '',
            'source_id' => $source['id'] ?? null,
            'category_id' => $source['category_id'] ?? null,
            'featured_image' => $data['featured_image'] ?? '',
            'published_date' => $data['date'] ?? date('Y-m-d H:i:s'),
            'status' => $this->config['auto_publish'] ? 'published' : 'draft',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Download article images
     */
    private function downloadArticleImages(array $article, string $url): array
    {
        $images = [];

        // Download featured image
        if (!empty($article['featured_image'])) {
            $featuredId = $this->imageDownloader->download($article['featured_image']);
            if ($featuredId) {
                $article['featured_image'] = $featuredId;
                $this->stats['images_downloaded']++;
            }
        }

        return $article;
    }

    /**
     * Save article to database
     */
    private function saveArticle(array $article, array $source): ?int
    {
        if (!$this->mysqli) {
            return null;
        }

        $stmt = $this->mysqli->prepare("
            INSERT INTO autocontent_articles (
                title, content, excerpt, author, url, original_url,
                source_id, category_id, featured_image, published_date,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            'ssssssisss',
            $article['title'],
            $article['content'],
            $article['excerpt'],
            $article['author'],
            $article['url'],
            $article['original_url'],
            $article['source_id'],
            $article['category_id'],
            $article['featured_image'],
            $article['published_date'],
            $article['status']
        );

        $result = $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();

        return $result ? (int)$insertId : null;
    }

    /**
     * Scrape multiple sources
     */
    public function scrapeAllSources(?array $options = []): array
    {
        $options += [
            'max_articles_per_source' => 10,
        ];

        // Load sources from database
        $sources = $this->sourceManager->loadFromDatabase();
        $activeSources = $this->sourceManager->getActiveSources();

        $results = [
            'total_sources' => count($sources),
            'sources_processed' => 0,
            'sources' => [],
        ];

        foreach ($activeSources as $source) {
            $sourceResult = $this->scrapeSource($source['id'], $options);
            $results['sources'][] = $sourceResult;
            $results['sources_processed']++;
        }

        return $results;
    }

    /**
     * Scrape URL directly
     */
    public function scrapeUrl(string $url, ?array $options = []): array
    {
        $options += [
            'check_duplicates' => $this->config['check_duplicates'],
            'download_images' => $this->config['download_images'],
        ];

        $source = [
            'id' => null,
            'category_id' => null,
            'name' => 'Direct URL',
        ];

        return $this->processArticle($url, $source, $options);
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'http_stats' => $this->httpClient->getStats(),
            'source_stats' => $this->sourceManager->getStats(),
        ]);
    }

    /**
     * Reset statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'articles_scraped' => 0,
            'articles_saved' => 0,
            'duplicates_skipped' => 0,
            'errors' => 0,
            'images_downloaded' => 0,
        ];

        $this->httpClient->resetStats();
    }

    /**
     * Get HTTP client
     */
    public function getHttpClient(): HttpClientService
    {
        return $this->httpClient;
    }

    /**
     * Get source manager
     */
    public function getSourceManager(): SourceConfigManager
    {
        return $this->sourceManager;
    }

    /**
     * Get article scraper
     */
    public function getArticleScraper(): ArticleScraper
    {
        return $this->articleScraper;
    }

    /**
     * Get pagination handler
     */
    public function getPaginationHandler(): PaginationHandler
    {
        return $this->paginationHandler;
    }

    /**
     * Set proxy configuration
     */
    public function setProxy(array $proxies): self
    {
        $this->httpClient->setProxy($proxies);
        $this->config['proxy_enabled'] = true;
        return $this;
    }

    /**
     * Set reverse proxy URL (single proxy server)
     * Usage: setReverseProxy('http://proxy:port') or setReverseProxy('http://user:pass@proxy:port')
     */
    public function setReverseProxy(string $proxyUrl): self
    {
        $this->httpClient->setReverseProxy($proxyUrl);
        $this->config['proxy_enabled'] = true;
        return $this;
    }

    /**
     * Set delay range
     */
    public function setDelayRange(int $min, int $max): self
    {
        $this->httpClient->setDelayRange($min, $max);
        return $this;
    }

    /**
     * Enable/disable duplicate checking
     */
    public function setDuplicateChecking(bool $enabled): self
    {
        $this->config['check_duplicates'] = $enabled;
        return $this;
    }

    /**
     * Enable/disable image downloading
     */
    public function setImageDownloading(bool $enabled): self
    {
        $this->config['download_images'] = $enabled;
        return $this;
    }

    /**
     * Add custom source configuration
     */
    public function addSource(array $source): self
    {
        $this->sourceManager->addSource($source);
        return $this;
    }

    /**
     * Get sources needing fetch
     */
    public function getSourcesNeedingFetch(): array
    {
        return $this->sourceManager->getSourcesNeedingFetch();
    }

    /**
     * Create source in database
     */
    public function createSource(array $data): ?int
    {
        return $this->sourceManager->createSource($data);
    }

    /**
     * Update source in database
     */
    public function updateSource(int $id, array $data): bool
    {
        return $this->sourceManager->updateSource($id, $data);
    }

    /**
     * Delete source from database
     */
    public function deleteSource(int $id): bool
    {
        return $this->sourceManager->deleteSource($id);
    }
}
