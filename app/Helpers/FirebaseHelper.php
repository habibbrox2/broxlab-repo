<?php

/**
 * helpers/FirebaseHelper.php
 * 
 * Firebase Helper Functions
 * Utility functions for Firebase operations (token verification, user sync, notifications).
 * 
 * @package Firebase\Helpers
 * @version 2.0.0
 */

use Firebase\FirebaseModel;

// =====================================================
// Environment and Configuration Helpers
// =====================================================

if (!function_exists('firebase_config')) {
    /**
     * Get Firebase configuration (cached)
     * 
     * @return array Firebase configuration array
     */
    function firebase_config(): array
    {
        static $cfg = null;
        if ($cfg !== null) return $cfg;

        $cfgFile = __DIR__ . '/../../Config/Firebase.php';
        if (file_exists($cfgFile)) {
            $cfg = @include $cfgFile;
            if (!is_array($cfg)) $cfg = [];
            return $cfg;
        }

        return [];
    }
}

if (!function_exists('config_valid_path')) {
    /**
     * Resolve a configuration path (file path or relative path)
     * 
     * Priority:
     * 1. Direct file existence check
     * 2. Project root relative check
     * 3. /config/ directory check
     * 
     * @param string $path Path to validate
     * @return string|null Absolute path or null if not found
     */
    function config_valid_path(?string $path): ?string
    {
        if (empty($path)) return null;

        // 1. Direct check
        if (file_exists($path)) {
            return realpath($path) ?: $path;
        }

        // 2. Root relative check
        $cleanPath = ltrim($path, '/\\');
        $rootPath = realpath(__DIR__ . '/../../');
        if ($rootPath) {
            $checkPath = $rootPath . DIRECTORY_SEPARATOR . $cleanPath;
            if (file_exists($checkPath)) {
                return $checkPath;
            }
        }

        // 3. Config directory check
        $configPath = __DIR__ . '/../../Config/' . basename($path);
        if (file_exists($configPath)) {
            return $configPath;
        }

        return null;
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable with optional default
     * 
     * @param string $key Environment variable name
     * @param mixed $default Default value if not found
     * @return mixed Environment variable value or default
     */
    function env(string $key, $default = null)
    {
        if (isset($_ENV[$key])) return $_ENV[$key];
        $val = getenv($key);
        return ($val !== false) ? $val : $default;
    }
}

// =====================================================
// Service Account Resolution
// =====================================================

if (!function_exists('resolve_firebase_service_account')) {
    /**
     * Resolve Firebase service account from multiple sources
     * 
     * Resolution order:
     * 1. config/firebase.php serviceAccountKey (raw JSON)
     * 2. config/firebase.php serviceAccountKeyPath
     * 3. Environment variables (JSON or path)
     * 4. Default file locations
     * 
     * @return array|null ['type' => 'path'|'json', 'value' => mixed]
     */
    function resolve_firebase_service_account(): ?array
    {
        $cfg = firebase_config();

        // 1. Try raw JSON from config
        if (!empty($cfg['serviceAccountKey'])) {
            $val = $cfg['serviceAccountKey'];
            if (is_array($val)) {
                return ['type' => 'json', 'value' => $val];
            }
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                if (is_array($decoded)) {
                    return ['type' => 'json', 'value' => $decoded];
                }
            }
        }

        // 2. Try path from config
        if (!empty($cfg['serviceAccountKeyPath'])) {
            $pv = config_valid_path($cfg['serviceAccountKeyPath']);
            if ($pv) return ['type' => 'path', 'value' => $pv];
        }

        // 3. Try environment variables
        $rawJson = env('FIREBASE_SERVICE_ACCOUNT_JSON') ?: getenv('FIREBASE_SERVICE_ACCOUNT_JSON');
        if ($rawJson) {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                return ['type' => 'json', 'value' => $decoded];
            }
        }

        $saEnv = env('FIREBASE_SERVICE_ACCOUNT');
        if ($saEnv && is_string($saEnv)) {
            $trim = trim($saEnv);
            if (strpos($trim, '{') === 0) {
                $decoded = json_decode($saEnv, true);
                if (is_array($decoded)) return ['type' => 'json', 'value' => $decoded];
            }
            $pv = config_valid_path($saEnv);
            if ($pv) return ['type' => 'path', 'value' => $pv];
        }

        // 4. Try default locations
        $defaults = [
            'Config/broxlab-firebase.json',
            __DIR__ . '/../../Config/broxlab-firebase.json',
        ];
        foreach ($defaults as $d) {
            $pv = config_valid_path($d);
            if ($pv) return ['type' => 'path', 'value' => $pv];
        }

        return null;
    }
}

