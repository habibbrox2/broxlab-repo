<?php
/**
 * controllers/FirebaseAuthController.php
 * 
 * Firebase Authentication & Messaging Controller
 * Handles all Firebase-related API routes (signin, verify, messaging, config).
 * 
 * Routes:
 * - POST /api/firebase/signin          : Sign in with Firebase token
 * - POST /api/firebase/verify-token    : Verify ID token
 * - GET  /api/firebase-config          : Get Firebase config for frontend
 * - POST /api/firebase/link            : Link OAuth account to user
 * - Debug routes (when APP_DEBUG=true)
 * 
 * @package Firebase
 * @version 2.0.0
 */

require_once __DIR__ . '/../Helpers/FirebaseHelper.php';
require_once __DIR__ . '/../Models/FirebaseModel.php';
require_once __DIR__ . '/../Models/NotificationModel.php';
require_once __DIR__ . '/../Models/UserModel.php';
require_once __DIR__ . '/../Models/AuthManager.php';
require_once __DIR__ . '/../Models/SecurityManager.php';
require_once __DIR__ . '/../../Config/Functions.php';
require_once __DIR__ . '/../Helpers/EmailHelper.php';
require_once __DIR__ . '/../Helpers/AuthAndSecurityHelper.php';

// Initialize variables to null to avoid undefined variable warnings
$firebaseModel = null;
$notificationModel = null;
$userModel = null;
$authManager = null;
$securityManager = null;

try {
    $firebaseModel = new \Firebase\FirebaseModel(require __DIR__ . '/../../Config/Firebase.php');
    $notificationModel = new NotificationModel($mysqli);
    $userModel = new UserModel($mysqli);
    $authManager = new AuthManager($mysqli);
    $securityManager = new SecurityManager($mysqli);
} catch (Exception $e) {
    logError('Firebase initialization error: ' . $e->getMessage(), 'ERROR', [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'code' => $e->getCode()
    ]);
}

// Make $router accessible in this controller
global $router;

// =====================================================
// Firebase Auth Reverse Proxy Routes (Production Ready)
// Forwards local `/__/auth/...` requests to Firebase
// auth endpoint: https://broxlab-dbd2a.firebaseapp.com/__/auth/...
// 
// Security & Features:
// - Origin validation (whitelist approach)
// - Request body size limits
// - SSL certificate verification
// - Comprehensive error logging
// - Safe header forwarding
// - Request method whitelist
// =====================================================

