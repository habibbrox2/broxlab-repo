<?php
declare(strict_types = 1);

namespace App\Telegram\Callbacks;

use App\Telegram\BaseCommandHandler;
use App\Telegram\InlineKeyboardBuilder;

/**
 * DeviceControlMenuCallback.php
 * Handles the 'menu_device' callback query.
 */
class DeviceControlMenuCallback extends BaseCommandHandler
{
    public function handle(array $update): void
    {
        $callbackQuery = $update['callback_query'] ?? null;
        if (!$callbackQuery) {
            return;
        }

        $chatId = (string)($callbackQuery['message']['chat']['id'] ?? '');
        if ($chatId === '') {
            return;
        }

        if (!$this->checkFeature('remote_device', $update)) {
            return;
        }

        $devices = $this->mysqli->query("SELECT id, device_name FROM devices WHERE status = 'online'");
        $builder = new InlineKeyboardBuilder();

        $text = "Remote Device Control\n\nSelect a device to manage:";

        if ($devices && $devices->num_rows > 0) {
            while ($device = $devices->fetch_assoc()) {
                $name = $device['device_name'] ?? ('Device #' . ($device['id'] ?? ''));
                $builder->addButton($name, 'device_select_' . $device['id'])->nextRow();
            }
        } else {
            $text = 'No online devices available.';
        }

        $builder->addButton('Back to Main Menu', 'menu_main');

        $this->telegram->sendMessage($chatId, $text, $builder->build());
    }
}
