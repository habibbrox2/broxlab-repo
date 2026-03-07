<?php

/**
 * controllers/AuthController.php 
 * 
 * Enterprise-grade authentication controller with:
 * - Email/password login with security
 * - Firebase OAuth providers
 * - Remember Me
 * - Forgot Password
 * - Email Verification
 * - 2FA/TOTP Support
 * 
 * @package BroxBhai
 * @version 2.0.0
 */

require_once __DIR__ . '/../../Config/Functions.php';
require_once __DIR__ . '/../Helpers/FirebaseHelper.php';
require_once __DIR__ . '/../Helpers/AuthAndSecurityHelper.php';

$userModel = new UserModel($mysqli);
$authManager = new AuthManager($mysqli);
$securityManager = new SecurityManager($mysqli);

/**
 * Display login form
 */
$router->get('/login', ['middleware' => ['guest_only']], function () use ($twig, $authManager, $securityManager, $userModel) {
    $oauthRedirect = resolveAuthRedirectPath([], true);

    // Check for auto-login via remember me cookie
    $rememberUser = $authManager->autoLoginWithRememberCookie();
    if ($rememberUser) {
        // Create session and redirect
        $authManager->createSession($rememberUser['id']);
        if ($oauthRedirect !== '') {
            $redirectUrl = $oauthRedirect;
        } elseif ($userModel->isSuperAdmin($rememberUser['id']) || $userModel->hasRole($rememberUser['id'], 'admin')) {
            $redirectUrl = '/admin/dashboard';
        } else {
            $redirectUrl = '/user/dashboard';
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['post_login_redirect']);
        }

        header("Location: $redirectUrl");
        exit;
    }

    $authError = sanitize_input($_GET['error'] ?? '');

    if (session_status() === PHP_SESSION_ACTIVE && empty($authError) && !empty($_SESSION['auth_error'])) {
        $authError = sanitize_input($_SESSION['auth_error']);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['auth_error'], $_SESSION['auth_error_code']);
    }

    echo $twig->render('auth/login.twig', [
        'title' => 'Login',
        'enable_remember_me' => $securityManager->getSetting('enable_remember_me', true),
        'enable_firebase_oauth' => $securityManager->isFirebaseOAuthEnabled(),
        'oauth_redirect' => $oauthRedirect,
        'error' => $authError,
    ]);
});

/**
 * Display registration form
 */
$router->get('/register', ['middleware' => ['guest_only']], function () use ($twig, $securityManager) {
    $oauthRedirect = resolveAuthRedirectPath([], true);

    echo $twig->render('auth/register.twig', [
        'title' => 'Register',
        'min_password_length' => $securityManager->getSetting('min_password_length', 8),
        'enable_firebase_oauth' => $securityManager->isFirebaseOAuthEnabled(),
        'oauth_redirect' => $oauthRedirect,
    ]);
});




$router->get('/forgot-password', ['middleware' => ['guest_only']], function () use ($twig) {
    echo $twig->render('auth/forgot-password.twig', [
        'title' => 'Forgot Password',
    ]);
});

/**
 * Reset password form (with token validation)
 */
$router->get('/reset-password', ['middleware' => ['guest_only']], function () use ($twig, $securityManager) {
    $token = sanitize_input($_GET['token'] ?? '');

    if (!$token) {
        authErrorResponse('invalid_token', 'Invalid reset link', 'redirect', 401, '/forgot-password');
    }

    // Verify token exists and is not expired
    $tokenData = $securityManager->verifyPasswordResetToken($token);

    if (!$tokenData) {
        authErrorResponse('invalid_token', 'Invalid or expired reset link', 'redirect', 401, '/forgot-password');
    }

    echo $twig->render('auth/reset-password.twig', [
        'title' => 'Reset Password',
        'reset_token' => $token,
        'min_password_length' => $securityManager->getSetting('min_password_length', 8),
    ]);
});

/**
 * Verify email form
 */
$router->get('/verify-email', function () use ($twig, $securityManager) {
    $token = sanitize_input($_GET['token'] ?? '');
    $email = sanitize_input($_GET['email'] ?? '');

    // If token provided, try to auto-verify
    if ($token && !$email) {
        // Try to verify with token
        if ($securityManager->verifyEmailWithToken($token)) {
            authSuccessResponse(
                [],
                'Email verified successfully!',
                'redirect',
                200,
                '/login'
            );
        }
    }

    echo $twig->render('auth/verify-email.twig', [
        'title' => 'Verify Email',
        'token' => $token,
        'email' => $email ?: $_SESSION['pending_verification']['email'] ?? '',
    ]);
});



/**
 * Logout
 */
$router->get('/logout', ['middleware' => ['auth']], function () use ($authManager, $mysqli) {
    $userId = AuthManager::getCurrentUserId();

    if ($userId) {
        // Migrate FCM tokens back to guest before destroying session
        $notificationModel = new NotificationModel($mysqli);
        $guestDeviceId = sanitize_input($_COOKIE['guest_device_id'] ?? '');

        if (!empty($guestDeviceId)) {
            $notificationModel->migrateUserTokensToGuest($userId, $guestDeviceId);
        }

        $authManager->destroySession($userId);
    }

    authSuccessResponse(
        [],
        'You have been logged out',
        'redirect',
        200,
        '/login'
    );
});

// ============================================================
// POST ROUTES
// ============================================================

/**
 * Login POST
 */
