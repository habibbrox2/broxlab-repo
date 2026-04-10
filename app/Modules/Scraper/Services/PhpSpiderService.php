<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Services;

use VDB\Spider\Spider;
use VDB\Spider\Discoverer\XPathExpressionDiscoverer;
use VDB\Spider\Discoverer\CssSelectorDiscoverer;
use VDB\Spider\Resource;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Exception;

/**
 * PHP Spider Service
 * Configurable web crawler using vdb/php-spider
 * Best for: Medium-complexity crawling with depth limits and caching
 */
class PhpSpiderService
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_depth' => 3,
            'max_queue_size' => 100,
            'user_agent' => 'BroxLab Spider/1.0',
            'download_delay' => 1,
            'politeness_policy' => true,
            'cache_enabled' => true,
            'cache_ttl' => 3600, // 1 hour
        ], $config);
    }

    /**
     * Crawl a website and return discovered resources
     */
    public function crawl(string $startUrl, array $options = []): array
    {
        $options = array_merge([
            'discoverer' => 'css', // 'css' or 'xpath'
            'selector' => 'a', // CSS selector or XPath expression
            'follow_patterns' => [], // URL patterns to follow
            'skip_patterns' => [], // URL patterns to skip
            'extract_data' => true,
        ], $options);

        try {
            $spider = new Spider($startUrl);
            $this->configureSpider($spider, $options);

            $resources = [];
            $spider->crawl();

            // Process discovered resources
            foreach ($spider->getDownloader()->getPersistenceHandler() as $resource) {
                if ($resource instanceof Resource) {
                    $resources[] = $this->processResource($resource, $options['extract_data']);
                }
            }

            return [
                'success' => true,
                'start_url' => $startUrl,
                'resources' => $resources,
                'stats' => [
                    'queued' => 0, // Stats not available in this version
                    'downloaded' => count($resources),
                    'skipped' => 0,
                    'failed' => 0,
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'start_url' => $startUrl,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Configure the spider with options
     */
    private function configureSpider(Spider $spider, array $options): void
    {
        // Set spider options
        $spider->getDiscovererSet()->maxDepth = $this->config['max_depth'];
        $spider->getQueueManager()->maxQueueSize = $this->config['max_queue_size'];

        // Set user agent and download delay via Guzzle client
        $guzzleClient = new \GuzzleHttp\Client([
            'headers' => [
                'User-Agent' => $this->config['user_agent']
            ],
            'delay' => $this->config['download_delay'] * 1000, // milliseconds
        ]);
        $requestHandler = new \VDB\Spider\RequestHandler\GuzzleRequestHandler($guzzleClient);
        $spider->getDownloader()->setRequestHandler($requestHandler);

        // Add politeness policy listener
        // Note: PolitenessPolicyListener doesn't implement EventSubscriberInterface
        // if ($this->config['politeness_policy']) {
        //     $politenessListener = new PolitenessPolicyListener($this->config['download_delay']);
        //     $spider->getDispatcher()->addSubscriber($politenessListener);
        // }

        // Set up discoverer
        if ($options['discoverer'] === 'css') {
            $discoverer = new CssSelectorDiscoverer($options['selector']);
        } else {
            $discoverer = new XPathExpressionDiscoverer($options['selector']);
        }
        $spider->getDiscovererSet()->set($discoverer);

        // Add filters
        // Note: Filters need to be implemented properly
        // if (!empty($options['follow_patterns'])) {
        //     foreach ($options['follow_patterns'] as $pattern) {
        //         $spider->getDiscovererSet()->addFilter(new \VDB\Spider\Filter\Prefetch\RestrictToBaseUriFilter($pattern));
        //     }
        // }

        // if (!empty($options['skip_patterns'])) {
        //     foreach ($options['skip_patterns'] as $pattern) {
        //         // Note: No direct DisallowedHostsFilter, using a custom approach or skip
        //     }
        // }

        // Configure caching
        if ($this->config['cache_enabled']) {
            $cacheHandler = new \VDB\Spider\PersistenceHandler\FileSerializedResourcePersistenceHandler(
                sys_get_temp_dir() . '/spider-cache'
            );
            $downloader = new \VDB\Spider\Downloader\Downloader($cacheHandler);
            $spider->setDownloader($downloader);
        }
    }

    /**
     * Process a discovered resource
     */
    private function processResource(Resource $resource, bool $extractData): array
    {
        $data = [
            'url' => $resource->getUri()->toString(),
            'status_code' => $resource->getResponse()->getStatusCode(),
            'content_type' => $resource->getResponse()->getHeaderLine('Content-Type'),
            'size' => strlen($resource->getResponse()->getBody()->__toString()),
        ];

        if ($extractData && $this->isHtmlContent($resource)) {
            $data['title'] = $this->extractTitle($resource);
            $data['content'] = $this->extractContent($resource);
            $data['links'] = $this->extractLinks($resource);
        }

        return $data;
    }

    /**
     * Check if resource contains HTML content
     */
    private function isHtmlContent(Resource $resource): bool
    {
        $contentType = $resource->getResponse()->getHeaderLine('Content-Type');
        return strpos($contentType, 'text/html') !== false;
    }

    /**
     * Extract title from HTML resource
     */
    private function extractTitle(Resource $resource): string
    {
        try {
            $crawler = $resource->getCrawler();
            return $crawler->filter('title')->first()->text();
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Extract content from HTML resource
     */
    private function extractContent(Resource $resource): string
    {
        try {
            $crawler = $resource->getCrawler();
            // Remove scripts and styles
            $crawler->filter('script, style')->each(function ($node) {
                $node->getNode(0)->parentNode->removeChild($node->getNode(0));
            });
            return $crawler->filter('body')->text();
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Extract links from HTML resource
     */
    private function extractLinks(Resource $resource): array
    {
        try {
            $crawler = $resource->getCrawler();
            return $crawler->filter('a')->each(function ($node) {
                return [
                    'url' => $node->attr('href'),
                    'text' => $node->text(),
                ];
            });
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get spider statistics
     */
    public function getStats(): array
    {
        return [
            'max_depth' => $this->config['max_depth'],
            'max_queue_size' => $this->config['max_queue_size'],
            'download_delay' => $this->config['download_delay'],
            'cache_enabled' => $this->config['cache_enabled'],
            // 'cache_ttl' => $this->config['cache_ttl'], // Not used in current implementation
        ];
    }
}
