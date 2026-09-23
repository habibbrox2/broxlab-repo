<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hero Alif online order. Status flow:
 * pending → confirmed → processing → ready → shipped → delivered
 * (cancelled/returned as terminal side-states).
 */
class HaOrder extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'processing', 'ready', 'shipped', 'delivered', 'cancelled', 'returned'];

    public const PAYMENT_METHODS = ['cash_on_delivery', 'bkash', 'nagad', 'bank'];

    public const PAYMENT_STATUSES = ['unpaid', 'advance_paid', 'paid'];

    use SoftDeletes;

    protected $table = 'ha_orders';

    protected $guarded = [];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'placed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(HaOrderItem::class, 'order_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(HaOrderStatusHistory::class, 'order_id')->orderByDesc('id');
    }

    public function customer()
    {
        return $this->belongsTo(HaCustomer::class, 'customer_id');
    }
}
