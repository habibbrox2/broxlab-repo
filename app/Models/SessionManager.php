<?php
/**
 * SessionManager.php
 * 
 * Centralized Session Management System
 * Handles all session operations consistently across the application
 * 
 * Features:
 * - Session initialization with security
 * - User session creation and validation
 * - Secure session data access
 * - Session cleanup and destruction
 * - Cross-request session management
 * - CSRF token generation and validation
 * 
 * @package BroxBhai
 * @version 2.0.0
 */

class SessionManager {
    
    // ============================================================
    // CONSTANTS
    // ============================================================
    
    const SESSION_TIMEOUT = 3600;           // 1 hour
    const CSRF_TOKEN_LENGTH = 32;
    const SESSION_COOKIE_NAME = 'BROXBHAI_SESSION';
    const SESSION_SECURE = true;
    const SESSION_HTTP_ONLY = true;
    const SESSION_SAME_SITE = 'Lax';
    
    // Session data keys
    const KEY_USER_ID = 'user_id';
    const KEY_USERNAME = 'username';
    const KEY_EMAIL = 'email';
    const KEY_FIRST_NAME = 'first_name';
    const KEY_LAST_NAME = 'last_name';
    const KEY_FULL_NAME = 'full_name';
    const KEY_ROLE = 'role';
    const KEY_ROLES = 'roles';
    const KEY_PERMISSIONS = 'permissions';
    const KEY_LOGGED_IN = 'logged_in';
    const KEY_LOGIN_TIME = 'login_time';
    const KEY_IP_ADDRESS = 'ip_address';
    const KEY_USER_AGENT = 'user_agent';
    const KEY_CSRF_TOKEN = 'csrf_token';
    const KEY_LAST_ACTIVITY = 'last_activity';
    const KEY_FLASH_MESSAGE = 'flash_message';
    const KEY_LAST_REGEN = 'last_regen';
    
    // ============================================================
    // SINGLETON PATTERN
    // ============================================================
    
    private static ?SessionManager $instance = null;
    private bool $initialized = false;
    
    private function __construct() {
        // Private constructor for singleton
    }
    
    public static function getInstance(): SessionManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // ============================================================
    // SESSION INITIALIZATION & SECURITY
    // ============================================================
    
