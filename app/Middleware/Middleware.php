<?php
declare(strict_types=1);

/**
 * app/Middleware/middleware.php
 * Fully Corrected & Production-Ready Middleware System
 */

require_once __DIR__ . '/../Models/AuthManager.php';
require_once __DIR__ . '/../Models/SessionManager.php';
require_once __DIR__ . '/../Models/FirebaseModel.php';
require_once __DIR__ . '/../Models/SecurityManager.php';
require_once __DIR__ . '/../Models/UserModel.php';

/* =====================================================
   BOOTSTRAP DEPENDENCIES
===================================================== */

global $mysqli, $twig;

if (!isset($mysqli)) {
    throw new RuntimeException('Database connection ($mysqli) not initialized.');
}

if (!isset($twig)) {
    throw new RuntimeException('Twig instance ($twig) not initialized.');
}

$userModel = new UserModel($mysqli);
$securityManager = new SecurityManager($mysqli);

/* =====================================================
   HELPER FUNCTIONS
===================================================== */

if (!function_exists('isIpBypassRateLimit')) {
    function isIpBypassRateLimit(string $ip): bool {
        $bypassIps = getenv('RATELIMIT_BYPASS_IPS');
        if (!$bypassIps) {
            return false;
        }
        return in_array($ip, array_map('trim', explode(',', $bypassIps)), true);
    }
}

