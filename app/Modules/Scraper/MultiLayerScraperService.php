<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * MultiLayerScraperService.php
 * Implements a 5-step scraping pipeline:
 * 
 * STEP 1: Fetch LIST PAGE - Get the source URL HTML
 * STEP 2: Extract ARTICLE LINKS - Parse HTML for article links
 * STEP 3: Loop article links - Iterate through each link
 * STEP 4: Fetch ARTICLE PAGE - Fetch each individual article
 * STEP 5: Extract title/content/image/date - Parse article content
 */

use App\Models\AutoContentModel;
use Exception;
use DOMDocument;
use DOMXPath;

class MultiLayerScraperService
{
    private $mysqli;
    private $duplicateChecker;
    private $contentCleaner;
    private $imageDownloader;
    private $requestDelay = 2;
    private $maxArticles = 10;
    private $debug = false;

    private $pipelineStatus = [
        'step' => 0,
        'step_name' => '',
        'total_links_found' => 0,
        'articles_collected' => 0,
        'errors' => [],
        'warnings' => []
    ];

    public function __construct($mysqli = null)
    {
        $this->mysqli = $mysqli;

        if ($mysqli) {
            require_once __DIR__ . '/ContentCleanerService.php';
            require_once __DIR__ . '/DuplicateCheckerService.php';
            require_once __DIR__ . '/ImageDownloaderService.php';

            $this->duplicateChecker = new DuplicateCheckerService($mysqli);
            $this->contentCleaner = new ContentCleanerService();
            $this->imageDownloader = new ImageDownloaderService();
        }
    }

    public function setRequestDelay(int $seconds): self
    {
        $this->requestDelay = max(1, $seconds);
        return $this;
    }

    public function setMaxArticles(int $max): self
    {
        $this->maxArticles = max(1, $max);
        return $this;
    }

    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    public function getPipelineStatus(): array
    {
        return $this->pipelineStatus;
    }

