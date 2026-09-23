<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Due collection / payment received from a customer.
 */
class HaCustomerPayment extends Model
{
    public const METHODS = ['cash', 'bkash', 'nagad', 'bank', 'other_mbanking'];

    protected $table = 'ha_customer_payments';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(HaCustomer::class, 'customer_id');
    }
}
