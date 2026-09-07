<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Port of the legacy TagsCategoriesController + ContentModel archive reads:
 * category/tag lookups and the mixed-content UNION queries behind the
 * /category/{slug} and /tag/{slug} archive pages.
 */
class ArchiveService
{
    public function __construct(
        protected ContentImages $images,
        protected PostTaxonomy $taxonomy,
    ) {}

    public function categoryBySlug(string $slug): ?array
    {
        $row = DB::table('categories')->select('id', 'name', 'slug')->where('slug', $slug)->first();

        return $row ? (array) $row : null;
    }

    public function tagBySlug(string $slug): ?array
    {
        $row = DB::table('tags')->select('id', 'name', 'slug')->where('slug', $slug)->first();

        return $row ? (array) $row : null;
    }

    public function categories(int $page = 1, int $perPage = 12, string $search = '', string $sort = 'name'): array
    {
        $query = DB::table('categories')->select('id', 'name', 'slug');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $orderCol = $sort === 'name' ? 'name' : 'id';
        $rows = $query->orderBy($orderCol, $sort === 'name' ? 'ASC' : 'DESC')
            ->forPage(max(1, $page), max(1, $perPage))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        foreach ($rows as &$c) {
            $c['count'] = $this->contentByCategoryCount($c['slug']);
        }
        unset($c);

        return $rows;
    }

    public function categoriesCount(string $search = ''): int
    {
        $query = DB::table('categories');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return (int) $query->count();
    }

    public function tags(int $page = 1, int $perPage = 12, string $search = ''): array
    {
        $query = DB::table('tags')->select('id', 'name', 'slug');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $rows = $query->orderBy('name', 'ASC')
            ->forPage(max(1, $page), max(1, $perPage))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        foreach ($rows as &$t) {
            $t['count'] = $this->contentByTagCount($t['slug']);
        }
        unset($t);

        return $rows;
    }

    public function tagsCount(string $search = ''): int
    {
        $query = DB::table('tags');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return (int) $query->count();
    }

    /**
     * Mixed content under a category (posts + pages) — same UNION shape as legacy.
     */
    public function contentByCategory(string $slug, int $page = 1, int $perPage = 12): array
    {
        $offset = (max(1, $page) - 1) * max(1, $perPage);

        $rows = $this->categoryUnion($slug)
            ->orderBy('created_at', 'desc')
            ->limit(max(1, $perPage))
            ->offset($offset)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return $this->enrich($rows);
    }

    public function contentByCategoryCount(string $slug): int
    {
        return (int) DB::table('posts as p')
            ->join('content_categories as cc', fn ($j) => $j->on('cc.content_id', '=', 'p.id')->where('cc.content_type', '=', 'post'))
            ->join('categories as c', 'c.id', '=', 'cc.category_id')
            ->where('c.slug', $slug)
            ->where('p.published', 1)
            ->count()
            + (int) DB::table('pages as pg')
                ->join('content_categories as cc2', fn ($j) => $j->on('cc2.content_id', '=', 'pg.id')->where('cc2.content_type', '=', 'page'))
                ->join('categories as c2', 'c2.id', '=', 'cc2.category_id')
                ->where('c2.slug', $slug)
                ->where('pg.published', 1)
                ->count();
    }

    /**
     * Mixed content under a tag (posts + pages + mobiles + services).
     */
    public function contentByTag(string $slug, int $page = 1, int $perPage = 12): array
    {
        $offset = (max(1, $page) - 1) * max(1, $perPage);

        $rows = $this->tagUnion($slug)
            ->orderBy('created_at', 'desc')
            ->limit(max(1, $perPage))
            ->offset($offset)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return $this->enrich($rows);
    }