$router->post('/login', ['middleware' => ['guest_only']], function () use ($authManager, $securityManager, $twig, $mysqli, $userModel) {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Get request data (handles both JSON AJAX and form submissions)
    $data = getRequestData();
    $requestedRedirect = resolveAuthRedirectPath($data);

    // Determine response type (AJAX or regular form submission)
    $type = isAjaxRequest() ? 'json' : 'redirect';

    // CSRF validation
    $csrfToken = $data['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        authErrorResponse('csrf_token_invalid', '', $type, 403, '/login');
    }

    // Rate limiting
    run_middleware('rate_limit', [
        'scope' => 'login',
        'limit' => $securityManager->getSetting('rate_limit_attempts', 10),
        'window' => $securityManager->getSetting('rate_limit_window', 60),
        'is_api' => false
    ]);

    // Get credentials
    $usernameOrEmail = sanitize_input($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $rememberMe = isset($data['remember_me']) && $data['remember_me'];

    if (!$usernameOrEmail || !$password) {
        authErrorResponse('invalid_credentials', 'Username/Email and Password are required', $type, 400, '/login');
    }

    // Authenticate
    $result = $authManager->authenticateWithPassword($usernameOrEmail, $password);

    // Track login attempt in analytics
    try {
        $securityManager = new SecurityManager($mysqli);
        $securityManager->logLoginAttempt(
            $result['user_id'] ?? null,
            $result['success'],
            $result['success'] ? null : ($result['error'] ?? 'Unknown error')
        );
    } catch (Throwable $e) {
        logError('Login Audit Tracking Error: ' . $e->getMessage());
    }

    if (!$result['success']) {
        // Handle email verification requirement
        if ($result['require_email_verification'] ?? false) {
            $_SESSION['pending_verification'] = [
                'user_id' => $result['user_id'],
                'email' => $result['email']
            ];

            authErrorResponse('email_not_verified', $result['error'], $type, 403, '/send-verification-email');
        }

        // Handle 2FA requirement
        if ($result['require_2fa'] ?? false) {
            $_SESSION['pending_2fa'] = [
                'user_id' => $result['user_id'],
                'challenge_token' => $result['challenge_token']
            ];

            if ($type === 'json') {
                // For AJAX requests, return JSON with next step
                authResponse([
                    'require_2fa' => true,
                    'user_id' => $result['user_id'],
                    'message' => 'Please verify your 2FA code'
                ], 'json', 403, '/verify-2fa');
            } else {
                // For regular form submissions, set flash message and redirect
                $_SESSION['flash_message'] = [
                    'message' => 'Please verify your 2FA code',
                    'type' => 'info',
                    'status' => 'info'
                ];

                header("Location: /verify-2fa");
                exit;
            }
        }

        authErrorResponse('invalid_credentials', $result['error'], $type, 401, '/login');
    }

    // Create session
    $authManager->createSession($result['user_id']);

    // Migrate guest FCM tokens to logged-in user
    $notificationModel = new NotificationModel($mysqli);
    $guestDeviceId = sanitize_input($_COOKIE['guest_device_id'] ?? '');
    $notificationModel->migrateGuestTokensToUser($result['user_id'], $guestDeviceId);

    // Send login notification
    $notifId = $notificationModel->create(
        (int)$result['user_id'],
        'নতুন লগইন',
        'আপনার অ্যাকাউন্টে সফলভাবে লগইন হয়েছে।',
        'update',
        [
            'user_id' => (int)$result['user_id'],
            'channels' => ['push', 'in_app', 'email']
        ]
    );
    if ($notifId) {
        $notificationModel->logDelivery($notifId, (int)$result['user_id'], 'sent', null, $_SERVER['REMOTE_ADDR'] ?? 'unknown', 'system', 'login');
    }

    // Handle remember me
    if ($rememberMe && $securityManager->getSetting('enable_remember_me', true)) {
        $authManager->createRememberMeCookie($result['user_id']);
    }

    // Redirect/Response
    if ($userModel->isSuperAdmin($result['user_id']) || $userModel->hasRole($result['user_id'], 'admin')) {
        $redirectUrl = '/admin/dashboard';
    } else {
        $redirectUrl = '/user/dashboard';
    }

    if ($requestedRedirect !== '') {
        $redirectUrl = $requestedRedirect;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['post_login_redirect']);
    }

    authSuccessResponse(['user_id' => $result['user_id']], 'Login successful', $type, 200, $redirectUrl);
});

/**
 * Register POST
 */