if (!function_exists('getMiddlewareTempDir')) {
    function getMiddlewareTempDir(): string {
        $base = defined('TEMP_DIR') ? TEMP_DIR : sys_get_temp_dir();
        $dir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'middleware';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}

if (!function_exists('getLoginRedirectTargetPath')) {
    function getLoginRedirectTargetPath(): string {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $requestUri = trim($requestUri);
        if ($requestUri === '') {
            return '/';
        }

        if (function_exists('isValidInternalPath') && !isValidInternalPath($requestUri)) {
            return '/';
        }

        $path = strtolower((string)(parse_url($requestUri, PHP_URL_PATH) ?? ''));
        $blockedPaths = ['/login', '/register', '/logout'];
        if (in_array($path, $blockedPaths, true) || strpos($path, '/__/auth') === 0) {
            return '/';
        }

        return $requestUri;
    }
}

if (!function_exists('redirectToLoginWithReturn')) {
    function redirectToLoginWithReturn(): void {
        $target = getLoginRedirectTargetPath();
        if ($target !== '' && $target !== '/') {
            redirect('/login?redirect=' . rawurlencode($target));
        }

        redirect('/login');
    }
}

/* =====================================================
   AUTHENTICATION MIDDLEWARE
===================================================== */

/**
 * Guest Only - Redirects authenticated users
 */
register_middleware('guest_only', function () {
    // Check if user is already authenticated
    if (AuthManager::isUserAuthenticated()) {
        $sessionMgr = AuthManager::getSessionManager();
        $isAnonymous = (bool)$sessionMgr->get('is_anonymous', false, 'bool');

        // Anonymous/guest users should still be able to access login/register pages
        // so they can upgrade into a full account.
        if ($isAnonymous) {
            return true;
        }

        $role = $sessionMgr->get('role', 'user', 'string');
        redirect($role === 'admin' ? '/admin/dashboard' : '/user/dashboard');
    }

    return true;
});

/**
 * Registered-only guard (blocks anonymous authenticated users)
 */
register_middleware('registered_only', function (array $ctx = []) {
    global $userModel;

    $userId = AuthManager::getCurrentUserId();
    if (!AuthManager::isUserAuthenticated() || !$userId) {
        logMiddlewareReject('registered_only', 'NOT_AUTHENTICATED');
        showMessage('Please log in.', 'danger');
        redirectToLoginWithReturn();
    }

    $user = $userModel->findById((int)$userId);
    $authProvider = strtolower(trim((string)($user['auth_provider'] ?? ($_SESSION['auth_provider'] ?? ''))));
    if ($authProvider !== 'anonymous') {
        return true;
    }

    $path = strtolower((string)($ctx['uri'] ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $isApiRequest = (strpos($path, '/api/') === 0)
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
        || (strpos($accept, 'application/json') !== false);

    $message = 'Guest session has limited access. Please sign in with Google/Facebook or create an account to continue.';

    logMiddlewareReject('registered_only', 'ANONYMOUS_BLOCKED', [
        'user_id' => $userId,
        'path' => $path
    ]);

    if ($isApiRequest) {
        json_response([
            'success' => false,
            'error' => $message,
            'error_code' => 'guest_upgrade_required',
            'redirect_url' => '/login?auth=upgrade'
        ], 403);
    }

    showMessage($message, 'warning');
    redirect('/login?auth=upgrade');
});

/**
 * Auth Required - Checks authentication, roles, and permissions
 */
/* =====================================================
   AUTH + ROLE + PERMISSION (Unified)
===================================================== */

register_middleware('auth', function (array $ctx = []) {
    global $userModel;

    // Check if user is authenticated
    $userId = AuthManager::getCurrentUserId();

    if (!AuthManager::isUserAuthenticated() || !$userId) {

        logMiddlewareReject(
            'auth',
            'NOT_AUTHENTICATED',
            [
                'required' => 'login'
            ]
        );

        showMessage('Please log in.', 'danger');
        redirectToLoginWithReturn();
    }

    // Anonymous session restrictions (MVP guard):
    // allow browsing/notifications, but block account/security/sensitive user actions.
    $currentPath = strtolower((string)($ctx['uri'] ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')));
    $registeredOnlyPrefixes = [
        '/profile',
        '/user/settings',
        '/user/security',
        '/user/change-password',
        '/api/oauth',
        '/api/firebase/link',
        '/api/user/linked-accounts',
        '/api/auth/skip-password-setup',
        '/applications/new',
        '/services/my-applications',
        '/services/applications',
        '/api/user/profile',
    ];

    foreach ($registeredOnlyPrefixes as $prefix) {
        if (strpos($currentPath, $prefix) === 0) {
            return run_middleware('registered_only', $ctx);
        }
    }

    // ===========================
    // Firebase ID token verification (enforce on every protected route)
    // ===========================
    if (!empty($_SESSION['firebaseToken'])) {
        try {
            $firebaseCfg = require BASE_PATH . 'Config/Firebase.php';
            $firebase = new \Firebase\FirebaseModel($firebaseCfg);
            $verify = $firebase->verifyIdToken($_SESSION['firebaseToken']);

            if (empty($verify['success'])) {
                // Try to refresh token once if refresh token exists (prevent infinite loops)
                if (!empty($_SESSION['firebase_refresh_token']) && (empty($_SESSION['firebase_refresh_attempts']) || $_SESSION['firebase_refresh_attempts'] < 2)) {
                    $ref = $firebase->refreshToken($_SESSION['firebase_refresh_token']);
                    if (!empty($ref['success'])) {
                        $_SESSION['firebaseToken'] = $ref['idToken'];
                        $_SESSION['firebase_refresh_token'] = $ref['refreshToken'] ?? $_SESSION['firebase_refresh_token'];
                        $_SESSION['firebase_refresh_attempts'] = ($_SESSION['firebase_refresh_attempts'] ?? 0) + 1;
                        $verify = $firebase->verifyIdToken($_SESSION['firebaseToken']);
                    } else {
                        AuthManager::getSessionManager()->destroySession($userId);
                        showMessage('Session expired. Please log in again.', 'danger');
                        redirectToLoginWithReturn();
                    }
                } else {
                    AuthManager::getSessionManager()->destroySession($userId);
                    showMessage('Session expired. Please log in again.', 'danger');
                    redirectToLoginWithReturn();
                }
            }

            // If verification succeeded, ensure backend user email matches Firebase email (if provided)
            if (!empty($verify['success']) && !empty($verify['email'])) {
                $u = $userModel->findById($userId);
                if ($u && isset($u['email']) && $u['email'] !== $verify['email']) {
                    AuthManager::getSessionManager()->destroySession($userId);
                    showMessage('Authentication mismatch. Please sign in again.', 'danger');
                    redirectToLoginWithReturn();
                }
            }
        } catch (Throwable $e) {
            error_log('[Middleware][Firebase Verify] ' . $e->getMessage());
            AuthManager::getSessionManager()->destroySession($userId);
            showMessage('Authentication error. Please sign in again.', 'danger');
            redirectToLoginWithReturn();
        }
    }

    // ===========================
    // Verify Firebase session cookie with revoked-check (if configured)
    // - Uses env FIREBASE_SESSION_COOKIE_NAME (default: FIREBASE_SESSION)
    // - If the session cookie has been revoked, destroy local session and log to auth_audit_log
    // ===========================
    $sessionCookieName = env('FIREBASE_SESSION_COOKIE_NAME', 'FIREBASE_SESSION');
    $sessionCookie = $_COOKIE[$sessionCookieName] ?? null;
    if (!empty($sessionCookie)) {
        try {
            $firebaseCfg = require BASE_PATH . 'Config/Firebase.php';
            $firebase = new \Firebase\FirebaseModel($firebaseCfg);
            // verifySessionCookie($cookie, $checkIfRevoked = false)
            $token = $firebase->verifySessionCookie($sessionCookie, true);
            // If verify succeeded we may compare UID with session (optional)
            $firebaseUid = $token['claims']['sub'] ?? null;
            // no-op on success
        } catch (Throwable $e) {
            // If this was a revoked-session exception, log it and destroy session
            $msg = $e->getMessage();
            error_log('[Middleware][Session Verify] ' . $msg);

            // Log consolidated audit entry for revoked session
            try {
                if (isset($securityManager) && method_exists($securityManager, 'recordAuthAudit')) {
                    $securityManager->recordAuthAudit([
                        'user_id' => $userId ?? null,
                        'event_type' => 'session',
                        'action' => 'session_revoked',
                        'status' => 'revoked',
                        'error_message' => $msg,
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                        'details' => ['cookie_name' => $sessionCookieName]
                    ]);
                }
            } catch (Throwable $ee) {
                error_log('[Middleware][Session Verify][audit] ' . $ee->getMessage());
            }

            // Destroy local session and force re-login
            AuthManager::getSessionManager()->destroySession($userId);
            showMessage('Your session has been revoked. Please sign in again.', 'danger');
            redirectToLoginWithReturn();
        }
    }

    /* ---------- ROLE CHECK ---------- */
    if (!empty($ctx['roles'])) {

        if (!$userModel->isSuperAdmin($userId)
            && !$userModel->hasAnyRole($userId, $ctx['roles'])) {

            logMiddlewareReject(
                'auth',
                'ROLE_DENIED',
                [
                    'required_roles' => implode(',', $ctx['roles']),
                    'user_id' => $userId
                ]
            );

            showMessage('Access denied (role).', 'danger');
            redirect('/');
        }
    }

    /* ---------- PERMISSION CHECK ---------- */
    if (!empty($ctx['permissions'])) {

        if (!$userModel->isSuperAdmin($userId)
            && !$userModel->hasAnyPermission($userId, $ctx['permissions'])) {

            logMiddlewareReject(
                'auth',
                'PERMISSION_DENIED',
                [
                    'required_permissions' => implode(',', $ctx['permissions']),
                    'user_id' => $userId
                ]
            );

            showMessage('Access denied (permission).', 'danger');
            redirect('/');
        }
    }

    return true;
});

/**
 * Admin Only - Requires super admin access
 */
/* =====================================================
   ADMIN / SUPER ADMIN ONLY
===================================================== */

register_middleware('admin_only', function () {
    global $userModel;

    // Check if user is authenticated
    $userId = AuthManager::getCurrentUserId();

    if (!AuthManager::isUserAuthenticated() || !$userId) {

        logMiddlewareReject(
            'admin_only',
            'NOT_AUTHENTICATED'
        );

        redirectToLoginWithReturn();
    }

    if (!$userModel->isSuperAdmin($userId)) {

        logMiddlewareReject(
            'admin_only',
            'NOT_SUPER_ADMIN',
            [
                'user_id' => $userId
            ]
        );

        showMessage('Super admin only.', 'danger');
        redirect('/');
    }

    return true;
});

/**
 * Admin or super admin only
 * - Allows role: admin
 * - Allows any super admin
 */
register_middleware('admin_or_super_only', function (array $ctx = []) {
    global $userModel;

    $userId = AuthManager::getCurrentUserId();

    if (!AuthManager::isUserAuthenticated() || !$userId) {
        logMiddlewareReject('admin_or_super_only', 'NOT_AUTHENTICATED');
        redirectToLoginWithReturn();
    }

    if ($userModel->isSuperAdmin($userId) || $userModel->hasRole($userId, 'admin')) {
        return true;
    }

    logMiddlewareReject('admin_or_super_only', 'NOT_ADMIN_OR_SUPER', ['user_id' => $userId]);
    showMessage('Admin or super admin only.', 'danger');
    redirect('/');
});

/**
 * User dashboard only
 * - Blocks users having admin/super-admin privileges from entering /user/dashboard
 * - Redirects privileged users to /admin/dashboard
 */
register_middleware('user_dashboard_only', function (array $ctx = []) {
    global $userModel;

    $userId = AuthManager::getCurrentUserId();

    if (!AuthManager::isUserAuthenticated() || !$userId) {
        logMiddlewareReject('user_dashboard_only', 'NOT_AUTHENTICATED');
        redirectToLoginWithReturn();
    }

    if ($userModel->isSuperAdmin($userId) || $userModel->hasRole($userId, 'admin')) {
        logMiddlewareReject('user_dashboard_only', 'ADMIN_BLOCKED_FROM_USER_DASHBOARD', [
            'user_id' => $userId
        ]);
        showMessage('Admin users must use the admin dashboard.', 'warning');
        redirect('/admin/dashboard');
    }

    return true;
});

/**
 * Firebase Bearer Token Middleware
 * Verifies incoming Authorization: Bearer <idToken> for API routes.
 * On success sets $_SERVER['FIREBASE_UID'] and $GLOBALS['firebase_claims']
 */
register_middleware('firebase_bearer', function(array $ctx = []) {
    // Only used for APIs
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $idToken = null;

    if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $m)) {
        $idToken = trim($m[1]);
    }

    // Fallback: accept idToken in JSON body (for clients that post JSON)
    if (!$idToken) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        if (!empty($input['idToken'])) $idToken = $input['idToken'];
    }

    if (empty($idToken)) {
        http_response_code(401);
        if (!empty($ctx['is_api'])) {
            json_response(['success' => false, 'error' => 'Missing Authorization token'], 401);
        }
        return false;
    }

    try {
        $cfg = require BASE_PATH . 'Config/Firebase.php';
        $fb = new \Firebase\FirebaseModel($cfg);
        $verify = $fb->verifyIdToken($idToken);

        if (empty($verify['success'])) {
            http_response_code(401);
            if (!empty($ctx['is_api'])) {
                json_response(['success' => false, 'error' => 'Invalid token: ' . ($verify['error'] ?? 'unknown')], 401);
            }
            return false;
        }

        // Attach verified info for downstream handlers
        $_SERVER['FIREBASE_UID'] = $verify['uid'];
        $GLOBALS['firebase_claims'] = $verify['claims'] ?? [];

        return true;
    } catch (Throwable $e) {
        error_log('[Middleware][firebase_bearer] ' . $e->getMessage());
        http_response_code(401);
        if (!empty($ctx['is_api'])) {
            json_response(['success' => false, 'error' => 'Token verification error'], 401);
        }
        return false;
    }
});


/* =====================================================
   RATE LIMITING
===================================================== */

register_middleware('rate_limit', function (array $ctx) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (isIpBypassRateLimit($ip)) {
        return true;
    }

    $window = $ctx['window'] ?? 60;
    $limit  = $ctx['limit'] ?? 100;
    $scope  = $ctx['scope'] ?? 'global';

    $dir = getMiddlewareTempDir();
    $file = $dir . '/ratelimit_' . md5($scope . $ip);

    $now = time();
    $data = ['count' => 0, 'start' => $now];

    if (file_exists($file)) {
        $stored = json_decode(file_get_contents($file), true);
        if (is_array($stored)) {
            $data = $stored;
        }

        if (($now - $data['start']) >= $window) {
            $data = ['count' => 0, 'start' => $now];
        }
    }

    $data['count']++;
    file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['count'] > $limit) {
        http_response_code(429);

        if (!empty($ctx['is_api'])) {
            json_response(['error' => 'Too many requests'], 429);
        }

        showMessage("Too many requests. Try again later.", 'danger');
        exit;
    }

    return true;
});



