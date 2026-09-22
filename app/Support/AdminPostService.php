<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ported from legacy app/Models/ContentModel.php — the post CRUD used by
 * PostsController's /admin routes, plus the tag/category attachment and
 * permalink helpers.
 *
 * Parity notes:
 * - Legacy getPosts()/getPostsCount() hardcode `published = 1`, so the admin
 *   list ignores a `draft` status filter (drafts never appear). Kept identical.
 * - Delete is a hard DELETE (posts have no soft-delete column).
 * - Image extraction in legacy uses DOMDocument; here the first-image probe is
 *   a regex over src/data-src/data-original/og:image (resolve=false semantics).
 */
class AdminPostService
{
    public const SORTS = ['id', 'title', 'created_at', 'updated_at'];

    public function __construct(protected PostTaxonomy $taxonomy, protected UserProfileService $users) {}

    // ── Reads ─────────────────────────────────────────────────────────

    /** Port of ContentModel::getPosts (admin list — legacy `published = 1` quirk kept). */
    public function getPosts(int $page, int $limit, string $search = '', string $sort = 'created_at', string $order = 'DESC', array $filters = []): array
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'created_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $q = DB::table('posts as p')
            ->selectRaw('p.*, 0 AS views, 0 AS impressions')
            ->where('p.published', 1)
            ->orderBy("p.$sort", $order)
            ->limit($limit)
            ->offset(($page - 1) * $limit);

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('p.title', 'like', '%'.$search.'%')
                    ->orWhere('p.content', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['status']) && in_array($filters['status'], ['published', 'draft'], true)) {
            $q->where('p.published', $filters['status'] === 'published' ? 1 : 0);
        }

        $images = new ContentImages;

        return collect($q->get()->all())
            ->map(function ($row) use ($images) {
                $arr = (array) $row;
                $arr['image'] = $images->extractFirst($arr['content'] ?? null);

                return $arr;
            })
            ->all();
    }

    /** Port of ContentModel::getPostsCount (same legacy `published = 1` quirk). */
    public function getPostsCount(string $search = '', array $filters = []): int
    {
        $q = DB::table('posts')->where('published', 1);

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('title', 'like', '%'.$search.'%')
                    ->orWhere('content', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['status']) && in_array($filters['status'], ['published', 'draft'], true)) {
            $q->where('published', $filters['status'] === 'published' ? 1 : 0);
        }

        return (int) $q->count();
    }

    /** Port of ContentModel::getPostById — p.* plus zeroed engagement columns. */
    public function getPostById(int $id): ?object
    {
        $row = DB::table('posts')->selectRaw('*, 0 AS views, 0 AS impressions')->where('id', $id)->first();

        return $row;
    }

    /** Port of ContentModel::getPostBySlug. */
    public function getPostBySlug(string $slug): ?object
    {
        return DB::table('posts')->selectRaw('*, 0 AS views, 0 AS impressions')->where('slug', $slug)->first();
    }

    /** Port of ContentModel::generateUniquePermalink. */
    public function generateUniquePermalink(string $title, ?int $excludePostId = null): string
    {
        if ($title === '') {
            $baseSlug = 'post-'.uniqid().'-'.random_int(1000, 9999);
        } else {
            $baseSlug = $this->slugify($title);
        }

        $q = DB::table('posts')->where('slug', $baseSlug);
        if ($excludePostId) {
            $q->where('id', '!=', $excludePostId);
        }
        if ($q->count() === 0) {
            return $baseSlug;
        }

        for ($counter = 1; $counter < 1000; $counter++) {
            $candidate = $baseSlug.'-'.$counter;
            if (DB::table('posts')->where('slug', $candidate)->count() === 0) {
                return $candidate;
            }
        }

        return 'post-'.uniqid();
    }

    /** Check-permalink API helper: is this slug available (optionally excluding a post id)? */
    public function permalinkAvailable(string $slug, ?int $excludeId = null): bool
    {
        if ($slug === '') {
            return true;
        }

        $q = DB::table('posts')->where('slug', $slug);
        if ($excludeId) {
            $q->where('id', '!=', $excludeId);
        }

        return $q->exists() === false;
    }

    // ── Writes ────────────────────────────────────────────────────────

    /** Port of ContentModel::createPost. Returns new id or null. */
    public function createPost(string $title, string $content, string $author, string $slug, int $published = 0, ?string $readerIndexing = null, ?string $publishedAt = null, ?string $sourceUrl = null, ?string $metaTitle = null, ?string $metaDescription = null): ?int
    {
        try {
            return DB::table('posts')->insertGetId([
                'title' => $title,
                'content' => $content,
                'author' => $author,
                'slug' => $slug,
                'published' => $published,
                'reader_indexing' => $readerIndexing,
                'published_at' => $publishedAt,
                'source_url' => $sourceUrl,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
            ]);
        } catch (\Throwable $e) {
            Log::warning('post create failed: '.$e->getMessage());

            return null;
        }
    }

    /** Port of ContentModel::updatePost. */
    public function updatePost(int $id, string $title, string $content, string $slug, int $published, ?string $readerIndexing = null, ?string $publishedAt = null, ?string $metaTitle = null, ?string $metaDescription = null): bool
    {
        try {
            $values = [
                'title' => $title,
                'content' => $content,
                'slug' => $slug,
                'published' => $published,
                'reader_indexing' => $readerIndexing,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
            ];

            // A publication timestamp is assigned only on a draft → published
            // transition. Do not erase the original date on later edits or
            // autosaves of an already-published post.
            if ($publishedAt !== null) {
                $values['published_at'] = $publishedAt;
            }

            DB::table('posts')->where('id', $id)->update($values);

            return true;
        } catch (\Throwable $e) {
            Log::warning('post update failed: '.$e->getMessage());

            return false;
        }
    }

    /** Port of ContentModel::deletePost — hard delete (legacy parity). */
    public function deletePost(int $id): bool
    {
        return DB::table('posts')->where('id', $id)->delete() > 0;
    }

    // ── Attachments ───────────────────────────────────────────────────

    /**
     * Port of ContentModel::attachTagsToContent / updateContentTags — replace
     * the content_tags rows for a piece of content.
     */
    public function attachTagsToContent(string $type, int $contentId, array $tagIds): void
    {
        DB::table('content_tags')->where('content_type', $type)->where('content_id', $contentId)->delete();

        foreach ($tagIds as $tagId) {
            DB::table('content_tags')->insert(['content_type' => $type, 'content_id' => $contentId, 'tag_id' => (int) $tagId]);
        }
    }

    /** Port of ContentModel::attachCategoriesToContent — replace rows. */
    public function attachCategoriesToContent(string $type, int $contentId, array $categoryIds): void
    {
        DB::table('content_categories')->where('content_type', $type)->where('content_id', $contentId)->delete();

        foreach ($categoryIds as $categoryId) {
            DB::table('content_categories')->insert(['content_type' => $type, 'content_id' => $contentId, 'category_id' => (int) $categoryId]);
        }
    }

    /**
     * Legacy tag-input resolution: numeric values are tag ids; strings are
     * slugified and looked up, creating the tag when missing.
     */
    public function resolveTagIds(array $tagInput): array
    {
        $tagIds = [];
        foreach ($tagInput as $tag) {
            if (is_numeric($tag)) {
                $tagIds[] = (int) $tag;

                continue;
            }
            $slug = $this->slugify((string) $tag);
            $existing = DB::table('tags')->where('slug', $slug)->first();
            $tagIds[] = $existing !== null
                ? (int) $existing->id
                : (int) DB::table('tags')->insertGetId(['name' => $tag, 'slug' => $slug]);
        }

        return $tagIds;
    }

    /** Create a category by name (used for new_categories[] from the form). */
    public function createCategoryByName(string $name): int
    {
        return (int) DB::table('categories')->insertGetId(['name' => $name, 'slug' => $this->slugify($name)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** Port of ContentModel::slugify fallback branch. */
    public function slugify(string $text): string
    {
        // ASCII-only word chars become hyphens; but Bengali/other unicode
        // letters are preserved (\p{L}/\p{N} with the /u modifier) so Bengali
        // tags get distinct slugs instead of every one collapsing to 'n-a'.
        $slug = mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $text), '-'));

        return $slug !== '' ? $slug : 'n-a';
    }

    /**
     * Port of legacy notifyPostApproval + sendContentCreatedPush — push to the
     * post author when their draft gets published. Non-fatal on failure.
     */
    public function notifyPostApproval(int $postId, string $postTitle, int $postAuthorId, int $approverId): void
    {
        try {
            $approver = DB::table('users')->where('id', $approverId)->value('username');
            $approverName = $approver ?: 'Admin';

            // Same payload shape as legacy NotificationHelper::notifyPostApproval
            $notificationData = [
                'action_type' => 'post_approved',
                'post_id' => (string) $postId,
                'post_title' => $postTitle,
                'approver' => $approverName,
                'action_url' => '/posts/'.$postId.'/'.$this->slugify($postTitle ?: ('post-'.$postId)),
            ];

            // Legacy title/body strings (Bengali), kept byte-for-byte
            $title = 'পোস্ট অনুমোদিত হয়েছে ✓';
            $body = 'আপনার পোস্ট "'.mb_substr($postTitle, 0, 30).'..." অনুমোদন করা হয়েছে।';

            app(AdminNotifier::class)->notifyUserById((int) $postAuthorId, $title, $body, $notificationData);
        } catch (\Throwable $e) {
            Log::warning('post approval notification failed (non-fatal): '.$e->getMessage());
        }
    }

    /** Activity-log row (same actions/strings as legacy PostsController). */
    public function logActivity(string $action, string $resourceType, int $resourceId, array $details, string $status = 'success'): void
    {
        try {
            $user = auth()->user();

            DB::table('activity_logs')->insert([
                'user_id' => $user?->id ?? 0,
                // The users table has no `role` column — derive the actor's role
                // from the RBAC tables (single shared definition), falling back
                // to 'admin' for unauthenticated/system contexts.
                'role' => $this->actorRole($user),
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'status' => $status,
                'ip_address' => request()?->ip() ?? '0.0.0.0',
                'user_agent' => mb_substr((string) (request()?->userAgent() ?? ''), 0, 500),
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('post activity log failed (non-fatal): '.$e->getMessage());
        }
    }

    /**
     * First RBAC role name for the actor, or 'admin' when unauthenticated
     * (legacy rows used that as the default for system-context entries).
     */
    protected function actorRole(?object $user): string
    {
        $roles = $this->users->rbacFor($user?->id)['roles'];

        return $roles[0] ?? 'admin';
    }
}