$router->post('/register', ['middleware' => ['guest_only']], function () use ($userModel, $securityManager, $authManager, $mysqli) {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Get request data (handles both JSON AJAX and form submissions)
    $data = getRequestData();
    $requestedRedirect = resolveAuthRedirectPath($data);

    // Determine response type (AJAX or regular form submission)
    $type = isAjaxRequest() ? 'json' : 'redirect';

    // CSRF validation
    $csrfToken = $data['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        authErrorResponse('csrf_token_invalid', '', $type, 403, '/register');
    }

    // Get input
    $username = sanitize_input($data['username'] ?? '');
    $email = sanitize_input($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';
    $first_name = sanitize_input($data['first_name'] ?? '');
    $last_name = sanitize_input($data['last_name'] ?? '');

    // Validate required fields
    if (!$username || !$email || !$password || !$confirmPassword) {
        authErrorResponse('invalid_credentials', 'Username, Email, and Password are required', $type, 400, '/register');
    }

    // Validate username format
    if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username)) {
        authErrorResponse('invalid_username', 'Username must be 3-30 characters and contain only letters, numbers, dots, hyphens, and underscores', $type, 400, '/register');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        authErrorResponse('invalid_email', '', $type, 400, '/register');
    }

    // Validate password match
    if ($password !== $confirmPassword) {
        authErrorResponse('password_mismatch', '', $type, 400, '/register');
    }

    // Validate password strength
    $passwordError = $securityManager->getPasswordValidationError($password);
    if ($passwordError) {
        authErrorResponse('weak_password', $passwordError, $type, 400, '/register');
    }

    // Check for reserved usernames
    $reservedUsernames = [
        'admin',
        'administrator',
        'root',
        'superadmin',
        'sysadmin',
        'system',
        'operator',
        'support',
        'owner',
        'master',
        'api',
        'oauth',
        'auth',
        'account',
        'profile'
    ];

    if (in_array(strtolower($username), $reservedUsernames)) {
        authErrorResponse('username_exists', 'This username is not available', $type, 409, '/register');
    }

    // Check for duplicates
    if ($userModel->findByUsername($username)) {
        authErrorResponse('username_exists', '', $type, 409, '/register');
    }

    if ($userModel->findByEmail($email)) {
        authErrorResponse('email_exists', '', $type, 409, '/register');
    }

    // Create user
    $userData = [
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'auth_provider' => 'email',
        'status' => 'active',
        'role' => 'user'
    ];

    if (!$userModel->create($userData)) {
        logActivity("User Registration Failed", "auth", null, ['email' => $email], 'failure');
        authErrorResponse('server_error', 'Registration failed. Please try again.', $type, 500, '/register');
    }

    // Get newly created user
    $newUser = $userModel->findByEmail($email);
    $userId = $newUser['id'];

    // Assign default user role
    $userModel->assignRole($userId, 4);

    // Check if email verification is required
    if ($securityManager->isEmailVerificationRequired()) {
        // Generate verification token
        $verificationToken = $securityManager->generateEmailVerificationToken($userId);

        // Send verification email
        $verificationLink = getAppUrl() . "/verify-email?token=" . $verificationToken;
        $siteName = getSetting('site_name', 'BroxBhai');

        // Send verification email using template
        sendEmailVerificationEmail($mysqli, $email, $first_name ?: $username, $verificationLink, 24 * 60);

        // Store pending verification in session
        $_SESSION['pending_verification'] = [
            'user_id' => $userId,
            'email' => $email
        ];

        logActivity("User Registered - Email Verification Pending", "auth", $userId, ['email' => $email], 'success');

        authSuccessResponse(
            ['user_id' => $userId],
            'Registration successful! Please check your email to verify your account.',
            $type,
            200,
            '/send-verification-email'
        );
    } else {
        // Create session and auto-login
        $authManager->createSession($userId);

        // Send welcome email using template
        sendWelcomeEmail($mysqli, $email, $first_name ?: $username, getAppUrl() . "/verify-email");

        // Send push & in-app notification for new user registration
        $notificationModel = new NotificationModel($mysqli);
        $notifId = $notificationModel->create(
            (int)$userId,
            'নতুন ব্যবহারকারী স্বাগত',
            'আপনার অ্যাকাউন্ট সফলভাবে তৈরি হয়েছে। আমাদের প্ল্যাটফর্মে স্বাগতম!',
            'announcement',
            [
                'user_id' => (int)$userId,
                'channels' => ['push', 'in_app', 'email']
            ]
        );

        // Log delivery for the new user
        if ($notifId) {
            $notificationModel->logDelivery($notifId, (int)$userId, 'sent', null, 'new_registration', 'system', 'account');
        }

        logActivity("User Registered", "auth", $userId, ['email' => $email], 'success');

        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['post_login_redirect']);
        }

        authSuccessResponse(
            ['user_id' => $userId],
            'Registration successful! Welcome to ' . getSetting('site_name', 'BroxBhai'),
            $type,
            200,
            $requestedRedirect !== '' ? $requestedRedirect : '/user/dashboard'
        );
    }

    exit;
});

/**
 * Forgot Password POST
 */
$router->post('/forgot-password', ['middleware' => ['guest_only', 'rate_limit']], function () use ($userModel, $securityManager, $mysqli) {
    // The 'rate_limit' middleware will use default settings.
    // To customize, you can pass options:
    // 'rate_limit' => ['scope' => 'forgot_password', 'limit' => 5, 'window' => 3600]

    // Get request data (handles both JSON AJAX and form submissions)
    $data = getRequestData();

    // Determine response type (AJAX or regular form submission)
    $type = isAjaxRequest() ? 'json' : 'redirect';

    // CSRF validation
    $csrfToken = $data['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        authErrorResponse('csrf_token_invalid', '', $type, 403, '/forgot-password');
    }

    $email = sanitize_input($data['email'] ?? '');

    if (!$email) {
        authErrorResponse('invalid_email', 'Email is required', $type, 400, '/forgot-password');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        authErrorResponse('invalid_email', '', $type, 400, '/forgot-password');
    }

    $user = $userModel->findByEmail($email);

    if ($user) {
        // Generate reset token
        $resetToken = $securityManager->generatePasswordResetToken($user['id']);

        if ($resetToken) {
            // Send reset email
            $resetLink = getAppUrl() . "/reset-password?token=" . $resetToken;
            $siteName = getSetting('site_name', 'BroxBhai');

            // Send password reset email using template
            sendPasswordResetEmail($mysqli, $email, $user['first_name'] ?: $user['username'], $resetLink, 60);
        }
    }

    // Always show success (for security, don't reveal if email exists)
    authSuccessResponse(
        [],
        'If an account with that email exists, a password reset link has been sent',
        $type,
        200,
        '/login'
    );
});

