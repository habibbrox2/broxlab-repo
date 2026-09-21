<?php

declare(strict_types=1);

namespace App\Support\Scraper;

use App\Support\Ai\AiProviderRepository;
use App\Support\Ai\ContentEnricher;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates one extraction run:
 *
 *   discover → fetch → parse → dedupe (seen.json) → optional AI enrich →
 *   merge into the per-source snapshot → record run history.
 *
 * The runner never throws for a single source; failures are reported in the
 * returned stats so the admin UI and the queue log can surface them.
 */
class ScraperRunner
{
    protected ScraperClient $client;

    protected FeedParser $feeds;

    protected HtmlListingExtractor $html;

    protected SitemapReader $sitemaps;

    protected ScraperStore $store;

    protected SourceCatalog $catalog;

    protected ?ContentEnricher $enricher = null;

    public function __construct(
        ?ScraperClient $client = null,
        ?ScraperStore $store = null,
        ?SourceCatalog $catalog = null,
        ?ContentEnricher $enricher = null,
    ) {
        $this->client = $client ?? new ScraperClient();
        $this->store = $store ?? new ScraperStore();
        $this->catalog = $catalog ?? new SourceCatalog();
        $this->feeds = new FeedParser();
        $this->html = new HtmlListingExtractor($this->client);
        $this->sitemaps = new SitemapReader($this->client);
        $this->enricher = $enricher;
    }

