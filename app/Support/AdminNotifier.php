<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Port of the legacy sendNotiAdmin()/sendNotiUser() flow (push channel only,
 * matching how the newsletter subscribe route used it): look up admin users,
 * push to their granted FCM tokens, and record delivery in notification_logs.
 *
 * Invalid-token cleanup mirrors legacy TokenManagementModel behavior
 * (recordTokenFailure → revoke UNREGISTERED / delete INVALID_REGISTRATION).
 */
class AdminNotifier
{
    public function __construct(
        protected FcmService $fcm,
        protected TokenCleanupService $cleanup,
    ) {}

    /**
     * Admin user IDs — same query as legacy NewsletterModel::getAdminUserIds().
     */
    public function adminIds(): array
    {
        return DB::table('users as u')
            ->join('user_roles as ur', 'ur.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->whereIn('r.name', ['admin', 'super_admin'])
            ->where('u.status', 'active')
            ->distinct()
            ->pluck('u.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Push a notification to all admins and log each delivery.
     */
    public function notifyAdmins(string $title, string $body, array $data = []): array
    {
        $summary = ['success' => 0, 'failed' => 0, 'details' => []];

        foreach ($this->adminIds() as $adminId) {
            $result = $this->notifyUser($adminId, $title, $body, $data);
            $summary['success'] += $result['success'];
            $summary['failed'] += $result['failed'];
            $summary['details'][] = ['user_id' => $adminId, 'result' => $result];
        }

        return $summary;
    }

    /**
     * Public wrapper for the per-user push (used by the post-approval flow in
     * AdminPostService, mirroring legacy notifyPostApproval → sendNotiUser).
     */
    public function notifyUserById(int $userId, string $title, string $body, array $data = []): array
    {
        return $this->notifyUser($userId, $title, $body, $data);
    }

    protected function notifyUser(int $userId, string $title, string $body, array $data): array
    {
        $result = ['success' => 0, 'failed' => 0, 'details' => []];

        if (! config('services.fcm.enabled', true)) {
            Log::info("AdminNotifier: FCM disabled — push skipped for user {$userId}");

            return $result;
        }

        // Same query as legacy: fcm_tokens WHERE user_id = ? AND permission='granted'
        $tokens = DB::table('fcm_tokens')
            ->where('user_id', $userId)
            ->where('permission', 'granted')
            ->get(['token', 'device_id']);

        foreach ($tokens as $row) {
            $sent = $this->fcm->send($row->token, $title, $body, $data);

            $status = $sent['success'] ? 'sent' : 'failed';
            $result[$status === 'sent' ? 'success' : 'failed']++;

            // Record delivery (same columns as legacy logDelivery())
            DB::table('notification_logs')->insert([
                'notification_id' => null,
                'user_id' => $userId,
                'device_id' => $row->device_id,
                'channel' => 'push',
                'ip_address' => null,
                'token' => $row->token,
                'status' => $status,
                'response' => $sent['error'],
                'message_id' => $sent['messageId'],
                'provider_response' => $sent['provider_response'],
                'metadata' => json_encode($data),
            ]);

            // Invalid-token cleanup — legacy TokenManagementModel parity
            if (! $sent['success']) {
                try {
                    $this->cleanup->handleFailedSend($sent, $row->token, $row->device_id);
                } catch (\Throwable $e) {
                    Log::error('AdminNotifier: token cleanup error: ' . $e->getMessage());
                }
            }

            $result['details'][] = [
                'device' => $row->device_id,
                'token' => substr((string) $row->token, 0, 20).'...',
                'status' => $status,
                'messageId' => $sent['messageId'],
            ];
        }

        if ($tokens->isEmpty()) {
            Log::info("AdminNotifier: no granted FCM tokens for admin user {$userId}");
        }

        return $result;
    }
}