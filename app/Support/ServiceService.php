<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Port of the legacy ServiceModel public reads + ServicesController list/detail
 * logic: active services with enrichment (images, categories, tags), in-memory
 * search/category/sort filtering, and taxonomy-based related services.
 */
class ServiceService
{
    public function __construct(
        protected ContentImages $images,
        protected PostTaxonomy $taxonomy,
    ) {}

    /**
     * getAllActive() + enrichService(): status IN (active, archived),
     * deleted_at IS NULL, engagement select is 0/0 (legacy shape).
     */
    public function allEnriched(): array
    {
        $rows = DB::table('services as s')
            ->select('s.*', DB::raw('0 AS views, 0 AS impressions'))
            ->whereIn('s.status', ['active', 'archived'])
            ->whereNull('s.deleted_at')
            ->orderByDesc('s.created_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return $this->enrichBatch($rows);
    }

    /**
     * Legacy list pipeline: in-memory search + category filter + sort, then slice.
     */
    public function list(int $page = 1, int $perPage = 12, string $search = '', string $category = '', string $sort = 'latest'): array
    {
        $services = $this->allEnriched();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $services = array_values(array_filter($services, function (array $s) use ($needle): bool {
                $haystack = mb_strtolower(trim((string) ($s['name'] ?? '').' '.(string) ($s['description'] ?? '')));

                return $needle === '' || str_contains($haystack, $needle);
            }));
        }

        if ($category !== '') {
            $categoryNorm = mb_strtolower($category);
            $services = array_values(array_filter($services, function (array $s) use ($categoryNorm): bool {
                foreach (($s['categories'] ?? []) as $cat) {
                    if (! is_array($cat)) {
                        continue;
                    }
                    $key = mb_strtolower(trim((string) ($cat['slug'] ?? '')));
                    $name = mb_strtolower(trim((string) ($cat['name'] ?? '')));
                    if (($key !== '' && $key === $categoryNorm) || ($name !== '' && $name === $categoryNorm)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        usort($services, function (array $a, array $b) use ($sort): int {
            if ($sort === 'name') {
                return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            }
            if ($sort === 'popularity') {
                $scoreA = (int) ($a['views'] ?? 0);
                $scoreB = (int) ($b['views'] ?? 0);

                return $scoreB <=> $scoreA ?: strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            }

            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        $offset = (max(1, $page) - 1) * max(1, $perPage);

        return array_slice($services, $offset, max(1, $perPage));
    }

    /**
     * Distinct category list derived from the enriched services (legacy build).
     */
    public function categories(): array
    {
        $all = [];
        foreach ($this->allEnriched() as $service) {
            foreach (($service['categories'] ?? []) as $cat) {
                if (! is_array($cat)) {
                    continue;
                }
                $key = mb_strtolower(trim((string) ($cat['slug'] ?? '')));
                if ($key === '' || isset($all[$key])) {
                    continue;
                }
                $all[$key] = trim((string) ($cat['name'] ?? ucfirst($key)));
            }
        }

        $cats = array_values($all);
        usort($cats, fn ($a, $b) => strcasecmp($a, $b));

        return $cats;
    }

    /**
     * Legacy findBySlug → getEnriched → enrichService for detail pages.
     */
    public function bySlugOrId(string $slugOrId): ?array
    {
        $slugOrId = trim($slugOrId);

        if ($slugOrId === '') {
            return null;
        }

        $row = ctype_digit($slugOrId)
            ? DB::table('services as s')->select('s.*', DB::raw('0 AS views, 0 AS impressions'))->where('s.id', (int) $slugOrId)->first()
            : DB::table('services as s')->select('s.*', DB::raw('0 AS views, 0 AS impressions'))->where('s.slug', $slugOrId)->first();

        if (! $row) {
            return null;
        }

        $service = (array) $row;

        return $this->enrich($service);
    }

    /**
     * Related services: same category/tag first (up to 3), then fallback to
     * other active services — legacy buildRelatedServices shape.
     */
    public function related(array $service, int $limit = 3): array
    {
        $currentId = (int) ($service['id'] ?? 0);
        $related = [];
        $seen = [$currentId => true];

        $push = function (array $item) use (&$related, &$seen, $currentId): void {
            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId <= 0 || $itemId === $currentId || isset($seen[$itemId])) {
                return;
            }
            $seen[$itemId] = true;
            $related[] = $item;
        };

        // Legacy prefers same-category/tag services first, then falls back to
        // other active services. The category/tag lookups resolve through the
        // same content_taxonomy tables; we approximate with other active
        // services ordered by recency (legacy fallback path) — full taxonomy
        // ranking is tracked in REMAINING_STEPS.
        if (count($related) < $limit) {
            foreach ($this->allEnriched() as $candidate) {
                $push($candidate);
                if (count($related) >= $limit) {
                    break;
                }
            }
        }

        return array_slice($related, 0, $limit);
    }

    /**
     * Enrichment for a single service (enrichService parity).
     */
    protected function enrich(array $service): array
    {
        $service['metadata'] = $this->jsonOrArray($service['metadata'] ?? null);
        $service['form_fields'] = $this->jsonOrArray($service['form_fields'] ?? null);
        $service['images'] = $this->imageRows((int) $service['id']);
        $service['image_urls'] = $this->imageUrls((int) $service['id'], (string) ($service['description'] ?? ''));
        $service['featured_image'] = $this->featuredImage((int) $service['id']);
        $service['featured_image_url'] = $this->featuredImageUrl((int) $service['id'], (string) ($service['description'] ?? ''));
        $service['categories'] = $this->taxonomy->categoriesForContent('service', (int) $service['id']);
        $service['tags'] = $this->taxonomy->tagsForContent('service', (int) $service['id']);

        return $service;
    }

    protected function enrichBatch(array $rows): array
    {
        $ids = array_map(fn ($r) => (int) $r['id'], $rows);
        $cats = $this->taxonomy->categoriesForContentBatch('service', $ids);
        $tags = $this->taxonomy->tagsForContentBatch('service', $ids);

        $imgRows = $this->imageRowsBatch($ids);

        foreach ($rows as &$s) {
            $id = (int) $s['id'];
            $s['metadata'] = $this->jsonOrArray($s['metadata'] ?? null);
            $s['form_fields'] = $this->jsonOrArray($s['form_fields'] ?? null);
            $s['images'] = $imgRows[$id] ?? [];
            $s['image_urls'] = $this->urlsFromRows($imgRows[$id] ?? [], (string) ($s['description'] ?? ''), 3);
            $s['featured_image'] = $this->featuredFromRows($imgRows[$id] ?? []);
            $s['featured_image_url'] = $this->featuredFromRows($imgRows[$id] ?? [])['url'] ?? ($s['image_urls'][0] ?? null);
            $s['categories'] = $cats[$id] ?? [];
            $s['tags'] = $tags[$id] ?? [];
        }
        unset($s);

        return $rows;
    }

    protected function jsonOrArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function imageRows(int $serviceId): array
    {
        return $this->imageRowsBatch([$serviceId])[$serviceId] ?? [];
    }

    protected function imageRowsBatch(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = DB::table('service_images')
            ->whereIn('service_id', $ids)
            ->orderBy('service_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['service_id']][] = $row;
        }

        return $out;
    }

    /**
     * Resolve an image reference to a usable URL (legacy resolveMaybeMediaReference):
     * arrays/JSON → url/path; numeric → media table lookup; else as-is.
     */
    protected function resolveUrl(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value['url'] ?? $value['path'] ?? $value['thumbnail_path'] ?? reset($value));
        }

        $val = trim((string) $value);
        if ($val === '') {
            return '';
        }

        if (($val[0] ?? '') === '{' || ($val[0] ?? '') === '[') {
            $decoded = json_decode($val, true);
            if (is_array($decoded)) {
                return (string) ($decoded['url'] ?? $decoded['path'] ?? $decoded['thumbnail_path'] ?? reset($decoded));
            }
        }

        if (preg_match('/^[0-9]+$/', $val)) {
            $media = DB::table('media')
                ->select('file_path', 'thumbnail_path')
                ->where('id', (int) $val)
                ->whereNull('deleted_at')
                ->first();
            if ($media) {
                return (string) ($media->thumbnail_path ?: $media->file_path);
            }
        }

        return $val;
    }

    protected function urlsFromRows(array $rows, string $descriptionHtml, int $limit): array
    {
        $urls = [];
        foreach ($rows as $r) {
            $src = $this->resolveUrl($r['image_path'] ?? ($r['thumbnail_path'] ?? null));
            if ($src !== '') {
                $urls[] = $src;
            }
            if (count($urls) >= $limit) {
                break;
            }
        }
        if (! empty($urls)) {
            return array_values(array_unique($urls));
        }

        // Fallback: extract from description HTML (legacy getServiceImageUrls)
        return $this->images->extractMultiple($descriptionHtml, $limit);
    }

    protected function featuredFromRows(array $rows): array
    {
        foreach ($rows as $r) {
            if (! empty($r['is_featured'])) {
                $url = $this->resolveUrl($r['image_path'] ?? ($r['thumbnail_path'] ?? null));

                return $url !== '' ? ['url' => $url, 'alt_text' => $r['alt_text'] ?? ''] : [];
            }
        }

        return [];
    }

    protected function imageUrls(int $serviceId, string $descriptionHtml): array
    {
        return $this->urlsFromRows($this->imageRows($serviceId), $descriptionHtml, 3);
    }

    protected function featuredImage(int $serviceId): array
    {
        return $this->featuredFromRows($this->imageRows($serviceId));
    }

    protected function featuredImageUrl(int $serviceId, string $descriptionHtml): ?string
    {
        $featured = $this->featuredImage($serviceId);
        if (! empty($featured['url'])) {
            return $featured['url'];
        }

        $urls = $this->imageUrls($serviceId, $descriptionHtml);

        return $urls[0] ?? null;
    }
}