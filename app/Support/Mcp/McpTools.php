<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use App\Support\MobileService;
use App\Support\PostTaxonomy;
use App\Support\PostsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-only MCP tool layer over the existing BroxLab services.
 *
 * Reuses PostsService / MobileService / PostTaxonomy rather than duplicating
 * SQL. Every method returns plain arrays shaped for MCP tool output. Content
 * from the database is always treated as untrusted data — it is returned
 * verbatim and never executed or interpreted.
 */
class McpTools
{
    public function __construct(
        protected PostsService $posts,
        protected MobileService $mobiles,
        protected PostTaxonomy $taxonomy,
    ) {}

    // ── Site ─────────────────────────────────────────────────────────────

    public function siteInfo(): array
    {
        $ttl = (int) config('mcp.cache_ttl', 300);

        return Cache::remember('mcp:site_info', $ttl, function () {
            $categories = $this->categoryList();

            return [
                'site_name' => config('app.name', 'BroxLab'),
                'site_url' => $this->url('/'),
                'description' => 'Bangla tech news, mobile phone prices in Bangladesh, banking, earnings and how-to guides.',
                'content_types' => ['articles', 'mobiles', 'categories'],
                'categories' => array_map(
                    fn (array $c) => ['name' => $c['name'], 'slug' => $c['slug']],
                    array_slice($categories['categories'], 0, 20)
                ),
                'tools' => [
                    'get_site_info', 'search_articles', 'get_article', 'get_latest_articles',
                    'list_categories', 'get_category_articles', 'search_devices',
                    'get_device', 'get_device_specs', 'compare_devices', 'search_site',
                ],
            ];
        });
    }

    // ── Articles ─────────────────────────────────────────────────────────

    public function searchArticles(string $query, ?string $category = null, int $page = 1, int $limit = 10): array
    {
        return $this->articleQuery(
            search: $query,
            category: $category,
            page: $page,
            limit: $limit,
        );
    }

    public function latestArticles(int $limit = 10, ?string $category = null): array
    {
        return $this->articleQuery(search: '', category: $category, page: 1, limit: $limit);
    }

    /**
     * Shared list/search implementation. Only published posts are returned
     * (PostsService.posts already filters published=1).
     */
    protected function articleQuery(string $search, ?string $category, int $page, int $limit): array
    {
        $ttl = (int) config('mcp.cache_ttl', 300);
        $page = max(1, $page);
        $limit = max(1, min($limit, (int) config('mcp.max_limit', 20)));

        $categoryId = $this->resolveCategoryId($category);

        if ($categoryId !== null && $categoryId === false) {
            // Unknown category slug — return empty rather than guessing.
            return ['results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'has_more' => false]];
        }

        $cacheKey = 'mcp:articles:' . md5(serialize([$search, $categoryId, $page, $limit]));
        $cacheable = $search === '' && $ttl > 0;

        if ($cacheable) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        // Reuse the existing public read service. Note: category filtering is
        // done after hydrate via the taxonomy batch (cheap — ids already loaded).
        $rows = $this->posts->posts($page, $limit, $search);
        $total = $this->posts->postsCount($search);

        if ($categoryId !== null) {
            $rows = array_values(array_filter($rows, function (array $r) use ($categoryId) {
                foreach (($r['categories'] ?? []) as $cat) {
                    if ((int) ($cat['id'] ?? 0) === $categoryId) {
                        return true;
                    }
                }

                return false;
            }));
        }

        $result = [
            'results' => array_map($this->articleSummary(...), $rows),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => $page * $limit < $total,
            ],
        ];

        if ($cacheable) {
            Cache::put($cacheKey, $result, $ttl);
        }

        return $result;
    }

    public function getArticle(?string $slug, ?string $id): ?array
    {
        $post = null;

        if ($slug !== null && $slug !== '') {
            $post = $this->posts->postBySlug($slug);
        } elseif ($id !== null && $id !== '') {
            $post = $this->posts->postById((int) $id);
        }

        // Only serve published posts through MCP.
        if ($post === null || (int) ($post['published'] ?? 0) !== 1) {
            return null;
        }

        $related = $this->posts->relatedPosts((int) $post['id'], 3);

        return [
            'id' => (string) $post['id'],
            'title' => $post['title'] ?? '',
            'slug' => $post['slug'] ?? '',
            'excerpt' => $this->excerpt($post),
            'content' => $this->contentText($post['content'] ?? ''),
            'category' => $post['categories'][0]['name'] ?? null,
            'author' => $post['author'] ?? null,
            'published_at' => $post['published_at'] ?? ($post['created_at'] ?? null),
            'updated_at' => $post['updated_at'] ?? null,
            'featured_image' => $this->absoluteImage($post['image'] ?? null),
            'url' => $this->url('/posts/view/' . ($post['slug'] ?? '')),
            'tags' => array_map(
                fn (array $t) => ['name' => $t['name'], 'slug' => $t['slug']],
                $this->taxonomy->tagsForContent('post', (int) $post['id'])
            ),
            'related' => array_map($this->articleSummary(...), $related),
            'source' => 'BroxLab',
        ];
    }

