<?php

// Config/Functions.php

/**
 * IMPORTANT: All define() constants are now in Config/Constants.php
 * This file contains helper functions only
 */

// ==================== LOAD HELPER FILES ====================
require_once dirname(__DIR__, 1) . '/app/Helpers/NotificationHelper.php';
require_once dirname(__DIR__, 1) . '/app/Helpers/BreadcrumbGenerator.php';
require_once dirname(__DIR__, 1) . '/app/Helpers/UrlHelper.php';
require_once dirname(__DIR__, 1) . '/app/Helpers/ClientInfoHelper.php';
require_once dirname(__DIR__, 1) . '/app/Helpers/PurifierHelper.php';


// ==================== SEO HELPER FUNCTIONS ====================

/**
 * Generate Canonical URL (SEO-safe: removes query params and fragments)
 * 
 * @return string Canonical URL without query parameters or fragments
 * 
 * Example:
 *   URL: https://example.com/posts?page=2&sort=date
 *   Result: https://example.com/posts
 */
if (!function_exists('getCanonicalUrl')) {
    function getCanonicalUrl()
    {
        $helper = new UrlHelper();
        return $helper->getCanonical();
    }
}

/**
 * Get Current Full URL (including query params)
 * 
 * @return string Full current URL
 */
if (!function_exists('getCurrentUrl')) {
    function getCurrentUrl()
    {
        $helper = new UrlHelper();
        return $helper->getCurrent();
    }
}

if (!function_exists('bootstrap_controller')) {
    /**
     * Load shared controller dependencies from the global scope.
     *
     * @param array<int, string> $dependencies
     * @return array<string, mixed>
     */
    function bootstrap_controller(array $dependencies): array
    {
        $resolved = [];

        foreach ($dependencies as $dependency) {
            if (!is_string($dependency)) {
                continue;
            }

            if (!array_key_exists($dependency, $GLOBALS)) {
                throw new RuntimeException("Controller dependency '{$dependency}' is not available in global scope");
            }

            $resolved[$dependency] = $GLOBALS[$dependency];
        }

        return $resolved;
    }
}





if (!function_exists('sanitize_input')) {

    /**
     * @param mixed $data
     * @return string
     */
    function sanitize_input($data)
    {

        if ($data === null || $data === '') {

            return '';
        }



        $data = trim((string) $data);



        $possibleFormats = ['d-m-Y', 'd/m/Y', 'm-d-Y', 'm/d/Y', 'Y-m-d'];



        foreach ($possibleFormats as $format) {

            $date = DateTime::createFromFormat($format, $data);

            if ($date && $date->format($format) === $data) {

                return $date->format('Y-m-d');
            }
        }
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

        return $data;
    }
}



if (!function_exists('generateCsrfToken')) {
    /**
     * Generate or retrieve CSRF token
     * CONSOLIDATED: Delegates to SessionManager for single source of truth
     * 
     * @return string CSRF token
     */
    function generateCsrfToken()
    {
        try {
            $sessionMgr = SessionManager::getInstance();
            return $sessionMgr->generateCsrfToken();
        } catch (Throwable $e) {
            error_log("generateCsrfToken error: " . $e->getMessage());
            // Fallback to direct session management if SessionManager fails
            if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }
    }
}



if (!function_exists('validateCsrfToken')) {
    /**
     * Validate CSRF token
     * CONSOLIDATED: Delegates to SessionManager for single source of truth
     * 
     * @param string|null $token Token to validate
     * @return bool Validation result
     */
    function validateCsrfToken($token)
    {
        try {
            $sessionMgr = SessionManager::getInstance();
            return $sessionMgr->validateCsrfToken($token);
        } catch (Throwable $e) {
            error_log("validateCsrfToken error: " . $e->getMessage());
            // Fallback to direct comparison
            if (empty($token) || empty($_SESSION['csrf_token'])) {
                return false;
            }
            return hash_equals($_SESSION['csrf_token'], $token);
        }
    }
}

