<?php

declare(strict_types=1);
// AI system routes – moved from AISystemChatController for centralisation.

require_once __DIR__ . '/../Modules/AISystem/AgentClient.php';
require_once __DIR__ . '/../Helpers/PromptLoader.php';
require_once __DIR__ . '/../Models/AIFeedback.php';
require_once __DIR__ . '/../Helpers/JsonResponse.php';
require_once __DIR__ . '/../Models/ContactModel.php';
require_once __DIR__ . '/../Models/AppSettings.php';
require_once __DIR__ . '/../Models/EmailTemplate.php';
require_once __DIR__ . '/../Helpers/FirebaseHelper.php';
$aiProviderPath = realpath(__DIR__ . '/../Models/AIProvider.php');
require_once $aiProviderPath ?: (__DIR__ . '/../Models/AIProvider.php');

// OCR Integration for AI Assistant
require_once __DIR__ . '/../Helpers/OCRService.php';
require_once __DIR__ . '/../Helpers/NodeOCRClient.php';
$ocrService = new OCRService();
$nodeOCRClient = new NodeOCRClient(getenv('OCR_API_URL') ?: 'http://localhost:3000/api/ocr');

// Centralize AI assistant/coplay endpoints here. We still reuse shared handler functions
// (aiChatHandleRequest, aiChatSendJson, aiChatStreamContent, ...) from AISystemController,
// but we prevent that controller from registering overlapping /api/* routes.
if (!defined('BROX_AI_API_ROUTES_HANDLED')) {
    define('BROX_AI_API_ROUTES_HANDLED', true);
}
require_once __DIR__ . '/../Controllers/AISystemController.php';

/**
 * Get OpenRouter API key from database settings or environment.
 * 
 * @return string|null API key or null if not configured
 */
function getOpenRouterApiKey(): ?string
{
    global $mysqli;

    // Try to get from database first
    $aiProvider = new AIProvider($mysqli);
    $key = $aiProvider->getSetting('openrouter_api_key', '');

    if (!empty($key)) {
        return $key;
    }

    // Fallback to environment variable
    $envKey = getenv('OPENROUTER_API_KEY');
    if (!empty($envKey)) {
        return $envKey;
    }

    return null;
}

/** @var \Router $router */
/** @var \mysqli $mysqli */
/** @var \Twig\Environment $twig */

// Public chat support endpoint – CSRF protected
$router->post('/api/public-chat/support', ['middleware' => ['csrf']], function () use ($mysqli) {
    $contactModel = new ContactModel($mysqli);
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    if (empty($message)) {
        jsonResponse(['success' => false, 'error' => 'Message is required']);
        return;
    }

    if ((empty($name) || empty($contact)) && AuthManager::isUserAuthenticated()) {
        $user = AuthManager::getCurrentUserArray();
        if (empty($name)) {
            $name = $user['full_name'] ?? $user['username'] ?? '';
        }
        if (empty($contact)) {
            $contact = $user['email'] ?? '';
        }
    }

    if (empty($name) || empty($contact)) {
        jsonResponse(['success' => false, 'error' => 'Name or contact missing']);
        return;
    }

    $subject = 'Support Request (Public Chat)';
    $contactId = $contactModel->createMessage($name, $contact, $subject, $message, $ip);

    if ($contactId) {
        logActivity("Contact Message Submitted", "contact", $contactId, ['name' => $name, 'email' => $contact, 'subject' => $subject], 'success');

        $settingsModel = new AppSettings($mysqli);
        $appSettings = $settingsModel->getSettings();
        $emailTemplate = new EmailTemplate($mysqli);
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $userAckSubject = $emailTemplate->renderSubject('contact_acknowledgment', [
                'SUBJECT' => $subject,
                'APP_NAME' => 'BroxBhai'
            ]);
            $userAckBody = $emailTemplate->render('contact_acknowledgment', [
                'USER_NAME' => $name,
                'USER_EMAIL' => $contact,
                'SUBJECT' => $subject,
                'APP_NAME' => 'BroxBhai'
            ]);
            if (!empty(trim($userAckBody))) {
                sendEmail($contact, $userAckSubject, $userAckBody, $name);
            }
        }
        if (!empty($appSettings['contact_email'])) {
            $adminSubject = $emailTemplate->renderSubject('admin_contact_notification', [
                'SUBJECT' => $subject,
                'APP_NAME' => 'BroxBhai'
            ]);
            $adminBody = $emailTemplate->render('admin_contact_notification', [
                'FROM_NAME' => $name,
                'FROM_EMAIL' => $contact,
                'SUBJECT' => $subject,
                'MESSAGE' => $message,
                'IP_ADDRESS' => $ip,
                'APP_NAME' => 'BroxBhai'
            ]);
            sendEmail($appSettings['contact_email'], $adminSubject, $adminBody);
        }
        $adminIds = $contactModel->getAdminUserIds();
        if (!empty($adminIds)) {
            $notificationTitle = "উপেন যোগাযোগ বার্তা";
            $notificationBody = "$name (" . substr($contact, 0, 15) . "...) एকটা বার্তা পাঠাএং: \"$subject\"";
            sendNotiAdmin(
                $mysqli,
                $adminIds,
                $notificationTitle,
                $notificationBody,
                null,
                ['action_url' => '/admin/contact', 'message_id' => $contactId],
                ['push']
            );
        }
        jsonResponse(['success' => true, 'id' => $contactId]);
    } else {
        jsonResponse(['success' => false, 'error' => 'Failed to save message']);
    }
});

