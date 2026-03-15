<?php

/**
 * classes/FirebaseModel.php
 * 
 * Firebase Model Class
 * Handles server-side Firebase operations (Auth, Messaging, Analytics).
 * 
 * Uses Kreait Firebase Admin SDK for secure operations.
 * 
 * @package Firebase
 * @version 2.0.0
 */

namespace Firebase;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Kreait\Firebase\Exception\MessagingException;
use Exception;
use Throwable;

class FirebaseModel
{
    private $config;
    private $projectId;
    private $serviceAccountPath;
    private $factory;
    private $auth;
    private $messaging;
    private $identityToolkitAccessToken;
    private $identityToolkitAccessTokenExpiresAt = 0;

    /**
     * Initialize Firebase Model with configuration
     * 
     * @param array $config Firebase configuration from config/firebase.php
     * @throws Exception If configuration is invalid
     */
    public function __construct(array $config)
    {
        if (!isset($config['projectId'])) {
            throw new Exception('Firebase projectId is required in config');
        }

        $this->config = $config;
        $this->projectId = $config['projectId'];

        $this->resolveServiceAccount();
        $this->initializeFactory();
    }

    /**
     * Resolve Firebase service account file path
     * 
     * @return void
     * @throws Exception If service account cannot be found
     */
    private function resolveServiceAccount(): void
    {
        $resolved = resolve_firebase_service_account();

        if (!$resolved) {
            throw new Exception('Firebase service account not found. Provide via config/firebase.php or env vars.');
        }

        // Store the resolved value directly (whether it's an array or a path string)
        $this->serviceAccountPath = $resolved['value'];
    }

    /**
     * Initialize Firebase Admin SDK Factory
     * 
     * @return void
     * @throws Exception If Firebase SDK cannot be initialized
     */
    private function initializeFactory(): void
    {
        if (!class_exists('Kreait\\Firebase\\Factory')) {
            throw new Exception('Kreait Firebase PHP SDK not found. Run: composer require kreait/firebase-php');
        }

        try {
            $this->factory = (new Factory())->withServiceAccount($this->serviceAccountPath);
        } catch (Throwable $e) {
            throw new Exception('Failed to initialize Firebase: ' . $e->getMessage());
        }
    }

    /**
     * Get Firebase Auth instance (lazy loaded)
     * 
     * @return \Kreait\Firebase\Auth
     */
    private function getAuth()
    {
        if ($this->auth === null) {
            $this->auth = $this->factory->createAuth();
        }
        return $this->auth;
    }

    /**
     * Get Firebase Messaging instance (lazy loaded)
     * 
     * @return \Kreait\Firebase\Messaging
     */
    private function getMessaging()
    {
        if ($this->messaging === null) {
            $this->messaging = $this->factory->createMessaging();
        }
        return $this->messaging;
    }

    // =====================================================
    // Authentication Methods
    // =====================================================

    /**
     * Verify Firebase ID token
     * 
     * @param string $idToken Firebase ID token
     * @return array ['success' => bool, 'uid' => string, 'claims' => array, 'error' => string]
     */
    public function verifyIdToken(string $idToken): array
    {
        try {
            if (empty($idToken)) {
                return [
                    'success' => false,
                    'error' => 'Empty ID token',
                ];
            }

            $auth = $this->getAuth();
            $leewaySeconds = (int)($this->config['idTokenLeewaySeconds'] ?? 120);
            $leewaySeconds = max(0, min(600, $leewaySeconds));

            try {
                $verified = $auth->verifyIdToken(
                    $idToken,
                    false,
                    $leewaySeconds > 0 ? $leewaySeconds : null
                );

                return [
                    'success' => true,
                    'uid' => $verified->claims()->get('sub'),
                    'claims' => $verified->claims()->all(),
                ];
            } catch (Throwable $verifyErr) {
                $errorMessage = $verifyErr->getMessage();
                $errorCode = 'token_verification_failed';
                $iatSkew = $this->getTokenIssuedAtSkew($idToken);

                if (stripos($errorMessage, 'issued in the future') !== false) {
                    $errorCode = 'token_issued_in_future';
                }

                logError('Firebase verifyIdToken primary method failed: ' . $errorMessage, 'ERROR', [
                    'error_code' => $errorCode,
                    'leeway_seconds' => $leewaySeconds,
                    'token_iat_skew_seconds' => $iatSkew,
                ]);

                return [
                    'success' => false,
                    'error' => 'Token verification failed: ' . $errorMessage,
                    'error_code' => $errorCode,
                    'meta' => [
                        'leeway_seconds' => $leewaySeconds,
                        'token_iat_skew_seconds' => $iatSkew,
                    ],
                ];
            }
        } catch (Throwable $e) {
            logError('Firebase verifyIdToken error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Token verification exception: ' . $e->getMessage(),
                'error_code' => 'token_verification_exception',
            ];
        }
    }

