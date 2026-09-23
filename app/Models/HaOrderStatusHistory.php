<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Order status change audit trail.
 */
class HaOrderStatusHistory extends Model
{
    protected $table = 'ha_order_status_history';

    protected $guarded = [];
}
