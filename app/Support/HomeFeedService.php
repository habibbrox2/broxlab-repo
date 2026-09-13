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
     *
     * Uses a three-phase approach to avoid the slow UNION ALL + filesort:
     * 1) Fetch lightweight ID-only rows from each table (uses indexes).
     * 2) Merge-sort in PHP, then paginate.
     * 3) Fetch full details only for the page of results.
     */
    public function unifiedContent(int $page = 1, int $limit = 12, string $sort = 'latest'): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        // Phase 1: lightweight ID + timestamp rows (index-only scans)
        $mobileIds = DB::table('mobiles')
            ->select('id', 'created_at as published_at')
            ->orderByDesc('created_at')
            ->limit($limit + $offset)
            ->pluck('published_at', 'id')
            ->all();

        $pageIds = DB::table('pages')
            ->select('id', 'created_at as published_at')
            ->where('published', 1)
            ->orderByDesc('created_at')
            ->limit($limit + $offset)
            ->pluck('published_at', 'id')
            ->all();

        $postIds = DB::table('posts')
            ->select('id', 'published_at')
            ->where('published', 1)
            ->orderByDesc('published_at')
            ->limit($limit + $offset)
            ->pluck('published_at', 'id')
            ->all();

        // Phase 2: merge-sort in PHP by published_at DESC
        $all = [];
        foreach ($mobileIds as $id => $ts) {
            $all[] = ['id' => $id, 'ts' => $ts, 'type' => 'mobile'];
        }
        foreach ($pageIds as $id => $ts) {
            $all[] = ['id' => $id, 'ts' => $ts, 'type' => 'page'];
        }
        foreach ($postIds as $id => $ts) {
            $all[] = ['id' => $id, 'ts' => $ts, 'type' => 'post'];
        }
        usort($all, fn ($a, $b) => strcmp((string) $b['ts'], (string) $a['ts']));

        $total = count($all);
        $sliced = array_slice($all, $offset, $limit);

        if (empty($sliced)) {
            return ['contents' => [], 'total_pages' => (int) ceil($total / $limit)];
        }

        // Phase 3: batch-fetch full details for selected IDs
        $byType = ['mobile' => [], 'page' => [], 'post' => []];
        foreach ($sliced as $item) {
            $byType[$item['type']][] = (int) $item['id'];
        }

        $rows = [];

        if ($byType['mobile']) {
            $mobileRows = DB::table('mobiles as m')
                ->leftJoin('mobile_images as img', function ($join) {
                    $join->on('m.id', '=', 'img.mobile_id')
                        ->whereRaw('img.id = (SELECT MIN(id) FROM mobile_images WHERE mobile_id = m.id)');
                })
                ->whereIn('m.id', $byType['mobile'])
                ->select(
                    'm.id', 'm.brand_name as title', 'm.model_name as subtitle',
                    'img.image_url as image', 'm.created_at',
                    DB::raw("'mobile' as type"),
                    DB::raw('NULL as url'),
                    'm.official_price', 'm.unofficial_price',
                    'm.is_official', 'm.status', 'm.release_date'
                )
                ->get()->keyBy('id');
            foreach ($byType['mobile'] as $id) {
                if (isset($mobileRows[$id])) {
                    $rows[] = (array) $mobileRows[$id];
                }
            }
        }

        if ($byType['page']) {
            $pageRows = DB::table('pages as p')
                ->whereIn('p.id', $byType['page'])
                ->select(
                    'p.id', 'p.title',
                    DB::raw('SUBSTRING(p.content, 1, 500) as subtitle'),
                    DB::raw('NULL as image'), 'p.created_at',
                    DB::raw("'page' as type"), 'p.slug as url',
                    DB::raw('NULL as official_price'), DB::raw('NULL as unofficial_price'),
                    DB::raw('NULL as is_official'), DB::raw('NULL as status'), DB::raw('NULL as release_date')
                )
                ->get()->keyBy('id');
            foreach ($byType['page'] as $id) {
                if (isset($pageRows[$id])) {
                    $rows[] = (array) $pageRows[$id];
                }
            }
        }

        if ($byType['post']) {
            $postRows = DB::table('posts as po')
                ->whereIn('po.id', $byType['post'])
                ->select(
                    'po.id', 'po.title',
                    DB::raw('SUBSTRING(po.content, 1, 500) as subtitle'),
                    DB::raw('NULL as image'), 'po.created_at',
                    DB::raw('COALESCE(po.published_at, po.created_at) as published_at'),
                    DB::raw("'post' as type"), 'po.slug as url',
                    DB::raw('NULL as official_price'), DB::raw('NULL as unofficial_price'),
                    DB::raw('NULL as is_official'), DB::raw('NULL as status'), DB::raw('NULL as release_date')
                )
                ->get()->keyBy('id');
            foreach ($byType['post'] as $id) {
                if (isset($postRows[$id])) {
                    $rows[] = (array) $postRows[$id];
                }
            }
        }

        // Re-sort by published_at DESC (maintains original merge order)
        usort($rows, function ($a, $b) {
            $ta = $a['published_at'] ?? $a['created_at'] ?? '';
            $tb = $b['published_at'] ?? $b['created_at'] ?? '';
            return strcmp((string) $tb, (string) $ta);
        });

        $postIdsArr = [];
        $pageIdsArr = [];
        foreach ($rows as $row) {
            if ($row['type'] === 'post') {
                $postIdsArr[] = (int) $row['id'];
            } elseif ($row['type'] === 'page') {
                $pageIdsArr[] = (int) $row['id'];
            }
        }

        $postCats = $this->taxonomy->categoriesForContentBatch('post', $postIdsArr);
        $postTags = $this->taxonomy->tagsForContentBatch('post', $postIdsArr);
        $pageCats = $this->taxonomy->categoriesForContentBatch('page', $pageIdsArr);

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
        // Aggregate ratings with JOIN instead of correlated subqueries,
        // then fetch content only for top rows.
        $topIds = DB::table('posts as p')
            ->leftJoin('content_ratings as cr', function ($join) {
                $join->on('p.id', '=', 'cr.content_id')
                    ->where('cr.content_type', '=', 'post');
            })
            ->select(
                'p.id',
                DB::raw('ROUND(COALESCE(AVG(cr.rating), 0), 1) AS rating_average'),
                DB::raw('COUNT(cr.id) AS rating_total')
            )
            ->where('p.published', 1)
            ->groupBy('p.id')
            ->orderByDesc(DB::raw('rating_average'))
            ->orderByDesc(DB::raw('rating_total'))
            ->orderByDesc('p.published_at')
            ->limit(max(1, $limit))
            ->pluck('id')
            ->all();

        if (empty($topIds)) {
            return [];
        }

        $rows = DB::table('posts as p')
            ->leftJoin('content_ratings as cr', function ($join) {
                $join->on('p.id', '=', 'cr.content_id')
                    ->where('cr.content_type', '=', 'post');
            })
            ->select(
                'p.id',
                'p.title',
                'p.slug',
                DB::raw('SUBSTRING(p.content, 1, 500) as content'),
                'p.created_at',
                DB::raw('ROUND(COALESCE(AVG(cr.rating), 0), 1) AS rating_average'),
                DB::raw('COUNT(cr.id) AS rating_total')
            )
            ->whereIn('p.id', $topIds)
            ->groupBy('p.id', 'p.title', 'p.slug', 'p.content', 'p.created_at')
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