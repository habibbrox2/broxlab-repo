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
require_once __DIR__ . '/../Helpers/PromptLoader.php';

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
            $notificationTitle = "নতুন যোগাযোগ বার্তা";
            $notificationBody = "$name (" . substr($contact, 0, 15) . "...) একটা বার্তা পাঠিয়েছেন: \"$subject\"";
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
    $provider = $input['provider'] ?? null;
    $model = $input['model'] ?? null;
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
