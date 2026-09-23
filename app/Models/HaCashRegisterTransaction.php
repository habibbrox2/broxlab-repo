<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cash movement inside a register session (cash sale, cash-in/out, refund).
 */
class HaCashRegisterTransaction extends Model
{
    public const TYPES = [
        'cash_sale', 'change', 'cash_in', 'cash_out', 'refund_cash', 'due_collection',
    ];

    protected $table = 'ha_cash_register_transactions';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
    ];
}
