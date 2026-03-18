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

    if (!$message) {
        jsonResponse(["error" => "No message provided"]);
        return;
    }

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

// AI feedback endpoint – CSRF protected
$router->post('/api/ai/feedback', ['middleware' => ['csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $csrfToken = $input['csrf_token'] ?? '';
    validateCsrfToken($csrfToken);
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
