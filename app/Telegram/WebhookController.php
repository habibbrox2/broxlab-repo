<?php
declare(strict_types = 1)
;

namespace App\Telegram;

use mysqli;
use App\Telegram\BotKernel;

/**
 * WebhookController.php
 * Receives incoming Telegram updates via HTTPS POST.
 */
class WebhookController
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function handle(): void
    {
        header('Content-Type: application/json');

        $settings = new \AppSettings($this->mysqli);
        $expectedSecret = $settings->get('telegram_webhook_secret', '');

        // Verify secret token (if provided by Telegram in Header)
        $secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

        if ($expectedSecret && $secretHeader !== $expectedSecret) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $input = file_get_contents('php://input');
        $update = json_decode((string)$input, true);

        if ($update) {
            $kernel = new BotKernel($this->mysqli);
            $kernel->handleUpdates($update);
            echo json_encode(['status' => 'ok']);
        }
        else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid update']);
        }
    }
}