if (!function_exists('getCsrfTokenFromRequest')) {
    /**
     * Extract CSRF token from current request (POST body or header)
     *
     * @return string|null
     */
    function getCsrfTokenFromRequest(): ?string
    {
        if (isset($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        $headers = [
            'HTTP_X_CSRF_TOKEN',
            'HTTP_X_XSRF_TOKEN',
            'X-CSRF-Token',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }

        return $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? $_SERVER['X-CSRF-Token'] ?? null;
    }
}

/**
 * ============================================================
 * AUTH RESPONSE HELPERS (moved from helpers/auth-response-helper.php)
 * Single source for JSON/redirect responses across controllers
 * ============================================================
 */
if (!function_exists('authResponse')) {
    /**
     * Generate unified auth response for both API and form-based auth
     *
     * @param array $data Response data
     * @param string $type Response type: 'json' or 'redirect'
     * @param int|null $statusCode HTTP status code (for JSON responses)
     * @param string|null $redirectUrl URL to redirect to (for form responses)
     * @return void
     */
    function authResponse(array $data, string $type = 'json', ?int $statusCode = 200, ?string $redirectUrl = null): void
    {
        // Normalize status codes
        if (!isset($data['status'])) {
            $data['status'] = $data['success'] ? 'success' : 'error';
        }

        // Backward compatibility: expose error_code alias for older JS handlers
        if (isset($data['code']) && !isset($data['error_code'])) {
            $data['error_code'] = $data['code'];
        }

        // Ensure consistent message key
        if (!isset($data['message']) && isset($data['error'])) {
            $data['message'] = $data['error'];
        }

        if ($type === 'json') {
            if ($redirectUrl) {
                $data['redirect_url'] = $redirectUrl;
            }
            http_response_code($statusCode ?? 200);
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }

        // Redirect response
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($data['message'])) {
            $_SESSION['flash_message'] = [
                'message' => $data['message'],
                'text' => $data['message'],
                'type' => $data['status'] === 'success' ? 'success' : 'danger',
                'status' => $data['status'] === 'success' ? 'success' : 'danger',
                'timestamp' => time(),
            ];
        }

        if ($redirectUrl) {
            header("Location: {$redirectUrl}");
        }
        exit;
    }
}

if (!function_exists('authErrorResponse')) {
    function authErrorResponse(
        string $code,
        string $message = '',
        string $type = 'json',
        int $statusCode = 400,
        ?string $redirectUrl = null
    ): void {
        $defaultMessages = [
            'invalid_credentials' => 'Invalid username/email or password',
            'user_not_found' => 'User not found',
            'email_not_verified' => 'Please verify your email to login',
            'account_locked' => 'Your account is locked. Please try again later',
            'account_inactive' => 'Your account is not active',
            'invalid_email' => 'Invalid email format',
            'invalid_username' => 'Invalid username format',
            'password_mismatch' => 'Passwords do not match',
            'weak_password' => 'Password is too weak',
            'email_exists' => 'Email already registered',
            'username_exists' => 'Username already taken',
            'invalid_token' => 'Invalid or expired token',
            'firebase_signin_failed' => 'Firebase sign in failed',
            'account_exists_with_different_credential' => 'This email is already registered with a different login method',
            'missing_idtoken' => 'Missing Firebase ID token',
            'token_verification_failed' => 'Token verification failed',
            'oauth_provider_disabled' => 'OAuth provider is currently disabled',
            'rate_limit_exceeded' => 'Too many login attempts. Please try again later',
            'csrf_token_invalid' => 'Invalid request token. Please try again',
            'server_error' => 'An error occurred. Please try again later',
        ];

        $finalMessage = $message ?: ($defaultMessages[$code] ?? 'Authentication failed');

        authResponse(
            [
                'success' => false,
                'status' => 'error',
                'code' => $code,
                'message' => $finalMessage,
            ],
            $type,
            $statusCode,
            $redirectUrl
        );
    }
}

if (!function_exists('authSuccessResponse')) {
    function authSuccessResponse(
        array $extraData = [],
        string $message = 'Login successful',
        string $type = 'json',
        int $statusCode = 200,
        ?string $redirectUrl = null
    ): void {
        authResponse(
            array_merge([
                'success' => true,
                'status' => 'success',
                'message' => $message,
            ], $extraData),
            $type,
            $statusCode,
            $redirectUrl
        );
    }
}

if (!function_exists('authConflictResponse')) {
    function authConflictResponse(array $conflictData): void
    {
        authResponse(
            [
                'success' => false,
                'status' => 'conflict',
                'code' => 'account_exists_with_different_credential',
                'message' => 'This email is already registered with a different login method',
                'conflict' => $conflictData,
            ],
            'json',
            409
        );
    }
}

if (!function_exists('getAuthErrorStatusCode')) {
    function getAuthErrorStatusCode(string $code): int
    {
        $statusCodes = [
            'invalid_credentials' => 401,
            'user_not_found' => 401,
            'email_not_verified' => 403,
            'account_locked' => 403,
            'account_inactive' => 403,
            'invalid_email' => 400,
            'invalid_username' => 400,
            'password_mismatch' => 400,
            'weak_password' => 400,
            'email_exists' => 409,
            'username_exists' => 409,
            'invalid_token' => 401,
            'firebase_signin_failed' => 401,
            'account_exists_with_different_credential' => 409,
            'missing_idtoken' => 400,
            'token_verification_failed' => 401,
            'oauth_provider_disabled' => 403,
            'rate_limit_exceeded' => 429,
            'csrf_token_invalid' => 403,
            'server_error' => 500,
        ];

        return $statusCodes[$code] ?? 400;
    }
}

// Provide a backwards-compatible stub so static analysis (Intelephense)
// does not flag `ensureUploadDirectories()` as undefined when analyzing bootstrap.
if (!function_exists('ensureUploadDirectories')) {
    function ensureUploadDirectories(): void
    {
        if (function_exists('initializeUploadDirectories')) {
            initializeUploadDirectories();
        }
    }
}




if (!function_exists('LoggedIn')) {
    /**
     * DEPRECATED: Use isUserAuthenticated() instead
     * Legacy function kept for backward compatibility
     * Check if user is logged in
     * 
     * @return bool True if user is authenticated
     */
    function LoggedIn()
    {
        // Fixed: Check if user_id is a valid integer, not === true
        return AuthManager::isUserAuthenticated();
    }
}



if (!function_exists('showMessage')) {
    /**
     * Display a flash message to the user
     * CONSOLIDATED: Now delegates to SessionManager for single source of truth
     * 
     * @param string $message The message to display
     * @param string $status Bootstrap status class (success, danger, warning, info)
     * @param bool $flash If true, store in session for display after redirect. If false, output directly.
     * @return void
     */
    function showMessage(string $message, string $status = 'info', bool $flash = true): void
    {

        // Normalize error to danger for Bootstrap
        if ($status === 'error') {

            $status = 'danger'; // Bootstrap convention

        }



        if ($flash) {

            // Delegate to SessionManager for consistent flash message handling
            try {
                $sessionMgr = SessionManager::getInstance();
                $sessionMgr->setFlash($message, $status);
            } catch (Throwable $e) {
                error_log("showMessage error: " . $e->getMessage());
                // Fallback: Direct session management
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['flash_message'] = [
                    'message' => $message,
                    'text' => $message, // Legacy key
                    'type' => $status,
                    'status' => $status, // Legacy key
                    'timestamp' => time(),
                ];
            }
        } else {

            echo "<div class='alert alert-{$status}' role='alert'>"

                . htmlspecialchars($message) .

                "</div>";
        }
    }
}



if (!function_exists('getFlash')) {

    /**
     * Get flash message from array (for testing/internal use)
     * CONSOLIDATED: Extracted flash from passed array
     * 
     * @param array $session Reference to session array
     * @return array|null Flash message or null
     */
    function getFlash(array &$session = []): ?array
    {

        if (!empty($session['flash_message'])) {

            $msg = $session['flash_message'];

            unset($session['flash_message']);

            return $msg;
        }

        return null;
    }
}



if (!function_exists('getFlashMessage')) {

    /**
     * Get and clear flash message from session
     * CONSOLIDATED: Now delegates to SessionManager
     * 
     * @return array|null Flash message with message and type keys, or null
     */
    function getFlashMessage(): ?array
    {

        try {
            $sessionMgr = SessionManager::getInstance();
            $flash = $sessionMgr->getFlash();

            // Convert to legacy format for backward compatibility
            if ($flash && isset($flash['message']) && isset($flash['type'])) {
                return [
                    'text' => $flash['message'],     // Legacy key
                    'status' => $flash['type'],      // Legacy key
                    'message' => $flash['message'],
                    'type' => $flash['type']
                ];
            }
            return $flash;
        } catch (Throwable $e) {
            error_log("getFlashMessage error: " . $e->getMessage());
            // Fallback: Direct session management
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!empty($_SESSION['flash_message'])) {
                $msg = $_SESSION['flash_message'];
                unset($_SESSION['flash_message']);
                return $msg;
            }
            return null;
        }
    }
}







