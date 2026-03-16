<?php
// app/Controllers/AISystemChatController.php

require_once __DIR__ . '/../Modules/AISystem/AgentClient.php';
require_once __DIR__ . '/../Helpers/PromptLoader.php';

/** @var \Router $router */
/** @var \mysqli $mysqli */
/** @var \Twig\Environment $twig */
// ==================== PUBLIC AI CHAT API ====================
// API endpoint for public assistant AI chat (uses backend AI provider)
// Accepts context parameter: 'public' or 'admin' to use appropriate system prompt

// Returns a system prompt (and structured prompt set) for the given context.
// It loads YAML/JSON prompts from system/prompts/, with fallback to DB settings.

function getSystemPromptForContext(string $context, mysqli $mysqli): string
{
    return PromptLoader::getSystemPrompt($context, $mysqli);
}

// API endpoint used by public assistant when topic is support
$router->post('/api/public-chat/support', function () use ($mysqli) {
    header('Content-Type: application/json');
    $contactModel = new ContactModel($mysqli);
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message is required']);
        return;
    }

    // if user authenticated and missing info, pull from session
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
        echo json_encode(['success' => false, 'error' => 'Name or contact missing']);
        return;
    }

    $subject = 'Support Request (Public Chat)';
    $contactId = $contactModel->createMessage($name, $contact, $subject, $message, $ip);

    if ($contactId) {
        // log activity and notify admins similar to standard contact
        logActivity("Contact Message Submitted", "contact", $contactId, ['name' => $name, 'email' => $contact, 'subject' => $subject], 'success');

        // send acknowledgement to user if contact looks like email
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
        // send notification email to admin if configured
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

        // push notification to admins as well
        $adminIds = $contactModel->getAdminUserIds();
        if (!empty($adminIds)) {
            require_once __DIR__ . '/../Helpers/FirebaseHelper.php';
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

        echo json_encode(['success' => true, 'id' => $contactId]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save message']);
    }
});
$router->post('/api/chat', function () use ($mysqli) {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? '';
    // Provider and model may be sent as empty strings from the UI. Treat empty values as null
    // so that AgentClient can apply auto‑switching logic.
    $provider = (!empty($input['provider'])) ? $input['provider'] : null;
    $model = (!empty($input['model'])) ? $input['model'] : null;
    $context = $input['context'] ?? 'public'; // 'public' or 'admin'

    if (!$message) {
        echo json_encode(["error" => "No message provided"]);
        return;
    }

    $agent = new AgentClient($mysqli);

    // Load context-aware system prompt using PromptLoader
    $systemPrompt = PromptLoader::getSystemPrompt($context, $mysqli);

    // Append relevant knowledge-base context (if any) based on the user message
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

    // The chat method will exit script automatically if streaming is active.
    $response = $agent->chat($messages, $provider, $model, $extraContext, $stream);
    echo json_encode($response);
});

// ==================== NEW API ENDPOINTS FOR MODEL MANAGEMENT ====================

// GET /api/ai/models/list - Get available models with caching
$router->get('/api/ai/models/list', function () use ($mysqli) {
    header('Content-Type: application/json');

    $agent = new AgentClient($mysqli);
    $provider = $_GET['provider'] ?? 'openrouter';

    // Use reflection to access private methods for backward compatibility
    $reflection = new ReflectionClass($agent);
    $getAvailableModels = $reflection->getMethod('getAvailableModels');
    $getAvailableModels->setAccessible(true);
    $models = $getAvailableModels->invoke($agent, $provider);

    echo json_encode([
        'success' => true,
        'provider' => $provider,
        'models' => $models,
        'cache_stats' => $agent->getCacheStats()
    ]);
});

// GET /api/ai/models/info - Get specific model information
$router->get('/api/ai/models/info', function () use ($mysqli) {
    header('Content-Type: application/json');

    $agent = new AgentClient($mysqli);
    $provider = $_GET['provider'] ?? 'openrouter';
    $modelName = $_GET['model'] ?? '';

    if (empty($modelName)) {
        echo json_encode(['success' => false, 'error' => 'Model name is required']);
        return;
    }

    // Use reflection to access private methods
    $reflection = new ReflectionClass($agent);
    $getModelInfo = $reflection->getMethod('getModelInfo');
    $getModelInfo->setAccessible(true);
    $result = $getModelInfo->invoke($agent, $provider, $modelName);
    echo json_encode($result);
});

// POST /api/ai/cache/clear - Clear cache
$router->post('/api/ai/cache/clear', function () use ($mysqli) {
    header('Content-Type: application/json');

    $agent = new AgentClient($mysqli);
    $type = $_POST['type'] ?? 'all'; // 'all', 'models', 'chat'

    // Use reflection to access private methods
    $reflection = new ReflectionClass($agent);
    $clearAllCache = $reflection->getMethod('clearAllCache');
    $clearAllCache->setAccessible(true);

    switch ($type) {
        case 'models':
            $clearProviderCache = $reflection->getMethod('clearProviderCache');
            $clearProviderCache->setAccessible(true);
            $clearProviderCache->invoke($agent, $_POST['provider'] ?? 'openrouter');
            break;
        case 'chat':
            $clearAllCache->invoke($agent);
            break;
        case 'all':
        default:
            $clearAllCache->invoke($agent);
    }

    echo json_encode(['success' => true, 'message' => 'Cache cleared successfully']);
});

// GET /api/ai/cache/stats - Get cache statistics
$router->get('/api/ai/cache/stats', function () use ($mysqli) {
    header('Content-Type: application/json');

    $agent = new AgentClient($mysqli);
    $stats = $agent->getCacheStats();

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
});

// POST /api/ai/test - Test AI connection with model caching
$router->post('/api/ai/test', function () use ($mysqli) {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $provider = $input['provider'] ?? 'openrouter';
    $model = $input['model'] ?? null;

    $agent = new AgentClient($mysqli);

    // Use reflection to access private property
    $reflection = new ReflectionClass($agent);
    $aiProviderProp = $reflection->getProperty('aiProvider');
    $aiProviderProp->setAccessible(true);
    $aiProvider = $aiProviderProp->getValue($agent);

    $result = $aiProvider->testConnection($provider, $model);

    echo json_encode($result);
});
