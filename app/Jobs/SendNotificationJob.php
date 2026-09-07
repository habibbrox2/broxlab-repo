<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Support\FcmService;
use App\Support\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $notificationId,
        public readonly string $title,
        public readonly string $message,
        public readonly string $type,
        public readonly int $createdBy,
    ) {}

    public function handle(MailService $mail, FcmService $fcm): void
    {
        $sentAt = now();

        $this->broadcastToUsers($mail, $fcm, $sentAt);

        try {
            DB::table('notifications')
                ->where('id', $this->notificationId)
                ->update([
                    'sent_to_all_at' => $sentAt,
                    'delivery_status' => 'delivered',
                    'updated_at' => $sentAt,
                ]);
        } catch (\Throwable $e) {
            Log::warning('SendNotificationJob: failed to update notification row: ' . $e->getMessage());
        }
    }

    private function broadcastToUsers(MailService $mail, FcmService $fcm, \Carbon\Carbon $sentAt): void
    {
        try {
            $users = DB::table('users')
                ->select('id', 'email', 'username', 'fcm_token')
                ->where('deleted_at', null)
                ->where('status', 'active')
                ->get();

            foreach ($users as $user) {
                $this->sendToUser($mail, $fcm, $user, $sentAt);
            }
        } catch (\Throwable $e) {
            Log::error('SendNotificationJob: user broadcast failed: ' . $e->getMessage());
        }
    }

    private function sendToUser(MailService $mail, FcmService $fcm, object $user, \Carbon\Carbon $sentAt): void
    {
        $email = (string) ($user->email ?? '');
        $token = (string) ($user->fcm_token ?? '');

        if ($email !== '') {
            try {
                $mail->sendTemplate('admin_notification', $email, $user->username ?? 'User', [
                    'title' => $this->title,
                    'message' => $this->message,
                    'type' => $this->type,
                    'sent_at' => $sentAt->format('M j, Y g:i A'),
                ]);
            } catch (\Throwable $e) {
                Log::warning('SendNotificationJob: email skipped for user ' . ($user->id ?? 'unknown') . ': ' . $e->getMessage());
            }
        }

        if ($token !== '' && config('services.fcm.enabled', true)) {
            try {
                $fcm->send($token, $this->title, $this->message, [
                    'type' => $this->type,
                    'notification_id' => (string) $this->notificationId,
                ]);
            } catch (\Throwable $e) {
                Log::warning('SendNotificationJob: push skipped for user ' . ($user->id ?? 'unknown') . ': ' . $e->getMessage());
            }
        }
    }

    public function tags(): array
    {
        return ['notifications', 'send', 'notification_id:' . $this->notificationId];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendNotificationJob failed: ' . $exception->getMessage(), [
            'notification_id' => $this->notificationId,
        ]);

        try {
            DB::table('notifications')
                ->where('id', $this->notificationId)
                ->update([
                    'delivery_status' => 'failed',
                    'updated_at' => now(),
                ]);
        } catch (\Throwable) {
            // Best-effort failure tracking.
        }
    }
}
