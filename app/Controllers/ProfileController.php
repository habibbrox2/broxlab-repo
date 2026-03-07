<?php
// controllers/ProfileController.php

global $mysqli;

$userModel = new UserModel($mysqli);

$normalizeDob = static function (?string $raw): ?string {
    $value = trim((string)($raw ?? ''));
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d', 'd-m-Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            $warningCount = is_array($errors) ? (int)($errors['warning_count'] ?? 0) : 0;
            $errorCount = is_array($errors) ? (int)($errors['error_count'] ?? 0) : 0;
            if ($warningCount === 0 && $errorCount === 0) {
                return $date->format('Y-m-d');
            }
        }
    }

    return null;
};

$normalizeGender = static function (?string $raw): ?string {
    $value = strtolower(trim((string)($raw ?? '')));
    return in_array($value, ['male', 'female', 'other'], true) ? $value : null;
};

$normalizeOptionalUrl = static function (?string $raw): array {
    $value = trim((string)($raw ?? ''));
    if ($value === '') {
        return [true, ''];
    }

    $sanitized = filter_var($value, FILTER_SANITIZE_URL);
    if (!is_string($sanitized) || $sanitized === '') {
        return [false, ''];
    }

    if (filter_var($sanitized, FILTER_VALIDATE_URL) === false) {
        return [false, ''];
    }

    return [true, $sanitized];
};

