<?php

/**
 * AISystemController.php
 * Handles AI provider configuration routes in the Admin Panel.
 * All database operations are handled by AIProvider model.
 */

require_once __DIR__ . '/../Models/AIProvider.php';
require_once __DIR__ . '/../Models/AppSettings.php';
require_once __DIR__ . '/../Helpers/PromptLoader.php';
require_once __DIR__ . '/../Models/AIChatModel.php';

function aiChatSendJson(array $payload, int $status = 200): void
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function aiChatStreamContent(string $content): void
{
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @ob_implicit_flush(true);

    $chunkSize = 200;
    if (function_exists('mb_strlen')) {
        $length = mb_strlen($content, 'UTF-8');
        for ($i = 0; $i < $length; $i += $chunkSize) {
            $chunk = mb_substr($content, $i, $chunkSize, 'UTF-8');
            echo 'data: ' . json_encode(['content' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();
        }
    } else {
        foreach (str_split($content, $chunkSize) as $chunk) {
            echo 'data: ' . json_encode(['content' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();
        }
    }

    echo "data: [DONE]\n\n";
    @ob_flush();
    flush();
}

function aiChatNormalizeMessages($messages, int $maxMessages, int $maxChars, ?string &$error = null): array
{
    if (!is_array($messages)) {
        $error = 'Messages array is required';
        return [];
    }

    $out = [];
    foreach ($messages as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        $role = $msg['role'] ?? '';
        if (!in_array($role, ['user', 'assistant'], true)) {
            continue;
        }
        $content = $msg['content'] ?? '';
        if (!is_string($content)) {
            continue;
        }
        $content = trim($content);
        if ($content === '') {
            continue;
        }
        $len = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
        if ($len > $maxChars) {
            $error = 'Message too long';
            return [];
        }
        $out[] = ['role' => $role, 'content' => $content];
    }

    if (empty($out)) {
        $error = 'No valid messages';
        return [];
    }

    if (count($out) > $maxMessages) {
        $out = array_slice($out, -$maxMessages);
    }

    return $out;
}

function aiChatLastUserMessage(array $messages): string
{
    for ($i = count($messages) - 1; $i >= 0; $i--) {
        if (($messages[$i]['role'] ?? '') === 'user') {
            return (string)($messages[$i]['content'] ?? '');
        }
    }
    return '';
}

function aiChatHandleRequest(array $input, mysqli $mysqli, bool $isAdmin, bool $allowOverrides): void
{
    $aiProvider = new AIProvider($mysqli);
    $chatModel = new AIChatModel($mysqli);

    $maxMessages = $isAdmin ? 40 : 20;
    $maxChars = $isAdmin ? 8000 : 4000;
    $error = null;
    $messages = aiChatNormalizeMessages($input['messages'] ?? null, $maxMessages, $maxChars, $error);
    if ($error) {
        aiChatSendJson(['success' => false, 'error' => $error], 400);
        return;
    }

    $stream = !empty($input['stream']);
    $contextType = $isAdmin ? 'admin' : 'public';
    $contextData = $input['context'] ?? null;

    $systemPrompt = PromptLoader::getSystemPrompt($contextType, $mysqli);
    if ($contextData && is_array($contextData)) {
        $systemPrompt .= "\n\n[USER CONTEXT]\n";
        foreach ($contextData as $key => $val) {
            if (is_scalar($val)) {
                $systemPrompt .= ucfirst((string)$key) . ": $val\n";
            }
        }
    }

    $lastUserMessage = aiChatLastUserMessage($messages);
    if ($lastUserMessage !== '') {
        $kbContext = PromptLoader::getKnowledgeContext($lastUserMessage, $mysqli);
        if ($kbContext) {
            $systemPrompt .= "\n\n" . $kbContext;
        }
    }

    array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);

    $settings = $aiProvider->getSettings();
    $effective = $aiProvider->getEffectiveProvider();
    $provider = $effective['provider_name'] ?? 'openrouter';
    $model = $settings['default_model'] ?? 'openrouter/auto';

    if ($allowOverrides) {
        if (!empty($input['provider']) && is_string($input['provider'])) {
            $provider = $input['provider'];
        }
        if (!empty($input['model']) && is_string($input['model'])) {
            $model = $input['model'];
        }
    }

    $options = [];
    if ($allowOverrides && isset($input['options']) && is_array($input['options'])) {
        $options = $input['options'];
    }
    $options['max_tokens'] = isset($options['max_tokens'])
        ? (int)$options['max_tokens']
        : (int)($settings['max_tokens'] ?? 4000);
    $options['temperature'] = isset($options['temperature'])
        ? (float)$options['temperature']
        : (float)($settings['temperature'] ?? 0.7);

    $convId = null;
    if (!$isAdmin) {
        $visitorToken = $input['visitorToken'] ?? null;
        if ($visitorToken) {
            $convId = $chatModel->getOrCreateConversation(null, $visitorToken);
            if ($convId && $lastUserMessage !== '') {
                $chatModel->addMessage($convId, 'user', $lastUserMessage);
            }
        }
    }

    $response = $aiProvider->callAPI($provider, $model, $messages, $options);

    if ($convId && !empty($response['success'])) {
        $aiText = $response['content'] ?? '';
        if ($aiText !== '') {
            $chatModel->addMessage($convId, 'assistant', $aiText);
        }
    }

    if ($stream) {
        if (empty($response['success'])) {
            aiChatSendJson(['success' => false, 'error' => $response['error'] ?? 'AI error'], 502);
            return;
        }
        aiChatStreamContent($response['content'] ?? '');
        return;
    }

    if (!empty($response['success'])) {
        aiChatSendJson([
            'success' => true,
            'content' => $response['content'] ?? '',
            'usage' => $response['usage'] ?? []
        ]);
        return;
    }

    aiChatSendJson(['success' => false, 'error' => $response['error'] ?? 'AI error'], 502);
}



    // ==================== GET /admin/ai-system ====================
    $router->get('/admin/ai-system', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
        $aiProvider = new AIProvider($mysqli);

        $providers = $aiProvider->getAll();
        // Remove Puter provider from UI/config management - Puter is only used client-side as a fallback.
        $providers = array_filter($providers, fn($p) => ($p['provider_name'] ?? '') !== 'puter');

        $settings = $aiProvider->getSettings();
        // Ensure frontend provider never returns the old Puter option
        if (($settings['frontend_provider'] ?? '') === 'puter-js' || ($settings['frontend_provider'] ?? '') === 'puter') {
            $settings['frontend_provider'] = 'openrouter';
        }

        $defaultProvider = $aiProvider->getDefault();
        if ($defaultProvider && ($defaultProvider['provider_name'] ?? '') === 'puter') {
            $defaultProvider = $aiProvider->getEffectiveProvider();
        }

        $providerConfigs = AIProvider::getAllProviderConfigs();

        // Determine where OpenRouter key comes from (DB vs environment).
        $openrouterDbKey = $settings['openrouter_api_key'] ?? '';
        $settings['openrouter_key_source'] = !empty($openrouterDbKey) ? 'db' : 'none';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => '/admin'],
            ['label' => 'AI SYSTEM', 'url' => '/admin/ai-system']
        ];

        echo $twig->render('admin/ai/system.twig', [
            'title' => 'AI SYSTEM',
            'breadcrumbs' => $breadcrumbs,
            'providers' => $providers,
            'settings' => $settings,
            'default_provider' => $defaultProvider,
            'provider_configs' => $providerConfigs,
            'csrf_token' => generateCsrfToken()
        ]);
    });

    // ==================== GET /api/ai-system/frontend ====================
    $router->get('/api/ai-system/frontend', function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);
        $settings = $aiProvider->getSettings();

        $openrouterDbKey = $settings['openrouter_api_key'] ?? '';

        $openrouterKeySource = !empty($openrouterDbKey) ? 'db' : 'none';

        // Ensure frontend provider never returns a Puter option (Puter is only used as a pure frontend fallback)
        $frontendProvider = $settings['frontend_provider'] ?? 'openrouter';
        if ($frontendProvider === 'puter-js' || $frontendProvider === 'puter') {
            $frontendProvider = 'openrouter';
        }

        // Default model selection varies by provider.
        $defaultModel = $settings['default_model'] ?? '';
        if (empty($defaultModel)) {
            if ($frontendProvider === 'openrouter') {
                // OpenRouter expects a model like openrouter/auto (auto router) or any other supported model ID.
                // If no model is configured, default to the auto router.
                $defaultModel = array_key_first(AIProvider::getProviderConfig('openrouter')['models'] ?? ['openrouter/auto' => 'Auto Router']);
            } else {
                $defaultModel = 'openrouter/auto';
            }
        }

        // Build provider list for frontend use (no API keys exposed)
        $activeProviders = $aiProvider->getActive();
        $providerList = [];
        foreach ($activeProviders as $p) {
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
            'providers' => $providerList,
            'openrouter_key_source' => $openrouterKeySource
        ]);
    });

    // ==================== POST /admin/ai-system/save ====================
    $router->post('/admin/ai-system/save', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);

        // Save general settings
        $settingsToSave = [
            'default_provider' => $_POST['default_provider'] ?? 'kilo',
            'frontend_provider' => $_POST['frontend_provider'] ?? 'openrouter',
            'backend_provider' => $_POST['backend_provider'] ?? $_POST['default_provider'] ?? 'kilo',
            'default_model' => $_POST['default_model'] ?? 'openrouter/auto',
            'max_tokens' => (int)($_POST['max_tokens'] ?? 4000),
            'temperature' => (float)($_POST['temperature'] ?? 0.7),
            'enable_fallback' => isset($_POST['enable_fallback']),
            'content_enhancement_enabled' => isset($_POST['content_enhancement_enabled']),
            'auto_publish_ai_content' => isset($_POST['auto_publish_ai_content']),
            'default_author' => $_POST['default_author'] ?? 'BroxBhai AI',
            // New separate prompts for Admin and Public assistants
            'admin_system_prompt' => $_POST['admin_system_prompt'] ?? '',
            'public_system_prompt' => $_POST['public_system_prompt'] ?? '',
            'system_prompt' => $_POST['system_prompt'] ?? '', // Keep for backwards compatibility
            'custom_instructions' => $_POST['custom_instructions'] ?? ''
        ];

        $aiProvider->updateSettings($settingsToSave);

        // Save API keys
        foreach ($_POST['api_keys'] ?? [] as $providerName => $apiKey) {
            if (!empty(trim($apiKey))) {
                $aiProvider->updateSetting($providerName . '_api_key', trim($apiKey));
            }
        }

        // Save default provider
        if (!empty($_POST['default_provider'])) {
            $provider = $aiProvider->getByName($_POST['default_provider']);
            if ($provider) {
                $aiProvider->setAsDefault($provider['id']);
            }
        }

        showMessage('AI SYSTEM saved successfully!', 'success');
        redirect('/admin/ai-system');
    });

    // ==================== POST /admin/ai-system/add-provider ====================
    $router->post('/admin/ai-system/add-provider', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);

        $displayName = trim($_POST['display_name'] ?? '');
        $apiEndpoint = trim($_POST['api_endpoint'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');

        if (empty($displayName) || empty($apiEndpoint)) {
            showMessage('Please provide provider name and API endpoint.', 'danger');
            redirect('/admin/ai-system');
            return;
        }

        $providerName = strtolower(preg_replace('/[^a-z0-9]/', '_', $displayName));
        $providerName = preg_replace('/_+/', '_', $providerName);

        $providerId = $aiProvider->create([
            'provider_name' => $providerName,
            'display_name' => $displayName,
            'description' => $_POST['description'] ?? 'Custom AI provider',
            'api_endpoint' => $apiEndpoint,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 100
        ]);

        if (!empty($apiKey)) {
            $aiProvider->updateSetting($providerName . '_api_key', $apiKey);
        }

        showMessage('Provider "' . htmlspecialchars($displayName) . '" added successfully!', 'success');
        redirect('/admin/ai-system');
    });

    // ==================== POST /admin/ai-system/update-provider ====================
    $router->post('/admin/ai-system/update-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);

        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid token']);
            return;
        }

        $providerId = (int)($_POST['provider_id'] ?? 0);
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'toggle':
                $provider = $aiProvider->getById($providerId);
                if ($provider) {
                    $aiProvider->update($providerId, ['is_active' => !$provider['is_active']]);
                    echo json_encode(['success' => true, 'is_active' => !$provider['is_active']]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Provider not found']);
                }
                break;

            case 'set_default':
                $aiProvider->setAsDefault($providerId);
                echo json_encode(['success' => true]);
                break;

            case 'test':
                $provider = $aiProvider->getById($providerId);
                if ($provider) {
                    $result = $aiProvider->testConnection($provider['provider_name'], $_POST['model'] ?? null);
                    echo json_encode($result);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Provider not found']);
                }
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
        }
    });

    // ==================== POST /admin/ai-system/delete-provider ====================
    $router->post('/admin/ai-system/delete-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);

        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid token']);
            return;
        }

        $providerId = (int)($_POST['provider_id'] ?? 0);
        $provider = $aiProvider->getById($providerId);

        if (!$provider) {
            echo json_encode(['success' => false, 'error' => 'Provider not found']);
            return;
        }

        if ($provider['provider_name'] === 'custom' && $provider['sort_order'] >= 90) {
            $aiProvider->updateSetting($provider['provider_name'] . '_api_key', '');
            $result = $aiProvider->delete($providerId);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Cannot delete built-in providers']);
        }
    });

    // ==================== API Routes ====================

    // GET /api/ai/providers
    $router->get('/api/ai/providers', function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);
        header('Content-Type: application/json');
        echo json_encode($aiProvider->getActive());
    });

    // GET /api/ai/settings
    $router->get('/api/ai/settings', function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);
        header('Content-Type: application/json');
        echo json_encode($aiProvider->getSettings());
    });

    // POST /api/ai/test
    $router->post('/api/ai/test', function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);
        $result = $aiProvider->testConnection($_POST['provider'] ?? '', $_POST['model'] ?? null);
        header('Content-Type: application/json');
        echo json_encode($result);
    });

    // GET /api/ai/current-provider
    $router->get('/api/ai/current-provider', function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);
        $provider = $aiProvider->getEffectiveProvider();

        if (!$provider) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'No active AI provider configured']);
            return;
        }

        $settings = $aiProvider->getSettings();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'provider' => [
                'name' => $provider['provider_name'],
                'display_name' => $provider['display_name'],
                'models' => array_keys($provider['supported_models'] ?? [])
            ],
            'settings' => [
                'default_model' => $settings['default_model'] ?? 'gpt-4o-mini',
                'max_tokens' => $settings['max_tokens'] ?? 4000,
                'temperature' => $settings['temperature'] ?? 0.7
            ]
        ]);
    });

    // GET /api/ai/models?provider=fireworks
    $router->get('/api/ai/models', function () use ($mysqli) {
        $providerName = $_GET['provider'] ?? '';
        $aiProvider = new AIProvider($mysqli);

        header('Content-Type: application/json');

        if (!$providerName) {
            echo json_encode(['success' => false, 'error' => 'No provider specified']);
            return;
        }

        $remote = $aiProvider->fetchRemoteModels($providerName);
        if (empty($remote)) {
            echo json_encode(['success' => false, 'error' => 'No models available or unable to fetch']);
            return;
        }

        echo json_encode(['success' => true, 'models' => $remote]);
    });

    // POST /api/ai/chat (Public assistant)
    $router->post('/api/ai/chat', function () use ($mysqli) {
        run_middleware('rate_limit', [
            'scope' => 'ai_public_chat',
            'limit' => 30,
            'window' => 60,
            'is_api' => true
        ]);

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        aiChatHandleRequest($input, $mysqli, false, false);
    });

    // POST /api/admin/ai/chat (Admin-only)
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

        // ==================== Knowledge Base Management (Admin) ====================
        require_once __DIR__ . '/../Models/AIKnowledge.php';

        // GET /api/admin/ai-knowledge - list knowledge slices
        $router->get('/api/admin/ai-knowledge', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
            $model = new AIKnowledge($mysqli);
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = 50;
            $offset = ($page - 1) * $limit;
            $rows = $model->list($limit, $offset);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'items' => $rows]);
        });

        // GET /api/admin/ai-knowledge/{id}
        $router->get('/api/admin/ai-knowledge/(\d+)', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
            $model = new AIKnowledge($mysqli);
            $row = $model->getById((int)$id);
            header('Content-Type: application/json');
            echo json_encode(['success' => $row !== null, 'item' => $row]);
        });

        // POST /api/admin/ai-knowledge - create or update
        $router->post('/api/admin/ai-knowledge', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
            $model = new AIKnowledge($mysqli);

            // Support both JSON requests and multipart/form-data file uploads
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($input['id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $content = trim($input['content'] ?? '');
            $source = in_array($input['source_type'] ?? 'text', ['text','pdf']) ? $input['source_type'] : 'text';

            // Handle uploaded PDF file (optional)
            if (!empty($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../public_html/uploads/knowledge';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                $tmp = $_FILES['pdf_file']['tmp_name'];
                $orig = basename($_FILES['pdf_file']['name']);
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
                $target = $uploadDir . '/' . time() . '_' . $safe;
                if (move_uploaded_file($tmp, $target)) {
                    // Store the public path in content for later processing
                    $publicPath = '/uploads/knowledge/' . basename($target);
                    $content = 'FILEPATH:' . $publicPath;
                    $source = 'pdf';
                }
            }

            if ($id > 0) {
                $ok = $model->update($id, ['title' => $title, 'content' => $content, 'source_type' => $source]);
                echo json_encode(['success' => $ok]);
                return;
            }

            $newId = $model->create(['title' => $title, 'content' => $content, 'source_type' => $source]);
            echo json_encode(['success' => $newId > 0, 'id' => $newId]);
        });

        // DELETE /api/admin/ai-knowledge/{id}
        $router->post('/api/admin/ai-knowledge/delete', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
            $model = new AIKnowledge($mysqli);
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID required']);
                return;
            }
            $ok = $model->delete($id);
            echo json_encode(['success' => $ok]);
        });

    // --- ADMIN CHAT MANAGEMENT ROUTES ---
    
    // GET /admin/ai-chats - Conversations Management Dashboard
    $router->get('/admin/ai-chats', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => '/admin'],
            ['label' => 'AI Conversations', 'url' => '/admin/ai-chats']
        ];

        echo $twig->render('admin/ai/chats.twig', [
            'title' => 'AI Conversations',
            'breadcrumbs' => $breadcrumbs,
            'current_page' => 'ai-chats',
            'csrf_token' => generateCsrfToken()
        ]);
    });

    // GET /admin/ai-knowledge - Knowledge Base Management UI
    $router->get('/admin/ai-knowledge', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => '/admin'],
            ['label' => 'AI Knowledge Base', 'url' => '/admin/ai-knowledge']
        ];

        echo $twig->render('admin/ai/knowledge.twig', [
            'title' => 'AI Knowledge Base',
            'breadcrumbs' => $breadcrumbs,
            'csrf_token' => generateCsrfToken()
        ]);
    });

    // GET /api/admin/ai-chats - List all conversations
    $router->get('/api/admin/ai-chats', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
        require_once __DIR__ . '/../Models/AIChatModel.php';
        $chatModel = new AIChatModel($mysqli);
        $page = (int)($_GET['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $convs = $chatModel->listConversations($limit, $offset);
        foreach ($convs as &$conv) {
            if (!isset($conv['visitor_token'])) {
                $conv['visitor_token'] = $conv['guest_token'] ?? null;
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'conversations' => $convs]);
    });

    // GET /api/admin/ai-chats/{id} - Get transcript
    $router->get('/api/admin/ai-chats/(\d+)', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
        require_once __DIR__ . '/../Models/AIChatModel.php';
        $chatModel = new AIChatModel($mysqli);
        $messages = $chatModel->getMessages((int)$id);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'messages' => $messages]);
    });

    // POST /api/admin/ai-chats/reply - Log manual admin response
    $router->post('/api/admin/ai-chats/reply', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
        require_once __DIR__ . '/../Models/AIChatModel.php';
        $chatModel = new AIChatModel($mysqli);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $convId = $input['conversation_id'] ?? 0;
        $content = $input['content'] ?? '';
        
        if (!$convId || !$content) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Conversation ID and content are required']);
            return;
        }

        $result = $chatModel->addMessage((int)$convId, 'assistant', $content);
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
    });

    // POST /api/admin/ai-chats/end - Close conversation
    $router->post('/api/admin/ai-chats/end', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
        require_once __DIR__ . '/../Models/AIChatModel.php';
        $chatModel = new AIChatModel($mysqli);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $convId = $input['conversation_id'] ?? 0;
        
        if (!$convId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Conversation ID is required']);
            return;
        }

        $result = $chatModel->setStatus((int)$convId, 'closed');
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
    });

    // POST /api/ai-system/set-default
    $router->post('/api/ai-system/set-default', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? 0;
        
        if (!$id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ID is required']);
            return;
        }

        $result = $aiProvider->setAsDefault($id);
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
    });

    // POST /api/ai-system/toggle-provider
    $router->post('/api/ai-system/toggle-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? 0;
        $active = $input['active'] ?? false;

        $result = $aiProvider->update($id, ['is_active' => $active]);
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
    });

    // POST /api/ai-system/delete-provider
    $router->post('/api/ai-system/delete-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? 0;

        $provider = $aiProvider->getById($id);
        if (!$provider) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Provider not found']);
            return;
        }

        if ($provider['provider_name'] === 'custom' && $provider['sort_order'] >= 90) {
            $result = $aiProvider->delete($id);
            header('Content-Type: application/json');
            echo json_encode(['success' => $result]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Cannot delete built-in providers']);
        }
    });

    // POST /api/ai-system/test
    $router->post('/api/ai-system/test', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? 0;
        $model = $input['model'] ?? null;

        $provider = $aiProvider->getById($id);
        if ($provider) {
            $result = $aiProvider->testConnection($provider['provider_name'], $model);
            header('Content-Type: application/json');
            echo json_encode($result);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Provider not found']);
        }
    });

