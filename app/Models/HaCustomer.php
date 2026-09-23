<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hero Alif business customer (walk-in or linked website user).
 */
class HaCustomer extends Model
{
    use SoftDeletes;

    protected $table = 'ha_customers';

    protected $guarded = [];

    protected $casts = [
        'due_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function sales()
    {
        return $this->hasMany(HaSale::class, 'customer_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(HaCustomerLedger::class, 'customer_id')->orderByDesc('id');
    }

    public function payments()
    {
        return $this->hasMany(HaCustomerPayment::class, 'customer_id')->orderByDesc('id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