    /**
     * Initialize secure PHP session
     * Should be called early in application bootstrap
     * 
     * @return bool Success status
     */
    public function initialize(): bool {
        if ($this->initialized) {
            return true;
        }
        
        // Check if session already started
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->initialized = true;
            return true;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            try {
                if (PHP_SAPI === 'cli') {
                    $cliSessionDir = defined('TEMP_DIR')
                        ? rtrim((string)TEMP_DIR, '\\/') . DIRECTORY_SEPARATOR . 'sessions' . DIRECTORY_SEPARATOR . 'cli'
                        : rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'broxbhai-sessions';

                    if (!is_dir($cliSessionDir)) {
                        @mkdir($cliSessionDir, 0775, true);
                    }

                    if (is_dir($cliSessionDir) && is_writable($cliSessionDir)) {
                        @session_save_path($cliSessionDir);
                        @ini_set('session.save_path', $cliSessionDir);
                    }
                }

                if (headers_sent()) {
                    if (function_exists('logError')) {
                        logError('Session initialization skipped because headers already sent');
                    }
                    return false;
                }

                // Detect HTTPS
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                    || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
                
                session_start([
                    'cookie_httponly' => self::SESSION_HTTP_ONLY,
                    'cookie_samesite' => self::SESSION_SAME_SITE,
                    'cookie_secure'   => $isHttps,
                    'use_strict_mode' => true,
                    'sid_length'      => 32,
                    'sid_bits_per_character' => 6,
                    'use_cookies' => PHP_SAPI !== 'cli',
                    'use_only_cookies' => PHP_SAPI !== 'cli',
                ]);
                
                $this->initialized = true;
                return true;
            } catch (Throwable $e) {
                logError("Session initialization error: " . $e->getMessage());
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Check if session is initialized
     */
    public function isInitialized(): bool {
        return $this->initialized || session_status() === PHP_SESSION_ACTIVE;
    }
    
    /**
     * Ensure session is initialized, throw if fails
     */
    public function requireInitialized(): void {
        if (!$this->initialize()) {
            throw new RuntimeException('Failed to initialize session');
        }
    }
    
    // ============================================================
    // USER SESSION CREATION & MANAGEMENT
    // ============================================================
    
    /**
     * Create authenticated user session
     * Should be called after successful login
     * 
     * @param int $userId User ID
     * @param array $userData User information (username, email, etc.)
     * @param array $roles User roles
     * @param array $permissions User permissions
     * @return bool Success
     */
    public function createUserSession(
        int $userId,
        array $userData = [],
        array $roles = [],
        array $permissions = []
    ): bool {
        try {
            $this->requireInitialized();
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Set user session data
            $_SESSION[self::KEY_USER_ID] = $userId;
            $_SESSION[self::KEY_USERNAME] = $userData['username'] ?? 'User';
            $_SESSION[self::KEY_EMAIL] = $userData['email'] ?? '';
            $_SESSION[self::KEY_FIRST_NAME] = $userData['first_name'] ?? '';
            $_SESSION[self::KEY_LAST_NAME] = $userData['last_name'] ?? '';
            $_SESSION[self::KEY_FULL_NAME] = trim(
                ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')
            ) ?: ($userData['username'] ?? 'User');
            $_SESSION[self::KEY_ROLE] = $userData['role'] ?? 'user';
            $_SESSION[self::KEY_ROLES] = $roles;
            $_SESSION[self::KEY_PERMISSIONS] = $permissions;
            $_SESSION[self::KEY_LOGGED_IN] = true;
            $_SESSION[self::KEY_LOGIN_TIME] = time();
            $_SESSION[self::KEY_IP_ADDRESS] = $this->getClientIp();
            $_SESSION[self::KEY_USER_AGENT] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION[self::KEY_LAST_ACTIVITY] = time();
            $_SESSION[self::KEY_LAST_REGEN] = time();
            
            // Generate CSRF token
            $this->generateCsrfToken();
            
            return true;
        } catch (Throwable $e) {
            logError("Session creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user session with new data
     * Useful for updating roles/permissions after change
     */
    public function updateUserSession(array $updates): bool {
        try {
            $this->requireInitialized();
            
            if (!$this->isAuthenticated()) {
                return false;
            }
            
            foreach ($updates as $key => $value) {
                $_SESSION[$key] = $value;
            }
            
            $_SESSION[self::KEY_LAST_ACTIVITY] = time();
            return true;
        } catch (Throwable $e) {
            logError("Session update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Destroy user session (logout)
     */
    public function destroySession(int $userId = 0): bool {
        try {
            $this->requireInitialized();
            
            // Clear all session data
            $_SESSION = [];
            
            // Destroy session cookie
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            
            // Destroy session
            session_destroy();
            $this->initialized = false;
            
            return true;
        } catch (Throwable $e) {
            logError("Session destruction error: " . $e->getMessage());
            return false;
        }
    }
    
    // ============================================================
    // SESSION DATA ACCESS - SAFE & TYPED
    // ============================================================
    
    /**
     * Get current authenticated user ID
     * 
     * @return int User ID (0 if not authenticated)
     */
    public function getUserId(): int {
        $this->requireInitialized();
        return (int)($_SESSION[self::KEY_USER_ID] ?? 0);
    }
    
    /**
     * Check if user is authenticated
     * 
     * @return bool True if user is logged in
     */
    public function isAuthenticated(): bool {
        $this->requireInitialized();
        return !empty($_SESSION[self::KEY_LOGGED_IN]) 
            && !empty($_SESSION[self::KEY_USER_ID])
            && (int)$_SESSION[self::KEY_USER_ID] > 0;
    }
    
    /**
     * Get complete authenticated user data as array
     * 
     * @return array User data with all fields
     */
    public function getUserData(): array {
        $this->requireInitialized();
        
        if (!$this->isAuthenticated()) {
            return [];
        }
        
        return [
            'id' => $this->getUserId(),
            'user_id' => $this->getUserId(),
            'username' => $this->get(self::KEY_USERNAME, 'User'),
            'email' => $this->get(self::KEY_EMAIL, ''),
            'first_name' => $this->get(self::KEY_FIRST_NAME, ''),
            'last_name' => $this->get(self::KEY_LAST_NAME, ''),
            'full_name' => $this->get(self::KEY_FULL_NAME, 'User'),
            'role' => $this->get(self::KEY_ROLE, 'user'),
            'roles' => $this->get(self::KEY_ROLES, [], 'array'),
            'permissions' => $this->get(self::KEY_PERMISSIONS, [], 'array'),
            'logged_in' => $this->isAuthenticated(),
            'login_time' => $this->get(self::KEY_LOGIN_TIME, 0, 'int'),
            'ip_address' => $this->get(self::KEY_IP_ADDRESS, ''),
        ];
    }
    
    /**
     * Get session value safely with type casting
     * 
     * @param string $key Session key
     * @param mixed $default Default value if not found
     * @param string $type Type casting ('int', 'string', 'bool', 'array')
     * @return mixed Typed session value
     */
    public function get(string $key, $default = null, string $type = 'string') {
        $this->requireInitialized();
        
        if (!isset($_SESSION[$key])) {
            return $default;
        }
        
        $value = $_SESSION[$key];
        
        switch ($type) {
            case 'int':
                return (int)$value;
            case 'bool':
                return (bool)$value;
            case 'array':
                return is_array($value) ? $value : ($default ?: []);
            case 'string':
            default:
                return (string)$value;
        }
    }
    
    /**
     * Set session value
     * 
     * @param string $key Session key
     * @param mixed $value Value to set
     * @return bool Success
     */
    public function set(string $key, $value): bool {
        try {
            $this->requireInitialized();
            $_SESSION[$key] = $value;
            $_SESSION[self::KEY_LAST_ACTIVITY] = time();
            return true;
        } catch (Throwable $e) {
            logError("Session set error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if session key exists
     */
    public function has(string $key): bool {
        $this->requireInitialized();
        return isset($_SESSION[$key]);
    }
    
    /**
     * Delete session key
     */
    public function delete(string $key): bool {
        try {
            $this->requireInitialized();
            unset($_SESSION[$key]);
            return true;
        } catch (Throwable $e) {
            logError("Session delete error: " . $e->getMessage());
            return false;
        }
    }
    
    // ============================================================
    // CSRF TOKEN MANAGEMENT
    // ============================================================
    
    /**
     * Generate or retrieve CSRF token
     * 
     * @return string CSRF token
     */
    public function generateCsrfToken(): string {
        $this->requireInitialized();
        
        // Return existing token if available
        if (!empty($_SESSION[self::KEY_CSRF_TOKEN])) {
            return $_SESSION[self::KEY_CSRF_TOKEN];
        }
        
        // Generate new token
        $token = bin2hex(random_bytes(self::CSRF_TOKEN_LENGTH / 2));
        $_SESSION[self::KEY_CSRF_TOKEN] = $token;
        
        return $token;
    }
    
    /**
     * Get current CSRF token
     */
    public function getCsrfToken(): string {
        $this->requireInitialized();
        return $_SESSION[self::KEY_CSRF_TOKEN] ?? $this->generateCsrfToken();
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string|null $token Token to validate
     * @return bool Validation result
     */
    public function validateCsrfToken(?string $token): bool {
        $this->requireInitialized();
        
        if (empty($token)) {
            return false;
        }
        
        $sessionToken = $_SESSION[self::KEY_CSRF_TOKEN] ?? null;
        
        if (empty($sessionToken)) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }
    
    // ============================================================
    // FLASH MESSAGE MANAGEMENT
    // ============================================================
    
    /**
     * Set flash message for next request
     * 
     * @param string $message Message text
     * @param string $type Message type (success, error, warning, info)
     */
    public function setFlash(string $message, string $type = 'info'): void {
        $this->requireInitialized();
        
        $_SESSION[self::KEY_FLASH_MESSAGE] = [
            'message' => $message,
            'type' => $type,
            'timestamp' => time(),
        ];
    }
    
    /**
     * Get and clear flash message
     * 
     * @return array|null Flash message or null
     */
    public function getFlash(): ?array {
        $this->requireInitialized();
        
        if (empty($_SESSION[self::KEY_FLASH_MESSAGE])) {
            return null;
        }
        
        $message = $_SESSION[self::KEY_FLASH_MESSAGE];
        unset($_SESSION[self::KEY_FLASH_MESSAGE]);
        
        return $message;
    }
    
    /**
     * Check if flash message exists
     */
    public function hasFlash(): bool {
        $this->requireInitialized();
        return !empty($_SESSION[self::KEY_FLASH_MESSAGE]);
    }
    
    // ============================================================
    // UTILITY METHODS
    // ============================================================
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        return trim($ip);
    }
    
    /**
     * Validate session timeout
     * 
     * @param int $timeout Timeout in seconds
     * @return bool True if session is still valid
     */
    public function isSessionValid(int $timeout = self::SESSION_TIMEOUT): bool {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $lastActivity = $this->get(self::KEY_LAST_ACTIVITY, 0, 'int');
        $now = time();
        
        if (($now - $lastActivity) > $timeout) {
            $this->destroySession();
            return false;
        }
        
        // Security: Validate User Agent to prevent session hijacking
        $storedUserAgent = $this->get(self::KEY_USER_AGENT, '');
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if ($storedUserAgent !== $currentUserAgent) {
            $this->destroySession();
            return false;
        }

        // Security: Periodically regenerate session ID (every 15 minutes)
        $lastRegen = $this->get(self::KEY_LAST_REGEN, 0, 'int');
        if (($now - $lastRegen) > 900) {
            session_regenerate_id(true);
            $_SESSION[self::KEY_LAST_REGEN] = $now;
        }

        // Update last activity
        $_SESSION[self::KEY_LAST_ACTIVITY] = $now;
        return true;
    }
    
    /**
     * Get session summary for logging
     */
    public function getSummary(): array {
        return [
            'authenticated' => $this->isAuthenticated(),
            'user_id' => $this->getUserId(),
            'username' => $this->get(self::KEY_USERNAME),
            'ip_address' => $this->get(self::KEY_IP_ADDRESS),
            'login_time' => date('Y-m-d H:i:s', $this->get(self::KEY_LOGIN_TIME, 0, 'int')),
            'session_id' => session_id(),
        ];
    }
}
