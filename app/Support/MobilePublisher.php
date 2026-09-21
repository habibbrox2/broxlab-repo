<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Scraper\ScraperStore;
use App\Support\Scraper\SourceCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Publishes mobile scraper snapshots into the live database.
 *
 * The extraction pipeline (ScraperRunner → MobileDetailParser) stores mobile
 * items as JSON snapshots under storage/app/scraping/sources/, but nothing in
 * that flow writes to the `mobiles` table. This service closes the loop:
 *
 *   store.readSource(key)  →  iterate items  →  MobileAdminService::upsert
 *
 * Upsert is by source_url, so repeated runs update prices / specs in place
 * instead of duplicating rows. After the mobile row is inserted or updated,
 * its specifications and image gallery are synced (DELETE + reinsert, matching
 * the legacy pattern in MobileAdminService).
 *
 * Publishing is gated by the same enable check as AutoPublishService:
 * the pipeline must be enabled AND the source must have a snapshot.
 */
class MobilePublisher
{
    public function __construct(
        protected ScraperStore $store,
        protected SourceCatalog $catalog,
        protected MobileAdminService $mobiles,
    ) {}

    /**
     * Publish every mobile item from every 'mobile' type source snapshot.
     *
     * @return array{published:int, updated:int, skipped:int, failed:int, sources:int}
     */
    public function publishAll(?string $type = null, ?int $limit = null): array
    {
        $totals = ['published' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'sources' => 0];

        if (! $this->isEnabled()) {
            return $totals;
        }

        $sources = $this->catalog->enabled($type === null ? 'mobile' : null);

        foreach ($sources as $source) {
            $key = (string) $source['key'];
            $sourceType = (string) ($source['type'] ?? 'news');
            if ($sourceType !== 'mobile') {
                continue;
            }

            $totals['sources']++;
            $items = $this->store->readSource($key)['items'];

            foreach ($items as $item) {
                $outcome = $this->publishItem($item);
                $totals[$outcome] = ($totals[$outcome] ?? 0) + 1;

                if ($limit !== null && ($totals['published'] + $totals['updated']) >= $limit) {
                    return $totals;
                }
            }
        }

        return $totals;
    }

    /**
     * Publish one mobile snapshot item. Returns 'published' | 'updated' | 'skipped' | 'failed'.
     *
     * @param  array<string, mixed>  $item
     */
    public function publishItem(array $item): string
    {
        $sourceUrl = rtrim(trim((string) ($item['link'] ?? '')), '/');
        $title = trim((string) ($item['title'] ?? ''));
        if ($sourceUrl === '' || $title === '') {
            return 'skipped';
        }

        $brandName = trim((string) ($item['brand_name'] ?? ''));
        $modelName = trim((string) ($item['model_name'] ?? $title));
        if ($brandName === '' && $modelName !== '') {
            // Fall back: derive brand from model's first word.
            $parts = preg_split('/\s+/u', $modelName, 2) ?: [];
            if (count($parts) >= 2) {
                $brandName = $parts[0];
                $modelName = $parts[1];
            } else {
                $brandName = $modelName;
            }
        }

        $data = [
            'brand_name' => $brandName,
            'model_name' => $modelName,
            'official_price' => (float) ($item['official_price'] ?? 0),
            'unofficial_price' => (float) ($item['unofficial_price'] ?? 0),
            'status' => (string) ($item['status'] ?? 'both'),
            'release_date' => (string) ($item['release_date'] ?? ''),
            'is_official' => (int) ($item['is_official'] ?? 0),
            'source_url' => $sourceUrl,
        ];

        try {
            // Check whether this is an insert or an update.
            $existingId = DB::table('mobiles')->where('source_url', $sourceUrl)->value('id');
            $isNew = $existingId === null;

            $mobileId = $this->mobiles->upsertMobileBySourceUrl($data);
            if (is_bool($mobileId) && ! $mobileId) {
                return 'failed';
            }
            $mobileId = (int) $mobileId;

            // Sync specifications (DELETE + reinsert — legacy pattern).
            $specs = (array) ($item['specifications'] ?? []);
            if (! empty($specs)) {
                $keys = array_map(fn ($s) => (string) ($s['key'] ?? ''), $specs);
                $values = array_map(fn ($s) => (string) ($s['value'] ?? ''), $specs);
                $this->mobiles->updateSpecifications($mobileId, $keys, $values);
            }

            // Sync images (DELETE + reinsert — legacy pattern).
            $images = array_filter((array) ($item['images'] ?? []), fn ($url) => is_string($url) && preg_match('#^https?://#i', $url));
            if (! empty($images)) {
                $this->mobiles->updateImages($mobileId, array_values($images));
            }

            // Activity log.
            $this->logPublishActivity($isNew, $mobileId, $title);

            return $isNew ? 'published' : 'updated';
        } catch (\Throwable $e) {
            Log::warning('MobilePublisher: failed to publish item: ' . $e->getMessage(), [
                'source_url' => $sourceUrl,
                'title' => $title,
            ]);

            return 'failed';
        }
    }

    /**
     * The same enable gate as AutoPublishService: pipeline enabled.
     */
    public function isEnabled(): bool
    {
        return app(ScraperPipelineService::class)->isEnabled();
    }

    /**
     * Log the scrape→publish action to activity_logs.
     */
    protected function logPublishActivity(bool $isNew, int $mobileId, string $title): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => 0,
                'role' => 'system',
                'action' => $isNew ? 'mobile_scraped_added' : 'mobile_scraped_updated',
                'resource_type' => 'mobile',
                'resource_id' => $mobileId,
                'status' => 'success',
                'ip_address' => '0.0.0.0',
                'user_agent' => '',
                'details' => json_encode([
                    'title' => $title,
                    'source' => 'scraper',
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('MobilePublisher: activity log failed (non-fatal): ' . $e->getMessage());
        }
    }
}
