<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hero Alif product. Physical goods track stock through the
 * ha_inventory_movements ledger — the stock_qty column is a cached balance
 * maintained by HaInventoryService inside transactions, never written raw.
 */
class HaProduct extends Model
{
    public const MODULES = ['smart_bazar', 'mustard_oil', 'fuel', 'machinery', 'printing', 'other'];

    public const UNITS = ['pcs', 'liter', 'kg', 'box', 'meter', 'service'];

    use SoftDeletes;

    protected $table = 'ha_products';

    protected $guarded = [];

    protected $casts = [
        'is_physical' => 'boolean',
        'is_active' => 'boolean',
        'stock_qty' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'reorder_level' => 'integer',
        'cost_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'attributes' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(HaCategory::class, 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(HaBrand::class, 'brand_id');
    }

    public function movements()
    {
        return $this->hasMany(HaInventoryMovement::class, 'product_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeModule(Builder $q, string $module): Builder
    {
        return $q->where('module', $module);
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->where('is_physical', true)
            ->whereColumn('stock_qty', '<=', 'reorder_level');
    }
}