/**
 * Reset Password POST
 */
$router->post('/reset-password', ['middleware' => ['guest_only']], function () use ($securityManager) {
    // Get request data (handles both JSON AJAX and form submissions)
    $data = getRequestData();

    // Determine response type (AJAX or regular form submission)
    $type = isAjaxRequest() ? 'json' : 'redirect';

    // CSRF validation
    $csrfToken = $data['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        authErrorResponse('csrf_token_invalid', '', $type, 403, '/login');
    }

    $token = sanitize_input($data['reset_token'] ?? '');
    $password = $data['password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';

    if (!$token || !$password || !$confirmPassword) {
        authErrorResponse('invalid_token', 'All fields are required', $type, 400, '/forgot-password');
    }

    if ($password !== $confirmPassword) {
        authErrorResponse('password_mismatch', '', $type, 400, '/reset-password?token=' . urlencode($token));
    }

    // Validate password strength
    $passwordError = $securityManager->getPasswordValidationError($password);
    if ($passwordError) {
        authErrorResponse('weak_password', $passwordError, $type, 400, '/reset-password?token=' . urlencode($token));
    }

    // Reset password
    if ($securityManager->resetPasswordWithToken($token, $password)) {
        logActivity("Password Reset - Success", "auth", null, [], 'success');
        authSuccessResponse(
            [],
            'Your password has been reset successfully. Please log in with your new password.',
            $type,
            200,
            '/login'
        );
    } else {
        logActivity("Password Reset - Failed", "auth", null, [], 'failure');
        authErrorResponse('invalid_token', 'Failed to reset password. The link may have expired.', $type, 401, '/forgot-password');
    }
});

/**
 * Verify Email POST
 */
$router->post('/verify-email', function () use ($securityManager) {
    header('Content-Type: application/json');

    // Get request data (handles both JSON AJAX and form submissions)
    $data = getRequestData();

    // Accept both 'token' and 'verification_token' for flexibility
    $token = sanitize_input($data['verification_token'] ?? $data['token'] ?? '');

    if (!$token) {
        authErrorResponse('invalid_token', '', 'json', 400);
    }

    if ($securityManager->verifyEmailWithToken($token)) {
        authSuccessResponse([], 'Email verified successfully', 'json', 200);
    } else {
        authErrorResponse('invalid_token', 'Invalid or expired token', 'json', 400);
    }
});

/**
 * Send Verification Email GET
 */
$router->get('/send-verification-email', function () use ($twig) {
    echo $twig->render('auth/send-verification-email.twig', [
        'title' => 'Verify Email',
    ]);
});

/**
 * Resend Verification Email POST
 */
$router->post('/resend-verification-email', function () use ($securityManager, $userModel, $mysqli) {
    // Get request data (handles both JSON AJAX and form submissions)
    $data = getRequestData();

    $email = sanitize_input($data['email'] ?? '');

    if (!$email) {
        authErrorResponse('invalid_email', 'Email is required', 'redirect', 400, '/send-verification-email');
    }

    $user = $userModel->findByEmail($email);

    if ($user && !$user['email_verified']) {
        $verificationToken = $securityManager->generateEmailVerificationToken($user['id']);

        if ($verificationToken) {
            $verificationLink = getAppUrl() . "/verify-email?token=" . $verificationToken;

            // Send verification email using template
            sendEmailVerificationEmail($mysqli, $email, $user['first_name'] ?: $user['username'], $verificationLink, 24 * 60);
        }
    }

    authSuccessResponse(
        [],
        'Verification email has been sent',
        'redirect',
        200,
        '/login'
    );
});
/**
 * Verify 2FA GET
 */
$router->get('/verify-2fa', function () use ($twig) {
    // Check if user has pending 2FA
    if (!isset($_SESSION['pending_2fa'])) {
        authErrorResponse('invalid_token', 'No 2FA verification in progress', 'redirect', 400, '/login');
    }

    echo $twig->render('auth/verify-2fa.twig', [
        'title' => 'Two-Factor Authentication',
    ]);
});

/**
 * Verify 2FA POST
 */
$router->post('/verify-2fa', function () use ($securityManager, $authManager) {
    header('Content-Type: application/json');

    // Get request data (handles both JSON AJAX and form submissions)
    $data = getRequestData();

    // Check if user has pending 2FA
    if (!isset($_SESSION['pending_2fa'])) {
        authErrorResponse('invalid_token', 'No 2FA verification in progress', 'json', 400);
    }

    $code = sanitize_input($data['code'] ?? '');
    $userId = $_SESSION['pending_2fa']['user_id'] ?? null;

    if (!$code || !$userId) {
        authErrorResponse('invalid_credentials', 'Invalid 2FA code', 'json', 400);
    }

    // Verify 2FA code
    if ($securityManager->verify2FACode($userId, $code)) {
        // Clear pending 2FA
        unset($_SESSION['pending_2fa']);

        // Create session
        $authManager->createSession($userId);

        authSuccessResponse(['redirect' => '/dashboard'], '2FA verified successfully', 'json', 200);
    } else {
        authErrorResponse('invalid_token', 'Invalid or expired 2FA code', 'json', 400);
    }
});

// =====================================================
// GET /api/user/linked-accounts (or /api/oauth/linked-accounts)
// Fetch all linked OAuth accounts for current user
// =====================================================
$router->get('/api/user/linked-accounts', ['middleware' => ['auth'], 'response' => 'json'], function () use ($userModel) {
    try {
        $userId = AuthManager::getCurrentUserId();

        if (!$userId) {
            return json_response(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $accounts = $userModel->getLinkedOAuthAccounts($userId);
        $hasPassword = $userModel->userHasPassword((int)$userId);

        return json_response([
            'success' => true,
            'linked_accounts' => $accounts,
            'accounts' => $accounts,  // Support both response formats
            'count' => count($accounts),
            'has_password' => $hasPassword
        ], 200);
    } catch (Throwable $e) {
        logError('Get linked accounts error: ' . $e->getMessage());
        return json_response(['success' => false, 'error' => 'Server error', 'error_code' => 'server_error'], 500);
    }
});

// Alias route
$router->get('/api/oauth/linked-accounts', ['middleware' => ['auth'], 'response' => 'json'], function () use ($userModel) {
    try {
        $userId = AuthManager::getCurrentUserId();

        if (!$userId) {
            return json_response(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $accounts = $userModel->getLinkedOAuthAccounts($userId);
        $hasPassword = $userModel->userHasPassword((int)$userId);

        return json_response([
            'success' => true,
            'linked_accounts' => $accounts,
            'accounts' => $accounts,  // Support both response formats
            'count' => count($accounts),
            'has_password' => $hasPassword
        ], 200);
    } catch (Throwable $e) {
        logError('Get linked accounts error: ' . $e->getMessage());
        return json_response(['success' => false, 'error' => 'Server error', 'error_code' => 'server_error'], 500);
    }
});

// =====================================================
// POST /api/oauth/reauth
// Re-authenticate user before sensitive OAuth actions
// =====================================================
$router->post('/api/oauth/reauth', ['middleware' => ['auth'], 'response' => 'json'], function () use ($userModel) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = AuthManager::getCurrentUserId();
        if (!$userId) {
            return json_response(['success' => false, 'error' => 'Unauthorized', 'error_code' => 'unauthorized'], 401);
        }

        $data = getRequestData();
        $csrfToken = (string)($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!validateCsrfToken($csrfToken)) {
            return json_response([
                'success' => false,
                'error' => 'Invalid CSRF token',
                'error_code' => 'csrf_token_invalid'
            ], 403);
        }

        $ttlSeconds = 300;
        $currentPassword = (string)($data['current_password'] ?? '');
        $provider = strtolower(trim((string)($data['provider'] ?? '')));
        $idToken = (string)($data['idToken'] ?? ($data['id_token'] ?? ''));

        // Password-first mode
        if ($currentPassword !== '') {
            $user = $userModel->getUserById((int)$userId);
            if (!$user || empty($user['password']) || !password_verify($currentPassword, (string)$user['password'])) {
                return json_response([
                    'success' => false,
                    'error' => 'Current password is incorrect',
                    'error_code' => 'invalid_credentials'
                ], 401);
            }

            $_SESSION['oauth_reauth_verified_at'] = time();
            $_SESSION['oauth_reauth_method'] = 'password';
            $_SESSION['oauth_reauth_expires_at'] = time() + $ttlSeconds;

            return json_response([
                'success' => true,
                'message' => 'Re-authentication successful',
                'reauth_expires_at' => $_SESSION['oauth_reauth_expires_at'],
                'method' => 'password'
            ], 200);
        }

        // OAuth fallback mode
        $validProviders = ['google', 'facebook', 'github'];
        if ($provider === '' || !in_array($provider, $validProviders, true)) {
            return json_response([
                'success' => false,
                'error' => 'Invalid OAuth provider',
                'error_code' => 'invalid_provider'
            ], 400);
        }

        if ($idToken === '') {
            return json_response([
                'success' => false,
                'error' => 'Missing Firebase ID token',
                'error_code' => 'missing_idtoken'
            ], 400);
        }

        try {
            $firebaseModel = new \Firebase\FirebaseModel(require __DIR__ . '/../../Config/Firebase.php');
        } catch (Throwable $e) {
            logError('OAuth reauth FirebaseModel init failed: ' . $e->getMessage());
            return json_response([
                'success' => false,
                'error' => 'Re-authentication is currently unavailable',
                'error_code' => 'server_error'
            ], 500);
        }

        $tokenResult = $firebaseModel->verifyIdToken($idToken);
        if (empty($tokenResult['success']) || empty($tokenResult['uid'])) {
            return json_response([
                'success' => false,
                'error' => 'Invalid or expired authentication token',
                'error_code' => 'invalid_token'
            ], 401);
        }

        $uid = (string)$tokenResult['uid'];
        $linkedAccount = $userModel->getLinkedAccountByProvider($provider, $uid);
        if ($linkedAccount === null) {
            return json_response([
                'success' => false,
                'error' => ucfirst($provider) . ' is not linked to your account',
                'error_code' => 'provider_not_linked'
            ], 400);
        }

        $linkedUserId = (int)($linkedAccount['user_id'] ?? 0);
        if ($linkedUserId > 0 && $linkedUserId !== (int)$userId) {
            return json_response([
                'success' => false,
                'error' => 'This provider account is linked to another user',
                'error_code' => 'credential_already_in_use'
            ], 409);
        }

        $_SESSION['oauth_reauth_verified_at'] = time();
        $_SESSION['oauth_reauth_method'] = 'oauth:' . $provider;
        $_SESSION['oauth_reauth_expires_at'] = time() + $ttlSeconds;

        return json_response([
            'success' => true,
            'message' => 'Re-authentication successful',
            'reauth_expires_at' => $_SESSION['oauth_reauth_expires_at'],
            'method' => 'oauth:' . $provider
        ], 200);
    } catch (Throwable $e) {
        logError('OAuth reauth error: ' . $e->getMessage());
        return json_response(['success' => false, 'error' => 'Server error', 'error_code' => 'server_error'], 500);
    }
});

// =====================================================
// POST /api/oauth/unlink
// Unlink an OAuth provider from user account
// =====================================================
$router->post('/api/oauth/unlink', ['middleware' => ['auth'], 'response' => 'json'], function () use ($userModel) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = AuthManager::getCurrentUserId();

        if (!$userId) {
            return json_response(['success' => false, 'error' => 'Unauthorized', 'error_code' => 'unauthorized'], 401);
        }

        $csrfToken = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!validateCsrfToken($csrfToken)) {
            return json_response([
                'success' => false,
                'error' => 'Invalid CSRF token',
                'error_code' => 'csrf_token_invalid'
            ], 403);
        }

        $reauthAt = (int)($_SESSION['oauth_reauth_verified_at'] ?? 0);
        $reauthExpiresAt = (int)($_SESSION['oauth_reauth_expires_at'] ?? 0);
        $now = time();
        if ($reauthAt <= 0 || $reauthExpiresAt <= $now || ($now - $reauthAt) > 300) {
            unset($_SESSION['oauth_reauth_verified_at'], $_SESSION['oauth_reauth_method'], $_SESSION['oauth_reauth_expires_at']);
            return json_response([
                'success' => false,
                'error' => 'Please re-authenticate before unlinking.',
                'error_code' => 'reauth_required'
            ], 428);
        }

        $provider = sanitize_input($_POST['provider'] ?? $_GET['provider'] ?? '');
        $provider = strtolower(trim((string)$provider));

        if (empty($provider)) {
            return json_response(['success' => false, 'error' => 'Provider is required', 'error_code' => 'invalid_provider'], 400);
        }

        // Validate provider
        $validProviders = ['google', 'facebook', 'github'];
        if (!in_array($provider, $validProviders)) {
            return json_response(['success' => false, 'error' => 'Invalid provider', 'error_code' => 'invalid_provider'], 400);
        }

        $linkedAccounts = $userModel->getLinkedOAuthAccounts((int)$userId);
        $providerLinked = array_filter($linkedAccounts, fn($acc) => strtolower((string)($acc['provider'] ?? '')) === $provider);
        if (empty($providerLinked)) {
            return json_response([
                'success' => false,
                'error' => ucfirst($provider) . ' account is not linked to your account',
                'error_code' => 'no_such_provider'
            ], 400);
        }

        // Check if user has password (can't unlink all auth methods without password)
        $user = $userModel->getUserById($userId);
        if (empty($user['password'])) {
            // Check if this is the only login method
            if (count($linkedAccounts) <= 1) {
                return json_response([
                    'success' => false,
                    'error' => 'Cannot unlink your only login method. Please set a password first.',
                    'error_code' => 'cannot_unlink_last_method'
                ], 400);
            }
        }

        // Unlink the account
        $success = $userModel->unlinkOAuthAccount($userId, $provider);

        if ($success) {
            logActivity("OAuth Account Unlinked", "auth", $userId, ['provider' => $provider], 'success');
            unset($_SESSION['oauth_reauth_verified_at'], $_SESSION['oauth_reauth_method'], $_SESSION['oauth_reauth_expires_at']);
            return json_response([
                'success' => true,
                'message' => ucfirst($provider) . ' account has been unlinked successfully'
            ], 200);
        } else {
            return json_response([
                'success' => false,
                'error' => 'Account not linked or already removed',
                'error_code' => 'no_such_provider'
            ], 400);
        }
    } catch (Throwable $e) {
        logError('Unlink OAuth error: ' . $e->getMessage());
        return json_response(['success' => false, 'error' => 'Server error', 'error_code' => 'server_error'], 500);
    }
});

