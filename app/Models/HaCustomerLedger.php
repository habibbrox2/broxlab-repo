<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Customer due ledger — append-only. Never update or delete rows in app
 * code; corrections are reversal entries (type: adjustment/refund_reversal).
 */
class HaCustomerLedger extends Model
{
    public const TYPES = ['opening_due', 'credit_sale', 'payment', 'adjustment', 'refund_reversal'];

    protected $table = 'ha_customer_ledger';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];
}
