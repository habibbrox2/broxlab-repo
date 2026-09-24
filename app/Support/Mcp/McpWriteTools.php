<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use App\Support\ActivityLogger;
use App\Support\AdminPostService;
use App\Support\CommentService;
use App\Support\MobileAdminService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Write/report tool layer for the MCP server.
 *
 * Every mutation is:
 *  - gated behind an explicit `write` scope key (MCP_WRITE_API_KEY, checked
 *    in McpController — read keys can never reach this class),
 *  - journaled to the existing `activity_logs` table via ActivityLogger,
 *  - audited in the response with the created resource id and timestamp.
 *
 * Content created here is ALWAYS a draft (published=0) — publishing stays a
 * human decision in the admin panel.
 */
class McpWriteTools
{
    public function __construct(
        protected AdminPostService $postAdmin,
        protected CommentService $comments,
        protected MobileAdminService $mobileAdmin,
        protected McpTools $read,
    ) {}

    // ── Content creation ────────────────────────────────────────────────

    /**
     * Create an article DRAFT. Never published directly.
     *
     * @return array<string, mixed>
     */
    public function createArticleDraft(string $title, string $content, ?string $categorySlug, array $tags, ?string $metaDescription): array
    {
        $slug = $this->postAdmin->generateUniquePermalink($title);

        $id = $this->postAdmin->createPost(
            title: $title,
            content: $content,
            author: 'MCP Bot',
            slug: $slug,
            published: 0, // draft — human publishes
        );

        if ($id === null) {
            throw new McpException('Draft could not be created', -32603);
        }

        // Optional taxonomy attach (best-effort; category by slug or name).
        $attached = [];
        try {
            if ($categorySlug !== null && $categorySlug !== '') {
                $categoryId = $this->findCategoryId($categorySlug);
                if ($categoryId !== null) {
                    $this->postAdmin->attachCategoriesToContent('post', $id, [$categoryId]);
                    $attached[] = ['type' => 'category', 'id' => $categoryId];
                }
            }
            $tagIds = $this->postAdmin->resolveTagIds($tags);
            if ($tagIds !== []) {
                $this->postAdmin->attachTagsToContent('post', $id, $tagIds);
                foreach ($tags as $t) {
                    $attached[] = ['type' => 'tag', 'name' => $t];
                }
            }
        } catch (Throwable) {
            // Taxonomy is optional; draft itself succeeded.
        }

        $this->audit('mcp.article.draft_created', 'post', $id, ['title' => $title, 'slug' => $slug]);

        return [
            'created' => true,
            'type' => 'article_draft',
            'id' => (string) $id,
            'title' => $title,
            'slug' => $slug,
            'status' => 'draft',
            'published' => false,
            'attached' => $attached,
            'admin_url' => config('mcp.site_url') . '/admin/posts/view/' . $id,
            'url' => config('mcp.site_url') . '/posts/view/' . $slug,
            'note' => 'Draft created. A human must publish it from the admin panel.',
            'source' => 'BroxLab',
        ];
    }

    public function createCategory(string $name, ?string $description): array
    {
        $id = $this->postAdmin->createCategoryByName($name);

        if ($id <= 0) {
            throw new McpException('Category could not be created', -32603);
        }

        if ($description !== null && $description !== '') {
            DB::table('categories')->where('id', $id)->update([
                'description' => $description,
                'updated_at' => now(),
            ]);
        }

        $this->audit('mcp.category.created', 'category', $id, ['name' => $name]);

        return [
            'created' => true,
            'type' => 'category',
            'id' => (string) $id,
            'name' => $name,
            'source' => 'BroxLab',
        ];
    }

    public function createDevice(
        string $brandName,
        string $modelName,
        ?float $officialPrice,
        ?float $unofficialPrice,
        ?string $releaseDate,
    ): array {
        $exists = DB::table('mobiles')
            ->where('brand_name', $brandName)
            ->where('model_name', $modelName)
            ->exists();

        if ($exists) {
            throw new McpException('Device already exists: ' . $brandName . ' ' . $modelName, -32602);
        }

        $id = $this->mobileAdmin->insertMobile(
            brandName: $brandName,
            modelName: $modelName,
            officialPrice: $officialPrice ?? 0.0,
            unofficialPrice: $unofficialPrice ?? 0.0,
            // mobiles.status is an enum(official|unofficial|both|upcoming).
            status: 'upcoming',
            releaseDate: $releaseDate ?? now()->toDateString(),
            isOfficial: $officialPrice !== null && $officialPrice > 0 ? 1 : 0,
        );

        if ($id <= 0) {
            throw new McpException('Device could not be created', -32603);
        }

        $this->audit('mcp.device.created', 'mobile', $id, [
            'brand_name' => $brandName, 'model_name' => $modelName,
        ]);

        return [
            'created' => true,
            'type' => 'device',
            'id' => (string) $id,
            'name' => trim($brandName . ' ' . $modelName),
            'url' => config('mcp.site_url') . '/mobiles/view/' . $id,
            'source' => 'BroxLab',
        ];
    }

