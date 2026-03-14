<?php
declare(strict_types = 1)
;

namespace App\Telegram\Callbacks;

use App\Telegram\BaseCommandHandler;
use App\Telegram\InlineKeyboardBuilder;

/**
 * SmsLogsCallback.php
 * Displays recent SMS logs in the Telegram bot.
 */
class SmsLogsCallback extends BaseCommandHandler
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

        $result = $this->mysqli->query("SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 5");

        $text = "📂 *Recent SMS Logs*\n\n";

        if ($result && $result->num_rows > 0) {
            while ($log = $result->fetch_assoc()) {
                $status = $log['type'] === 'sent' ? '✅' : '❌';
                $text .= "{$status} *To:* {$log['phone_number']}\n";
                $text .= "💬 {$log['message']}\n";
                $text .= "📅 _" . date('Y-m-d H:i', strtotime($log['created_at'])) . "_\n\n";
            }
        }
        else {
            $text .= "No logs found.";
        }

        $keyboard = (new InlineKeyboardBuilder())
            ->addButton('⬅️ Back to SMS Menu', 'menu_sms')
            ->build();

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }
}
