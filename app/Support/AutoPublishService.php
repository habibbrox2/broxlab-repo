<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Scraper\ScraperStore;
use App\Support\Scraper\SourceCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-publishes scraped content as posts.
 *
 * The extraction pipeline (ScraperRunner) stores items as JSON snapshots under
 * storage/app/scraping/sources, but nothing turned them into `posts` rows — the
 * scraped articles were visible only on the admin scraper screens. This service
 * closes that loop: every snapshot item whose link is not yet a post.source_url
 * becomes a published post, attributed to its source, tagged with its category
 * (news/jobs/tech) and any AI enrichment tags.
 *
 * Design notes:
 * - Dedupe is by posts.source_url (the item link), so re-runs are idempotent
 *   even though the scraper's own `seen` store may have been reset.
 * - Content is honest about its origin: the summary/excerpt plus a canonical
 *   "Source" link back to the original article. We do not scrape full article
 *   bodies — the pipeline only extracts listing summaries.
 * - Publishing is gated: only runs when the extraction pipeline itself is
 *   enabled AND scraper.autopublish is on (fail closed, like the rest of the
 *   scraper config).
 */
class AutoPublishService
{
    /** Snapshot item type → category name created/attached on posts. */
    protected const TYPE_CATEGORY = [
        'news' => 'News',
        'jobs' => 'Jobs',
        'tech' => 'Tech',
    ];

    public function __construct(
        protected ScraperStore $store,
        protected SourceCatalog $catalog,
        protected AdminPostService $posts,
    ) {}

    /**
     * The auto-publish gate: pipeline enabled AND autopublish flag on.
     *
     * The flag comes from the admin-managed app_settings value when an admin
     * has set it (0/1); otherwise it falls back to config/scraper.php.
     */
    public function isEnabled(): bool
    {
        $pipeline = app(ScraperPipelineService::class);
        if (! $pipeline->isEnabled()) {
            return false;
        }

        return (bool) $this->autopublishSetting();
    }

    /** Admin override (or null when unset → config default). */
    public function autopublishSetting(): ?bool
    {
        try {
            $row = (array) (DB::table('app_settings')->where('id', 1)->first() ?? []);
        } catch (\Throwable) {
            return null;
        }

        $value = $row['scraper_autopublish_enabled'] ?? null;

        return $value === null ? null : (bool) $value;
    }

    /** Effective autopublish flag: DB override if set, else config. */
    protected function autopublishFlag(): bool
    {
        return $this->autopublishSetting() ?? (bool) config('scraper.autopublish', true);
    }

    /** Per-run publish cap: DB override if set, else config. */
    public function autopublishLimit(): int
    {
        try {
            $row = (array) (DB::table('app_settings')->where('id', 1)->first() ?? []);
        } catch (\Throwable) {
            $row = [];
        }

        $limit = (int) ($row['scraper_autopublish_limit'] ?? 0);

        return $limit > 0 ? $limit : (int) config('scraper.autopublish_limit', 50);
    }

    /**
     * Publish every not-yet-published item across the enabled sources.
     *
     * @return array{published:int, skipped:int, failed:int, sources:int}
     */
    public function publishAll(?string $type = null, ?int $limit = null): array
    {
        $totals = ['published' => 0, 'skipped' => 0, 'failed' => 0, 'sources' => 0];
        $limit = $limit ?? $this->autopublishLimit();

        foreach ($this->catalog->enabled($type) as $source) {
            $totals['sources']++;
            $items = $this->store->readSource((string) $source['key'])['items'];

            foreach ($items as $item) {
                if ($totals['published'] >= $limit) {
                    return $totals;
                }

                $outcome = $this->publishItem($item);
                $totals[$outcome] = ($totals[$outcome] ?? 0) + 1;
            }
        }

        return $totals;
    }

