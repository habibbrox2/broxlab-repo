<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `posts` — legacy content table. Thin for now; relations/scopes are added
 * when the content module is migrated (Phase 4).
 */
class Post extends Model
{
    use SoftDeletes;

    protected $table = 'posts';

    protected $guarded = [];
}