<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * User-facing service-application lifecycle.
 *
 * Rule (from the feature request):
 *   "প্রতিটি সার্ভিস এর জন্য টাকা কাটা হবে, সার্ভিস যদি ক্যানেন/সেল হয় তাহলে টাকা ফেরত যাবে"
 *
 *  - Applying for a *paid* service debits the user's wallet atomically.
 *  - If the balance is insufficient the stub application is rolled back and
 *    the caller is redirected to the recharge page.
 *  - Cancelling an application that was paid refunds the full price.
 */
class ServiceApplicationService
{
    public function __construct(
        protected WalletService $wallet,
    ) {
    }

    /**
     * Create a service application, charging the wallet for paid services.
     *
     * @return array{application_id:int|null,status:string,charged:bool,balance_before:float,balance_after:float,error:?string}
     */
    public function createApplication(User $user, int $serviceId, array $applicationData, float $amount): array
    {
        return DB::transaction(function () use ($user, $serviceId, $applicationData, $amount): array {
            // One active application per (user, service).
            $dup = DB::table('service_applications')
                ->where('user_id', $user->id)
                ->where('service_id', $serviceId)
                ->whereNull('deleted_at')
                ->whereIn('status', ['pending', 'processing', 'approved'])
                ->exists();

            if ($dup) {
                return [
                    'application_id' => null,
                    'status' => 'pending',
                    'charged' => false,
                    'balance_before' => $this->wallet->balance($user),
                    'balance_after' => $this->wallet->balance($user),
                    'error' => 'already_applied',
                ];
            }

            $appId = DB::table('service_applications')->insertGetId([
                'user_id' => $user->id,
                'service_id' => $serviceId,
                'status' => 'pending',
                'priority' => 'normal',
                'application_data' => $applicationData ? json_encode($applicationData, JSON_UNESCAPED_UNICODE) : null,
                'source' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($amount > 0) {
                $before = $this->wallet->balance($user);

                if (! $this->wallet->chargeForServiceApplication($user, $serviceId, $appId, $amount)) {
                    // Insufficient funds — remove the stub so we leave no orphan application.
                    DB::table('service_applications')->where('id', $appId)->delete();

                    return [
                        'application_id' => $appId,
                        'status' => 'failed',
                        'charged' => false,
                        'balance_before' => $before,
                        'balance_after' => $this->wallet->balance($user),
                        'error' => 'insufficient_balance',
                    ];
                }

                return [
                    'application_id' => $appId,
                    'status' => 'pending',
                    'charged' => true,
                    'balance_before' => $before,
                    'balance_after' => $this->wallet->balance($user),
                    'error' => null,
                ];
            }

            return [
                'application_id' => $appId,
                'status' => 'pending',
                'charged' => false,
                'balance_before' => 0.0,
                'balance_after' => 0.0,
                'error' => null,
            ];
        });
    }

    /**
     * Cancel an application. Paid applications are refunded to the wallet.
     */
    public function cancel(User $user, int $applicationId, string $reason): bool
    {
        return DB::transaction(function () use ($user, $applicationId, $reason): bool {
            $app = DB::table('service_applications')
                ->where('id', $applicationId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $app) {
                return false;
            }

            // Already terminal — nothing to do.
            if (in_array($app->status, ['rejected', 'cancelled', 'cancelled_by_admin'], true)) {
                return false;
            }

            DB::table('service_applications')
                ->where('id', $applicationId)
                ->update([
                    'status' => 'cancelled',
                    'rejection_reason' => $reason,
                    'updated_at' => now(),
                ]);

            // Refund if the application actually charged the wallet.
            $payment = DB::table('service_application_payments')
                ->where('application_id', $applicationId)
                ->first();

            if ($payment && $payment->status === 'paid') {
                $this->wallet->refundServiceApplication($applicationId, $reason);
            }

            return true;
        });
    }

    /**
     * The authenticated user's applications (for the "My Applications" page).
     */
    public function myApplications(User $user, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::table('service_applications as a')
            ->join('services as s', 's.id', '=', 'a.service_id')
            ->where('a.user_id', $user->id)
            ->orderByDesc('a.id')
            ->select('a.*', 's.name as service_name', 's.price as service_price')
            ->paginate($perPage);
    }
}
