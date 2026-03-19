<?php

global $router, $mysqli, $twig;

if (!isset($router, $mysqli, $twig)) {
    return;
}

if (!function_exists('bootstrapTelegramSystemDependencies')) {
    function bootstrapTelegramSystemDependencies(): void
    {
        static $bootstrapped = false;

        if ($bootstrapped) {
            return;
        }

        $paths = [
            BASE_PATH . 'app/FeatureFlags',
            BASE_PATH . 'app/Telegram',
            BASE_PATH . 'app/Modules/SmsGateway',
            BASE_PATH . 'app/Modules/DeviceControl',
            BASE_PATH . 'app/Modules/Scraper',
            BASE_PATH . 'app/Modules/PdfTools',
        ];

        foreach ($paths as $path) {
            if (is_dir($path) && function_exists('requireAllPhpFiles')) {
                requireAllPhpFiles($path);
            }
        }

        $bootstrapped = true;
    }
}

if (!function_exists('telegramSystemReadJsonPayload')) {
    function telegramSystemReadJsonPayload(): array
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode((string)$raw, true);
        if (!is_array($payload) || empty($payload)) {
            $payload = $_POST;
        }
        return is_array($payload) ? $payload : [];
    }
}

if (!function_exists('telegramSystemResolveDeviceToken')) {
    function telegramSystemResolveDeviceToken(array $payload): string
    {
        $headerToken = trim((string)($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? ''));
        if ($headerToken !== '') {
            return $headerToken;
        }
        return trim((string)($payload['api_token'] ?? $payload['device_token'] ?? ''));
    }
}

