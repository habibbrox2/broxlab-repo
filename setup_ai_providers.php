<?php

require_once __DIR__ . '/Config/Db.php';
require_once __DIR__ . '/Config/Functions.php';
require_once __DIR__ . '/app/Models/AIProvider.php';

global $mysqli;

$aiProvider = new AIProvider($mysqli);

// Default providers to insert
$defaultProviders = [
    [
        'provider_name' => 'openrouter',
        'display_name' => 'OpenRouter',
        'description' => 'Unified API for multiple AI models',
        'api_endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
        'is_active' => 1,
        'is_default' => 1,
        'sort_order' => 1,
        'supported_models' => json_encode(['openrouter/auto' => 'Auto (Recommended)'])
    ],
    [
        'provider_name' => 'kilo',
        'display_name' => 'Kilo.ai',
        'description' => 'Kilo AI provider',
        'api_endpoint' => 'https://api.kilo.ai/api/gateway',
        'is_active' => 1,
        'is_default' => 0,
        'sort_order' => 2,
        'supported_models' => json_encode(['kilo/kilo' => 'Kilo Model'])
    ],
    [
        'provider_name' => 'ollama',
        'display_name' => 'Ollama (Local)',
        'description' => 'Local Ollama instance',
        'api_endpoint' => 'http://localhost:11434',
        'is_active' => 1,
        'is_default' => 0,
        'sort_order' => 0,
        'supported_models' => json_encode([])
    ]
];

foreach ($defaultProviders as $provider) {
    // Check if already exists
    $stmt = $mysqli->prepare("SELECT id FROM ai_providers WHERE provider_name = ?");
    $stmt->bind_param('s', $provider['provider_name']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Insert
        $stmt = $mysqli->prepare("INSERT INTO ai_providers (provider_name, display_name, description, api_endpoint, is_active, is_default, sort_order, supported_models) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssiiis', $provider['provider_name'], $provider['display_name'], $provider['description'], $provider['api_endpoint'], $provider['is_active'], $provider['is_default'], $provider['sort_order'], $provider['supported_models']);
        $stmt->execute();
        echo "Inserted provider: " . $provider['provider_name'] . "\n";
    } else {
        echo "Provider already exists: " . $provider['provider_name'] . "\n";
    }
}

echo "Default providers setup complete.\n";
