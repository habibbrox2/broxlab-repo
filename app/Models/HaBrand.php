<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hero Alif product brand.
 */
class HaBrand extends Model
{
    use SoftDeletes;

    protected $table = 'ha_brands';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function products()
    {
        return $this->hasMany(HaProduct::class, 'brand_id');
    }
}