    // ── Categories ───────────────────────────────────────────────────────

    public function categoryList(): array
    {
        $ttl = (int) config('mcp.cache_ttl', 300);

        return Cache::remember('mcp:categories', $ttl, function () {
            $rows = DB::table('categories')
                ->select('id', 'name', 'slug', 'description')
                ->where('status', 'active')
                ->orderBy('order')
                ->limit(50)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            // One grouped query for counts instead of per-category COUNTs.
            $counts = DB::table('posts')
                ->select('category_id', DB::raw('count(*) as c'))
                ->where('published', 1)
                ->groupBy('category_id')
                ->pluck('c', 'category_id');

            $categories = array_map(function (array $r) use ($counts) {
                return [
                    'id' => (string) $r['id'],
                    'name' => $r['name'],
                    'slug' => $r['slug'],
                    'description' => $r['description'] ?: null,
                    'article_count' => (int) ($counts[$r['id']] ?? 0),
                ];
            }, $rows);

            return ['categories' => $categories];
        });
    }

    public function categoryArticles(string $category, int $page = 1, int $limit = 10): array
    {
        return $this->articleQuery(search: '', category: $category, page: $page, limit: $limit);
    }

    /**
     * Resolve a category slug/name to an id. Returns null when no filter was
     * requested, and false when the category does not exist.
     */
    protected function resolveCategoryId(?string $category): int|false|null
    {
        if ($category === null || trim($category) === '') {
            return null;
        }

        $needle = mb_strtolower(trim($category));
        foreach ($this->categoryList()['categories'] as $cat) {
            if (mb_strtolower($cat['slug']) === $needle || mb_strtolower($cat['name']) === $needle) {
                return (int) $cat['id'];
            }
        }

        return false;
    }

    // ── Devices (mobiles) ────────────────────────────────────────────────

    public function searchDevices(string $query, ?string $brand = null, int $limit = 10): array
    {
        $limit = max(1, min($limit, (int) config('mcp.max_limit', 20)));

        if ($brand !== null && trim($brand) !== '') {
            $rows = $this->mobiles->list(1, 100, $query);
            $needle = mb_strtolower(trim($brand));
            $rows = array_values(array_filter($rows, fn (array $r) => mb_strtolower((string) ($r['brand_name'] ?? '')) === $needle));
        } else {
            $rows = $this->mobiles->list(1, $limit, $query);
        }

        return [
            'results' => array_slice(array_map($this->deviceSummary(...), $rows), 0, $limit),
            'pagination' => ['page' => 1, 'limit' => $limit, 'total' => count($rows), 'has_more' => false],
        ];
    }

    public function getDevice(?string $slug, ?string $id): ?array
    {
        $mobile = null;

        if ($id !== null && $id !== '') {
            $mobile = $this->mobiles->complete((int) $id);
        }

        if ($mobile === null && $slug !== null && $slug !== '') {
            // mobiles table has no slug column; treat slug as "brand-model"
            // search against model_name.
            $model = str_replace('-', ' ', $slug);
            $found = $this->mobiles->list(1, 5, $model);
            if ($found !== []) {
                $mobile = $this->mobiles->complete((int) $found[0]['id']);
            }
        }

        if ($mobile === null) {
            return null;
        }

        return $this->deviceDetail($mobile);
    }

    public function getDeviceSpecs(?string $slug, ?string $id): ?array
    {
        $mobile = $this->getDevice($slug, $id);

        if ($mobile === null) {
            return null;
        }

        return [
            'device' => ['name' => $mobile['name'], 'brand' => $mobile['brand']],
            'specifications' => $mobile['specifications'],
            'url' => $mobile['url'],
        ];
    }

    public function compareDevices(string $device1, string $device2): array
    {
        $a = $this->getDevice(slug: $device1);
        $b = $this->getDevice(slug: $device2);

        if ($a === null || $b === null) {
            return ['device1' => $a, 'device2' => $b, 'comparison' => null];
        }

        // Factual spec-by-spec diff — no subjective ranking.
        $specA = [];
        foreach ($a['specifications'] as $s) {
            $specA[mb_strtolower($s['key'])] = $s['value'];
        }
        $specB = [];
        foreach ($b['specifications'] as $s) {
            $specB[mb_strtolower($s['key'])] = $s['value'];
        }

        $comparison = [];
        $keys = array_unique(array_merge(array_keys($specA), array_keys($specB)));
        foreach ($keys as $key) {
            $va = $specA[$key] ?? null;
            $vb = $specB[$key] ?? null;
            if ($va !== null && $vb !== null) {
                $comparison[] = ['spec' => $key, 'device1' => $va, 'device2' => $vb, 'same' => $va === $vb];
            }
        }

        return [
            'device1' => ['name' => $a['name'], 'brand' => $a['brand'], 'price' => $a['price']],
            'device2' => ['name' => $b['name'], 'brand' => $b['brand'], 'price' => $b['price']],
            'comparison' => $comparison,
            'urls' => [$a['url'], $b['url']],
        ];
    }

