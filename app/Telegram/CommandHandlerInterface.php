<?php
declare(strict_types = 1)
;

namespace App\Telegram;

/**
 * CommandHandlerInterface.php
 * Interface for all Telegram command and callback handlers.
 */
interface CommandHandlerInterface
{
    /**
     * Handle the incoming Telegram update.
     * 
     * @param array $update The update object from Telegram.
     */
    public function handle(array $update): void;
}
