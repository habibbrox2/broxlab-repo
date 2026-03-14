<?php
declare(strict_types = 1)
;

namespace App\Modules\SmsGateway;

/**
 * AndroidApiClient.php
 * REST client to communicate with the Android Gateway App.
 */
class AndroidApiClient
{
    /**
     * Send a request to the Android app.
     */
    public function sendRequest(string $apiToken, array $params): bool
    {
        // This is a placeholder for the actual Android App URL logic.
        // In a real scenario, this would be an FCM message or a direct HTTP request if device has public IP.
        // For this implementation, we assume FCM/Firebase is used as the bridge.

        error_log("Sending request to Android Gateway: " . json_encode($params));
        return true;
    }
}