// =====================================================
// Firebase ID Token Verification
// =====================================================

// =====================================================
// Firebase Cloud Messaging (FCM)
// =====================================================

if (!function_exists('sendFirebaseNotification')) {
    /**
     * Send FCM notification to a device token
     * 
     * @param string $token Device FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Optional data payload (all values converted to strings)
     * @return array ['success' => bool, 'messageId' => ?string, 'error' => ?string, 'provider_response' => ?string]
     */
    function sendFirebaseNotification(string $token, string $title, string $body, array $data = []): array
    {
        // ensure token management class is loaded when needed
        if (!class_exists('TokenManagementModel')) {
            $tmPath = __DIR__ . '/../Models/TokenManagementModel.php';
            if (file_exists($tmPath)) {
                require_once $tmPath;
            }
        }
        try {
            $model = new FirebaseModel(firebase_config());

            // Map array data values to strings as before
            $dataStrings = [];
            foreach ($data as $key => $value) {
                $dataStrings[$key] = (string)$value;
            }

            $result = $model->sendMessage($token, $title, $body, $dataStrings);

            // automatically classify and act on common failure reasons so callers
            // (especially those that bypass NotificationModel) can't accidentally
            // keep using dead registration tokens.
            if (is_array($result) && !$result['success']) {
                if (function_exists('classify_fcm_send_error')) {
                    $info = classify_fcm_send_error($result);
                    // try to get mysqli from global scope or a passed-in variable
                    if (!empty($GLOBALS['mysqli'])) {
                        $tmm = new \TokenManagementModel($GLOBALS['mysqli']);
                        if ($info['not_registered']) {
                            $tmm->revokeByTokenOrDevice($token, null, 'auto_not_registered');
                        }
                        if ($info['invalid_registration']) {
                            $tmm->deleteByTokenOrDevice($token, null);
                        }
                    }
                }
            }

            return $result;
        } catch (Throwable $e) {
            error_log('FCM send error: ' . $e->getMessage());
            return [
                'success' => false,
                'messageId' => null,
                'error' => $e->getMessage(),
                'error_code' => null,
                'error_status' => null,
                'provider_response' => null
            ];
        }
    }
}

if (!function_exists('classify_fcm_send_error')) {
    /**
     * Normalize FCM send errors into actionable flags.
     *
     * @param array $result Send result array from sendFirebaseNotification()
     * @return array{
     *   error: string,
     *   error_code: string,
     *   error_status: string,
     *   not_registered: bool,
     *   invalid_registration: bool,
     *   sender_mismatch: bool
     * }
     */
    function classify_fcm_send_error(array $result): array
    {
        $error = (string)($result['error'] ?? '');
        $errorCode = strtoupper(trim((string)($result['error_code'] ?? '')));
        $errorStatus = strtoupper(trim((string)($result['error_status'] ?? '')));
        $errLower = strtolower($error);

        $notRegistered = (
            $errorCode === 'UNREGISTERED' ||
            $errorStatus === 'NOT_FOUND' ||
            strpos($errLower, 'requested entity was not found') !== false ||
            strpos($errLower, 'registration-token-not-registered') !== false ||
            strpos($errLower, 'notregistered') !== false ||
            strpos($errLower, 'not registered') !== false ||
            strpos($errLower, 'unregistered') !== false
        );

        $invalidRegistration = (
            $errorCode === 'INVALID_ARGUMENT' ||
            $errorCode === 'INVALID_REGISTRATION' ||
            strpos($errLower, 'invalidregistration') !== false ||
            strpos($errLower, 'invalid registration') !== false ||
            strpos($errLower, 'invalid argument') !== false ||
            strpos($errLower, 'invalid token') !== false ||
            strpos($errLower, 'not a valid fcm registration token') !== false
        );

        $senderMismatch = (
            $errorCode === 'SENDER_ID_MISMATCH' ||
            $errorCode === 'MISMATCHED_CREDENTIAL' ||
            strpos($errLower, 'senderid') !== false ||
            (strpos($errLower, 'sender') !== false && strpos($errLower, 'mismatch') !== false) ||
            strpos($errLower, 'mismatched credential') !== false
        );

        return [
            'error' => $error,
            'error_code' => $errorCode,
            'error_status' => $errorStatus,
            'not_registered' => $notRegistered,
            'invalid_registration' => $invalidRegistration,
            'sender_mismatch' => $senderMismatch
        ];
    }
}

// =====================================================
// Local User Management
// =====================================================