    /**
     * @return array<string, mixed>
     */
    public function run(?string $type = null, ?int $limit = null, ?string $sourceId = null, ?bool $enrich = null): array
    {
        $limit = $limit ?? (int) config('scraper.max_items', 25);
        $limit = max(1, min($limit, 200));

        if ($sourceId !== null) {
            $source = $this->catalog->find($sourceId);
            if ($source === null) {
                return [
                    'status' => 'error',
                    'error' => "Unknown source: {$sourceId}",
                    'results' => [],
                    'totals' => ['fetched' => 0, 'added' => 0, 'skipped' => 0],
                ];
            }
            $sources = [$source];
        } else {
            $sources = $this->catalog->enabled($type);
        }

        $enrich = $enrich ?? (bool) config('scraper.ai_enrich', false);
        $results = [];
        $totals = ['fetched' => 0, 'added' => 0, 'skipped' => 0];

        foreach ($sources as $source) {
            $result = $this->runSource($source, $limit, $enrich);
            $results[] = $result;
            $totals['fetched'] += $result['fetched'];
            $totals['added'] += $result['added'];
            $totals['skipped'] += $result['skipped'];

            $this->client->sleep();
        }

        $summary = [
            'status' => 'completed',
            'type' => $type,
            'source_id' => $sourceId,
            'limit' => $limit,
            'enriched' => $enrich,
            'sources' => count($sources),
            'totals' => $totals,
            'results' => $results,
        ];

        $this->store->appendRun([
            'status' => $summary['status'],
            'type' => $type,
            'source_id' => $sourceId,
            'limit' => $limit,
            'enriched' => $enrich,
            'totals' => $totals,
            'results' => $results,
        ]);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function runSource(array $source, int $limit, bool $enrich = false): array
    {
        $key = (string) $source['key'];
        $base = ['key' => $key, 'name' => $source['name'] ?? $key, 'type' => $source['type'] ?? 'news'];

        try {
            $discovery = $this->discover($source, $limit);
        } catch (\Throwable $e) {
            Log::warning("Scraper source {$key} discovery failed: " . $e->getMessage());
            $discovery = ['items' => [], 'strategy' => 'error', 'error' => $e->getMessage()];
        }

        $raw = $discovery['items'];
        $fetched = count($raw);

        if ($fetched === 0) {
            return array_merge($base, [
                'status' => 'no_items',
                'strategy' => $discovery['strategy'],
                'error' => $discovery['error'] ?? null,
                'fetched' => 0,
                'added' => 0,
                'skipped' => 0,
            ]);
        }

        $normalized = $this->normalize($raw, $source);
        $fresh = $this->store->filterUnseen($normalized);
        $skipped = count($normalized) - count($fresh);
        $fresh = array_slice($fresh, 0, $limit);

        if ($enrich) {
            $fresh = $this->enrichItems($fresh);
        }

        $merged = $this->merge($key, $fresh);
        $this->store->writeSource($key, $merged);
        $this->store->markSeen(array_column($fresh, 'link'));

        return array_merge($base, [
            'status' => 'completed',
            'strategy' => $discovery['strategy'],
            'error' => $discovery['error'] ?? null,
            'fetched' => $fetched,
            'added' => count($fresh),
            'skipped' => $skipped,
        ]);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{items: array<int, array<string, mixed>>, strategy: string, error: ?string}
     */
    protected function discover(array $source, int $limit): array
    {
        $homepage = (string) ($source['homepage'] ?? '');
        $strategy = (string) ($source['strategy'] ?? 'auto');
        $declaredFeed = $source['feed'] ?? null;
        $error = null;

        if (in_array($strategy, ['auto', 'feed'], true)) {
            foreach ($this->feedCandidates($source, $strategy === 'auto') as $feedUrl) {
                $response = $this->client->get($feedUrl);
                if (! $response['ok'] || $response['body'] === null) {
                    $error ??= $response['error'];
                    continue;
                }

                $items = $this->feeds->parse($response['body'], $homepage, $this->client);
                if ($items !== []) {
                    return ['items' => array_slice($items, 0, $limit), 'strategy' => 'feed', 'error' => null];
                }
            }
        }

        if (in_array($strategy, ['auto', 'sitemap'], true) && $homepage !== '') {
            $items = $this->sitemaps->latest($homepage, $limit);
            if ($items !== []) {
                return ['items' => $items, 'strategy' => 'sitemap', 'error' => null];
            }
        }

        if (in_array($strategy, ['auto', 'html'], true) && $homepage !== '') {
            $response = $this->client->get($homepage);
            if ($response['ok'] && $response['body'] !== null) {
                $items = $this->html->extract($response['body'], $homepage, $source);
                if ($items !== []) {
                    return ['items' => array_slice($items, 0, $limit), 'strategy' => 'html', 'error' => null];
                }
            } else {
                $error ??= $response['error'];
            }
        }

        return ['items' => [], 'strategy' => 'none', 'error' => $error];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<int, string>
     */
    protected function feedCandidates(array $source, bool $discover): array
    {
        $candidates = [];
        if ($discover && ! empty($source['homepage'])) {
            $candidates = array_merge($candidates, $this->discoverFeedUrls((string) $source['homepage']));
        }
        if (! empty($source['feed'])) {
            $candidates[] = (string) $source['feed'];
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @return array<int, string>
     */
    protected function discoverFeedUrls(string $homepage): array
    {
        $response = $this->client->get($homepage);
        if (! $response['ok'] || $response['body'] === null) {
            return [];
        }

        $found = [];
        if (preg_match_all('#<link[^>]+>#i', $response['body'], $matches)) {
            foreach ($matches[0] as $tag) {
                if (stripos($tag, 'application/rss+xml') === false && stripos($tag, 'application/atom+xml') === false) {
                    continue;
                }
                if (preg_match('#href=["\']([^"\']+)["\']#i', $tag, $href) === 1) {
                    $found[] = $this->client->resolveUrl($href[1], $homepage);
                }
            }
        }

        foreach (['/feed', '/rss', '/rss.xml', '/feed.xml'] as $path) {
            $found[] = rtrim($homepage, '/') . $path;
        }

        return array_values(array_unique(array_filter($found)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @param  array<string, mixed>  $source
     * @return array<int, array<string, mixed>>
     */
    protected function normalize(array $raw, array $source): array
    {
        $items = [];
        $seen = [];

        foreach ($raw as $item) {
            $link = trim((string) ($item['link'] ?? ''));
            $title = $this->cleanTitle((string) ($item['title'] ?? ''));
            if ($link === '' || $title === '') {
                continue;
            }

            $dedupeKey = rtrim($link, '/');
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $items[] = [
                'title' => $title,
                'link' => $link,
                'summary' => $this->cleanSummary($item['summary'] ?? null),
                'published_at' => $item['published_at'] ?? null,
                'guid' => $item['guid'] ?? $dedupeKey,
                'source_key' => $source['key'],
                'source_name' => $source['name'] ?? $source['key'],
                'type' => $source['type'] ?? 'news',
                'lang' => $source['lang'] ?? 'bn',
                'extracted_at' => gmdate('c'),
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fresh
     * @return array<int, array<string, mixed>>
     */
    protected function enrichItems(array $fresh): array
    {
        $enricher = $this->enricher;
        if ($enricher === null) {
            $repository = app(AiProviderRepository::class);
            if ($repository->default() === null) {
                return $fresh;
            }
            $enricher = app(ContentEnricher::class);
        }

        foreach ($fresh as $index => $item) {
            try {
                $fresh[$index] = $enricher->enrich($item);
            } catch (\Throwable $e) {
                Log::warning('Scraper AI enrichment failed: ' . $e->getMessage());
            }
        }

        return $fresh;
    }

    /**
     * Newest-first merge of fresh items into the stored snapshot.
     *
     * @param  array<int, array<string, mixed>>  $fresh
     * @return array<int, array<string, mixed>>
     */
    protected function merge(string $key, array $fresh): array
    {
        $existing = $this->store->readSource($key)['items'];
        $limit = (int) config('scraper.source_cap', 500);

        $byLink = [];
        foreach (array_merge($fresh, $existing) as $item) {
            $link = rtrim((string) ($item['link'] ?? ''), '/');
            if ($link !== '' && ! isset($byLink[$link])) {
                $byLink[$link] = $item;
            }
        }

        return array_slice(array_values($byLink), 0, $limit);
    }

    protected function cleanTitle(string $title): string
    {
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = (string) preg_replace('/\s+/u', ' ', $title);

        return trim($title);
    }

    protected function cleanSummary(mixed $summary): ?string
    {
        if (! is_string($summary) || trim($summary) === '') {
            return null;
        }

        $text = strip_tags(html_entity_decode($summary, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        return $text === '' ? null : mb_substr($text, 0, 500);
    }
}
