<?php
/**
 * AuthManager.php
 * 
 * Complete authentication management including:
 * - Email/password login
 * - Firebase auth account sync
 * - Account binding
 * - Session management
 * - Remember Me
 * - 2FA
 * 
 * @package BroxBhai
 * @version 1.0.1
 */

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/UserModel.php';
require_once __DIR__ . '/SecurityManager.php';

class AuthManager {
    private $mysqli;
    private $userModel;
    private $securityManager;
    
    const COOKIE_NAME = 'broxbhai_remember';
    const COOKIE_PATH = '/';
    const COOKIE_SECURE = true; // Must be HTTPS in production
    const COOKIE_HTTPONLY = true;
    const COOKIE_SAMESITE = 'Lax';
    
    public function __construct(mysqli $mysqli) {
        $this->mysqli = $mysqli;
        $this->userModel = new UserModel($mysqli);
        $this->securityManager = new SecurityManager($mysqli);
    }
    
    /**
     * ========== EMAIL/PASSWORD LOGIN ==========
     */
    
    /**
     * Authenticate user with email/username and password
     */
    public function authenticateWithPassword(string $usernameOrEmail, string $password): array {
        // Input validation
        if (empty($usernameOrEmail) || empty($password)) {
            return ['success' => false, 'error' => 'Username/email and password are required'];
        }
        
        // Find user
        $user = $this->userModel->findByUsernameOrEmail($usernameOrEmail);
        
        if (!$user) {
            // Don't record failed login with invalid user_id - just return error
            // Sleep to prevent timing attacks
            usleep(rand(100000, 300000)); // 0.1-0.3 seconds
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        $userId = $user['id'];
        
        // Check if account is locked
        if ($this->securityManager->isAccountLocked($userId)) {
            $lockInfo = $this->securityManager->getAccountLockoutInfo($userId);
            $this->securityManager->recordFailedLogin($userId, null, null, 'account_locked');
            return [
                'success' => false,
                'error' => 'Account temporarily locked due to multiple failed login attempts',
                'locked_until' => $lockInfo['locked_until']
            ];
        }
        
        // Check account status
        if ($user['deleted_at']) {
            $this->securityManager->recordFailedLogin($userId, null, null, 'account_deleted');
            return ['success' => false, 'error' => 'Account has been deleted'];
        }
        
        if ($user['status'] !== 'active') {
            $this->securityManager->recordFailedLogin($userId, null, null, 'account_' . $user['status']);
            return ['success' => false, 'error' => "Account is {$user['status']}. Please contact support."];
        }
        
        // Check email verification (if required)
        if ($this->securityManager->isEmailVerificationRequired() && !$user['email_verified']) {
            return [
                'success' => false,
                'error' => 'Please verify your email before logging in',
                'require_email_verification' => true,
                'user_id' => $userId,
                'email' => $user['email']
            ];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            $this->securityManager->recordFailedLogin($userId, null, $user['username'], 'invalid_password');
            // Sleep to prevent timing attacks
            usleep(rand(100000, 300000));
            return ['success' => false, 'error' => 'Invalid credentials']; // Changed from 'Invalid password' to prevent user enumeration
        }
        
        // Check if password needs rehashing (if algorithm changed)
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->userModel->updateUser($userId, ['password' => $newHash]);
        }
        
        // Check if 2FA is required
        if ($this->securityManager->is2FAEnabled($userId) || $this->securityManager->is2FARequiredForAdmin($userId)) {
            // Generate 2FA challenge
            $challengeToken = SecurityManager::generateToken();
            $stmt = $this->mysqli->prepare("
                INSERT INTO password_resets (user_id, token, token_type, expires_at)
                VALUES (?, ?, 'twofa_challenge', DATE_ADD(NOW(), INTERVAL 15 MINUTE))
            ");
            $tokenHash = SecurityManager::hashToken($challengeToken);
            $stmt->bind_param('is', $userId, $tokenHash);
            $stmt->execute();
            $stmt->close();
            
            return [
                'success' => false,
                'require_2fa' => true,
                'user_id' => $userId,
                'challenge_token' => $challengeToken,
                'error' => '2FA required'
            ];
        }
        
        // Record successful login
        $this->securityManager->recordSuccessfulLogin($userId, 'email_password');
        
        return [
            'success' => true,
            'user' => $user,
            'user_id' => $userId
        ];
    }
    
    /**
     * Create or sync local user from Firebase auth data
     *
     * Logic:
     * 1. Try find by firebase_uid
     * 2. Try find by email and link firebase_uid
     * 3. Create new user if not found
     *
     * @param array $data ['uid','email','name','picture','provider']
     * @return int Local user ID
     * @throws Exception on failure
     */
    public function createOrSyncLocalUserFromFirebase(array $data): int {
        $uid = $data['uid'] ?? null;
        $email = $data['email'] ?? null;
        $provider = $data['provider'] ?? 'unknown';

        if (!$uid || !$email) {
            throw new Exception('Missing uid or email');
        }

        // 1. Find by firebase_uid
        $user = $this->userModel->findByFirebaseUid($uid);
        if ($user) {
            // Update all available fields when user already exists
            $updateData = [];
            
            // Always update email if changed
            if (!empty($email) && ($user['email'] !== $email)) {
                $updateData['email'] = $email;
            }
            
            // Update first name if provided and different
            if (!empty($data['first_name']) && ($user['first_name'] !== $data['first_name'])) {
                $updateData['first_name'] = $data['first_name'];
            }
            
            // Update last name if provided and different
            if (!empty($data['last_name']) && ($user['last_name'] !== $data['last_name'])) {
                $updateData['last_name'] = $data['last_name'];
            }
            
            // Update profile picture only - do NOT update provider-specific picture columns
            // Provider pictures should only be stored in user_linked_accounts table if needed
            if (!empty($data['photoURL'])) {
                if (empty($user['profile_pic']) || $user['profile_pic'] !== $data['photoURL']) {
                    $updateData['profile_pic'] = $data['photoURL'];
                }
            }
            
            // Apply updates if there's anything to update
            if (!empty($updateData)) {
                $this->userModel->updateUser((int)$user['id'], $updateData);
            }
            
            return (int)$user['id'];
        }

        // 2. Link by email
        $existing = $this->userModel->findByEmail($email);
        if ($existing) {
            $this->userModel->linkFirebaseUid((int)$existing['id'], $uid);
            
            // Update all available fields for linked user
            $updateData = [];
            
            if (!empty($data['first_name']) && ($existing['first_name'] !== $data['first_name'])) {
                $updateData['first_name'] = $data['first_name'];
            }
            
            if (!empty($data['last_name']) && ($existing['last_name'] !== $data['last_name'])) {
                $updateData['last_name'] = $data['last_name'];
            }
            
            if (!empty($data['photoURL'])) {
                if (empty($existing['profile_pic']) || $existing['profile_pic'] !== $data['photoURL']) {
                    $updateData['profile_pic'] = $data['photoURL'];
                }
            }
            
            if (!empty($updateData)) {
                $this->userModel->updateUser((int)$existing['id'], $updateData);
            }
            
            return (int)$existing['id'];
        }

        // 3. Create new user with all OAuth data
        $newId = $this->userModel->createFromFirebase([
            'uid' => $uid,
            'email' => $email,
            'displayName' => $data['displayName'] ?? null,
            'photoURL' => $data['photoURL'] ?? null,
            'username' => $data['username'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'provider' => $provider,
        ]);
        
        if (!$newId) {
            throw new Exception('Failed to create local user from Firebase data');
        }

        return (int)$newId;
    }

    /**
     * ========== ACCOUNT BINDING ==========
     */
    
    /**
     * Bind OAuth account to existing user
     */
    public function bindOAuthAccount(int $userId, string $provider, string $providerId, string $providerEmail, ?string $providerPicture = null, ?string $providerData = null): array {
        // Validate inputs
        if ($userId <= 0 || empty($provider) || empty($providerId)) {
            logOAuthAudit($provider, $providerId, $providerEmail, $userId, 'failure', 'invalid_parameters');
            return [
                'success' => false,
                'error' => 'Invalid parameters'
            ];
        }
        
        // Check if this provider account is already linked to another user
        $stmt = $this->mysqli->prepare("
            SELECT user_id FROM user_linked_accounts 
            WHERE provider = ? AND provider_user_id = ?
        ");
        $stmt->bind_param('ss', $provider, $providerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            
            // Check if it's already linked to the same user
            if ($row['user_id'] == $userId) {
                return [
                    'success' => false,
                    'error' => ucfirst($provider) . ' account is already linked to your account'
                ];
            }
            
            return [
                'success' => false,
                'error' => ucfirst($provider) . ' account is already linked to another user'
            ];
        }
        $stmt->close();
        
        // Check if same email
        $user = $this->userModel->findById($userId);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }
        
        if ($user['email'] !== $providerEmail) {
            return [
                'success' => false,
                'error' => 'Email mismatch. Please use the same email for binding.'
            ];
        }
        
        // Link the account with provider picture
        $result = $this->userModel->linkOAuthAccount($userId, $provider, $providerId, $providerEmail, $providerData ?? json_encode([]), $providerPicture);
        
        if ($result) {
            logActivity("OAuth Account Bound", "auth", $userId, [
                'provider' => $provider,
                'provider_id' => $providerId
            ], 'success');
            logOAuthAudit($provider, $providerId, $providerEmail, $userId, 'success', 'bound');
            
            return ['success' => true, 'message' => ucfirst($provider) . ' account successfully linked'];
        }
        
        logOAuthAudit($provider, $providerId, $providerEmail, $userId, 'failure', 'failed_bind');
        return ['success' => false, 'error' => 'Failed to bind account'];
    }
    
    /**
     * Get linked OAuth accounts for user
     */
    public function getLinkedAccounts(int $userId): array {
        if ($userId <= 0) {
            return [];
        }
        
        $stmt = $this->mysqli->prepare("
            SELECT provider, provider_email, is_primary, linked_at
            FROM user_linked_accounts
            WHERE user_id = ?
            ORDER BY is_primary DESC, linked_at DESC
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        $accounts = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        $stmt->close();
        
        return $accounts;
    }
    
    /**
     * Unbind OAuth account
     */
    public function unbindOAuthAccount(int $userId, string $provider): array {
        // Validate inputs
        if ($userId <= 0 || empty($provider)) {
            return [
                'success' => false,
                'error' => 'Invalid parameters'
            ];
        }
        
        // Check if user has other auth methods
        $user = $this->userModel->findById($userId);
        
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }
        
        // Prevent unlinking if user has no password and this is their only login method
        $hasPassword = !empty($user['password']) && strlen($user['password']) > 10;
        $linkedAccounts = $this->getLinkedAccounts($userId);
        $isLastAuthMethod = !$hasPassword && count($linkedAccounts) <= 1;

        if ($isLastAuthMethod) {
            return [
                'success' => false,
                'error' => 'Cannot unlink your only login method. Please set a password first.'
            ];
        }

        $stmt = $this->mysqli->prepare("
            DELETE FROM user_linked_accounts
            WHERE user_id = ? AND provider = ?
        ");
        $stmt->bind_param('is', $userId, $provider);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            logActivity("OAuth Account Unbound", "auth", $userId, ['provider' => $provider], 'success');
            return ['success' => true, 'message' => ucfirst($provider) . ' account unlinked'];
        }
        
        $stmt->close();
        return ['success' => false, 'error' => 'Account binding not found or already removed'];
    }
    
    /**
     * ========== REMEMBER ME ==========
     */
    
    /**
     * Create remember me cookie and database token
     */
    public function createRememberMeCookie(int $userId): bool {
        if ($userId <= 0) {
            return false;
        }
        
        $tokenData = $this->securityManager->generateRememberMeToken($userId);
        
        if (!$tokenData) {
            return false;
        }
        
        $token = $tokenData['token'];
        $expires = strtotime($tokenData['expires']);
        
        // Set secure HTTP-only cookie
        $cookieValue = json_encode([
            'token' => $token,
            'family' => $tokenData['family']
        ]);
        
        // Set SameSite attribute properly (PHP 7.3+)
        $cookieOptions = [
            'expires' => $expires,
            'path' => self::COOKIE_PATH,
            'domain' => '',
            'secure' => self::COOKIE_SECURE,
            'httponly' => self::COOKIE_HTTPONLY,
            'samesite' => self::COOKIE_SAMESITE
        ];
        
        return setcookie(self::COOKIE_NAME, $cookieValue, $cookieOptions);
    }
    
    /**
     * Verify remember me cookie and auto-login
     */
    public function autoLoginWithRememberCookie(): ?array {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        
        $cookieValue = $_COOKIE[self::COOKIE_NAME];
        $cookieData = json_decode($cookieValue, true);
        
        if (!$cookieData || !isset($cookieData['token'])) {
            $this->clearRememberMeCookie();
            return null;
        }
        
        $token = $cookieData['token'];
        $user = $this->securityManager->getUserByRememberToken($token);
        
        if (!$user) {
            $this->clearRememberMeCookie();
            return null;
        }
        
        // Check if user is still active
        if ($user['deleted_at'] || $user['status'] !== 'active') {
            $this->clearRememberMeCookie();
            return null;
        }
        
        // Rotate token if enabled
        if ($this->securityManager->getSetting('remember_me_rotation', true)) {
            $newTokenData = $this->securityManager->rotateRememberMeToken($token);
            
            if ($newTokenData) {
                $newCookieValue = json_encode([
                    'token' => $newTokenData['token'],
                    'family' => $newTokenData['family']
                ]);
                
                $cookieOptions = [
                    'expires' => strtotime($newTokenData['expires']),
                    'path' => self::COOKIE_PATH,
                    'domain' => '',
                    'secure' => self::COOKIE_SECURE,
                    'httponly' => self::COOKIE_HTTPONLY,
                    'samesite' => self::COOKIE_SAMESITE
                ];
                
                setcookie(self::COOKIE_NAME, $newCookieValue, $cookieOptions);
            }
        }
        
        logActivity("Auto-login via Remember Me", "auth", $user['id'], [], 'success');
        
        return $user;
    }
    
    /**
     * Clear remember me cookie
     */
    public function clearRememberMeCookie(int $userId = 0): void {
        // Delete cookie with proper options
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            $cookieOptions = [
                'expires' => time() - 3600,
                'path' => self::COOKIE_PATH,
                'domain' => '',
                'secure' => self::COOKIE_SECURE,
                'httponly' => self::COOKIE_HTTPONLY,
                'samesite' => self::COOKIE_SAMESITE
            ];
            
            setcookie(self::COOKIE_NAME, '', $cookieOptions);
            unset($_COOKIE[self::COOKIE_NAME]);
        }
        
        // Revoke tokens from database
        if ($userId > 0) {
            $this->securityManager->revokeRememberMeToken($userId);
        }
    }
    
    /**
     * ========== SESSION MANAGEMENT ==========
     */
    
    /**
     * Create user session
     */
    public function createSession(int $userId): string {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Invalid user ID');
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        $sessionId = session_id();
        
        $user = $this->userModel->findById($userId);
        
        if (!$user) {
            throw new RuntimeException('User not found');
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Store session in database
        $sessionTimeout = $this->securityManager->getSetting('session_timeout', 3600);
        $expiresAt = date('Y-m-d H:i:s', time() + $sessionTimeout);
        
        $stmt = $this->mysqli->prepare("
            INSERT INTO user_sessions 
            (user_id, session_id, ip_address, user_agent, last_activity, expires_at, is_active)
            VALUES (?, ?, ?, ?, NOW(), ?, 1)
        ");
        $stmt->bind_param('issss', $userId, $sessionId, $ip, $userAgent, $expiresAt);
        $stmt->execute();
        $stmt->close();
        
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['auth_provider'] = $user['auth_provider'] ?? 'email';
        $_SESSION['is_anonymous'] = (strtolower((string)($_SESSION['auth_provider'] ?? '')) === 'anonymous');
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $ip;
        $_SESSION['last_activity'] = time();
        $_SESSION['user_agent'] = $userAgent;
        $_SESSION['last_regen'] = time();
        
        logActivity("Session Created", "auth", $userId, ['session_id' => $sessionId], 'success');
        
        return $sessionId;
    }
    
    /**
     * Validate active session
     */
    public function validateSession(int $userId, string $sessionId): bool {
        if ($userId <= 0 || empty($sessionId)) {
            return false;
        }
        
        $stmt = $this->mysqli->prepare("
            SELECT id FROM user_sessions
            WHERE user_id = ? AND session_id = ? AND is_active = 1 AND expires_at > NOW()
        ");
        $stmt->bind_param('is', $userId, $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $isValid = $result->num_rows > 0;
        $stmt->close();
        
        // Update last activity if valid
        if ($isValid) {
            $updateStmt = $this->mysqli->prepare("
                UPDATE user_sessions 
                SET last_activity = NOW() 
                WHERE user_id = ? AND session_id = ?
            ");
            $updateStmt->bind_param('is', $userId, $sessionId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        return $isValid;
    }
    
    /**
     * Destroy session
     */
    public function destroySession(int $userId): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionId = session_id();
        
        if ($userId > 0) {
            // Mark session as inactive in database
            $stmt = $this->mysqli->prepare("
                UPDATE user_sessions SET is_active = 0
                WHERE user_id = ? AND session_id = ?
            ");
            $stmt->bind_param('is', $userId, $sessionId);
            $stmt->execute();
            $stmt->close();
            
            logActivity("Session Destroyed", "auth", $userId, ['session_id' => $sessionId], 'success');
        }
        
        // Clear session variables
        $_SESSION = [];
        session_unset();
        session_destroy();
        
        // Clear remember me cookie
        $this->clearRememberMeCookie($userId);
    }
    
    /**
     * ========== UTILITY FUNCTIONS ==========
     */
    
    /**
     * Sanitize username - remove special characters
     */
    private function sanitizeUsername(string $username): string {
        // Remove special characters, keep only alphanumeric, underscore, and dash
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
        
        // Ensure it's not empty after sanitization
        if (empty($sanitized)) {
            $sanitized = 'user';
        }
        
        // Limit length
        return substr($sanitized, 0, 30);
    }
    
    /**
     * Generate unique username from email
     */
    private function generateUniqueUsername(string $baseUsername): string {
        $username = $baseUsername;
        $counter = 1;
        
        while ($this->userModel->findByUsername($username)) {
            $username = $baseUsername . '_' . $counter;
            $counter++;
            
            // Prevent infinite loops
            if ($counter > 1000) {
                $username = $baseUsername . '_' . bin2hex(random_bytes(4));
                break;
            }
        }
        
        return $username;
    }
    
    /**
     * Get current authenticated user
     */
    public static function getCurrentUser(): ?array {
        if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'first_name' => $_SESSION['first_name'] ?? null,
            'last_name' => $_SESSION['last_name'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
    }
    
    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool {
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user ID
     */
    public static function getCurrentUserId(): ?int {
        try {
            $sessionMgr = SessionManager::getInstance();
            return $sessionMgr->getUserId();
        } catch (Throwable $e) {
            logError("AuthManager::getCurrentUserId error: " . $e->getMessage());
            return null; // Changed from 0 to null for consistency
        }
    }

    /**
     * Check if user is authenticated
     */
    public static function isUserAuthenticated(): bool {
        try {
            $sessionMgr = SessionManager::getInstance();
            return $sessionMgr->isAuthenticated();
        } catch (Throwable $e) {
            logError("AuthManager::isUserAuthenticated error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current authenticated user ID (alias of getCurrentUserId)
     * For backward compatibility and alternate naming
     */
    public static function getUserId(): int {
        return self::getCurrentUserId() ?? 0;
    }

    /**
     * Get session value with optional default
     * Safely retrieves session values with type casting
     */
    public static function getSessionValue(string $key, $default = null, string $type = 'string') {
        try {
            $sessionMgr = SessionManager::getInstance();
            return $sessionMgr->get($key, $default, $type);
        } catch (Throwable $e) {
            logError("AuthManager::getSessionValue error: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Build current user array from session values
     * Constructs a user array from individual session fields
     * Useful for Twig rendering and quick access
     */
    public static function getCurrentUserArray(): array {
        try {
            $sessionMgr = SessionManager::getInstance();
            
            if (!$sessionMgr->isAuthenticated()) {
                return [];
            }
            
            $userId = $sessionMgr->getUserId();
            
            // Get base session user data
            $userData = $sessionMgr->getUserData();
            
            // Get additional user details from UserModel if needed
            if (!empty($GLOBALS['mysqli']) && $userId) {
                $userModel = new UserModel($GLOBALS['mysqli']);
                $profile = $userModel->loadUserById($userId);
                
                if ($profile) {
                    // Merge profile data with session data (session data takes precedence)
                    $userData = array_merge($profile, $userData);
                }
            }
            
            return !empty($userData) ? $userData : [];
        } catch (Throwable $e) {
            logError("AuthManager::getCurrentUserArray error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get SessionManager instance for advanced operations
     */
    public static function getSessionManager(): SessionManager {
        return SessionManager::getInstance();
    }
}
