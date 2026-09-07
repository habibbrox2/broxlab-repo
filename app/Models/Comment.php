<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `comments` — legacy comments table. Thin for now (Phase 4 migration).
 */
class Comment extends Model
{
    use SoftDeletes;

    protected $table = 'comments';

    protected $guarded = [];
}