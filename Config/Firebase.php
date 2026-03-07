<?php
/**
 * config/firebase.php
 * 
 * Firebase Configuration File
 * Contains all Firebase project credentials and settings.
 * 
 * SECURITY: Never commit sensitive keys to version control.
 * Use environment variables for production deployments.
 * 
 * @package Firebase
 * @version 2.0.0
 */

$normalizeDomainValue = static function ($value, $fallback) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return (string)$fallback;
    }

    // Keep only the first token, so inline comments in .env do not break the domain value.
    $parts = preg_split('/\s+/', $raw);
    $candidate = trim((string)($parts[0] ?? ''));
    $candidate = preg_replace('#^https?://#i', '', $candidate);
    $candidate = rtrim($candidate, '/');

    return $candidate !== '' ? $candidate : (string)$fallback;
};

$host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
$httpsFlag = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
$forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
$serverPort = (int)($_SERVER['SERVER_PORT'] ?? 0);

$isHttpsRequest = (
    ($httpsFlag !== '' && $httpsFlag !== 'off' && $httpsFlag !== '0') ||
    $forwardedProto === 'https' ||
    $serverPort === 443
);

$isLocalHost = (
    $host === 'localhost' ||
    strpos($host, 'localhost:') === 0 ||
    $host === '127.0.0.1' ||
    strpos($host, '127.0.0.1:') === 0 ||
    $host === '::1' ||
    strpos($host, '[::1]:') === 0
);

$localAuthDomain = $normalizeDomainValue(
    env('FIREBASE_AUTH_DOMAIN_LOCAL', 'broxlab-dbd2a.firebaseapp.com'),
    'broxlab-dbd2a.firebaseapp.com'
);
$liveAuthDomain = $normalizeDomainValue(
    env('FIREBASE_AUTH_DOMAIN_LIVE', 'broxlab.online'),
    'broxlab.online'
);

$resolvedAuthDomain = (!$isHttpsRequest && $isLocalHost) ? $localAuthDomain : $liveAuthDomain;

return [
    // Firebase Project Credentials
    'projectId' => env('FIREBASE_PROJECT_ID', 'broxlab-dbd2a'),
    'apiKey' => env('FIREBASE_API_KEY', 'AIzaSyDAyxlElm44gr2Kh5eehb2vgEsQ-RNOFwk'),
    // Auto-select auth domain:
    // - http + localhost => Firebase default auth domain
    // - otherwise        => custom production domain
    'authDomain' => $resolvedAuthDomain,
    'databaseUrl' => env('FIREBASE_DATABASE_URL', ''), // not provided in JS config
    'storageBucket' => env('FIREBASE_STORAGE_BUCKET', 'broxlab-dbd2a.firebasestorage.app'),
    
    // Service Account Key (credentials for server-side operations)
    // Supports both file path and raw JSON
    'serviceAccountKeyPath' => env('FIREBASE_SERVICE_ACCOUNT', __DIR__ . '/broxlab-firebase.json'),
    'serviceAccountKey' => env('FIREBASE_SERVICE_ACCOUNT_JSON', null),
    
    // Firebase Cloud Messaging (FCM) Settings
    'fcm' => [
        'vapidKey' => env('FIREBASE_VAPID_KEY', ''),
        'messagingSenderId' => env('FIREBASE_MESSAGING_SENDER_ID', '940556742943'),
        'serverApiKey' => env('FIREBASE_SERVER_API_KEY', ''),
    ],
    
    // Firebase App Settings
    'app' => [
        'appId' => env('FIREBASE_APP_ID', '1:940556742943:web:b81ba31457cab98d70002a'),
        'measurementId' => env('FIREBASE_MEASUREMENT_ID', 'G-P76NMZBDQJ'),
    ],
    
    // OAuth provider enable/disable is managed in Firebase Console
    // and resolved live via Firebase Admin APIs (not local client secrets).

    // ID token verification clock skew tolerance (seconds)
    // Helps when client/server time is slightly out of sync.
    'idTokenLeewaySeconds' => (int) env('FIREBASE_ID_TOKEN_LEEWAY', 120),
    
    // Database Configuration (Realtime Database)
    'database' => [
        'enabled' => env('FIREBASE_DATABASE_ENABLED', false),
        'url' => env('FIREBASE_DATABASE_URL', null),
    ],
    
    // Firestore Configuration
    'firestore' => [
        'enabled' => env('FIREBASE_FIRESTORE_ENABLED', false),
        'projectId' => env('FIREBASE_FIRESTORE_PROJECT_ID', null),
    ],
    
    // Logging and Caching
    'logging' => [
        'enabled' => true,
        'path' => __DIR__ . '/../storage/logs/firebase.log',
        'level' => env('APP_DEBUG') === 'true' ? 'debug' : 'error',
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'driver' => 'file', // file, redis, memcached
    ],
];
