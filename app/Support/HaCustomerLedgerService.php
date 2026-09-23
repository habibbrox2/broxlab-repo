<?php

namespace App\Support;

use App\Models\HaCustomer;
use App\Models\HaCustomerLedger;
use App\Models\HaCustomerPayment;
use Illuminate\Support\Facades\DB;

/**
 * Customer due ledger. The due_balance column on ha_customers is a cached
 * mirror of the latest ledger balance; both are written inside the same
 * transaction under a customer row lock (POS + collections can race).
 */
class HaCustomerLedgerService
{
    /**
     * Post a ledger entry. Amount sign: + increases due, − reduces due.
     * MUST run inside a transaction (enforced like the inventory ledger).
     */
    public function postEntry(
        int $customerId,
        string $type,
        float $amount,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
        ?int $userId = null,
    ): HaCustomerLedger {
        if (DB::transactionLevel() === 0) {
            throw new \RuntimeException('HaCustomerLedgerService::postEntry() requires an active DB transaction.');
        }

        if (! in_array($type, HaCustomerLedger::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown ledger entry type: {$type}");
        }

        if ($amount == 0.0) {
            throw new \InvalidArgumentException('Ledger amount cannot be zero.');
        }

        /** @var HaCustomer|null $customer */
        $customer = HaCustomer::query()->whereKey($customerId)->lockForUpdate()->first();
        if (! $customer) {
            throw new \RuntimeException("Customer {$customerId} not found.");
        }

        $balanceBefore = (float) $customer->due_balance;
        $balanceAfter = round($balanceBefore + $amount, 2);

        if ($balanceAfter < 0) {
            throw new \RuntimeException(
                "Ledger entry would make due negative for customer {$customer->name} ({$balanceBefore} + {$amount})."
            );
        }

        $entry = HaCustomerLedger::query()->create([
            'customer_id' => $customerId,
            'type' => $type,
            'amount' => round($amount, 2),
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'created_by' => $userId,
        ]);

        $customer->due_balance = $balanceAfter;
        $customer->save();

        return $entry;
    }

    /**
     * Collect a due payment (partial or full). Writes the payment row +
     * a reducing ledger entry + a cash-register cash row when applicable.
     */
    public function collectPayment(
        int $customerId,
        float $amount,
        string $method,
        ?int $saleId = null,
        ?string $reference = null,
        ?string $note = null,
        ?int $userId = null,
        ?object $register = null,
    ): array {
        return DB::transaction(function () use ($customerId, $amount, $method, $saleId, $reference, $note, $userId, $register) {
            if ($amount <= 0) {
                throw new \InvalidArgumentException('Payment amount must be positive.');
            }
            if (! in_array($method, HaCustomerPayment::METHODS, true)) {
                throw new \InvalidArgumentException("Unknown payment method: {$method}");
            }

            $payment = HaCustomerPayment::query()->create([
                'customer_id' => $customerId,
                'sale_id' => $saleId,
                'amount' => round($amount, 2),
                'method' => $method,
                'reference' => $reference,
                'note' => $note,
                'received_by' => $userId,
            ]);

            $this->postEntry(
                $customerId,
                'payment',
                -$amount,
                'ha_customer_payments',
                $payment->id,
                $note ?? ($saleId ? "Due collection against sale #{$saleId}" : 'Due collection'),
                $userId
            );

            if ($register && $method === 'cash') {
                app(HaCashRegisterService::class)->recordTransaction(
                    $register,
                    'due_collection',
                    $amount,
                    'ha_customer_payments',
                    $payment->id,
                    $note ?? 'Due collection',
                    $userId
                );
            }

            ActivityLogger::log('ha_customer_payment', $payment->id, 'collected', [
                'customer_id' => $customerId, 'amount' => $amount, 'method' => $method,
            ]);

            $customer = HaCustomer::query()->find($customerId);

            return [
                'payment_id' => $payment->id,
                'amount' => round($amount, 2),
                'remaining_due' => (float) $customer->due_balance,
            ];
        });
    }

    /**
     * Statement rows (newest first) for the PDF/export + profile page.
     */
    public function statement(int $customerId, int $limit = 200): array
    {
        return HaCustomerLedger::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->all();
    }
}
