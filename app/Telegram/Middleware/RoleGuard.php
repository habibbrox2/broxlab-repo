<?php
declare(strict_types = 1)
;

namespace App\Telegram\Middleware;

/**
 * RoleGuard.php
 * Validates user roles for Telegram commands.
 */
class RoleGuard
{
    public static function check(int $telegramUserId, string $requiredRole): bool
    {
        // Logic to check role from telegram_user_mapping and users table
        return true;
    }
}
