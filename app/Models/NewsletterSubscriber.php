<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * `newsletter_subscribers` — legacy table (no created_at column, so
 * Eloquent timestamps are disabled and subscribed_at is managed manually).
 */
class NewsletterSubscriber extends Model
{
    protected $table = 'newsletter_subscribers';

    public $timestamps = false;

    protected $fillable = [
        'email', 'name', 'status', 'preferences', 'ip_address', 'subscribed_at', 'updated_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}