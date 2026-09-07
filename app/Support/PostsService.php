<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Port of the legacy ContentModel public-read methods for posts.
 * SQL stays faithful to the legacy queries (including the absence of a
 * soft-delete filter — legacy public post queries never filter deleted_at).
 */
class PostsService
{
    public function __construct(
        protected ContentImages $images,
        protected PostTaxonomy $taxonomy,
    ) {}

    public function posts(int $page = 1, int $perPage = 12, string $search = '', string $sort = 'latest', string $order = 'DESC'): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // legacy maps 'latest'/'oldest' through getPosts' allowed-sort list
        $column = match ($sort) {
            'oldest' => 'created_at',
            'title' => 'title',
            default => 'created_at',
        };

        $query = DB::table('posts as p')
            ->select('p.*', DB::raw('0 AS views, 0 AS impressions'))
            ->where('p.published', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('p.title', 'like', "%{$search}%")
                    ->orWhere('p.content', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderBy('p.'.$column, $order)
            ->limit($perPage)
            ->offset($offset)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $ids = array_map(fn ($r) => (int) $r['id'], $rows);
        $categories = $this->taxonomy->categoriesForContentBatch('post', $ids);

        foreach ($rows as &$row) {
            $row['image'] = $this->images->extractFirst($row['content'] ?? null);
            $row['images'] = $this->images->extractMultiple((string) ($row['content'] ?? ''), 3);
            $row['categories'] = $categories[(int) $row['id']] ?? [];
        }
        unset($row);

        return $rows;
    }

    public function postsCount(string $search = ''): int
    {
        $query = DB::table('posts')->where('published', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        return (int) $query->count();
    }

    /**
     * Legacy parity: no published filter on slug lookup.
     */
    public function postBySlug(string $slug): ?array
    {
        $row = DB::table('posts as p')
            ->select('p.*', DB::raw('0 AS views, 0 AS impressions'))
            ->where('p.slug', $slug)
            ->first();

        if (! $row) {
            return null;
        }

        return $this->hydratePost((array) $row);
    }

    public function postById(int $id): ?array
    {
        $row = DB::table('posts as p')
            ->select('p.*', DB::raw('0 AS views, 0 AS impressions'))
            ->where('p.id', $id)
            ->first();

        if (! $row) {
            return null;
        }

        return $this->hydratePost((array) $row);
    }

    protected function hydratePost(array $row): array
    {
        $row['image'] = $this->images->extractFirst($row['content'] ?? null);
        $row['images'] = $this->images->extractMultiple((string) ($row['content'] ?? ''), 3);
        $row['tags'] = $this->taxonomy->tagsForContent('post', (int) $row['id']);
        $row['categories'] = $this->taxonomy->categoriesForContent('post', (int) $row['id']);

        return $row;
    }

    public function previousPost(int $id): ?array
    {
        $row = DB::table('posts as p')
            ->select('p.*', 'p.slug as url')
            ->where('p.id', '<', $id)
            ->orderByDesc('p.id')
            ->first();

        return $row ? (array) $row : null;
    }

    public function nextPost(int $id): ?array
    {
        $row = DB::table('posts as p')
            ->select('p.*', 'p.slug as url')
            ->where('p.id', '>', $id)
            ->orderBy('p.id')
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Random published posts excluding the current one (legacy query shape).
     */
    public function relatedPosts(int $postId, int $limit = 3): array
    {
        $rows = DB::table('posts as p')
            ->select('p.*', DB::raw('0 AS views, 0 AS impressions'))
            ->where('p.published', 1)
            ->where('p.id', '!=', $postId)
            ->where('p.id', '>=', DB::raw('(SELECT FLOOR(RAND() * (SELECT MAX(id) FROM posts)))'))
            ->orderBy('p.id')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        if (count($rows) < $limit) {
            $extra = DB::table('posts as p')
                ->select('p.*', DB::raw('0 AS views, 0 AS impressions'))
                ->where('p.published', 1)
                ->where('p.id', '!=', $postId)
                ->orderBy('p.id')
                ->limit($limit - count($rows))
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
            $rows = array_merge($rows, $extra);
        }

        foreach ($rows as &$row) {
            $row['image'] = $this->images->extractFirst($row['content'] ?? null);
            $row['type'] = 'post';
            $row['tags'] = $this->taxonomy->tagsForContent('post', (int) $row['id']);
            $row['categories'] = $this->taxonomy->categoriesForContent('post', (int) $row['id']);
        }
        unset($row);

        return array_slice($rows, 0, $limit);
    }
}