<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Online order line item (price snapshot at placement).
 */
class HaOrderItem extends Model
{
    protected $table = 'ha_order_items';

    protected $guarded = [];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(HaOrder::class, 'order_id');
    }
}