    // ── Moderation ──────────────────────────────────────────────────────

    /**
     * Reply to a visitor comment. Replies enter as admin replies pending
     * review parity with CommentService::addComment ('pending' status).
     */
    public function replyToComment(int $commentId, string $content): array
    {
        $parent = $this->comments->commentById($commentId);

        if ($parent === null) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'message' => 'Comment not found'];
        }

        $id = $this->comments->addComment(
            userId: null,
            guestName: 'BroxLab Team',
            content: $content,
            parentId: $commentId,
            contentType: (string) $parent['content_type'],
            contentId: (int) $parent['content_id'],
        );

        if ($id === false) {
            throw new McpException('Reply could not be created', -32603);
        }

        $this->audit('mcp.comment.replied', 'comment', (int) $id, [
            'parent_comment_id' => $commentId,
        ]);

        return [
            'created' => true,
            'type' => 'comment_reply',
            'id' => (string) $id,
            'parent_comment_id' => (string) $commentId,
            'status' => 'pending',
            'note' => 'Reply queued with pending status; moderators review it.',
            'source' => 'BroxLab',
        ];
    }

    public function pendingComments(int $limit): array
    {
        $rows = DB::table('comments')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit(min($limit, 50))
            ->get(['id', 'guest_name', 'user_id', 'content', 'content_type', 'content_id', 'created_at'])
            ->map(fn ($r) => (array) $r)
            ->all();

        return [
            'results' => array_map(fn (array $r) => [
                'id' => (string) $r['id'],
                'author' => $r['guest_name'] ?? ($r['user_id'] ? 'user#' . $r['user_id'] : 'guest'),
                'content' => mb_substr((string) $r['content'], 0, 300),
                'content_type' => $r['content_type'],
                'content_id' => (string) $r['content_id'],
                'created_at' => $r['created_at'],
            ], $rows),
        ];
    }

    // ── Reporting ───────────────────────────────────────────────────────

    public function siteReport(): array
    {
        $postStats = DB::table('posts')->selectRaw(
            "count(*) as total, sum(case when published = 1 then 1 else 0 end) as published, sum(case when published = 0 then 1 else 0 end) as drafts"
        )->first();

        $topViewed = DB::table('posts')
            ->where('published', 1)
            ->orderByDesc('view_count')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'view_count'])
            ->map(fn ($r) => (array) $r)
            ->all();

        $recent = DB::table('posts')
            ->where('published', 1)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'created_at'])
            ->map(fn ($r) => (array) $r)
            ->all();

        return [
            'posts' => [
                'total' => (int) ($postStats->total ?? 0),
                'published' => (int) ($postStats->published ?? 0),
                'drafts' => (int) ($postStats->drafts ?? 0),
            ],
            'mobiles' => ['total' => DB::table('mobiles')->count()],
            'comments_pending' => DB::table('comments')->where('status', 'pending')->count(),
            'top_viewed' => array_map(fn (array $r) => [
                'title' => $r['title'],
                'views' => (int) $r['view_count'],
                'url' => config('mcp.site_url') . '/posts/view/' . $r['slug'],
            ], $topViewed),
            'recently_published' => array_map(fn (array $r) => [
                'title' => $r['title'],
                'url' => config('mcp.site_url') . '/posts/view/' . $r['slug'],
                'published_at' => $r['created_at'],
            ], $recent),
            'source' => 'BroxLab',
        ];
    }

    /** Recent MCP activity from the existing activity_logs journal. */
    public function activityLogs(int $limit): array
    {
        $rows = DB::table('activity_logs')
            ->where('action', 'like', 'mcp.%')
            ->orderByDesc('id')
            ->limit(min($limit, 50))
            ->get(['id', 'action', 'resource_type', 'resource_id', 'status', 'details', 'created_at'])
            ->map(fn ($r) => (array) $r)
            ->all();

        return [
            'results' => array_map(fn (array $r) => [
                'id' => (string) $r['id'],
                'action' => $r['action'],
                'resource' => $r['resource_type'] . '#' . $r['resource_id'],
                'status' => $r['status'],
                'details' => json_decode((string) $r['details'], true),
                'at' => $r['created_at'],
            ], $rows),
        ];
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function findCategoryId(string $slugOrName): ?int
    {
        $row = DB::table('categories')
            ->where('slug', $slugOrName)
            ->orWhere('name', $slugOrName)
            ->first(['id']);

        return $row !== null ? (int) $row->id : null;
    }

    protected function audit(string $action, string $resourceType, int $resourceId, array $details): void
    {
        ActivityLogger::log($resourceType, $resourceId, $action, $details, 'success');
    }
}
