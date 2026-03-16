<?php
/**
 * controllers/AppSecuritySettingsController.php
 * Super admin app security settings management routes
 */

$appSecurityModel = new AppSecuritySettingsModel($mysqli);

// ==================== APP SECURITY SETTINGS GROUP ====================
$router->group('/admin/app-settings/security', ['middleware' => ['auth', 'super_admin_only']], function ($router) use ($appSecurityModel) {

    // ==================== UPDATE SINGLE SETTING ====================
    // POST /admin/app-settings/security/update - Update one setting
    $router->post('/update', function () use ($appSecurityModel) {
            try {
                // Verify CSRF token
                $csrfToken = $_POST['csrf_token'] ?? '';
                if (!validateCsrfToken($csrfToken)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
                    exit;
                }

                $key = sanitize_input($_POST['key'] ?? '');
                $value = $_POST['value'] ?? '';

                if (empty($key)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Setting key is required']);
                    exit;
                }

                // Validate the setting
                $validation = $appSecurityModel->validateSetting($key, $value);
                if (!$validation['valid']) {
                    http_response_code(400);
                    echo json_encode([
                    'success' => false,
                    'message' => $validation['error'] ?? 'Validation failed'
                    ]);
                    exit;
                }

                // Update the setting
                $success = $appSecurityModel->updateSetting($key, $value);

                if ($success) {
                    echo json_encode([
                    'success' => true,
                    'message' => "Setting '$key' updated successfully"
                    ]);
                }
                else {
                    http_response_code(500);
                    echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update setting'
                    ]);
                }
            }
            catch (Throwable $e) {
                logError("Update Setting Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                http_response_code(500);
                echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
                ]);
            }
            exit;
        }
        );

        // ==================== BULK UPDATE SETTINGS ====================
        // POST /admin/app-settings/security/bulk-update - Update multiple settings
        $router->post('/bulk-update', function () use ($appSecurityModel) {
            try {
                // Verify CSRF token
                $csrfToken = $_POST['csrf_token'] ?? '';
                if (!validateCsrfToken($csrfToken)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
                    exit;
                }

                $settings = $_POST['settings'] ?? [];

                if (empty($settings)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'No settings provided']);
                    exit;
                }

                $cleanedSettings = [];
                foreach ($settings as $key => $value) {
                    $cleanedSettings[sanitize_input($key)] = $_POST['settings'][$key];
                }

                // Update multiple settings
                $result = $appSecurityModel->updateMultipleSettings($cleanedSettings);

                echo json_encode([
                'success' => $result['failed'] === 0,
                'updated' => $result['updated'],
                'failed' => $result['failed'],
                'total' => $result['total'],
                'message' => "Updated {$result['updated']} settings" . ($result['failed'] > 0 ? ", {$result['failed']} failed" : '')
                ]);
            }
            catch (Throwable $e) {
                logError("Bulk Update Settings Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                http_response_code(500);
                echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
                ]);
            }
            exit;
        }
        );

        // ==================== RESET TO DEFAULTS ====================
        // POST /admin/app-settings/security/reset - Reset all settings to defaults
        $router->post('/reset', function () use ($appSecurityModel) {
            try {
                // Verify CSRF token
                $csrfToken = $_POST['csrf_token'] ?? '';
                if (!validateCsrfToken($csrfToken)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
                    exit;
                }

                // Additional confirmation required
                $confirm = sanitize_input($_POST['confirm'] ?? '');
                if ($confirm !== 'RESET_ALL_SETTINGS') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Reset confirmation required']);
                    exit;
                }

                // Reset to defaults (implementation depends on your defaults)
                $success = $appSecurityModel->resetToDefaults();

                if ($success) {
                    echo json_encode([
                    'success' => true,
                    'message' => 'All settings reset to defaults'
                    ]);
                }
                else {
                    http_response_code(500);
                    echo json_encode([
                    'success' => false,
                    'message' => 'Failed to reset settings'
                    ]);
                }
            }
            catch (Throwable $e) {
                logError("Reset Settings Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                http_response_code(500);
                echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
                ]);
            }
            exit;
        }
        );

        // ==================== GET SETTING HISTORY ====================
        // GET /admin/app-settings/security/history/:key - Get setting change history
        $router->get('/history/:key', function ($key) use ($appSecurityModel) {
            try {
                $key = sanitize_input($key);
                $history = $appSecurityModel->getSettingHistory($key, 100);

                echo json_encode([
                'success' => true,
                'key' => $key,
                'history' => $history,
                'count' => count($history)
                ]);
            }
            catch (Throwable $e) {
                logError("Get History Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                http_response_code(500);
                echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
                ]);
            }
            exit;
        }
        );

        // ==================== EXPORT SETTINGS ====================
        // GET /admin/app-settings/security/export - Export all settings as JSON
        $router->get('/export', function () use ($appSecurityModel) {
            try {
                $jsonData = $appSecurityModel->exportSettings();

                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="security-settings-' . date('Y-m-d-H-i-s') . '.json"');
                echo $jsonData;
            }
            catch (Throwable $e) {
                logError("Export Settings Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Server error']);
            }
            exit;
        }
        );

        // ==================== IMPORT SETTINGS ====================
        // POST /admin/app-settings/security/import - Import settings from JSON
        $router->post('/import', function () use ($appSecurityModel) {
            try {
                // Verify CSRF token
                $csrfToken = $_POST['csrf_token'] ?? '';
                if (!validateCsrfToken($csrfToken)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
                    exit;
                }

                if (!isset($_FILES['settings_file']) || $_FILES['settings_file']['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'File upload failed']);
                    exit;
                }

                $filePath = $_FILES['settings_file']['tmp_name'];
                $jsonData = file_get_contents($filePath);

                if (!$jsonData) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Could not read file']);
                    exit;
                }

                $result = $appSecurityModel->importSettings($jsonData);

                echo json_encode($result);
            }
            catch (Throwable $e) {
                logError("Import Settings Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                http_response_code(500);
                echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
                ]);
            }
            exit;
        }
        );

        // ==================== GET SINGLE SETTING (AJAX) ====================
        // GET /admin/app-settings/security/get/:key - Get single setting value
        $router->get('/get/:key', function ($key) use ($appSecurityModel) {
            try {
                $key = sanitize_input($key);
                $setting = $appSecurityModel->getSetting($key);

                if (!$setting) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Setting not found']);
                    exit;
                }

                echo json_encode([
                'success' => true,
                'setting' => $setting
                ]);
            }
            catch (Throwable $e) {
                logError("Get Setting Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                http_response_code(500);
                echo json_encode([
                'success' => false,
                'message' => 'Server error'
                ]);
            }
            exit;
        }
        );

        // ==================== GET ALL SETTINGS (AJAX) ====================
        // GET /admin/app-settings/security/all - Get all settings
        $router->get('/all', function () use ($appSecurityModel) {
            try {
                $settings = $appSecurityModel->getAllSettings(false);

                echo json_encode([
                'success' => true,
                'settings' => $settings,
                'count' => count($settings)
                ]);
            }
            catch (Throwable $e) {
                logError("Get All Settings Error: " . $e->getMessage(), "ERROR",
                ['file' => $e->getFile(), 'line' => $e->getLine()]);
                http_response_code(500);
                echo json_encode([
                'success' => false,
                'message' => 'Server error'
                ]);
            }
            exit;
        }
        );    });