/**

 * Get application setting value globally

 */

/**
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getSetting(string $key, $default = null)
{

    global $appSettings;



    if (isset($appSettings) && $appSettings instanceof AppSettings) {

        return $appSettings->get($key, $default);
    }



    return $default;
}



/**

 * Update application setting globally

 */

/**
 * @param string $key
 * @param mixed $value
 * @return bool
 */
function updateSetting(string $key, $value): bool
{

    global $appSettings;



    if (isset($appSettings) && $appSettings instanceof AppSettings) {

        return $appSettings->set($key, $value);
    }



    return false;
}



/**

 * Check if feature is enabled in settings

 */

function isFeatureEnabled(string $featureName): bool
{

    global $appSettings;



    if (isset($appSettings) && $appSettings instanceof AppSettings) {

        return (bool)$appSettings->get('enable_' . $featureName, false);
    }



    return false;
}









function redirect(string $url, int $statusCode = 302): void
{

    header("Location: {$url}", true, $statusCode);

    exit;
}













if (!function_exists('logActivity')) {

    /**

     * Log user activity to activity_logs table with IP address and browser information

     * @param string $action - Description of action (required)

     * @param string|null $resource_type - Type of resource (user, content, etc.)

     * @param int|null $resource_id - ID of affected resource

     * @param array|null $details - Additional context data

     * @param string $status - Status (success, failure, etc.)

     * @return int|false - Activity log ID or false on failure

     */

    function logActivity(

        string $action,

        ?string $resource_type = null,

        ?int $resource_id = null,

        ?array $details = null,

        string $status = 'success'

    ) {

        global $mysqli, $activityModel;



        // If activity model not available globally, initialize it

        if (!isset($activityModel) || !($activityModel instanceof ActivityModel)) {

            if (!isset($mysqli)) return false;

            $activityModel = new ActivityModel($mysqli);
        }



        // Get current user info from session

        $user_id = $_SESSION['user_id'] ?? 0;

        $role = $_SESSION['user_role'] ?? 'guest';

        // Limit role length to prevent database truncation (max 50 chars)

        $role = mb_substr($role, 0, 50);



        // Get client IP address

        $ip_address = getClientIpAddress();



        // Get browser/user agent information

        $browser_info = getBrowserInfo();



        // Add IP and browser info to details if not already present

        if (!is_array($details)) {

            $details = [];
        }
        if (empty($details['_ip_address'])) {

            $details['_ip_address'] = $ip_address;
        }
        if (empty($details['_browser'])) {

            $details['_browser'] = $browser_info;
        }



        try {

            return $activityModel->log(

                $action,

                $resource_type,

                $resource_id,

                $details,

                $status,

                (int) $user_id,

                (string) $role

            );
        } catch (Exception $e) {

            error_log('logActivity error: ' . $e->getMessage());

            return false;
        }
    }
}



