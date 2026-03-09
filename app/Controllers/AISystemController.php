<?php

/**
 * AISystemController.php
 * Handles AI provider configuration routes in the Admin Panel.
 * All database operations are handled by AIProvider model.
 */

require_once __DIR__ . '/../Models/AIProvider.php';
require_once __DIR__ . '/../Models/AppSettings.php';

// Check if router is available
if (isset($router)) {

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

        echo $twig->render('admin/ai-system.twig', [
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

        // Prefer stored settings, but fall back to environment variables when present.
        $fireworksDbKey = $settings['fireworks_api_key'] ?? '';
        $fireworksEnvKey = getenv('FIREWORKS_API_KEY') ?: '';
        $fireworksKey = $fireworksDbKey ?: $fireworksEnvKey;

        $openrouterDbKey = $settings['openrouter_api_key'] ?? '';
        $openrouterKey = $openrouterDbKey;

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
                $defaultModel = array_key_first(self::getProviderConfig('openrouter')['models'] ?? ['openrouter/auto' => 'Auto Router']);
            } else {
                $defaultModel = 'openrouter/auto';
            }
        }

        // Build provider list for frontend use (includes API keys for active providers)
        $activeProviders = $aiProvider->getActive();
        $providerList = [];
        foreach ($activeProviders as $p) {
            $providerName = $p['provider_name'];
            $key = '';

            if ($providerName === 'openrouter') {
                $key = $settings['openrouter_api_key'] ?? '';
            } else {
                $envKey = strtoupper($providerName) . '_API_KEY';
                $key = getenv($envKey) ?: ($settings[$providerName . '_api_key'] ?? '');
            }

            $providerList[] = [
                'provider_name' => $providerName,
                'display_name' => $p['display_name'],
                'api_key' => $key,
                'has_api_key' => !empty($key),
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
            // include transparent API keys for client JS (will be kept secret in production)
            'fireworks_api_key' => $fireworksKey,
            'openrouter_api_key' => $openrouterKey,
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

    // POST /api/ai-system/chat
    // Proxies chat requests to AI providers using server-side API keys.
    $router->post('/api/ai-system/chat', function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);
        
        // Get request data
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $messages = $input['messages'] ?? [];
        $provider = $input['provider'] ?? '';
        $model = $input['model'] ?? '';
        $options = $input['options'] ?? [];
        
        if (empty($messages)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Messages are required']);
            return;
        }

        // If provider/model not specified, use defaults
        if (empty($provider) || empty($model)) {
            $settings = $aiProvider->getSettings();
            $effective = $aiProvider->getEffectiveProvider();
            
            $provider = $provider ?: ($effective['provider_name'] ?? 'openrouter');
            $model = $model ?: ($settings['default_model'] ?? 'openrouter/auto');
        }

        // Call the API
        $response = $aiProvider->callAPI($provider, $model, $messages, $options);
        
        header('Content-Type: application/json');
        echo json_encode($response);
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
}
