<?php
declare(strict_types = 1)
;

namespace App\Telegram\Callbacks;

use App\Telegram\BaseCommandHandler;
use App\Telegram\TelegramSessionManager;
use App\Telegram\InlineKeyboardBuilder;

/**
 * PdfMenuCallback.php
 * Handles the 'menu_pdf' callback query.
 */
class PdfMenuCallback extends BaseCommandHandler
{
    public function handle(array $update): void
    {
        $callbackQuery = $update['callback_query'] ?? null;
        if (!$callbackQuery)
            return;

        $chatId = (string)$callbackQuery['message']['chat']['id'];

        if (!$this->checkFeature('pdf_tools', $update)) {
            return;
        }

        $session = new TelegramSessionManager($this->mysqli);
        $session->setState($chatId, 'pdf_waiting_files', ['files' => []]);

        $text = "📄 *PDF Tools*\n\nPlease upload the PDF files you want to work with. (Max 5 files)\n\nOnce uploaded, you can choose to Merge or Split.";

        $this->telegram->sendMessage($chatId, $text);
    }
}
