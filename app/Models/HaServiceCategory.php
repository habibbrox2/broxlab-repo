<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaServiceCategory extends Model
{
    protected $table = 'ha_service_categories';
    protected $guarded = [];
    protected $casts = [
        'base_fee' => 'decimal:2',
        'form_fields' => 'array',
        'is_active' => 'boolean',
    ];

    public function requests()
    {
        return $this->hasMany(HaServiceRequest::class, 'category_id');
    }
}
