<?php
// controllers/UserSecurityController.php
<<<<<<< HEAD
// ইজার সেলফ-সার্ভিস 2FA ম্যানেজমেন্ট
=======
// ইউজার সেলফ-সার্ভিস 2FA ম্যানেজমেন্ট
>>>>>>> temp_branch

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
require_once __DIR__ . '/../Helpers/AuthAndSecurityHelper.php';

$userModel = new UserModel($mysqli);
$securityManager = new SecurityManager($mysqli);

// ==================== ইউজার 2FA ম্যানেজমেন্ট গ্রুপ ====================
$router->group('/user/security', ['middleware' => ['auth']], function ($router) use ($twig, $userModel, $securityManager) {
    // GET /user/security/2fa - 2FA স্ট্যাটাস এবং ম্যানেজমেন্ট অপশন দেখান
    $router->get('/2fa', function () use ($twig, $securityManager) {
            if (session_status() === PHP_SESSION_NONE)
                session_start();

            $user = AuthManager::getCurrentUserArray();
            if (!$user) {
                header("Location: /login");
                exit;
            }

            // Get 2FA status from SecurityManager
            $security = $securityManager->get2FAStatus($user['id']);

            echo $twig->render('user/security_2fa.twig', [
            'title' => 'Two-Factor Authentication',
<<<<<<< HEAD
            'header_title' => '2FA à¦¸à§‡à¦Ÿà¦¿à¦‚à¦¸',
=======
            'header_title' => '2FA সেটিংস',
>>>>>>> temp_branch
            'user' => $user,
            'twofa_enabled' => $security['enabled'],
            'twofa_created' => $security['created_at'] ?? null,
            'csrf_token' => generateCsrfToken(),
            ]);
        }
        );

<<<<<<< HEAD
        // ==================== 2FA à¦¸à§‡à¦Ÿà¦†à¦ª à¦¶à§à¦°à§ à¦•à¦°à§à¦¨ ====================
        // GET /user/security/2fa/setup - QR à¦•à§‹à¦¡ à¦à¦¬à¦‚ à¦¸à§‡à¦Ÿà¦†à¦ª à¦«à¦°à§à¦® à¦¦à§‡à¦–à§à¦¨
=======
        // ==================== 2FA সেটআপ শুরু করুন ====================
        // GET /user/security/2fa/setup - QR কোড এবং সেটআপ ফর্ম দেখুন
>>>>>>> temp_branch
        $router->get('/2fa/setup', function () use ($twig) {
            if (session_status() === PHP_SESSION_NONE)
                session_start();

            $user = AuthManager::getCurrentUserArray();
            if (!$user) {
                header("Location: /login");
                exit;
            }

<<<<<<< HEAD
            // à¦¨à¦¤à§à¦¨ à¦¸à¦¿à¦•à§à¦°à§‡à¦Ÿ à¦œà§‡à¦¨à¦¾à¦°à§‡à¦Ÿ à¦•à¦°à§à¦¨ (à¦¯à¦¦à¦¿ à¦¸à§‡à¦¶à¦¨à§‡ à¦¨à¦¾ à¦¥à¦¾à¦•à§‡)
=======
            // নতুন সিক্রেট জেনারেট করুন (যদি সেশনে না থাকে)
>>>>>>> temp_branch
            if (!isset($_SESSION['temp_2fa_secret'])) {
                $_SESSION['temp_2fa_secret'] = generateBase32Secret();
            }

            $secret = $_SESSION['temp_2fa_secret'];
            $email = $user['email'];

<<<<<<< HEAD
            // TOTP URI à¦¤à§ˆà¦°à¦¿ à¦•à¦°à§à¦¨ (Google Authenticator à¦à¦° à¦œà¦¨à§à¦¯)
            $totp_uri = "otpauth://totp/broxbhai:{$email}?secret={$secret}&issuer=Broxbhai";

            // QR à¦•à§‹à¦¡ à¦œà§‡à¦¨à¦¾à¦°à§‡à¦Ÿ à¦•à¦°à§à¦¨
=======
            // TOTP URI তৈরি করুন (Google Authenticator এর জন্য)
            $totp_uri = "otpauth://totp/broxbhai:{$email}?secret={$secret}&issuer=Broxbhai";

            // QR কোড জেনারেট করুন
>>>>>>> temp_branch
            try {
                $qrCode = new QrCode($totp_uri);
                $writer = new PngWriter();
                $result = $writer->write($qrCode);
                $qrImage = 'data:image/png;base64,' . base64_encode($result->getString());
            }
            catch (Exception $e) {
                $qrImage = null;
            }

            echo $twig->render('user/security_2fa_setup.twig', [
            'title' => 'Setup Two-Factor Authentication',
<<<<<<< HEAD
            'header_title' => '2FA à¦¸à§‡à¦Ÿà¦†à¦ª à¦•à¦°à§à¦¨',
=======
            'header_title' => '2FA সেটআপ করুন',
>>>>>>> temp_branch
            'user' => $user,
            'secret' => $secret,
            'qr_image' => $qrImage,
            'totp_uri' => $totp_uri,
            'csrf_token' => generateCsrfToken(),
            ]);
        }
        );

<<<<<<< HEAD
        // ==================== 2FA à¦¸à§‡à¦Ÿà¦†à¦ª à¦¯à¦¾à¦šà¦¾à¦‡ à¦•à¦°à§à¦¨ ====================
        // POST /user/security/2fa/verify - à¦‡à¦‰à¦œà¦¾à¦° à¦à¦° à¦•à§‹à¦¡ à¦¯à¦¾à¦šà¦¾à¦‡ à¦à¦¬à¦‚ à¦¸à¦•à§à¦·à¦® à¦•à¦°à§à¦¨
=======
        // ==================== 2FA সেটআপ যাচাই করুন ====================
        // POST /user/security/2fa/verify - ইউজার এর কোড যাচাই এবং সক্ষম করুন
>>>>>>> temp_branch
        $router->post('/2fa/verify', function () use ($twig, $securityManager) {
            if (session_status() === PHP_SESSION_NONE)
                session_start();

<<<<<<< HEAD
            // CSRF à¦¯à¦¾à¦šà¦¾à¦‡ à¦•à¦°à§à¦¨
=======
>>>>>>> temp_branch
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                showMessage("Invalid CSRF token", "error");
                header("Location: /user/security/2fa/setup");
                exit;
            }

            $user = AuthManager::getCurrentUserArray();
            if (!$user) {
                showMessage("User not found", "error");
                header("Location: /login");
                exit;
            }

<<<<<<< HEAD
            // à¦¸à¦¿à¦•à§à¦°à§‡à¦Ÿ à¦à¦¬à¦‚ à¦•à§‹à¦¡ à¦ªà§à¦°à¦¾à¦ªà§à¦¤ à¦•à¦°à§à¦¨
=======
            // সিক্রেট এবং কোড প্রাপ্ত করুন
>>>>>>> temp_branch
            $secret = $_SESSION['temp_2fa_secret'] ?? null;
            $code = sanitize_input($_POST['code'] ?? '');

            if (!$secret) {
                showMessage("2FA session expired. Please start again.", "error");
                header("Location: /user/security/2fa");
                exit;
            }

            if (strlen($code) != 6 || !is_numeric($code)) {
                showMessage("Invalid code format. Please enter 6 digits.", "error");
                header("Location: /user/security/2fa/setup");
                exit;
            }

<<<<<<< HEAD
            // TOTP à¦•à§‹à¦¡ à¦¯à¦¾à¦šà¦¾à¦‡ à¦•à¦°à§à¦¨
=======
            // TOTP কোড যাচাই করুন
>>>>>>> temp_branch
            if (!$securityManager->verifyNewTOTPCode($secret, $code)) {
                showMessage("Invalid code. Please check and try again.", "error");
                header("Location: /user/security/2fa/setup");
                exit;
            }

            // Enable 2FA using SecurityManager
            if (!$securityManager->enable2FA($user['id'], $secret)) {
                showMessage("Failed to enable 2FA", "error");
                header("Location: /user/security/2fa/setup");
                exit;
            }

<<<<<<< HEAD
            // à¦¸à§‡à¦¶à¦¨ à¦ªà¦°à¦¿à¦·à§à¦•à¦¾à¦° à¦•à¦°à§à¦¨
=======
            // সেশন পরিষ্কার করুন
>>>>>>> temp_branch
            unset($_SESSION['temp_2fa_secret']);

            showMessage("2FA enabled successfully! Please save your backup codes.", "success");
            header("Location: /user/security/2fa/backup");
            exit;
        }
        );

<<<<<<< HEAD
        // ==================== à¦¬à§à¦¯à¦¾à¦•à¦†à¦ª à¦•à§‹à¦¡ à¦¦à§‡à¦–à§à¦¨ ====================
        // GET /user/security/2fa/backup - à¦¬à§à¦¯à¦¾à¦•à¦†à¦ª à¦•à§‹à¦¡ à¦¡à¦¾à¦‰à¦¨à¦²à§‹à¦¡/à¦¦à§‡à¦–à§à¦¨
=======
        // ==================== ব্যাকআপ কোড দেখুন ====================
        // GET /user/security/2fa/backup - ব্যাকআপ কোড ডাউনলোড/দেখুন
>>>>>>> temp_branch
        $router->get('/2fa/backup', function () use ($twig, $securityManager) {
            if (session_status() === PHP_SESSION_NONE)
                session_start();

            $user = AuthManager::getCurrentUserArray();
            if (!$user) {
                header("Location: /login");
                exit;
            }

            // Get backup codes from SecurityManager
            $backupCodes = $securityManager->getBackupCodes($user['id']);

            if (empty($backupCodes)) {
                showMessage("2FA is not enabled", "error");
                header("Location: /user/security/2fa");
                exit;
            }

            echo $twig->render('user/security_2fa_backup.twig', [
            'title' => 'Backup Codes',
<<<<<<< HEAD
            'header_title' => 'à¦¬à§à¦¯à¦¾à¦•à¦†à¦ª à¦•à§‹à¦¡à¦—à§à¦²à¦¿ à¦¸à¦‚à¦°à¦•à§à¦·à¦£ à¦•à¦°à§à¦¨',
=======
            'header_title' => 'ব্যাকআপ কোডগুলি সংরক্ষণ করুন',
>>>>>>> temp_branch
            'user' => $user,
            'backup_codes' => $backupCodes,
            'csrf_token' => generateCsrfToken(),
            ]);
        }
        );

<<<<<<< HEAD
        // ==================== 2FA à¦ªà§à¦°à¦¤à¦¿à¦¬à¦¨à§à¦§à§€ à¦•à¦°à§à¦¨ ====================
        // POST /user/security/2fa/disable - à¦ªà¦¾à¦¸à¦“à¦¯à¦¼à¦¾à¦°à§à¦¡ à¦¯à¦¾à¦šà¦¾à¦‡à¦¯à¦¼à§‡à¦° à¦ªà¦°à§‡ à¦ªà§à¦°à¦¤à¦¿à¦¬à¦¨à§à¦§à§€ à¦•à¦°à§à¦¨
=======
        // ==================== 2FA নিষ্ক্রিয় করুন ====================
        // POST /user/security/2fa/disable - পাসওয়ার্ড যাচাইয়ের পরে নিষ্ক্রিয় করুন
>>>>>>> temp_branch
        $router->post('/2fa/disable', function () use ($twig, $userModel, $securityManager) {
            if (session_status() === PHP_SESSION_NONE)
                session_start();

<<<<<<< HEAD
            // CSRF à¦¯à¦¾à¦šà¦¾à¦‡ à¦•à¦°à§à¦¨
=======
            // CSRF যাচাই করুন
>>>>>>> temp_branch
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                showMessage("Invalid CSRF token", "error");
                header("Location: /user/security/2fa");
                exit;
            }

            $user = AuthManager::getCurrentUserArray();
            if (!$user) {
                showMessage("User not found", "error");
                header("Location: /login");
                exit;
            }

<<<<<<< HEAD
            // à¦ªà¦¾à¦¸à¦“à¦¯à¦¼à¦¾à¦°à§à¦¡ à¦¯à¦¾à¦šà¦¾à¦‡ à¦•à¦°à§à¦¨ (à¦¨à¦¿à¦°à¦¾à¦ªà¦¤à§à¦¤à¦¾à¦° à¦œà¦¨à§à¦¯)
=======
            // পাসওয়ার্ড যাচাই করুন (নিরাপত্তার জন্য)
>>>>>>> temp_branch
            $password = $_POST['password'] ?? '';
            $dbUser = $userModel->findById($user['id']);

            if (!password_verify($password, $dbUser['password'])) {
                showMessage("Invalid password", "error");
                header("Location: /user/security/2fa");
                exit;
            }

            // Disable 2FA using SecurityManager
            if (!$securityManager->disable2FA($user['id'])) {
                showMessage("Failed to disable 2FA", "error");
                header("Location: /user/security/2fa");
                exit;
            }

            showMessage("2FA has been disabled", "success");
            header("Location: /user/security/2fa");
            exit;
        }
        );
    });

