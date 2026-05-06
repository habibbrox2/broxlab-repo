<?php

require_once __DIR__ . '/Config/Db.php';
require_once __DIR__ . '/Config/Functions.php';

global $mysqli;

// Default AI settings
$defaultSettings = [
    ['setting_key' => 'frontend_provider', 'setting_value' => 'openrouter', 'setting_type' => 'string', 'description' => 'Frontend AI provider'],
    ['setting_key' => 'backend_provider', 'setting_value' => 'kilo', 'setting_type' => 'string', 'description' => 'Backend AI provider'],
    ['setting_key' => 'default_model', 'setting_value' => 'openrouter/auto', 'setting_type' => 'string', 'description' => 'Default AI model'],
    ['setting_key' => 'frontend_model', 'setting_value' => 'openrouter/auto', 'setting_type' => 'string', 'description' => 'Frontend model'],
    ['setting_key' => 'backend_model', 'setting_value' => 'kilo/kilo', 'setting_type' => 'string', 'description' => 'Backend model'],
    ['setting_key' => 'max_tokens', 'setting_value' => '4000', 'setting_type' => 'number', 'description' => 'Max tokens per request'],
    ['setting_key' => 'temperature', 'setting_value' => '0.7', 'setting_type' => 'number', 'description' => 'AI temperature'],
    ['setting_key' => 'ai_enabled', 'setting_value' => 'true', 'setting_type' => 'boolean', 'description' => 'AI system enabled'],
    ['setting_key' => 'streaming_enabled', 'setting_value' => 'false', 'setting_type' => 'boolean', 'description' => 'Streaming responses enabled'],
    ['setting_key' => 'kb_use_nodejs', 'setting_value' => 'false', 'setting_type' => 'boolean', 'description' => 'Use Node.js for KB'],
    ['setting_key' => 'admin_system_prompt', 'setting_value' => 'You are a helpful AI assistant for BroxBhai admin panel that can help with content management, user management, analytics, and website administration tasks.', 'setting_type' => 'string', 'description' => 'Admin system prompt'],
    ['setting_key' => 'public_system_prompt', 'setting_value' => 'You are a helpful AI assistant for BroxBhai website visitors. You can answer questions about the website content, services, and provide general information.', 'setting_type' => 'string', 'description' => 'Public system prompt'],
    ['setting_key' => 'openrouter_api_key', 'setting_value' => '', 'setting_type' => 'string', 'description' => 'OpenRouter API key', 'is_sensitive' => 1],
    ['setting_key' => 'kilo_api_key', 'setting_value' => '', 'setting_type' => 'string', 'description' => 'Kilo API key', 'is_sensitive' => 1],
];

foreach ($defaultSettings as $setting) {
    // Check if already exists
    $stmt = $mysqli->prepare("SELECT setting_key FROM ai_settings WHERE setting_key = ?");
    $stmt->bind_param('s', $setting['setting_key']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Insert
        $stmt = $mysqli->prepare("INSERT INTO ai_settings (setting_key, setting_value, setting_type, description, is_sensitive) VALUES (?, ?, ?, ?, ?)");
        $is_sensitive = $setting['is_sensitive'] ?? 0;
        $stmt->bind_param('sssss', $setting['setting_key'], $setting['setting_value'], $setting['setting_type'], $setting['description'], $is_sensitive);
        $stmt->execute();
        echo "Inserted setting: " . $setting['setting_key'] . "\n";
    } else {
        echo "Setting already exists: " . $setting['setting_key'] . "\n";
    }
}

echo "Default AI settings setup complete.\n";