// =====================================================
// POST /api/oauth/set-primary
// Set which OAuth provider is the primary login method
// =====================================================
$router->post('/api/oauth/set-primary', ['middleware' => ['auth'], 'response' => 'json'], function () use ($userModel) {
    try {
        $userId = AuthManager::getCurrentUserId();

        if (!$userId) {
            return json_response(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $provider = sanitize_input($_POST['provider'] ?? $_GET['provider'] ?? '');

        if (empty($provider)) {
            return json_response(['success' => false, 'error' => 'Provider is required'], 400);
        }

        // Validate provider
        $validProviders = ['google', 'facebook', 'github', 'email'];
        if (!in_array($provider, $validProviders)) {
            return json_response(['success' => false, 'error' => 'Invalid provider'], 400);
        }

        // If setting email as primary, ensure password exists
        if ($provider === 'email') {
            $user = $userModel->getUserById($userId);
            if (empty($user['password'])) {
                return json_response([
                    'success' => false,
                    'error' => 'Please set a password before using email as primary login method'
                ], 400);
            }
        } else {
            // Verify the provider account is linked
            $linkedAccounts = $userModel->getLinkedOAuthAccounts($userId);
            $providerLinked = array_filter($linkedAccounts, fn($acc) => $acc['provider'] === $provider);

            if (empty($providerLinked)) {
                return json_response([
                    'success' => false,
                    'error' => ucfirst($provider) . ' account is not linked to your account'
                ], 400);
            }
        }

        // Set as primary
        $success = $userModel->setPrimaryOAuthProvider($userId, $provider);

        if ($success) {
            logActivity("Primary OAuth Provider Changed", "auth", $userId, ['provider' => $provider], 'success');
            return json_response([
                'success' => true,
                'message' => ucfirst($provider) . ' set as primary login method'
            ], 200);
        } else {
            return json_response([
                'success' => false,
                'error' => 'Failed to set primary provider'
            ], 500);
        }
    } catch (Throwable $e) {
        logError('Set primary provider error: ' . $e->getMessage());
        return json_response(['success' => false, 'error' => 'Server error'], 500);
    }
});

// =====================================================
// POST /api/oauth/set-password
// Set password for OAuth-only users (first-time setup)
// =====================================================
$setOAuthPasswordHandler = function () use ($userModel) {
    try {
        $userId = AuthManager::getCurrentUserId();

        if (!$userId) {
            return json_response(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirm'] ?? '';

        if (empty($password) || empty($confirmPassword)) {
            return json_response(['success' => false, 'error' => 'Password fields are required'], 400);
        }

        if ($password !== $confirmPassword) {
            return json_response(['success' => false, 'error' => 'Passwords do not match'], 400);
        }

        // Validate password strength (minimum 8 chars, mixed case, number, special)
        if (strlen($password) < 8) {
            return json_response(['success' => false, 'error' => 'Password must be at least 8 characters'], 400);
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return json_response(['success' => false, 'error' => 'Password must contain uppercase, lowercase, and numbers'], 400);
        }

        // Update password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $success = $userModel->updateUserPassword($userId, $hashedPassword);

        if ($success) {
            logActivity("Password Set for OAuth User", "auth", $userId, [], 'success');
            return json_response([
                'success' => true,
                'message' => 'Password has been set successfully'
            ], 200);
        } else {
            return json_response(['success' => false, 'error' => 'Failed to set password'], 500);
        }
    } catch (Throwable $e) {
        logError('Set password error: ' . $e->getMessage());
        return json_response(['success' => false, 'error' => 'Server error'], 500);
    }
};

$router->post('/api/oauth/set-password', ['middleware' => ['auth'], 'response' => 'json'], $setOAuthPasswordHandler);

// Backward-compatible alias for older admin/user settings scripts
$router->post('/user/set-password', ['middleware' => ['auth'], 'response' => 'json'], $setOAuthPasswordHandler);

// =====================================================
// GET /api/oauth/providers
// Get list of configured OAuth providers
// =====================================================
$router->get('/api/oauth/providers', ['response' => 'json'], function () use ($securityManager) {
    try {
        if (!$securityManager->isFirebaseOAuthEnabled()) {
            return json_response([
                'success' => true,
                'providers' => [],
                'count' => 0,
                'source' => 'firebase_live'
            ], 200);
        }

        $firebaseModel = new \Firebase\FirebaseModel(require __DIR__ . '/../../Config/Firebase.php');
        $liveResult = $firebaseModel->getEnabledOAuthProvidersLive();
        if (empty($liveResult['success'])) {
            $errorCode = (string)($liveResult['error_code'] ?? 'provider_status_fetch_failed');
            logError('Get OAuth providers live fetch failed: ' . ($liveResult['error'] ?? 'unknown'));
            return json_response([
                'success' => true,
                'providers' => [],
                'count' => 0,
                'source' => 'firebase_live',
                'error_code' => $errorCode
            ], 200);
        }

        return json_response([
            'success' => true,
            'providers' => $liveResult['providers'] ?? [],
            'count' => (int)($liveResult['count'] ?? 0),
            'source' => 'firebase_live'
        ], 200);
    } catch (Throwable $e) {
        logError('Get OAuth providers error: ' . $e->getMessage());
        return json_response([
            'success' => true,
            'providers' => [],
            'count' => 0,
            'source' => 'firebase_live',
            'error_code' => 'provider_status_fetch_failed'
        ], 200);
    }
});

// =====================================================
// POST /api/oauth/resolve-account-conflict
// Handle account-exists-with-different-credential resolution
// User chooses to either:
// 1. Link the new provider to existing account
// 2. Cancel and create new account
// =====================================================
$router->post('/api/oauth/resolve-account-conflict', ['response' => 'json'], function () use ($userModel) {
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $action = $input['action'] ?? null;  // 'link' or 'cancel'
        $existingUserId = (int)($input['user_id'] ?? 0);
        $provider = $input['provider'] ?? null;
        $providerUserId = $input['provider_user_id'] ?? null;
        $email = $input['email'] ?? null;
        $providerData = $input['provider_data'] ?? null;
        $providerPicture = $input['provider_picture'] ?? null;
        $temporaryToken = $input['temporary_token'] ?? null;

        // Validate required fields
        if (empty($action) || !in_array($action, ['link', 'cancel'], true)) {
            return json_response(['success' => false, 'error' => 'Invalid action'], 400);
        }

        if ($action === 'link') {
            // ========== LINK ACTION: Link new provider to existing account ==========

            if (empty($existingUserId) || empty($provider) || empty($providerUserId) || empty($email)) {
                return json_response(['success' => false, 'error' => 'Missing required fields for linking'], 400);
            }

            // Validate conflict resolution
            $validation = $userModel->validateConflictResolution($existingUserId, $provider, $email);

            if (!$validation['valid']) {
                logError("Conflict resolution validation failed: " . $validation['reason']);
                return json_response([
                    'success' => false,
                    'error' => $validation['reason']
                ], 400);
            }

            // Link the OAuth account using existing method
            $success = $userModel->linkOAuthAccount(
                $existingUserId,
                $provider,
                $providerUserId,
                $email,
                $providerData ? json_encode($providerData) : null,
                $providerPicture
            );

            if (!$success) {
                logError("Failed to link OAuth account during conflict resolution");
                return json_response([
                    'success' => false,
                    'error' => 'Failed to link account'
                ], 500);
            }

            // ========== AUDIT LOGGING ==========
            // 1. Record in auth_audit_log (event_type='oauth')
            if (isset($securityManager)) {
                $securityManager->recordOAuthAction(
                    $existingUserId,
                    'conflict_resolved_link',
                    $provider,
                    $providerUserId,
                    'success'
                );
                logError('OAuth audit log recorded: conflict_resolved_link for user_id=' . $existingUserId);
            }

            // 2. Log the action
            logActivity('Account conflict resolved - provider linked', 'auth', $existingUserId, [
                'provider' => $provider,
                'action' => 'link_to_existing',
                'provider_user_id' => $providerUserId,
                'email' => $email
            ], 'success');

            // Create session for the user
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (class_exists('AuthManager')) {
                $authManager = new AuthManager($GLOBALS['mysqli'] ?? null);
                if ($authManager) {
                    $authManager->createSession($existingUserId);
                }
            }

            return json_response([
                'success' => true,
                'message' => 'Account linked successfully',
                'action' => 'account_linked',
                'user_id' => $existingUserId
            ], 200);
        } else {
            // ========== CANCEL ACTION: User wants to create new account ==========

            logError("User chose to create new account instead of linking - Provider: {$provider}, Email: {$email}");

            // ========== AUDIT LOGGING ==========
            // 1. Record in auth_audit_log (event_type='oauth')
            if (isset($securityManager)) {
                $securityManager->recordOAuthAction(
                    $existingUserId,
                    'conflict_resolved_cancel',
                    $provider,
                    $providerUserId,
                    'user_choice'
                );
                logError('OAuth audit log recorded: conflict_resolved_cancel for user_id=' . $existingUserId);
            }

            // 2. Log the action
            logActivity('Account conflict detected - user chose to create new account', 'auth', null, [
                'provider' => $provider,
                'email' => $email,
                'action' => 'create_new_account'
            ], 'info');

            return json_response([
                'success' => true,
                'message' => 'Please create a new account with a different email address',
                'action' => 'show_new_account_form',
                'suggested_email_suffix' => '+' . uniqid(),
                'instructions' => 'Use a different email address to create a new account, or use your password to login to your existing account'
            ], 200);
        }
    } catch (Throwable $e) {
        logError('Resolve account conflict error: ' . $e->getMessage());
        return json_response(['success' => false, 'error' => 'Server error'], 500);
    }
});

// =====================================================
// POST /api/oauth/check-account-conflict
// Check if email has account conflict with different provider
// Called before showing conflict resolution UI
// =====================================================
$router->post('/api/oauth/check-account-conflict', ['response' => 'json'], function () use ($userModel) {
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $email = $input['email'] ?? null;
        $provider = $input['provider'] ?? null;
        $providerUserId = $input['provider_user_id'] ?? null;

        if (empty($email) || empty($provider) || empty($providerUserId)) {
            return json_response(['success' => false, 'error' => 'Missing email, provider, or provider_user_id'], 400);
        }

        // Check for conflict
        $conflict = $userModel->checkAccountConflict($email, $provider, $providerUserId);

        if ($conflict === null) {
            // No conflict - account is safe to use
            return json_response([
                'success' => true,
                'has_conflict' => false,
                'message' => 'No account conflict detected'
            ], 200);
        }

        // Conflict exists
        return json_response([
            'success' => true,
            'has_conflict' => true,
            'conflict' => [
                'email' => $conflict['email'],
                'existing_providers' => $conflict['existing_providers'],
                'has_password' => $conflict['has_password'],
                'user_id' => $conflict['user_id'],
                'account_age_days' => $conflict['account_age'],
                'suggestions' => [
                    'link' => 'Link this ' . ucfirst($provider) . ' account to your existing account',
                    'new' => 'Create a new account with a different email address',
                    'password' => $conflict['has_password'] ? 'Login with your password instead' : null
                ]
            ]
        ], 200);
    } catch (Throwable $e) {
        logError('Check account conflict error: ' . $e->getMessage());
        return json_response(['success' => false, 'error' => 'Server error'], 500);
    }
});
