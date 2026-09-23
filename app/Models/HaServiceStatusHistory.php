<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaServiceStatusHistory extends Model
{
    protected $table = 'ha_service_status_history';
    protected $guarded = [];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