// Chat endpoint – CSRF protected
$router->post('/api/chat', ['middleware' => ['csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? '';
    $provider = (!empty($input['provider'])) ? $input['provider'] : null;
    $model = (!empty($input['model'])) ? $input['model'] : null;
    $context = $input['context'] ?? 'public';
    $visitorToken = $input['visitor_token'] ?? null;

    if (!$message) {
        jsonResponse(["error" => "No message provided"]);
        return;
    }

    // Audit log: Public chat request
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    logActivity(
        "Public AI Chat Request",
        "ai_chat",
        null,
        [
            'visitor_token' => $visitorToken,
            'message_length' => strlen($message),
            'provider' => $provider,
            'model' => $model,
            'ip' => $ip
        ],
        'info'
    );

    $agent = new AgentClient($mysqli);
    $systemPrompt = PromptLoader::getSystemPrompt($context, $mysqli);
    $kbContext = PromptLoader::getKnowledgeContext($message, $mysqli);
    if ($kbContext) {
        $systemPrompt .= "\n\n" . $kbContext;
    }
    $messages = [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user", "content" => $message]
    ];
    $extraContext = [];
    if (isset($input['context']) && is_array($input['context'])) {
        $extraContext = $input['context'];
    }
    $stream = isset($input['stream']) ? (bool)$input['stream'] : false;
    $response = $agent->chat($messages, $provider, $model, $extraContext, $stream);

    // Audit log: Public chat response
    if (isset($response['error'])) {
        logActivity(
            "Public AI Chat Error",
            "ai_chat",
            null,
            [
                'visitor_token' => $visitorToken,
                'error' => $response['error'],
                'ip' => $ip
            ],
            'warning'
        );
    }

    jsonResponse($response);
});

// Model list endpoint
$router->get('/api/ai/models/list', function () use ($mysqli) {
    $agent = new AgentClient($mysqli);
    $provider = $_GET['provider'] ?? 'openrouter';
    // Use reflection to access private getAvailableModels method
    $reflection = new ReflectionClass($agent);
    $getAvailableModels = $reflection->getMethod('getAvailableModels');
    $getAvailableModels->setAccessible(true);
    $models = $getAvailableModels->invoke($agent, $provider);
    jsonResponse([
        'success' => true,
        'provider' => $provider,
        'models' => $models,
        'cache_stats' => $agent->getCacheStats()
    ]);
});

// Model info endpoint
$router->get('/api/ai/models/info', function () use ($mysqli) {
    $agent = new AgentClient($mysqli);
    $provider = $_GET['provider'] ?? 'openrouter';
    $modelName = $_GET['model'] ?? '';
    if (empty($modelName)) {
        jsonResponse(['success' => false, 'error' => 'Model name is required']);
        return;
    }
    $result = $agent->getModelInfo($provider, $modelName);
    jsonResponse($result);
});

// Cache clear endpoint – CSRF protected
$router->post('/api/ai/cache/clear', ['middleware' => ['csrf']], function () use ($mysqli) {
    $type = $_POST['type'] ?? 'all';
    $agent = new AgentClient($mysqli);
    switch ($type) {
        case 'models':
            $agent->clearProviderCache($_POST['provider'] ?? 'openrouter');
            break;
        case 'chat':
        case 'all':
        default:
            $agent->clearAllCache();
    }
    jsonResponse(['success' => true, 'message' => 'Cache cleared successfully']);
});

// Cache stats endpoint
$router->get('/api/ai/cache/stats', function () use ($mysqli) {
    $agent = new AgentClient($mysqli);
    $stats = $agent->getCacheStats();
    jsonResponse(['success' => true, 'stats' => $stats]);
});

// AI test endpoint – CSRF protected
$router->post('/api/ai/test', ['middleware' => ['csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $provider = $input['provider'] ?? 'openrouter';
    $model = $input['model'] ?? null;
    $agent = new AgentClient($mysqli);
    // Access private aiProvider via reflection (no public method)
    $reflection = new ReflectionClass($agent);
    $prop = $reflection->getProperty('aiProvider');
    $prop->setAccessible(true);
    $aiProvider = $prop->getValue($agent);
    $result = $aiProvider->testConnection($provider, $model);
    jsonResponse($result);
});

// ==================== Assistant Chat APIs (Canonical) ====================

// POST /api/ai/chat (Public assistant) – CSRF required + SSE streaming supported
$router->post('/api/ai/chat', function () use ($mysqli) {
    run_middleware('rate_limit', [
        'scope' => 'ai_public_chat',
        'limit' => 30,
        'window' => 60,
        'is_api' => true
    ]);

    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    // CSRF required – accept from body or header
    $csrfToken = (string)($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!function_exists('validateCsrfToken') || !validateCsrfToken($csrfToken)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Invalid CSRF token',
            'error_code' => 'csrf_token_invalid'
        ]);
        return;
    }

    aiChatHandleRequest($input, $mysqli, false, false);
});

