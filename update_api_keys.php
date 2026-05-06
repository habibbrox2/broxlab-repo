<?php

require_once __DIR__ . '/Config/Db.php';
require_once __DIR__ . '/Config/Functions.php';

global $mysqli;

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get API keys from env
$openrouterKey = $_ENV['OPENROUTER_API_KEY'] ?? '';
$kiloKey = $_ENV['KILO_API_KEY'] ?? '';

// Update settings
$updates = [
    'openrouter_api_key' => $openrouterKey,
    'kilo_api_key' => $kiloKey,
];

foreach ($updates as $key => $value) {
    $stmt = $mysqli->prepare("UPDATE ai_settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->bind_param('ss', $value, $key);
    $stmt->execute();
    echo "Updated $key\n";
}

echo "API keys updated from .env\n";
