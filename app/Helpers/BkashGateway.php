<?php

/**
 * helpers/BkashGateway.php
 * Lightweight bKash payment integration helper (moved from classes/)
 */

class BkashGateway
{
    private string $appKey = '';
    private string $authToken = '';
    private string $mode = 'sandbox';
    private string $baseUrl = '';

    public function __construct($mysqli = null)
    {
        // Read configuration from AppSecuritySettingsModel when available
        if ($mysqli && class_exists('AppSecuritySettingsModel')) {
            try {
                $cfg = new AppSecuritySettingsModel($mysqli);
                $this->mode = (string)$cfg->getSettingValue('bkash_mode', 'sandbox');
                $this->appKey = (string)$cfg->getSettingValue('bkash_app_key', '');
                $this->authToken = (string)$cfg->getSettingValue('bkash_auth_token', '');
            } catch (Throwable $e) {
                // ignore and fall back to env/global
            }
        }

        // Fallback to environment variables when present
        if (getenv('BKASH_MODE') !== false) $this->mode = (string)getenv('BKASH_MODE');
        if (getenv('BKASH_APP_KEY') !== false) $this->appKey = (string)getenv('BKASH_APP_KEY');
        if (getenv('BKASH_AUTH_TOKEN') !== false) $this->authToken = (string)getenv('BKASH_AUTH_TOKEN');

        $prefix = $this->mode === 'production' || $this->mode === 'prod' ? 'https://checkout.bka.sh' : 'https://checkout.sandbox.bka.sh';
        $this->baseUrl = rtrim($prefix, '/') . '/v1.2.0-beta/checkout';
    }

    /**
     * Create a payment on bKash (sale or authorize)
     * @param string $amount
     * @param string $currency
     * @param string $intent 'sale' or 'authorize'
     * @param string $merchantInvoiceNumber
     * @param string $callbackURL
     * @return array
     */
    public function createPayment(string $amount, string $currency, string $intent, string $merchantInvoiceNumber, string $callbackURL): array
    {
        $url = $this->baseUrl . '/payment/create';

        $payload = json_encode([
            'amount' => (string)$amount,
            'currency' => (string)$currency,
            'intent' => (string)$intent,
            'merchantInvoiceNumber' => (string)$merchantInvoiceNumber,
            'callbackURL' => (string)$callbackURL
        ]);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];

        if ($this->appKey !== '') {
            $headers[] = 'X-APP-Key: ' . $this->appKey;
        }
        if ($this->authToken !== '') {
            $headers[] = 'Authorization: ' . $this->authToken;
        }

        if (!function_exists('curl_init')) {
            return ['success' => false, 'error' => 'cURL is required'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            return ['success' => false, 'error' => $err ?: 'Empty response from bKash'];
        }

        $decoded = json_decode($raw, true);
        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'status' => $status, 'data' => $decoded, 'paymentID' => $decoded['paymentID'] ?? $decoded['paymentId'] ?? null, 'raw' => $decoded];
        }

        return ['success' => false, 'status' => $status, 'error' => $decoded['message'] ?? ($decoded['error'] ?? 'Unknown error'), 'raw' => $decoded];
    }

    /**
     * Execute / confirm a payment using paymentID
     * @param string $paymentID
     * @return array
     */
    public function executePayment(string $paymentID): array
    {
        $url = $this->baseUrl . '/payment/execute';
        $payload = json_encode(['paymentID' => (string)$paymentID]);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        if ($this->appKey !== '') $headers[] = 'X-APP-Key: ' . $this->appKey;
        if ($this->authToken !== '') $headers[] = 'Authorization: ' . $this->authToken;

        if (!function_exists('curl_init')) {
            return ['success' => false, 'error' => 'cURL is required'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            return ['success' => false, 'error' => $err ?: 'Empty response from bKash'];
        }

        $decoded = json_decode($raw, true);
        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'status' => $status, 'data' => $decoded, 'raw' => $decoded];
        }

        return ['success' => false, 'status' => $status, 'error' => $decoded['message'] ?? ($decoded['error'] ?? 'Unknown error'), 'raw' => $decoded];
    }

    /**
     * Query payment status (if needed)
     * @param string $paymentID
     * @return array
     */
    public function queryPayment(string $paymentID): array
    {
        $url = $this->baseUrl . '/payment/search';
        $payload = json_encode(['paymentID' => (string)$paymentID]);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        if ($this->appKey !== '') $headers[] = 'X-APP-Key: ' . $this->appKey;
        if ($this->authToken !== '') $headers[] = 'Authorization: ' . $this->authToken;

        if (!function_exists('curl_init')) {
            return ['success' => false, 'error' => 'cURL is required'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            return ['success' => false, 'error' => $err ?: 'Empty response from bKash'];
        }

        $decoded = json_decode($raw, true);
        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'status' => $status, 'data' => $decoded, 'raw' => $decoded];
        }

        return ['success' => false, 'status' => $status, 'error' => $decoded['message'] ?? ($decoded['error'] ?? 'Unknown error'), 'raw' => $decoded];
    }
}
