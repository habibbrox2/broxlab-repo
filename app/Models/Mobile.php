<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `mobiles` — legacy device table. Thin for now (Phase 7 migration).
 */
class Mobile extends Model
{
    use SoftDeletes;

    protected $table = 'mobiles';

    protected $guarded = [];
}