    /**
     * Return positive skew (seconds) if token iat is in the future compared to server time.
     * Returns null when token cannot be decoded or iat is unavailable.
     */
    private function getTokenIssuedAtSkew(string $idToken): ?int
    {
        $parts = explode('.', $idToken);
        if (count($parts) < 2) {
            return null;
        }

        $payloadSegment = strtr($parts[1], '-_', '+/');
        $paddingLength = strlen($payloadSegment) % 4;
        if ($paddingLength > 0) {
            $payloadSegment .= str_repeat('=', 4 - $paddingLength);
        }

        $payloadJson = base64_decode($payloadSegment, true);
        if ($payloadJson === false) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || !isset($payload['iat']) || !is_numeric($payload['iat'])) {
            return null;
        }

        $iat = (int)$payload['iat'];
        $now = time();
        return $iat > $now ? ($iat - $now) : 0;
    }

    /**
     * Verify a Firebase session cookie
     *
     * @param string $sessionCookie Session cookie string
     * @param bool $checkIfRevoked Whether to check if the session has been revoked
     * @return array ['success' => bool, 'uid' => string|null, 'claims' => array, 'error' => string]
     */
    public function verifySessionCookie(string $sessionCookie, bool $checkIfRevoked = false): array
    {
        try {
            if (empty($sessionCookie)) {
                return ['success' => false, 'error' => 'Empty session cookie'];
            }

            $auth = $this->getAuth();

            try {
                // Kreait provides verifySessionCookie; send $checkIfRevoked where supported
                if ($checkIfRevoked) {
                    $verified = $auth->verifySessionCookie($sessionCookie, true);
                } else {
                    $verified = $auth->verifySessionCookie($sessionCookie);
                }

                return [
                    'success' => true,
                    'uid' => $verified->claims()->get('sub'),
                    'claims' => $verified->claims()->all(),
                ];
            } catch (Throwable $verifyErr) {
                // Bubble up a clear error message for revoked sessions or verification failures
                return [
                    'success' => false,
                    'error' => 'Session cookie verification failed: ' . $verifyErr->getMessage(),
                ];
            }
        } catch (Throwable $e) {
            logError('Firebase verifySessionCookie error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Session cookie verification exception: ' . $e->getMessage()];
        }
    }

    /**
     * Get Firebase user by UID
     * 
     * @param string $uid Firebase user UID
     * @return array|null User data or null if not found
     */
    public function getUserByUid(string $uid): ?array
    {
        try {
            $auth = $this->getAuth();
            $user = $auth->getUser($uid);

            return [
                'uid' => $user->uid,
                'email' => $user->email,
                'displayName' => $user->displayName,
                'photoUrl' => $user->photoUrl,
                'emailVerified' => $user->emailVerified,
                'disabled' => $user->disabled,
                'createdAt' => $user->metadata->createdAt->format('Y-m-d H:i:s'),
                'lastSignInAt' => $user->metadata->lastLoginAt?->format('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            logError('Firebase getUserByUid error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get Firebase user by email
     * 
     * @param string $email User email address
     * @return array|null User data or null if not found
     */
    public function getUserByEmail(string $email): ?array
    {
        try {
            $auth = $this->getAuth();
            $user = $auth->getUserByEmail($email);

            return [
                'uid' => $user->uid,
                'email' => $user->email,
                'displayName' => $user->displayName,
                'photoUrl' => $user->photoUrl,
                'emailVerified' => $user->emailVerified,
                'disabled' => $user->disabled,
                'createdAt' => $user->metadata->createdAt->format('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            logError('Firebase getUserByEmail error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create Firebase user
     * 
     * @param array $data User data [email, password, displayName, photoUrl, etc.]
     * @return array ['success' => bool, 'uid' => string, 'error' => string]
     */
    public function createUser(array $data): array
    {
        try {
            $auth = $this->getAuth();
            $properties = [];

            if (!empty($data['email'])) $properties['email'] = $data['email'];
            if (!empty($data['password'])) $properties['password'] = $data['password'];
            if (!empty($data['displayName'])) $properties['displayName'] = $data['displayName'];
            if (!empty($data['photoUrl'])) $properties['photoUrl'] = $data['photoUrl'];
            if (isset($data['emailVerified'])) $properties['emailVerified'] = (bool)$data['emailVerified'];
            if (isset($data['disabled'])) $properties['disabled'] = (bool)$data['disabled'];

            $user = $auth->createUser($properties);

            return [
                'success' => true,
                'uid' => $user->uid,
                'email' => $user->email,
            ];
        } catch (Throwable $e) {
            logError('Firebase createUser error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update Firebase user
     * 
     * @param string $uid Firebase user UID
     * @param array $data User data to update [email, displayName, photoUrl, etc.]
     * @return array ['success' => bool, 'error' => string]
     */
    public function updateUser(string $uid, array $data): array
    {
        try {
            $auth = $this->getAuth();
            $properties = [];

            if (!empty($data['email'])) $properties['email'] = $data['email'];
            if (!empty($data['password'])) $properties['password'] = $data['password'];
            if (!empty($data['displayName'])) $properties['displayName'] = $data['displayName'];
            if (!empty($data['photoUrl'])) $properties['photoUrl'] = $data['photoUrl'];
            if (isset($data['emailVerified'])) $properties['emailVerified'] = (bool)$data['emailVerified'];
            if (isset($data['disabled'])) $properties['disabled'] = (bool)$data['disabled'];

            $auth->updateUser($uid, $properties);

            return ['success' => true];
        } catch (Throwable $e) {
            logError('Firebase updateUser error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete Firebase user
     * 
     * @param string $uid Firebase user UID
     * @return array ['success' => bool, 'error' => string]
     */
    public function deleteUser(string $uid): array
    {
        try {
            $auth = $this->getAuth();
            $auth->deleteUser($uid);
            return ['success' => true];
        } catch (Throwable $e) {
            logError('Firebase deleteUser error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // =====================================================
    // Firebase Cloud Messaging (FCM)
    // =====================================================

    /**
     * Send FCM message to device token
     * 
     * @param string $token Device FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Optional custom data payload
     * @return array ['success' => bool, 'messageId' => string, 'error' => string]
     */
    public function sendMessage(string $token, string $title, string $body, array $data = []): array
    {
        try {
            // Convert all data values to strings
            $dataStrings = array_map('strval', $data);

            $message = CloudMessage::new()
                ->toToken($token)
                ->withNotification(FcmNotification::create($title, $body))
                ->withData($dataStrings);

            $messaging = $this->getMessaging();
            $messageId = $messaging->send($message);

            return [
                'success' => true,
                'messageId' => $messageId,
                'error' => null,
                'error_code' => null,
                'error_status' => null,
                'provider_response' => null,
            ];
        } catch (MessagingException $e) {
            $providerResponse = null;
            $errorCode = null;
            $errorStatus = null;

            try {
                $errors = $e->errors();
                if (!empty($errors)) {
                    $providerResponse = json_encode($errors);
                    $errorStatus = $errors['error']['status'] ?? null;

                    if (!empty($errors['error']['details']) && is_array($errors['error']['details'])) {
                        foreach ($errors['error']['details'] as $detail) {
                            if (is_array($detail) && ($detail['@type'] ?? '') === 'type.googleapis.com/google.firebase.fcm.v1.FcmError') {
                                $errorCode = $detail['errorCode'] ?? $errorCode;
                                break;
                            }
                        }
                    }
                }
            } catch (Throwable $parseErr) {
                // keep defaults
            }

            // include token snippet to make it easier to debug which registration caused the issue
            $tokenSnippet = substr($token, 0, 20) . '...';
            logError("Firebase sendMessage MessagingException (token: $tokenSnippet): " . $e->getMessage(), 'ERROR', [
                'data' => [
                    'error_code' => $errorCode,
                    'error_status' => $errorStatus,
                    'provider_response' => $providerResponse,
                ]
            ]);

            return [
                'success' => false,
                'messageId' => null,
                'error' => $e->getMessage(),
                'error_code' => $errorCode,
                'error_status' => $errorStatus,
                'provider_response' => $providerResponse,
            ];
        } catch (Throwable $e) {
            $tokenSnippet = substr($token, 0, 20) . '...';
            logError("Firebase sendMessage error (token: $tokenSnippet): " . $e->getMessage());
            return [
                'success' => false,
                'messageId' => null,
                'error' => $e->getMessage(),
                'error_code' => null,
                'error_status' => null,
                'provider_response' => null,
            ];
        }
    }

    /**
     * Send FCM message to multiple tokens
     * 
     * @param array $tokens Array of device FCM tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Optional custom data payload
     * @return array Results with success/failed counts
     */
    public function sendMessageMultiple(array $tokens, string $title, string $body, array $data = []): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'total' => count($tokens),
            'messages' => [],
        ];

        foreach ($tokens as $token) {
            $result = $this->sendMessage($token, $title, $body, $data);
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            $results['messages'][] = $result;
        }

        return $results;
    }

    /**
     * Send FCM message to topic
     * 
     * @param string $topic Topic name
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Optional custom data payload
     * @return array ['success' => bool, 'messageId' => string, 'error' => string]
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        try {
            $dataStrings = array_map('strval', $data);

            $message = CloudMessage::new()
                ->toTopic($topic)
                ->withNotification(FcmNotification::create($title, $body))
                ->withData($dataStrings);

            $messaging = $this->getMessaging();
            $messageId = $messaging->send($message);

            return [
                'success' => true,
                'messageId' => $messageId,
                'topic' => $topic,
            ];
        } catch (MessagingException $e) {
            logError('Firebase sendToTopic MessagingException: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => null, // Simplify for topics unless needed
            ];
        } catch (Throwable $e) {
            logError('Firebase sendToTopic error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // =====================================================
    // Configuration Accessors
    // =====================================================

    /**
     * Get Firebase configuration for frontend SDK
     * 
     * @return array Frontend-safe Firebase config
     */
    public function getFirebaseConfig(): array
    {
        return [
            'apiKey' => $this->config['apiKey'] ?? null,
            'authDomain' => $this->config['authDomain'] ?? null,
            'projectId' => $this->projectId,
            'storageBucket' => $this->config['storageBucket'] ?? null,
            'messagingSenderId' => $this->config['fcm']['messagingSenderId'] ?? null,
            'appId' => $this->config['app']['appId'] ?? null,
            'measurementId' => $this->config['app']['measurementId'] ?? null,
            'vapidKey' => $this->config['fcm']['vapidKey'] ?? null,
        ];
    }

    /**
     * Get project ID
     * 
     * @return string Firebase project ID
     */
    public function getProjectId(): string
    {
        return $this->projectId;
    }

    /**
     * Get OAuth configuration
     * 
     * @return array OAuth provider configurations
     */
    public function getOAuthConfig(): array
    {
        return $this->config['oauth'] ?? [];
    }

    /**
     * Load service account JSON from resolved source.
     *
     * @return array|null
     */
    private function getServiceAccountCredentials(): ?array
    {
        if (is_array($this->serviceAccountPath)) {
            return $this->serviceAccountPath;
        }

        if (!is_string($this->serviceAccountPath) || !file_exists($this->serviceAccountPath)) {
            return null;
        }

        $raw = @file_get_contents($this->serviceAccountPath);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Base64 URL-safe encoding without padding.
     */
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Build Google OAuth2 access token for Identity Toolkit Admin API.
     *
     * @return array ['success'=>bool, 'access_token'=>string|null, 'expires_at'=>int, 'error'=>string, 'error_code'=>string]
     */
    private function getIdentityToolkitAccessToken(): array
    {
        $now = time();
        if (!empty($this->identityToolkitAccessToken) && $this->identityToolkitAccessTokenExpiresAt > ($now + 60)) {
            return [
                'success' => true,
                'access_token' => $this->identityToolkitAccessToken,
                'expires_at' => $this->identityToolkitAccessTokenExpiresAt,
            ];
        }

        $serviceAccount = $this->getServiceAccountCredentials();
        if (!$serviceAccount) {
            return [
                'success' => false,
                'error' => 'Service account credentials unavailable',
                'error_code' => 'service_account_unavailable',
            ];
        }

        $clientEmail = (string)($serviceAccount['client_email'] ?? '');
        $privateKey = (string)($serviceAccount['private_key'] ?? '');
        if ($clientEmail === '' || $privateKey === '') {
            return [
                'success' => false,
                'error' => 'Invalid service account credentials',
                'error_code' => 'invalid_service_account',
            ];
        }

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/identitytoolkit',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $unsignedToken = $this->base64UrlEncode(json_encode($header)) . '.' . $this->base64UrlEncode(json_encode($payload));

        $signature = '';
        $signed = openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$signed) {
            return [
                'success' => false,
                'error' => 'Failed to sign service account JWT',
                'error_code' => 'jwt_sign_failed',
            ];
        }

        $assertion = $unsignedToken . '.' . $this->base64UrlEncode($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return [
                'success' => false,
                'error' => 'Failed to request Google OAuth token: ' . $curlErr,
                'error_code' => 'token_request_failed',
            ];
        }

        $decoded = json_decode($responseBody, true);
        if ($httpCode < 200 || $httpCode >= 300 || empty($decoded['access_token'])) {
            return [
                'success' => false,
                'error' => 'Google OAuth token request failed',
                'error_code' => 'token_request_failed',
            ];
        }

        $expiresIn = (int)($decoded['expires_in'] ?? 3600);
        $this->identityToolkitAccessToken = (string)$decoded['access_token'];
        $this->identityToolkitAccessTokenExpiresAt = $now + max(60, $expiresIn);

        return [
            'success' => true,
            'access_token' => $this->identityToolkitAccessToken,
            'expires_at' => $this->identityToolkitAccessTokenExpiresAt,
        ];
    }

    /**
     * Perform request to Identity Toolkit Admin API.
     *
     * @param string $method
     * @param string $path Path under /admin/v2/projects/{projectId}
     * @param array|null $body
     * @return array ['success'=>bool, 'status'=>int, 'data'=>array, 'error'=>string, 'error_code'=>string]
     */
    private function identityToolkitAdminRequest(string $method, string $path, ?array $body = null): array
    {
        $tokenResult = $this->getIdentityToolkitAccessToken();
        if (empty($tokenResult['success'])) {
            return [
                'success' => false,
                'status' => 0,
                'data' => [],
                'error' => (string)($tokenResult['error'] ?? 'Unable to obtain access token'),
                'error_code' => (string)($tokenResult['error_code'] ?? 'token_unavailable'),
            ];
        }

        $normalizedPath = '/' . ltrim($path, '/');
        $url = 'https://identitytoolkit.googleapis.com/admin/v2/projects/' . rawurlencode($this->projectId) . $normalizedPath;

        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $tokenResult['access_token'],
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $responseBody = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return [
                'success' => false,
                'status' => 0,
                'data' => [],
                'error' => 'Identity Toolkit request failed: ' . $curlErr,
                'error_code' => 'identitytoolkit_request_failed',
            ];
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'status' => $httpCode,
                'data' => $decoded,
                'error' => '',
                'error_code' => '',
            ];
        }

        $errorMessage = (string)($decoded['error']['message'] ?? ('Identity Toolkit API returned HTTP ' . $httpCode));
        return [
            'success' => false,
            'status' => $httpCode,
            'data' => $decoded,
            'error' => $errorMessage,
            'error_code' => 'identitytoolkit_api_error',
        ];
    }

    /**
     * Read Google provider status from Firebase Auth live config.
     *
     * @return array ['success'=>bool, 'provider'=>'google', 'provider_id'=>'google.com', 'enabled'=>bool, ...]
     */
    public function getGoogleProviderStatus(): array
    {
        try {
            $response = $this->identityToolkitAdminRequest('GET', '/defaultSupportedIdpConfigs/google.com');
            if (empty($response['success'])) {
                if ((int)($response['status'] ?? 0) === 404) {
                    return [
                        'success' => true,
                        'provider' => 'google',
                        'provider_id' => 'google.com',
                        'enabled' => false,
                        'metadata' => [],
                    ];
                }

                return [
                    'success' => false,
                    'provider' => 'google',
                    'provider_id' => 'google.com',
                    'enabled' => false,
                    'error' => (string)($response['error'] ?? 'Unable to fetch google provider status'),
                    'error_code' => (string)($response['error_code'] ?? 'provider_status_fetch_failed'),
                ];
            }

            return [
                'success' => true,
                'provider' => 'google',
                'provider_id' => 'google.com',
                'enabled' => (bool)($response['data']['enabled'] ?? false),
                'metadata' => $response['data'],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'provider' => 'google',
                'provider_id' => 'google.com',
                'enabled' => false,
                'error' => $e->getMessage(),
                'error_code' => 'provider_status_exception',
            ];
        }
    }

    /**
     * Read OAuth provider status (facebook/github) from Firebase Auth live config.
     *
     * @param string $providerId ex: facebook.com, github.com
     * @return array
     */
    public function getOauthProviderStatus(string $providerId): array
    {
        $providerId = strtolower(trim($providerId));
        if ($providerId === '') {
            return [
                'success' => false,
                'provider' => '',
                'provider_id' => '',
                'enabled' => false,
                'error' => 'Provider ID is required',
                'error_code' => 'invalid_provider',
            ];
        }

        if (strpos($providerId, '.') === false) {
            $providerId .= '.com';
        }

        $provider = explode('.', $providerId)[0] ?? $providerId;

        try {
            // Some Firebase projects expose providers under defaultSupportedIdpConfigs,
            // while others use oauthIdpConfigs. Probe both for compatibility.
            $defaultSupported = $this->identityToolkitAdminRequest('GET', '/defaultSupportedIdpConfigs/' . rawurlencode($providerId));
            if (!empty($defaultSupported['success'])) {
                return [
                    'success' => true,
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'enabled' => (bool)($defaultSupported['data']['enabled'] ?? false),
                    'metadata' => $defaultSupported['data'],
                ];
            }

            $oauthConfig = $this->identityToolkitAdminRequest('GET', '/oauthIdpConfigs/' . rawurlencode($providerId));
            if (!empty($oauthConfig['success'])) {
                return [
                    'success' => true,
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'enabled' => (bool)($oauthConfig['data']['enabled'] ?? false),
                    'metadata' => $oauthConfig['data'],
                ];
            }

            $defaultStatus = (int)($defaultSupported['status'] ?? 0);
            $oauthStatus = (int)($oauthConfig['status'] ?? 0);
            if ($defaultStatus === 404 && $oauthStatus === 404) {
                return [
                    'success' => true,
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'enabled' => false,
                    'metadata' => [],
                ];
            }

            $primaryFailure = ($oauthStatus !== 404) ? $oauthConfig : $defaultSupported;
            return [
                'success' => false,
                'provider' => $provider,
                'provider_id' => $providerId,
                'enabled' => false,
                'error' => (string)($primaryFailure['error'] ?? 'Unable to fetch provider status'),
                'error_code' => (string)($primaryFailure['error_code'] ?? 'provider_status_fetch_failed'),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'provider' => $provider,
                'provider_id' => $providerId,
                'enabled' => false,
                'error' => $e->getMessage(),
                'error_code' => 'provider_status_exception',
            ];
        }
    }

    /**
     * Get live enabled OAuth providers from Firebase Auth.
     *
     * @return array ['success'=>bool, 'providers'=>array, 'count'=>int, 'source'=>'firebase_live', ...]
     */
    public function getEnabledOAuthProvidersLive(): array
    {
        $providerMeta = [
            'google' => ['name' => 'Google', 'icon' => 'fab fa-google'],
            'facebook' => ['name' => 'Facebook', 'icon' => 'fab fa-facebook'],
            'github' => ['name' => 'GitHub', 'icon' => 'fab fa-github'],
        ];

        $statuses = [
            'google' => $this->getGoogleProviderStatus(),
            'facebook' => $this->getOauthProviderStatus('facebook.com'),
            'github' => $this->getOauthProviderStatus('github.com'),
        ];

        foreach ($statuses as $status) {
            if (empty($status['success'])) {
                return [
                    'success' => false,
                    'providers' => [],
                    'count' => 0,
                    'source' => 'firebase_live',
                    'error' => (string)($status['error'] ?? 'Provider status fetch failed'),
                    'error_code' => (string)($status['error_code'] ?? 'provider_status_fetch_failed'),
                ];
            }
        }

        $enabledProviders = [];
        foreach ($statuses as $provider => $status) {
            if (empty($status['enabled'])) {
                continue;
            }

            $enabledProviders[$provider] = [
                'name' => $providerMeta[$provider]['name'],
                'icon' => $providerMeta[$provider]['icon'],
                'enabled' => true,
                'provider_id' => $status['provider_id'] ?? ($provider . '.com'),
            ];
        }

        return [
            'success' => true,
            'providers' => $enabledProviders,
            'count' => count($enabledProviders),
            'source' => 'firebase_live',
        ];
    }

    // =====================================================
    // Token Refresh
    // =====================================================

    /**
     * Refresh Firebase ID token using refresh token
     * 
     * @param string $refreshToken Firebase refresh token
     * @return array ['success' => bool, 'idToken' => string, 'refreshToken' => string, 'error' => string]
     */
    public function refreshToken(string $refreshToken): array
    {
        try {
            if (empty($refreshToken)) {
                return [
                    'success' => false,
                    'error' => 'Empty refresh token',
                ];
            }

            // Firebase token refresh via REST API
            $apiKey = $this->config['apiKey'] ?? null;
            if (!$apiKey) {
                return [
                    'success' => false,
                    'error' => 'Firebase apiKey not configured',
                ];
            }

            $url = 'https://securetoken.googleapis.com/v1/token';
            $postData = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url . '?key=' . urlencode($apiKey),
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => 'Token refresh failed with HTTP ' . $httpCode,
                ];
            }

            $data = json_decode($response, true);
            if (!$data || !isset($data['id_token'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid token refresh response',
                ];
            }

            return [
                'success' => true,
                'idToken' => $data['id_token'],
                'refreshToken' => $data['refresh_token'] ?? $refreshToken,
                'expiresIn' => $data['expires_in'] ?? 3600,
            ];
        } catch (Throwable $e) {
            logError('Firebase refreshToken error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Token refresh exception: ' . $e->getMessage(),
            ];
        }
    }
}
