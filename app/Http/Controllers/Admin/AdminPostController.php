<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPostService;
use App\Support\PostTaxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/PostsController.php — the /admin/posts
 * CRUD plus the two AJAX endpoints (check_permalink, autosave). The public
 * read side (/posts, /posts/view/{slug}) was already ported in Phase 4.
 *
 * Parity notes:
 * - Route style differs (legacy uses ?id= query strings; here /{id} paths are
 *   registered AND the legacy query-string form is honoured, so existing
 *   links like /admin/posts/edit?id=5 keep working).
 * - Content is sanitized through HTMLPurifier with the same allowed-tags
 *   config as legacy PurifierHelper, then image-watermarked (no-op outside
 *   legacy's /uploads layout).
 * - Push notification + author-approval notification are non-fatal, same as
 *   legacy try/catch blocks.
 */
class AdminPostController extends Controller
{
    public function __construct(protected AdminPostService $posts, protected PostTaxonomy $taxonomy) {}

    // ── List ──────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $page = max(1, (int) $request->query('page', '1'));
        $limit = max(5, min(100, (int) $request->query('limit', '20')));
        $search = $this->sanitizeInput((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'created_at');
        $order = (string) $request->query('order', 'DESC');
        $status = (string) $request->query('status', '');

        $filters = [];
        if ($status !== '' && in_array($status, ['draft', 'published'], true)) {
            $filters['status'] = $status;
        }

        $total = $this->posts->getPostsCount($search, $filters);

        return view('admin.posts.index', [
            'posts' => $this->posts->getPosts($page, $limit, $search, $sort, $order, $filters),
            'pagination' => [
                'current_page' => $page,
                'total_pages' => (int) max(1, ceil($total / $limit)),
                'limit' => $limit,
                'per_page' => $limit,
                'total' => $total,
                'from' => ($page - 1) * $limit + 1,
                'to' => min($page * $limit, $total),
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
                'status' => $status,
            ],
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
            'status' => $status,
            'limit' => $limit,
        ]);
    }

    // ── View ──────────────────────────────────────────────────────────

    public function show(Request $request, ?int $id = null): View
    {
        $id = $id ?? (int) $request->query('id', '0');

        return view('admin.posts.show', [
            'post' => $this->posts->getPostById($id),
            'tags' => $this->taxonomy->tagsForContent('post', $id),
            'categories' => $this->taxonomy->categoriesForContent('post', $id),
        ]);
    }

    // ── Create ────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.posts.form', [
            'title' => 'Add New Post',
            'isCreate' => true,
            'item' => null,
            'categories' => $this->allCategories(),
            'allTags' => $this->allTags(),
            'selectedTags' => [],
            'selectedCategories' => [],
            'status' => 'published',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data === null) {
            return redirect('/admin/posts/create')->with('error', 'Failed to create post');
        }

        $slug = $data['slug'] !== '' ? $data['slug'] : $this->posts->generateUniquePermalink($data['title']);
        $published = $data['status'] === 'published' ? 1 : 0;
        $publishedAt = $published ? now()->format('Y-m-d H:i:s') : null;

        $postId = $this->posts->createPost(
            $data['title'],
            $data['content'],
            $data['author'],
            $slug,
            $published,
            $data['reader_indexing'],
            $publishedAt,
            null,
            $data['meta_title'],
            $data['meta_description'],
        );

        if (! $postId) {
            $this->posts->logActivity('Post Creation Failed', 'post', 0, ['title' => $data['title']], 'failure');

            return redirect('/admin/posts/create')->with('error', 'Failed to create post');
        }

        $categoryIds = $this->attachTaxonomy($postId, $data);

        $this->posts->logActivity('Post Created', 'post', $postId, ['title' => $data['title'], 'status' => $data['status']], 'success');

        // Author-approval push on publish (non-fatal, legacy try/catch parity)
        if ($published === 1) {
            $this->posts->notifyPostApproval($postId, $data['title'], (int) (Auth::id() ?? 0), (int) (Auth::id() ?? 0));
        }

        return redirect('/admin/posts')->with('status', 'Post created successfully');
    }

    // ── Edit ──────────────────────────────────────────────────────────

    public function edit(Request $request, ?int $id = null): View
    {
        $id = $id ?? (int) $request->query('id', '0');
        $post = $this->posts->getPostById($id);

        $status = (! empty($post->published)) ? 'published' : 'draft';

        return view('admin.posts.form', [
            'title' => 'Edit Post',
            'isCreate' => false,
            'item' => $post,
            'categories' => $this->allCategories(),
            'allTags' => $this->allTags(),
            'selectedTags' => $this->taxonomy->tagsForContent('post', $id),
            'selectedCategories' => $this->taxonomy->categoriesForContent('post', $id),
            'status' => $status,
        ]);
    }

    public function update(Request $request, ?int $id = null): RedirectResponse
    {
        $id = $id ?? (int) $request->input('id', '0');

        $data = $this->validated($request);
        if ($data === null) {
            return redirect("/admin/posts/edit?id={$id}")->with('error', 'Failed to update post');
        }

        $slug = $data['slug'] !== '' ? $data['slug'] : $this->posts->generateUniquePermalink($data['title'], $id);
        $published = $data['status'] === 'published' ? 1 : 0;

        $previous = $this->posts->getPostById($id);
        $wasPublished = $previous ? (bool) ($previous->published ?? false) : false;

        $publishedAt = null;
        if ($published && ! $wasPublished) {
            $publishedAt = now()->format('Y-m-d H:i:s');
        }

        $ok = $this->posts->updatePost(
            $id,
            $data['title'],
            $data['content'],
            $slug,
            $published,
            $data['reader_indexing'],
            $publishedAt,
            $data['meta_title'],
            $data['meta_description'],
        );

        if (! $ok) {
            $this->posts->logActivity('Post Update Failed', 'post', $id, ['title' => $data['title']], 'failure');

            return redirect("/admin/posts/edit?id={$id}")->with('error', 'Failed to update post');
        }

        $this->attachTaxonomy($id, $data);

        // Draft → published transition notifies the author (legacy parity)
        if ($published && ! $wasPublished && $previous) {
            $authorId = (int) ($previous->user_id ?? 0);
            if ($authorId > 0 && $authorId !== (int) Auth::id()) {
                $this->posts->notifyPostApproval($id, $data['title'], $authorId, (int) (Auth::id() ?? 0));
            }
        }

        $this->posts->logActivity('Post Updated', 'post', $id, ['title' => $data['title'], 'status' => $data['status']], 'success');

        return redirect("/admin/posts/edit?id={$id}")->with('status', 'Post updated successfully');
    }

    // ── Delete ────────────────────────────────────────────────────────

    /**
     * GET /admin/posts/delete/{id} (and the legacy ?id= form) — renders the
     * confirmation page and performs no side effects. The delete itself is a
     * POST so it is CSRF-protected; legacy deleted on GET, which let a link
     * prefetch, crawler or <img> tag destroy a post.
     */
    public function deleteConfirm(Request $request, ?int $id = null): View
    {
        $id = $id ?? (int) $request->query('id', '0');
        $post = $id ? $this->posts->getPostById($id) : null;

        if (! $post) {
            abort(404);
        }

        return view('admin.posts.delete', ['post' => $post]);
    }

    public function destroy(Request $request, ?int $id = null): RedirectResponse
    {
        $id = $id ?? (int) ($request->input('id') ?? $request->query('id', '0'));

        if (! $id) {
            $this->posts->logActivity('Post Deletion Failed', 'post', 0, ['reason' => 'Post ID not provided'], 'failure');

            return redirect('/admin/posts')->with('error', 'Post ID not provided');
        }

        $post = $this->posts->getPostById($id);
        if (! $post) {
            $this->posts->logActivity('Post Deletion Failed', 'post', $id, ['reason' => 'Post not found'], 'failure');

            return redirect('/admin/posts')->with('error', 'Post not found');
        }

        if (! $this->posts->deletePost($id)) {
            $this->posts->logActivity('Post Deletion Failed', 'post', $id, ['title' => $post->title], 'failure');

            return redirect('/admin/posts')->with('error', 'Failed to delete post');
        }

        // Clean attachment rows (legacy leaves them orphaned; MySQL has no FK here)
        $this->posts->attachTagsToContent('post', $id, []);
        $this->posts->attachCategoriesToContent('post', $id, []);

        $this->posts->logActivity('Post Deleted', 'post', $id, ['title' => $post->title], 'success');

        return redirect('/admin/posts')->with('status', 'Post deleted successfully');
    }

    // ── AJAX API ──────────────────────────────────────────────────────

    /** Port of GET /api/posts/check_permalink. */
    public function checkPermalink(Request $request): JsonResponse
    {
        $slug = $this->sanitizeInput((string) $request->query('slug', ''));
        $excludeId = $request->filled('exclude_id') ? (int) $request->query('exclude_id') : null;

        $available = $this->posts->permalinkAvailable($slug, $excludeId);

        return response()->json(['available' => $available, 'success' => $available]);
    }

    /** Port of POST /api/posts/autosave — updates or creates a draft. */
    public function autosave(Request $request): JsonResponse
    {
        $id = (int) ($request->input('id') ?? 0);
        $title = $this->sanitizeInput((string) $request->input('title', ''));
        $content = $this->purify((string) $request->input('content', ''));
        $slug = $this->sanitizeInput((string) $request->input('slug', ''));
        $metaTitle = $this->sanitizeInput((string) $request->input('meta_title', ''));
        $metaDescription = $this->sanitizeInput((string) $request->input('meta_description', ''));
        $status = $this->sanitizeInput((string) $request->input('status', 'draft'));
        $published = $status === 'published' ? 1 : 0;

        if ($id) {
            $updated = $this->posts->updatePost($id, $title, $content, $slug, $published, null, null, $metaTitle, $metaDescription);

            return response()->json($updated
                ? ['success' => true, 'message' => 'Post auto-saved', 'id' => $id, 'status' => $status, 'published' => $published, 'is_new' => false]
                : ['success' => false, 'message' => 'Failed to auto-save post', 'error' => 'Database update failed']);
        }

        if ($slug === '') {
            $slug = $this->posts->generateUniquePermalink($title);
        }

        $author = (string) (auth()->user()->username ?? '');
        $newId = $this->posts->createPost($title, $content, $author, $slug, 0, null, null, null, $metaTitle, $metaDescription);

        return response()->json($newId
            ? ['success' => true, 'message' => 'Draft post created', 'id' => (int) $newId, 'status' => 'draft', 'published' => 0, 'is_new' => true]
            : ['success' => false, 'message' => 'Failed to create draft post', 'error' => 'Database insert failed']);
    }

    // ── Shared ────────────────────────────────────────────────────────

    /**
     * Validate the create/edit submit. Legacy reads $_POST directly with
     * required client-side fields (title, slug, content); a bad submit follows
     * the legacy failure path (flash + redirect back) instead of a 500.
     */
    protected function validated(Request $request): ?array
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
            'content' => 'required|string',
            'slug' => 'nullable|string|max:500',
            'author' => 'nullable|string|max:255',
            'status' => 'nullable|in:draft,published,scheduled',
            'reader_indexing' => 'nullable|in:,noindex,nofollow',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return null;
        }

        $v = $validator->validated();
        $status = in_array($v['status'] ?? 'draft', ['published', 'draft'], true) ? ($v['status'] ?? 'draft') : 'draft';

        return [
            'title' => $v['title'],
            'content' => $this->purify($v['content']),
            'slug' => trim((string) ($v['slug'] ?? '')),
            'author' => trim((string) ($v['author'] ?? '')),
            'status' => $status,
            'reader_indexing' => ($v['reader_indexing'] ?? '') !== '' ? $v['reader_indexing'] : null,
            'meta_title' => trim((string) ($v['meta_title'] ?? '')),
            'meta_description' => trim((string) ($v['meta_description'] ?? '')),
            'tag_ids' => $this->posts->resolveTagIds((array) $request->input('tags', [])),
            'category_ids' => array_map('intval', (array) ($request->input('category_ids', $request->input('categories', [])))),
            'new_categories' => array_values(array_filter(array_map(
                fn ($n) => $this->sanitizeInput((string) $n),
                (array) $request->input('new_categories', [])
            ), fn ($n) => $n !== '')),
        ];
    }

    /** Attach tags + categories (incl. new_categories[]), legacy order. */
    protected function attachTaxonomy(int $postId, array $data): array
    {
        $tagIds = $data['tag_ids'];
        if (! empty($tagIds)) {
            $this->posts->attachTagsToContent('post', $postId, $tagIds);
        }

        $categoryIds = $data['category_ids'];
        foreach ($data['new_categories'] as $name) {
            $categoryIds[] = $this->posts->createCategoryByName($name);
        }
        if (! empty($categoryIds)) {
            $this->posts->attachCategoriesToContent('post', $postId, $categoryIds);
        }

        return $categoryIds;
    }

    /** Port of legacy getPurifier() — same HTML.Allowed config + safe iframes. */
    protected function purify(string $content): string
    {
        try {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', null);
            $config->set('HTML.Allowed',
                'p,br,hr,h1,h2,h3,h4,h5,h6,span,div,b,strong,i,em,u,blockquote,pre,code,ul,ol,li,a[href|title|target],'
                .'img[src|alt|width|height],'
                .'table,tr,td,th,thead,tbody,tfoot,'
                .'iframe[src|width|height|frameborder]'
            );
            $config->set('HTML.SafeIframe', true);
            $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube\.com/embed/|player\.vimeo\.com/video/)%');

            $purified = (new \HTMLPurifier($config))->purify($content);

            return is_string($purified) ? $purified : '';
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('post purify fallback: '.$e->getMessage());

            return strip_tags($content);
        }
    }

    /**
     * Port of legacy sanitize_input (Config/Functions.php): date-format
     * normalisation is irrelevant here, so trim + stripslashes +
     * htmlspecialchars (ENT_QUOTES) is the behaviour-affecting subset.
     */
    protected function sanitizeInput(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $data = trim($data);
        $data = stripslashes($data);

        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    /** Port of ContentModel::getAllCategories / getAllTags. */
    protected function allCategories(): array
    {
        return DB::table('categories')->orderByDesc('id')->get(['id', 'name', 'slug'])->map(fn ($r) => (array) $r)->all();
    }

    protected function allTags(): array
    {
        return DB::table('tags')->orderByDesc('id')->get(['id', 'name', 'slug'])->map(fn ($r) => (array) $r)->all();
    }
}
