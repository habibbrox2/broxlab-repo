<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendScheduledNotificationJob implements ShouldQueue
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
        public readonly string $scheduledAt,
        public readonly int $createdBy,
    ) {}

    public function handle(): void
    {
        $scheduled = Carbon::parse($this->scheduledAt);

        if ($scheduled->isFuture()) {
            $delay = $scheduled->diffInSeconds(now(), false);
            $delay = max(0, min((int) $delay, 3600));

            if ($delay > 0) {
                self::dispatch($this->notificationId, $this->title, $this->message, $this->type, $this->scheduledAt, $this->createdBy)
                    ->onConnection('notifications')
                    ->onQueue('notifications')
                    ->delay($delay);

                return;
            }
        }

        SendNotificationJob::dispatch(
            $this->notificationId,
            $this->title,
            $this->message,
            $this->type,
            $this->createdBy,
        )->onConnection('notifications')->onQueue('notifications');
    }

    public function tags(): array
    {
        return ['notifications', 'scheduled', 'notification_id:' . $this->notificationId];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendScheduledNotificationJob failed: ' . $exception->getMessage(), [
            'notification_id' => $this->notificationId,
            'scheduled_at' => $this->scheduledAt,
        ]);
    }
}