if (!function_exists('getClientIpAddress')) {

    /**

     * Get the client's IP address

     * @return string - Client IP address

     */

    function getClientIpAddress(): string
    {
        $helper = new ClientInfoHelper();
        return $helper->getIp();
    }
}



if (!function_exists('getBrowserInfo')) {

    /**

     * Get browser and OS information from user agent

     * @return string - Formatted browser info string

     */

    function getBrowserInfo(): string
    {
        $helper = new ClientInfoHelper();
        return $helper->getBrowser();
    }
}



if (!function_exists('getAppUrl')) {

    function getAppUrl()
    {
        $helper = new UrlHelper();
        return $helper->getBase();
    }
}



if (!function_exists('getBreadcrumbsForType')) {
    function getBreadcrumbsForType(string $type, array $params = []): array
    {
        $generator = new BreadcrumbGenerator();
        return $generator->generate($type, $params);
    }
}

if (!function_exists('getBreadcrumbsForPost')) {
    function getBreadcrumbsForPost($category = null, $postTitle = null)
    {
        return getBreadcrumbsForType('post', [
            'category' => $category,
            'item' => $postTitle,
        ]);
    }
}

if (!function_exists('getBreadcrumbsForPage')) {
    function getBreadcrumbsForPage($pageTitle = null)
    {
        return getBreadcrumbsForType('page', [
            'item' => $pageTitle,
        ]);
    }
}

if (!function_exists('getBreadcrumbsForMobile')) {
    function getBreadcrumbsForMobile($brand = null, $model = null)
    {
        return getBreadcrumbsForType('mobile', [
            'brand' => $brand,
            'item' => $model,
        ]);
    }
}