$removeOldProfileImageIfLocal = static function (?string $oldPic): void {
    $raw = trim((string)($oldPic ?? ''));
    if ($raw === '') {
        return;
    }

    $parsedPath = parse_url($raw, PHP_URL_PATH);
    $path = is_string($parsedPath) ? $parsedPath : $raw;
    $path = trim($path);
    if ($path === '') {
        return;
    }

    if (!defined('UPLOADS_DIR')) {
        return;
    }

    $uploadsBaseUrl = defined('UPLOADS_PUBLIC_URL') ? '/' . trim((string)UPLOADS_PUBLIC_URL, '/') : '/uploads';

    $uploadsRootPath = (string)UPLOADS_DIR;
    $uploadsRoot = realpath($uploadsRootPath);
    if ($uploadsRoot === false) {
        return;
    }

    $allowedDirs = [];
    $profileDir = realpath($uploadsRoot . '/profile');
    if ($profileDir !== false) {
        $allowedDirs[] = $profileDir;
    }
    $profilesDir = realpath($uploadsRoot . '/profiles');
    if ($profilesDir !== false) {
        $allowedDirs[] = $profilesDir;
    }

    if (empty($allowedDirs)) {
        return;
    }

    $candidates = [];

    if (strpos($path, $uploadsBaseUrl . '/profile/') === 0 || strpos($path, $uploadsBaseUrl . '/profiles/') === 0) {
        $relativePath = ltrim(substr($path, strlen($uploadsBaseUrl . '/')), '/');
        $candidates[] = rtrim($uploadsRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }
    elseif (strpos($path, ltrim($uploadsBaseUrl, '/') . '/profile/') === 0 || strpos($path, ltrim($uploadsBaseUrl, '/') . '/profiles/') === 0) {
        $relativePath = ltrim(substr($path, strlen(ltrim($uploadsBaseUrl, '/') . '/')), '/');
        $candidates[] = rtrim($uploadsRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }
    elseif (strpos($path, '/') === false) {
        $file = basename($path);
        foreach ($allowedDirs as $dir) {
            $candidates[] = $dir . DIRECTORY_SEPARATOR . $file;
        }
    }
    else {
        return;
    }

    foreach ($candidates as $candidatePath) {
        if (!is_string($candidatePath) || $candidatePath === '' || !file_exists($candidatePath)) {
            continue;
        }

        $realCandidate = realpath($candidatePath);
        if ($realCandidate === false) {
            continue;
        }

        foreach ($allowedDirs as $allowedDir) {
            if (strpos($realCandidate, $allowedDir . DIRECTORY_SEPARATOR) === 0) {
                @unlink($realCandidate);
                return;
            }
        }
    }
};

// ------------------ USER PROFILE GROUP ------------------
$router->group('/profile', ['middleware' => ['auth']], function ($router) use ($twig, $userModel, $mysqli, $normalizeDob, $normalizeGender, $normalizeOptionalUrl, $removeOldProfileImageIfLocal
) {
    // View profile
    $router->get('', function () use ($twig) {
            $user = AuthManager::getCurrentUserArray();
            echo $twig->render('user/profile.twig', [
            'title' => 'Your Profile',
            'header_title' => 'Profile Details',
            'user' => $user,
            ]);
        }
        );

        // Edit profile form
        $router->get('/edit', function () use ($twig) {
            $user = AuthManager::getCurrentUserArray();
            echo $twig->render('user/profile_edit.twig', [
            'title' => 'Edit Profile',
            'header_title' => 'Update Your Information',
            'user' => $user,
            'csrf_token' => generateCsrfToken(),
            ]);
        }
        );

        // Update profile
        $router->post('/edit', function () use ($mysqli, $userModel, $normalizeDob, $normalizeGender, $normalizeOptionalUrl, $removeOldProfileImageIfLocal) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                showMessage("Invalid CSRF token", "error");
                header("Location: /profile/edit");
                exit;
            }

            $user = AuthManager::getCurrentUserArray();
            if (!$user) {
                showMessage("User not found", "error");
                header("Location: /profile/edit");
                exit;
            }

            $data = [
                'username' => sanitize_input($_POST['username'] ?? ''),
                'email' => sanitize_input($_POST['email'] ?? ''),
                'first_name' => sanitize_input($_POST['first_name'] ?? ''),
                'last_name' => sanitize_input($_POST['last_name'] ?? ''),
                'gender' => $normalizeGender($_POST['gender'] ?? null),
                'dob' => $normalizeDob($_POST['dob'] ?? null),
                'phone' => sanitize_input($_POST['phone'] ?? ''),
                'alternate_phone' => sanitize_input($_POST['alternate_phone'] ?? ''),
                'address' => sanitize_input($_POST['address'] ?? ''),
                'city' => sanitize_input($_POST['city'] ?? ''),
                'state' => sanitize_input($_POST['state'] ?? ''),
                'country' => sanitize_input($_POST['country'] ?? ''),
                'zipcode' => sanitize_input($_POST['zipcode'] ?? ''),
            ];

            $socialInputs = [
                'facebook_url' => $_POST['facebook_url'] ?? ($_POST['facebook'] ?? ''),
                'twitter_url' => $_POST['twitter_url'] ?? ($_POST['twitter'] ?? ''),
                'instagram_url' => $_POST['instagram_url'] ?? ($_POST['instagram'] ?? ''),
                'linkedin_url' => $_POST['linkedin_url'] ?? ($_POST['linkedin'] ?? ''),
            ];

            foreach ($socialInputs as $field => $rawUrl) {
                [$isValidUrl, $normalizedUrl] = $normalizeOptionalUrl($rawUrl);
                if (!$isValidUrl) {
                    $fieldLabel = ucwords(str_replace('_', ' ', str_replace('_url', '', $field)));
                    showMessage("Invalid {$fieldLabel} URL", "error");
                    header("Location: /profile/edit");
                    exit;
                }
                $data[$field] = $normalizedUrl;
            }

            // Reserved usernames
            $reservedUsernames = [
                'admin', 'administrator', 'root', 'superadmin', 'sysadmin',
                'system', 'operator', 'support', 'owner', 'master',
                'admin1', 'admin01', 'admin123', 'admin2024', 'admin2025',
                'root1', 'root123', 'superuser', 'supervisor', 'manager1',
                'cmsadmin', 'siteadmin', 'webadmin', 'portaladmin', 'mainadmin',
                'control', 'dashboard', 'panel', 'controlpanel', 'moderator',
                'manager', 'usermanager', 'itadmin', 'dbadmin', 'netadmin',
                'devadmin', 'sysop', 'hostmaster', 'webmaster', 'security',
                'john_admin', 'alice_admin', 'mike_admin', 'admin_mary', 'super_jane',
                'testadmin', 'demo_admin', 'qa_admin', 'defaultadmin', 'guestadmin',
                'service', 'rootadmin', 'adm', 'adm1', 'systemadmin'
            ];

            if (in_array(strtolower($data['username']), $reservedUsernames, true)) {
                showMessage("This username is not allowed. Please choose another.", "error");
                header("Location: /profile/edit");
                exit;
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                showMessage("Invalid email address", "error");
                header("Location: /profile/edit");
                exit;
            }

            if ($data['username'] !== (string)$user['username']) {
                $existingUser = $userModel->findByUsernameOrEmail($data['username']);
                if ($existingUser && (int)$existingUser['id'] !== (int)$user['id']) {
                    showMessage("Username already taken", "error");
                    header("Location: /profile/edit");
                    exit;
                }
            }

            if ($data['email'] !== (string)$user['email']) {
                $existingEmail = $userModel->findByUsernameOrEmail($data['email']);
                if ($existingEmail && (int)$existingEmail['id'] !== (int)$user['id']) {
                    showMessage("Email already in use", "error");
                    header("Location: /profile/edit");
                    exit;
                }
            }

            // Handle profile picture
            $oldPic = $user['profile_pic'] ?? null;
            if (!empty($_FILES['profile_pic']['tmp_name'])) {
                $uploadService = new UploadService($mysqli);
                $result = $uploadService->upload($_FILES['profile_pic'], 'profiles', [
                    'user_id' => (int)$user['id']
                ]);

                if (!$result['success']) {
                    showMessage($result['error'] ?? 'Profile image upload failed', "error");
                    header("Location: /profile/edit");
                    exit;
                }

                $data['profile_pic'] = $result['url'] ?? '';
                $removeOldProfileImageIfLocal($oldPic);
            }

            $updated = $userModel->updateUser((int)$user['id'], $data);

            if ($updated) {
                $currentUserId = (int)$user['id'];
                $notificationModel = new NotificationModel($mysqli);
                $notifId = $notificationModel->create(
                    $currentUserId,
                    'Profile Updated',
                    'Your profile was updated successfully.',
                    'update',
                [
                    'user_id' => $currentUserId,
                    'channels' => ['push', 'in_app', 'email']
                ]
                );
                if ($notifId) {
                    $notificationModel->logDelivery(
                        $notifId,
                        $currentUserId,
                        'sent',
                        null,
                        'profile_update',
                        'system',
                        'profile'
                    );
                }

                logActivity(
                    "User Profile Updated",
                    "user",
                    (int)$user['id'],
                ['username' => $data['username'], 'email' => $data['email']],
                    'success'
                );
                showMessage("Profile updated successfully", "success");
                header("Location: /profile/edit");
                exit;
            }

            logActivity(
                "User Profile Update Failed",
                "user",
                (int)$user['id'],
            ['username' => $data['username']],
                'failure'
            );
            showMessage("Failed to update profile", "error");
            header("Location: /profile/edit");
            exit;
        }
        );

        // Legacy compatibility route -> canonical user security route
        $router->get('/2fa', function () {
            header("Location: /user/security/2fa", true, 302);
            exit;
        }
        );

        // Change password
        $router->get('/password', function () use ($twig) {
            echo $twig->render('user/profile_password.twig', [
            'title' => 'Change Password',
            'header_title' => 'Update Password',
            'csrf_token' => generateCsrfToken(),
            ]);
        }
        );

        $router->post('/password', function () use ($userModel) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                showMessage("Invalid CSRF token", "error");
                header("Location: /profile/password");
                exit;
            }

            $user = AuthManager::getCurrentUserArray();
            if (!$user) {
                showMessage("User not found", "error");
                header("Location: /profile/password");
                exit;
            }

            $current = sanitize_input($_POST['current_password'] ?? '');
            $new = sanitize_input($_POST['new_password'] ?? '');
            $confirm = sanitize_input($_POST['confirm_password'] ?? '');

            if ($new !== $confirm) {
                showMessage("New password and confirmation do not match", "error");
                header("Location: /profile/password");
                exit;
            }

            $dbUser = $userModel->findById((int)$user['id']);
            if (!$dbUser || !password_verify($current, $dbUser['password'])) {
                showMessage("Current password is incorrect", "error");
                header("Location: /profile/password");
                exit;
            }

            $userModel->updateUser((int)$user['id'], ['password' => password_hash($new, PASSWORD_DEFAULT)]);
            showMessage("Password updated successfully", "success");
            header('Location: /profile/password');
            exit;
        }
        );    });

