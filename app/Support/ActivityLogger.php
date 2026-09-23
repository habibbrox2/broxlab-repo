<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Central audit logging into the existing `activity_logs` table
 * (same shape MobileAdminService uses: user, action, resource, ip, details).
 * Failures must never break the main flow — logging is best-effort.
 */
class ActivityLogger
{
    public static function log(
        string $resourceType,
        ?int $resourceId,
        string $action,
        array $details = [],
        string $status = 'success',
    ): void {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id() ?? 0,
                'role' => 'admin',
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'status' => $status,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
