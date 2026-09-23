<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hero Alif product category (smart_bazar / mustard_oil / fuel / machinery).
 * `ha_` prefixed — unrelated to the content taxonomy tables.
 */
class HaCategory extends Model
{
    use SoftDeletes;

    protected $table = 'ha_categories';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function products()
    {
        return $this->hasMany(HaProduct::class, 'category_id');
    }
}
