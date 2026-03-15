<?php

declare(strict_types=1);

namespace App\Modules\AutoContent;

use App\Modules\Scraper\DuplicateCheckerService;
use App\Modules\Scraper\EnhancedScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use mysqli;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * CronWorker orchestrates multi-site scraping, deduplication, and optional Telegram posting.
 */
class CronWorker
{
    private mysqli $mysqli;
    private EnhancedScraperService $scraper;
    private DuplicateCheckerService $dupChecker;
    private TelegramNotifier $telegram;
    private \AutoContentModel $model;
    private array $config;

    public function __construct(mysqli $mysqli, array $config = [])
    {
        $this->mysqli = $mysqli;
        $defaults = [
            'max_articles_per_source' => 10,
            'max_sources_per_run' => 20,
            'proxies' => [],
            'dedup_similarity' => 0.8,
            'telegram' => [
                'enabled' => false,
                'post_on_collect' => false,
                'template' => "*{title}*\n{excerpt}\n\n{url}",
            ],
        ];
        $this->config = array_replace_recursive($defaults, $config);

        $scraperConfig = [
            'proxies' => $this->config['proxies'],
        ];
        $this->scraper = new EnhancedScraperService($scraperConfig);
        $this->dupChecker = new DuplicateCheckerService($mysqli, (float)$this->config['dedup_similarity']);
        $this->telegram = new TelegramNotifier($this->config['telegram']);
        $this->model = new \AutoContentModel($mysqli);
    }

    /**
     * Run the worker for active sources.
     */
    public function run(): array
    {
        $summary = [
            'sources_processed' => 0,
            'articles_created' => 0,
            'duplicates_skipped' => 0,
            'errors' => [],
        ];

        $sources = $this->model->getActiveSources();
        $sources = array_slice($sources, 0, (int)$this->config['max_sources_per_run']);

        foreach ($sources as $source) {
            if (!$this->shouldFetch($source)) {
                continue;
            }

            try {
                $created = $this->processSource($source);
                $summary['sources_processed']++;
                $summary['articles_created'] += $created['created'];
                $summary['duplicates_skipped'] += $created['duplicates'];
            } catch (Throwable $e) {
                $summary['errors'][] = "Source {$source['id']}: " . $e->getMessage();
            }
        }

        return $summary;
    }

    private function shouldFetch(array $source): bool
    {
        if (empty($source['last_fetched_at'])) {
            return true;
        }

        $interval = (int)($source['fetch_interval'] ?? 3600);
        $last = strtotime($source['last_fetched_at']);
        return (time() - $last) >= $interval;
    }

    private function processSource(array $source): array
    {
        $created = 0;
        $duplicates = 0;
        $max = (int)$this->config['max_articles_per_source'];

        $items = [];
        switch ($source['type']) {
            case 'rss':
                $items = $this->collectFromRss($source, $max);
                break;
            case 'api':
                $items = $this->collectFromJsonApi($source, $max);
                break;
            default: // html / scrape
                $items = $this->collectFromHtml($source, $max);
                break;
        }

        foreach ($items as $item) {
            if ($this->dupChecker->urlExists($item['url'])) {
                $duplicates++;
                continue;
            }
            if (!empty($item['title']) && $this->dupChecker->titleExists($item['title'])) {
                $duplicates++;
                continue;
            }

            $result = $this->ingestArticle($source, $item);
            if ($result) {
                $created++;
            }
        }

        $this->markFetched((int)$source['id']);

        return ['created' => $created, 'duplicates' => $duplicates];
    }

    private function ingestArticle(array $source, array $item): bool
    {
        $scraped = $this->scraper->scrape($item['url']);
        if (!($scraped['success'] ?? false)) {
            return false;
        }

        $data = [
            'source_id' => (int)$source['id'],
            'url' => $item['url'],
            'original_url' => $item['url'],
            'original_title' => $scraped['title'] ?? ($item['title'] ?? ''),
            'original_content' => $scraped['content'] ?? '',
            'original_excerpt' => $item['excerpt'] ?? '',
            'original_author' => $scraped['author'] ?? ($item['author'] ?? ''),
            'featured_image' => $scraped['featured_image'] ?? ($scraped['images'][0] ?? ''),
            'original_published_at' => $item['published_at'] ?? ($scraped['date'] ?? null),
            'status' => 'collected',
        ];

        $id = $this->model->createArticle($data);
        if ($id > 0 && $this->telegram->isEnabled() && ($this->config['telegram']['post_on_collect'] ?? false)) {
            $this->telegram->sendArticle([
                'title' => $data['original_title'],
                'excerpt' => $data['original_excerpt'] ?: mb_substr(strip_tags($data['original_content']), 0, 180),
                'url' => $item['url'],
                'source' => $source['name'] ?? '',
            ], $this->config['telegram']['template'] ?? "*{title}*\n{excerpt}\n\n{url}");
        }

        return $id > 0;
    }

