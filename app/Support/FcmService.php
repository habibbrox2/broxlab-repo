<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging (HTTP v1) — replaces the legacy
 * FirebaseModel::sendMessage() path (kreait/firebase-php).
 *
 * Authenticates with the service account (storage/firebase/broxlab-firebase.json)
 * via an RS256-signed JWT exchanged for an OAuth2 access token, then posts
 * to the FCM v1 messages:send endpoint. Disable with FCM_ENABLED=false.
 */
class FcmService
{
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    protected const FCM_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    public function serviceAccount(): ?array
    {
        $path = env('FIREBASE_SERVICE_ACCOUNT')
            ?: base_path('storage/firebase/broxlab-firebase.json');

        if (! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : null;
    }

    /**
     * Send a push notification to a single device token.
     * Return shape mirrors the legacy sendFirebaseNotification() result.
     */
    public function send(string $token, string $title, string $body, array $data = []): array
    {
        $failure = fn (string $error, ?string $code = null) => [
            'success' => false,
            'messageId' => null,
            'error' => $error,
            'error_code' => $code,
            'error_status' => null,
            'provider_response' => null,
        ];

        if (! config('services.fcm.enabled', true)) {
            return $failure('fcm_disabled');
        }

        $account = $this->serviceAccount();
        if (! $account || empty($account['project_id']) || empty($account['client_email']) || empty($account['private_key'])) {
            Log::warning('FCM: service account missing or incomplete — push skipped');

            return $failure('missing_service_account');
        }

        try {
            $accessToken = $this->accessToken($account);

            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->post(sprintf(self::FCM_URL, $account['project_id']), [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map('strval', $data),
                    ],
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'messageId' => data_get($response->json(), 'name'),
                    'error' => null,
                    'error_code' => null,
                    'error_status' => null,
                    'provider_response' => null,
                ];
            }

            $payload = $response->json() ?? [];
            $error = data_get($payload, 'error.message', $response->body());
            $status = data_get($payload, 'error.status');
            $code = data_get($payload, 'error.details.0.errorCode');

            Log::warning("FCM send failed: {$status} / {$code} / {$error}", ['token' => substr($token, 0, 20).'...']);

            return [
                'success' => false,
                'messageId' => null,
                'error' => $error,
                'error_code' => $code,
                'error_status' => $status,
                'provider_response' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('FCM send error: ' . $e->getMessage());

            return $failure($e->getMessage());
        }
    }

    /**
     * Exchange the service account JWT for an OAuth2 access token,
     * cached for the token lifetime (minus a safety margin).
     */
    protected function accessToken(array $account): string
    {
        return Cache::remember('fcm:access_token', 3000, function () use ($account) {
            $now = time();
            $claims = [
                'iss' => $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = $this->base64Url(json_encode($claims));
            $signingInput = $header . '.' . $payload;

            openssl_sign($signingInput, $signature, $account['private_key'], OPENSSL_ALGO_SHA256);

            $jwt = $signingInput . '.' . $this->base64Url($signature);

            $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('FCM token exchange failed: ' . $response->body());
            }

            return (string) $response->json('access_token');
        });
    }

    protected function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}