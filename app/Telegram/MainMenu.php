<?php
declare(strict_types = 1)
;

namespace App\Telegram;

use App\Telegram\InlineKeyboardBuilder;

/**
 * MainMenu.php
 * Generates the main menu for the Telegram bot.
 */
class MainMenu
{
    public static function get(): array
    {
        return (new InlineKeyboardBuilder())
            ->addButton('📱 SMS Gateway', 'menu_sms')
            ->addButton('📨 Incoming SMS', 'menu_incoming')
            ->nextRow()
            ->addButton('📶 SIM Routing', 'menu_sim')
            ->addButton('🖥 Device Control', 'menu_device')
            ->nextRow()
            ->addButton('📄 PDF Tools', 'menu_pdf')
            ->addButton('🕷 Scraper', 'menu_scraper')
            ->nextRow()
            ->addButton('⚙ Feature Settings', 'menu_settings')
            ->build();
    }
}
