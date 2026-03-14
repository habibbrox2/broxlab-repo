<?php
declare(strict_types = 1)
;

namespace App\Telegram;

/**
 * InlineKeyboardBuilder.php
 * Fluent interface for building Telegram inline keyboards.
 */
class InlineKeyboardBuilder
{
    private array $keyboard = [];
    private array $currentRow = [];

    public function addButton(string $text, string $callbackData): self
    {
        $this->currentRow[] = [
            'text' => $text,
            'callback_data' => $callbackData
        ];
        return $this;
    }

    public function nextRow(): self
    {
        if (!empty($this->currentRow)) {
            $this->keyboard[] = $this->currentRow;
            $this->currentRow = [];
        }
        return $this;
    }

    public function build(): array
    {
        $this->nextRow();
        return ['inline_keyboard' => $this->keyboard];
    }
}
