<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Line item of a Hero Alif purchase.
 */
class HaPurchaseItem extends Model
{
    protected $table = 'ha_purchase_items';

    protected $guarded = [];

    protected $casts = [
        'qty' => 'integer',
        'received_qty' => 'integer',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function purchase()
    {
        return $this->belongsTo(HaPurchase::class, 'purchase_id');
    }

    public function product()
    {
        return $this->belongsTo(HaProduct::class, 'product_id');
    }
}
