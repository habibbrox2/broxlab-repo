<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily cash register session (opening → expected → actual → difference).
 */
class HaCashRegister extends Model
{
    protected $table = 'ha_cash_registers';

    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_balance' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function transactions()
    {
        return $this->hasMany(HaCashRegisterTransaction::class, 'register_id');
    }
}
