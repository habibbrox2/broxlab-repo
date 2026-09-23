<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaServiceRequest extends Model
{
    protected $table = 'ha_service_requests';
    protected $guarded = [];
    protected $casts = [
        'fee' => 'decimal:2',
        'form_data' => 'array',
    ];

    public const STATUSES = ['pending', 'submitted', 'under_review', 'processing', 'waiting_customer', 'waiting_external', 'completed', 'cancelled', 'rejected'];

    public function category()
    {
        return $this->belongsTo(HaServiceCategory::class, 'category_id');
    }

    public function documents()
    {
        return $this->hasMany(HaServiceDocument::class, 'service_request_id');
    }

    public function history()
    {
        return $this->hasMany(HaServiceStatusHistory::class, 'service_request_id')->oldest();
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }
}
