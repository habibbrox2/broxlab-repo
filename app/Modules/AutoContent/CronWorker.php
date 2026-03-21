<?php

declare(strict_types=1);

namespace App\Modules\AutoContent;

use App\Modules\Scraper\DuplicateCheckerService;
use App\Modules\Scraper\EnhancedScraperService;
use App\Modules\Scraper\NodeScraperRunner;
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
    private NodeScraperRunner $nodeRunner;

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
        $this->model->ensureTablesExist();
        $this->nodeRunner = new NodeScraperRunner();
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
            case 'xml': 
                $items = $this->collectFromSitemap($source, $max); 
                break; 
            case 'api': 
                $items = $this->collectFromJsonApi($source, $max); 
                break; 
            case 'scrape': 
                // Data-quality policy: scrape sources must use Node preset pipeline. 
                $presetKey = trim((string)($source['website_preset_key'] ?? '')); 
                $sid = (int)($source['id'] ?? 0); 
                if ($sid <= 0 || $presetKey === '') { 
                    $this->model->insertScrapeLog($sid > 0 ? $sid : null, (string)($source['url'] ?? ''), 'skipped', null, 0.0, 'missing_website_preset_key'); 
                    if ($sid > 0) { 
                        $this->pauseSource($sid); 
                        $this->markFetched($sid); 
                    } 
                    return ['created' => 0, 'duplicates' => 0]; 
                } 
 
                $node = $this->nodeRunner->runForSourceId($sid, $max, 180);  
                if (($node['success'] ?? false) && is_array($node['data'] ?? null)) {  
                    $data = $node['data'];  
                    if (!($data['success'] ?? true)) { 
                        $err = (string)($data['error'] ?? ($data['status'] ?? 'node_failed')); 
                        $this->model->insertScrapeLog($sid, (string)($source['url'] ?? ''), $err === 'waf_challenge' ? 'waf_challenge' : 'failed', null, 0.0, $err); 
                        if ($err === 'waf_challenge') { 
                            $this->pauseSource($sid); 
                        } 
                        $this->markFetched($sid); 
                        return ['created' => 0, 'duplicates' => 0]; 
                    } 
                    $created = (int)($data['saved'] ?? 0);  
                    $duplicates = (int)($data['duplicates'] ?? 0);  
                    $this->model->insertScrapeLog($sid, (string)($source['url'] ?? ''), 'success', 200, 0.0, null);  
                    $this->markFetched($sid);  
                    return ['created' => $created, 'duplicates' => $duplicates];  
                }  
 
                $err = (string)($node['error'] ?? 'node_scraper_failed'); 
                $stderr = (string)($node['stderr'] ?? ''); 
                $errorMsg = $err . ($stderr !== '' ? (' | ' . substr($stderr, 0, 200)) : ''); 
                $this->model->insertScrapeLog($sid, (string)($source['url'] ?? ''), 'failed', null, 0.0, $errorMsg); 
                if ($this->isWafBlocked($stderr, $stderr)) { 
                    $this->model->insertScrapeLog($sid, (string)($source['url'] ?? ''), 'waf_blocked', null, 0.0, 'node_waf_blocked'); 
                    $this->pauseSource($sid); 
                } 
                $this->markFetched($sid); 
                return ['created' => 0, 'duplicates' => 0]; 
            default: // html 
                $items = $this->collectFromHtml($source, $max); 
                break; 
        } 
 
        foreach ($items as $item) { 
            $url = (string)($item['url'] ?? ''); 
            $sid = (int)($source['id'] ?? 0); 
            if ($sid > 0 && $url !== '') { 
                $this->model->upsertCrawlQueue($sid, $url, 'pending', 0, 0, null); 
            } 
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
        $url = (string)($item['url'] ?? ''); 
        $sid = (int)($source['id'] ?? 0); 
        $start = microtime(true); 
        $scraped = $this->scraper->scrape($url); 
        $elapsed = microtime(true) - $start; 
        if (!($scraped['success'] ?? false)) { 
            $this->model->insertScrapeLog($sid > 0 ? $sid : null, $url, 'article_fetch_failed', null, (float)$elapsed, (string)($scraped['error'] ?? 'scrape_failed')); 
            return false; 
        } 
 
        $title = (string)($scraped['title'] ?? ($item['title'] ?? '')); 
        $content = (string)($scraped['content'] ?? ''); 
        if ($this->isWafBlocked($title, $content)) { 
            $this->model->insertScrapeLog($sid > 0 ? $sid : null, $url, 'waf_blocked', 200, (float)$elapsed, 'waf_challenge_detected', strlen($content)); 
            if ($sid > 0) { 
                $this->pauseSource($sid); 
            } 
            return false; 
        } 
 
        $data = [ 
            'source_id' => (int)$source['id'], 
            'url' => $url, 
            'original_url' => $url, 
            'original_title' => $title, 
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

        if ($id > 0) { 
            $this->model->insertScrapeLog($sid > 0 ? $sid : null, $url, 'article_fetch_success', 200, (float)$elapsed, null, strlen((string)($data['original_content'] ?? ''))); 
            return true; 
        } 
 
        $this->model->insertScrapeLog($sid > 0 ? $sid : null, $url, 'failed', null, (float)$elapsed, 'db_insert_failed'); 
        return false; 
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
        $sid = (int)($source['id'] ?? 0); 
        $start = microtime(true); 
        $html = $this->fetchHtml($source['url']); 
        $elapsed = microtime(true) - $start; 
        if ($html === null) { 
            $this->model->insertScrapeLog($sid > 0 ? $sid : null, (string)($source['url'] ?? ''), 'list_fetch_failed', null, (float)$elapsed, 'fetch_failed'); 
            return []; 
        } 
        $this->model->insertScrapeLog($sid > 0 ? $sid : null, (string)($source['url'] ?? ''), 'list_fetch_success', 200, (float)$elapsed, null, strlen($html)); 
 
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
 
    private function collectFromSitemap(array $source, int $limit): array 
    { 
        $sid = (int)($source['id'] ?? 0); 
        $start = microtime(true); 
        $xml = $this->fetchHtml($source['url']); 
        $elapsed = microtime(true) - $start; 
        if ($xml === null) { 
            $this->model->insertScrapeLog($sid > 0 ? $sid : null, (string)($source['url'] ?? ''), 'list_fetch_failed', null, (float)$elapsed, 'sitemap_fetch_failed'); 
            return []; 
        } 
        $this->model->insertScrapeLog($sid > 0 ? $sid : null, (string)($source['url'] ?? ''), 'list_fetch_success', 200, (float)$elapsed, null, strlen($xml)); 
 
        $items = []; 
        try { 
            $sx = @simplexml_load_string($xml); 
            if (!$sx) { 
                return []; 
            } 
 
            $locs = []; 
            if (isset($sx->sitemap)) { 
                foreach ($sx->sitemap as $sm) { 
                    $loc = (string)($sm->loc ?? ''); 
                    if ($loc !== '') { 
                        $locs[] = $loc; 
                    } 
                    if (count($locs) >= $limit) { 
                        break; 
                    } 
                } 
            } elseif (isset($sx->url)) { 
                foreach ($sx->url as $u) { 
                    $loc = (string)($u->loc ?? ''); 
                    if ($loc !== '') { 
                        $locs[] = $loc; 
                    } 
                    if (count($locs) >= $limit) { 
                        break; 
                    } 
                } 
            } 
 
            foreach ($locs as $loc) { 
                $items[] = [ 
                    'title' => '', 
                    'url' => $loc, 
                    'excerpt' => '', 
                    'published_at' => null, 
                    'author' => '', 
                ]; 
                if (count($items) >= $limit) { 
                    break; 
                } 
            } 
        } catch (Throwable $e) { 
            error_log("Sitemap parse failed for source {$source['id']}: " . $e->getMessage()); 
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
 
    private function isWafBlocked(string $title, string $content): bool 
    { 
        $t = strtolower(trim($title)); 
        if ($t === 'just a moment...' || str_contains($t, 'just a moment')) { 
            return true; 
        } 
 
        $c = strtolower($content); 
        $markers = [ 
            'cf-chl-', 
            'cloudflare', 
            'challenge-platform', 
            'turnstile', 
            'checking your browser', 
            'ddos protection', 
            'waf_challenge', 
        ]; 
 
        foreach ($markers as $m) { 
            if ($m !== '' && str_contains($c, $m)) { 
                return true; 
            } 
        } 
 
        return false; 
    } 
 
    private function pauseSource(int $sourceId): void 
    { 
        if ($sourceId <= 0) { 
            return; 
        } 
        try { 
            $stmt = $this->mysqli->prepare("UPDATE autocontent_sources SET is_active = 0 WHERE id = ? LIMIT 1"); 
            if ($stmt) { 
                $stmt->bind_param('i', $sourceId); 
                $stmt->execute(); 
                $stmt->close(); 
            } 
        } catch (Throwable $e) { 
            // ignore 
        } 
    } 
 
    private function markFetched(int $sourceId): void 
    { 
        $stmt = $this->mysqli->prepare("UPDATE autocontent_sources SET last_fetched_at = NOW() WHERE id = ?"); 
        $stmt->bind_param("i", $sourceId);
        $stmt->execute();
        $stmt->close();
    }
}
