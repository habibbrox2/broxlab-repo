<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable-ish inventory ledger row. Rows are only inserted (and reversed
 * with a compensating row), never edited or deleted by application code.
 */
class HaInventoryMovement extends Model
{
    public const TYPES = [
        'purchase', 'sale', 'sale_return', 'purchase_return',
        'adjustment', 'damage', 'transfer_in', 'transfer_out', 'opening',
    ];

    protected $table = 'ha_inventory_movements';

    protected $guarded = [];

    protected $casts = [
        'qty' => 'integer',
        'balance_after' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(HaProduct::class, 'product_id');
    }
}
