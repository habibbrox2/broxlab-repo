<?php
declare(strict_types = 1)
;

namespace App\Telegram\Callbacks;

use App\Telegram\BaseCommandHandler;
use App\Telegram\TelegramSessionManager;

/**
 * SmsSendCallback.php
 * Starts the "Send SMS" conversation flow.
 */
class SmsSendCallback extends BaseCommandHandler
{
    public function handle(array $update): void
    {
        $callbackQuery = $update['callback_query'] ?? null;
        if (!$callbackQuery)
            return;

        $chatId = (string)$callbackQuery['message']['chat']['id'];

        if (!$this->checkFeature('sms_gateway', $update)) {
            return;
        }

        $session = new TelegramSessionManager($this->mysqli);
        $session->setState($chatId, 'sms_waiting_recipient', []);

        $text = "📤 *Send SMS Step 1/2*\n\nPlease enter the recipient's phone number (with country code, e.g., +8801700000000):";

        $this->telegram->sendMessage($chatId, $text);
    }
}
