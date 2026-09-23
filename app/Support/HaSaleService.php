<?php

namespace App\Support;

use App\Models\HaCashRegister;
use App\Models\HaCashRegisterTransaction;
use App\Models\HaProduct;
use App\Models\HaSale;
use App\Models\HaSaleItem;
use App\Models\HaSalePayment;
use Illuminate\Support\Facades\DB;

/**
 * Hero Alif sale engine (POS + future online orders).
 *
 * All money math happens SERVER-SIDE from DB prices (spec §11: never trust
 * the client). completeSale() runs one transaction:
 *   validate stock → sale header + items (price/cost snapshots)
 *   → inventory movements (sale) → payment rows → register cash rows
 *   → commit. Any failure rolls the whole sale back.
 *
 * Refunds never rewrite history: a refund flips status and writes
 * compensating payment + inventory rows.
 */
class HaSaleService
{
    public function __construct(
        protected HaInventoryService $inventory,
        protected HaCashRegisterService $registers,
        protected HaCustomerLedgerService $customerLedger,
    ) {}

    /**
     * Complete a POS/online sale in one atomic transaction.
     *
     * @param array $items    [['product_id'=>int,'qty'=>int], ...] — prices come from DB
     * @param array $payments [['method'=>string,'amount'=>float,'reference'=>?string], ...]
     */
    public function completeSale(array $items, array $payments, array $opts = []): array
    {
        return DB::transaction(function () use ($items, $payments, $opts) {
            if ($items === []) {
                throw new \InvalidArgumentException('A sale needs at least one item.');
            }

            // ── Resolve products with locks, server-side prices ─────────
            $productIds = array_map(fn ($i) => (int) $i['product_id'], $items);
            $products = HaProduct::query()
                ->whereIn('id', array_unique($productIds))
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0.0;
            $resolved = [];

            foreach ($items as $line) {
                $pid = (int) $line['product_id'];
                $qty = (int) $line['qty'];
                $product = $products->get($pid);

                if (! $product) {
                    throw new \InvalidArgumentException("Product {$pid} not found or inactive.");
                }
                if ($qty <= 0) {
                    throw new \InvalidArgumentException("Invalid quantity for {$product->name}.");
                }

                if ($product->is_physical) {
                    // Optimistic in-transaction check; the ledger enforces
                    // the authoritative check under the row lock.
                    if ($product->stock_qty < $qty) {
                        throw new \InvalidArgumentException("Insufficient stock for {$product->name} ({$product->stock_qty} left).");
                    }
                }

                $unitPrice = (float) $product->retail_price; // server-side price, never client
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $resolved[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            // ── Totals ──────────────────────────────────────────────────
            $discount = min(max(0.0, (float) ($opts['discount'] ?? 0)), $subtotal);
            $vatPercent = min(max(0.0, (float) ($opts['vat_percent'] ?? 0)), 100);
            $vatAmount = round(($subtotal - $discount) * $vatPercent / 100, 2);
            $grandTotal = round($subtotal - $discount + $vatAmount, 2);

            // ── Payments: server-side allocation, mixed methods ─────────
            // Simple, auditable model: money received (cash + digital) is
            // capped at grand total; any excess is change; the remainder is
            // due. 'due' rows from the client are intent only — the server
            // always computes the authoritative due.
            $digital = 0.0;
            $cashTendered = 0.0;
            $normalizedPayments = [];

            foreach ($payments as $p) {
                $method = (string) ($p['method'] ?? '');
                $amount = round((float) ($p['amount'] ?? 0), 2);

                if (! in_array($method, HaSale::PAYMENT_METHODS, true)) {
                    throw new \InvalidArgumentException("Unknown payment method: {$method}");
                }
                if ($amount <= 0) {
                    continue; // silently skip zero rows from the UI
                }

                $normalizedPayments[] = [
                    'method' => $method,
                    'amount' => $amount,
                    'reference' => $p['reference'] ?? null,
                ];

                if ($method === 'due') {
                    continue; // credit intent; due is computed below
                }
                if ($method === 'cash') {
                    $cashTendered += $amount;
                } else {
                    $digital += $amount;
                }
            }

            $received = round($digital + $cashTendered, 2);
            $change = round(max(0.0, $received - $grandTotal), 2);
            $paid = min($received, $grandTotal);
            $due = round($grandTotal - $paid, 2);

            if ($due > 0 && ($opts['customer_id'] ?? null) === null) {
                throw new \InvalidArgumentException('Due sale requires a customer (walk-in cannot carry due).');
            }

            // ── Header ──────────────────────────────────────────────────
            $sale = HaSale::query()->create([
                'invoice_no' => $this->nextInvoiceNo(),
                'customer_id' => $opts['customer_id'] ?? null,
                'cashier_id' => $opts['cashier_id'] ?? null,
                'sale_type' => $opts['sale_type'] ?? 'pos',
                'status' => 'completed',
                'subtotal' => round($subtotal, 2),
                'discount' => $discount,
                'vat_percent' => $vatPercent,
                'vat_amount' => $vatAmount,
                'grand_total' => $grandTotal,
                'paid_amount' => $paid,
                'due_amount' => $due,
                'change_amount' => $change,
                'register_id' => $opts['register_id'] ?? null,
                'note' => $opts['note'] ?? null,
                'completed_at' => now(),
            ]);

            // ── Items + inventory movements ─────────────────────────────
            foreach ($resolved as $r) {
                HaSaleItem::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $r['product']->id,
                    'name' => $r['product']->name,
                    'sku' => $r['product']->sku,
                    'module' => $r['product']->module,
                    'qty' => $r['qty'],
                    'unit_price' => $r['unit_price'],
                    'unit_cost' => (float) $r['product']->cost_price, // snapshot for P&L
                    'line_total' => $r['line_total'],
                ]);

                if ($r['product']->is_physical) {
                    $this->inventory->recordMovement(
                        $r['product']->id,
                        'sale',
                        -$r['qty'],
                        (float) $r['product']->cost_price,
                        'ha_sales',
                        $sale->id,
                        "Sale {$sale->invoice_no}",
                        $opts['cashier_id'] ?? null
                    );
                }
            }

            // ── Payment rows (what was actually collected) ──────────────
            // Net cash booked = tendered − change (change leaves the drawer
            // immediately); the reduction is applied to the LAST cash row.
            $cashRowsBooked = 0;
            foreach ($normalizedPayments as $p) {
                if ($p['method'] === 'due') {
                    continue; // due is booked as one computed row below
                }

                HaSalePayment::query()->create([
                    'sale_id' => $sale->id,
                    'kind' => 'payment',
                    'method' => $p['method'],
                    'amount' => $p['amount'],
                    'reference' => $p['reference'],
                    'created_by' => $opts['cashier_id'] ?? null,
                ]);

                if ($p['method'] === 'cash') {
                    $cashRowsBooked++;
                }
            }

            if ($change > 0 && $cashRowsBooked > 0) {
                $lastCash = HaSalePayment::query()
                    ->where('sale_id', $sale->id)
                    ->where('method', 'cash')
                    ->orderByDesc('id')
                    ->first();
                $lastCash?->update(['amount' => round(max(0.0, (float) $lastCash->amount - $change), 2)]);
            }

            if ($due > 0) {
                HaSalePayment::query()->create([
                    'sale_id' => $sale->id,
                    'kind' => 'payment',
                    'method' => 'due',
                    'amount' => $due,
                    'created_by' => $opts['cashier_id'] ?? null,
                ]);

                // Customer due ledger entry (Phase 3): +increases due.
                $this->customerLedger->postEntry(
                    (int) $opts['customer_id'],
                    'credit_sale',
                    $due,
                    'ha_sales',
                    $sale->id,
                    "Credit sale {$sale->invoice_no}",
                    $opts['cashier_id'] ?? null
                );
            }

            // ── Register cash rows (cash in/out only) ───────────────────
            if (! empty($opts['register_id'])) {
                $register = HaCashRegister::query()->find($opts['register_id']);
                if ($register) {
                    if ($cashTendered > 0) {
                        $this->registers->recordTransaction($register, 'cash_sale', $cashTendered - $change, 'ha_sales', $sale->id, "Sale {$sale->invoice_no}", $opts['cashier_id'] ?? null);
                    }
                    if ($change > 0) {
                        $this->registers->recordTransaction($register, 'change', -$change, 'change for sale '.$sale->invoice_no);
                    }
                }
            }

            ActivityLogger::log('ha_sale', $sale->id, 'completed', [
                'invoice_no' => $sale->invoice_no,
                'grand_total' => $grandTotal,
                'paid' => $paid,
                'due' => $due,
                'items' => count($resolved),
            ]);

            return [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'grand_total' => $grandTotal,
                'paid' => $paid,
                'due' => $due,
                'change' => $change,
            ];
        });
    }

