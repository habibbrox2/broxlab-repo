<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hero Alif purchase invoice (PO receiving).
 */
class HaPurchase extends Model
{
    public const STATUSES = ['ordered', 'received', 'partial', 'cancelled'];

    public const PAYMENT_METHODS = ['cash', 'bkash', 'nagad', 'bank', 'due', 'mixed'];

    use SoftDeletes;

    protected $table = 'ha_purchases';

    protected $guarded = [];

    protected $casts = [
        'purchase_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'transport_cost' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(HaSupplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(HaPurchaseItem::class, 'purchase_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
