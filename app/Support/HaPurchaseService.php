<?php

namespace App\Support;

use App\Models\HaPurchase;
use App\Models\HaPurchaseItem;
use Illuminate\Support\Facades\DB;

/**
 * Purchase receiving for Hero Alif.
 *
 * createPurchase() is fully transactional: purchase header + items +
 * inventory movements are written together or not at all. Payment
 * (paid/due split) lands on the purchase header; a full supplier ledger
 * arrives in Phase 3.
 */
class HaPurchaseService
{
    public function __construct(
        protected HaInventoryService $inventory,
    ) {}

    public function list(string $search = '', string $status = '', int $supplierId = 0, int $perPage = 50)
    {
        $q = HaPurchase::query()
            ->with(['supplier:id,name,shop_name'])
            ->orderByDesc('id');

        if ($search !== '') {
            $term = '%'.$search.'%';
            $q->where('invoice_no', 'like', $term);
        }

        if ($status !== '') {
            $q->where('status', $status);
        }

        if ($supplierId > 0) {
            $q->where('supplier_id', $supplierId);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?HaPurchase
    {
        return HaPurchase::query()
            ->with(['supplier', 'items.product:id,name,sku,unit'])
            ->find($id);
    }

    /**
     * Create a purchase with items and inventory movements atomically.
     *
     * @param array $items [['product_id'=>int,'qty'=>int,'unit_cost'=>float], ...]
     */
    public function createPurchase(array $data, array $items, int $userId): array
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            if ($items === []) {
                throw new \InvalidArgumentException('A purchase needs at least one item.');
            }

            $subtotal = 0.0;
            foreach ($items as $i => $item) {
                $qty = (int) ($item['qty'] ?? 0);
                $cost = (float) ($item['unit_cost'] ?? 0);
                if ($qty <= 0 || $cost < 0) {
                    throw new \InvalidArgumentException("Invalid qty/cost on item row {$i}.");
                }
                $subtotal += $qty * $cost;
            }

            $discount = (float) ($data['discount'] ?? 0);
            $transport = (float) ($data['transport_cost'] ?? 0);
            $grandTotal = round($subtotal - $discount + $transport, 2);
            $paid = min((float) ($data['paid_amount'] ?? 0), $grandTotal);
            $due = round($grandTotal - $paid, 2);

            $purchase = HaPurchase::query()->create([
                'invoice_no' => $this->nextInvoiceNo(),
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'subtotal' => round($subtotal, 2),
                'discount' => $discount,
                'transport_cost' => $transport,
                'grand_total' => $grandTotal,
                'paid_amount' => $paid,
                'due_amount' => $due,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'status' => $data['status'] ?? 'received',
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                $qty = (int) $item['qty'];
                $cost = (float) $item['unit_cost'];

                HaPurchaseItem::query()->create([
                    'purchase_id' => $purchase->id,
                    'product_id' => (int) $item['product_id'],
                    'qty' => $qty,
                    'received_qty' => $qty,
                    'unit_cost' => $cost,
                    'line_total' => round($qty * $cost, 2),
                ]);

                // Stock in, with the purchase as ledger reference.
                $this->inventory->recordMovement(
                    (int) $item['product_id'],
                    'purchase',
                    $qty,
                    $cost,
                    'ha_purchases',
                    $purchase->id,
                    "Purchase {$purchase->invoice_no}",
                    $userId
                );
            }

            return [
                'id' => $purchase->id,
                'invoice_no' => $purchase->invoice_no,
                'grand_total' => $grandTotal,
                'due_amount' => $due,
            ];
        });
    }

    private function nextInvoiceNo(): string
    {
        $prefix = 'PINV-'.now()->format('ymd').'-';

        return DB::transaction(function () use ($prefix) {
            $last = HaPurchase::query()
                ->where('invoice_no', 'like', $prefix.'%')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $seq = $last
                ? ((int) substr($last->invoice_no, strlen($prefix)) + 1)
                : 1;

            return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