    /**
     * Hold a sale (park the cart). Stock is NOT deducted until resume.
     */
    public function holdSale(array $items, array $opts = []): int
    {
        return DB::transaction(function () use ($items, $opts) {
            $sale = HaSale::query()->create([
                'invoice_no' => $this->nextInvoiceNo(),
                'customer_id' => $opts['customer_id'] ?? null,
                'cashier_id' => $opts['cashier_id'] ?? null,
                'sale_type' => 'pos',
                'status' => 'held',
                'subtotal' => 0,
                'grand_total' => 0,
                'register_id' => $opts['register_id'] ?? null,
                'note' => $opts['note'] ?? null,
                'held_at' => now(),
            ]);

            foreach ($items as $line) {
                $product = HaProduct::query()->find((int) $line['product_id']);
                if (! $product) {
                    throw new \InvalidArgumentException("Product {$line['product_id']} not found.");
                }

                HaSaleItem::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'module' => $product->module,
                    'qty' => (int) $line['qty'],
                    'unit_price' => (float) $product->retail_price,
                    'unit_cost' => (float) $product->cost_price,
                    'line_total' => round($product->retail_price * $line['qty'], 2),
                ]);
            }

            return $sale->id;
        });
    }

    /**
     * Resume a held sale into the POS cart state (returns items).
     */
    public function resumeSale(int $saleId): array
    {
        $sale = HaSale::query()->where('status', 'held')->findOrFail($saleId);

        return [
            'sale_id' => $sale->id,
            'customer_id' => $sale->customer_id,
            'items' => $sale->items()->get(['product_id', 'qty'])->map(fn ($i) => [
                'product_id' => $i->product_id,
                'qty' => $i->qty,
            ])->all(),
        ];
    }

    /**
     * Refund a completed sale (full). Compensating rows only — history intact.
     */
    public function refundSale(int $saleId, string $reason, int $userId, ?float $amount = null): HaSale
    {
        return DB::transaction(function () use ($saleId, $reason, $userId, $amount) {
            $sale = HaSale::query()->where('status', 'completed')->lockForUpdate()->findOrFail($saleId);

            $amount = $amount ?? $sale->grand_total;
            $amount = min($amount, $sale->paid_amount);

            foreach ($sale->items as $item) {
                $product = HaProduct::query()->lockForUpdate()->find($item->product_id);
                if ($product && $product->is_physical) {
                    $this->inventory->recordMovement(
                        $item->product_id,
                        'sale_return',
                        $item->qty,
                        (float) $item->unit_cost,
                        'ha_sales',
                        $sale->id,
                        "Refund {$sale->invoice_no}: {$reason}",
                        $userId
                    );
                }
            }

            if ($sale->paid_amount > 0) {
                HaSalePayment::query()->create([
                    'sale_id' => $sale->id,
                    'kind' => 'refund',
                    'method' => 'cash', // refund channel configurable in Phase 7
                    'amount' => $sale->paid_amount,
                    'note' => "Refund: {$reason}",
                    'created_by' => $userId,
                ]);
            }

            if ($sale->register_id) {
                $register = HaCashRegister::query()->find($sale->register_id);
                if ($register && $sale->paid_amount > 0) {
                    $this->registers->recordTransaction($register, 'refund_cash', -$sale->paid_amount, 'ha_sales', $sale->id, "Refund {$sale->invoice_no}", $userId);
                }
            }

            $sale->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_reason' => $reason,
            ]);

            ActivityLogger::log('ha_sale', $sale->id, 'refunded', ['reason' => $reason, 'amount' => $amount]);

            return $sale;
        });
    }

    private function nextInvoiceNo(): string
    {
        $prefix = 'INV-'.now()->format('ymd').'-';

        return DB::transaction(function () use ($prefix) {
            $last = HaSale::query()
                ->where('invoice_no', 'like', $prefix.'%')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $seq = $last ? ((int) substr($last->invoice_no, strlen($prefix)) + 1) : 1;

            return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