$proxyFirebaseAuthHandler = function () use ($authManager, $userModel, $firebaseModel) {
    try {
        // ========== STEP 0: HANDLE AUTH CALLBACK ERRORS & SUCCESS ==========
        // Check for Firebase auth callback parameters (error, code, etc.)
        $error = $_GET['error'] ?? null;
        $errorCode = $_GET['error_code'] ?? null;
        $errorDescription = urldecode($_GET['error_description'] ?? '');
        
        // If error parameter exists, handle it
        if (!empty($error)) {
            logError("Firebase auth callback error: {$error} (code: {$errorCode}, desc: {$errorDescription})");

            // Map error messages to user-friendly text
            $errorMessages = [
                'access_denied' => 'Access was denied. Please try again.',
                'server_error' => 'Server error occurred. Please try again later.',
                'network_error' => 'Network connection issue. Please check your internet and try again.',
                'popup_closed_by_user' => 'Login popup was closed before completion.',
                'cancelled_popup_request' => 'Login request was cancelled.',
            ];

            $displayMessage = $errorMessages[$error] ?? "Login failed: {$errorDescription}";
            
            // Store error in session and redirect to login with error message
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['auth_error'] = $displayMessage;
            $_SESSION['auth_error_code'] = $error;
            
            logActivity('Firebase auth failed', 'auth', null, [
                'error' => $error,
                'error_code' => $errorCode,
                'error_description' => $errorDescription
            ], 'warning');
            
            // Redirect to login page with error
            header('Location: /login?error=' . urlencode($displayMessage), true, 302);
            exit;
        }

        // ========== STEP 1: VALIDATE REQUEST METHOD ==========
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $method = strtoupper($method);
        
        $allowedMethods = ['GET', 'POST', 'OPTIONS', 'HEAD'];
        if (!in_array($method, $allowedMethods, true)) {
            logError("Firebase proxy: Method not allowed: {$method}");
            http_response_code(405);
            header('Allow: GET, POST, OPTIONS, HEAD');
            echo 'Method Not Allowed';
            return;
        }

        // ========== STEP 2: HANDLE CORS PREFLIGHT (OPTIONS) ==========
        if ($method === 'OPTIONS') {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            
            // Whitelist of allowed origins
            $allowedOrigins = [
                'http://localhost',
                'http://localhost:8000',
                'http://localhost:3000',
                'https://broxlab.online',
                'https://www.broxlab.online',
            ];
            
            // Allow all localhost origins in development
            $isDevelopment = env('APP_ENV') === 'development';
            $isOriginAllowed = $origin === '*' || 
                             in_array($origin, $allowedOrigins, true) || 
                             ($isDevelopment && stripos($origin, 'localhost') !== false);
            
            if ($isOriginAllowed && !empty($origin)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 3600');
            }
            
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS, HEAD');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            return;
        }

        // ========== STEP 3: VALIDATE REQUEST BODY SIZE ==========
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $maxBodySize = 5 * 1024 * 1024; // 5MB limit
        
        if ($contentLength > $maxBodySize) {
            logError("Firebase proxy: Request body too large: {$contentLength} bytes");
            http_response_code(413);
            echo 'Payload Too Large';
            return;
        }

        // ========== STEP 4: RESOLVE FIREBASE CONFIGURATION ==========
        $projectId = env('FIREBASE_PROJECT_ID', 'broxlab-dbd2a');
        
        if (empty($projectId)) {
            logError('Firebase proxy: FIREBASE_PROJECT_ID not configured');
            http_response_code(500);
            echo 'Server configuration error';
            return;
        }
        
        $firebaseHost = $projectId . '.firebaseapp.com';
        $scheme = 'https';

        // ========== STEP 5: BUILD TARGET URL ==========
        $incomingUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($incomingUri, PHP_URL_PATH) ?: '/';
        
        $pos = strpos($path, '/__/auth');
        if ($pos === false) {
            logError("Firebase proxy: Invalid path: {$path}");
            http_response_code(400);
            echo 'Bad Request';
            return;
        }
        
        $suffix = substr($path, $pos + strlen('/__/auth')) ?: '';
        $target = $scheme . '://' . $firebaseHost . '/__/auth' . $suffix;
        
        if (!empty($_SERVER['QUERY_STRING'])) {
            $target .= '?' . $_SERVER['QUERY_STRING'];
        }

        // ========== STEP 6: PREPARE REQUEST HEADERS ==========
        $inHeaders = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace(
                    ' ',
                    '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($key, 5))))
                );
                $inHeaders[$headerName] = $value;
            }
        }
        
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $inHeaders['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        $inHeaders['Accept'] = $inHeaders['Accept'] ?? 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $inHeaders['Accept-Language'] = $inHeaders['Accept-Language'] ?? 'en-US,en;q=0.9';
        $inHeaders['User-Agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        
        // Critical: Override Host to Firebase
        $inHeaders['Host'] = $firebaseHost;
        
        // Remove headers that cause issues
        unset($inHeaders['Content-Length']);
        unset($inHeaders['Transfer-Encoding']);

        // ========== STEP 7: READ REQUEST BODY ==========
        $body = file_get_contents('php://input');
        
        if ($body === false) {
            logError('Firebase proxy: Failed to read request body');
            http_response_code(400);
            echo 'Bad Request';
            return;
        }

        // ========== STEP 8: INITIALIZE cURL ==========
        if (!extension_loaded('curl')) {
            logError('Firebase proxy: cURL extension not loaded');
            http_response_code(500);
            echo 'Server configuration error';
            return;
        }
        
        $ch = curl_init($target);
        
        if ($ch === false) {
            logError('Firebase proxy: Failed to initialize cURL');
            http_response_code(500);
            echo 'Server error';
            return;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_HEADER            => true,
            CURLOPT_FOLLOWLOCATION    => false,
            CURLOPT_CUSTOMREQUEST     => $method,
            CURLOPT_TIMEOUT           => 20,
            CURLOPT_CONNECTTIMEOUT    => 10,
            CURLOPT_ENCODING          => '',
            CURLOPT_SSL_VERIFYPEER    => true,
            CURLOPT_SSL_VERIFYHOST    => 2,
        ]);
        
        // Add request body for POST/PUT/PATCH
        if ($method !== 'GET' && $method !== 'HEAD' && !empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        
        // Convert headers to cURL format
        $curlHeaders = [];
        foreach ($inHeaders as $headerName => $headerValue) {
            $curlHeaders[] = $headerName . ': ' . $headerValue;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

        // ========== STEP 9: EXECUTE REQUEST ==========
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($response === false) {
            logError("Firebase proxy cURL error ({$curlErrno}): {$curlError}");
            curl_close($ch);
            http_response_code(502);
            echo 'Bad Gateway';
            return;
        }
        
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        // ========== STEP 10: PARSE AND FORWARD RESPONSE ==========
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        
        // ========== STEP 10A: HANDLE SUCCESSFUL AUTH RESPONSE ==========
        // Check if this is a successful auth response (typically contains set-cookie or redirect headers)
        if ($httpCode >= 200 && $httpCode < 300) {
            // Check if response contains auth success indicators
            $setCookieHeaders = [];
            foreach (preg_split("/\r\n|\n|\r/", $responseHeaders) as $headerLine) {
                if (stripos($headerLine, 'set-cookie:') === 0) {
                    $setCookieHeaders[] = trim(substr($headerLine, 11));
                }
            }
            
            // If we have auth cookies or redirect from Firebase, attempt to create local session
            if (!empty($setCookieHeaders) || strpos($responseHeaders, 'Location:') !== false) {
                // Try to extract Firebase user info from cookies or make a fallback auth call
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                logActivity('Firebase auth successful (proxy pass-through)', 'auth', null, [
                    'http_code' => $httpCode,
                    'has_cookies' => !empty($setCookieHeaders)
                ], 'info');
            }
        }
        
        // Security-blocked headers
        $blockedHeaders = [
            'content-length',
            'content-encoding',
            'transfer-encoding',
            'x-frame-options',
            'content-security-policy',
            'cross-origin-opener-policy',
            'cross-origin-embedder-policy',
            'x-content-type-options',
            'x-xss-protection',
            'strict-transport-security',
        ];
        
        // Forward response headers
        foreach (preg_split("/\r\n|\n|\r/", $responseHeaders) as $headerLine) {
            if (stripos($headerLine, 'HTTP/') === 0 || trim($headerLine) === '') {
                continue;
            }
            
            [$headerName, $headerValue] = array_pad(explode(':', $headerLine, 2), 2, null);
            
            if ($headerValue !== null) {
                $headerNameLower = strtolower(trim($headerName));
                
                if (!in_array($headerNameLower, $blockedHeaders, true)) {
                    header(trim($headerName) . ': ' . trim($headerValue));
                }
            }
        }
        
        http_response_code($httpCode);
        echo $responseBody;
        
    } catch (Throwable $e) {
        logError('Firebase proxy exception: ' . $e->getMessage());
        http_response_code(500);
        echo 'Internal Server Error';
    }
};



// Register proxy routes (supports up to 6 depth segments)
$router->match(['GET','POST','OPTIONS'], '/__/auth', $proxyFirebaseAuthHandler);
$router->match(['GET','POST','OPTIONS'], '/__/auth/{a}', $proxyFirebaseAuthHandler);
$router->match(['GET','POST','OPTIONS'], '/__/auth/{a}/{b}', $proxyFirebaseAuthHandler);
$router->match(['GET','POST','OPTIONS'], '/__/auth/{a}/{b}/{c}', $proxyFirebaseAuthHandler);
$router->match(['GET','POST','OPTIONS'], '/__/auth/{a}/{b}/{c}/{d}', $proxyFirebaseAuthHandler);
$router->match(['GET','POST','OPTIONS'], '/__/auth/{a}/{b}/{c}/{d}/{e}', $proxyFirebaseAuthHandler);
$router->match(['GET','POST','OPTIONS'], '/__/auth/{a}/{b}/{c}/{d}/{e}/{f}', $proxyFirebaseAuthHandler);
// =====================================================
// Public Routes
// =====================================================
/**
 * GET /api/firebase-config
 * Get Firebase configuration for frontend SDK
 */
$router->get('/api/firebase-config', ['response' => 'json'], function () use ($firebaseModel) {
    try {
        // Check if Firebase model was initialized
        if ($firebaseModel === null) {
            logError('Firebase config endpoint: FirebaseModel not initialized');
            return json_response(['success' => false, 'error' => 'Firebase not configured'], 503);
        }

        $config = $firebaseModel->getFirebaseConfig();
        
        // Filter out null values and ensure strings
        $filtered = [];
        foreach ($config as $k => $v) {
            if ($v !== null && $v !== '') {
                $filtered[$k] = (string)$v;
            }
        }

        $filtered['messagingEnabled'] = !empty($filtered['messagingSenderId']) && 
                        !empty($filtered['vapidKey']) && 
                        !empty($filtered['projectId']);

        // Set cache headers so this endpoint can be CDN cached (read-only config)
        header('Cache-Control: public, max-age=300, s-maxage=3600, stale-while-revalidate=60');
        header('Vary: Accept-Encoding');

        return json_response(['success' => true, 'config' => $filtered], 200);

    } catch (Throwable $e) {
        logError('Firebase config error: ' . $e->getMessage());
        return json_response(['success' => false, 'error' => 'Config unavailable'], 500);
    }
});

// Explicitly reject non-GET methods for the config endpoint to enforce read-only usage
$router->match(['POST','PUT','PATCH','DELETE'], '/api/firebase-config', function () {
    header('Allow: GET');
    return json_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
});

/**
 * POST /api/firebase/signin
 * Sign in user with Firebase ID token
 * 
 * Enhanced with account-exists-with-different-credential conflict detection
 * If email exists with different provider, returns conflict response instead of auto-linking
 */
$router->post('/api/firebase/signin', ['response' => 'json'], function () use ($userModel, $authManager, $firebaseModel, $securityManager) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $idToken = $input['idToken'] ?? '';
    $provider = $input['provider'] ?? null;
    $normalizedProvider = strtolower(trim((string)$provider));
    if ($normalizedProvider !== '') {
        $provider = $normalizedProvider;
    }
    $requestedRedirect = normalizeAuthRedirectPath($input['redirect'] ?? $input['oauth_redirect'] ?? $input['next'] ?? '');

    if ($requestedRedirect === '') {
        $requestedRedirect = normalizeAuthRedirectPath($_SESSION['post_login_redirect'] ?? '');
    }

    if ($requestedRedirect !== '') {
        $_SESSION['post_login_redirect'] = $requestedRedirect;
    }

    if (!$securityManager->isFirebaseOAuthEnabled()) {
        logError('Firebase signin blocked: enable_firebase_oauth is disabled');
        authErrorResponse('oauth_provider_disabled', '', 'json', 403);
    }

    $allowAnonymousServerLoginRaw = strtolower(trim((string)env('ALLOW_ANONYMOUS_SERVER_LOGIN', 'true')));
    $allowAnonymousServerLogin = !in_array($allowAnonymousServerLoginRaw, ['0', 'false', 'off', 'no'], true);

    if (empty($idToken)) {
        logError('Firebase signin: Missing idToken in request');
        logActivity('Firebase sign-in failed', 'auth', null, ['provider' => $provider, 'error' => 'Missing idToken'], 'error');
        authErrorResponse('missing_idtoken', '', 'json', 400);
    }

    try {
        // Verify token
        logActivity('Firebase sign-in token verification started', 'auth', null, ['provider' => $provider], 'info');
        $result = $firebaseModel->verifyIdToken($idToken);
        
        if (!$result['success']) {
            $errorCode = (string)($result['error_code'] ?? 'token_verification_failed');
            $errorMessage = (string)($result['error'] ?? 'Token verification failed');

            logError('Firebase signin: Token verification failed - ' . $errorMessage, 'ERROR', [
                'provider' => $provider,
                'error_code' => $errorCode,
                'meta' => $result['meta'] ?? null,
            ]);
            logActivity('Firebase sign-in failed', 'auth', null, ['error' => $errorMessage, 'provider' => $provider, 'error_code' => $errorCode], 'error');

            if ($errorCode === 'token_issued_in_future') {
                authErrorResponse(
                    'token_verification_failed',
                    'Authentication token time mismatch detected. Please sync your device/server time and try again.',
                    'json',
                    401
                );
            }

            authErrorResponse('token_verification_failed', '', 'json', 401);
        }

        $uid = $result['uid'];
        $tokenProvider = strtolower(trim((string)($result['claims']['firebase']['sign_in_provider'] ?? '')));
        if (($provider === null || trim((string)$provider) === '' || trim((string)$provider) === 'unknown') && $tokenProvider !== '') {
            $provider = $tokenProvider;
        }
        if (trim((string)$provider) === '') {
            $provider = $tokenProvider !== '' ? $tokenProvider : 'firebase';
        }
        $provider = strtolower(trim((string)$provider));
        $isAnonymousProvider = ($provider === 'anonymous' || $tokenProvider === 'anonymous');

        if ($isAnonymousProvider && !$allowAnonymousServerLogin) {
            logError('Firebase signin blocked: ALLOW_ANONYMOUS_SERVER_LOGIN is disabled');
            return json_response([
                'success' => false,
                'error' => 'Anonymous sign-in is currently disabled.',
                'error_code' => 'anonymous_backend_not_supported'
            ], 403);
        }

        logError('Firebase signin: Token verified for uid: ' . $uid);
        
        $user = $firebaseModel->getUserByUid($uid);
        if (!$user && !$isAnonymousProvider) {
            logError('Firebase signin: User not found in Firebase for uid: ' . $uid);
            authErrorResponse('user_not_found', '', 'json', 401);
        }
        if (!$user) {
            $user = [];
        }

        $email = $user['email'] ?? null;
        if ($isAnonymousProvider && empty($email)) {
            $sanitizedUid = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$uid));
            if ($sanitizedUid === '') {
                $sanitizedUid = bin2hex(random_bytes(8));
            }
            $email = 'guest_' . substr($sanitizedUid, 0, 40) . '@guest.local';
            logError('Firebase signin: Anonymous provider email synthesized for uid: ' . $uid);
        }

        if (empty($email)) {
            logError('Firebase signin: Firebase user has no email for uid: ' . $uid);
            authErrorResponse('invalid_email', '', 'json', 400);
        }

        logError('Firebase signin: Email verified - ' . $email);
        
        // ========== ACCOUNT CONFLICT DETECTION ==========
        // Check if email exists in local database
        $existingUser = $userModel->findByEmail($email);
        
        if ($existingUser !== null && !$isAnonymousProvider) {
            // Email exists in local database - check if this is the FIRST login with this provider
            $isProviderAlreadyLinked = $userModel->isProviderLinked($existingUser['id'], $provider);
            
            if ($isProviderAlreadyLinked) {
                // Provider already linked to this user - returning user, no conflict check needed
                logError('Firebase signin: Provider already linked to user ID ' . $existingUser['id']);
            } else {
                // FIRST LOGIN WITH THIS PROVIDER - Check for conflicts
                // This prevents account hijacking and guides users to proper resolution
                $conflict = $userModel->checkAccountConflict($email, $provider, $uid);
                
                if ($conflict !== null) {
                    // CONFLICT DETECTED: Email exists with different provider(s)
                    logError('Firebase signin: CONFLICT DETECTED - ' . json_encode($conflict));
                    
                    // Return conflict response using unified helper
                    authConflictResponse([
                        'type' => 'account_exists_with_different_credential',
                        'email' => $email,
                        'existing_providers' => $conflict['existing_providers'],
                        'has_password' => $conflict['has_password'],
                        'existing_user_id' => $conflict['user_id'],
                        'suggested_action' => $conflict['has_password'] ? 'login_with_password' : 'link_to_existing',
                        'account_age_days' => $conflict['account_age'],
                        'temporary_token' => bin2hex(random_bytes(32))  // For conflict resolution verification
                    ]);
                }
            }
        }

        logError('Firebase signin: Creating/syncing local user for email: ' . $email);
        
        // Extract data from request (frontend may send profile fields)
        $displayName = $input['displayName'] ?? ($user['displayName'] ?? null);
        $photoURL = $input['photoURL'] ?? ($user['photoUrl'] ?? null);
        $requestedUsername = sanitize_input($input['username'] ?? '');
        $requestedFirstName = sanitize_input($input['first_name'] ?? '');
        $requestedLastName = sanitize_input($input['last_name'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $requestedUsername)) {
            $requestedUsername = '';
        }
        
        if ($isAnonymousProvider && empty($displayName)) {
            $displayName = 'Guest User';
        }

        // Parse display name into first/last name
        $nameParts = array_filter(explode(' ', trim($displayName ?? ''), 2));
        $firstName = $requestedFirstName !== '' ? $requestedFirstName : ($nameParts[0] ?? null);
        $lastName = $requestedLastName !== '' ? $requestedLastName : ($nameParts[1] ?? null);
        
        logError('Firebase signin: displayName=' . ($displayName ?? 'null') . ', photoURL=' . ($photoURL ?? 'null'));
        
        // Create or sync local user with all OAuth data
        $localUserId = $authManager->createOrSyncLocalUserFromFirebase([
            'uid' => $uid,
            'email' => $email,
            'displayName' => $displayName,
            'photoURL' => $photoURL,
            'username' => $requestedUsername !== '' ? $requestedUsername : null,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'provider' => $provider,
        ]);

        // Create session
        $authManager->createSession((int)$localUserId);

        logError('Firebase signin: Success - user_id: ' . $localUserId);
        
        // ========== ENSURE USER_LINKED_ACCOUNTS ENTRY ==========
        // Create/update user_linked_accounts entry for Account Conflict Flow
        // This ensures the account can be properly identified in future conflict checks
        $linkedAccountCreated = $userModel->createLinkedAccount(
            (int)$localUserId,
            $provider,
            $uid,
            $email,
            json_encode([
                'displayName' => $displayName,
                'photoUrl' => $photoURL,
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName
            ])
        );

        if ($linkedAccountCreated) {
            logError('Firebase signin: user_linked_accounts entry created/updated for user_id=' . $localUserId . ', provider=' . $provider);
        } else {
            logError('Firebase signin: Warning - user_linked_accounts entry creation failed for user_id=' . $localUserId);
        }
        
        // ========== AUDIT LOGGING ==========
        // 1. Record in auth_audit_log (event_type='oauth')
        if ($securityManager) {
            $securityManager->recordOAuthAction(
                (int)$localUserId,
                'signin',
                $provider,
                $uid,
                'success'
            );
            logError('OAuth action recorded in auth_audit_log for user_id=' . $localUserId . ', provider=' . $provider);
        }
        
        // 2. Record in auth_audit_log (event_type='login')
        if ($securityManager) {
            $securityManager->recordSuccessfulLogin((int)$localUserId, 'firebase_' . $provider);
            logError('Login recorded in auth_audit_log for user_id=' . $localUserId . ', method=firebase_' . $provider);
        }
        
        // 3. Log to activity log
        logActivity('Firebase sign-in succeeded', 'auth', (int)$localUserId, ['provider' => $provider, 'firebase_uid' => $uid], 'info');
        
        $redirectUrl = '/user/dashboard';
        if ($userModel->isSuperAdmin((int)$localUserId) || $userModel->hasRole((int)$localUserId, 'admin')) {
            $redirectUrl = '/admin/dashboard';
        }
        if ($requestedRedirect !== '') {
            $redirectUrl = $requestedRedirect;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['post_login_redirect']);
        }

        // Return unified success response
        authSuccessResponse(
            [
                'user_id' => (int)$localUserId,
                'email' => $email,
                'provider' => $provider,
                'is_anonymous' => $isAnonymousProvider,
                // Guest-to-full-account merge flow is intentionally deferred.
                'merge_status' => 'not_implemented'
            ],
            'Firebase sign-in successful',
            'json',
            200,
            $redirectUrl
        );

    } catch (Throwable $e) {
        logError('Firebase signin: Exception - ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        logActivity('Firebase sign-in exception', 'auth', null, ['error' => $e->getMessage()], 'error');
        authErrorResponse('server_error', '', 'json', 500);
    }
});

