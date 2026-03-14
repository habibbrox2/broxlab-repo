<?php
declare(strict_types = 1)
;

namespace App\Telegram;

use mysqli;

/**
 * CommandRegistry.php
 * Maps Telegram commands to their respective handlers.
 */
class CommandRegistry
{
    private array $commands = [];
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function register(string $command, string $handlerClass): void
    {
        $this->commands[$command] = $handlerClass;
    }

    public function execute(string $commandText, array $update): void
    {
        $parts = explode(' ', $commandText);
        $command = $parts[0];

        if (isset($this->commands[$command])) {
            $handlerClass = $this->commands[$command];
            if (class_exists($handlerClass)) {
                $handler = new $handlerClass($this->mysqli);
                if ($handler instanceof CommandHandlerInterface) {
                    $handler->handle($update);
                }
            }
        }
    }
}
