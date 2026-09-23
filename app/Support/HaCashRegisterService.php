<?php

namespace App\Support;

use App\Models\HaCashRegister;
use App\Models\HaCashRegisterTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Daily cash register sessions.
 *
 * Expected cash = opening + cash sales + cash-in + due collections
 *                 − change − cash expenses − cash refunds.
 * Closing records actual cash and the computed difference; every
 * open/close/entry is a logged, transactional operation.
 */
class HaCashRegisterService
{
    public function open(int $userId, float $openingBalance = 0.00, string $note = null): HaCashRegister
    {
        return DB::transaction(function () use ($userId, $openingBalance, $note) {
            $existing = HaCashRegister::query()->where('status', 'open')->lockForUpdate()->first();
            if ($existing) {
                throw new \RuntimeException('A register session is already open. Close it first.');
            }

            $register = HaCashRegister::query()->create([
                'opened_by' => $userId,
                'opened_at' => now(),
                'opening_balance' => $openingBalance,
                'status' => 'open',
                'note' => $note,
            ]);

            if ($openingBalance != 0.0) {
                HaCashRegisterTransaction::query()->create([
                    'register_id' => $register->id,
                    'type' => 'cash_in',
                    'amount' => $openingBalance,
                    'note' => 'Opening balance',
                    'created_by' => $userId,
                ]);
            }

            ActivityLogger::log('ha_cash_register', $register->id, 'opened', [
                'opening_balance' => $openingBalance,
            ]);

            return $register;
        });
    }

    public function current(): ?HaCashRegister
    {
        return HaCashRegister::query()->where('status', 'open')->orderByDesc('id')->first();
    }

    public function recordTransaction(
        HaCashRegister $register,
        string $type,
        float $amount,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
        ?int $userId = null,
    ): HaCashRegisterTransaction {
        if (! in_array($type, HaCashRegisterTransaction::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown register transaction type: {$type}");
        }

        return DB::transaction(function () use ($register, $type, $amount, $referenceType, $referenceId, $note, $userId) {
            $fresh = HaCashRegister::query()->whereKey($register->id)->where('status', 'open')->lockForUpdate()->first();
            if (! $fresh) {
                throw new \RuntimeException('Register session is closed.');
            }

            return HaCashRegisterTransaction::query()->create([
                'register_id' => $fresh->id,
                'type' => $type,
                'amount' => round($amount, 2),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Cash-summary totals for the open (or a given) register session.
     */
    public function summary(HaCashRegister $register): array
    {
        $tx = $register->transactions();

        $sum = fn (string $type) => (float) (clone $tx)->where('type', $type)->sum('amount');

        // The opening balance is already booked as a cash_in transaction at
        // open time — do NOT add the header value again (double count).
        $expected = $sum('cash_in')
            + $sum('cash_sale')
            + $sum('due_collection')
            - abs($sum('change'))
            - abs($sum('cash_out'))
            - abs($sum('refund_cash'));

        return [
            'cash_sales' => $sum('cash_sale'),
            'cash_in' => $sum('cash_in'),
            'cash_out' => abs($sum('cash_out')),
            'change_given' => abs($sum('change')),
            'refunds_cash' => abs($sum('refund_cash')),
            'due_collection' => $sum('due_collection'),
            'expected_cash' => round($expected, 2),
        ];
    }

    public function close(HaCashRegister $register, float $actualCash, string $note = null, ?int $userId = null): HaCashRegister
    {
        return DB::transaction(function () use ($register, $actualCash, $note, $userId) {
            $fresh = HaCashRegister::query()->whereKey($register->id)->where('status', 'open')->lockForUpdate()->first();
            if (! $fresh) {
                throw new \RuntimeException('Register session is already closed.');
            }

            $summary = $this->summary($fresh);
            $expected = $summary['expected_cash'];

            $fresh->update([
                'closed_by' => $userId,
                'closed_at' => now(),
                'expected_cash' => $expected,
                'actual_cash' => round($actualCash, 2),
                'difference' => round($actualCash - $expected, 2),
                'status' => 'closed',
                'note' => $note ?? $fresh->note,
            ]);

            ActivityLogger::log('ha_cash_register', $fresh->id, 'closed', [
                'expected' => $expected,
                'actual' => $actualCash,
                'difference' => $fresh->difference,
            ]);

            return $fresh->refresh();
        });
    }
}
