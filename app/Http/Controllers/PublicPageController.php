<?php

namespace App\Http\Controllers;

use App\Support\CommentService;
use App\Support\PostTaxonomy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Port of the legacy public PagesController route — GET /pages/view/{slug}
 * (the CMS "pages" the admin creates under /admin/pages).
 */
class PublicPageController extends Controller
{
    public function __construct(
        protected PostTaxonomy $taxonomy,
        protected CommentService $comments,
    ) {}

    public function view(Request $request, ?string $slug = null): View
    {
        $slug = trim((string) ($slug ?? $request->query('slug')));
        if ($slug === '') {
            abort(404, 'Page slug missing');
        }

        $page = DB::table('pages')->where('slug', $slug)->first();
        if (! $page) {
            abort(404, 'Page not found');
        }

        $page = (array) $page;
        $page['tags'] = $this->taxonomy->tagsForContent('page', (int) $page['id']);
        $page['categories'] = $this->taxonomy->categoriesForContent('page', (int) $page['id']);

        // Prev / next navigation (legacy getPreviousPage / getNextPage).
        $previous = DB::table('pages')
            ->where('published', 1)
            ->where('id', '<', (int) $page['id'])
            ->orderByDesc('id')
            ->first(['id', 'title', 'slug']);
        $next = DB::table('pages')
            ->where('published', 1)
            ->where('id', '>', (int) $page['id'])
            ->orderBy('id')
            ->first(['id', 'title', 'slug']);

        // Related pages: same category first, newest fallback, limited to 3.
        $related = $this->relatedPages((int) $page['id'], $page['categories'], 3);

        return view('pages.page-view', [
            'title' => $page['title'] ?? '',
            'page' => $page,
            'previousPage' => $previous ? (array) $previous : null,
            'nextPage' => $next ? (array) $next : null,
            'relatedPages' => $related,
            'comments' => $this->comments->tree('page', (int) $page['id']),
        ]);
    }

    /**
     * Related pages — legacy getRelatedPages(): same-category pages first
     * (ordered by id DESC), then any published page as fallback, sliced to 3.
     */
    protected function relatedPages(int $pageId, array $categories, int $limit): array
    {
        $categoryIds = array_map(fn ($c) => (int) $c['id'], $categories);

        $related = [];
        if (! empty($categoryIds)) {
            $related = DB::table('pages as p')
                ->join('content_categories as cc', 'cc.content_id', '=', 'p.id')
                ->select('p.id', 'p.title', 'p.slug', 'p.created_at', 'p.updated_at')
                ->where('p.published', 1)
                ->where('p.id', '!=', $pageId)
                ->whereIn('cc.category_id', $categoryIds)
                ->where('cc.content_type', 'page')
                ->orderByDesc('p.id')
                ->limit($limit)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        }

        if (count($related) < $limit) {
            $seen = array_merge([$pageId], array_map(fn ($r) => (int) $r['id'], $related));
            $fallback = DB::table('pages')
                ->where('published', 1)
                ->whereNotIn('id', $seen)
                ->orderByDesc('id')
                ->limit($limit - count($related))
                ->get(['id', 'title', 'slug', 'created_at', 'updated_at'])
                ->map(fn ($r) => (array) $r)
                ->all();
            $related = array_merge($related, $fallback);
        }

        foreach ($related as &$rp) {
            $rp['type'] = 'page';
            $rp['url'] = $rp['slug'];
            $rp['image'] = null;
        }
        unset($rp);

        return $related;
    }
}