<<<<<<< HEAD
// ==================== à¦¸à¦¹à¦¾à¦¯à¦¼à¦• à¦«à¦¾à¦‚à¦¶à¦¨à¦—à§à¦²à¦¿ ====================

/**
 * Base32 à¦¸à¦¿à¦•à§à¦°à§‡à¦Ÿ à¦œà§‡à¦¨à¦¾à¦°à§‡à¦Ÿ à¦•à¦°à§à¦¨
 * Google Authenticator à¦à¦° à¦œà¦¨à§à¦¯ à¦‰à¦ªà¦¯à§à¦•à§à¦¤ à¦«à¦°à¦®à§à¦¯à¦¾à¦Ÿà§‡
=======
// ==================== সহায়ক ফাংশনগুলি ====================

/**
 * Base32 সিক্রেট জেনারেট করুন
 * Google Authenticator এর জন্য উপযুক্ত ফরম্যাটে
>>>>>>> temp_branch
 */
// ==================== PASSWORD CHANGE ====================

/**
 * Password Change Endpoint (AJAX/Modal)
 * POST /user/change-password
 */
$router->post('/user/change-password', ['middleware' => ['auth'], 'response' => 'json'], function () use ($userModel) {
    try {
        $userId = AuthManager::getCurrentUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // CSRF validation (supports both form field and header token)
        $csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!validateCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid request token']);
            exit;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirm'] ?? '';

        // Validate inputs
        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'All fields are required']);
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
            exit;
        }

        // Validate password strength
        if (strlen($newPassword) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
            exit;
        }

        $hasUppercase = preg_match('/[A-Z]/', $newPassword);
        $hasLowercase = preg_match('/[a-z]/', $newPassword);
        $hasNumber = preg_match('/[0-9]/', $newPassword);
        $hasSpecial = preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\]/', $newPassword);

        if (!($hasUppercase && $hasLowercase && $hasNumber && $hasSpecial)) {
            http_response_code(400);
            echo json_encode([
            'success' => false,
            'error' => 'Password must contain uppercase, lowercase, number, and special character'
            ]);
            exit;
        }

        // Get user and verify current password
        $user = $userModel->getUserById($userId);
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            logActivity("Password Change Failed - Invalid Current Password", "auth", $userId, [], 'failure');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            exit;
        }

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $result = $userModel->updateUserPassword($userId, $hashedPassword);

        if ($result) {
            logActivity("Password Changed Successfully", "auth", $userId, [], 'success');
            http_response_code(200);
            echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully'
            ]);
        }
        else {
            logActivity("Password Change Failed - Database Error", "auth", $userId, [], 'error');
            http_response_code(500);
            echo json_encode([
            'success' => false,
            'error' => 'Failed to change password'
            ]);
        }
        exit;

    }
    catch (Exception $e) {
        logActivity("Password Change Error: " . $e->getMessage(), "auth", AuthManager::getCurrentUserId(), [], 'error');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
        exit;
    }
});

// ==================== SKIP PASSWORD SETUP ====================

/**
 * Skip First-Time Password Setup
 * POST /api/auth/skip-password-setup
 */
$router->post('/api/auth/skip-password-setup', ['middleware' => ['auth'], 'response' => 'json'], function () {
    try {
        $userId = AuthManager::getCurrentUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // Clear the session flag
        unset($_SESSION['show_password_setup']);

        logActivity("Password Setup Skipped", "auth", $userId, [], 'info');

        http_response_code(200);
        echo json_encode([
        'success' => true,
        'message' => 'Password setup skipped'
        ]);
        exit;

    }
    catch (Exception $e) {
        logActivity("Skip Password Setup Error: " . $e->getMessage(), "auth", AuthManager::getCurrentUserId(), [], 'error');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
        exit;
    }
});