// POST /api/admin/ai/chat (Admin-only) – SSE streaming supported
$router->post('/api/admin/ai/chat', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    aiChatHandleRequest($input, $mysqli, true, true);
});

// POST /api/ai-system/chat (Legacy alias for admin)
$router->post('/api/ai-system/chat', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    aiChatHandleRequest($input, $mysqli, true, true);
});

// POST /api/admin/ai/upload (Admin-only image upload for copilot)
$router->post('/api/admin/ai/upload', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        aiChatSendJson(['success' => false, 'error' => 'No file uploaded'], 400);
        return;
    }
    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        aiChatSendJson(['success' => false, 'error' => 'Upload failed'], 400);
        return;
    }

    if (!class_exists('UploadService')) {
        aiChatSendJson(['success' => false, 'error' => 'Upload service unavailable'], 500);
        return;
    }

    $userId = 0;
    if (isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    } elseif (!empty($_SESSION['auth_user_id'])) {
        $userId = (int)$_SESSION['auth_user_id'];
    }

    $uploadService = new UploadService($mysqli, $userId);
    $result = $uploadService->upload($file, 'ai_upload', ['preserve_name' => true]);
    if (empty($result['success'])) {
        aiChatSendJson(['success' => false, 'error' => $result['error'] ?? 'Upload failed'], 400);
        return;
    }

    aiChatSendJson([
        'success' => true,
        'url' => $result['url'] ?? '',
        'size' => $result['size'] ?? ($file['size'] ?? 0),
        'mime' => $file['type'] ?? ''
    ]);
});

// POST /api/ai/clear-image-context
$router->post('/api/ai/clear-image-context', function () {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $sessionKey = null;
    if (!empty($input['visitorToken'])) {
        $sessionKey = 'visitor_' . (string)$input['visitorToken'];
    } else {
        $userId = AuthManager::getCurrentUserId() ?? ($_SESSION['user_id'] ?? null);
        if ($userId) {
            $sessionKey = 'user_' . (int)$userId;
        }
    }

    if ($sessionKey && isset($_SESSION['ai_image_context'][$sessionKey])) {
        unset($_SESSION['ai_image_context'][$sessionKey]);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
});

// GET /api/ai-system/frontend
$router->get('/api/ai-system/frontend', function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $settings = $aiProvider->getSettings();

    $openrouterDbKey = $settings['openrouter_api_key'] ?? '';
    $openrouterKeySource = !empty($openrouterDbKey) ? 'db' : 'none';

    $frontendProvider = $settings['frontend_provider'] ?? 'openrouter';
    if ($frontendProvider === 'puter-js' || $frontendProvider === 'puter') {
        $frontendProvider = 'openrouter';
    }

    $providers = $aiProvider->getActive();
    $defaultModel = aiSystemResolveModel(
        $aiProvider,
        $frontendProvider,
        (string)($settings['frontend_model'] ?? ''),
        $providers,
        (string)($settings['default_model'] ?? '')
    );

    $backendProvider = $settings['backend_provider'] ?? $frontendProvider;
    $backendModel = aiSystemResolveModel(
        $aiProvider,
        $backendProvider,
        (string)($settings['backend_model'] ?? ''),
        $providers,
        (string)($settings['default_model'] ?? '')
    );

    $providerList = [];
    foreach ($providers as $p) {
        $providerName = $p['provider_name'];
        $providerList[] = [
            'provider_name' => $providerName,
            'display_name' => $p['display_name'],
            'has_api_key' => !empty($settings[$providerName . '_api_key'] ?? ''),
            'models' => $p['supported_models'] ?? [],
            'is_default' => !empty($p['is_default']),
            'is_active' => !empty($p['is_active'])
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'provider' => $frontendProvider,
        'model' => $defaultModel,
        'frontend_model' => $defaultModel,
        'backend_model' => $backendModel,
        'providers' => $providerList,
        'openrouter_key_source' => $openrouterKeySource
    ]);
});

// GET /api/ai-system/admin-defaults
$router->get('/api/ai-system/admin-defaults', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $settings = $aiProvider->getSettings();
    $providers = $aiProvider->getActive();

    $defaultProvider = trim((string)($settings['default_provider'] ?? ''));
    if ($defaultProvider === '') {
        $effective = $aiProvider->getEffectiveProvider();
        $defaultProvider = $effective['provider_name'] ?? 'openrouter';
    }

    $defaultModel = aiSystemResolveModel(
        $aiProvider,
        $defaultProvider,
        '',
        $providers,
        (string)($settings['default_model'] ?? '')
    );

    header('Content-Type: application/json');
    echo json_encode([
        'provider' => $defaultProvider,
        'model' => $defaultModel,
        'default_model' => $settings['default_model'] ?? ''
    ]);
});

