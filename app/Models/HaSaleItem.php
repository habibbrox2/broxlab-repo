<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sale line item with price/cost snapshots for exact revenue + P&L.
 */
class HaSaleItem extends Model
{
    protected $table = 'ha_sale_items';

    protected $guarded = [];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(HaSale::class, 'sale_id');
    }

    public function product()
    {
        return $this->belongsTo(HaProduct::class, 'product_id');
    }
}