    /**
     * Publish one snapshot item. Returns 'published' | 'skipped' | 'failed'.
     *
     * @param  array<string, mixed>  $item
     */
    public function publishItem(array $item): string
    {
        $link = rtrim(trim((string) ($item['link'] ?? '')), '/');
        $title = trim((string) ($item['title'] ?? ''));
        if ($link === '' || $title === '') {
            return 'skipped';
        }

        // Idempotency: source_url is the identity of a scraped post.
        if (DB::table('posts')->where('source_url', $link)->exists()) {
            return 'skipped';
        }

        $sourceName = (string) ($item['source_name'] ?? $item['source_key'] ?? 'Scraped');
        $itemType = (string) ($item['type'] ?? 'news');

        $summary = trim((string) ($item['ai_summary'] ?? $item['summary'] ?? ''));
        $content = $this->buildContent($title, $summary, $link, $sourceName, $item['image'] ?? null);

        try {
            $slug = $this->posts->generateUniquePermalink($title);
            $publishedAt = $this->normalizeDate($item['published_at'] ?? null) ?? now()->toDateTimeString();

            $postId = (int) DB::table('posts')->insertGetId([
                'title' => $title,
                'content' => $content,
                'author' => $sourceName,
                'slug' => $slug,
                'published' => 1,
                'status' => 'published',
                'excerpt' => mb_substr($summary !== '' ? $summary : $title, 0, 300),
                'published_at' => $publishedAt,
                'source_url' => $link,
                'meta_title' => $title,
                'meta_description' => mb_substr($summary !== '' ? $summary : $title, 0, 160),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->attachTaxonomy((int) $postId, $itemType, $sourceName, $item);

            return 'published';
        } catch (\Throwable $e) {
            Log::warning('AutoPublish: failed to publish scraped item: '.$e->getMessage());

            return 'failed';
        }
    }

    /**
     * Attribute the post: the type category (News/Jobs/Tech), the source name
     * as a tag, plus any AI-suggested tags from enrichment.
     *
     * @param  array<string, mixed>  $item
     */
    protected function attachTaxonomy(int $postId, string $itemType, string $sourceName, array $item): void
    {
        try {
            $categoryName = self::TYPE_CATEGORY[$itemType] ?? 'News';
            $existing = DB::table('categories')->where('name', $categoryName)->first();
            $categoryId = $existing !== null
                ? (int) $existing->id
                : $this->posts->createCategoryByName($categoryName);

            $this->posts->attachCategoriesToContent('post', $postId, [$categoryId]);

            $tagNames = [$sourceName];
            foreach ((array) ($item['ai_tags'] ?? []) as $tag) {
                if (is_string($tag) && trim($tag) !== '') {
                    $tagNames[] = trim($tag);
                }
            }
            $this->posts->attachTagsToContent('post', $postId, $this->posts->resolveTagIds($tagNames));
        } catch (\Throwable $e) {
            // Taxonomy is decorative — never fail the publish over it.
            Log::warning('AutoPublish: taxonomy attach failed (non-fatal): '.$e->getMessage());
        }
    }

    /**
     * Body copy for the post: summary + explicit attribution. Kept short and
     * honest — we aggregate headlines, we do not republish full articles.
     */
    protected function buildContent(string $title, string $summary, string $link, string $sourceName, mixed $image = null): string
    {
        $paragraphs = [];

        // Lead image from the scrape — the front-end's image pipeline probes
        // the first <img src> in the content, so this also becomes the card
        // thumbnail. Lazy attributes so the page doesn't load it eagerly.
        if (is_string($image) && preg_match('#^https?://#i', $image)) {
            $paragraphs[] = '<p><img src="'.htmlspecialchars($image, ENT_QUOTES, 'UTF-8')
                .'" alt="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                .'" loading="lazy" style="max-width:100%;height:auto;"></p>';
        }

        if ($summary !== '') {
            $paragraphs[] = '<p>'.htmlspecialchars($summary, ENT_QUOTES, 'UTF-8').'</p>';
        } else {
            $paragraphs[] = '<p>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</p>';
        }

        $paragraphs[] = '<p>'.htmlspecialchars(
            'এই নিবন্ধটি স্বয়ংক্রিয়ভাবে সংগৃহীত (auto-published)। সম্পূর্ণ বিবরণ মূল উৎসে পড়ুন।',
            ENT_QUOTES,
            'UTF-8'
        ).'</p>';
        $paragraphs[] = '<p><a href="'.htmlspecialchars($link, ENT_QUOTES, 'UTF-8').'" rel="noopener nofollow" target="_blank">'
            .htmlspecialchars($sourceName.' — মূল নিবন্ধ পড়ুন', ENT_QUOTES, 'UTF-8').'</a></p>';

        return implode("\n", $paragraphs);
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