// GET /api/ai/default-provider (Admin only)
$router->get('/api/ai/default-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $settings = $aiProvider->getSettings();

    $provider = trim((string)($settings['default_provider'] ?? ''));
    if ($provider === '') {
        $effective = $aiProvider->getEffectiveProvider();
        $provider = $effective['provider_name'] ?? 'openrouter';
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'provider' => $provider
    ]);
});

// GET /api/ai/models?provider=fireworks
$router->get('/api/ai/models', function () use ($mysqli) {
    $providerName = $_GET['provider'] ?? '';
    $scope = $_GET['scope'] ?? '';
    $forceRefresh = !empty($_GET['refresh']);
    $aiProvider = new AIProvider($mysqli);

    header('Content-Type: application/json');

    if ($providerName === 'ollama' || $scope === 'admin' || $forceRefresh) {
        if (
            !run_middleware('auth', ['method' => 'GET', 'uri' => '/api/ai/models'])
            || !run_middleware('admin_only', ['method' => 'GET', 'uri' => '/api/ai/models'])
        ) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }
    }

    $settings = $aiProvider->getSettings();
    $defaultModel = $settings['default_model'] ?? '';

    if (!$providerName) {
        $providers = [];
        $providerMeta = [];
        foreach ($aiProvider->getActive() as $provider) {
            $name = $provider['provider_name'] ?? '';
            if ($name === '') {
                continue;
            }
            $models = $provider['supported_models'] ?? [];
            if (empty($models)) {
                $config = AIProvider::getProviderConfig($name);
                $models = $config['models'] ?? [];
            }

            $list = [];
            foreach ($models as $id => $label) {
                $list[] = [
                    'id' => (string)$id,
                    'name' => (string)$label,
                    'default' => ($defaultModel !== '' && $defaultModel === (string)$id)
                ];
            }

            if (!empty($list) && !array_filter($list, fn($m) => !empty($m['default']))) {
                $list[0]['default'] = true;
            }

            $providers[$name] = $list;
            $providerMeta[$name] = [
                'supports_multimodal' => !empty($provider['supports_multimodal'])
            ];
        }

        echo json_encode([
            'success' => true,
            'providers' => $providers,
            'provider_meta' => $providerMeta
        ]);
        return;
    }

    $provider = $aiProvider->getByName($providerName);
    if (!$provider) {
        echo json_encode(['success' => false, 'error' => 'Provider not found']);
        return;
    }

    $models = $provider['supported_models'] ?? [];
    if (empty($models)) {
        $config = AIProvider::getProviderConfig($providerName);
        $models = $config['models'] ?? [];
    }

    if (in_array($providerName, ['openrouter', 'openai', 'fireworks', 'huggingface', 'ollama', 'kilo'], true)) {
        $remote = $aiProvider->fetchRemoteModels($providerName, $forceRefresh);
        if (!empty($remote)) {
            $models = $remote;
        }
    }

    if (empty($models)) {
        echo json_encode(['success' => false, 'error' => 'No models available']);
        return;
    }

    $providerSupportsRich = $aiProvider->supportsRichContent($providerName, $provider);
    $overrides = $provider['extra_settings']['model_multimodal'] ?? [];
    if (!is_array($overrides)) {
        $overrides = [];
    }

    $list = [];
    foreach ($models as $id => $label) {
        $modelId = (string)$id;
        if (array_key_exists($modelId, $overrides)) {
            $supportsMultimodal = (bool)$overrides[$modelId];
        } else {
            $supportsMultimodal = $providerSupportsRich;
        }
        $list[] = [
            'id' => $modelId,
            'name' => (string)$label,
            'default' => ($defaultModel !== '' && $defaultModel === (string)$id),
            'supports_multimodal' => $supportsMultimodal
        ];
    }

    if (!empty($list) && !array_filter($list, fn($m) => !empty($m['default']))) {
        $list[0]['default'] = true;
    }

    echo json_encode(['success' => true, 'models' => $list]);
});

// POST /api/ai/knowledge/feedback – KB feedback (moved from AISystemController)
$router->post('/api/ai/knowledge/feedback', ['middleware' => ['csrf']], function () use ($mysqli) {
    require_once __DIR__ . '/../Models/AIKnowledge.php';
    $model = new AIKnowledge($mysqli);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $knowledgeId = (int)($input['knowledge_id'] ?? 0);
    $isHelpful = isset($input['is_helpful']) ? (bool)$input['is_helpful'] : false;
    $feedbackText = $input['feedback_text'] ?? null;
    $sessionId = $input['session_id'] ?? null;
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if (!$knowledgeId) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Knowledge ID required']);
        return;
    }

    $ok = $model->recordFeedback($knowledgeId, $isHelpful, is_string($feedbackText) ? $feedbackText : null, is_string($sessionId) ? $sessionId : null, $userId);
    header('Content-Type: application/json');
    echo json_encode(['success' => $ok]);
});

