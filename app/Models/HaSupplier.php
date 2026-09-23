<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hero Alif supplier.
 */
class HaSupplier extends Model
{
    use SoftDeletes;

    protected $table = 'ha_suppliers';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'opening_due' => 'decimal:2',
    ];
}