/* =====================================================
   CSRF PROTECTION
===================================================== */

register_middleware('csrf', function (array $ctx) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return true;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $token =
        $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $input['csrf_token']
        ?? $_POST['csrf_token']
        ?? null;

    if (!validateCsrfToken($token)) {
        showMessage('Invalid CSRF token.', 'danger');
        redirect('/');
    }

    return true;
});

/* =====================================================
   ACTIVITY LOG
===================================================== */

register_middleware('activity_log', function (array $ctx) {
    // Check if user is authenticated
    $userId = AuthManager::getCurrentUserId();
    
    if (!AuthManager::isUserAuthenticated() || !$userId) {
        return true;
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        logActivity(
            "HTTP {$method} {$uri}",
            'request',
            $userId,
            ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']
        );
    }

    return true;
});

/* =====================================================
   END
===================================================== */

/**
 * API Headers / CORS middleware
 * - Sets JSON response header for API routes
 * - Handles CORS preflight requests
 * - Decodes JSON body into $_POST for convenience
 */
register_middleware('api_headers', function (array $ctx = []) {
    // Allow CORS for API endpoints when configured
    $allowOrigin = getenv('API_ALLOW_ORIGIN') ?: '*';
    header('Access-Control-Allow-Origin: ' . $allowOrigin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-CSRF-Token, X-CSRF-TOKEN');

    // Preflight
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // If request content-type is JSON, decode into $_POST for legacy handlers
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (is_array($data)) {
            // Merge but do not overwrite existing POST values
            foreach ($data as $k => $v) {
                if (!isset($_POST[$k])) {
                    $_POST[$k] = $v;
                }
            }
        }
    }

    return true;
});


/**
 * 'admin' middleware alias — keeps older route definitions working
 */
register_middleware('admin', function (array $ctx = []) {
    return run_middleware('admin_only', $ctx);
});


/**
 * Super admin only - stricter than admin_only (explicit name used in routes)
 */
register_middleware('super_admin_only', function (array $ctx = []) {
    global $userModel;

    $userId = AuthManager::getCurrentUserId();

    if (!AuthManager::isUserAuthenticated() || !$userId) {
        logMiddlewareReject('super_admin_only', 'NOT_AUTHENTICATED');
        redirectToLoginWithReturn();
    }

    if (!$userModel->isSuperAdmin($userId)) {
        logMiddlewareReject('super_admin_only', 'NOT_SUPER_ADMIN', ['user_id' => $userId]);
        showMessage('Super admin only.', 'danger');
        redirect('/');
    }

    return true;
});
