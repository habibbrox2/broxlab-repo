<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaServiceDocument extends Model
{
    protected $table = 'ha_service_documents';

    protected $guarded = [];

    public function request()
    {
        return $this->belongsTo(HaServiceRequest::class, 'service_request_id');
    }
}
