<?php
declare(strict_types = 1)
;

namespace App\Modules\DeviceControl;

/**
 * DeviceControlService.php
 * Module for handling remote Android device commands.
 */
use mysqli;
use App\FeatureFlags\FeatureManager;
use App\Modules\SmsGateway\AndroidApiClient;

class DeviceControlService
{
    private mysqli $mysqli;
    private FeatureManager $featureManager;
    private AndroidApiClient $androidClient;
    private const ALLOWED_COMMANDS = ['ping', 'battery', 'reboot', 'status', 'network'];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->featureManager = FeatureManager::getInstance($mysqli);
        $this->androidClient = new AndroidApiClient();
        $this->ensureSchema();
    }

    public function executeCommand(int $deviceId, string $command, array $payload = [], ?int $requestedBy = null): array
    {
        $this->featureManager->requireEnabled('remote_device');

        $deviceId = (int)$deviceId;
        $command = strtolower(trim($command));

        if ($deviceId <= 0) {
            return ['success' => false, 'message' => 'Invalid device id.', 'dispatched' => false];
        }
        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            return ['success' => false, 'message' => 'Invalid command.', 'dispatched' => false];
        }

        $stmt = $this->mysqli->prepare("SELECT id, api_token, device_name, status FROM devices WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Failed to prepare device query.', 'dispatched' => false];
        }
        $stmt->bind_param('i', $deviceId);
        $stmt->execute();
        $device = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$device) {
            return ['success' => false, 'message' => 'Device not found.', 'dispatched' => false];
        }

        $payloadJson = !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $requestedBy = $requestedBy !== null ? (int)$requestedBy : null;
        $status = 'queued';
        $insert = $this->mysqli->prepare(
            "INSERT INTO device_control_commands (device_id, command, payload, status, requested_by, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        if (!$insert) {
            return ['success' => false, 'message' => 'Failed to create command queue entry.', 'dispatched' => false];
        }
        $insert->bind_param('isssi', $deviceId, $command, $payloadJson, $status, $requestedBy);
        if (!$insert->execute()) {
            $insert->close();
            return ['success' => false, 'message' => 'Failed to queue command.', 'dispatched' => false];
        }
        $commandId = (int)$insert->insert_id;
        $insert->close();

        $dispatched = false;
        if (($device['status'] ?? '') === 'online' && !empty($device['api_token'])) {
            $dispatched = $this->androidClient->sendRequest((string)$device['api_token'], [
                'action' => 'control',
                'command_id' => $commandId,
                'command' => $command,
                'payload' => $payload,
            ]);
        }

        if ($dispatched) {
            $update = $this->mysqli->prepare(
                "UPDATE device_control_commands
                 SET status = 'delivered', delivered_at = NOW()
                 WHERE id = ? LIMIT 1"
            );
            if ($update) {
                $update->bind_param('i', $commandId);
                $update->execute();
                $update->close();
            }
        }

        $deviceName = (string)($device['device_name'] ?? ('Device #' . $deviceId));
        $message = $dispatched
            ? "Command '{$command}' sent to {$deviceName}."
            : "Command '{$command}' queued for {$deviceName}. Device will receive it on next sync.";

        return [
            'success' => true,
            'dispatched' => $dispatched,
            'command_id' => $commandId,
            'device_name' => $deviceName,
            'device_status' => (string)($device['status'] ?? 'unknown'),
            'message' => $message,
        ];
    }

    public function updateDeviceHeartbeat(string $apiToken, array $payload = []): array
    {
        $this->featureManager->requireEnabled('remote_device');

        $device = $this->findDeviceByToken($apiToken);
        if (!$device) {
            return ['success' => false, 'error' => 'Invalid device token'];
        }

        $updates = ["status = 'online'", 'last_sync = NOW()', 'updated_at = NOW()'];
        $types = '';
        $params = [];

        if (array_key_exists('battery_level', $payload) && $payload['battery_level'] !== null && $payload['battery_level'] !== '') {
            $battery = max(0, min(100, (int)$payload['battery_level']));
            $updates[] = 'battery_level = ?';
            $types .= 'i';
            $params[] = $battery;
        }
        if (array_key_exists('is_charging', $payload)) {
            $isCharging = filter_var($payload['is_charging'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isCharging !== null) {
                $updates[] = 'is_charging = ?';
                $types .= 'i';
                $params[] = $isCharging ? 1 : 0;
            }
        }
        if (!empty($payload['device_name'])) {
            $updates[] = 'device_name = ?';
            $types .= 's';
            $params[] = trim((string)$payload['device_name']);
        }
        if (!empty($payload['device_model'])) {
            $updates[] = 'device_model = ?';
            $types .= 's';
            $params[] = trim((string)$payload['device_model']);
        }

        $sql = 'UPDATE devices SET ' . implode(', ', $updates) . ' WHERE id = ? LIMIT 1';
        $types .= 'i';
        $params[] = (int)$device['id'];

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'error' => 'Failed to update device heartbeat'];
        }
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();

        return [
            'success' => (bool)$ok,
            'device_id' => (int)$device['id'],
            'device_name' => (string)($device['device_name'] ?? ('Device #' . $device['id'])),
        ];
    }

    public function pullPendingCommands(string $apiToken, int $limit = 10): array
    {
        $this->featureManager->requireEnabled('remote_device');

        $device = $this->findDeviceByToken($apiToken);
        if (!$device) {
            return ['success' => false, 'error' => 'Invalid device token', 'commands' => []];
        }

        $limit = max(1, min(20, (int)$limit));
        $stmt = $this->mysqli->prepare(
            "SELECT id, command, payload, created_at
             FROM device_control_commands
             WHERE device_id = ? AND status = 'queued'
             ORDER BY created_at ASC
             LIMIT ?"
        );
        if (!$stmt) {
            return ['success' => false, 'error' => 'Failed to load commands', 'commands' => []];
        }
        $deviceId = (int)$device['id'];
        $stmt->bind_param('ii', $deviceId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();

        $commands = [];
        $ids = [];
        foreach ($rows as $row) {
            $commandId = (int)$row['id'];
            $ids[] = $commandId;
            $decodedPayload = [];
            if (!empty($row['payload'])) {
                $json = json_decode((string)$row['payload'], true);
                if (is_array($json)) {
                    $decodedPayload = $json;
                }
            }
            $commands[] = [
                'command_id' => $commandId,
                'command' => (string)$row['command'],
                'payload' => $decodedPayload,
                'created_at' => (string)$row['created_at'],
            ];
        }

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE device_control_commands
                    SET status = 'delivered', delivered_at = NOW()
                    WHERE id IN ({$placeholders})";
            $update = $this->mysqli->prepare($sql);
            if ($update) {
                $types = str_repeat('i', count($ids));
                $update->bind_param($types, ...$ids);
                $update->execute();
                $update->close();
            }
        }

        $touch = $this->mysqli->prepare("UPDATE devices SET status = 'online', last_sync = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1");
        if ($touch) {
            $touch->bind_param('i', $deviceId);
            $touch->execute();
            $touch->close();
        }

        return [
            'success' => true,
            'device_id' => $deviceId,
            'device_name' => (string)($device['device_name'] ?? ('Device #' . $deviceId)),
            'commands' => $commands,
        ];
    }

    public function reportCommandResult(string $apiToken, int $commandId, bool $success, string $responseText = ''): array
    {
        $this->featureManager->requireEnabled('remote_device');

        $device = $this->findDeviceByToken($apiToken);
        if (!$device) {
            return ['success' => false, 'error' => 'Invalid device token'];
        }

        $commandId = (int)$commandId;
        if ($commandId <= 0) {
            return ['success' => false, 'error' => 'Invalid command id'];
        }

        $status = $success ? 'completed' : 'failed';
        $stmt = $this->mysqli->prepare(
            "UPDATE device_control_commands
             SET status = ?, response_text = ?, executed_at = NOW()
             WHERE id = ? AND device_id = ? LIMIT 1"
        );
        if (!$stmt) {
            return ['success' => false, 'error' => 'Failed to update command result'];
        }
        $deviceId = (int)$device['id'];
        $responseText = trim($responseText);
        $stmt->bind_param('ssii', $status, $responseText, $commandId, $deviceId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected <= 0) {
            return ['success' => false, 'error' => 'Command not found for this device'];
        }

        $touch = $this->mysqli->prepare("UPDATE devices SET status = 'online', last_sync = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1");
        if ($touch) {
            $touch->bind_param('i', $deviceId);
            $touch->execute();
            $touch->close();
        }

        return [
            'success' => true,
            'command_id' => $commandId,
            'status' => $status,
        ];
    }

    public function getRecentCommands(int $limit = 200): array
    {
        $limit = max(10, min(500, (int)$limit));
        $stmt = $this->mysqli->prepare(
            "SELECT c.id, c.device_id, c.command, c.status, c.response_text, c.created_at, c.delivered_at, c.executed_at, c.requested_by,
                    d.device_name
             FROM device_control_commands c
             LEFT JOIN devices d ON d.id = c.device_id
             ORDER BY c.created_at DESC
             LIMIT ?"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
        return $rows;
    }

    public function getDeviceSummary(): array
    {
        $summary = [
            'total' => 0,
            'online' => 0,
            'offline' => 0,
            'disabled' => 0,
        ];
        $result = $this->mysqli->query(
            "SELECT status, COUNT(*) AS cnt
             FROM devices
             GROUP BY status"
        );
        if (!$result) {
            return $summary;
        }

        while ($row = $result->fetch_assoc()) {
            $status = (string)($row['status'] ?? '');
            $count = (int)($row['cnt'] ?? 0);
            $summary['total'] += $count;
            if (array_key_exists($status, $summary)) {
                $summary[$status] = $count;
            }
        }

        return $summary;
    }

    private function findDeviceByToken(string $apiToken): ?array
    {
        $apiToken = trim($apiToken);
        if ($apiToken === '') {
            return null;
        }

        $stmt = $this->mysqli->prepare(
            "SELECT id, device_name, status, api_token
             FROM devices
             WHERE api_token = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $apiToken);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    private function ensureSchema(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS device_control_commands (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    device_id INT NOT NULL,
                    command VARCHAR(50) NOT NULL,
                    payload LONGTEXT NULL,
                    status ENUM('queued','delivered','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
                    requested_by BIGINT NULL,
                    response_text TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    delivered_at TIMESTAMP NULL DEFAULT NULL,
                    executed_at TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_dcc_device_status (device_id, status, created_at),
                    INDEX idx_dcc_created_at (created_at),
                    CONSTRAINT fk_dcc_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        try {
            $this->mysqli->query($sql);
        } catch (\Throwable $e) {
            error_log('DeviceControlService ensureSchema error: ' . $e->getMessage());
        }
    }
}