if (!function_exists('getBreadcrumbsForCategory')) {
    function getBreadcrumbsForCategory($categoryName = null)
    {
        return getBreadcrumbsForType('category', [
            'item' => $categoryName,
        ]);
    }
}

if (!function_exists('getBreadcrumbsForTag')) {
    function getBreadcrumbsForTag($tagName = null)
    {
        return getBreadcrumbsForType('tag', [
            'item' => $tagName,
        ]);
    }
}

if (!function_exists('getBreadcrumbsForSearch')) {
    function getBreadcrumbsForSearch($query = null)
    {
        return getBreadcrumbsForType('search', [
            'item' => $query,
        ]);
    }
}

if (!function_exists('getBreadcrumbsForContact')) {
    function getBreadcrumbsForContact()
    {
        return getBreadcrumbsForType('contact');
    }
}

if (!function_exists('getBreadcrumbsForAbout')) {
    function getBreadcrumbsForAbout()
    {
        return getBreadcrumbsForType('about');
    }
}

if (!function_exists('getAdminBreadcrumbs')) {
    function getAdminBreadcrumbs($page = null, $subpage = null, $item = null)
    {
        $generator = new BreadcrumbGenerator();
        return $generator->generateAdmin($page, $subpage, $item);
    }
}

if (!function_exists('autoAdminBreadcrumbs')) {
    function autoAdminBreadcrumbs(string $requestUri = null): array
    {
        $generator = new BreadcrumbGenerator();
        return $generator->generateAutoAdmin($requestUri);
    }
}

if (!function_exists('sanitizeBreadcrumbs')) {
    function sanitizeBreadcrumbs(array $breadcrumbs, string $baseUrl = null): array
    {
        $generator = new BreadcrumbGenerator();
        return $generator->sanitize($breadcrumbs, $baseUrl);
    }
}

if (!function_exists('sanitizeBreadcrumbsForTwig')) {
    function sanitizeBreadcrumbsForTwig(array $breadcrumbs): array
    {
        return sanitizeBreadcrumbs($breadcrumbs);
    }
}

if (!function_exists('json_response')) {

    /**

     * Send JSON response and exit

     * @param array $data Data to encode as JSON

     * @param int $statusCode HTTP status code

     */

    function json_response(array $data, int $statusCode = 200): void
    {

        http_response_code($statusCode);

        header('Content-Type: application/json');

        echo json_encode($data);

        exit;
    }
}

// =====================================================
// SESSION USER HELPER FUNCTIONS (Aliases to AuthManager)
// =====================================================

/**
 * Get current authenticated user ID from session
 * Alias to AuthManager::getCurrentUserId()
 * 
 * @return int The user ID (0 if not authenticated)
 */
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId(): int
    {
        return AuthManager::getCurrentUserId() ?? 0;
    }
}

/**
 * Check if user is authenticated
 * Alias to AuthManager::isUserAuthenticated()
 * 
 * @return bool True if user is logged in, false otherwise
 */
if (!function_exists('isUserAuthenticated')) {
    function isUserAuthenticated(): bool
    {
        return AuthManager::isUserAuthenticated();
    }
}

/**
 * Require authentication or redirect to login
 * Returns user ID if authenticated, redirects and exits if not
 * 
 * @return int User ID
 */
