<?php

require_once __DIR__ . '/Config/Db.php';
require_once __DIR__ . '/Config/Functions.php';
require_once __DIR__ . '/app/Models/AIProvider.php';

global $mysqli;

$aiProvider = new AIProvider($mysqli);

// Test OpenRouter connection
echo "Testing OpenRouter connection...\n";
$result = $aiProvider->testConnection('openrouter');
echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// Test Kilo connection
echo "Testing Kilo connection...\n";
$result = $aiProvider->testConnection('kilo');
echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// Get effective provider
echo "Effective provider: " . json_encode($aiProvider->getEffectiveProvider(), JSON_PRETTY_PRINT) . "\n\n";

// Get settings
echo "AI Settings: " . json_encode($aiProvider->getSettings(), JSON_PRETTY_PRINT) . "\n\n";
