<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Port of the legacy app/Models/ContactModel.php — the public contact form.
 *
 * Parity: inserts into `contact_messages` with the same columns, and exposes
 * the admin-user lookup the push-notification flow needs.
 */
class ContactService
{
    /**
     * Insert a contact message (same columns as legacy createMessage()).
     */
    public function createMessage(string $name, string $email, string $subject, string $message, ?string $ip = null): int|false
    {
        try {
            return DB::table('contact_messages')->insertGetId([
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message,
                'ip_address' => $ip,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ContactService::createMessage failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Admin user IDs — same query as legacy ContactModel::getAdminUserIds().
     */
    public function getAdminUserIds(): array
    {
        try {
            return DB::table('users as u')
                ->join('user_roles as ur', 'ur.user_id', '=', 'u.id')
                ->join('roles as r', 'r.id', '=', 'ur.role_id')
                ->whereIn('r.name', ['admin', 'super_admin'])
                ->where('u.status', 'active')
                ->distinct()
                ->pluck('u.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            Log::error('ContactService::getAdminUserIds failed: ' . $e->getMessage());

            return [];
        }
    }
}