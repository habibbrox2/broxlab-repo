<?php
declare(strict_types = 1)
;

namespace App\Telegram;

use mysqli;
use App\FeatureFlags\FeatureManager;
use App\Telegram\TelegramService;

/**
 * BaseCommandHandler.php
 * Abstract base class for all Telegram command and callback handlers.
 */
abstract class BaseCommandHandler implements CommandHandlerInterface
{
    protected mysqli $mysqli;
    protected FeatureManager $featureManager;
    protected TelegramService $telegram;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->featureManager = FeatureManager::getInstance($mysqli);

        $settings = new \AppSettings($mysqli);
        $token = $settings->get('telegram_bot_token', '');
        $this->telegram = new TelegramService($token);
    }

    /**
     * Check if a feature is enabled before handling.
     */
    protected function checkFeature(string $featureKey, array $update): bool
    {
        if (!$this->featureManager->isEnabled($featureKey)) {
            $chatId = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage((string)$chatId, "⚠️ This feature is currently disabled.");
            }
            return false;
        }
        return true;
    }
}