// AI feedback endpoint – CSRF protected
$router->post('/api/ai/feedback', ['middleware' => ['csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        return;
    }
    $conversationId = $input['conversation_id'] ?? '';
    $messageId = $input['message_id'] ?? 0;
    $rating = $input['rating'] ?? 0;
    $comment = $input['comment'] ?? null;
    $userId = AuthManager::isUserAuthenticated() ? AuthManager::getCurrentUserId() : null;
    if (empty($conversationId) || !$messageId || $rating < 1 || $rating > 5) {
        jsonResponse(['success' => false, 'error' => 'Invalid feedback data']);
        return;
    }
    $feedbackModel = new AIFeedback($mysqli);
    $feedbackModel->ensureTable();
    $success = $feedbackModel->saveFeedback($conversationId, $messageId, $rating, $comment, $userId);
    jsonResponse(['success' => $success]);
});

// GDPR Consent Audit Trail Endpoint – CSRF protected
$router->post('/api/gdpr/consent', ['middleware' => ['csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $visitorToken = $input['visitor_token'] ?? null;
    $consentData = $input['consent'] ?? [];
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    if (empty($visitorToken)) {
        jsonResponse(['success' => false, 'error' => 'Visitor token required']);
        return;
    }

    // Log consent to activity
    logActivity(
        "GDPR Consent",
        "gdpr_consent",
        null,
        [
            'visitor_token' => $visitorToken,
            'consent_data' => $consentData,
            'ip' => $ip,
            'user_agent' => $userAgent
        ],
        'info'
    );

    // Optionally store in database for audit trail
    require_once __DIR__ . '/../Models/AIFeedback.php';
    $feedbackModel = new AIFeedback($mysqli);

    // Create GDPR consent table if not exists
    $tableName = 'ai_gdpr_consent';
    $mysqli->query("CREATE TABLE IF NOT EXISTS `$tableName` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `visitor_token` VARCHAR(255) NOT NULL,
        `consent_data` JSON,
        `ip_address` VARCHAR(45),
        `user_agent` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_visitor_token (`visitor_token`),
        INDEX idx_created_at (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Store consent
    $stmt = $mysqli->prepare("INSERT INTO `$tableName` (visitor_token, consent_data, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $consentJson = json_encode($consentData);
    $stmt->bind_param('ssss', $visitorToken, $consentJson, $ip, $userAgent);
    $success = $stmt->execute();
    $stmt->close();

    jsonResponse(['success' => $success]);
});

// Admin Text-to-Speech (TTS) Endpoint – Auth + CSRF protected
$router->post('/api/admin/ai/tts', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $text = $input['text'] ?? '';
    $voice = $input['voice'] ?? 'alloy';
    $model = $input['model'] ?? 'gpt-4o-mini-tts';
    $format = $input['format'] ?? 'wav';

    if (empty($text)) {
        jsonResponse(['success' => false, 'error' => 'Text is required']);
        return;
    }

    // Validate voice
    $allowedVoices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];
    if (!in_array($voice, $allowedVoices)) {
        $voice = 'alloy';
    }

    // Validate format
    $allowedFormats = ['wav', 'mp3', 'opus', 'aac'];
    if (!in_array($format, $allowedFormats)) {
        $format = 'wav';
    }

    // Get API key from settings
    $aiProvider = new AIProvider($mysqli);
    $settings = $aiProvider->getSettings();
    $apiKey = $settings['openai_api_key'] ?? '';

    if (empty($apiKey)) {
        jsonResponse(['success' => false, 'error' => 'OpenAI API key not configured']);
        return;
    }

    // Call OpenAI TTS API
    $ch = curl_init('https://api.openai.com/v1/audio/speech');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'voice' => $voice,
            'input' => $text,
            'response_format' => $format
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        jsonResponse(['success' => false, 'error' => 'TTS generation failed', 'details' => $response]);
        return;
    }

    // Return audio as base64
    $audioBase64 = base64_encode($response);
    $mimeType = match ($format) {
        'mp3' => 'audio/mpeg',
        'opus' => 'audio/opus',
        'aac' => 'audio/aac',
        default => 'audio/wav'
    };

    jsonResponse([
        'success' => true,
        'audio' => $audioBase64,
        'mime_type' => $mimeType,
        'format' => $format
    ]);
});

// Admin Image Generation Endpoint – Auth + CSRF protected
$router->post('/api/admin/ai/image', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $prompt = $input['prompt'] ?? '';
    $model = $input['model'] ?? 'gpt-image-1';
    $quality = $input['quality'] ?? 'standard';
    $size = $input['size'] ?? '1024x1024';
    $n = $input['n'] ?? 1;

    if (empty($prompt)) {
        jsonResponse(['success' => false, 'error' => 'Prompt is required']);
        return;
    }

    // Validate size
    $allowedSizes = ['1024x1024', '1024x1536', '1536x1024', '512x512', '768x768'];
    if (!in_array($size, $allowedSizes)) {
        $size = '1024x1024';
    }

    // Validate quality
    $allowedQuality = ['standard', 'hd'];
    if (!in_array($quality, $allowedQuality)) {
        $quality = 'standard';
    }

    // Validate n
    $n = max(1, min(10, (int)$n));

    // Get API key from settings
    $aiProvider = new AIProvider($mysqli);
    $settings = $aiProvider->getSettings();
    $apiKey = $settings['openai_api_key'] ?? '';

    if (empty($apiKey)) {
        jsonResponse(['success' => false, 'error' => 'OpenAI API key not configured']);
        return;
    }

    // Call OpenAI Images API using Responses API for GPT Image
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'input' => $prompt,
            'tools' => [['type' => 'image_generation']],
            'preferences' => [
                'quality' => $quality,
                'size' => $size
            ]
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        jsonResponse(['success' => false, 'error' => 'Image generation failed', 'http_code' => $httpCode, 'details' => $response]);
        return;
    }

    $data = json_decode($response, true);

    // Extract images from response
    $images = [];
    if (!empty($data['output'])) {
        foreach ($data['output'] as $output) {
            if (($output['type'] ?? '') === 'image_generation_call') {
                $imageBase64 = $output['result'] ?? '';
                if (!empty($imageBase64)) {
                    $images[] = [
                        'base64' => $imageBase64,
                        'mime_type' => 'image/png'
                    ];
                }
            }
        }
    }

    if (empty($images)) {
        jsonResponse(['success' => false, 'error' => 'No images generated', 'response' => $data]);
        return;
    }

    jsonResponse([
        'success' => true,
        'images' => $images,
        'model' => $model,
        'quality' => $quality,
        'size' => $size
    ]);
});

