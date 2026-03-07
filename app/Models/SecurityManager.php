<?php

/**

 * SecurityManager.php

 * 

 * Central security management class for authentication and authorization.

 * Handles:

 * - Login attempt tracking and account lockout

 * - Password security and reset

 * - Session management

 * - 2FA/TOTP

 * - Security settings enforcement

 * - Audit logging

 * 

 * @package BroxBhai

 * @version 1.0.0

 */



class SecurityManager {

    private $mysqli;

    private $settingsCache = [];

    

    const DEFAULT_SESSION_TIMEOUT = 3600;

    const DEFAULT_IDLE_TIMEOUT = 1800;

    const TOKEN_LENGTH = 64; // bytes

    

    public function __construct(mysqli $mysqli) {

        $this->mysqli = $mysqli;

        $this->loadSecuritySettings();

    }

    

    /**

     * Load all security settings into memory cache

     */

    private function loadSecuritySettings(): void {

        $result = $this->mysqli->query("SELECT setting_key, setting_value, setting_type FROM app_security_settings");

        while ($row = $result->fetch_assoc()) {

            $value = $row['setting_value'];

            

            // Type casting

            switch ($row['setting_type']) {

                case 'integer':

                    $value = (int)$value;

                    break;

                case 'boolean':

                    $value = in_array(strtolower($value), ['1', 'true', 'yes']);

                    break;

                case 'json':

                    $value = json_decode($value, true) ?? [];

                    break;

            }

            

            $this->settingsCache[$row['setting_key']] = $value;

        }

    }

    

    /**

     * Check if audit table exists (safe table existence check for graceful degradation)

     * @param string $tableName

     * @return bool

     */

    private function auditTableExists(string $tableName): bool {

        $result = $this->mysqli->query("SELECT 1 FROM {$tableName} LIMIT 1");

        return $result !== false;

    }

    

    /**

     * Get security setting value

     */

    public function getSetting(string $key, mixed $default = null): mixed {

        return $this->settingsCache[$key] ?? $default;

    }

    /**
     * Check whether Firebase OAuth login/link flows are globally enabled.
     */
    public function isFirebaseOAuthEnabled(): bool {

        return (bool)$this->getSetting('enable_firebase_oauth', true);

    }

    /**
     * Backward-compatible provider check; current policy is global-only control.
     */
    public function isOAuthProviderEnabled(string $provider): bool {

        return $this->isFirebaseOAuthEnabled();

    }

    

    /**

     * Update security setting

     */