    public function contentByTagCount(string $slug): int
    {
        $counts = [];

        $counts[] = (int) DB::table('posts as p')
            ->join('content_tags as ct', fn ($j) => $j->on('ct.content_id', '=', 'p.id')->where('ct.content_type', '=', 'post'))
            ->join('tags as t', 't.id', '=', 'ct.tag_id')
            ->where('t.slug', $slug)
            ->where('p.published', 1)
            ->count();

        $counts[] = (int) DB::table('pages as pg')
            ->join('content_tags as ct2', fn ($j) => $j->on('ct2.content_id', '=', 'pg.id')->where('ct2.content_type', '=', 'page'))
            ->join('tags as t2', 't2.id', '=', 'ct2.tag_id')
            ->where('t2.slug', $slug)
            ->where('pg.published', 1)
            ->count();

        $counts[] = (int) DB::table('mobiles as m')
            ->join('content_tags as ct3', fn ($j) => $j->on('ct3.content_id', '=', 'm.id')->where('ct3.content_type', '=', 'mobile'))
            ->join('tags as t3', 't3.id', '=', 'ct3.tag_id')
            ->where('t3.slug', $slug)
            ->count();

        $counts[] = (int) DB::table('services as s')
            ->join('content_tags as ct4', fn ($j) => $j->on('ct4.content_id', '=', 's.id')->where('ct4.content_type', '=', 'service'))
            ->join('tags as t4', 't4.id', '=', 'ct4.tag_id')
            ->where('t4.slug', $slug)
            ->where('s.status', 'active')
            ->whereNull('s.deleted_at')
            ->count();

        return array_sum($counts);
    }

    protected function categoryUnion(string $slug)
    {
        $posts = DB::table('posts as p')
            ->join('content_categories as cc', fn ($j) => $j->on('cc.content_id', '=', 'p.id')->where('cc.content_type', '=', 'post'))
            ->join('categories as c', 'c.id', '=', 'cc.category_id')
            ->select(
                DB::raw("'post' as type"),
                'p.id',
                'p.title',
                'p.slug as url',
                'p.content',
                'p.created_at',
                'p.author',
                DB::raw('0 as is_premium'),
                DB::raw('NULL as status'),
                DB::raw('0 AS views, 0 AS impressions')
            )
            ->where('c.slug', $slug)
            ->where('p.published', 1);

        $pages = DB::table('pages as pg')
            ->join('content_categories as cc2', fn ($j) => $j->on('cc2.content_id', '=', 'pg.id')->where('cc2.content_type', '=', 'page'))
            ->join('categories as c2', 'c2.id', '=', 'cc2.category_id')
            ->select(
                DB::raw("'page' as type"),
                'pg.id',
                'pg.title',
                'pg.slug as url',
                'pg.content',
                'pg.created_at',
                DB::raw('NULL as author'),
                DB::raw('0 as is_premium'),
                DB::raw('NULL as status'),
                DB::raw('0 AS views, 0 AS impressions')
            )
            ->where('c2.slug', $slug)
            ->where('pg.published', 1);

        return $posts->unionAll($pages);
    }

