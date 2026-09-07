<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Port of the legacy TokenManagementModel invalid-token cleanup, triggered
 * after every failed FCM send (legacy sendNotiUser/sendNotiAdmin behavior):
 *
 *  1. recordTokenFailure()            — bump failure_count, set last_invalidated_at
 *  2. classify_fcm_send_error()       — split UNREGISTERED vs INVALID_REGISTRATION
 *  3. not_registered        → revoke  (token_status = 'revoked')
 *  4. invalid_registration  → delete  (backup row to fcm_tokens_backup first)
 */
class TokenCleanupService
{
    /**
     * Port of classify_fcm_send_error() (FirebaseHelper).
     */
    public function classify(array $result): array
    {
        $error = (string) ($result['error'] ?? '');
        $errorCode = strtoupper(trim((string) ($result['error_code'] ?? '')));
        $errorStatus = strtoupper(trim((string) ($result['error_status'] ?? '')));
        $errLower = strtolower($error);

        $notRegistered = (
            $errorCode === 'UNREGISTERED' ||
            $errorStatus === 'NOT_FOUND' ||
            str_contains($errLower, 'requested entity was not found') ||
            str_contains($errLower, 'registration-token-not-registered') ||
            str_contains($errLower, 'notregistered') ||
            str_contains($errLower, 'not registered') ||
            str_contains($errLower, 'unregistered')
        );

        $invalidRegistration = (
            $errorCode === 'INVALID_ARGUMENT' ||
            $errorCode === 'INVALID_REGISTRATION' ||
            str_contains($errLower, 'invalidregistration') ||
            str_contains($errLower, 'invalid registration') ||
            str_contains($errLower, 'invalid argument') ||
            str_contains($errLower, 'invalid token') ||
            str_contains($errLower, 'not a valid fcm registration token')
        );

        $senderMismatch = (
            $errorCode === 'SENDER_ID_MISMATCH' ||
            $errorCode === 'MISMATCHED_CREDENTIAL' ||
            str_contains($errLower, 'senderid') ||
            (str_contains($errLower, 'sender') && str_contains($errLower, 'mismatch')) ||
            str_contains($errLower, 'mismatched credential')
        );

        return [
            'error' => $error,
            'error_code' => $errorCode,
            'error_status' => $errorStatus,
            'not_registered' => $notRegistered,
            'invalid_registration' => $invalidRegistration,
            'sender_mismatch' => $senderMismatch,
        ];
    }

    /**
     * UPDATE fcm_tokens SET failure_count = COALESCE(failure_count,0)+1,
     * last_invalidated_at = NOW() ... WHERE token = ? OR device_id = ? LIMIT 1
     */
    public function recordTokenFailure(?string $token, ?string $deviceId = null, string $error = ''): bool
    {
        try {
            $query = DB::table('fcm_tokens')
                ->where(fn ($q) => $q->where('token', $token)->orWhere('device_id', $deviceId));

            if ($token === null && $deviceId === null) {
                return false;
            }

            return (bool) $query->limit(1)->update([
                'failure_count' => DB::raw('COALESCE(failure_count, 0) + 1'),
                'last_invalidated_at' => DB::raw('NOW()'),
                'updated_at' => DB::raw('NOW()'),
            ]);
        } catch (\Throwable $e) {
            Log::error('[TokenCleanup] recordTokenFailure error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Revoke an UNREGISTERED token: token_status='revoked', revoked_at=NOW().
     */
    public function revokeByTokenOrDevice(?string $token, ?string $deviceId = null, ?string $reason = null): bool
    {
        try {
            if ($token === null && $deviceId === null) {
                return false;
            }

            return (bool) DB::table('fcm_tokens')
                ->where(fn ($q) => $q->where('token', $token)->orWhere('device_id', $deviceId))
                ->limit(1)
                ->update([
                    'token_status' => 'revoked',
                    'revoked_at' => DB::raw('NOW()'),
                    'updated_at' => DB::raw('NOW()'),
                ]);
        } catch (\Throwable $e) {
            Log::error('[TokenCleanup] revokeByTokenOrDevice error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Backup the token row into fcm_tokens_backup, then delete it
     * (legacy deleteByTokenOrDevice — used for INVALID_REGISTRATION tokens).
     */
    public function deleteByTokenOrDevice(?string $token, ?string $deviceId = null): bool
    {
        try {
            if ($token === null && $deviceId === null) {
                return false;
            }

            if ($token !== null) {
                DB::table('fcm_tokens_backup')->insertUsing(
                    ['user_id', 'device_id', 'token', 'device_type', 'device_name'],
                    DB::table('fcm_tokens')
                        ->select('user_id', 'device_id', 'token', 'device_type', 'device_name')
                        ->where('token', $token)
                        ->limit(1)
                );
            }

            return (bool) DB::table('fcm_tokens')
                ->where(fn ($q) => $q->where('token', $token)->orWhere('device_id', $deviceId))
                ->limit(1)
                ->delete();
        } catch (\Throwable $e) {
            Log::error('[TokenCleanup] deleteByTokenOrDevice error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Full port of the send-failure cleanup block:
     * record failure, then revoke (UNREGISTERED) or delete (INVALID_REGISTRATION).
     */
    public function handleFailedSend(array $sendResult, ?string $token, ?string $deviceId = null): array
    {
        $info = $this->classify($sendResult);
        $action = 'none';

        $this->recordTokenFailure($token, $deviceId, (string) ($sendResult['error'] ?? ''));

        if ($info['not_registered']) {
            $this->revokeByTokenOrDevice($token, $deviceId, 'NotRegistered');
            $action = 'revoked';
        } elseif ($info['invalid_registration']) {
            $this->deleteByTokenOrDevice($token, $deviceId);
            $action = 'deleted';
        }

        Log::info("[TokenCleanup] FCM failure handled: action={$action} token=" . substr((string) $token, 0, 20) . '...', [
            'error_code' => $info['error_code'],
            'error_status' => $info['error_status'],
        ]);

        return $info + ['cleanup_action' => $action];
    }
}