// Admin Web Search Endpoint – Add Real-time Web Data to AI Model Responses
$router->post('/api/admin/ai/websearch', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);

    $query = $input['query'] ?? '';
    $model = $input['model'] ?? 'openai/gpt-4o';
    $maxResults = isset($input['max_results']) ? (int)$input['max_results'] : 5;
    $includeDomains = $input['include_domains'] ?? [];
    $excludeDomains = $input['exclude_domains'] ?? [];
    $engine = $input['engine'] ?? 'exa';

    if (empty($query)) {
        jsonResponse(['success' => false, 'error' => 'Query is required']);
        return;
    }

    $apiKey = getOpenRouterApiKey();
    if (!$apiKey) {
        jsonResponse(['success' => false, 'error' => 'OpenRouter API key not configured']);
        return;
    }

    $plugins = [
        [
            'id' => 'web-search',
            'web' => [
                'engine' => $engine,
                'max_results' => $maxResults
            ]
        ]
    ];

    if (!empty($includeDomains)) {
        $plugins[0]['web']['include_domains'] = $includeDomains;
    }
    if (!empty($excludeDomains)) {
        $plugins[0]['web']['exclude_domains'] = $excludeDomains;
    }

    $modelName = $model;
    if (strpos($model, ':online') === false) {
        $modelName = $model . ':online';
    }

    $url = 'https://openrouter.ai/api/v1/chat/completions';
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'HTTP-Referer: ' . ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_HOST'] ?? ''),
        'X-Title: BroxLab Admin'
    ];

    $payload = [
        'model' => $modelName,
        'messages' => [
            ['role' => 'user', 'content' => $query]
        ],
        'temperature' => 0.7,
        'max_tokens' => 4000
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        jsonResponse(['success' => false, 'error' => 'CURL error: ' . $curlError]);
        return;
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200 || isset($data['error'])) {
        jsonResponse(['success' => false, 'error' => $data['error']['message'] ?? 'Web search failed', 'response' => $data]);
        return;
    }

    $content = $data['choices'][0]['message']['content'] ?? '';
    $usage = $data['usage'] ?? [];

    jsonResponse(['success' => true, 'query' => $query, 'response' => $content, 'model' => $modelName, 'engine' => $engine, 'usage' => $usage]);
});

// Admin PDF Input Endpoint – Process PDF Documents with AI
$router->post('/api/admin/ai/pdf', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);

    $prompt = $input['prompt'] ?? 'Extract and summarize the key information from this PDF document.';
    $pdfUrl = $input['url'] ?? '';
    $pdfBase64 = $input['base64'] ?? '';
    $model = $input['model'] ?? 'openai/gpt-4o-mini';
    $pdfEngine = $input['engine'] ?? 'pdf-text';

    if (empty($pdfUrl) && empty($pdfBase64)) {
        jsonResponse(['success' => false, 'error' => 'Either PDF URL or base64 data is required']);
        return;
    }

    $apiKey = getOpenRouterApiKey();
    if (!$apiKey) {
        jsonResponse(['success' => false, 'error' => 'OpenRouter API key not configured']);
        return;
    }

    $fileContent = [];
    if (!empty($pdfUrl)) {
        $fileContent = [
            'type' => 'file',
            'file' => ['filename' => 'document.pdf', 'file_data' => $pdfUrl]
        ];
    } elseif (!empty($pdfBase64)) {
        $fileContent = [
            'type' => 'file',
            'file' => ['filename' => 'document.pdf', 'file_data' => 'data:application/pdf;base64,' . $pdfBase64]
        ];
    }

    $plugins = [
        ['id' => 'file-parser', 'pdf' => ['engine' => $pdfEngine]]
    ];

    $url = 'https://openrouter.ai/api/v1/chat/completions';
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'HTTP-Referer: ' . ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_HOST'] ?? ''),
        'X-Title: BroxLab Admin'
    ];

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => $prompt],
                $fileContent
            ]]
        ],
        'temperature' => 0.7,
        'max_tokens' => 4000
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        jsonResponse(['success' => false, 'error' => 'CURL error: ' . $curlError]);
        return;
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200 || isset($data['error'])) {
        jsonResponse(['success' => false, 'error' => $data['error']['message'] ?? 'PDF processing failed', 'response' => $data]);
        return;
    }

    $content = $data['choices'][0]['message']['content'] ?? '';
    $usage = $data['usage'] ?? [];
    $annotations = $data['choices'][0]['message']['annotations'] ?? null;

    jsonResponse(['success' => true, 'response' => $content, 'model' => $model, 'pdf_engine' => $pdfEngine, 'usage' => $usage, 'annotations' => $annotations]);
});

