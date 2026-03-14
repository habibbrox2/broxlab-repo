<?php
declare(strict_types = 1)
;

namespace App\Telegram;

use mysqli;

/**
 * CallbackRegistry.php
 * Maps Telegram callback data to their respective handlers.
 */
class CallbackRegistry
{
    private array $callbacks = [];
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function register(string $callbackData, string $handlerClass): void
    {
        $this->callbacks[$callbackData] = $handlerClass;
    }

    public function execute(string $callbackData, array $update): void
    {
        if (isset($this->callbacks[$callbackData])) {
            $handlerClass = $this->callbacks[$callbackData];
            if (class_exists($handlerClass)) {
                $handler = new $handlerClass($this->mysqli);
                if ($handler instanceof CommandHandlerInterface) {
                    $handler->handle($update);
                }
            }
        }
    }
}