    private function collectFromRss(array $source, int $limit): array
    {
        $items = [];
        try {
            $feed = @simplexml_load_file($source['url']);
            if (!$feed || !isset($feed->channel->item)) {
                return $items;
            }
            foreach ($feed->channel->item as $entry) {
                $items[] = [
                    'title' => (string)$entry->title,
                    'url' => (string)$entry->link,
                    'excerpt' => (string)($entry->description ?? ''),
                    'published_at' => isset($entry->pubDate) ? date('Y-m-d H:i:s', strtotime((string)$entry->pubDate)) : null,
                ];
                if (count($items) >= $limit) {
                    break;
                }
            }
        } catch (Throwable $e) {
            error_log("RSS scrape failed for source {$source['id']}: " . $e->getMessage());
        }
        return $items;
    }

    private function collectFromJsonApi(array $source, int $limit): array
    {
        $items = [];
        $payload = $this->fetchHtml($source['url']);
        if ($payload === null) {
            return $items;
        }

        $json = json_decode($payload, true);
        if (!is_array($json)) {
            return $items;
        }

        foreach ($json as $row) {
            if (empty($row['url'])) {
                continue;
            }
            $items[] = [
                'title' => $row['title'] ?? '',
                'url' => $row['url'],
                'excerpt' => $row['excerpt'] ?? '',
                'published_at' => $row['published_at'] ?? null,
                'author' => $row['author'] ?? '',
            ];
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function collectFromHtml(array $source, int $limit): array
    {
        $html = $this->fetchHtml($source['url']);
        if ($html === null) {
            return [];
        }

        $crawler = new Crawler($html, $source['url']);
        $selector = $source['selector_list_item'] ?: 'article';
        $items = [];

        foreach ($crawler->filter($selector) as $node) {
            $nodeCrawler = new Crawler($node);
            $linkNode = $nodeCrawler->filter($source['selector_list_title'] ?: 'a');
            if ($linkNode->count() === 0) {
                continue;
            }

            $href = $linkNode->first()->attr('href');
            if (empty($href)) {
                continue;
            }

            $items[] = [
                'title' => trim($linkNode->first()->text('')),
                'url' => $this->resolveUrl($href, $source['url']),
                'excerpt' => $this->extractOptionalText($nodeCrawler, $source['selector_excerpt'] ?? ''),
                'published_at' => $this->extractOptionalText($nodeCrawler, $source['selector_date'] ?? ''),
                'author' => $this->extractOptionalText($nodeCrawler, $source['selector_author'] ?? ''),
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function extractOptionalText(Crawler $crawler, string $selector): string
    {
        if (empty($selector)) {
            return '';
        }
        try {
            $node = $crawler->filter($selector);
            if ($node->count() > 0) {
                return trim($node->first()->text(''));
            }
        } catch (Throwable $e) {
            // ignore selector errors
        }
        return '';
    }

    private function fetchHtml(string $url): ?string
    {
        $client = new Client([
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; BroxBhaiBot/1.0)',
            ],
        ]);

        $options = [];
        if (!empty($this->config['proxies'])) {
            $proxy = $this->config['proxies'][array_rand($this->config['proxies'])];
            if (!empty($proxy)) {
                $options['proxy'] = $proxy;
            }
        }

        try {
            $response = $client->get($url, $options);
            return (string)$response->getBody();
        } catch (GuzzleException $e) {
            error_log("fetchHtml failed for {$url}: " . $e->getMessage());
            return null;
        }
    }

    private function resolveUrl(string $href, string $base): string
    {
        if (str_starts_with($href, 'http')) {
            return $href;
        }
        $parsedBase = parse_url($base);
        $scheme = $parsedBase['scheme'] ?? 'http';
        $host = $parsedBase['host'] ?? '';
        $path = rtrim(dirname($parsedBase['path'] ?? '/'), '/');
        if (str_starts_with($href, '//')) {
            return $scheme . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $scheme . '://' . $host . $href;
        }
        return $scheme . '://' . $host . $path . '/' . ltrim($href, '/');
    }

    private function markFetched(int $sourceId): void
    {
        $stmt = $this->mysqli->prepare("UPDATE autocontent_sources SET last_fetched_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $sourceId);
        $stmt->execute();
        $stmt->close();
    }
}
