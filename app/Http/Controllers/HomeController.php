<?php

namespace App\Http\Controllers;

use App\Support\HomeFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/HomeController.php — the public home
 * page (`/`). Reads flow through HomeFeedService (HomeModel parity).
 */
class HomeController extends Controller
{
    public function __construct(
        protected HomeFeedService $feed,
    ) {}

    public function home(Request $request): View
    {
        $page = max(1, (int) $request->query('page', 1));
        $limit = 12; // legacy HOMEPAGE_FEED_LIMIT
        $sort = 'latest';

        $data = $this->feed->unifiedContent($page, $limit, $sort);

        return view('pages.home', [
            'title' => 'Home',
            'contents' => $data['contents'],
            'total_pages' => $data['total_pages'],
            'current_page' => $page,
            'homepage_feed_limit' => $limit,
            'sort' => $sort,
            'stats' => $this->feed->homepageStats(),
            'top_posts' => $this->feed->topPosts(8),
            'top_services' => $this->feed->topServices(8),
            'homepage_services' => $this->feed->homepageServices(15),
            'latest_mobiles' => $this->feed->latestMobiles(8),
            // Category options for the discovery-feed toolbar (legacy used the
            // Twig `categories` global; the port reads them from the service).
            'feed_categories' => $this->feed->feedCategories(),
        ]);
    }

    /**
     * Port of legacy GET /api/feed/load-more — server-rendered feed HTML for
     * the discovery dashboard's Load More / infinite scroll.
     */
    public function loadMore(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $limit = 12; // legacy HOMEPAGE_FEED_LIMIT
        $sort = 'latest';

        try {
            $data = $this->feed->unifiedContent($page, $limit, $sort);

            $html = view('partials.public.home-feed-items', [
                'items' => $data['contents'],
                'startIndex' => (($page - 1) * $limit) + 1,
                'emptyText' => 'No content available at the moment.',
            ])->render();

            return response()->json([
                'success' => true,
                'feed' => 'recent',
                'html' => $html,
                'items' => $data['contents'],
                'current_page' => $page,
                'total_pages' => $data['total_pages'],
                'has_more' => $page < $data['total_pages'],
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load items',
                'error_code' => 'internal_error',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }
}