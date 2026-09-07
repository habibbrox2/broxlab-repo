<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Parsedown;

/**
 * Port of the legacy CommentModel public read/write methods:
 *
 *  - tree()        → getCommentsByContent(): SELECT * FROM comments WHERE
 *                    content_type=? AND content_id=? ORDER BY created_at,
 *                    then tree-building + markdown HTML + reaction counts.
 *                    (Faithful port — legacy has NO status/deleted_at filter.)
 *  - addComment()  → INSERT INTO comments (user_id, guest_name, content,
 *                    parent_id, content_type, content_id).
 *
 * The user display fields (username/avatar/role) are enriched with a users
 * join — legacy left them empty in this path, falling back to guest_name.
 */
class CommentService
{
    protected Parsedown $parsedown;

    public function __construct()
    {
        $this->parsedown = new Parsedown();
    }

    /**
     * Comment tree for a content item, enriched with markdown HTML,
     * reaction counts, and author info (legacy getCommentsByContent parity).
     */
    public function tree(string $contentType, int $contentId): array
    {
        // Note: users has no avatar/role columns — admin badge comes from the
        // user_roles join (legacy left these fields empty on this path).
        $rows = DB::table('comments as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->select(
                'c.*',
                'u.username',
                'u.status as user_status',
                DB::raw("EXISTS (
                    SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id
                    WHERE ur.user_id = c.user_id AND r.name IN ('admin','super_admin')
                ) as user_role")
            )
            ->where('c.content_type', $contentType)
            ->where('c.content_id', $contentId)
            ->orderBy('c.created_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        // Normalize the EXISTS result to a role name ('admin' | null)
        foreach ($rows as &$row) {
            $row['user_role'] = ! empty($row['user_role']) ? 'admin' : null;
            $row['user_avatar'] = null;
        }
        unset($row);

        $ids = array_map(fn ($r) => (int) $r['id'], $rows);

        $reactions = $this->reactionsBatch($ids);

        foreach ($rows as &$c) {
            $c['content_html'] = $this->parsedown->text((string) ($c['content'] ?? ''));
            $c['reactions'] = $reactions[(int) $c['id']] ?? [];
            $c['replies'] = [];
        }
        unset($c);

        // Tree building (legacy map+references approach)
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = $row;
        }

        $tree = [];
        foreach ($map as $id => $row) {
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId > 0 && isset($map[$parentId])) {
                $map[$parentId]['replies'][] = $row;
            } else {
                $tree[] = $row;
            }
        }

        return $tree;
    }

    public function addComment(
        ?int $userId,
        ?string $guestName,
        string $content,
        ?int $parentId,
        string $contentType,
        int $contentId
    ): int|false {
        try {
            return (int) DB::table('comments')->insertGetId([
                'user_id' => $userId,
                'guest_name' => $guestName !== null ? mb_substr($guestName, 0, 100) : null,
                'content' => $content,
                'parent_id' => $parentId,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::error('[CommentService] addComment error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Owner-scoped content update (legacy editComment — WHERE id AND user_id).
     * Also stamps edited_at (column exists in the schema; legacy left it null).
     */
    public function editComment(int $commentId, int $userId, string $content): bool
    {
        return (bool) DB::table('comments')
            ->where('id', $commentId)
            ->where('user_id', $userId)
            ->update([
                'content' => $content,
                'edited_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Owner-scoped hard delete (legacy deleteComment — hard DELETE, parity:
     * the legacy read query does not filter deleted_at, so a soft delete would
     * leave the comment visible).
     */
    public function deleteComment(int $commentId, int $userId): bool
    {
        return (bool) DB::table('comments')
            ->where('id', $commentId)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Upsert reaction, then return the updated emoji => count map.
     * (Legacy addReaction: INSERT ... ON DUPLICATE KEY UPDATE.)
     */
    public function addReaction(int $commentId, ?int $userId, ?string $guestIp, string $reactionEmoji): array
    {
        try {
            // Legacy dedupe semantics: mysqli bound NULL never matched (SQL
            // `= NULL`), so for guests only the same guest_ip counts. Laravel's
            // builder turns where('user_id', null) into IS NULL — replicate the
            // legacy behaviour with an explicit conditional instead.
            $existing = DB::table('comment_reactions')
                ->where('comment_id', $commentId)
                ->where(function ($q) use ($userId, $guestIp) {
                    if ($userId !== null) {
                        $q->where('user_id', $userId);
                    } else {
                        $q->where('guest_ip', $guestIp);
                    }
                })
                ->first();

            if ($existing) {
                DB::table('comment_reactions')
                    ->where('id', $existing->id)
                    ->update([
                        'reaction_emoji' => $reactionEmoji,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('comment_reactions')->insert([
                    'comment_id' => $commentId,
                    'user_id' => $userId,
                    'guest_ip' => $guestIp,
                    'reaction_emoji' => $reactionEmoji,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return ['success' => true, 'message' => 'Reaction added', 'reactions' => $this->reactionsFor((int) $commentId)];
        } catch (\Throwable $e) {
            Log::error('[CommentService] addReaction error: ' . $e->getMessage());

            return ['success' => false, 'error' => 'Failed to add reaction'];
        }
    }

    /**
     * Like a comment (legacy likeComment): dedupe by user_id OR guest_ip,
     * insert, recount, update comments.likes. Returns total or false if duped.
     */
    public function likeComment(int $commentId, ?int $userId, ?string $guestIp): int|false
    {
        // Legacy parity: mysqli `user_id = NULL` never matched, so guest likes
        // dedupe on guest_ip only (see addReaction for details).
        $dup = DB::table('comment_likes')
            ->where('comment_id', $commentId)
            ->where(function ($q) use ($userId, $guestIp) {
                if ($userId !== null) {
                    $q->where('user_id', $userId);
                } else {
                    $q->where('guest_ip', $guestIp);
                }
            })
            ->exists();

        if ($dup) {
            return false;
        }

        // Note: comment_likes has no updated_at column (schema parity)
        DB::table('comment_likes')->insert([
            'comment_id' => $commentId,
            'user_id' => $userId,
            'guest_ip' => $guestIp,
            'created_at' => now(),
        ]);

        $total = (int) DB::table('comment_likes')->where('comment_id', $commentId)->count();

        DB::table('comments')->where('id', $commentId)->update(['likes' => $total]);

        return $total;
    }

    public function commentById(int $id): ?array
    {
        $row = DB::table('comments')->where('id', $id)->first();

        if (! $row) {
            return null;
        }

        $comment = (array) $row;
        $comment['content_html'] = $this->parsedown->text((string) ($comment['content'] ?? ''));
        $comment['reactions'] = $this->reactionsFor($id);

        return $comment;
    }

    public function count(string $contentType, int $contentId): int
    {
        return (int) DB::table('comments')
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->count();
    }

    protected function reactionsFor(int $commentId): array
    {
        return $this->reactionsBatch([$commentId])[$commentId] ?? [];
    }

    /**
     * reaction_emoji => count per comment (legacy getCommentReactions batch).
     */
    protected function reactionsBatch(array $commentIds): array
    {
        if (empty($commentIds)) {
            return [];
        }

        $rows = DB::table('comment_reactions')
            ->select('comment_id', 'reaction_emoji', DB::raw('COUNT(*) as count'))
            ->whereIn('comment_id', $commentIds)
            ->groupBy('comment_id', 'reaction_emoji')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->comment_id][$row->reaction_emoji] = (int) $row->count;
        }

        return $out;
    }
}