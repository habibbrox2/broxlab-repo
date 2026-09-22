<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserRecharge;
use App\Models\UserWalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Wallet / balance engine.
 *
 * Rules implemented (per the feature request:
 *   "প্রতিটি সার্ভস এর জন্য টাকা কাটা হবে, সার্ভিস যদি ক্যানে্সল হয় তাহলে টাকা ফেরত যাবে")
 *
 *  - Every balance change is written as a signed ledger row + users.balance update
 *    atomically, so there is always an audit trail to reconcile the wallet.
 *  - Recharges (top-ups) are created pending and must be confirmed (by admin or
 *    by a bKash/Nagad callback) to actually credit the account.
 *  - Paid services debit the user's balance. If balance is insufficient the debit
 *    is refused (returns false) — the caller should then redirect to recharge.
 *  - If a service application that charged the balance is later cancelled,
 *    the full price is refunded via a credit transaction.
 */
class WalletService
{
    /**
     * Current balance as a float (0.0 when the column is null / not selected).
     */
    public function balance(User $user): float
    {
        return (float) ($user->balance ?? 0);
    }

    /**
     * Does this user have enough balance to cover $amount?
     */
    public function canAfford(User $user, float $amount): bool
    {
        return $this->balance($user) >= round($amount, 2);
    }

    /**
     * Create a pending recharge (top-up) request. The wallet is NOT credited
     * here — it only becomes available after confirmRecharge().
     */
    public function createRecharge(User $user, array $data): UserRecharge
    {
        return DB::transaction(function () use ($user, $data) {
            $recharge = UserRecharge::create([
                'user_id' => $user->id,
                'amount' => (float) $data['amount'],
                'currency' => $data['currency'] ?? 'BDT',
                'method' => $data['method'] ?? 'bkash',
                'transaction_id' => $data['transaction_id'] ?? null,
                'payer_phone' => $data['payer_phone'] ?? null,
                'status' => 'pending',
            ]);

            $snapshot = $this->balance($user);
            $this->writeRow($user, 0.0, 'recharge_requested', $snapshot, $snapshot, $recharge, null, [
                'recharge_id' => $recharge->id,
                'method' => $recharge->method,
                'requested_amount' => (float) $recharge->amount,
            ]);

            return $recharge;
        });
    }

    /**
     * Confirm a pending recharge — credits the user's wallet and flips the
     * recharge status to completed. Safe to call from a callback handler.
     *
     * @return bool
     */
    public function confirmRecharge(int $rechargeId, ?string $adminNote = null): bool
    {
        return DB::transaction(function () use ($rechargeId, $adminNote): bool {
            $recharge = UserRecharge::lockForUpdate()->find($rechargeId);

            if (! $recharge || $recharge->status !== 'pending') {
                return false;
            }

            $recharge->status = 'processing';
            $recharge->save();

            $user = User::find($recharge->user_id);
            if (! $user) {
                $recharge->status = 'failed';
                $recharge->save();

                return false;
            }

            // Credit the wallet.
            $this->credit($user, (float) $recharge->amount, 'recharge', $recharge,
                'Wallet top-up via ' . $recharge->method . ' — TrxID ' . ($recharge->transaction_id ?? 'N/A')
            );

            $recharge->status = 'completed';
            $recharge->processed_at = now();
            $recharge->admin_note = $adminNote ?? $recharge->admin_note;
            $recharge->save();

            return true;
        });
    }

    /**
     * Reject/fail a pending recharge.
     */
    public function rejectRecharge(int $rechargeId, string $reason): bool
    {
        $recharge = UserRecharge::find($rechargeId);
        if (! $recharge || in_array($recharge->status, ['completed', 'cancelled', 'failed'], true)) {
            return false;
        }

        $recharge->status = 'failed';
        $recharge->admin_note = ($recharge->admin_note ? $recharge->admin_note . ' | ' : '') . $reason;
        $recharge->processed_at = now();
        $recharge->save();

        return true;
    }

    /**
     * Charge the user's wallet for a paid service application.
     *
     * Records a `service_payment` ledger debit AND writes a row to
     * `service_application_payments` (status=paid) so the existing payments
     * reporting stays consistent.
     *
     * @return bool true if the debit succeeded, false if insufficient balance
     */
    public function chargeForServiceApplication(
        User $user,
        int $serviceId,
        int $applicationId,
        float $amount
    ): bool {
        if ($amount <= 0) {
            return false;
        }

        if (! $this->canAfford($user, $amount)) {
            return false;
        }

        return DB::transaction(function () use ($user, $serviceId, $applicationId, $amount): bool {
            // Debit the wallet first.
            $tx = $this->debit($user, $amount, 'service_payment', null, null, [
                'service_id' => $serviceId,
                'application_id' => $applicationId,
            ]);

            if (! $tx) {
                return false;
            }

            $paymentColumns = [
                'application_id' => $applicationId,
                'user_id' => $user->id,
                'service_id' => $serviceId,
                'mode' => 'wallet',
                'gateway' => 'wallet',
                'payment_method' => 'balance',
                'amount' => $amount,
                'currency' => 'BDT',
                'status' => 'paid',
                'paid_at' => now(),
                'submitted_at' => now(),
            ];

            // Only touch the existing payments table if it actually exists
            // (the bridge is also used in environments where it may not).
            if (DB::getSchemaBuilder()->hasTable('service_application_payments')) {
                DB::table('service_application_payments')->upsert(
                    array_merge($paymentColumns, ['transaction_id' => $tx->id]),
                    ['application_id'],
                    ['status', 'amount', 'updated_at', 'transaction_id', 'paid_at']
                );
            }

            return true;
        });
    }

