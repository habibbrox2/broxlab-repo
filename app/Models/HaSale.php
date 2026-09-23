<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hero Alif sale (POS or online). Financial row: immutable by policy —
 * refunds create compensating payment rows + stock movements and flip status.
 */
class HaSale extends Model
{
    public const STATUSES = ['held', 'completed', 'refunded', 'cancelled'];

    public const PAYMENT_METHODS = ['cash', 'bkash', 'nagad', 'bank', 'other_mbanking', 'due'];

    use SoftDeletes;

    protected $table = 'ha_sales';

    protected $guarded = [];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'held_at' => 'datetime',
        'completed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(HaSaleItem::class, 'sale_id');
    }

    public function payments()
    {
        return $this->hasMany(HaSalePayment::class, 'sale_id');
    }

    public function customer()
    {
        return $this->belongsTo(HaCustomer::class, 'customer_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function register()
    {
        return $this->belongsTo(HaCashRegister::class, 'register_id');
    }
}
