<?php

namespace App\Support;

use App\Models\HaInventoryMovement;
use App\Models\HaProduct;
use Illuminate\Support\Facades\DB;

/**
 * Hero Alif inventory ledger.
 *
 * Every stock change is a ledger row; ha_products.stock_qty is a cached
 * balance maintained HERE and nowhere else. All mutations must run inside a
 * database transaction: this service refuses to write otherwise. The product
 * row is locked FOR UPDATE so concurrent POS/checkouts serialize correctly.
 *
 * Movement types (see HaInventoryMovement::TYPES):
 *   purchase, sale, sale_return, purchase_return, adjustment, damage,
 *   transfer_in, transfer_out, opening.
 */
class HaInventoryService
{
    /** Movement types that add stock (positive direction). */
    public const IN_TYPES = ['purchase', 'sale_return', 'purchase_return', 'transfer_in', 'opening'];

    /** Movement types that remove stock. */
    public const OUT_TYPES = ['sale', 'adjustment', 'damage', 'transfer_out'];

    public function recordMovement(
        int $productId,
        string $type,
        int $qty,
        ?float $unitCost = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
        ?int $userId = null,
    ): HaInventoryMovement {
        if (DB::transactionLevel() === 0) {
            // Financial/stock integrity rule: ledger writes never happen
            // outside a transaction (spec §36).
            throw new \RuntimeException('HaInventoryService::recordMovement() requires an active DB transaction.');
        }

        if (! in_array($type, HaInventoryMovement::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown movement type: {$type}");
        }

        if ($qty === 0) {
            throw new \InvalidArgumentException('Movement quantity cannot be zero.');
        }

        // Direction sanity: signed qty must match the type's direction.
        if (in_array($type, self::IN_TYPES, true) && $qty < 0) {
            throw new \InvalidArgumentException("Movement type {$type} must carry a positive quantity.");
        }
        if (in_array($type, self::OUT_TYPES, true) && $qty > 0) {
            throw new \InvalidArgumentException("Movement type {$type} must carry a negative quantity.");
        }

        /** @var HaProduct|null $product */
        $product = HaProduct::query()->whereKey($productId)->lockForUpdate()->first();

        if ($product === null) {
            throw new \RuntimeException("Product {$productId} not found for stock movement.");
        }

        if (! $product->is_physical) {
            throw new \RuntimeException("Product {$productId} is a service; stock movements do not apply.");
        }

        $newBalance = $product->stock_qty + $qty;

        if ($newBalance < 0) {
            throw new \RuntimeException(
                "Insufficient stock for product {$product->sku} ({$product->name}): "
                ."available {$product->stock_qty}, requested change {$qty}."
            );
        }

        $movement = HaInventoryMovement::query()->create([
            'product_id' => $productId,
            'type' => $type,
            'qty' => $qty,
            'balance_after' => $newBalance,
            'unit_cost' => $unitCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'created_by' => $userId,
        ]);

        $product->stock_qty = $newBalance;
        $product->save();

        return $movement;
    }

    /**
     * Record the initial stock of a new physical product (type: opening).
     */
    public function recordOpening(int $productId, int $qty, ?int $userId = null): HaInventoryMovement
    {
        return $this->recordMovement(
            $productId,
            'opening',
            $qty,
            null,
            'manual',
            null,
            'Initial stock on product creation',
            $userId
        );
    }

    /**
     * Movement history for one product (newest first).
     */
    public function movementsFor(int $productId, int $limit = 50): array
    {
        return HaInventoryMovement::query()
            ->where('product_id', $productId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();
    }
}
