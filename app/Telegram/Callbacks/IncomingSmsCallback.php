<?php
declare(strict_types = 1)
;

namespace App\Telegram\Callbacks;

use App\Telegram\BaseCommandHandler;
use App\Telegram\InlineKeyboardBuilder;

/**
 * IncomingSmsCallback.php
 * Displays recently received SMS in the Telegram bot.
 */
class IncomingSmsCallback extends BaseCommandHandler
{
    public function handle(array $update): void
    {
        $callbackQuery = $update['callback_query'] ?? null;
        if (!$callbackQuery)
            return;

        $chatId = (string)$callbackQuery['message']['chat']['id'];

        if (!$this->checkFeature('incoming_sms', $update)) {
            return;
        }

        $result = $this->mysqli->query("SELECT * FROM sms_logs WHERE type = 'received' ORDER BY created_at DESC LIMIT 5");

        $text = "📨 *Recent Incoming SMS*\n\n";

        if ($result && $result->num_rows > 0) {
            while ($log = $result->fetch_assoc()) {
                $text .= "👤 *From:* {$log['phone_number']}\n";
                $text .= "💬 {$log['message']}\n";
                $text .= "📅 _" . date('Y-m-d H:i', strtotime($log['created_at'])) . "_\n\n";
            }
        }
        else {
            $text .= "No incoming messages found.";
        }

        $keyboard = (new InlineKeyboardBuilder())
            ->addButton('⬅️ Back to SMS Menu', 'menu_sms')
            ->build();

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }
}
