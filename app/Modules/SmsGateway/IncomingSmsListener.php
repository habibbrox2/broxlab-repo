<?php
declare(strict_types=1);

namespace App\Modules\SmsGateway;

use mysqli;
use App\FeatureFlags\FeatureManager;
use App\Telegram\TelegramService;

/**
 * IncomingSmsListener.php
 * Handles webhooks from Android devices for incoming SMS.
 * - Stores SMS in DB
 * - Forwards to Telegram admin
 * - Triggers SIM routing rules (if feature enabled)
 */
class IncomingSmsListener
{
    private mysqli $mysqli;
    private FeatureManager $featureManager;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->featureManager = FeatureManager::getInstance($mysqli);
    }

    public function handleRequest(array $data): void
    {
        $this->featureManager->requireEnabled('incoming_sms');

        $phoneNumber = (string)($data['from'] ?? '');
        $message     = (string)($data['message'] ?? '');
        $deviceId    = (int)($data['device_id'] ?? 0);
        $simSlot     = (int)($data['sim'] ?? 1);

        if ($phoneNumber === '' || $message === '' || $deviceId === 0) {
            http_response_code(400);
            return;
        }

        // 1. Store in DB
        $this->storeInDb($deviceId, $simSlot, $phoneNumber, $message);

        $settings    = new \AppSettings($this->mysqli);
        $botToken    = $settings->get('telegram_bot_token', '');
        $adminChatId = $settings->get('telegram_admin_chat_id', '');
        $deviceName  = $this->getDeviceName($deviceId);

        // 2. Forward to Telegram admin (direct notification)
        if ($adminChatId !== '' && $botToken !== '') {
            $telegram     = new TelegramService($botToken);
            $telegramText = "📨 *Incoming SMS*\n" .
                            "👤 *From:* `{$phoneNumber}`\n" .
                            "📱 *Device:* `{$deviceName}` (SIM {$simSlot})\n\n" .
                            "💬 {$message}";
            $telegram->sendMessage($adminChatId, $telegramText);
        }

        // 3. Evaluate SIM routing rules if feature is enabled
        if ($this->featureManager->isEnabled('sim_routing')) {
            $router = new SimRoutingService($this->mysqli);
            $router->process($phoneNumber, $message, $deviceId, $simSlot);
        }
    }

    private function getDeviceName(int $deviceId): string
    {
        $stmt = $this->mysqli->prepare("SELECT device_name FROM devices WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return "Device #{$deviceId}";
        }
        $stmt->bind_param('i', $deviceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (string)$row['device_name'] : "Device #{$deviceId}";
    }

    private function storeInDb(int $deviceId, int $simSlot, string $phoneNumber, string $message): void
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO sms_logs (device_id, sim_slot, phone_number, message, type) VALUES (?, ?, ?, ?, 'received')"
        );
        $stmt->bind_param('iiss', $deviceId, $simSlot, $phoneNumber, $message);
        $stmt->execute();
    }
}
