<?php
/**
 * controllers/AdminSecurityController.php
 * Admin-specific security and 2FA management routes
 * Separate from user self-service 2FA routes
 */

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
require_once __DIR__ . '/../Helpers/AuthAndSecurityHelper.php';
$userModel = new UserModel($mysqli);
$securityManager = new SecurityManager($mysqli);

// ==================== ADMIN 2FA MANAGEMENT GROUP ====================
$router->group('/admin/security', ['middleware' => ['auth', 'admin_or_super_only']], function ($router) use ($twig, $userModel, $securityManager) {

    $canManageAdminSecurity = function (?array $user, int $userId) use ($userModel): bool {
            if (!$user || $userId <= 0) {
                return false;
            }

            if ($userModel->isSuperAdmin($userId)) {
                return true;
            }

            if ($userModel->hasRole($userId, 'admin')) {
                return true;
            }

            return strtolower((string)($user['role'] ?? '')) === 'admin';
        }
            ;

        // ==================== ADMIN 2FA DASHBOARD ====================
        // GET /admin/security/2fa - Admin view for managing 2FA settings
        $router->get('/2fa', function () use ($twig, $securityManager, $canManageAdminSecurity) {
            try {
                $userId = AuthManager::getCurrentUserId();
                if (!$userId) {
                    header("Location: /login");
                    exit;
                }

                $user = AuthManager::getCurrentUserArray();
                if (!$canManageAdminSecurity($user, (int)$userId)) {
                    showMessage("Access denied", "error");
                    header('Location: /admin/dashboard');
                    exit;
                }

                // Get 2FA status
                $twoFAStatus = $securityManager->get2FAStatus($userId);

                echo $twig->render('admin/security/2fa.twig', [
                'title' => 'Two-Factor Authentication',
                'page_title' => 'Admin Two-Factor Authentication',
                'user' => $user,
                'twofa_enabled' => $twoFAStatus['enabled'],
                'twofa_method' => $twoFAStatus['method'] ?? 'totp',
                'csrf_token' => generateCsrfToken(),
                ]);
            }
            catch (Throwable $e) {
                logError("Admin 2FA Dashboard Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                showMessage("Failed to load 2FA settings", "danger");
                header('Location: /admin/dashboard');
                exit;
            }
        }
        );

        // ==================== ADMIN 2FA SETUP ====================
        // GET /admin/security/2fa/setup - Setup 2FA for admin
        $router->get('/2fa/setup', function () use ($twig, $canManageAdminSecurity) {
            try {
                $userId = AuthManager::getCurrentUserId();
                if (!$userId) {
                    header("Location: /login");
                    exit;
                }

                $user = AuthManager::getCurrentUserArray();
                if (!$canManageAdminSecurity($user, (int)$userId)) {
                    showMessage("Access denied", "error");
                    header('Location: /admin/dashboard');
                    exit;
                }

                // Generate temporary secret
                if (!isset($_SESSION['temp_2fa_secret'])) {
                    $_SESSION['temp_2fa_secret'] = generateBase32Secret();
                }

                $secret = $_SESSION['temp_2fa_secret'];
                $email = $user['email'];

                // Generate QR code
                $totp_uri = "otpauth://totp/broxbhai:{$email}?secret={$secret}&issuer=Broxbhai";

                try {
                    $qrCode = new QrCode($totp_uri);
                    $writer = new PngWriter();
                    $result = $writer->write($qrCode);
                    $qrImage = 'data:image/png;base64,' . base64_encode($result->getString());
                }
                catch (Exception $e) {
                    $qrImage = null;
                }

                echo $twig->render('admin/security/2fa_setup.twig', [
                'title' => 'Setup Two-Factor Authentication',
                'page_title' => 'Setup 2FA for Admin',
                'user' => $user,
                'secret' => $secret,
                'qr_image' => $qrImage,
                'totp_uri' => $totp_uri,
                'csrf_token' => generateCsrfToken(),
                ]);
            }
            catch (Throwable $e) {
                logError("Admin 2FA Setup Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                showMessage("Failed to setup 2FA", "danger");
                header('Location: /admin/security/2fa');
                exit;
            }
        }
        );

        // ==================== ADMIN 2FA VERIFY SETUP ====================
        // POST /admin/security/2fa/verify - Verify and enable 2FA
        $router->post('/2fa/verify', function () use ($securityManager, $canManageAdminSecurity) {
            try {
                if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                    showMessage("Invalid CSRF token", "error");
                    header("Location: /admin/security/2fa/setup");
                    exit;
                }

                $userId = AuthManager::getCurrentUserId();
                $user = AuthManager::getCurrentUserArray();
                if (!$canManageAdminSecurity($user, (int)$userId)) {
                    showMessage("Access denied", "error");
                    header('Location: /admin/dashboard');
                    exit;
                }

                $code = sanitize_input($_POST['code'] ?? '');
                $secret = $_SESSION['temp_2fa_secret'] ?? null;

                if (!$secret) {
                    showMessage("2FA setup session expired. Please try again.", "error");
                    header("Location: /admin/security/2fa");
                    exit;
                }

                if (!$code || strlen($code) !== 6 || !is_numeric($code)) {
                    showMessage("Invalid authentication code. Please enter a 6-digit code.", "error");
                    header("Location: /admin/security/2fa/setup");
                    exit;
                }

                // Verify the code
                if (!$securityManager->verifyNewTOTPCode($secret, $code)) {
                    logActivity("Admin 2FA Setup Failed - Invalid Code", "security", $userId, [], 'failure');
                    showMessage("Invalid authentication code. Please try again.", "error");
                    header("Location: /admin/security/2fa/setup");
                    exit;
                }

                // Save 2FA settings
                $result = $securityManager->enable2FA((int)$userId, $secret);

                if ($result) {
                    unset($_SESSION['temp_2fa_secret']);
                    logActivity("Admin 2FA Enabled Successfully", "security", $userId, [], 'success');
                    showMessage("Two-Factor Authentication has been enabled successfully!", "success");
                    header("Location: /admin/security/2fa");
                    exit;
                }
                else {
                    logActivity("Admin 2FA Setup Failed - Database Error", "security", $userId, [], 'error');
                    showMessage("Failed to enable 2FA. Please try again.", "error");
                    header("Location: /admin/security/2fa");
                    exit;
                }
            }
            catch (Throwable $e) {
                logError("Admin 2FA Verify Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                showMessage("An error occurred. Please try again.", "danger");
                header('Location: /admin/security/2fa/setup');
                exit;
            }
        }
        );

        // ==================== ADMIN 2FA DISABLE ====================
        // POST /admin/security/2fa/disable - Disable 2FA for admin
        $router->post('/2fa/disable', function () use ($securityManager, $canManageAdminSecurity) {
            try {
                header('Content-Type: application/json');

                if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                    exit;
                }

                $userId = AuthManager::getCurrentUserId();
                if (!$userId) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                    exit;
                }

                $user = AuthManager::getCurrentUserArray();
                if (!$canManageAdminSecurity($user, (int)$userId)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Access denied']);
                    exit;
                }

                // Verify current password
                $currentPassword = $_POST['password'] ?? '';
                if (!password_verify($currentPassword, $user['password'])) {
                    logActivity("Admin 2FA Disable Failed - Invalid Password", "security", $userId, [], 'failure');
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Invalid password']);
                    exit;
                }

                // Disable 2FA
                $result = $securityManager->disable2FA($userId);

                if ($result) {
                    logActivity("Admin 2FA Disabled", "security", $userId, [], 'success');
                    http_response_code(200);
                    echo json_encode([
                    'success' => true,
                    'message' => '2FA has been disabled'
                    ]);
                }
                else {
                    logActivity("Admin 2FA Disable Failed", "security", $userId, [], 'error');
                    http_response_code(500);
                    echo json_encode([
                    'success' => false,
                    'error' => 'Failed to disable 2FA'
                    ]);
                }
                exit;
            }
            catch (Throwable $e) {
                logError("Admin 2FA Disable Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                http_response_code(500);
                echo json_encode([
                'success' => false,
                'error' => 'Internal server error'
                ]);
                exit;
            }
        }
        );

        // ==================== ADMIN 2FA BACKUP CODES VIEW ====================
        // GET /admin/security/2fa/backup-codes - View backup codes after setup
        $router->get('/2fa/backup-codes', function () use ($twig, $securityManager, $canManageAdminSecurity) {
            try {
                $userId = AuthManager::getCurrentUserId();
                if (!$userId) {
                    header("Location: /login");
                    exit;
                }

                $user = AuthManager::getCurrentUserArray();
                if (!$canManageAdminSecurity($user, (int)$userId)) {
                    showMessage("Access denied", "error");
                    header('Location: /admin/dashboard');
                    exit;
                }

                // Get backup codes
                $backupCodes = $securityManager->getBackupCodes($userId);

                if (empty($backupCodes)) {
                    showMessage("No backup codes available. Please setup 2FA first.", "warning");
                    header('Location: /admin/security/2fa');
                    exit;
                }

                echo $twig->render('admin/security/2fa_backup.twig', [
                'title' => 'Backup Codes',
                'page_title' => 'Your 2FA Backup Codes',
                'user' => $user,
                'backup_codes' => $backupCodes,
                'csrf_token' => generateCsrfToken(),
                ]);
            }
            catch (Throwable $e) {
                logError("Admin Backup Codes View Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                showMessage("Failed to load backup codes", "danger");
                header('Location: /admin/security/2fa');
                exit;
            }
        }
        );

        // ==================== ADMIN 2FA REGENERATE BACKUP CODES ====================
        // POST /admin/security/2fa/backup-codes/regenerate - Generate new backup codes
        $router->post('/2fa/backup-codes/regenerate', function () use ($securityManager, $canManageAdminSecurity) {
            try {
                header('Content-Type: application/json');

                if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                    exit;
                }

                $userId = AuthManager::getCurrentUserId();
                if (!$userId) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                    exit;
                }

                $user = AuthManager::getCurrentUserArray();
                if (!$canManageAdminSecurity($user, (int)$userId)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Access denied']);
                    exit;
                }

                // Verify password
                $password = $_POST['password'] ?? '';
                if (!password_verify($password, $user['password'])) {
                    logActivity("Admin Backup Codes Regenerate Failed - Invalid Password", "security", $userId, [], 'failure');
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Invalid password']);
                    exit;
                }

                // Generate new backup codes
                $backupCodes = [];
                for ($i = 0; $i < 10; $i++) {
                    $backupCodes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                }

                // Save to database
                $sql = "UPDATE user_security SET backup_codes = ? WHERE user_id = ? AND twofa_enabled = 1";
                $stmt = $GLOBALS['mysqli']->prepare($sql);
                if (!$stmt) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Database error']);
                    exit;
                }

                $backupCodesJson = json_encode($backupCodes);
                $stmt->bind_param("si", $backupCodesJson, $userId);

                if ($stmt->execute()) {
                    logActivity("Admin Backup Codes Regenerated", "security", $userId, [], 'success');
                    http_response_code(200);
                    echo json_encode([
                    'success' => true,
                    'message' => 'New backup codes generated',
                    'codes' => $backupCodes
                    ]);
                }
                else {
                    logActivity("Admin Backup Codes Regenerate Failed", "security", $userId, [], 'error');
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Failed to generate codes']);
                }
                $stmt->close();
                exit;
            }
            catch (Throwable $e) {
                logError("Admin Backup Codes Regenerate Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Internal server error']);
                exit;
            }
        }
        );
    });