$router->post('/api/telegram/webhook', function () use ($mysqli) {
    bootstrapTelegramSystemDependencies();

    try {
        $controller = new \App\Telegram\WebhookController($mysqli);
        $controller->handle();
    } catch (Throwable $e) {
        logError('Telegram webhook failed: ' . $e->getMessage(), 'ERROR', [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Telegram webhook failed']);
    }
    exit;
});

// Health check endpoint for Telegram bot
$router->get('/api/telegram/health', function () use ($mysqli) {
    header('Content-Type: application/json');

    $health = [
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'checks' => []
    ];

    // Check database connection
    try {
        $result = $mysqli->query('SELECT 1');
        $health['checks']['database'] = $result !== false ? 'ok' : 'error';
    } catch (Throwable $e) {
        $health['checks']['database'] = 'error: ' . $e->getMessage();
        $health['status'] = 'degraded';
    }

    // Check Telegram sessions table
    try {
        $result = $mysqli->query("SELECT COUNT(*) as cnt FROM telegram_sessions");
        $row = $result->fetch_assoc();
        $health['checks']['sessions'] = 'ok';
        $health['checks']['active_sessions'] = (int)($row['cnt'] ?? 0);
    } catch (Throwable $e) {
        $health['checks']['sessions'] = 'error: ' . $e->getMessage();
        $health['status'] = 'degraded';
    }

    // Check rate limit table
    try {
        $result = $mysqli->query("SELECT COUNT(*) as cnt FROM telegram_rate_limit WHERE expires_at > " . time());
        $row = $result->fetch_assoc();
        $health['checks']['rate_limit'] = 'ok';
        $health['checks']['active_rate_limits'] = (int)($row['cnt'] ?? 0);
    } catch (Throwable $e) {
        $health['checks']['rate_limit'] = 'error: ' . $e->getMessage();
        $health['status'] = 'degraded';
    }

    // Check bot token configuration
    $settings = new \AppSettings($mysqli);
    $botToken = $settings->get('telegram_bot_token', '');
    $health['checks']['bot_configured'] = !empty($botToken) ? 'ok' : 'not_configured';

    // Check webhook URL
    $webhookSecret = $settings->get('telegram_webhook_secret', '');
    $health['checks']['webhook_secret'] = !empty($webhookSecret) ? 'configured' : 'not_configured';

    // Feature flags
    require_once BASE_PATH . 'app/FeatureFlags/FeatureManager.php';
    $featureManager = \App\FeatureFlags\FeatureManager::getInstance($mysqli);
    $health['checks']['feature_telegram_panel'] = $featureManager->isEnabled('telegram_panel') ? 'enabled' : 'disabled';
    $health['checks']['feature_sms_gateway'] = $featureManager->isEnabled('sms_gateway') ? 'enabled' : 'disabled';
    $health['checks']['feature_remote_device'] = $featureManager->isEnabled('remote_device') ? 'enabled' : 'disabled';

    // Determine overall status
    $hasError = false;
    foreach ($health['checks'] as $key => $value) {
        if (is_string($value) && strpos($value, 'error') === 0) {
            $hasError = true;
            break;
        }
    }

    if ($hasError) {
        $health['status'] = 'degraded';
        http_response_code(503);
    } else {
        http_response_code(200);
    }

    echo json_encode($health);
});

$router->post('/api/sms/incoming', function () use ($mysqli) {
    bootstrapTelegramSystemDependencies();

    header('Content-Type: application/json');

    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);

    if (!is_array($payload) || empty($payload)) {
        $payload = $_POST;
    }

    if (!is_array($payload) || empty($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    $requiredFields = ['from', 'message', 'device_id'];
    foreach ($requiredFields as $field) {
        if (!isset($payload[$field]) || $payload[$field] === '') {
            http_response_code(400);
            echo json_encode(['error' => "Missing field: {$field}"]);
            exit;
        }
    }

    try {
        $listener = new \App\Modules\SmsGateway\IncomingSmsListener($mysqli);
        $listener->handleRequest($payload);

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $isFeatureDisabled = stripos($message, 'disabled') !== false;

        logError('Incoming SMS webhook failed: ' . $message, 'ERROR', [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        http_response_code($isFeatureDisabled ? 403 : 500);
        echo json_encode([
            'error' => $isFeatureDisabled ? 'Feature disabled' : 'Incoming SMS webhook failed',
        ]);
    }
    exit;
});

$router->post('/api/device-control/heartbeat', function () use ($mysqli) {
    bootstrapTelegramSystemDependencies();
    header('Content-Type: application/json');

    $payload = telegramSystemReadJsonPayload();
    $token = telegramSystemResolveDeviceToken($payload);
    if ($token === '') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Missing device token']);
        exit;
    }

    try {
        $service = new \App\Modules\DeviceControl\DeviceControlService($mysqli);
        $result = $service->updateDeviceHeartbeat($token, $payload);
        http_response_code(!empty($result['success']) ? 200 : 401);
        echo json_encode($result);
    } catch (Throwable $e) {
        logError('Device heartbeat failed: ' . $e->getMessage(), 'ERROR', [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Device heartbeat failed']);
    }
    exit;
});

$router->post('/api/device-control/commands/pull', function () use ($mysqli) {
    bootstrapTelegramSystemDependencies();
    header('Content-Type: application/json');

    $payload = telegramSystemReadJsonPayload();
    $token = telegramSystemResolveDeviceToken($payload);
    if ($token === '') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Missing device token', 'commands' => []]);
        exit;
    }

    $limit = (int)($payload['limit'] ?? 10);

    try {
        $service = new \App\Modules\DeviceControl\DeviceControlService($mysqli);
        $result = $service->pullPendingCommands($token, $limit);
        http_response_code(!empty($result['success']) ? 200 : 401);
        echo json_encode($result);
    } catch (Throwable $e) {
        logError('Device command pull failed: ' . $e->getMessage(), 'ERROR', [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Device command pull failed', 'commands' => []]);
    }
    exit;
});

$router->post('/api/device-control/commands/result', function () use ($mysqli) {
    bootstrapTelegramSystemDependencies();
    header('Content-Type: application/json');

    $payload = telegramSystemReadJsonPayload();
    $token = telegramSystemResolveDeviceToken($payload);
    if ($token === '') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Missing device token']);
        exit;
    }

    $commandId = (int)($payload['command_id'] ?? 0);
    if ($commandId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid command_id']);
        exit;
    }

    $success = filter_var($payload['success'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $responseText = trim((string)($payload['response_text'] ?? $payload['message'] ?? ''));

    try {
        $service = new \App\Modules\DeviceControl\DeviceControlService($mysqli);
        $result = $service->reportCommandResult($token, $commandId, $success === true, $responseText);
        http_response_code(!empty($result['success']) ? 200 : 401);
        echo json_encode($result);
    } catch (Throwable $e) {
        logError('Device command result failed: ' . $e->getMessage(), 'ERROR', [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Device command result failed']);
    }
    exit;
});

$router->get('/admin/feature-flags', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli, $twig) {
    $features = [];
    $result = $mysqli->query('SELECT id, feature_key, enabled, super_admin_only FROM feature_flags ORDER BY feature_key ASC');

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $features[] = $row;
        }
    }

    echo $twig->render('admin/settings/feature_flags.twig', [
        'title' => 'Feature Flags',
        'current_page' => 'feature-flags',
        'features' => $features,
        'csrf_token' => generateCsrfToken(),
    ]);
});

$router->post('/admin/feature-flags/toggle', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showMessage('Invalid request token.', 'danger');
        redirect('/admin/feature-flags');
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        showMessage('Invalid feature identifier.', 'danger');
        redirect('/admin/feature-flags');
    }

    $stmt = $mysqli->prepare('UPDATE feature_flags SET enabled = NOT enabled WHERE id = ? LIMIT 1');
    if (!$stmt) {
        showMessage('Failed to prepare feature toggle query.', 'danger');
        redirect('/admin/feature-flags');
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($ok && $affected > 0) {
        showMessage('Feature flag updated successfully.', 'success');
    } else {
        showMessage('Feature toggle failed or no changes applied.', 'warning');
    }

    redirect('/admin/feature-flags');
});

$router->get('/admin/sms-logs', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli, $twig) {
    $logs = [];
    $sql = "SELECT
                l.*,
                COALESCE(d.device_name, CONCAT('Device #', COALESCE(l.device_id, 0))) AS device_name
            FROM sms_logs l
            LEFT JOIN devices d ON d.id = l.device_id
            ORDER BY l.created_at DESC
            LIMIT 200";

    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }

    echo $twig->render('admin/logs/sms.twig', [
        'title' => 'SMS Logs',
        'current_page' => 'sms-logs',
        'logs' => $logs,
    ]);
});

$router->get('/admin/device-control', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli, $twig) {
    bootstrapTelegramSystemDependencies();

    $service = new \App\Modules\DeviceControl\DeviceControlService($mysqli);
    $limit = max(20, min(500, (int)($_GET['limit'] ?? 200)));
    $commands = $service->getRecentCommands($limit);
    $summary = $service->getDeviceSummary();

    echo $twig->render('admin/logs/device_control.twig', [
        'title' => 'Device Control Logs',
        'current_page' => 'device-control',
        'commands' => $commands,
        'summary' => $summary,
        'limit' => $limit,
        'csrf_token' => generateCsrfToken(),
    ]);
});
