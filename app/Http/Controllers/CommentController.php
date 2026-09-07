<?php

namespace App\Http\Controllers;

use App\Support\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ported from legacy app/Controllers/CommentController.php — the comment
 * write endpoint the Blade comment form posts to (the read tree renders
 * server-side through CommentService::tree()).
 *
 * Write parity: HTMLPurifier sanitize, 2-char minimum, guest-name rule,
 * owner notification (notifications + notification_logs), activity log.
 */
class CommentController extends Controller
{
    public function __construct(
        protected CommentService $comments,
    ) {}

    public function add(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id() ? (int) auth()->id() : null;
            $guestName = trim((string) $request->input('guest_name', ''));
            $content = trim((string) $request->input('content', ''));
            $parentIdRaw = $request->input('parent_id', '');
            $parentId = $parentIdRaw !== '' && $parentIdRaw !== null ? (int) $parentIdRaw : null;
            $contentType = preg_replace('/[^a-z0-9_-]/i', '', (string) $request->input('content_type', 'post')) ?: 'post';
            $contentId = (int) $request->input('content_id', 0);

            $content = $this->purify($content);

            if ($content === '' || mb_strlen($content) < 2) {
                return response()->json(['success' => false, 'message' => 'Comment must be at least 2 characters'], 400);
            }
            if ($contentId <= 0) {
                return response()->json(['success' => false, 'message' => 'Invalid content target'], 400);
            }
            if (! $userId && $guestName === '') {
                return response()->json(['success' => false, 'message' => 'Guest name required for anonymous comments'], 400);
            }

            $commentId = $this->comments->addComment(
                $userId,
                $userId ? null : $guestName,
                $content,
                $parentId,
                $contentType,
                $contentId
            );

            if (! $commentId) {
                throw new \RuntimeException('Failed to insert comment');
            }

            // Notify owner (parent-comment owner first, otherwise content owner).
            if ($userId) {
                $this->notifyOwner($userId, $parentId, $contentType, $contentId, $content, $commentId);
            }

            $this->logActivity($userId, $guestName, $contentType, $contentId, $commentId);

            return response()->json([
                'success' => true,
                'message' => 'Comment posted successfully',
                'id' => $commentId,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Comment add failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to add comment'], 500);
        }
    }

    /**
     * POST /comment/edit — owner-only content update.
     */
    public function edit(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id() ? (int) auth()->id() : null;

            if (! $userId) {
                return response()->json(['success' => false, 'message' => 'Authentication required'], 401);
            }

            $commentId = (int) $request->input('comment_id', 0);
            $content = trim((string) $request->input('content', ''));

            if ($commentId <= 0 || $content === '' || mb_strlen($content) < 2) {
                return response()->json(['success' => false, 'message' => 'Invalid input'], 400);
            }

            $content = $this->purify($content);

            if ($this->comments->editComment($commentId, $userId, $content)) {
                $this->logActivity($userId, '', 'comment', $commentId, $commentId, 'Comment Edited');

                return response()->json(['success' => true, 'message' => 'Comment updated'], 200);
            }

            return response()->json(['success' => false, 'message' => 'Failed to edit comment'], 403);
        } catch (\Throwable $e) {
            Log::error('Comment edit failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error editing comment'], 500);
        }
    }

    /**
     * POST /comment/delete — owner-only delete (hard delete, legacy parity).
     */
    public function delete(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id() ? (int) auth()->id() : null;

            if (! $userId) {
                return response()->json(['success' => false, 'message' => 'Authentication required'], 401);
            }

            $commentId = (int) $request->input('comment_id', 0);

            if ($commentId <= 0) {
                return response()->json(['success' => false, 'message' => 'Invalid comment'], 400);
            }

            if ($this->comments->deleteComment($commentId, $userId)) {
                $this->logActivity($userId, '', 'comment', $commentId, $commentId, 'Comment Deleted');

                return response()->json(['success' => true, 'message' => 'Comment deleted'], 200);
            }

            return response()->json(['success' => false, 'message' => 'Failed to delete comment'], 403);
        } catch (\Throwable $e) {
            Log::error('Comment delete failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error deleting comment'], 500);
        }
    }

    /**
     * POST /comment/react — upsert an emoji reaction, return the new counts.
     */
    public function react(Request $request): JsonResponse
    {
        try {
            $commentId = (int) $request->input('comment_id', 0);
            $reaction = trim((string) $request->input('reaction', ''));
            $userId = auth()->id() ? (int) auth()->id() : null;
            $guestIp = $request->ip();

            if ($commentId <= 0) {
                return response()->json(['success' => false, 'message' => 'Comment ID required'], 400);
            }
            if ($reaction === '') {
                return response()->json(['success' => false, 'message' => 'Reaction required'], 400);
            }

            $validReactions = ['👍', '❤️', '🔥', '😂', '😮', '😢', '🎉', '💯'];
            if (! in_array($reaction, $validReactions, true)) {
                return response()->json(['success' => false, 'message' => 'Invalid reaction'], 400);
            }

            $result = $this->comments->addReaction($commentId, $userId, $guestIp, $reaction);

            if (! ($result['success'] ?? false)) {
                throw new \RuntimeException($result['error'] ?? 'Failed to add reaction');
            }

            $this->logActivity($userId, '', 'comment', $commentId, $commentId, 'Comment Reacted');

            return response()->json([
                'success' => true,
                'message' => 'Reaction added',
                'reactions' => $result['reactions'],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('React error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to add reaction'], 500);
        }
    }

    /**
     * POST /comment/like — like a comment (dedupe by user/ip), notify owner.
     */
    public function like(Request $request): JsonResponse
    {
        try {
            $commentId = (int) $request->input('comment_id', 0);
            $userId = auth()->id() ? (int) auth()->id() : null;
            $guestIp = $request->ip();

            if ($commentId <= 0) {
                return response()->json(['success' => false, 'message' => 'Comment ID required'], 400);
            }

            $likes = $this->comments->likeComment($commentId, $userId, $guestIp);

            if ($likes === false) {
                return response()->json(['success' => false, 'message' => 'Already liked'], 400);
            }

            // Notify comment owner with liker as actor (legacy parity)
            if ($userId) {
                $comment = $this->comments->commentById($commentId);
                $commentOwnerId = (int) ($comment['user_id'] ?? 0);

                if ($commentOwnerId > 0 && $commentOwnerId !== $userId) {
                    $this->createNotification(
                        $commentOwnerId,
                        $userId,
                        'Comment Liked',
                        'Your comment received a new like ('.$likes.' total likes).',
                        'comment_like'
                    );
                }
            }

            $this->logActivity($userId, '', 'comment', $commentId, $commentId, 'Comment Liked');

            return response()->json(['success' => true, 'likes' => $likes], 200);
        } catch (\Throwable $e) {
            Log::error('Comment like failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to like comment'], 500);
        }
    }

    protected function purify(string $content): string
    {
        try {
            $purifier = app(\HTMLPurifier::class);
            $purified = $purifier->purify($content);

            return is_string($purified) ? trim($purified) : '';
        } catch (\Throwable $e) {
            Log::warning('Comment purify fallback: ' . $e->getMessage());

            return strip_tags($content);
        }
    }

    /**
     * Port of the legacy owner-notification block: resolve the target user
     * (parent comment owner, else content owner) and create the notification
     * + delivery log row.
     */
    protected function notifyOwner(int $userId, ?int $parentId, string $contentType, int $contentId, string $content, int $commentId): void
    {
        $targetUserId = 0;

        if ($parentId) {
            $parent = DB::table('comments')->select('user_id')->where('id', $parentId)->first();
            $targetUserId = (int) ($parent->user_id ?? 0);
        } else {
            $ownerTable = match ($contentType) {
                'post' => 'posts',
                'page' => 'pages',
                default => null,
            };

            if ($ownerTable !== null) {
                $targetUserId = (int) DB::table($ownerTable)->where('id', $contentId)->value('user_id') ?? 0;
            }
        }

        if ($targetUserId <= 0 || $targetUserId === $userId) {
            return;
        }

        $isReply = ! empty($parentId);
        $title = $isReply ? 'New Reply' : 'New Comment';
        $message = $isReply
            ? 'Someone replied to your comment: "'.mb_substr($content, 0, 50).'..."'
            : 'Someone commented on your content: "'.mb_substr($content, 0, 50).'..."';

        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $targetUserId,
            'created_by' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => 'announcement',
            'data' => json_encode(['user_id' => $targetUserId, 'channels' => ['push', 'in_app', 'email']], JSON_UNESCAPED_UNICODE),
            'action_url' => '',
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($notificationId) {
            $this->createNotificationDelivery($notificationId, $targetUserId, (string) $commentId, 'comment');
        }
    }

    /**
     * Shared notification creation + delivery logging (used by add/like).
     */
    protected function createNotification(int $targetUserId, int $actorId, string $title, string $message, string $deliveryResponse): void
    {
        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $targetUserId,
            'created_by' => $actorId,
            'title' => $title,
            'message' => $message,
            'type' => 'announcement',
            'data' => json_encode(['user_id' => $targetUserId, 'channels' => ['push', 'in_app']], JSON_UNESCAPED_UNICODE),
            'action_url' => '',
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($notificationId) {
            $this->createNotificationDelivery($notificationId, $targetUserId, (string) $deliveryResponse, 'system');
        }
    }

    protected function createNotificationDelivery(int $notificationId, int $targetUserId, string $token, string $response): void
    {
        DB::table('notification_logs')->insert([
            'notification_id' => $notificationId,
            'user_id' => $targetUserId,
            'device_id' => null,
            'channel' => 'system',
            'ip_address' => null,
            'token' => $token,
            'status' => 'sent',
            'response' => $response,
            'message_id' => null,
            'provider_response' => null,
            'metadata' => null,
        ]);
    }

    protected function logActivity(?int $userId, string $guestName, string $contentType, int $contentId, int $commentId, string $action = 'Comment Added'): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => $userId ?? 0,
                'role' => $userId ? 'user' : 'guest',
                'action' => $action,
                'resource_type' => 'comment',
                'resource_id' => $commentId,
                'status' => 'success',
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
                'details' => json_encode([
                    'content_type' => $contentType,
                    'content_id' => $contentId,
                    'author' => $guestName !== '' ? $guestName : "User {$userId}",
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Comment activity log failed: ' . $e->getMessage());
        }
    }
}