// Skip PDF Parsing Costs – Reuse annotations from previous requests
$router->post('/api/admin/ai/pdf/continue', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);

    $prompt = $input['prompt'] ?? '';
    $annotations = $input['annotations'] ?? [];
    $pdfBase64 = $input['base64'] ?? '';
    $model = $input['model'] ?? 'openai/gpt-4o-mini';

    if (empty($prompt)) {
        jsonResponse(['success' => false, 'error' => 'Prompt is required']);
        return;
    }
    if (empty($annotations)) {
        jsonResponse(['success' => false, 'error' => 'Annotations from previous request are required to skip parsing costs']);
        return;
    }

    $apiKey = getOpenRouterApiKey();
    if (!$apiKey) {
        jsonResponse(['success' => false, 'error' => 'OpenRouter API key not configured']);
        return;
    }

    $content = [
        ['type' => 'text', 'text' => $prompt]
    ];

    if (!empty($pdfBase64)) {
        $content[] = ['type' => 'file', 'file' => ['filename' => 'document.pdf', 'file_data' => 'data:application/pdf;base64,' . $pdfBase64]];
    }

    $content[] = ['role' => 'assistant', 'content' => '', 'annotations' => $annotations];
    $content[] = ['role' => 'user', 'content' => $prompt];

    $url = 'https://openrouter.ai/api/v1/chat/completions';
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'HTTP-Referer: ' . ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_HOST'] ?? ''),
        'X-Title: BroxLab Admin'
    ];

    $payload = [
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $content]],
        'temperature' => 0.7,
        'max_tokens' => 4000
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        jsonResponse(['success' => false, 'error' => 'CURL error: ' . $curlError]);
        return;
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200 || isset($data['error'])) {
        jsonResponse(['success' => false, 'error' => $data['error']['message'] ?? 'PDF continue request failed', 'response' => $data]);
        return;
    }

    $content = $data['choices'][0]['message']['content'] ?? '';
    $usage = $data['usage'] ?? [];

    jsonResponse(['success' => true, 'response' => $content, 'model' => $model, 'usage' => $usage, 'note' => 'Annotations reused - no additional PDF parsing costs']);
});

// Admin AI Presence - Check who's currently using the AI assistant
$router->get('/api/admin/ai/presence', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $sessionKey = 'ai_active_sessions';
    $currentUserId = AuthManager::getCurrentUserId() ?? $_SESSION['user_id'] ?? null;
    $currentUserName = AuthManager::getCurrentUserArray()['username'] ?? 'Admin';

    // Initialize session tracking
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = [];
    }

    $sessions = $_SESSION[$sessionKey];
    $now = time();
    $activeUsers = [];

    // Clean up old sessions (older than 5 minutes)
    foreach ($sessions as $userId => $session) {
        if ($now - $session['last_active'] > 300) {
            unset($sessions[$userId]);
        } else {
            $activeUsers[] = [
                'user_id' => $userId,
                'username' => $session['username'],
                'last_active' => date('H:i:s', $session['last_active']),
                'action' => $session['action'] ?? 'idle'
            ];
        }
    }

    // Update current user's session
    if ($currentUserId) {
        $sessions[$currentUserId] = [
            'username' => $currentUserName,
            'last_active' => $now,
            'action' => 'chatting'
        ];
    }

    $_SESSION[$sessionKey] = $sessions;

    jsonResponse([
        'success' => true,
        'active_users' => $activeUsers,
        'total_active' => count($activeUsers),
        'your_id' => $currentUserId
    ]);
});

// Admin AI Heartbeat - Keep session alive
$router->post('/api/admin/ai/heartbeat', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'idle';

    $sessionKey = 'ai_active_sessions';
    $currentUserId = AuthManager::getCurrentUserId() ?? $_SESSION['user_id'] ?? null;

    if ($currentUserId && isset($_SESSION[$sessionKey][$currentUserId])) {
        $_SESSION[$sessionKey][$currentUserId]['last_active'] = time();
        $_SESSION[$sessionKey][$currentUserId]['action'] = $action;
    }

    jsonResponse(['success' => true, 'timestamp' => time()]);
});