if (!function_exists('diagnose_senderId_mismatch')) {
    /**
     * Diagnose and fix SenderId mismatch issues
     * Cleans invalid tokens from database
     * 
     * @param mysqli $mysqli Database connection
     * @param bool $auto_cleanup Whether to auto-cleanup invalid tokens
     * @return array Diagnostic results
     */
    function diagnose_senderId_mismatch($mysqli, $auto_cleanup = true): array
    {
        try {
            $config = firebase_config();
            $senderId = $config['fcm']['messagingSenderId'] ?? null;
            $vapidKey = $config['fcm']['vapidKey'] ?? null;

            // Check Firebase configuration
            if (empty($senderId)) {
                error_log('Firebase SenderId is not configured');
                return [
                    'status' => 'error',
                    'message' => 'Firebase Sender ID not configured',
                    'senderId_configured' => false,
                    'tokens_count' => 0,
                    'invalid_tokens_removed' => 0
                ];
            }

            // Check for valid tokens in database
            $result = $mysqli->query("SELECT COUNT(*) as cnt FROM fcm_tokens");
            $tokenCount = $result->fetch_assoc()['cnt'] ?? 0;

            // If auto_cleanup enabled, remove suspicious/old tokens
            if ($auto_cleanup && $tokenCount > 0) {
                // Remove tokens older than 90 days (likely stale)
                $nineDaysAgo = date('Y-m-d H:i:s', strtotime('-90 days'));
                $backupStmt = $mysqli->prepare("
                    INSERT INTO fcm_tokens 
                    SELECT * FROM fcm_tokens 
                    WHERE created_at < ? AND user_id IS NOT NULL
                ");
                $backupStmt->bind_param('s', $nineDaysAgo);
                $backupStmt->execute();
                $backupStmt->close();

                $deleteStmt = $mysqli->prepare("
                    DELETE FROM fcm_tokens 
                    WHERE created_at < ? AND user_id IS NOT NULL
                ");
                $deleteStmt->bind_param('s', $nineDaysAgo);
                $deleteStmt->execute();
                $deletedCount = $deleteStmt->affected_rows;
                $deleteStmt->close();

                error_log("Removed $deletedCount stale tokens (older than 90 days)");

                return [
                    'status' => 'cleaned',
                    'message' => "Cleaned $deletedCount stale tokens and reconfigured SenderId",
                    'senderId_configured' => true,
                    'sender_id' => substr($senderId, 0, 10) . '...',
                    'tokens_before' => $tokenCount,
                    'tokens_removed' => $deletedCount,
                    'tokens_after' => $tokenCount - $deletedCount
                ];
            }

            return [
                'status' => 'ok',
                'message' => 'Firebase configuration is valid',
                'senderId_configured' => true,
                'sender_id' => substr($senderId, 0, 10) . '...',
                'tokens_count' => $tokenCount,
                'tokens_removed' => 0
            ];
        } catch (Exception $e) {
            error_log('Diagnosis error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'senderId_configured' => false
            ];
        }
    }
}

if (!function_exists('validate_firebase_messaging')) {
    /**
     * Validate Firebase messaging setup is correct
     * 
     * @return array Validation results with recommendations
     */
    function validate_firebase_messaging(): array
    {
        $config = firebase_config();
        $issues = [];
        $recommendations = [];

        // Check configuration (Client-side requirements)
        if (empty($config['fcm']['messagingSenderId'])) {
            $issues[] = 'Messaging Sender ID not configured';
            $recommendations[] = 'Set FIREBASE_MESSAGING_SENDER_ID environment variable';
        }

        if (empty($config['fcm']['vapidKey'])) {
            $issues[] = 'VAPID Key not configured';
            $recommendations[] = 'Set FIREBASE_VAPID_KEY environment variable';
        }

        // NOTE: Server API Key is OPTIONAL - only needed for REST API method
        // Kreait SDK uses service account authentication which is more secure

        // Check service account (Server-side requirement for Kreait SDK)
        $sa = resolve_firebase_service_account();
        if (!$sa) {
            $issues[] = 'Firebase service account not found';
            $recommendations[] = 'Place service account JSON in /Config/broxlab-firebase.json';
        } elseif ($sa['type'] === 'json') {
            $json = $sa['value'];
            if (empty($json['project_id'])) {
                $issues[] = 'Service account project_id is missing';
                $recommendations[] = 'Re-download service account from Firebase Console';
            }
        }

        return [
            'valid' => count($issues) === 0,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'status_summary' => count($issues) === 0
                ? 'Firebase messaging is properly configured'
                : 'Firebase messaging has ' . count($issues) . ' configuration issues'
        ];
    }
}