    /**
     * Main entry point - Run full 5-step pipeline
     */
    public function runPipeline(array $source): array
    {
        $this->resetPipelineStatus();
        $this->pipelineStatus['step_name'] = 'Initializing';

        $result = [
            'success' => false,
            'source_id' => $source['id'] ?? 0,
            'source_name' => $source['name'] ?? 'Unknown',
            'source_url' => $source['url'] ?? '',
            'steps_completed' => [],
            'articles_collected' => 0,
            'errors' => [],
            'warnings' => []
        ];

        try {
            // STEP 1: Fetch LIST PAGE
            $this->updateStatus(1, 'Fetching List Page');
            $html = $this->fetchListPage($source['url']);

            if (empty($html)) {
                throw new Exception('Failed to fetch list page - empty response');
            }
            $result['steps_completed'][] = 'fetch_list_page';

            // STEP 2: Extract ARTICLE LINKS
            $this->updateStatus(2, 'Extracting Article Links');
            $articleLinks = $this->extractArticleLinks($html, $source);
            $this->pipelineStatus['total_links_found'] = count($articleLinks);

            if (empty($articleLinks)) {
                $result['warnings'][] = 'No article links found on list page';
                $result['steps_completed'][] = 'extract_links';
                return $result;
            }
            $result['steps_completed'][] = 'extract_links';

            // STEP 3, 4, 5: Loop links, Fetch pages, Extract content
            $this->updateStatus(3, 'Processing Articles');
            $collected = $this->loopAndExtractArticles($articleLinks, $source);
            $this->pipelineStatus['articles_collected'] = $collected;
            $result['articles_collected'] = $collected;
            $result['steps_completed'][] = 'loop_and_extract';

            $result['success'] = $collected > 0;
            $result['errors'] = $this->pipelineStatus['errors'];
            $result['warnings'] = $this->pipelineStatus['warnings'];
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $this->pipelineStatus['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * STEP 1: Fetch LIST PAGE
     */
    public function fetchListPage(string $url): string
    {
        $this->log("Fetching list page: $url");

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_ENCODING => '',
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: $httpCode");
        }

        if (empty($html)) {
            throw new Exception('Empty response from server');
        }

        $this->log("Fetched " . strlen($html) . " bytes");
        return $html;
    }

    /**
     * STEP 2: Extract ARTICLE LINKS
     */
    public function extractArticleLinks(string $html, array $source): array
    {
        $links = [];

        $listContainer = $source['selector_list_container'] ?? '';
        $listItem = $source['selector_list_item'] ?? '';
        $listLink = $source['selector_list_link'] ?? $source['selector_list_title'] ?? '';

        $this->log("Using selectors - container: $listContainer, item: $listItem, link: $listLink");

        try {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8">' . $html);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);

            if (!empty($listContainer) || !empty($listItem)) {
                $items = [];

                if (!empty($listContainer)) {
                    $containers = $xpath->query($this->cssToXPath($listContainer));
                    foreach ($containers as $container) {
                        if (!empty($listItem)) {
                            $foundItems = $xpath->query($this->cssToXPath($listItem), $container);
                        } else {
                            $foundItems = $xpath->query('.//a', $container);
                        }
                        foreach ($foundItems as $item) {
                            $items[] = $item;
                        }
                    }
                } elseif (!empty($listItem)) {
                    $foundItems = $xpath->query($this->cssToXPath($listItem));
                    foreach ($foundItems as $item) {
                        $items[] = $item;
                    }
                }

                foreach ($items as $item) {
                    $link = '';

                    if (!empty($listLink)) {
                        $linkNodes = $xpath->query($this->cssToXPath($listLink), $item);
                        if ($linkNodes && $linkNodes->length > 0) {
                            $link = $linkNodes->item(0)->getAttribute('href');
                        }
                    }

                    if (empty($link)) {
                        $link = $item->getAttribute('href');
                    }

                    if (empty($link) && $item->nodeName === 'a') {
                        $link = $item->getAttribute('href');
                    }

                    if (!empty($link)) {
                        $absoluteUrl = $this->makeAbsoluteUrl($link, $source['url']);
                        if ($this->isValidArticleUrl($absoluteUrl)) {
                            $links[] = $absoluteUrl;
                        }
                    }
                }
            } else {
                $allLinks = $xpath->query('//a[@href]');
                foreach ($allLinks as $link) {
                    $href = $link->getAttribute('href');
                    $absoluteUrl = $this->makeAbsoluteUrl($href, $source['url']);

                    if ($this->isValidArticleUrl($absoluteUrl)) {
                        $links[] = $absoluteUrl;
                    }
                }
            }

            $links = array_values(array_unique($links));
            $links = array_slice($links, 0, $this->maxArticles);
        } catch (Exception $e) {
            $this->log("Error extracting links: " . $e->getMessage());
            $this->pipelineStatus['errors'][] = "Link extraction error: " . $e->getMessage();
        }

        $this->log("Found " . count($links) . " article links");
        return $links;
    }

    /**
     * STEP 3, 4, 5: Loop through links and extract content
     */
    public function loopAndExtractArticles(array $links, array $source): int
    {
        $collected = 0;
        $total = count($links);

        $this->log("Starting article extraction for $total links");

        foreach ($links as $index => $articleUrl) {
            $this->updateStatus(4, "Processing article " . ($index + 1) . " of $total");

            try {
                // STEP 4: Fetch ARTICLE PAGE
                $html = $this->fetchArticlePage($articleUrl);

                if (empty($html)) {
                    $this->pipelineStatus['warnings'][] = "Empty response from: $articleUrl";
                    continue;
                }

                // STEP 5: Extract title/content/image/date
                $articleData = $this->extractArticleContent($html, $articleUrl, $source);

                if (empty($articleData['title'])) {
                    $this->pipelineStatus['warnings'][] = "No title found for: $articleUrl";
                    continue;
                }

                // Check for duplicates
                if ($this->duplicateChecker && $this->duplicateChecker->isDuplicate($articleUrl)) {
                    $this->pipelineStatus['warnings'][] = "Duplicate article skipped: " . substr($articleData['title'], 0, 50);
                    continue;
                }

                // Save to database
                if ($this->mysqli) {
                    $saved = $this->saveArticle($articleData, $source);
                    if ($saved) {
                        $collected++;
                        $this->log("Saved article: " . substr($articleData['title'], 0, 50));
                    }
                } else {
                    $collected++;
                }
            } catch (Exception $e) {
                $this->pipelineStatus['errors'][] = "Error processing $articleUrl: " . $e->getMessage();
                $this->log("Error: " . $e->getMessage());
            }

            // Rate limiting
            if ($index < $total - 1) {
                sleep($this->requestDelay);
            }
        }

        $this->log("Collected $collected articles");
        return $collected;
    }

    /**
     * STEP 4: Fetch ARTICLE PAGE
     */
    public function fetchArticlePage(string $url): string
    {
        $this->log("Fetching article: $url");

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_ENCODING => '',
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            throw new Exception("HTTP $httpCode - $error");
        }

        return $html;
    }

    /**
     * STEP 5: Extract title/content/image/date
     */
    public function extractArticleContent(string $html, string $url, array $source): array
    {
        $data = [
            'title' => '',
            'content' => '',
            'excerpt' => '',
            'image_url' => '',
            'author' => '',
            'published_at' => '',
            'url' => $url
        ];

        $titleSelector = $source['selector_title'] ?? 'h1';
        $contentSelector = $source['selector_content'] ?? 'article';
        $imageSelector = $source['selector_image'] ?? '';
        $excerptSelector = $source['selector_excerpt'] ?? '';
        $dateSelector = $source['selector_date'] ?? '';
        $authorSelector = $source['selector_author'] ?? '';

        try {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8">' . $html);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);

            // Extract Title
            if (!empty($titleSelector)) {
                $titleNodes = $xpath->query($this->cssToXPath($titleSelector));
                if ($titleNodes && $titleNodes->length > 0) {
                    $data['title'] = trim($titleNodes->item(0)->textContent);
                }
            }

            if (empty($data['title'])) {
                $metaTitle = $xpath->query('//meta[@property="og:title"]/@content');
                if ($metaTitle && $metaTitle->length > 0) {
                    $data['title'] = trim($metaTitle->item(0)->textContent);
                }
            }

            // Extract Content
            if (!empty($contentSelector)) {
                $contentNodes = $xpath->query($this->cssToXPath($contentSelector));
                if ($contentNodes && $contentNodes->length > 0) {
                    $contentNode = $contentNodes->item(0);
                    $data['content'] = $this->contentCleaner->clean($dom->saveHTML($contentNode));
                    $data['excerpt'] = trim($contentNode->textContent);
                }
            }

            // Extract Excerpt
            if (empty($data['excerpt']) && !empty($excerptSelector)) {
                $excerptNodes = $xpath->query($this->cssToXPath($excerptSelector));
                if ($excerptNodes && $excerptNodes->length > 0) {
                    $data['excerpt'] = trim($excerptNodes->item(0)->textContent);
                }
            }

            if (empty($data['excerpt'])) {
                $metaDesc = $xpath->query('//meta[@property="og:description"]/@content');
                if ($metaDesc && $metaDesc->length > 0) {
                    $data['excerpt'] = trim($metaDesc->item(0)->textContent);
                }
            }

            if (strlen($data['excerpt']) > 200) {
                $data['excerpt'] = substr($data['excerpt'], 0, 197) . '...';
            }

            // Extract Image
            if (!empty($imageSelector)) {
                $imageNodes = $xpath->query($this->cssToXPath($imageSelector));
                if ($imageNodes && $imageNodes->length > 0) {
                    $data['image_url'] = $imageNodes->item(0)->getAttribute('src');
                }
            }

            if (empty($data['image_url'])) {
                $metaImage = $xpath->query('//meta[@property="og:image"]/@content');
                if ($metaImage && $metaImage->length > 0) {
                    $data['image_url'] = $metaImage->item(0)->textContent;
                }
            }

            // Extract Author
            if (!empty($authorSelector)) {
                $authorNodes = $xpath->query($this->cssToXPath($authorSelector));
                if ($authorNodes && $authorNodes->length > 0) {
                    $data['author'] = trim($authorNodes->item(0)->textContent);
                }
            }

            if (empty($data['author'])) {
                $metaAuthor = $xpath->query('//meta[@name="author"]/@content');
                if ($metaAuthor && $metaAuthor->length > 0) {
                    $data['author'] = trim($metaAuthor->item(0)->textContent);
                }
            }

            // Extract Date
            if (!empty($dateSelector)) {
                $dateNodes = $xpath->query($this->cssToXPath($dateSelector));
                if ($dateNodes && $dateNodes->length > 0) {
                    $data['published_at'] = $this->parseDate($dateNodes->item(0)->textContent);
                }
            }

            if (empty($data['published_at'])) {
                $metaDate = $xpath->query('//meta[@property="article:published_time"]/@content');
                if ($metaDate && $metaDate->length > 0) {
                    $data['published_at'] = $this->parseDate($metaDate->item(0)->textContent);
                }
            }

            if (empty($data['published_at'])) {
                $data['published_at'] = date('Y-m-d H:i:s');
            }
        } catch (Exception $e) {
            $this->log("Error extracting content: " . $e->getMessage());
        }

        return $data;
    }

    private function saveArticle(array $articleData, array $source): bool
    {
        if (!$this->mysqli) {
            return false;
        }

        $model = new AutoContentModel($this->mysqli);

        $data = [
            'source_id' => $source['id'],
            'title' => $articleData['title'],
            'url' => $articleData['url'],
            'content' => $articleData['content'],
            'excerpt' => $articleData['excerpt'],
            'image_url' => $articleData['image_url'],
            'author' => $articleData['author'],
            'published_at' => $articleData['published_at'],
            'status' => 'collected'
        ];

        $id = $model->createArticle($data);
        return $id > 0;
    }

    private function cssToXPath(string $css): string
    {
        $xpath = $css;
        $xpath = preg_replace('/\.([^:\[\]]+)/', "[@class~='$1']", $xpath);
        $xpath = preg_replace('/#([^:\[\]]+)/', "[@id='$1']", $xpath);
        $xpath = preg_replace('/:first-child/', '[1]', $xpath);
        $xpath = preg_replace('/:last-child/', '[last()]', $xpath);

        if (strpos($xpath, '//') !== 0 && strpos($xpath, '/') !== 0) {
            $xpath = '//' . $xpath;
        }

        return $xpath;
    }

    private function makeAbsoluteUrl(string $url, string $baseUrl): string
    {
        if (parse_url($url, PHP_URL_SCHEME)) {
            return $url;
        }

        $base = parse_url($baseUrl);

        if (strpos($url, '/') === 0) {
            return $base['scheme'] . '://' . $base['host'] . $url;
        }

        $path = dirname($base['path']);
        return $base['scheme'] . '://' . $base['host'] . '/' . ltrim($path . '/' . $url, '/');
    }

    private function isValidArticleUrl(string $url): bool
    {
        if (!parse_url($url, PHP_URL_SCHEME)) {
            return false;
        }

        $excludePatterns = [
            '/\/category\//i',
            '/\/tag\//i',
            '/\/author\//i',
            '/\/page\//i',
            '/\/search\//i',
            '/\/login/i',
            '/\/register/i',
            '/\.pdf$/i',
            '/\.jpg$/i',
            '/\.png$/i',
            '/\.gif$/i',
        ];

        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }

        return true;
    }

    private function parseDate(string $dateStr): string
    {
        if (empty($dateStr)) {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($dateStr);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        return date('Y-m-d H:i:s');
    }

    private function updateStatus(int $step, string $stepName): void
    {
        $this->pipelineStatus['step'] = $step;
        $this->pipelineStatus['step_name'] = $stepName;
        $this->log("Step $step: $stepName");
    }

    private function resetPipelineStatus(): void
    {
        $this->pipelineStatus = [
            'step' => 0,
            'step_name' => '',
            'total_links_found' => 0,
            'articles_collected' => 0,
            'errors' => [],
            'warnings' => []
        ];
    }

    private function log(string $message): void
    {
        if ($this->debug) {
            error_log("[MultiLayerScraper] $message");
        }
    }
}