// ------------------ ADMIN PROFILE GROUP ------------------
$router->group('/admin/profile', ['middleware' => ['auth', 'admin_or_super_only']], function ($router) use ($twig, $userModel, $mysqli, $normalizeDob, $normalizeGender, $normalizeOptionalUrl, $removeOldProfileImageIfLocal
) {
    $router->get('', function () use ($twig, $userModel) {
            $user = AuthManager::getCurrentUserArray();
            $userId = (int)($user['id'] ?? 0);

            // Load fresh profile to ensure roles/primary role reflect DB state.
            if ($userId > 0) {
                $profile = $userModel->loadUserById($userId);
                if ($profile) {
                    // Prefer DB profile fields (role/roles/is_super_admin) over stale session values.
                    $user = array_merge($user, $profile);
                }
            }

            echo $twig->render('admin/profile_admin.twig', [
            'title' => 'Admin Profile',
            'header_title' => 'Admin Profile Details',
            'user' => $user,
            ]);
        }
        );

        $router->get('/edit', function () use ($twig) {
            $user = AuthManager::getCurrentUserArray();
            echo $twig->render('admin/profile_edit.twig', [
            'title' => 'Edit Admin Profile',
            'header_title' => 'Update Admin Info',
            'user' => $user,
            'csrf_token' => generateCsrfToken(),
            ]);
        }
        );

        $router->post('/edit', function () use ($mysqli, $userModel, $normalizeDob, $normalizeGender, $normalizeOptionalUrl, $removeOldProfileImageIfLocal) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                showMessage("Invalid CSRF token", "error");
                header("Location: /admin/profile/edit");
                exit;
            }

            $user = AuthManager::getCurrentUserArray();
            if (!$user) {
                showMessage("Admin not found", "error");
                header("Location: /admin/profile/edit");
                exit;
            }

            $data = [
                'username' => sanitize_input($_POST['username'] ?? ''),
                'email' => sanitize_input($_POST['email'] ?? ''),
                'first_name' => sanitize_input($_POST['first_name'] ?? ''),
                'last_name' => sanitize_input($_POST['last_name'] ?? ''),
                'gender' => $normalizeGender($_POST['gender'] ?? null),
                'dob' => $normalizeDob($_POST['dob'] ?? null),
                'phone' => sanitize_input($_POST['phone'] ?? ''),
                'alternate_phone' => sanitize_input($_POST['alternate_phone'] ?? ''),
                'address' => sanitize_input($_POST['address'] ?? ''),
                'city' => sanitize_input($_POST['city'] ?? ''),
                'state' => sanitize_input($_POST['state'] ?? ''),
                'country' => sanitize_input($_POST['country'] ?? ''),
                'zipcode' => sanitize_input($_POST['zipcode'] ?? ''),
            ];

            $socialInputs = [
                'facebook_url' => $_POST['facebook_url'] ?? ($_POST['facebook'] ?? ''),
                'twitter_url' => $_POST['twitter_url'] ?? ($_POST['twitter'] ?? ''),
                'instagram_url' => $_POST['instagram_url'] ?? ($_POST['instagram'] ?? ''),
                'linkedin_url' => $_POST['linkedin_url'] ?? ($_POST['linkedin'] ?? ''),
            ];

            foreach ($socialInputs as $field => $rawUrl) {
                [$isValidUrl, $normalizedUrl] = $normalizeOptionalUrl($rawUrl);
                if (!$isValidUrl) {
                    $fieldLabel = ucwords(str_replace('_', ' ', str_replace('_url', '', $field)));
                    showMessage("Invalid {$fieldLabel} URL", "error");
                    header("Location: /admin/profile/edit");
                    exit;
                }
                $data[$field] = $normalizedUrl;
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                showMessage("Invalid email address", "error");
                header("Location: /admin/profile/edit");
                exit;
            }

            if ($data['username'] !== (string)$user['username']) {
                $existingUser = $userModel->findByUsernameOrEmail($data['username']);
                if ($existingUser && (int)$existingUser['id'] !== (int)$user['id']) {
                    showMessage("Username already taken", "error");
                    header("Location: /admin/profile/edit");
                    exit;
                }
            }

            if ($data['email'] !== (string)$user['email']) {
                $existingEmail = $userModel->findByUsernameOrEmail($data['email']);
                if ($existingEmail && (int)$existingEmail['id'] !== (int)$user['id']) {
                    showMessage("Email already in use", "error");
                    header("Location: /admin/profile/edit");
                    exit;
                }
            }

            $oldPic = $user['profile_pic'] ?? null;
            if (!empty($_FILES['profile_pic']['tmp_name'])) {
                $uploadService = new UploadService($mysqli);
                $result = $uploadService->upload($_FILES['profile_pic'], 'profiles', [
                    'user_id' => (int)$user['id']
                ]);

                if (!$result['success']) {
                    showMessage($result['error'] ?? 'Profile image upload failed', "error");
                    header("Location: /admin/profile/edit");
                    exit;
                }

                $data['profile_pic'] = $result['url'] ?? '';
                $removeOldProfileImageIfLocal($oldPic);
            }

            $updated = $userModel->updateUser((int)$user['id'], $data);
            if ($updated) {
                showMessage("Profile updated successfully", "success");
                header("Location: /admin/profile/edit");
                exit;
            }

            showMessage("Failed to update profile", "error");
            header("Location: /admin/profile/edit");
            exit;
        }
        );

        // Legacy compatibility route -> canonical admin security route
        $router->get('/2fa', function () {
            header("Location: /admin/security/2fa", true, 302);
            exit;
        }
        );

        $router->get('/password', function () use ($twig) {
            echo $twig->render('admin/profile_password.twig', [
            'title' => 'Change Admin Password',
            'header_title' => 'Update Password',
            'csrf_token' => generateCsrfToken(),
            ]);
        }
        );

        $router->post('/password', function () use ($userModel) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                showMessage("Invalid CSRF token", "error");
                header("Location: /admin/profile/password");
                exit;
            }

            $user = AuthManager::getCurrentUserArray();
            if (!$user) {
                showMessage("Admin not found", "error");
                header("Location: /admin/profile/password");
                exit;
            }

            $current = sanitize_input($_POST['current_password'] ?? '');
            $new = sanitize_input($_POST['new_password'] ?? '');
            $confirm = sanitize_input($_POST['confirm_password'] ?? '');

            if ($new !== $confirm) {
                showMessage("New password and confirmation do not match", "error");
                header("Location: /admin/profile/password");
                exit;
            }

            $dbUser = $userModel->findById((int)$user['id']);
            if (!$dbUser || !password_verify($current, $dbUser['password'])) {
                showMessage("Current password is incorrect", "error");
                header("Location: /admin/profile/password");
                exit;
            }

            $userModel->updateUser((int)$user['id'], ['password' => password_hash($new, PASSWORD_DEFAULT)]);
            showMessage("Admin password updated successfully", "success");
            header('Location: /admin/profile/password');
            exit;
        }
        );    });