    // ── Unified search ───────────────────────────────────────────────────

    public function searchSite(string $query, ?string $type = null, int $limit = 10): array
    {
        $limit = max(1, min($limit, (int) config('mcp.max_limit', 20)));
        $results = [];

        $types = $type !== null && $type !== '' ? [$type] : ['article', 'device'];

        if (in_array('article', $types, true)) {
            $articles = $this->searchArticles($query, null, 1, $limit)['results'];
            foreach ($articles as $a) {
                $results[] = ['type' => 'article', 'id' => $a['id'], 'title' => $a['title'], 'url' => $a['url'], 'description' => $a['excerpt']];
            }
        }

        if (in_array('device', $types, true) && count($results) < $limit) {
            $devices = $this->searchDevices($query, null, $limit)['results'];
            foreach ($devices as $d) {
                $results[] = ['type' => 'device', 'id' => $d['id'], 'title' => $d['name'], 'url' => $d['url'], 'description' => trim(($d['brand'] ?? '') . ' ' . ($d['price'] !== null ? '৳' . $d['price'] : ''))];
            }
        }

        if (in_array('category', $types, true) && count($results) < $limit) {
            foreach ($this->categoryList()['categories'] as $c) {
                if (count($results) >= $limit) {
                    break;
                }
                if (mb_stripos($c['name'] . ' ' . $c['slug'], $query) !== false) {
                    $results[] = ['type' => 'category', 'id' => $c['id'], 'title' => $c['name'], 'url' => $this->url('/posts?category=' . $c['slug']), 'description' => $c['description']];
                }
            }
        }

        return ['results' => array_slice($results, 0, $limit)];
    }

    // ── Shapers ──────────────────────────────────────────────────────────

    protected function articleSummary(array $post): array
    {
        return [
            'id' => (string) $post['id'],
            'title' => $post['title'] ?? '',
            'slug' => $post['slug'] ?? '',
            'excerpt' => $this->excerpt($post),
            'category' => $post['categories'][0]['name'] ?? null,
            'published_at' => $post['published_at'] ?? ($post['created_at'] ?? null),
            'url' => $this->url('/posts/view/' . ($post['slug'] ?? '')),
        ];
    }

    protected function deviceSummary(array $mobile): array
    {
        $price = $mobile['official_price'] ?? null;

        return [
            'id' => (string) $mobile['id'],
            'name' => trim(($mobile['brand_name'] ?? '') . ' ' . ($mobile['model_name'] ?? '')),
            'brand' => $mobile['brand_name'] ?? null,
            'slug' => isset($mobile['brand_name'], $mobile['model_name'])
                ? \Illuminate\Support\Str::slug($mobile['brand_name'] . ' ' . $mobile['model_name'])
                : null,
            'image' => $this->absoluteImage($mobile['image_path'] ?? null),
            'price' => $price !== null && $price !== '' ? (float) $price : null,
            'release_date' => $mobile['release_date'] ?? null,
            'url' => $this->url('/mobiles/view/' . $mobile['id']),
        ];
    }

    protected function deviceDetail(array $mobile): array
    {
        $summary = $this->deviceSummary($mobile);

        return array_merge($summary, [
            'status' => $mobile['status'] ?? null,
            'is_official' => (int) ($mobile['is_official'] ?? 0) === 1,
            'specifications' => array_map(
                fn (array $s) => ['key' => $s['spec_key'], 'value' => $s['spec_value']],
                $mobile['specifications'] ?? []
            ),
            'images' => array_values(array_filter(array_map(
                fn (array $i) => $this->absoluteImage($i['image_url'] ?? null),
                $mobile['images'] ?? []
            ))),
            'source' => 'BroxLab',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    protected function url(string $path): string
    {
        return config('mcp.site_url') . $path;
    }

    protected function absoluteImage(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return config('mcp.site_url') . '/' . ltrim($path, '/');
    }

    protected function excerpt(array $post): ?string
    {
        if (! empty($post['excerpt'])) {
            return mb_substr(strip_tags((string) $post['excerpt']), 0, 300);
        }

        return mb_substr(strip_tags((string) ($post['content'] ?? '')), 0, 200) ?: null;
    }

    /**
     * Article content for MCP output: strip nav/footer chrome, keep text.
     * Content is returned as plain text — it is data, never instructions.
     */
    protected function contentText(string $html): string
    {
        $text = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);
        $text = preg_replace('#<style[^>]*>.*?</style>#is', '', $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\n{3,}/u', "\n\n", $text);

        return mb_substr(trim((string) $text), 0, 20000);
    }
}
