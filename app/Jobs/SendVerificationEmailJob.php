<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendVerificationEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $rawToken,
        public readonly string $email,
        public readonly string $userName,
        public readonly string $siteName,
        public readonly string $verifyLink,
        public readonly string $expiryTime,
        public readonly string $templateSlug = 'email_verification',
    ) {}

    public function handle(MailService $mail): void
    {
        try {
            $ok = $mail->sendTemplate(
                $this->templateSlug,
                $this->email,
                $this->userName,
                [
                    'APP_NAME' => $this->siteName,
                    'USER_NAME' => $this->userName,
                    'USER_EMAIL' => $this->email,
                    'VERIFY_LINK' => $this->verifyLink,
                    'EXPIRY_TIME' => $this->expiryTime,
                ],
            );

            if (! $ok) {
                Log::warning('SendVerificationEmailJob: template render/send returned false for user ' . $this->userId);
            }
        } catch (\Throwable $e) {
            Log::error('SendVerificationEmailJob failed: ' . $e->getMessage(), [
                'user_id' => $this->userId,
                'email' => $this->email,
            ]);
        }
    }

    public function tags(): array
    {
        return ['email', 'verification', 'user_id:' . $this->userId];
    }
}
