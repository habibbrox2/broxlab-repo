<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings table (legacy `app_settings`).
 * Columns are read directly; there is no key/value structure.
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = [
        'site_name', 'site_logo', 'favicon', 'default_language', 'timezone',
        'meta_title', 'meta_description', 'meta_keywords',
        'contact_email', 'contact_phone', 'contact_address',
        'social_facebook', 'social_twitter', 'social_instagram', 'social_youtube',
        'allow_user_registration', 'require_email_verification', 'enable_2fa',
        'asset_version',
        'header_nav_items',
    ];

    protected $casts = [
        'allow_user_registration' => 'boolean',
        'require_email_verification' => 'boolean',
        'enable_2fa' => 'boolean',
        'header_nav_items' => 'array',
    ];
}