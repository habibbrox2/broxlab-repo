<?php
// Test script for Telegram system security and functionality
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'Config/Db.php';
require_once BASE_PATH . 'app/Controllers/TelegramSystemController.php';

// Test webhook controller
echo "Testing WebhookController...\n";
$webhookController = new \App\Telegram\WebhookController($mysqli);
echo "WebhookController instantiated successfully\n";

// Test TelegramService
echo "\nTesting TelegramService...\n";
$botToken = (new \AppSettings($mysqli))->get('telegram_bot_token', '');
if (empty($botToken)) {
    echo "⚠️ Telegram bot token not configured\n";
} else {
    $telegramService = new \App\Telegram\TelegramService($botToken);
    echo "TelegramService instantiated successfully\n";
}

// Test BotKernel
echo "\nTesting BotKernel...\n";
$botKernel = new \App\Telegram\BotKernel($mysqli);
echo "BotKernel instantiated successfully\n";

// Test session manager
echo "\nTesting TelegramSessionManager...\n";
$sessionManager = new \App\Telegram\TelegramSessionManager($mysqli);
echo "Session count: " . $sessionManager->getActiveCount() . "\n";
echo "Session manager working\n";

// Test rate limiting
echo "\nTesting rate limiting...\n";
$webhookController->testRateLimiting();
echo "Rate limiting test completed\n";

// Test security features
echo "\nTesting security features...\n";
$webhookController->testSecurityFeatures();
echo "Security features test completed\n";

echo "\n✅ Telegram system test completed successfully!\n";
