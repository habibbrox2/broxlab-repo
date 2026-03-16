<?php
declare(strict_types = 1)
;

namespace App\Modules\SmsGateway;

use mysqli;
use App\FeatureFlags\FeatureManager;
use App\Modules\SmsGateway\AndroidApiClient;

/**
 * SmsService.php
 * Module service for handling outgoing SMS.
 */
class SmsService
{
    private mysqli $mysqli;
    private FeatureManager $featureManager;
    private AndroidApiClient $androidClient;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->featureManager = FeatureManager::getInstance($mysqli);
        $this->androidClient = new AndroidApiClient();
    }

    /**
     * Send SMS via Android device.
     */
    public function sendSms(string $phoneNumber, string $message, int $deviceId, int $simSlot = 1): bool
    {
        $this->featureManager->requireEnabled('sms_gateway');

        // Logic to fetch device API token from DB
        $stmt = $this->mysqli->prepare("SELECT api_token FROM devices WHERE id = ? AND status = 'online' LIMIT 1");
        $stmt->bind_param('i', $deviceId);
        $stmt->execute();
        $result = $stmt->get_result();
        $device = $result->fetch_assoc();

        if (!$device) {
            error_log("Device ID $deviceId not found or offline.");
            return false;
        }

        $success = $this->androidClient->sendRequest($device['api_token'], [
            'action' => 'send_sms',
            'to' => $phoneNumber,
            'message' => $message,
            'sim' => $simSlot
        ]);

        $this->logSms($deviceId, $simSlot, $phoneNumber, $message, $success ? 'sent' : 'failed');

        return $success;
    }

    private function logSms(int $deviceId, int $simSlot, string $phoneNumber, string $message, string $type): void
    {
        $stmt = $this->mysqli->prepare("INSERT INTO sms_logs (device_id, sim_slot, phone_number, message, type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iisss', $deviceId, $simSlot, $phoneNumber, $message, $type);
        $stmt->execute();
    }
}