    /**
     * Refund the wallet when a service application that charged the balance is
     * cancelled / rejected. Credits the user + marks the payment refunded.
     *
     * @return bool true if a refund was issued, false if there was nothing to refund
     */
    public function refundServiceApplication(int $applicationId, string $reason): bool
    {
        return DB::transaction(function () use ($applicationId, $reason): bool {
            $application = DB::table('service_applications')
                ->where('id', $applicationId)
                ->lockForUpdate()
                ->first();

            if (! $application) {
                return false;
            }

            $service = DB::table('services')
                ->where('id', $application->service_id)
                ->first();
            $price = $service ? (float) $service->price : 0.0;

            if ($price <= 0) {
                return false; // free service — nothing to refund
            }

            $user = User::find($application->user_id);
            if (! $user) {
                return false;
            }

            // Avoid double-refund: skip if we already refunded this application
            // (linked through the application_id stored in the JSON meta column).
            $existingRefund = DB::table('user_wallet_transactions')
                ->where('type', 'refund')
                ->whereRaw("JSON_EXTRACT(meta, '$.application_id') = ?", [$application->id])
                ->exists();

            if ($existingRefund) {
                return false;
            }

            $payment = DB::table('service_application_payments')
                ->where('application_id', $applicationId)
                ->lockForUpdate()
                ->first();

            $this->credit($user, $price, 'refund', null, null, [
                'service_id' => $application->service_id,
                'application_id' => $application->id,
                'reason' => $reason,
            ]);

            if ($payment) {
                DB::table('service_application_payments')
                    ->where('id', $payment->id)
                    ->update([
                        'status' => 'refunded',
                        'updated_at' => now(),
                    ]);
            }

            return true;
        });
    }

    // ── Internal: signed ledger writers ───────────────────────────────

    /**
     * Debit the wallet. Returns the transaction row (with balance snapshot),
     * or null if it would overdraw the account — nothing is written on failure.
     */
    protected function debit(User $user, float $amount, string $type, $reference = null, ?string $description = null, ?array $meta = null): ?UserWalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $type, $reference, $description, $meta): ?UserWalletTransaction {
            $user->refresh();
            $before = $this->balance($user);
            $amount = round($amount, 2);

            if ($before < $amount) {
                return null; // insufficient funds — nothing written
            }

            $after  = round($before - $amount, 2);
            $user->forceFill(['balance' => $after])->save();

            return $this->writeRow($user, abs($amount), $type, $before, $after, $reference, $description, $meta);
        });
    }

    /** Credit the wallet (positive amount). Returns the new transaction row. */
    protected function credit(User $user, float $amount, string $type, $reference = null, ?string $description = null, ?array $meta = null): UserWalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $type, $reference, $description, $meta): UserWalletTransaction {
            $user->refresh();
            $before = $this->balance($user);
            $amount = round($amount, 2);
            $after  = round($before + $amount, 2);

            $user->forceFill(['balance' => $after])->save();

            return $this->writeRow($user, abs($amount), $type, $before, $after, $reference, $description, $meta);
        });
    }

    /**
     * Persist one signed ledger row with an explicit before/after snapshot.
     * `$amount` is always the absolute movement (caller already sign-resolved).
     */
    protected function writeRow(User $user, float $amount, string $type, float $before, float $after, $reference = null, ?string $description = null, ?array $meta = null): UserWalletTransaction
    {
        return UserWalletTransaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'currency' => is_array($meta) && isset($meta['currency']) ? $meta['currency'] : 'BDT',
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference ? $reference->id : null,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    /**
     * Ledger listing for the user-facing "My Transactions" page.
     */
    public function transactions(User $user, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return UserWalletTransaction::where('user_id', $user->id)
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Pending (awaiting confirmation) recharges for the user.
     */
    public function pendingRecharges(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return UserRecharge::where('user_id', $user->id)->where('status', 'pending')->latest('id')->get();
    }

    /**
     * Last N wallet movements for compact displays (e.g. dashboard summary).
     */
    public function recentTransactions(User $user, int $limit = 8): \Illuminate\Database\Eloquent\Collection
    {
        return UserWalletTransaction::where('user_id', $user->id)->latest('id')->limit($limit)->get();
    }

    /**
     * Manual admin adjustment of a user's wallet balance.
     * Positive amount = credit, negative amount = debit.
     */
    public function adjustBalance(User $user, float $amount, ?string $description = null): UserWalletTransaction
    {
        $amount = round((float) $amount, 2);

        if ($amount > 0) {
            return $this->credit($user, $amount, 'admin_credit', null, $description ?? 'Admin balance adjustment');
        }

        if ($amount < 0) {
            $tx = $this->debit($user, abs($amount), 'admin_debit', null, $description ?? 'Admin balance adjustment');
            if ($tx === null) {
                throw new \RuntimeException('Insufficient balance for debit adjustment.');
            }
            return $tx;
        }

        throw new \RuntimeException('Adjustment amount must be non-zero.');
    }
}
