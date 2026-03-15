<?php
<<<<<<< HEAD
=======

>>>>>>> temp_branch
/**
 * Settings Controller
 * 
 * Handles all application settings management routes
 * Includes settings display, updates, email templates, and API endpoints
 */

declare(strict_types=1);

global $mysqli;


<<<<<<< HEAD
=======
// ========= SETTINGS MANAGEMENT - Redirect /admin/settings to /admin/app-settings =========

/**
 * Redirect /admin/settings to /admin/app-settings
 */
$router->get('/admin/settings', function () {
    header('Location: /admin/app-settings', true, 302);
    exit;
});


>>>>>>> temp_branch
// ========= SETTINGS MANAGEMENT - View & Edit ==========

/**
 * Show app settings page (GET)
 * Display all application settings organized by category
 */
$router->get('/admin/app-settings', ['middleware' => ['auth', 'super_admin_only']], function () use ($twig, $mysqli) {
    $settingsModel = new AppSettings($mysqli);
    $appSecurityModel = new AppSecuritySettingsModel($mysqli);
<<<<<<< HEAD
    
=======

>>>>>>> temp_branch
    // Fetch all settings
    $settings = $settingsModel->getAll();
    $publicNavItems = $settingsModel->getPublicNavItems($settings, false);
    $publicNavRows = brox_prepare_public_nav_rows($publicNavItems, 8);
    $publicNavColumnAvailable = array_key_exists('public_nav_json', $settings);

    // Generate timezones dynamically
    $timezones = [];
    foreach (DateTimeZone::listIdentifiers() as $tz) {
        $timezone = new DateTimeZone($tz);
        $dt = new DateTime('now', $timezone);
        $offset = $timezone->getOffset($dt);
        $hours = intdiv($offset, 3600);
        $minutes = abs(($offset % 3600) / 60);
        $sign = $hours >= 0 ? '+' : '-';
        $timezones[$tz] = sprintf('(UTC%s%02d:%02d) %s', $sign, abs($hours), $minutes, $tz);
    }

    // Define supported languages
    $languages = [
        'en' => 'English',
        'bn' => 'Bengali (à¦¬à¦¾à¦‚à¦²à¦¾)',
        'hi' => 'Hindi (à¤¹à¤¿à¤¨à¥à¤¦à¥€)',
        'es' => 'Spanish (EspaÃ±ol)',
        'fr' => 'French (FranÃ§ais)',
        'de' => 'German (Deutsch)',
    ];

    brox_ensure_branding_assets_dir();

    $settings['site_logo'] = brox_normalize_branding_asset_path((string)($settings['site_logo'] ?? ''));
    $settings['favicon'] = brox_normalize_branding_asset_path((string)($settings['favicon'] ?? ''));

    // Set defaults for logo & favicon if not set
    $uploadsLogoPrefix = brox_get_uploads_base_url() . '/logo/';
    $settings['site_logo'] = $settings['site_logo'] !== '' ? $settings['site_logo'] : $uploadsLogoPrefix . 'logo.png';
    $settings['favicon'] = $settings['favicon'] !== '' ? $settings['favicon'] : $uploadsLogoPrefix . 'favicon.ico';

    $settingsByCategory = $appSecurityModel->getSettingsByCategory();
    $categoryLabels = [
        'login_security' => 'Login Security',
        'password_policy' => 'Password Policy',
        'email_verification' => 'Email Verification',
        'two_factor_auth' => 'Two-Factor Authentication',
        'session_management' => 'Session Management',
        'oauth_providers' => 'OAuth Providers',
        'ip_geo_blocking' => 'IP & Geo Blocking'
    ];
    $require2FAForAdmin = (bool)($appSecurityModel->getSettingValue('require_2fa_for_admin', false));
    $defaultPaymentMethodList = [
        ['key' => 'bkash', 'label' => 'bKash'],
        ['key' => 'nagad', 'label' => 'Nagad'],
        ['key' => 'rocket', 'label' => 'Rocket'],
    ];
    $disallowedPaymentMethodKeys = ['bank_transfer', 'card', 'cash', 'other_gateway'];
    $paymentMethodListRaw = $appSecurityModel->getSettingValue('service_payment_methods', $defaultPaymentMethodList);
    $paymentMethodListRaw = is_array($paymentMethodListRaw) ? $paymentMethodListRaw : [];
    $paymentMethodList = [];
    $seenPaymentMethodKeys = [];
    foreach ($paymentMethodListRaw as $row) {
        if (is_string($row)) {
            $keyRaw = $row;
            $labelRaw = $row;
        } elseif (is_array($row)) {
            $keyRaw = (string)($row['key'] ?? '');
            $labelRaw = (string)($row['label'] ?? '');
        } else {
            continue;
        }

        $methodKey = strtolower(trim($keyRaw));
        $methodKey = preg_replace('/[^a-z0-9_]+/', '_', $methodKey);
        $methodKey = trim((string)$methodKey, '_');
        if (
            $methodKey === '' ||
            isset($seenPaymentMethodKeys[$methodKey]) ||
            in_array($methodKey, $disallowedPaymentMethodKeys, true)
        ) {
            continue;
        }

        $methodLabel = trim($labelRaw);
        if ($methodLabel === '') {
            $methodLabel = ucwords(str_replace('_', ' ', $methodKey));
        }

        $seenPaymentMethodKeys[$methodKey] = true;
        $paymentMethodList[] = ['key' => $methodKey, 'label' => $methodLabel];
    }
    if (empty($paymentMethodList)) {
        $paymentMethodList = $defaultPaymentMethodList;
    }

    $manualPaymentMethods = $appSecurityModel->getSettingValue('service_manual_payment_methods', []);
    $manualPaymentMethods = is_array($manualPaymentMethods) ? $manualPaymentMethods : [];
    $normalizedManualPaymentMethods = [];
    foreach ($paymentMethodList as $method) {
        $methodKey = $method['key'];
        $row = is_array($manualPaymentMethods[$methodKey] ?? null) ? $manualPaymentMethods[$methodKey] : [];
        $normalizedManualPaymentMethods[$methodKey] = [
            'receiver_number' => trim((string)($row['receiver_number'] ?? '')),
            'message' => trim((string)($row['message'] ?? '')),
        ];
    }

    // Render the merged app settings page
<<<<<<< HEAD
    echo $twig->render('admin/app-settings.twig', [
=======
    echo $twig->render('admin/settings/app.twig', [
>>>>>>> temp_branch
        'settings' => $settings,
        'timezones' => $timezones,
        'languages' => $languages,
        'settings_by_category' => $settingsByCategory,
        'category_labels' => $categoryLabels,
        'require_2fa_for_admin' => $require2FAForAdmin,
        'page_title' => 'Application Settings',
        'current_page' => 'app-settings',
        'csrf_token' => generateCsrfToken(),
        'public_nav_rows' => $publicNavRows,
        'public_nav_column_available' => $publicNavColumnAvailable,
        'payment_method_list' => $paymentMethodList,
        'manual_payment_methods' => $normalizedManualPaymentMethods,
    ]);
});


/**
 * Update app settings (POST)
 * Validate and save updated settings
 */
$router->post('/admin/app-settings', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli) {
    $settingsModel = new AppSettings($mysqli);
    $appSecurityModel = new AppSecuritySettingsModel($mysqli);
<<<<<<< HEAD
    
=======

>>>>>>> temp_branch
    try {
        $errors = [];
        $warnings = [];

        // Validate required fields
        if (empty($_POST['site_name'])) {
            $errors[] = "Site name is required";
        }

        if (!empty($_POST['admin_email']) && !filter_var($_POST['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid admin email address";
        }

        if (!empty($_POST['mail_from_address']) && !filter_var($_POST['mail_from_address'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid 'from' email address";
        }

        if (!empty($_POST['contact_email']) && !filter_var($_POST['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid contact email address";
        }

        if (!empty($_POST['smtp_port']) && (!is_numeric($_POST['smtp_port']) || $_POST['smtp_port'] < 0 || $_POST['smtp_port'] > 65535)) {
            $errors[] = "SMTP port must be between 0 and 65535";
        }

        if (!empty($_POST['cache_lifetime']) && (!is_numeric($_POST['cache_lifetime']) || $_POST['cache_lifetime'] < 1)) {
            $errors[] = "Cache lifetime must be a positive number";
        }

        if (!empty($_POST['max_login_attempts']) && (!is_numeric($_POST['max_login_attempts']) || $_POST['max_login_attempts'] < 1)) {
            $errors[] = "Max login attempts must be a positive number";
        }

        if (!empty($errors)) {
            showMessage(implode(" | ", $errors), "danger");
            header('Location: /admin/app-settings');
            exit;
        }

        brox_ensure_branding_assets_dir();

        // Handle logo upload (accept either URL in `site_logo` or uploaded file `site_logo_file`)
        $logo_path = brox_normalize_branding_asset_path((string)sanitize_input($_POST['site_logo'] ?? ''));
        $existingSettings = $settingsModel->getAll();
        $publicNavColumnAvailable = array_key_exists('public_nav_json', $existingSettings);
        $oldLogo = brox_normalize_branding_asset_path((string)($existingSettings['site_logo'] ?? ''));

        // Handle explicit remove-site-logo request
        if (!empty($_POST['remove_site_logo'])) {
            brox_delete_branding_file($oldLogo);
            $logo_path = '';
        }

        if (!empty($_FILES['site_logo_file']['tmp_name']) && ($_FILES['site_logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $result = brox_store_branding_upload($_FILES['site_logo_file'], 'logo');
            if (!empty($result['success'])) {
                $logo_path = brox_normalize_branding_asset_path((string)$result['url']);
                brox_delete_branding_file($oldLogo);
            } else {
                $warnings[] = "Logo upload failed: " . ($result['error'] ?? 'Unknown');
            }
        }

        // Handle favicon upload (file field name: favicon_file)
        $favicon_path = brox_normalize_branding_asset_path((string)sanitize_input($_POST['favicon'] ?? ''));
        $oldFavicon = brox_normalize_branding_asset_path((string)($existingSettings['favicon'] ?? ''));
        if (!empty($_FILES['favicon_file']['tmp_name']) && ($_FILES['favicon_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $result = brox_store_branding_upload($_FILES['favicon_file'], 'favicon');
            if (!empty($result['success'])) {
                $favicon_path = brox_normalize_branding_asset_path((string)$result['url']);
                brox_delete_branding_file($oldFavicon);
            } else {
                $warnings[] = "Favicon upload failed: " . ($result['error'] ?? 'Unknown');
            }
        }

        // Prepare data for update
        $data = [
            // General
            'site_name'          => htmlspecialchars($_POST['site_name'] ?? ''),
            'site_logo'          => $logo_path,
            'favicon'            => $favicon_path,
            'default_language'   => sanitize_input($_POST['default_language'] ?? 'en'),
            'timezone'           => sanitize_input($_POST['timezone'] ?? 'UTC'),
            'maintenance_mode'   => isset($_POST['maintenance_mode']) ? 1 : 0,

            // Contact
            'contact_email'      => sanitize_input($_POST['contact_email'] ?? ''),
            'contact_phone'      => sanitize_input($_POST['contact_phone'] ?? ''),
            'contact_address'    => sanitize_input($_POST['contact_address'] ?? ''),

            // SEO
            'meta_title'         => htmlspecialchars($_POST['meta_title'] ?? ''),
            'meta_description'   => htmlspecialchars($_POST['meta_description'] ?? ''),
            'meta_keywords'      => htmlspecialchars($_POST['meta_keywords'] ?? ''),

            // Social Media
            'social_facebook'    => sanitize_input($_POST['social_facebook'] ?? ''),
            'social_twitter'     => sanitize_input($_POST['social_twitter'] ?? ''),
            'social_instagram'   => sanitize_input($_POST['social_instagram'] ?? ''),
            'social_youtube'     => sanitize_input($_POST['social_youtube'] ?? ''),

            // Email/SMTP
            'smtp_host'          => sanitize_input($_POST['smtp_host'] ?? ''),
            'smtp_port'          => intval($_POST['smtp_port'] ?? 587),
            'smtp_username'      => sanitize_input($_POST['smtp_username'] ?? ''),
            'smtp_encryption'    => sanitize_input($_POST['smtp_encryption'] ?? 'tls'),
            'mail_from_address'  => sanitize_input($_POST['mail_from_address'] ?? ''),
            'mail_from_name'     => sanitize_input($_POST['mail_from_name'] ?? ''),

            // User Management
            'allow_user_registration'    => isset($_POST['allow_user_registration']) ? 1 : 0,
            'require_email_verification' => isset($_POST['require_email_verification']) ? 1 : 0,
            'enable_2fa'                 => isset($_POST['enable_2fa']) ? 1 : 0,
            'max_login_attempts'         => intval($_POST['max_login_attempts'] ?? 5),

            // Payment & Currency
            'currency_code'      => sanitize_input($_POST['currency_code'] ?? 'USD'),
            'currency_symbol'    => sanitize_input($_POST['currency_symbol'] ?? '$'),
            'payment_gateway'    => sanitize_input($_POST['payment_gateway'] ?? ''),
            'payment_mode'       => sanitize_input($_POST['payment_mode'] ?? 'sandbox'),

            // Analytics & Security
            'google_analytics_id' => sanitize_input($_POST['google_analytics_id'] ?? ''),
            'recaptcha_site_key' => sanitize_input($_POST['recaptcha_site_key'] ?? ''),

            // Cache
            'enable_cache'       => isset($_POST['enable_cache']) ? 1 : 0,
            'cache_driver'       => sanitize_input($_POST['cache_driver'] ?? 'file'),
            'cache_lifetime'     => intval($_POST['cache_lifetime'] ?? 3600),
        ];

        // SMTP password - only update if provided
        if (!empty($_POST['smtp_password'])) {
            $data['smtp_password'] = sanitize_input($_POST['smtp_password']);
        }

        // reCAPTCHA secret - only update if provided
        if (!empty($_POST['recaptcha_secret_key'])) {
            $data['recaptcha_secret_key'] = sanitize_input($_POST['recaptcha_secret_key']);
        }

        $publicNavSubmission = brox_collect_public_nav_items_from_post($_POST, 8);
        if ($publicNavSubmission['submitted']) {
            if (!$publicNavColumnAvailable) {
                $warnings[] = "Public header menu was not saved because public_nav_json column is missing.";
            } elseif (!empty($publicNavSubmission['errors'])) {
                $warnings[] = "Public header menu was not updated. " . implode(' ', $publicNavSubmission['errors']);
            } else {
                $encodedNav = json_encode(
                    $publicNavSubmission['items'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                if ($encodedNav === false) {
                    $warnings[] = "Public header menu was not updated due to JSON encoding error.";
                } else {
                    $data['public_nav_json'] = $encodedNav;
                }
            }
        }

        if ($settingsModel->update($data)) {
            $defaultPaymentMethodList = [
                ['key' => 'bkash', 'label' => 'bKash'],
                ['key' => 'nagad', 'label' => 'Nagad'],
                ['key' => 'rocket', 'label' => 'Rocket'],
            ];
            $disallowedPaymentMethodKeys = ['bank_transfer', 'card', 'cash', 'other_gateway'];
            $submittedMethodKeys = isset($_POST['service_payment_method_key']) && is_array($_POST['service_payment_method_key'])
                ? $_POST['service_payment_method_key']
                : [];
            $submittedMethodLabels = isset($_POST['service_payment_method_label']) && is_array($_POST['service_payment_method_label'])
                ? $_POST['service_payment_method_label']
                : [];
            $submittedReceiverNumbers = isset($_POST['service_payment_receiver_number']) && is_array($_POST['service_payment_receiver_number'])
                ? $_POST['service_payment_receiver_number']
                : [];
            $submittedMessages = isset($_POST['service_payment_message']) && is_array($_POST['service_payment_message'])
                ? $_POST['service_payment_message']
                : [];

            $paymentMethodList = [];
            $manualPaymentMethodsConfig = [];
            $seenPaymentMethodKeys = [];
            $rowCount = max(
                count($submittedMethodKeys),
                count($submittedMethodLabels),
                count($submittedReceiverNumbers),
                count($submittedMessages)
            );

            for ($i = 0; $i < $rowCount; $i++) {
                $methodKeyRaw = trim((string)sanitize_input($submittedMethodKeys[$i] ?? ''));
                $methodLabelRaw = trim((string)sanitize_input($submittedMethodLabels[$i] ?? ''));
                if ($methodKeyRaw === '' && $methodLabelRaw !== '') {
                    $methodKeyRaw = $methodLabelRaw;
                }

                $methodKey = strtolower($methodKeyRaw);
                $methodKey = preg_replace('/[^a-z0-9_]+/', '_', $methodKey);
                $methodKey = trim((string)$methodKey, '_');
                if (
                    $methodKey === '' ||
                    isset($seenPaymentMethodKeys[$methodKey]) ||
                    in_array($methodKey, $disallowedPaymentMethodKeys, true)
                ) {
                    continue;
                }

                $methodLabel = $methodLabelRaw !== '' ? $methodLabelRaw : ucwords(str_replace('_', ' ', $methodKey));
                $receiverNumber = trim((string)sanitize_input($submittedReceiverNumbers[$i] ?? ''));
                $instructionMessage = trim((string)sanitize_input($submittedMessages[$i] ?? ''));

                $seenPaymentMethodKeys[$methodKey] = true;
                $paymentMethodList[] = [
                    'key' => $methodKey,
                    'label' => $methodLabel,
                ];
                $manualPaymentMethodsConfig[$methodKey] = [
                    'receiver_number' => $receiverNumber,
                    'message' => $instructionMessage,
                ];
            }

            if (empty($paymentMethodList)) {
                $paymentMethodList = $defaultPaymentMethodList;
                foreach ($defaultPaymentMethodList as $method) {
                    $manualPaymentMethodsConfig[$method['key']] = ['receiver_number' => '', 'message' => ''];
                }
                $warnings[] = "No valid payment method rows found. Default methods were restored.";
            }

            $paymentMethodSettingKey = 'service_payment_methods';
            $manualPaymentSettingKey = 'service_manual_payment_methods';
            $existingMethodListSetting = $appSecurityModel->getSetting($paymentMethodSettingKey);
            $methodListSaved = false;
            if ($existingMethodListSetting) {
                $methodListSaved = $appSecurityModel->updateSetting(
                    $paymentMethodSettingKey,
                    $paymentMethodList,
                    'json'
                );
            } else {
                $methodListSaved = $appSecurityModel->createSetting(
                    $paymentMethodSettingKey,
                    $paymentMethodList,
                    'json',
                    'Configurable service payment methods',
                    false
                );
            }

            if (!$methodListSaved) {
                $warnings[] = "Payment method list was not saved.";
            }

            $existingPaymentSetting = $appSecurityModel->getSetting($manualPaymentSettingKey);
            $manualConfigSaved = false;
            if ($existingPaymentSetting) {
                $manualConfigSaved = $appSecurityModel->updateSetting(
                    $manualPaymentSettingKey,
                    $manualPaymentMethodsConfig,
                    'json'
                );
            } else {
                $manualConfigSaved = $appSecurityModel->createSetting(
                    $manualPaymentSettingKey,
                    $manualPaymentMethodsConfig,
                    'json',
                    'Manual payment method-wise receiver number and instruction message',
                    false
                );
            }

            if (!$manualConfigSaved) {
                $warnings[] = "Method-wise manual payment instructions were not saved.";
            }

            // Send admin notification for settings update
            $currentUserId = (int)(AuthManager::getCurrentUserId() ?? ($_SESSION["user_id"] ?? 0));
            if ($currentUserId > 0) {
                $notificationModel = new NotificationModel($mysqli);
                $notifId = $notificationModel->create(
                    $currentUserId,
                    "Settings Updated",
                    "Application settings were updated successfully.",
                    "update",
                    [
                        "user_id" => $currentUserId,
                        "channels" => ["push", "in_app", "email"],
                        "action_url" => "/admin/app-settings",
                    ]
                );

                if ($notifId) {
                    $notificationModel->logDelivery($notifId, $currentUserId, "sent", null, null, "admin_settings", "in_app");
                }
            }
<<<<<<< HEAD
            
=======

>>>>>>> temp_branch
            $successMessage = "Settings updated successfully";
            if (!empty($warnings)) {
                $successMessage .= " | " . implode(" | ", $warnings);
            }
            showMessage($successMessage, "success");
        } else {
            showMessage("Failed to update settings. Please try again.", "danger");
        }

        header('Location: /admin/app-settings');
        exit;
<<<<<<< HEAD

    } catch (Throwable $e) {
        logError("Settings Update Error: " . $e->getMessage(), "ERROR", 
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        logError(
            "Settings Update Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        showMessage("An error occurred while updating settings", "danger");
        header('Location: /admin/app-settings');
        exit;
    }
});


/**
 * Send test email (POST)
 * Send a test email to verify SMTP configuration
 */
$router->post('/admin/app-settings/send-test-email', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli) {
    $settingsModel = new AppSettings($mysqli);
<<<<<<< HEAD
    
=======

>>>>>>> temp_branch
    try {
        $settings = $settingsModel->getAll();
        $to = trim($_POST['test_email'] ?? '');

        if (empty($to)) {
            showMessage("Email address is required", "danger");
            header('Location: /admin/app-settings');
            exit;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            showMessage("Invalid email address", "danger");
            header('Location: /admin/app-settings');
            exit;
        }

        $siteName = $settings['site_name'] ?? 'Application';
        $subject = sprintf('Test Email from %s', $siteName);
        $body = '<p>This is a test email sent from the application to verify SMTP settings.</p>';

        $sent = sendMail($to, $subject, $body);

        if ($sent) {
            showMessage('Test email sent successfully to ' . htmlspecialchars($to), 'success');
        } else {
            showMessage('Failed to send test email. Please check SMTP settings.', 'danger');
        }

        header('Location: /admin/app-settings');
        exit;
    } catch (Throwable $e) {
<<<<<<< HEAD
        logError('Send Test Email Error: ' . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
        logError(
            'Send Test Email Error: ' . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        showMessage('An error occurred while sending test email.', 'danger');
        header('Location: /admin/app-settings');
        exit;
    }
});


/**
 * Send test email (AJAX JSON)
 * Returns JSON response for test email requests
 */
$router->post('/admin/app-settings/send-test-email-ajax', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
<<<<<<< HEAD
    
=======

>>>>>>> temp_branch
    $settingsModel = new AppSettings($mysqli);

    try {
        $settings = $settingsModel->getAll();
        $to = trim($_POST['test_email'] ?? '');

        if (empty($to)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email address is required']);
            exit;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }

        $siteName = $settings['site_name'] ?? 'Application';
        $subject = sprintf('Test Email from %s', $siteName);
        $body = '<p>This is a test email sent from the application to verify SMTP settings.</p>';

        $sent = sendMail($to, $subject, $body);

        if ($sent) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Test email sent successfully to ' . $to]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to send test email. Please check SMTP settings.']);
        }
<<<<<<< HEAD

    } catch (Throwable $e) {
        http_response_code(500);
        logError('Send Test Email AJAX Error: ' . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        http_response_code(500);
        logError(
            'Send Test Email AJAX Error: ' . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        echo json_encode(['success' => false, 'message' => 'An error occurred while sending test email.']);
    }
});


// ========= SETTINGS API - JSON Endpoints ==========

/**
 * Get all settings (API)
 * GET /api/settings
 */
$router->get('/api/settings', function () use ($mysqli) {
    header('Content-Type: application/json');
<<<<<<< HEAD
    
=======

>>>>>>> temp_branch
    try {
        $settingsModel = new AppSettings($mysqli);
        $settings = $settingsModel->getAll();

        // Mask sensitive fields
        $sensitive_fields = ['smtp_password', 'recaptcha_secret_key'];
        foreach ($sensitive_fields as $field) {
            if (isset($settings[$field]) && !empty($settings[$field])) {
                $settings[$field] = '***HIDDEN***';
            }
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $settings,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
<<<<<<< HEAD

    } catch (Throwable $e) {
        http_response_code(500);
        logError('API Settings Error: ' . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        http_response_code(500);
        logError(
            'API Settings Error: ' . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve settings']);
    }
});

/**
 * Get specific setting (API)
 * GET /api/settings/{key}
 */
$router->get('/api/settings/:key', function ($key) use ($mysqli) {
    header('Content-Type: application/json');
<<<<<<< HEAD
    
=======

>>>>>>> temp_branch
    try {
        $settingsModel = new AppSettings($mysqli);
        $value = $settingsModel->get($key);

        if ($value === null) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Setting not found',
                'key' => htmlspecialchars($key)
            ]);
        } else {
            // Mask sensitive fields
            if (in_array($key, ['smtp_password', 'recaptcha_secret_key']) && !empty($value)) {
                $value = '***HIDDEN***';
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'key' => htmlspecialchars($key),
                'value' => $value,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
<<<<<<< HEAD

    } catch (Throwable $e) {
        http_response_code(500);
        logError('API Get Setting Error: ' . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        http_response_code(500);
        logError(
            'API Get Setting Error: ' . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve setting']);
    }
});

/**
 * Update specific setting (API)
 * POST /api/settings/{key}
 * Requires: super_admin_only middleware
 */
$router->post('/api/settings/:key', ['middleware' => ['auth', 'super_admin_only']], function ($key) use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['value'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Value is required']);
            exit;
        }

        $settingsModel = new AppSettings($mysqli);

        if ($settingsModel->set($key, $data['value'])) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Setting updated successfully',
                'key' => htmlspecialchars($key),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update setting']);
        }
<<<<<<< HEAD

    } catch (Throwable $e) {
        http_response_code(500);
        logError('API Update Setting Error: ' . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        http_response_code(500);
        logError(
            'API Update Setting Error: ' . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
    }
});


// ========= EMAIL TEMPLATES MANAGEMENT ==========

/**
 * Show email templates list page
 * GET /admin/email-templates
 */
$router->get('/admin/email-templates', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $emailTemplate = new EmailTemplate($mysqli);
        $templates = $emailTemplate->getAll();

        echo $twig->render('admin/email-templates/list.twig', [
            'templates' => $templates,
            'page_title' => 'Email Templates'
        ]);
<<<<<<< HEAD

    } catch (Throwable $e) {
        logError('Email Templates List Error: ' . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        logError(
            'Email Templates List Error: ' . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        showMessage("Failed to load email templates", "danger");
    }
});

/**
 * Show email template edit form
 * GET /admin/email-templates/{id}/edit
 */
$router->get('/admin/email-templates/{id}/edit', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $emailTemplate = new EmailTemplate($mysqli);
        $template = $emailTemplate->getById((int)$id);

        if (!$template) {
            showMessage("Email template not found", "danger");
            header('Location: /admin/email-templates');
            exit;
        }

        // Parse variables JSON
        $variables = [];
        if (!empty($template['variables'])) {
            if (is_string($template['variables'])) {
                $variables = json_decode($template['variables'], true) ?? [];
            } elseif (is_array($template['variables'])) {
                $variables = $template['variables'];
            }
        }

        echo $twig->render('admin/email-templates/edit.twig', [
            'template' => $template,
            'variables' => $variables,
            'page_title' => 'Edit Email Template: ' . $template['name']
        ]);
<<<<<<< HEAD

    } catch (Throwable $e) {
        logError('Email Template Edit Error: ' . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        logError(
            'Email Template Edit Error: ' . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        showMessage("Failed to load email template", "danger");
        header('Location: /admin/email-templates');
        exit;
    }
});

/**
 * Update email template
 * POST /admin/email-templates/{id}
 */
$router->post('/admin/email-templates/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    try {
        $emailTemplate = new EmailTemplate($mysqli);
        $template = $emailTemplate->getById((int)$id);

        if (!$template) {
            showMessage("Email template not found", "danger");
            header('Location: /admin/email-templates');
            exit;
        }

        // Validate inputs
        $errors = [];

        if (empty($_POST['subject'])) {
            $errors[] = "Subject is required";
        }

        if (empty($_POST['body'])) {
            $errors[] = "Body is required";
        }

        if (!empty($errors)) {
            showMessage(implode(" | ", $errors), "danger");
            header('Location: /admin/email-templates/' . $id . '/edit');
            exit;
        }

        // Parse variables from body (find {{VARIABLE}} patterns)
        $pattern = '/\{\{([A-Z_]+)\}\}/';
        preg_match_all($pattern, $_POST['body'], $matches);
        $variables = [];
        if (!empty($matches[1])) {
            $variables = array_unique($matches[1]);
            $variables = array_combine($variables, array_fill(0, count($variables), ''));
        }

        // Update template
        $updateData = [
            'subject' => htmlspecialchars($_POST['subject']),
            'body' => $_POST['body'],
            'variables' => !empty($variables) ? json_encode($variables) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($emailTemplate->update((int)$id, $updateData)) {
            showMessage("Email template updated successfully", "success");
        } else {
            showMessage("Failed to update email template", "danger");
        }

        header('Location: /admin/email-templates/' . $id . '/edit');
        exit;
<<<<<<< HEAD

    } catch (Throwable $e) {
        logError("Email Template Update Error: " . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        logError(
            "Email Template Update Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        showMessage("An error occurred while updating template", "danger");
        header('Location: /admin/email-templates');
        exit;
    }
});

/**
 * Preview email template (AJAX)
 * POST /admin/email-templates/{id}/preview
 */
$router->post('/admin/email-templates/{id}/preview', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $emailTemplate = new EmailTemplate($mysqli);
        $template = $emailTemplate->getById((int)$id);

        if (!$template) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Template not found']);
            exit;
        }

        // Get variables from POST
        $variables = [];
        if (!empty($_POST['variables'])) {
            $vars = json_decode($_POST['variables'], true);
            if (is_array($vars)) {
                $variables = array_map('sanitize_input', $vars);
            }
        }

        // Parse variables from body if not provided
        if (empty($variables) && !empty($template['variables'])) {
            $vars = $template['variables'];
            if (is_string($vars)) {
                $vars = json_decode($vars, true);
            }
            if (is_array($vars)) {
                foreach (array_keys($vars) as $var) {
                    $variables[$var] = "Sample " . str_replace('_', ' ', strtolower($var));
                }
            }
        }

        // Render subject and body
        $subject = $emailTemplate->renderSubject($template['slug'], $variables);
        $body = $emailTemplate->render($template['slug'], $variables);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'subject' => $subject,
            'body' => $body,
            'variables' => $variables
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
<<<<<<< HEAD

    } catch (Throwable $e) {
        http_response_code(500);
        logError("Email Template Preview Error: " . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        http_response_code(500);
        logError(
            "Email Template Preview Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        echo json_encode(['success' => false, 'error' => 'Failed to preview template: ' . $e->getMessage()]);
    }
});

/**
 * Delete email template
 * POST /admin/email-templates/{id}/delete
 */
$router->post('/admin/email-templates/{id}/delete', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $emailTemplate = new EmailTemplate($mysqli);
        $template = $emailTemplate->getById((int)$id);

        if (!$template) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Template not found']);
            exit;
        }

        // Don't allow deletion of core templates
        $coreTemplates = ['welcome_email', 'email_verification', 'password_reset'];
        if (in_array($template['slug'], $coreTemplates)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Core templates cannot be deleted']);
            exit;
        }

        if ($emailTemplate->delete((int)$id)) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Email template deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete email template']);
        }
<<<<<<< HEAD

    } catch (Throwable $e) {
        http_response_code(500);
        logError("Email Template Delete Error: " . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        http_response_code(500);
        logError(
            "Email Template Delete Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
    }
});


// ========= USER ACCOUNT SETTINGS ==========

/**
 * User Account Settings & OAuth Management
 * GET /user/settings
 */
<<<<<<< HEAD
$router->get('/user/settings', ['middleware'=>['auth']], function () use ($twig, $mysqli) {
    try {
        $userId = AuthManager::getCurrentUserId();
        
        $userModel = new UserModel($mysqli);
        
        // Get user profile
        $user = $userModel->getUserById($userId);
        
=======
$router->get('/user/settings', ['middleware' => ['auth']], function () use ($twig, $mysqli) {
    try {
        $userId = AuthManager::getCurrentUserId();

        $userModel = new UserModel($mysqli);

        // Get user profile
        $user = $userModel->getUserById($userId);

>>>>>>> temp_branch
        if (!$user) {
            showMessage("User not found", "danger");
            header('Location: /');
            exit;
        }
<<<<<<< HEAD
        
        // Check if user has password set
        $userHasPassword = $userModel->userHasPassword($userId);
        
        // Check if needs first-time password setup
        $showPasswordSetup = $userModel->needsFirstTimePasswordSetup($userId);
        
=======

        // Check if user has password set
        $userHasPassword = $userModel->userHasPassword($userId);

        // Check if needs first-time password setup
        $showPasswordSetup = $userModel->needsFirstTimePasswordSetup($userId);

>>>>>>> temp_branch
        echo $twig->render('user/settings.twig', [
            'title'            => 'Account Settings',
            'user_data'             => $user,
            'user_has_password' => $userHasPassword,
            'show_password_setup' => $showPasswordSetup,
            'csrf_token'       => generateCsrfToken(),
            'current_page'     => 'account',
            'current_tab'      => $_GET['tab'] ?? 'account',
        ]);
<<<<<<< HEAD
        
    } catch (Throwable $e) {
        logError("Account Settings Error: " . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        logError(
            "Account Settings Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        showMessage("Failed to load account settings", "danger");
        header('Location: /');
        exit;
    }
});


// ========= ADMIN ACCOUNT SETTINGS ==========

/**
 * Admin Account Settings Page
 * GET /admin/account-settings
 * Display account security, password management, and linked OAuth accounts
 */
$router->get('/admin/account-settings', ['middleware' => ['auth', 'admin_or_super_only']], function () use ($twig, $mysqli) {
    try {
        $userId = AuthManager::getCurrentUserId();
<<<<<<< HEAD
        
=======

>>>>>>> temp_branch
        if (!$userId) {
            header('Location: /login');
            exit;
        }
<<<<<<< HEAD
        
        $userModel = new UserModel($mysqli);
        $securityManager = new SecurityManager($mysqli);
        
        // Get current user details
        $user = AuthManager::getCurrentUserArray();
        
=======

        $userModel = new UserModel($mysqli);
        $securityManager = new SecurityManager($mysqli);

        // Get current user details
        $user = AuthManager::getCurrentUserArray();

>>>>>>> temp_branch
        if (!$user) {
            showMessage("User not found", "error");
            header('Location: /admin/dashboard');
            exit;
        }
<<<<<<< HEAD
        
        // Check if user has password set
        $userHasPassword = !empty($user['password']);
        
        // Get 2FA status
        $twoFAStatus = $securityManager->get2FAStatus($userId);
        
        // Render the account settings page
        echo $twig->render('admin/account-settings.twig', [
=======

        // Check if user has password set
        $userHasPassword = !empty($user['password']);

        // Get 2FA status
        $twoFAStatus = $securityManager->get2FAStatus($userId);

        // Render the account settings page
        echo $twig->render('admin/settings/account.twig', [
>>>>>>> temp_branch
            'title' => 'Account Settings',
            'page_title' => 'Account Settings',
            'user' => $user,
            'user_has_password' => $userHasPassword,
            'twofa_enabled' => $twoFAStatus['enabled'],
            'csrf_token' => generateCsrfToken(),
            'current_page' => 'account-settings',
        ]);
<<<<<<< HEAD
        
    } catch (Throwable $e) {
        logError("Admin Account Settings Error: " . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
=======
    } catch (Throwable $e) {
        logError(
            "Admin Account Settings Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
>>>>>>> temp_branch
        showMessage("Failed to load account settings", "danger");
        header('Location: /admin/dashboard');
        exit;
    }
});
<<<<<<< HEAD

=======
>>>>>>> temp_branch
