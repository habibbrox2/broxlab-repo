<?php
declare(strict_types = 1)
;

namespace App\Telegram\Callbacks;

use App\Telegram\BaseCommandHandler;
use App\Telegram\TelegramSessionManager;

/**
 * ScraperMenuCallback.php
 * Handles the 'menu_scraper' callback query.
 */
class ScraperMenuCallback extends BaseCommandHandler
{
    public function handle(array $update): void
    {
        $callbackQuery = $update['callback_query'] ?? null;
        if (!$callbackQuery)
            return;

        $chatId = (string)$callbackQuery['message']['chat']['id'];

        if (!$this->checkFeature('data_scraper', $update)) {
            return;
        }

        $session = new TelegramSessionManager($this->mysqli);
        $session->setState($chatId, 'scraper_waiting_url', []);

        $text = "🕷 *Data Scraper Bot*\n\nPlease enter the URL you want to scrape metadata from (e.g., https://example.com):";

        $this->telegram->sendMessage($chatId, $text);
    }
}