    protected function tagUnion(string $slug)
    {
        $posts = DB::table('posts as p')
            ->join('content_tags as ct', fn ($j) => $j->on('ct.content_id', '=', 'p.id')->where('ct.content_type', '=', 'post'))
            ->join('tags as t', 't.id', '=', 'ct.tag_id')
            ->select(
                DB::raw("'post' as type"),
                'p.id',
                'p.title',
                'p.slug as url',
                'p.content',
                'p.created_at',
                'p.author',
                DB::raw('0 as is_premium'),
                DB::raw('NULL as status'),
                DB::raw('0 AS views, 0 AS impressions')
            )
            ->where('t.slug', $slug)
            ->where('p.published', 1);

        $pages = DB::table('pages as pg')
            ->join('content_tags as ct2', fn ($j) => $j->on('ct2.content_id', '=', 'pg.id')->where('ct2.content_type', '=', 'page'))
            ->join('tags as t2', 't2.id', '=', 'ct2.tag_id')
            ->select(
                DB::raw("'page' as type"),
                'pg.id',
                'pg.title',
                'pg.slug as url',
                'pg.content',
                'pg.created_at',
                DB::raw('NULL as author'),
                DB::raw('0 as is_premium'),
                DB::raw('NULL as status'),
                DB::raw('0 AS views, 0 AS impressions')
            )
            ->where('t2.slug', $slug)
            ->where('pg.published', 1);

        $mobiles = DB::table('mobiles as m')
            ->join('content_tags as ct3', fn ($j) => $j->on('ct3.content_id', '=', 'm.id')->where('ct3.content_type', '=', 'mobile'))
            ->join('tags as t3', 't3.id', '=', 'ct3.tag_id')
            ->select(
                DB::raw("'mobile' as type"),
                'm.id',
                DB::raw("CONCAT(m.brand_name, ' ', m.model_name) as title"),
                DB::raw("CONCAT('/mobiles/view/', m.id) as url"),
                DB::raw("(SELECT GROUP_CONCAT(CONCAT(ms.spec_key, ': ', ms.spec_value) SEPARATOR '\n') FROM mobile_specs ms WHERE ms.mobile_id = m.id) as content"),
                'm.created_at',
                DB::raw('NULL as author'),
                DB::raw('0 as is_premium'),
                DB::raw('NULL as status'),
                DB::raw('0 AS views, 0 AS impressions')
            )
            ->where('t3.slug', $slug);

        $services = DB::table('services as s')
            ->join('content_tags as ct4', fn ($j) => $j->on('ct4.content_id', '=', 's.id')->where('ct4.content_type', '=', 'service'))
            ->join('tags as t4', 't4.id', '=', 'ct4.tag_id')
            ->select(
                DB::raw("'service' as type"),
                's.id',
                's.name as title',
                DB::raw("CONCAT('/services/', s.slug) as url"),
                DB::raw('COALESCE(s.description, "") as content'),
                's.created_at',
                DB::raw('NULL as author'),
                's.is_premium',
                's.status',
                DB::raw('0 AS views, 0 AS impressions')
            )
            ->where('t4.slug', $slug)
            ->where('s.status', 'active')
            ->whereNull('s.deleted_at');

        return $posts->unionAll($pages)->unionAll($mobiles)->unionAll($services);
    }

    /**
     * Attach categories/tags + normalized image fields (legacy controller parity).
     */
    protected function enrich(array $rows): array
    {
        $postIds = [];
        $pageIds = [];
        $mobileIds = [];
        $serviceIds = [];

        foreach ($rows as $row) {
            match ($row['type']) {
                'post' => $postIds[] = (int) $row['id'],
                'page' => $pageIds[] = (int) $row['id'],
                'mobile' => $mobileIds[] = (int) $row['id'],
                'service' => $serviceIds[] = (int) $row['id'],
                default => null,
            };
        }

        $cats = $this->taxonomy->categoriesForContentBatch('post', $postIds)
            + $this->taxonomy->categoriesForContentBatch('page', $pageIds)
            + $this->taxonomy->categoriesForContentBatch('mobile', $mobileIds)
            + $this->taxonomy->categoriesForContentBatch('service', $serviceIds);
        $tags = $this->taxonomy->tagsForContentBatch('post', $postIds)
            + $this->taxonomy->tagsForContentBatch('page', $pageIds)
            + $this->taxonomy->tagsForContentBatch('mobile', $mobileIds)
            + $this->taxonomy->tagsForContentBatch('service', $serviceIds);

        foreach ($rows as &$row) {
            $id = (int) $row['id'];
            $row['categories'] = $cats[$id] ?? [];
            $row['tags'] = $tags[$id] ?? [];
            $row['images'] = $this->images->extractMultiple((string) ($row['content'] ?? ''), 3);
            $row['image'] = $this->images->extractFirst($row['content'] ?? null);
        }
        unset($row);

        return $rows;
    }
}