if (!function_exists('requireAuth')) {
    function requireAuth(): int
    {
        if (!AuthManager::isUserAuthenticated()) {
            $currentPath = strtolower((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
            $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
            $isApiRequest = (strpos($currentPath, '/api/') === 0)
                || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
                || (strpos($accept, 'application/json') !== false);

            if ($isApiRequest) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }

            showMessage('Please log in.', 'danger');
            redirectToLoginWithReturn();
        }

        return AuthManager::getCurrentUserId() ?? 0;
    }
}

/**
 * Get current authenticated user ID (alias of getCurrentUserId)
 * For backward compatibility and alternate naming
 * Alias to AuthManager::getUserId()
 * 
 * @return int The user ID (0 if not authenticated)
 */
if (!function_exists('getUserId')) {
    function getUserId(): int
    {
        return AuthManager::getUserId();
    }
}

/**
 * Get session value with optional default
 * Alias to AuthManager::getSessionValue()
 * Safely retrieves session values with type casting
 * 
 * @param string $key Session key to retrieve
 * @param mixed $default Default value if key doesn't exist
 * @param string $type Optional type casting ('int', 'string', 'bool', 'array')
 * @return mixed The session value or default
 */
if (!function_exists('getSessionValue')) {
    /**
     * @param string $key
     * @param mixed $default
     * @param string $type
     * @return mixed
     */
    function getSessionValue(string $key, $default = null, string $type = 'string')
    {
        return AuthManager::getSessionValue($key, $default, $type);
    }
}

/**
 * Build current user array from session values
 * Alias to AuthManager::getCurrentUserArray()
 * Constructs a user array from individual session fields
 * Useful for Twig rendering and quick access
 * 
 * @return array Current user data
 */
if (!function_exists('getCurrentUserArray')) {
    function getCurrentUserArray(): array
    {
        return AuthManager::getCurrentUserArray();
    }
}

/**
 * Get SessionManager instance for advanced operations
 * Alias to AuthManager::getSessionManager()
 * 
 * @return SessionManager Session manager singleton
 */
if (!function_exists('getSessionManager')) {
    function getSessionManager(): SessionManager
    {
        return AuthManager::getSessionManager();
    }
}

if (!function_exists('bnToEnDigits')) {
    function bnToEnDigits(string $value): string
    {
        $bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($bn, $en, $value);
    }
}

if (!function_exists('enToBnDigits')) {
    function enToBnDigits(string $value): string
    {
        $en = ['0', '1', '2', '3', '৪', '৫', '৬', '৭', '৮', '৯'];
        $bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        return str_replace($en, $bn, $value);
    }
}

if (!function_exists('enToBnMonth')) {
    function enToBnMonth(string $month): string
    {
        $months = [
            'January' => 'জানুয়ারি',
            'February' => 'ফেব্রুয়ারি',
            'March' => 'মার্চ',
            'April' => 'এপ্রিল',
            'May' => 'মে',
            'June' => 'জুন',
            'July' => 'জুলাই',
            'August' => 'আগস্ট',
            'September' => 'সেপ্টেম্বর',
            'October' => 'অক্টোবর',
            'November' => 'নভেম্বর',
            'December' => 'ডিসেম্বর',
        ];
        return $months[$month] ?? $month;
    }
}

// Helper function to fetch user details (browser, IP, etc.)
if (!function_exists('getUserDetails')) {
    function getUserDetails()
    {
        $helper = new ClientInfoHelper();
        return $helper->getDetails();
    }
}


// OAuth audit logging helper
if (!function_exists('logOAuthAudit')) {
    /**
     * Log OAuth audit actions to auth_audit_log table (event_type='oauth')
     * @param string $provider
     * @param string|null $providerUserId
     * @param string|null $providerEmail
     * @param int|null $userId
     * @param string $status 'success'|'failure'
     * @param string|null $message
     * @return bool
     */
    function logOAuthAudit(string $provider, ?string $providerUserId = null, ?string $providerEmail = null, ?int $userId = null, string $status = 'success', ?string $message = null): bool
    {
        global $mysqli;
        try {
            $ip = getClientIpAddress();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $stmt = $mysqli->prepare("INSERT INTO auth_audit_log (user_id, event_type, action, provider, provider_user_id, provider_email, status, error_message, ip_address, user_agent, created_at) VALUES (?, 'oauth', 'login', ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('issssss', $userId, $provider, $providerUserId, $providerEmail, $ip, $ua, $status, $message);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Throwable $e) {
            error_log('logOAuthAudit error: ' . $e->getMessage());
            return false;
        }
    }
}

// Validate internal redirect path (basic check: allow only relative paths starting with '/')
if (!function_exists('isValidInternalPath')) {
    function isValidInternalPath(?string $path): bool
    {
        if (!$path) return false;
        // No scheme or host allowed
        $p = parse_url($path);
        if (isset($p['scheme']) || isset($p['host'])) return false;
        // Must start with a single '/'
        if (strpos($path, '/') !== 0) return false;
        // Prevent protocol-relative or double-slash
        if (strpos($path, '//') === 0) return false;
        // Basic allowed chars
        return (bool)preg_match('~^/[A-Za-z0-9_\-\/\.\?=&%#]*$~', $path);
    }
}



/**
 * Helper function to generate URL slug from title
 * 
 * @param string $title
 * @param int|null $id
 * @return string
 */
function urlSlug($title, $id = null)
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    if ($id) {
        $slug .= '-' . $id;
    }

    return $slug;
}
