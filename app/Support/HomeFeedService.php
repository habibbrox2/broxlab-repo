<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Port of the legacy HomeModel public-read methods (unified feed, homepage
 * stats, top posts/services, latest mobiles) using the query builder so the
 * SQL stays faithful to the legacy queries.
 */
class HomeFeedService
{
    public function __construct(
        protected ContentImages $images,
        protected PostTaxonomy $taxonomy,
    ) {}

    /**
     * Unified feed: mobiles ∪ pages ∪ posts, ordered by published_at DESC.
     */
    public function unifiedContent(int $page = 1, int $limit = 12, string $sort = 'latest'): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $mobiles = DB::table('mobiles as m')
            ->leftJoin('mobile_images as img', function ($join) {
                $join->on('m.id', '=', 'img.mobile_id')
                    ->whereRaw('img.id = (SELECT MIN(id) FROM mobile_images WHERE mobile_id = m.id)');
            })
            ->select(
                'm.id',
                'm.brand_name as title',
                'm.model_name as subtitle',
                'img.image_url as image',
                'm.created_at',
                'm.created_at as published_at',
                DB::raw("'mobile' as type"),
                DB::raw('NULL as url'),
                'm.official_price',
                'm.unofficial_price',
                'm.is_official',
                'm.status',
                'm.release_date'
            );

        $pages = DB::table('pages as p')
            ->select(
                'p.id',
                'p.title',
                'p.content as subtitle',
                DB::raw('NULL as image'),
                'p.created_at',
                'p.created_at as published_at',
                DB::raw("'page' as type"),
                'p.slug as url',
                DB::raw('NULL as official_price'),
                DB::raw('NULL as unofficial_price'),
                DB::raw('NULL as is_official'),
                DB::raw('NULL as status'),
                DB::raw('NULL as release_date')
            )
            ->whereIn('p.published', [1, '1', 0, '0']);

        $posts = DB::table('posts as po')
            ->select(
                'po.id',
                'po.title',
                'po.content as subtitle',
                DB::raw('NULL as image'),
                'po.created_at',
                DB::raw('COALESCE(po.published_at, po.created_at) as published_at'),
                DB::raw("'post' as type"),
                'po.slug as url',
                DB::raw('NULL as official_price'),
                DB::raw('NULL as unofficial_price'),
                DB::raw('NULL as is_official'),
                DB::raw('NULL as status'),
                DB::raw('NULL as release_date')
            )
            ->whereIn('po.published', [1, '1', 0, '0']);

        $rows = $mobiles->unionAll($pages)->unionAll($posts)
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $postIds = [];
        $pageIds = [];
        foreach ($rows as $row) {
            if ($row['type'] === 'post') {
                $postIds[] = (int) $row['id'];
            } elseif ($row['type'] === 'page') {
                $pageIds[] = (int) $row['id'];
            }
        }

        $postCats = $this->taxonomy->categoriesForContentBatch('post', $postIds);
        $postTags = $this->taxonomy->tagsForContentBatch('post', $postIds);
        $pageCats = $this->taxonomy->categoriesForContentBatch('page', $pageIds);

        foreach ($rows as &$row) {
            $images = [];
            if ($row['type'] === 'mobile' && ! empty($row['image'])) {
                $images[] = $row['image'];
            } elseif ($row['type'] === 'post' || $row['type'] === 'page') {
                $images = $this->images->extractMultiple((string) $row['subtitle'], 3);
            }
            $row['images'] = $images;

            $id = (int) $row['id'];
            $row['categories'] = $row['type'] === 'mobile' ? [] : ($postCats[$id] ?? $pageCats[$id] ?? []);
            $row['tags'] = ($row['type'] === 'post' && isset($postTags[$id])) ? $postTags[$id] : [];
        }
        unset($row);

        // Total pages — same SUM-of-counts as legacy
        $total = (int) DB::table('mobiles')->count()
            + (int) DB::table('pages')->whereIn('published', [1, '1', 0, '0'])->count()
            + (int) DB::table('posts')->whereIn('published', [1, '1', 0, '0'])->count();