// Admin AI Share Session - Generate shareable session link
$router->post('/api/admin/ai/share', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $shareWithUserId = $input['user_id'] ?? null;
    $expiresIn = (int)($input['expires_hours'] ?? 24);

    if (!$shareWithUserId) {
        jsonResponse(['success' => false, 'error' => 'User ID is required']);
        return;
    }

    // Generate share token
    $token = bin2hex(random_bytes(16));
    $expiresAt = time() + ($expiresIn * 3600);

    $shareKey = 'ai_session_shares';
    if (!isset($_SESSION[$shareKey])) {
        $_SESSION[$shareKey] = [];
    }

    $_SESSION[$shareKey][$token] = [
        'shared_by' => AuthManager::getCurrentUserId() ?? $_SESSION['user_id'],
        'shared_with' => $shareWithUserId,
        'expires_at' => $expiresAt,
        'created_at' => time()
    ];

    $baseUrl = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_HOST'] ?? '';
    $shareUrl = $baseUrl . '/admin/ai-copilot?share_token=' . $token;

    jsonResponse([
        'success' => true,
        'share_token' => $token,
        'share_url' => $shareUrl,
        'expires_at' => date('Y-m-d H:i:s', $expiresAt)
    ]);
});

// ================== OCR Integration for AI Assistant ==================

// OCR Health Check
$router->get('/api/ai/ocr/health', function () use ($ocrService) {
    try {
        $usageInfo = $ocrService->getUsageInfo();

        jsonResponse([
            'status' => 'healthy',
            'ocr_available' => $ocrService->isAvailable(),
            'available_languages' => $ocrService->getAvailableLanguages(),
            'service_type' => 'ocr_space_api',
            'usage_info' => $usageInfo,
            'timestamp' => date('c')
        ]);
    } catch (Exception $e) {
        jsonResponse([
            'status' => 'error',
            'error' => $e->getMessage()
        ], 500);
    }
});

// Extract text from image
$router->post('/api/ai/ocr/image', function () use ($ocrService) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['image'])) {
            jsonResponse(['success' => false, 'error' => 'Image data is required'], 400);
            return;
        }

        $options = $input['options'] ?? [];
        $result = $ocrService->extractTextFromImage($input['image'], $options);

        jsonResponse($result);
    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Extract text from PDF
$router->post('/api/ai/ocr/pdf', function () use ($ocrService) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['pdf'])) {
            jsonResponse(['success' => false, 'error' => 'PDF data is required'], 400);
            return;
        }

        $options = $input['options'] ?? [];
        $result = $ocrService->extractTextFromPDF($input['pdf'], $options);

        jsonResponse($result);
    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Process batch OCR
$router->post('/api/ai/ocr/batch', function () use ($ocrService) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['files'])) {
            jsonResponse(['success' => false, 'error' => 'Files array is required'], 400);
            return;
        }

        $options = $input['options'] ?? [];
        $result = $ocrService->processBatch($input['files'], $options);

        jsonResponse($result);
    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Handle file upload OCR
$router->post('/api/ai/ocr/upload', function () use ($ocrService) {
    try {
        if (!isset($_FILES['file'])) {
            jsonResponse(['success' => false, 'error' => 'File upload is required'], 400);
            return;
        }

        $file = $_FILES['file'];
        $filePath = $file['tmp_name'];
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'bmp', 'tiff', 'tif', 'pdf'])) {
            jsonResponse(['success' => false, 'error' => 'Unsupported file type'], 400);
            return;
        }

        $fileData = file_get_contents($filePath);
        $base64Data = base64_encode($fileData);

        $options = $_POST['options'] ?? [];
        $options = is_string($options) ? json_decode($options, true) : $options;
        $options = is_array($options) ? $options : [];

        if ($fileType === 'pdf') {
            $result = $ocrService->extractTextFromPDF($base64Data, $options);
        } else {
            $result = $ocrService->extractTextFromImage($base64Data, $options);
        }

        jsonResponse($result);
    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Health check endpoints for admin dashboard
$router->get('/api/admin/health/database', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $result = $mysqli->query('SELECT 1');
        if ($result) {
            jsonResponse(['status' => 'ok', 'message' => 'Database is healthy']);
        } else {
            jsonResponse(['status' => 'error', 'message' => 'Database query failed'], 500);
        }
    } catch (Exception $e) {
        jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

$router->get('/api/admin/health/redis', ['middleware' => ['auth', 'admin_only']], function () {
    header('Content-Type: application/json');
    try {
        $redis = new Redis();
        $connected = $redis->connect('127.0.0.1', 6379);
        if ($connected) {
            $redis->close();
            jsonResponse(['status' => 'ok', 'message' => 'Redis is healthy']);
        } else {
            jsonResponse(['status' => 'error', 'message' => 'Could not connect to Redis'], 500);
        }
    } catch (Exception $e) {
        jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});
