<?php
declare(strict_types=1);

namespace App\Modules\SmsGateway;

use mysqli;
use App\Telegram\TelegramService;

/**
 * SimRoutingService.php
 * Evaluates SIM routing rules and performs defined actions on incoming SMS.
 */
class SimRoutingService
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Process an incoming SMS against active routing rules.
     *
     * @param string $fromNumber   Sender phone number
     * @param string $message      SMS body
     * @param int    $deviceId     Receiving device ID
     * @param int    $simSlot      SIM slot on device
     */
    public function process(string $fromNumber, string $message, int $deviceId, int $simSlot): void
    {
        $routes = $this->getActiveRoutes();
        if (empty($routes)) {
            return;
        }

        $settings    = new \AppSettings($this->mysqli);
        $botToken    = $settings->get('telegram_bot_token', '');
        $adminChatId = $settings->get('telegram_admin_chat_id', '');

        foreach ($routes as $route) {
            if (!$this->matches($route, $fromNumber, $message)) {
                continue;
            }

            $action = $route['action'];

            if (in_array($action, ['forward_telegram', 'both'], true) && $botToken !== '' && $adminChatId !== '') {
                $deviceName = $this->getDeviceName($deviceId);
                $telegram   = new TelegramService($botToken);
                $text = "📡 *SIM Route Triggered:* `{$route['label']}`\n" .
                        "👤 *From:* `{$fromNumber}`\n" .
                        "📱 *Device:* `{$deviceName}` (SIM {$simSlot})\n\n" .
                        "💬 {$message}";
                $telegram->sendMessage($adminChatId, $text);
            }

            if (in_array($action, ['reply_sms', 'both'], true) && !empty($route['reply_message'])) {
                $replyDeviceId = !empty($route['device_id']) ? (int)$route['device_id'] : $deviceId;
                $replySimSlot  = !empty($route['sim_slot']) ? (int)$route['sim_slot'] : $simSlot;
                $smsService    = new SmsService($this->mysqli);
                $smsService->sendSms($fromNumber, (string)$route['reply_message'], $replyDeviceId, $replySimSlot);
            }
        }
    }

    /**
     * Get all enabled routing rules.
     */
    private function getActiveRoutes(): array
    {
        $result = $this->mysqli->query(
            "SELECT * FROM sim_routes WHERE enabled = 1 ORDER BY id ASC"
        );
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Check if a route matches the given message.
     */
    private function matches(array $route, string $fromNumber, string $message): bool
    {
        $matchType  = $route['match_type'] ?? 'any';
        $matchValue = (string)($route['match_value'] ?? '');

        if ($matchType === 'any') {
            return true;
        }

        if ($matchType === 'sender') {
            return $matchValue !== '' && strpos($fromNumber, $matchValue) !== false;
        }

        if ($matchType === 'keyword') {
            return $matchValue !== '' && stripos($message, $matchValue) !== false;
        }

        return false;
    }

    /**
     * Get device name by ID.
     */
    private function getDeviceName(int $deviceId): string
    {
        $stmt = $this->mysqli->prepare("SELECT device_name FROM devices WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return "Device #{$deviceId}";
        }
        $stmt->bind_param('i', $deviceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? $row['device_name'] : "Device #{$deviceId}";
    }

    /**
     * List all routing rules for the Admin Panel.
     */
    public function getAllRoutes(): array
    {
        $result = $this->mysqli->query("SELECT * FROM sim_routes ORDER BY id ASC");
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Save (insert or update) a routing rule.
     */
    public function saveRoute(array $data): bool
    {
        $id           = (int)($data['id'] ?? 0);
        $label        = trim((string)($data['label'] ?? ''));
        $matchType    = in_array($data['match_type'] ?? '', ['sender', 'keyword', 'any']) ? $data['match_type'] : 'any';
        $matchValue   = trim((string)($data['match_value'] ?? '')) ?: null;
        $action       = in_array($data['action'] ?? '', ['forward_telegram', 'reply_sms', 'both']) ? $data['action'] : 'forward_telegram';
        $deviceId     = !empty($data['device_id']) ? (int)$data['device_id'] : null;
        $simSlot      = !empty($data['sim_slot']) ? (int)$data['sim_slot'] : 1;
        $replyMessage = trim((string)($data['reply_message'] ?? '')) ?: null;
        $enabled      = (int)(bool)($data['enabled'] ?? 1);

        if ($label === '') {
            return false;
        }

        if ($id > 0) {
            $stmt = $this->mysqli->prepare(
                "UPDATE sim_routes SET label=?, match_type=?, match_value=?, action=?, device_id=?, sim_slot=?, reply_message=?, enabled=? WHERE id=?"
            );
            $stmt->bind_param('ssssiisii', $label, $matchType, $matchValue, $action, $deviceId, $simSlot, $replyMessage, $enabled, $id);
        } else {
            $stmt = $this->mysqli->prepare(
                "INSERT INTO sim_routes (label, match_type, match_value, action, device_id, sim_slot, reply_message, enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('ssssiisi', $label, $matchType, $matchValue, $action, $deviceId, $simSlot, $replyMessage, $enabled);
        }

        return $stmt->execute();
    }

    /**
     * Delete a routing rule by ID.
     */
    public function deleteRoute(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM sim_routes WHERE id = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
}
