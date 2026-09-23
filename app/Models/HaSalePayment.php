<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One payment (or refund) entry against a sale. Multiple rows per sale
 * enable mixed payments (cash + bKash + due).
 */
class HaSalePayment extends Model
{
    public const KINDS = ['payment', 'refund'];

    protected $table = 'ha_sale_payments';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(HaSale::class, 'sale_id');
    }
}
