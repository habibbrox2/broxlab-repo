<?php
declare(strict_types = 1)
;

namespace App\Telegram\Commands;

use App\Telegram\BaseCommandHandler;
use App\Telegram\MainMenu;

/**
 * StartCommand.php
 * Handles the /start command.
 */
class StartCommand extends BaseCommandHandler
{
    public function handle(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!$message)
            return;

        $chatId = (string)$message['chat']['id'];
        $firstName = $message['from']['first_name'] ?? 'User';

        $text = "👋 *Welcome to BroxBot, {$firstName}!*\n\nYour advanced control panel for SMS, Scrapers, and Device Management.";

        $this->telegram->sendMessage($chatId, $text, MainMenu::get());
    }
}