    public function updateSetting(string $key, mixed $value, string $type = 'string'): bool {

        // Convert to string for storage

        if (is_array($value)) {

            $value = json_encode($value);

            $type = 'json';

        } elseif (is_bool($value)) {

            $value = $value ? '1' : '0';

            $type = 'boolean';

        } else {

            $value = (string)$value;

        }

        

        $stmt = $this->mysqli->prepare("

            INSERT INTO app_security_settings (setting_key, setting_value, setting_type, updated_at)

            VALUES (?, ?, ?, NOW())

            ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?, updated_at = NOW()

        ");

        

        $stmt->bind_param('sssss', $key, $value, $type, $value, $type);

        $result = $stmt->execute();

        

        // Update cache

        if ($result) {

            $this->settingsCache[$key] = $value;

        }

        

        return $result;

    }

    

    /**

     * ========== ACCOUNT LOCKOUT & LOGIN ATTEMPTS ==========

     */

    

    /**

     * Check if account is locked

     */

    public function isAccountLocked(int $userId): bool {

        $stmt = $this->mysqli->prepare("

            SELECT account_locked_until FROM users 

            WHERE id = ? AND deleted_at IS NULL

        ");

        $stmt->bind_param('i', $userId);

        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();

        

        if (!$result) {

            return false;

        }

        

        if ($result['account_locked_until'] && strtotime($result['account_locked_until']) > time()) {

            return true;

        }

        

        // Unlock expired lockout

        if ($result['account_locked_until']) {

            $this->unlockAccount($userId);

        }

        

        return false;

    }

    

    /**

     * Get account lockout details

     */

    public function getAccountLockoutInfo(int $userId): ?array {

        $stmt = $this->mysqli->prepare("

            SELECT 

                failed_login_attempts,

                account_locked_until,

                last_failed_login_at

            FROM users 

            WHERE id = ? AND deleted_at IS NULL

        ");

        $stmt->bind_param('i', $userId);

        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();

        

        if (!$result) {

            return null;

        }

        

        return [

            'failed_attempts' => (int)$result['failed_login_attempts'],

            'locked_until' => $result['account_locked_until'],

            'last_failed_at' => $result['last_failed_login_at']

        ];

    }

    

    /**

     * Record failed login attempt

     */

    public function recordFailedLogin(

        int $userId,

        ?string $emailAttempted = null,

        ?string $usernameAttempted = null,

        string $reason = 'invalid_credentials',

        string $loginMethod = 'email_password'

    ): void {

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        

        // Write into consolidated auth_audit_log (best-effort)
        try {
            $this->recordAuthAudit([
                'user_id' => $userId,
                'event_type' => 'login',
                'action' => null,
                'email_attempted' => $emailAttempted,
                'username_attempted' => $usernameAttempted,
                'login_method' => $loginMethod,
                'success' => 0,
                'failure_reason' => $reason,
                'ip_address' => $ip,
                'user_agent' => $userAgent
            ]);
        } catch (Exception $e) { logError('recordAuthAudit (failed_login) failed: ' . $e->getMessage()); }

        

        // Increment failed login attempts directly (instead of stored procedure)

        $this->mysqli->query("UPDATE users SET failed_login_attempts = COALESCE(failed_login_attempts, 0) + 1, last_failed_login_at = NOW() WHERE id = $userId");

        

        // Log activity

        logActivity("Login Failed", "auth", $userId, [

            'reason' => $reason,

            'ip' => $ip,

            'method' => $loginMethod

        ], 'failure');

    }

    

    /**

     * Record successful login

     */

    public function recordSuccessfulLogin(int $userId, string $loginMethod = 'email_password'): void {

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        

        // Update last_login, login_ip, login_device in users table

        $updateStmt = $this->mysqli->prepare("

            UPDATE users 

            SET last_login = NOW(), login_ip = ?, login_device = ?

            WHERE id = ?

        ");

        $updateStmt->bind_param('ssi', $ip, $userAgent, $userId);

        $updateStmt->execute();

        $updateStmt->close();

        

        // Best-effort write into consolidated auth_audit_log
        try {
            $this->recordAuthAudit([
                'user_id' => $userId,
                'event_type' => 'login',
                'action' => 'login_success',
                'login_method' => $loginMethod,
                'success' => 1,
                'ip_address' => $ip,
                'user_agent' => $userAgent
            ]);
        } catch (Exception $e) { logError('recordAuthAudit (login_success) failed: ' . $e->getMessage()); }

        

        // Reset failed login attempts and unlock account

        $resetStmt = $this->mysqli->prepare("

            UPDATE user_security 

            SET failed_login_attempts = 0, account_locked_until = NULL

            WHERE user_id = ?

        ");

        $resetStmt->bind_param('i', $userId);

        $resetStmt->execute();

        $resetStmt->close();

        

        // Log activity

        logActivity("Login Success", "auth", $userId, [

            'method' => $loginMethod,

            'ip' => $ip

        ], 'success');
        
        // Also write to consolidated auth_audit_log (non-blocking)
        try {
            $this->recordAuthAudit([
                'user_id' => $userId,
                'event_type' => 'login',
                'action' => 'login_success',
                'login_method' => $loginMethod,
                'success' => 1,
                'ip_address' => $ip,
                'user_agent' => $userAgent
            ]);
        } catch (Exception $e) { logError('recordAuthAudit (login_success) failed: ' . $e->getMessage()); }

    }
    
    /**
     * Record OAuth action (signin, link, unlink, etc.)
     */
    public function recordOAuthAction(int $userId, string $action, string $provider, string $providerUserId, string $status = 'success'): bool {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $result = $this->recordAuthAudit([
                'user_id' => $userId,
                'event_type' => 'oauth',
                'action' => $action,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'status' => $status,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'details' => null
            ]);

            if ($result) {
                logActivity("OAuth {$action} ({$provider})", "oauth", $userId, ['provider' => $provider, 'status' => $status], $status === 'success' ? 'success' : 'warning');
            }
            return (bool)$result;
        } catch (Exception $e) { logError("SecurityManager::recordOAuthAction - Exception: " . $e->getMessage()); return false; }
    }

    /**
     * Record into consolidated auth_audit_log table.
     * Accepts an associative array of values; fields are optional and will be coerced to strings when necessary.
     */
    public function recordAuthAudit(array $data): bool {
        try {
            // Check if table exists before attempting to write (graceful degradation)
            if (!$this->auditTableExists('auth_audit_log')) {
                logError('WARNING: auth_audit_log table does not exist. Audit logging disabled. Run /db-migrate.php to apply migrations.');
                return false;
            }
            
            $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
            $eventType = $data['event_type'] ?? 'login';
            $action = $data['action'] ?? null;
            $provider = $data['provider'] ?? null;
            $providerUserId = $data['provider_user_id'] ?? null;
            $providerEmail = $data['provider_email'] ?? null;
            $emailAttempted = $data['email_attempted'] ?? null;
            $usernameAttempted = $data['username_attempted'] ?? null;
            $loginMethod = $data['login_method'] ?? null;
            $success = isset($data['success']) ? (int)$data['success'] : null;
            $failureReason = $data['failure_reason'] ?? null;
            $status = $data['status'] ?? null;
            $errorMessage = $data['error_message'] ?? null;
            $ip = $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
            $userAgent = $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
            $deviceName = $data['device_name'] ?? null;
            $browser = $data['browser'] ?? null;
            $os = $data['os'] ?? null;
            $details = isset($data['details']) ? json_encode($data['details']) : null;

            $stmt = $this->mysqli->prepare(
                "INSERT INTO auth_audit_log (user_id, event_type, action, provider, provider_user_id, provider_email, email_attempted, username_attempted, login_method, success, failure_reason, status, error_message, ip_address, user_agent, device_name, browser, os, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            if (!$stmt) { logError("SecurityManager::recordAuthAudit - prepare error: " . $this->mysqli->error); return false; }

            $types = 'issssssssisssssssss';
            $stmt->bind_param($types,
                $userId,
                $eventType,
                $action,
                $provider,
                $providerUserId,
                $providerEmail,
                $emailAttempted,
                $usernameAttempted,
                $loginMethod,
                $success,
                $failureReason,
                $status,
                $errorMessage,
                $ip,
                $userAgent,
                $deviceName,
                $browser,
                $os,
                $details
            );

            $res = $stmt->execute();
            $stmt->close();
            return (bool)$res;
        } catch (Exception $e) {
            logError('SecurityManager::recordAuthAudit exception: ' . $e->getMessage());
            return false;
        }
    }

        
        

    /**

     * Unlock user account

     */

    public function unlockAccount(int $userId): bool {

        $stmt = $this->mysqli->prepare("

            UPDATE users SET 

                account_locked_until = NULL,

                failed_login_attempts = 0

            WHERE id = ?

        ");

        $stmt->bind_param('i', $userId);

        return $stmt->execute();

    }

    

    /**

     * Lock user account immediately

     */

    public function lockAccount(int $userId, string $reason = 'admin_lockout'): bool {

        $lockoutDuration = $this->getSetting('account_lockout_duration', 1800);

        $lockedUntil = date('Y-m-d H:i:s', time() + $lockoutDuration);

        

        $stmt = $this->mysqli->prepare("

            UPDATE users SET 

                account_locked_until = ?,

                failed_login_attempts = 999

            WHERE id = ?

        ");

        $stmt->bind_param('si', $lockedUntil, $userId);

        return $stmt->execute();

    }

    

    /**

     * ========== PASSWORD RESET ==========

     */

    

    /**

     * Generate secure password reset token

     */

    public function generatePasswordResetToken(int $userId): ?string {

        $userModel = new UserModel($this->mysqli);

        $user = $userModel->findById($userId);

        

        if (!$user) {

            return null;

        }

        

        // Generate random token

        $rawToken = bin2hex(random_bytes(self::TOKEN_LENGTH));

        $tokenHash = hash('sha256', $rawToken);

        

        // Set expiry (default 1 hour)

        $expirySeconds = 3600;

        $expiresAt = date('Y-m-d H:i:s', time() + $expirySeconds);

        

        // Store hashed token in database

        $stmt = $this->mysqli->prepare("

            INSERT INTO password_resets 

            (user_id, token, token_type, expires_at)

            VALUES (?, ?, 'password_reset', ?)

        ");

        $stmt->bind_param('iss', $userId, $tokenHash, $expiresAt);

        

        if ($stmt->execute()) {

            logActivity("Password Reset Token Generated", "auth", $userId, ['expires_at' => $expiresAt], 'success');

            return $rawToken; // Return unhashed token to send in URL

        }

        

        return null;

    }

    

    /**

     * Verify password reset token

     */

    public function verifyPasswordResetToken(string $token): ?array {

        $tokenHash = hash('sha256', $token);

        

        $stmt = $this->mysqli->prepare("

            SELECT id, user_id, token_type, used, expires_at 

            FROM password_resets 

            WHERE token = ? AND used = 0

            ORDER BY created_at DESC

            LIMIT 1

        ");

        $stmt->bind_param('s', $tokenHash);

        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();

        

        if (!$result) {

            logActivity("Invalid Password Reset Token", "auth", null, ['token_prefix' => substr($token, 0, 10)], 'failure');

            return null;

        }

        

        // Check expiry

        if (strtotime($result['expires_at']) < time()) {

            logActivity("Expired Password Reset Token", "auth", $result['user_id'], [], 'failure');

            return null;

        }

        

        return $result;

    }

    

    /**

     * Reset password with token

     */

    public function resetPasswordWithToken(string $token, string $newPassword): bool {

        $tokenData = $this->verifyPasswordResetToken($token);

        

        if (!$tokenData) {

            return false;

        }

        

        $userId = $tokenData['user_id'];

        $tokenId = $tokenData['id'];

        

        // Validate password

        if (!$this->validatePassword($newPassword)) {

            logActivity("Password Reset Failed - Invalid Password", "auth", $userId, ['reason' => 'weak_password'], 'failure');

            return false;

        }

        

        // Hash password

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        

        // Update password and mark token as used

        $stmt = $this->mysqli->prepare("

            UPDATE users SET 

                password = ?,

                password_changed_at = NOW()

            WHERE id = ?

        ");

        $stmt->bind_param('si', $hashedPassword, $userId);

        $result = $stmt->execute();

        

        if ($result) {

            // Mark token as used

            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            $stmt = $this->mysqli->prepare("

                UPDATE password_resets SET 

                    used = 1,

                    used_at = NOW(),

                    used_ip = ?

                WHERE id = ?

            ");

            $stmt->bind_param('si', $ip, $tokenId);

            $stmt->execute();

            

            logActivity("Password Reset - Success", "auth", $userId, ['ip' => $ip], 'success');

        }

        

        return $result;

    }

    

    /**

     * ========== REMEMBER ME TOKENS ==========

     */

    

    /**

     * Generate Remember Me token

     */

    public function generateRememberMeToken(int $userId): ?array {

        if (!$this->getSetting('enable_remember_me', true)) {

            return null;

        }

        

        // Generate random token

        $rawToken = bin2hex(random_bytes(self::TOKEN_LENGTH));

        $tokenHash = hash('sha256', $rawToken);

        $tokenFamily = bin2hex(random_bytes(16));

        

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $duration = $this->getSetting('remember_me_duration', 2592000);

        $expiresAt = date('Y-m-d H:i:s', time() + $duration);

        

        $stmt = $this->mysqli->prepare("

            INSERT INTO remember_tokens 

            (user_id, token_hash, token_family, ip_address, user_agent, expires_at)

            VALUES (?, ?, ?, ?, ?, ?)

        ");

        $stmt->bind_param('isssss', $userId, $tokenHash, $tokenFamily, $ip, $userAgent, $expiresAt);

        

        if ($stmt->execute()) {

            logActivity("Remember Me Token Generated", "auth", $userId, ['expires_at' => $expiresAt], 'success');

            

            return [

                'token' => $rawToken,

                'family' => $tokenFamily,

                'expires' => $expiresAt

            ];

        }

        

        return null;

    }

    

    /**

     * Verify Remember Me token

     */

    public function verifyRememberMeToken(string $token): ?array {

        $tokenHash = hash('sha256', $token);

        

        $stmt = $this->mysqli->prepare("

            SELECT id, user_id, token_family, expires_at, last_used_at 

            FROM remember_tokens 

            WHERE token_hash = ? AND is_active = 1 AND revoked_at IS NULL

            LIMIT 1

        ");

        $stmt->bind_param('s', $tokenHash);

        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();

        

        if (!$result) {

            logActivity("Invalid Remember Me Token", "auth", null, [], 'failure');

            return null;

        }

        

        // Check expiry

        if (strtotime($result['expires_at']) < time()) {

            logActivity("Expired Remember Me Token", "auth", $result['user_id'], [], 'failure');

            $this->revokeRememberMeToken($result['user_id']);

            return null;

        }

        

        return $result;

    }

    

    /**

     * Rotate Remember Me token (called after successful use)

     */

    public function rotateRememberMeToken(string $oldToken): ?array {

        if (!$this->getSetting('remember_me_rotation', true)) {

            return null;

        }

        

        $tokenData = $this->verifyRememberMeToken($oldToken);

        

        if (!$tokenData) {

            return null;

        }

        

        $userId = $tokenData['user_id'];

        $tokenFamily = $tokenData['token_family'];

        

        // Mark old token as used

        $oldHash = hash('sha256', $oldToken);

        $stmt = $this->mysqli->prepare("

            UPDATE remember_tokens SET last_used_at = NOW()

            WHERE token_hash = ?

        ");

        $stmt->bind_param('s', $oldHash);

        $stmt->execute();

        

        // Generate new token with same family

        $rawNewToken = bin2hex(random_bytes(self::TOKEN_LENGTH));

        $newTokenHash = hash('sha256', $rawNewToken);

        

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $duration = $this->getSetting('remember_me_duration', 2592000);

        $expiresAt = date('Y-m-d H:i:s', time() + $duration);

        

        $stmt = $this->mysqli->prepare("

            INSERT INTO remember_tokens 

            (user_id, token_hash, token_family, ip_address, user_agent, expires_at)

            VALUES (?, ?, ?, ?, ?, ?)

        ");

        $stmt->bind_param('isssss', $userId, $newTokenHash, $tokenFamily, $ip, $userAgent, $expiresAt);

        

        if ($stmt->execute()) {

            return [

                'token' => $rawNewToken,

                'family' => $tokenFamily,

                'expires' => $expiresAt

            ];

        }

        

        return null;

    }

    

    /**

     * Revoke Remember Me tokens for user

     */

    public function revokeRememberMeToken(int $userId, ?string $tokenFamily = null): bool {

        if ($tokenFamily) {

            $stmt = $this->mysqli->prepare("

                UPDATE remember_tokens SET 

                    is_active = 0,

                    revoked_at = NOW()

                WHERE user_id = ? AND token_family = ?

            ");

            $stmt->bind_param('is', $userId, $tokenFamily);

        } else {

            // Revoke all tokens

            $stmt = $this->mysqli->prepare("

                UPDATE remember_tokens SET 

                    is_active = 0,

                    revoked_at = NOW()

                WHERE user_id = ?

            ");

            $stmt->bind_param('i', $userId);

        }

        

        return $stmt->execute();

    }

    

    /**

     * ========== PASSWORD VALIDATION ==========

     */

    

    /**

     * Validate password strength

     */

    public function validatePassword(string $password): bool {

        $minLength = $this->getSetting('min_password_length', 8);

        $complexity = $this->getSetting('password_complexity', [

            'uppercase' => 1,

            'lowercase' => 1,

            'numbers' => 1,

            'symbols' => 1

        ]);

        

        // Check minimum length

        if (strlen($password) < $minLength) {

            return false;

        }

        

        // Check complexity requirements

        if ($complexity['uppercase'] && !preg_match('/[A-Z]/', $password)) {

            return false;

        }

        

        if ($complexity['lowercase'] && !preg_match('/[a-z]/', $password)) {

            return false;

        }

        

        if ($complexity['numbers'] && !preg_match('/[0-9]/', $password)) {

            return false;

        }

        

        if ($complexity['symbols'] && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {

            return false;

        }

        

        return true;

    }

    

    /**

     * Get password validation error message

     */

    public function getPasswordValidationError(string $password): ?string {

        $minLength = $this->getSetting('min_password_length', 8);

        $complexity = $this->getSetting('password_complexity', []);

        

        if (strlen($password) < $minLength) {

            return "Password must be at least $minLength characters long";

        }

        

        if ($complexity['uppercase'] && !preg_match('/[A-Z]/', $password)) {

            return "Password must contain at least one uppercase letter";

        }

        

        if ($complexity['lowercase'] && !preg_match('/[a-z]/', $password)) {

            return "Password must contain at least one lowercase letter";

        }

        

        if ($complexity['numbers'] && !preg_match('/[0-9]/', $password)) {

            return "Password must contain at least one number";

        }

        

        if ($complexity['symbols'] && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {

            return "Password must contain at least one special character";

        }

        

        return null;

    }

    

    /**

     * ========== 2FA / TOTP ==========

     */

    

    /**

     * Check if 2FA is enabled for user

     */

    public function is2FAEnabled(int $userId): bool {

        // Check global requirement

        if ($this->getSetting('enable_2fa_global', false)) {

            return true;

        }

        

        // Check per-user setting

        $stmt = $this->mysqli->prepare("

            SELECT twofa_enabled FROM user_security 

            WHERE user_id = ? AND twofa_enabled = 1

        ");

        $stmt->bind_param('i', $userId);

        $stmt->execute();

        return $stmt->get_result()->num_rows > 0;

    }

    

    /**

     * Check if 2FA is required for admin

     */

    public function is2FARequiredForAdmin(int $userId): bool {

        if (!$this->getSetting('require_2fa_for_admin', false)) {

            return false;

        }

        

        $userModel = new UserModel($this->mysqli);

        $user = $userModel->findById($userId);

        

        return $user && $user['role'] === 'admin';

    }

    

    /**

     * ========== EMAIL VERIFICATION ==========

     */

    

    /**

     * Generate email verification token

     */

    public function generateEmailVerificationToken(int $userId): ?string {

        $rawToken = bin2hex(random_bytes(32));

        $tokenHash = hash('sha256', $rawToken);

        

        $expirySeconds = $this->getSetting('email_verification_token_expiry', 86400);

        $expiresAt = date('Y-m-d H:i:s', time() + $expirySeconds);

        

        $stmt = $this->mysqli->prepare("

            UPDATE users SET 

                email_verification_token = ?,

                email_verification_token_expires_at = ?

            WHERE id = ?

        ");

        $stmt->bind_param('ssi', $tokenHash, $expiresAt, $userId);

        

        if ($stmt->execute()) {

            logActivity("Email Verification Token Generated", "auth", $userId, [], 'success');

            return $rawToken;

        }

        

        return null;

    }

    

    /**

     * Verify email with token

     */

    public function verifyEmailWithToken(string $token): bool {

        $tokenHash = hash('sha256', $token);

        

        $stmt = $this->mysqli->prepare("

            SELECT id FROM users 

            WHERE email_verification_token = ? 

            AND email_verification_token_expires_at > NOW()

            LIMIT 1

        ");

        $stmt->bind_param('s', $tokenHash);

        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();

        

        if (!$result) {

            logActivity("Email Verification Failed - Invalid Token", "auth", null, [], 'failure');

            return false;

        }

        

        $userId = $result['id'];

        

        // Mark as verified

        $stmt = $this->mysqli->prepare("

            UPDATE users SET 

                email_verified = 1,

                email_verification_token = NULL,

                email_verification_token_expires_at = NULL

            WHERE id = ?

        ");

        $stmt->bind_param('i', $userId);

        

        if ($stmt->execute()) {

            logActivity("Email Verified", "auth", $userId, [], 'success');

            return true;

        }

        

        return false;

    }

    

    /**

     * Check if email verification is required

     */

    public function isEmailVerificationRequired(): bool {

        return $this->getSetting('require_email_verification', true);

    }

    

    /**

     * Verify 2FA code (TOTP)

     * Uses Google Authenticator compatible TOTP algorithm

     */

    public function verify2FACode(int $userId, string $code): bool {

        // Get user's 2FA secret

        $stmt = $this->mysqli->prepare("

            SELECT twofa_secret FROM user_security 

            WHERE user_id = ? AND twofa_enabled = 1

            LIMIT 1

        ");

        $stmt->bind_param('i', $userId);

        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();

        

        if (!$result || !$result['twofa_secret']) {

            logActivity("2FA Verification Failed - No Secret", "auth", $userId, [], 'failure');

            return false;

        }

        

        $secret = $result['twofa_secret'];

        

        // Verify the code - check current and adjacent time windows for time sync issues

        if ($this->verifyTOTPCode($code, $secret)) {

            logActivity("2FA Code Verified", "auth", $userId, [], 'success');

            return true;

        }

        

        logActivity("2FA Verification Failed - Invalid Code", "auth", $userId, [], 'failure');

        return false;

    }

    

    /**

     * Verify a new TOTP code during setup (before storing secret)

     * Used when setting up 2FA for the first time

     */

    public function verifyNewTOTPCode(string $secret, string $code): bool {

        if (strlen($code) != 6 || !is_numeric($code)) {

            return false;

        }

        

        // Verify the code against the secret

        return $this->verifyTOTPCode($code, $secret);

    }

    

    /**

     * Verify TOTP code

     * Validates a time-based one-time password against a secret

     */

    private function verifyTOTPCode(string $code, string $secret): bool {

        // Base32 decode the secret

        $secretBinary = $this->base32Decode($secret);

        

        if (!$secretBinary) {

            return false;

        }

        

        // Get current time counter

        $timeCounter = floor(time() / 30); // 30-second window

        

        // Check current time and adjacent windows (±1 for clock drift tolerance)

        for ($i = -1; $i <= 1; $i++) {

            $counter = $timeCounter + $i;

            $hash = hash_hmac('sha1', pack('N*', 0, $counter), $secretBinary, true);

            $offset = ord($hash[19]) & 0xf;

            $value = unpack('N', substr($hash, $offset, 4))[1];

            $value = ($value & 0x7fffffff) % 1000000;

            

            $generatedCode = str_pad($value, 6, '0', STR_PAD_LEFT);

            

            if (hash_equals($generatedCode, $code)) {

                return true;

            }

        }

        

        return false;

    }

    

    /**

     * Base32 decode function (RFC 4648)

     */

    private function base32Decode(string $input): ?string {

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        $input = strtoupper($input);

        $padCount = 0;

        

        // Count padding

        for ($i = strlen($input) - 1; $i >= 0; $i--) {

            if ($input[$i] === '=') {

                $padCount++;

            } else {

                break;

            }

        }

        

        // Remove padding

        $input = rtrim($input, '=');

        

        // Validate input

        for ($i = 0; $i < strlen($input); $i++) {

            if (strpos($alphabet, $input[$i]) === false) {

                return null;

            }

        }

        

        // Decode

        $output = '';

        $v = 0;

        $bits = 0;

        

        for ($i = 0; $i < strlen($input); $i++) {

            $v <<= 5;

            $v += strpos($alphabet, $input[$i]);

            $bits += 5;

            

            if ($bits >= 8) {

                $bits -= 8;

                $output .= chr(($v >> $bits) & 0xff);

                $v &= (1 << $bits) - 1;

            }

        }

        

        return $output;

    }

    

    /**

     * ========== SECURITY UTILITIES ==========

     */

    

    /**

     * Generate secure random token

     */

    public static function generateToken(int $length = 64): string {

        return bin2hex(random_bytes($length));

    }

    

    /**

     * Hash token for storage

     */

    public static function hashToken(string $token): string {

        return hash('sha256', $token);

    }

    

    /**

     * Verify token matches hash

     */

    public static function verifyToken(string $token, string $hash): bool {

        return hash_equals($hash, hash('sha256', $token));

    }

    

    /**

     * Get user by remember me token

     */

    public function getUserByRememberToken(string $token): ?array {

        $tokenData = $this->verifyRememberMeToken($token);

        

        if (!$tokenData) {

            return null;

        }

        

        $userModel = new UserModel($this->mysqli);

        return $userModel->findById($tokenData['user_id']);

    }

    

    /**

     * Cleanup expired data

     */

    /**
     * Get 2FA security information for a user
     */
    public function get2FASecurityInfo(int $userId): array {
        $stmt = $this->mysqli->prepare("
            SELECT twofa_enabled, twofa_secret, twofa_verified_at, created_at 
            FROM user_security 
            WHERE user_id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row;
        }
        
        $stmt->close();
        return ['twofa_enabled' => 0, 'twofa_secret' => null, 'twofa_verified_at' => null, 'created_at' => null];
    }

    public function cleanupExpiredData(): void {

        // TODO: Stored procedure references non-existent definer, disabled for now
        // $this->mysqli->query("CALL cleanup_expired_tokens()");

    }

    /**
     * Enable 2FA for a user (store secret and backup codes)
     * 
     * @param int $userId - User ID
     * @param string $secret - Base32 encoded TOTP secret
     * @return bool - True on success
     */
    public function enable2FA(int $userId, string $secret): bool {
        try {
            // Generate backup codes
            $backupCodes = $this->generateBackupCodes();
            $backupCodesJson = json_encode($backupCodes);

            $sql = "INSERT INTO user_security (user_id, twofa_enabled, twofa_secret, backup_codes, created_at, updated_at) 
                    VALUES (?, 1, ?, ?, NOW(), NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    twofa_enabled = 1, twofa_secret = ?, backup_codes = ?, updated_at = NOW()";

            $stmt = $this->mysqli->prepare($sql);
            if (!$stmt) {
                logError("SecurityManager::enable2FA - Prepare failed: " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param("issss", $userId, $secret, $backupCodesJson, $secret, $backupCodesJson);

            if (!$stmt->execute()) {
                logError("SecurityManager::enable2FA - Execute failed: " . $stmt->error);
                $stmt->close();
                return false;
            }

            $stmt->close();

            logActivity("2FA Enabled", "user_security", $userId, ['method' => 'TOTP'], 'success');
            return true;
        } catch (Exception $e) {
            logError("SecurityManager::enable2FA - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Disable 2FA for a user
     * 
     * @param int $userId - User ID
     * @return bool - True on success
     */
    public function disable2FA(int $userId): bool {
        try {
            $sql = "UPDATE user_security SET twofa_enabled = 0, twofa_secret = NULL, backup_codes = NULL 
                    WHERE user_id = ?";

            $stmt = $this->mysqli->prepare($sql);
            if (!$stmt) {
                logError("SecurityManager::disable2FA - Prepare failed: " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param("i", $userId);

            if (!$stmt->execute()) {
                logError("SecurityManager::disable2FA - Execute failed: " . $stmt->error);
                $stmt->close();
                return false;
            }

            $stmt->close();

            logActivity("2FA Disabled", "user_security", $userId, ['method' => 'TOTP'], 'success');
            return true;
        } catch (Exception $e) {
            logError("SecurityManager::disable2FA - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get 2FA backup codes for a user
     * 
     * @param int $userId - User ID
     * @return array - Array of backup codes or empty array
     */
    public function getBackupCodes(int $userId): array {
        try {
            $sql = "SELECT backup_codes FROM user_security WHERE user_id = ? AND twofa_enabled = 1";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if (!$row || empty($row['backup_codes'])) {
                return [];
            }

            return json_decode($row['backup_codes'], true) ?: [];
        } catch (Exception $e) {
            logError("SecurityManager::getBackupCodes - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get 2FA status for a user
     * Returns 2FA enabled status and creation date
     * 
     * @param int $userId - User ID
     * @return array - Array with 'enabled' and 'created_at' keys
     */
    public function get2FAStatus(int $userId): array {
        try {
            $sql = "SELECT twofa_enabled, created_at FROM user_security WHERE user_id = ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if (!$row) {
                return ['enabled' => false, 'created_at' => null];
            }

            return [
                'enabled' => (bool)$row['twofa_enabled'],
                'created_at' => $row['created_at']
            ];
        } catch (Exception $e) {
            logError("SecurityManager::get2FAStatus - " . $e->getMessage());
            return ['enabled' => false, 'created_at' => null];
        }
    }

    /**
     * Generate backup codes (10 codes in format XXXX-XXXX-XXXX)
     * 
     * @param int $count - Number of backup codes to generate
     * @return array - Array of backup codes
     */
    private function generateBackupCodes($count = 10): array {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $code = '';
            for ($j = 0; $j < 3; $j++) {
                $code .= sprintf('%04X', random_int(0, 65535));
                if ($j < 2) $code .= '-';
            }
            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * Log OAuth audit data
     * @param array $data
     * @return bool
     */
    public function logOAuthAudit(array $data): bool {
        try {
            return $this->recordAuthAudit([
                'user_id' => $data['user_id'] ?? null,
                'event_type' => 'oauth',
                'action' => $data['action'] ?? null,
                'details' => $data['details'] ?? null,
                'ip_address' => $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')
            ]);
        } catch (Exception $e) { logError('logOAuthAudit -> recordAuthAudit failed: ' . $e->getMessage()); return false; }
    }

    /**
     * Log login audit data
     * @param array $data
     * @return bool
     */
    public function logLoginAudit(array $data): bool {
        // Delegate to consolidated auth_audit_log (recordAuthAudit handles table existence)
        try {
            return $this->recordAuthAudit([
                'user_id' => $data['user_id'] ?? null,
                'event_type' => 'login',
                'success' => isset($data['success']) ? (int)$data['success'] : null,
                'ip_address' => $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'details' => $data['details'] ?? null
            ]);
        } catch (Exception $e) { logError('logLoginAudit -> recordAuthAudit failed: ' . $e->getMessage()); return false; }
    }

    /**
     * Log OAuth action (login or link)
     * @param int $userId
     * @param string $action 'login' or 'link'
     * @param string $provider OAuth provider (google, facebook, etc.)
     * @param string $providerId Provider's user ID
     * @param bool $success Whether the action was successful
     * @param string|null $errorMessage Error message if failed
     * @return bool
     */
    public function logOAuthAction(?int $userId, string $action, string $provider, string $providerId, bool $success, ?string $errorMessage = null): bool {
        $userDetails = getUserDetails();
        $details = json_encode([
            'provider' => $provider,
            'provider_id' => $providerId,
            'action' => $action,
            'success' => $success,
            'error' => $errorMessage,
            'ip_address' => $userDetails['ip_address'],
            'user_agent' => $userDetails['user_agent'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        try {
            return $this->recordAuthAudit([
                'user_id' => $userId,
                'event_type' => 'oauth',
                'action' => $action,
                'provider' => $provider,
                'provider_user_id' => $providerId,
                'status' => $success ? 'success' : 'failure',
                'error_message' => $errorMessage,
                'details' => json_decode($details, true)
            ]);
        } catch (Exception $e) { logError('logOAuthAction -> recordAuthAudit failed: ' . $e->getMessage()); return false; }
    }

    /**
     * Log login attempt
     * @param int $userId
     * @param bool $success Whether login was successful
     * @param string|null $errorMessage Error message if failed
     * @return bool
     */
    public function logLoginAttempt(?int $userId, bool $success, ?string $errorMessage = null): bool {
        // Delegate to consolidated auth_audit_log (recordAuthAudit handles table existence)
        try {
            return $this->recordAuthAudit([
                'user_id' => $userId,
                'event_type' => 'login',
                'success' => $success ? 1 : 0,
                'ip_address' => getUserDetails()['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => getUserDetails()['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'error_message' => $errorMessage
            ]);
        } catch (Exception $e) { logError('logLoginAttempt -> recordAuthAudit failed: ' . $e->getMessage()); return false; }
    }

}
