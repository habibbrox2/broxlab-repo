<?php

/**
 * AISettingsController.php
 * Handles AI provider configuration routes in the Admin Panel.
 * All database operations are handled by AIProvider model.
 */

require_once __DIR__ . '/../Models/AIProvider.php';
require_once __DIR__ . '/../Models/AppSettings.php';

// Check if router is available
if (isset($router)) {

    // ==================== GET /admin/ai-settings ====================
    $router->get('/admin/ai-settings', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
        $aiProvider = new AIProvider($mysqli);

        $providers = $aiProvider->getAll();
        $settings = $aiProvider->getSettings();
        $defaultProvider = $aiProvider->getDefault();
        $providerConfigs = AIProvider::getAllProviderConfigs();

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => '/admin'],
            ['label' => 'AI Settings', 'url' => '/admin/ai-settings']
        ];

        echo $twig->render('admin/ai-settings.twig', [
            'title' => 'AI Settings',
            'breadcrumbs' => $breadcrumbs,
            'providers' => $providers,
            'settings' => $settings,
            'default_provider' => $defaultProvider,
            'provider_configs' => $providerConfigs,
            'csrf_token' => generateCsrfToken()
        ]);
    });

    // ==================== GET /api/ai-settings/frontend ====================
    $router->get('/api/ai-settings/frontend', function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);
        $settings = $aiProvider->getSettings();

        header('Content-Type: application/json');
        echo json_encode([
            'provider' => $settings['frontend_provider'] ?? 'openrouter',
            'model' => $settings['default_model'] ?? 'openai/gpt-5.2',
            // include transparent API keys for client JS (will be kept secret in production)
            'fireworks_api_key' => $settings['fireworks_api_key'] ?? '',
            'openrouter_api_key' => $settings['openrouter_api_key'] ?? ''
        ]);
    });

    // ==================== POST /admin/ai-settings/save ====================
    $router->post('/admin/ai-settings/save', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);

        // Save general settings
        $settingsToSave = [
            'default_provider' => $_POST['default_provider'] ?? 'kilo',
            'frontend_provider' => $_POST['frontend_provider'] ?? 'openrouter',
            'backend_provider' => $_POST['backend_provider'] ?? $_POST['default_provider'] ?? 'kilo',
            'default_model' => $_POST['default_model'] ?? 'openai/gpt-5.2',
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

        showMessage('AI settings saved successfully!', 'success');
        redirect('/admin/ai-settings');
    });

    // ==================== POST /admin/ai-settings/add-provider ====================
    $router->post('/admin/ai-settings/add-provider', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
        $aiProvider = new AIProvider($mysqli);

        $displayName = trim($_POST['display_name'] ?? '');
        $apiEndpoint = trim($_POST['api_endpoint'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');

        if (empty($displayName) || empty($apiEndpoint)) {
            showMessage('Please provide provider name and API endpoint.', 'danger');
            redirect('/admin/ai-settings');
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
        redirect('/admin/ai-settings');
    });

    // ==================== POST /admin/ai-settings/update-provider ====================
    $router->post('/admin/ai-settings/update-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
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

    // ==================== POST /admin/ai-settings/delete-provider ====================
    $router->post('/admin/ai-settings/delete-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
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
}
