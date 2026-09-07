<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationsAdminService
{
    /**
     * @var MailService
     */
    protected static ?MailService $mailService = null;

    public static function mailService(): MailService
    {
        if (self::$mailService === null) {
            self::$mailService = app(MailService::class);
        }

        return self::$mailService;
    }

    public static function getNotificationsList(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'DESC',
        string $search = '',
        string $status = '',
        string $type = ''
    ): array {
        $sort = in_array($sort, ['id', 'title', 'created_at', 'status'], true) ? $sort : 'id';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $offset = max(0, ($page - 1) * $perPage);

        $query = DB::table('notifications')
            ->select('id', 'title', 'message', 'type', 'status', 'is_read', 'user_id', 'created_by', 'scheduled_at', 'sent_to_all_at', 'created_at')
            ->where('deleted_at', null);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('message', 'LIKE', "%{$search}%");
            });
        }

        if ($status !== '' && in_array($status, ['scheduled', 'sent', 'failed', 'draft'])) {
            $query->where('status', $status);
        }

        if ($type !== '' && in_array($type, ['info', 'alert', 'promotion', 'system', 'reminder', 'maintenance'])) {
            $query->where('type', $type);
        }

        $total = $query->count();

        $notifications = $query
            ->orderBy($sort, $order)
            ->skip($offset)
            ->take($perPage)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message ?? '',
                'type' => $n->type ?? '',
                'status' => $n->status ?? '',
                'is_read' => (bool) ($n->is_read ?? false),
                'user_id' => $n->user_id,
                'created_by' => $n->created_by,
                'scheduled_at' => $n->scheduled_at ? Carbon::parse($n->scheduled_at)->format('M j, Y g:i A') : null,
                'sent_to_all_at' => $n->sent_to_all_at ? Carbon::parse($n->sent_to_all_at)->format('M j, Y g:i A') : null,
                'created_at' => $n->created_at,
                'read_count' => self::getNotificationReadCount($n->id),
            ])
            ->all();

        return [
            'notifications' => $notifications,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
            'status_filter' => $status,
            'type_filter' => $type,
        ];
    }

    public static function getNotificationById(int $id): ?array
    {
        $n = DB::table('notifications')
            ->select('id', 'title', 'message', 'type', 'status', 'is_read', 'user_id', 'created_by', 'data', 'action_url', 'scheduled_at', 'sent_to_all_at', 'delivery_status', 'paused', 'pause_reason', 'created_at', 'updated_at')
            ->where('id', $id)
            ->where('deleted_at', null)
            ->first();

        if (!$n) {
            return null;
        }

        return [
            'id' => $n->id,
            'title' => $n->title,
            'message' => $n->message ?? '',
            'type' => $n->type ?? '',
            'status' => $n->status ?? '',
            'is_read' => (bool) ($n->is_read ?? false),
            'user_id' => $n->user_id,
            'created_by' => $n->created_by,
            'data' => $n->data ?? null,
            'action_url' => $n->action_url ?? '',
            'scheduled_at' => $n->scheduled_at,
            'sent_to_all_at' => $n->sent_to_all_at,
            'delivery_status' => $n->delivery_status ?? '',
            'paused' => (bool) ($n->paused ?? false),
            'pause_reason' => $n->pause_reason ?? '',
            'created_at' => $n->created_at,
            'updated_at' => $n->updated_at,
            'read_count' => self::getNotificationReadCount($n->id),
        ];
    }

    public static function sendNotification(array $data, ?User $admin = null): array
    {
        $errors = [];
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            $errors[] = 'Title is required';
        }
        $message = trim($data['message'] ?? '');
        if ($message === '') {
            $errors[] = 'Message is required';
        }
        if (empty($errors)) {
            return ['errors' => $errors];
        }

        DB::beginTransaction();
        try {
            $now = now();
            $id = DB::table('notifications')->insertGetId([
                'title' => $title,
                'message' => $message,
                'type' => $data['type'] ?? 'info',
                'status' => 'sent',
                'created_by' => $admin?->id ?? 0,
                'sent_to_all_at' => $now,
                'delivery_status' => 'delivered',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            self::logActivity($admin, $id, 'notifications', 'send', "Notification '{$title}' sent");
            DB::commit();

            self::queueSend($id, $title, $message, $data['type'] ?? 'info', ($admin ?? new User())->id);

            return ['notification_id' => $id, 'status' => "Notification sent successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function scheduleNotification(array $data, ?User $admin = null): array
    {
        $errors = [];
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            $errors[] = 'Title is required';
        }
        $message = trim($data['message'] ?? '');
        if ($message === '') {
            $errors[] = 'Message is required';
        }
        $scheduledAt = $data['scheduled_at'] ?? '';
        if ($scheduledAt === '') {
            $errors[] = 'Scheduled date/time is required';
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        DB::beginTransaction();
        try {
            $now = now();
            $id = DB::table('notifications')->insertGetId([
                'title' => $title,
                'message' => $message,
                'type' => $data['type'] ?? 'info',
                'status' => 'scheduled',
                'scheduled_at' => $scheduledAt,
                'created_by' => $admin?->id ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

                self::logActivity($admin, $id, 'notifications', 'schedule', "Notification '{$title}' scheduled for {$scheduledAt}");
            DB::commit();

            self::queueSchedule($id, $title, $message, $data['type'] ?? 'info', $scheduledAt, ($admin ?? new User())->id);

            return ['notification_id' => $id, 'status' => "Notification scheduled for {$scheduledAt}"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function pauseNotification(int $id, ?User $admin = null): array
    {
        $n = DB::table('notifications')->where('id', $id)->where('deleted_at', null)->first();
        if (!$n) {
            return ['error' => 'Notification not found'];
        }

        DB::beginTransaction();
        try {
            $now = now();
            DB::table('notifications')->where('id', $id)->update([
                'paused' => 1,
                'pause_reason' => $data['pause_reason'] ?? 'Paused by admin',
                'updated_at' => $now,
            ]);

            self::logActivity($admin, $id, 'notifications', 'pause', "Notification '{$n->title}' paused");
            DB::commit();

            return ['status' => "Notification paused"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function deleteNotification(int $id, ?User $admin = null): array
    {
        $n = DB::table('notifications')->where('id', $id)->where('deleted_at', null)->first();
        if (!$n) {
            return ['error' => 'Notification not found'];
        }

        DB::beginTransaction();
        try {
            $now = now();
            DB::table('notifications')->where('id', $id)->update(['deleted_at' => $now]);

            self::logActivity($admin, $id, 'notifications', 'delete', "Notification '{$n->title}' deleted");
            DB::commit();

            return ['status' => "Notification deleted successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Dispatch the notification delivery to the queue.
     * Kept thin on purpose: the job owns retries, push + email, and
     * failure logging so the HTTP request stays fast.
     */
    private static function queueSend(int $id, string $title, string $message, string $type, int $createdBy): void
    {
        SendNotificationJob::dispatch($id, $title, $message, $type, $createdBy)
            ->onConnection('notifications')
            ->onQueue('notifications');
    }

    /**
     * Dispatch a scheduled notification to be evaluated by the queue worker.
     * The job self-dispatches the real delivery when its scheduled time arrives.
     */
    private static function queueSchedule(int $id, string $title, string $message, string $type, string $scheduledAt, int $createdBy): void
    {
        SendScheduledNotificationJob::dispatch($id, $title, $message, $type, $scheduledAt, $createdBy)
            ->onConnection('notifications')
            ->onQueue('notifications');
    }

    private static function getNotificationReadCount(int $notificationId): int
    {
        try {
            return DB::table('notification_reads')->where('notification_id', $notificationId)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function logActivity(?User $admin, int $id, string $domain, string $action, string $message): void
    {
        if ($admin === null || !$admin->id) {
            return;
        }

        try {
            DB::table('activity_logs')->insert([
                'user_id' => $admin->id,
                'username' => $admin->username ?? 'admin',
                'activity' => $message,
                'domain' => $domain,
                'action' => $action,
                'item_id' => $id,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Table may not exist
        }
    }
}