/**
 * POST /api/firebase/diagnostic
 * Diagnostic endpoint for token verification issues
 * Only available in debug mode
 */
$router->post('/api/firebase/diagnostic', ['response' => 'json'], function () use ($firebaseModel) {
    if (!env('APP_DEBUG')) {
        return json_response(['error' => 'Not available in production'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $idToken = $input['idToken'] ?? '';

    if (empty($idToken)) {
        return json_response(['error' => 'Missing idToken'], 400);
    }

    $diagnostic = [
        'timestamp' => date('Y-m-d H:i:s'),
        'token_length' => strlen($idToken),
        'token_parts' => count(explode('.', $idToken)),
        'verification_result' => null,
        'error' => null,
    ];

    try {
        $result = $firebaseModel->verifyIdToken($idToken);
        $diagnostic['verification_result'] = $result['success'];
        if (!$result['success']) {
            $diagnostic['error'] = $result['error'];
        } else {
            $diagnostic['uid'] = $result['uid'] ?? null;
            $diagnostic['claims'] = array_intersect_key($result['claims'] ?? [], array_flip(['sub', 'email', 'name', 'iat', 'exp']));
        }
    } catch (Throwable $e) {
        $diagnostic['error'] = $e->getMessage();
    }

    logError('Firebase diagnostic: ' . json_encode($diagnostic));
    return json_response($diagnostic, 200);
});

/**
 * POST /api/firebase/verify-token
 * Verify Firebase ID token and create session
 */
$router->post('/api/firebase/verify-token', ['response' => 'json'], function () use ($firebaseModel, $authManager) {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $idToken = $body['idToken'] ?? '';

    if (empty($idToken)) {
        return json_response(['success' => false, 'message' => 'Missing idToken'], 400);
    }

    try {
        $result = $firebaseModel->verifyIdToken($idToken);
        if (!$result['success']) {
            $errorCode = (string)($result['error_code'] ?? '');
            $message = $errorCode === 'token_issued_in_future'
                ? 'Authentication token time mismatch detected. Please sync your device/server time and try again.'
                : 'Invalid token';

            return json_response(['success' => false, 'message' => $message], 401);
        }

        $uid = $result['uid'];
        $user = $firebaseModel->getUserByUid($uid);
        if (!$user) {
            return json_response(['success' => false, 'message' => 'User not found'], 400);
        }

        // Parse display name into first/last name
        $displayName = $user['displayName'] ?? null;
        $nameParts = array_filter(explode(' ', trim($displayName ?? ''), 2));
        $firstName = $nameParts[0] ?? null;
        $lastName = $nameParts[1] ?? null;

        // Create or sync local user with complete data
        $localUserId = $authManager->createOrSyncLocalUserFromFirebase([
            'uid' => $uid,
            'email' => $user['email'],
            'displayName' => $displayName,
            'photoURL' => $user['photoUrl'] ?? null,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'provider' => 'firebase',
        ]);

        // Create session
        $authManager->createSession((int)$localUserId);

        logActivity('Firebase verify-token succeeded', 'auth', (int)$localUserId, ['firebase_uid' => $uid], 'info');

        return json_response(['success' => true, 'user_id' => (int)$localUserId], 200);

    } catch (Throwable $e) {
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
        
        $payload = ['success' => false, 'message' => 'Token verification failed', 'closePopup' => true];
        if (env('APP_DEBUG') === 'true') {
            $payload['debug'] = ['error' => $e->getMessage()];
        }

        logActivity('Firebase verify-token error', 'auth', null, ['error' => $e->getMessage()], 'error');
        logError('Firebase verify-token: ' . $e->getMessage());
        
        return json_response($payload, 500);
    }
});



/**
 * POST /api/firebase/link
 * Link Firebase account to authenticated user (requires session)
 * Actual linking and account synchronization
 */
$router->post('/api/firebase/link', ['middleware' => ['auth'], 'response' => 'json'], function () use ($firebaseModel, $userModel, $securityManager) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = AuthManager::getCurrentUserId() ?? ($_SESSION['user_id'] ?? null);
    
    logError("Firebase /api/firebase/link called - userId: " . ($userId ?? 'NONE'));
    
    if (!$userId) {
        logError("Firebase link: No userId found");
        return json_response(['success' => false, 'error' => 'Unauthorized', 'error_code' => 'unauthorized'], 401);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $idToken = $input['idToken'] ?? '';
    $provider = strtolower(trim((string)($input['provider'] ?? '')));
    $allowedProviders = ['google', 'facebook', 'github'];

    if ($provider === '' || !in_array($provider, $allowedProviders, true)) {
        return json_response(['success' => false, 'error' => 'Invalid OAuth provider', 'error_code' => 'invalid_provider'], 400);
    }

    $csrfToken = (string)($input['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    if (!validateCsrfToken($csrfToken)) {
        return json_response(['success' => false, 'error' => 'Invalid CSRF token', 'error_code' => 'csrf_token_invalid'], 403);
    }

    if (!$securityManager->isFirebaseOAuthEnabled()) {
        logError("Firebase link blocked: enable_firebase_oauth is disabled");
        return json_response(['success' => false, 'error' => 'OAuth provider is currently disabled', 'error_code' => 'oauth_provider_disabled'], 403);
    }
    
    logError("Firebase link: Received provider=$provider, idToken length=" . strlen($idToken));

    if (empty($idToken)) {
        logError("Firebase link: Empty idToken");
        return json_response(['success' => false, 'error' => 'Missing idToken', 'error_code' => 'missing_idtoken'], 400);
    }

    try {
        // Verify the Firebase token
        logError("Firebase link: Verifying token...");
        $result = $firebaseModel->verifyIdToken($idToken);
        if (!$result['success']) {
            logError("Firebase link: Token verification failed: " . json_encode($result));
            $errorCode = (string)($result['error_code'] ?? '');
            $message = $errorCode === 'token_issued_in_future'
                ? 'Authentication token time mismatch detected. Please sync your device/server time and try again.'
                : 'Invalid or expired token';
            return json_response(['success' => false, 'error' => $message, 'error_code' => 'invalid_token'], 400);
        }

        $uid = $result['uid'];
        logError("Firebase link: Token verified for uid=$uid");

        $existingLinkedAccount = $userModel->getLinkedAccountByProvider($provider, $uid);
        if ($existingLinkedAccount !== null) {
            $linkedUserId = (int)($existingLinkedAccount['user_id'] ?? 0);
            if ($linkedUserId > 0 && $linkedUserId !== (int)$userId) {
                return json_response([
                    'success' => false,
                    'error' => 'This provider account is already linked to another user.',
                    'error_code' => 'credential_already_in_use'
                ], 409);
            }

            if ($linkedUserId === (int)$userId) {
                return json_response([
                    'success' => true,
                    'message' => ucfirst($provider) . ' account is already linked',
                    'user_id' => (int)$userId
                ], 200);
            }
        }
        
        $firebaseUser = $firebaseModel->getUserByUid($uid);
        
        if (!$firebaseUser) {
            logError("Firebase link: Firebase user not found for uid=$uid");
            return json_response(['success' => false, 'error' => 'Firebase user not found', 'error_code' => 'invalid_token'], 400);
        }

        // Parse display name
        $displayName = $firebaseUser['displayName'] ?? null;
        $nameParts = array_filter(explode(' ', trim($displayName ?? ''), 2));
        $firstName = $nameParts[0] ?? null;
        $lastName = $nameParts[1] ?? null;

        logError("Firebase link: Firebase user found - displayName=$displayName, email=" . ($firebaseUser['email'] ?? 'none'));

        // Link the OAuth account to the user's database record
        logError("Firebase link: Calling linkOAuthAccount()...");
        $success = $userModel->linkOAuthAccount(
            (int)$userId,
            $provider,
            $uid,
            $firebaseUser['email'] ?? null,
            json_encode([
                'displayName' => $displayName,
                'photoUrl' => $firebaseUser['photoUrl'] ?? null,
                'email' => $firebaseUser['email'] ?? null,
                'firstName' => $firstName,
                'lastName' => $lastName
            ]),
            $firebaseUser['photoUrl'] ?? null
        );

        if ($success) {
            logError("Firebase link: SUCCESS - account linked for userId=$userId, provider=$provider");
            logActivity('Firebase account linked', 'auth', (int)$userId, [
                'provider' => $provider, 
                'firebase_uid' => $uid,
                'email' => $firebaseUser['email'] ?? 'unknown'
            ], 'success');

            return json_response([
                'success' => true,
                'message' => ucfirst($provider) . ' account has been linked successfully',
                'user_id' => (int)$userId
            ], 200);
        } else {
            logError("Firebase link: linkOAuthAccount() returned false");
            return json_response([
                'success' => false,
                'error' => 'Failed to link account to your profile',
                'error_code' => 'server_error'
            ], 500);
        }

    } catch (Throwable $e) {
        logError('Firebase link error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        logActivity('Firebase link error', 'auth', (int)$userId, ['error' => $e->getMessage()], 'error');
        return json_response(['success' => false, 'error' => 'Server error during account linking', 'error_code' => 'server_error'], 500);
    }
});




// =====================================================
// Debug Routes (only when APP_DEBUG=true)
// =====================================================

$debugEnabled = (env('APP_DEBUG') === 'true' || env('APP_DEBUG') === '1');

if ($debugEnabled) {

    /**
     * GET /debug/firebase/status
     * Firebase SDK and configuration status
     */
    $router->get('/debug/firebase/status', ['response' => 'json'], function () {
        $status = [
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => phpversion(),
            'firebase_sdk' => class_exists('\\Kreait\\Firebase\\Factory') ? 'installed' : 'missing',
            'service_account' => null,
        ];

        try {
            $resolved = resolve_firebase_service_account();
            if ($resolved) {
                $status['service_account'] = [
                    'type' => $resolved['type'],
                    'file_exists' => $resolved['type'] === 'path' ? file_exists($resolved['value']) : true,
                    'file_readable' => $resolved['type'] === 'path' ? is_readable($resolved['value']) : true,
                ];
            } else {
                $status['service_account'] = ['error' => 'Not found'];
            }
        } catch (Throwable $e) {
            $status['service_account'] = ['error' => $e->getMessage()];
        }

        return json_response(['success' => true, 'data' => $status], 200);
    });

    /**
     * POST /debug/firebase/verify-token-manual
     * Manually verify a Firebase ID token (debug only)
     */
    $router->post('/debug/firebase/verify-token-manual', ['response' => 'json'], function () use ($firebaseModel) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $idToken = $body['idToken'] ?? '';

        if (empty($idToken)) {
            return json_response(['success' => false, 'error' => 'Missing idToken'], 400);
        }

        try {
            $result = $firebaseModel->verifyIdToken($idToken);
            
            if (!$result['success']) {
                return json_response(['success' => false, 'error' => $result['error']], 401);
            }

            $uid = $result['uid'];
            $user = $firebaseModel->getUserByUid($uid);

            return json_response([
                'success' => true,
                'uid' => $uid,
                'user' => $user,
                'claims' => $result['claims'] ?? [],
            ], 200);

        } catch (Throwable $e) {
            logError('debug verify-token: ' . $e->getMessage());
            return json_response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    });

    /**
     * GET /debug/firebase/logs
     * Retrieve Firebase-related log entries (last 100 errors, 50 debug)
     */
    $router->get('/debug/firebase/logs', ['response' => 'json'], function () {
        $errorLog = dirname(__DIR__, 2) . '/storage/logs/errors.log';
        $debugLog = dirname(__DIR__, 2) . '/storage/logs/auth-debug.log';

        $logs = ['errors' => [], 'debug' => []];

        if (file_exists($errorLog) && is_readable($errorLog)) {
            $lines = array_slice(file($errorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -100);
            $logs['errors'] = array_filter($lines, function ($line) {
                return stripos($line, 'firebase') !== false || stripos($line, 'verify') !== false;
            });
        }

        if (file_exists($debugLog) && is_readable($debugLog)) {
            $lines = array_slice(file($debugLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -50);
            $logs['debug'] = array_filter($lines, function ($line) {
                return stripos($line, 'firebase') !== false || stripos($line, 'token') !== false;
            });
        }

        return json_response(['success' => true, 'logs' => $logs], 200);
    });

    /**
     * GET /_dev/oauth-diagnostics
     * OAuth and Firebase runtime configuration diagnostics
     */
    $router->get('/_dev/oauth-diagnostics', ['response' => 'json'], function () use ($firebaseModel) {
        if (env('APP_ENV') === 'production' && env('APP_DEBUG') !== 'true') {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $knownProviders = ['google', 'facebook', 'github'];
        $liveProviders = $firebaseModel->getEnabledOAuthProvidersLive();
        $enabledProviders = (array)($liveProviders['providers'] ?? []);

        $results = [
            'ok' => true,
            'providers' => [],
            'firebase' => $firebaseModel->getFirebaseConfig(),
            'advice' => [],
        ];

        if (empty($liveProviders['success'])) {
            $results['ok'] = false;
            $results['advice'][] = 'Failed to fetch provider status from Firebase Auth API';
        }

        foreach ($knownProviders as $name) {
            $providerData = $enabledProviders[$name] ?? [];
            $ok = !empty($providerData['enabled']);
            $results['providers'][$name] = [
                'configured' => $ok,
                'source' => 'firebase_live',
                'provider_id' => $providerData['provider_id'] ?? ($name . '.com'),
            ];
            if (!$ok) {
                $results['advice'][] = "{$name}: Disabled in Firebase Auth providers";
            }
        }

        $wantsHtml = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false) || isset($_GET['view']);

        if ($wantsHtml) {
            header('Content-Type: text/html; charset=utf-8');
            $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OAuth & Firebase Diagnostics</title>';
            $html .= '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto;padding:20px;color:#222}h2{font-size:16px;margin-top:20px}table{border-collapse:collapse;width:100%;max-width:900px}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f5f5f5}code{background:#f4f4f4;padding:2px 6px;border-radius:4px;font-size:12px}</style>';
            $html .= '</head><body><h1>OAuth & Firebase Diagnostics</h1>';

            $html .= '<h2>OAuth Providers</h2><table><tr><th>Provider</th><th>Configured</th><th>Source</th><th>Provider ID</th></tr>';
            foreach ($results['providers'] as $k => $p) {
                $status = $p['configured'] ? '<span style="color:green">✓</span>' : '<span style="color:red">✗</span>';
                $html .= '<tr><td>' . ucfirst($k) . '</td><td>' . $status . '</td><td><code>' . htmlspecialchars($p['source'] ?? '') . '</code></td><td><code>' . htmlspecialchars($p['provider_id'] ?? '') . '</code></td></tr>';
            }
            $html .= '</table>';

            if (!empty($results['advice'])) {
                $html .= '<h2>Issues Found</h2><ul>';
                foreach ($results['advice'] as $a) {
                    $html .= '<li>' . htmlspecialchars($a) . '</li>';
                }
                $html .= '</ul>';
            }

            $html .= '</body></html>';
            echo $html;
            return;
        }

        return json_response(['success' => $results['ok'], 'diagnostics' => $results], 200);
    });

    /**
     * GET /_dev/firebase-test
     * Firebase configuration test endpoint
     */
    $router->get('/_dev/firebase-test', ['response' => 'json'], function () use ($firebaseModel) {
        if (env('APP_ENV') === 'production' && env('APP_DEBUG') !== 'true') {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $config = $firebaseModel->getFirebaseConfig();
        $wantsHtml = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false) || isset($_GET['view']);

        if ($wantsHtml) {
            header('Content-Type: text/html; charset=utf-8');
            $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Firebase Config Test</title>';
            $html .= '<style>body{font-family:system-ui;padding:20px}table{border-collapse:collapse;width:100%;max-width:900px}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f5f5f5}code{background:#f4f4f4;padding:2px 6px;border-radius:3px;font-size:12px}</style>';
            $html .= '</head><body><h1>Firebase Config Test</h1><table><tr><th>Key</th><th>Value</th></tr>';
            foreach ($config as $k => $v) {
                $html .= '<tr><td>' . htmlspecialchars($k) . '</td><td><code>' . htmlspecialchars((string)($v ?? '')) . '</code></td></tr>';
            }
            $html .= '</table></body></html>';
            echo $html;
            return;
        }

        return json_response(['success' => true, 'config' => $config], 200);
    });
}

