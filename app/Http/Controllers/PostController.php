<?php

namespace App\Http\Controllers;

use App\Support\CommentService;
use App\Support\PostsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/PostsController.php — public read side:
 * /posts list, /posts/view?slug=, /posts/view/{slug}, /posts/{id}/{slug}, /posts/{id}.
 */
class PostController extends Controller
{
    public function __construct(
        protected PostsService $posts,
        protected CommentService $comments,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'latest');
        $order = (string) $request->query('order', 'DESC');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(6, min(60, (int) $request->query('per_page', 12)));

        $totalPosts = $this->posts->postsCount($search);
        $totalPages = (int) ceil($totalPosts / $perPage);

        $posts = $this->posts->posts($page, $perPage, $search, $sort, $order);

        // SEO rel=next/prev URLs (mirrors legacy controller)
        $queryParts = [];
        if ($search !== '') {
            $queryParts['search'] = $search;
        }
        if ($sort !== 'latest') {
            $queryParts['sort'] = $sort;
        }
        if ($order !== 'DESC') {
            $queryParts['order'] = $order;
        }
        if ($perPage !== 12) {
            $queryParts['per_page'] = $perPage;
        }

        $paginationNextUrl = $page < $totalPages ? '/posts?page='.($page + 1).$this->queryString($queryParts) : null;
        $paginationPrevUrl = $page > 1 ? '/posts'.($page - 1 > 1 ? '?page='.($page - 1) : '').$this->queryString($queryParts, $page - 1 > 1) : null;

        return view('pages.posts-list', [
            'title' => 'Articles',
            'posts' => $posts,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total_posts' => $totalPosts,
            'available_per_page' => [6, 12, 18, 24, 36, 60],
            'pagination_next_url' => $paginationNextUrl,
            'pagination_prev_url' => $paginationPrevUrl,
        ]);
    }

    public function view(Request $request, ?string $slug = null): View
    {
        $slug = trim((string) ($slug ?? $request->query('slug', '')));

        abort_if($slug === '', 404, 'Post slug missing');

        $post = $this->posts->postBySlug($slug);

        abort_if(! $post, 404, 'Post not found');

        return $this->renderPost($post);
    }

    public function viewById(int $id): View
    {
        $post = $this->posts->postById($id);

        abort_if(! $post, 404, 'Post not found');

        return $this->renderPost($post);
    }

    protected function renderPost(array $post): View
    {
        return view('pages.post-view', [
            'title' => $post['title'] ?? 'Post',
            'post' => $post,
            'previousPost' => $this->posts->previousPost((int) $post['id']),
            'nextPost' => $this->posts->nextPost((int) $post['id']),
            'relatedPosts' => $this->posts->relatedPosts((int) $post['id'], 3),
            'comments' => $this->comments->tree('post', (int) $post['id']),
        ]);
    }

    protected function queryString(array $parts, bool $hasPage = true): string
    {
        if (empty($parts)) {
            return '';
        }

        return ($hasPage ? '&' : '?').http_build_query($parts);
    }
}