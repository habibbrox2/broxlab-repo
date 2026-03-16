<?php
declare(strict_types = 1)
;

namespace App\Telegram\Callbacks;

use App\Telegram\BaseCommandHandler;
use App\Telegram\InlineKeyboardBuilder;

/**
 * SmsMenuCallback.php
 * Handles the 'menu_sms' callback query.
 */
class SmsMenuCallback extends BaseCommandHandler
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

        $text = "📱 *SMS Gateway Control*\n\nManage your connected devices and send SMS remotely.";

        $keyboard = (new InlineKeyboardBuilder())
            ->addButton('📤 Send SMS', 'sms_send')
            ->addButton('📂 SMS Logs', 'sms_logs')
            ->nextRow()
            ->addButton('📱 Devices', 'sms_devices')
            ->nextRow()
            ->addButton('⬅️ Back to Main Menu', 'menu_main')
            ->build();

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }
}