        return [
            'contents' => $rows,
            'total_pages' => (int) ceil($total / $limit),
        ];
    }

    public function homepageStats(): array
    {
        $activeUsers = (int) DB::table('users as u')
            ->leftJoin('activity_logs as al', 'u.id', '=', 'al.user_id')
            ->where(function ($q) {
                $q->where('al.created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 30 DAY)'))
                    ->orWhere('u.created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 30 DAY)'));
            })
            ->distinct()
            ->count('u.id');

        $deviceSpecs = (int) DB::table('mobiles')->count();
        $articles = (int) DB::table('posts')->where('published', 1)->count();

        $jobPosts = 0;
        if (DB::getSchemaBuilder()->hasTable('job_posts')) {
            $jobPosts = (int) DB::table('job_posts')
                ->whereIn('status', ['published', 'active'])
                ->count();
        }

        return [
            'active_users' => $activeUsers,
            'device_specs' => $deviceSpecs,
            'articles' => $articles,
            'job_posts' => $jobPosts,
        ];
    }

    public function topPosts(int $limit = 8): array
    {
        $rows = DB::table('posts as p')
            ->select(
                'p.id',
                'p.title',
                'p.slug',
                'p.content',
                'p.created_at',
                DB::raw('(SELECT ROUND(AVG(cr.rating), 1) FROM content_ratings cr WHERE cr.content_type = "post" AND cr.content_id = p.id) AS rating_average'),
                DB::raw('(SELECT COUNT(*) FROM content_ratings cr WHERE cr.content_type = "post" AND cr.content_id = p.id) AS rating_total')
            )
            ->where('p.published', 1)
            ->orderByDesc(DB::raw('rating_average'))
            ->orderByDesc(DB::raw('rating_total'))
            ->orderByDesc(DB::raw('COALESCE(p.published_at, p.created_at)'))
            ->limit(max(1, $limit))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        foreach ($rows as &$row) {
            $images = $this->images->extractMultiple((string) ($row['content'] ?? ''), 1);
            $row['image'] = $images[0] ?? '';
            $row['type'] = 'post';
            $row['rating_average'] = (float) ($row['rating_average'] ?? 0);
            $row['rating_total'] = (int) ($row['rating_total'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    public function topServices(int $limit = 8): array
    {
        $rows = DB::table('services as s')
            ->select(
                's.id',
                's.name',
                's.slug',
                's.description',
                's.created_at',
                's.status',
                DB::raw('(SELECT COALESCE(si.thumbnail_path, si.image_path) FROM service_images si WHERE si.service_id = s.id AND si.deleted_at IS NULL ORDER BY si.is_featured DESC, si.display_order ASC, si.id ASC LIMIT 1) AS image'),
                DB::raw('(SELECT ROUND(AVG(cr.rating), 1) FROM content_ratings cr WHERE cr.content_type = "service" AND cr.content_id = s.id) AS rating_average'),
                DB::raw('(SELECT COUNT(*) FROM content_ratings cr WHERE cr.content_type = "service" AND cr.content_id = s.id) AS rating_total')
            )
            ->where('s.status', 'active')
            ->whereNull('s.deleted_at')
            ->orderByDesc(DB::raw('rating_average'))
            ->orderByDesc(DB::raw('rating_total'))
            ->orderByDesc('s.created_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        foreach ($rows as &$row) {
            $row['title'] = $row['name'] ?? 'Service';
            $img = trim((string) ($row['image'] ?? ''));
            // Legacy rows may still carry the retired /public_html web-root
            // prefix; normalise it to the current web root.
            if (str_starts_with($img, '/public_html/')) {
                $img = substr($img, strlen('/public_html'));
            } elseif (str_starts_with($img, '/public/')) {
                $img = substr($img, strlen('/public'));
            }
            $row['image'] = $img;
            $row['type'] = 'service';
            $row['rating_average'] = (float) ($row['rating_average'] ?? 0);
            $row['rating_total'] = (int) ($row['rating_total'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    /**
     * Homepage services — simplified port of ServiceModel::getHomepageServices
     * (no per-service enrichment beyond the featured image).
     */
    public function homepageServices(int $limit = 15): array
    {
        return DB::table('services as s')
            ->select(
                's.*',
                DB::raw('(SELECT COALESCE(si.thumbnail_path, si.image_path) FROM service_images si WHERE si.service_id = s.id AND si.deleted_at IS NULL ORDER BY si.is_featured DESC, si.display_order ASC, si.id ASC LIMIT 1) AS featured_image')
            )
            ->whereNull('s.deleted_at')
            ->orderByDesc('s.created_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Category options for the discovery-feed toolbar. The legacy view read
     * the global `categories`; the port keeps it scoped to the feed service.
     */
    public function feedCategories(): array
    {
        return DB::table('categories')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function latestMobiles(int $limit = 8): array
    {
        $rows = DB::table('mobiles as m')
            ->select(
                'm.id',
                'm.brand_name',
                'm.model_name',
                'm.official_price',
                'm.unofficial_price',
                'm.is_official',
                'm.status',
                'm.release_date',
                'm.created_at',
                DB::raw('(SELECT img.image_url FROM mobile_images img WHERE img.mobile_id = m.id ORDER BY img.id ASC LIMIT 1) AS image_path')
            )
            ->orderByDesc('m.created_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        foreach ($rows as &$row) {
            $row['type'] = 'mobile';
            $row['title'] = trim((string) ($row['brand_name'] ?? ''));
            $row['subtitle'] = trim((string) ($row['model_name'] ?? ''));
            $row['image'] = $row['image_path'] ?? null;
            $row['images'] = ! empty($row['image_path']) ? [$row['image_path']] : [];
            $row['official_price'] = (float) ($row['official_price'] ?? 0);
            $row['unofficial_price'] = (float) ($row['unofficial_price'] ?? 0);
            $row['is_official'] = (int) ($row['is_official'] ?? 0);
        }
        unset($row);

        return $rows;
    }
}