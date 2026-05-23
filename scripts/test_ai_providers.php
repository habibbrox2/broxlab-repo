<?php
// One-off script to run verbose connection tests for configured AI providers.
// Usage: php scripts/test_ai_providers.php

require_once __DIR__ . '/../Config/Db.php';
require_once __DIR__ . '/../app/Models/AIProvider.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) {
    echo "No database connection available. Ensure Config/Db.php sets up \$mysqli\n";
    exit(1);
}

$providerModel = new AIProvider($mysqli);
$active = $providerModel->getActive();
if (empty($active)) {
    echo "No active providers configured.\n";
    exit(0);
}

$results = [];
foreach ($active as $p) {
    $name = $p['provider_name'] ?? '';
    if ($name === '') continue;

    $models = $p['supported_models'] ?? [];
    $testModel = (string)($models ? array_key_first($models) : '');

    echo "\n=== Provider: $name (test model: $testModel) ===\n";

    try {
        $res = $providerModel->testConnectionVerbose($name, $testModel);
        echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
