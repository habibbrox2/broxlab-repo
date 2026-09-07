<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Replaces the legacy `AppSettings` mysqli model for reads.
 * Settings live in a single row (id=1) of `app_settings`.
 */
class AppSettings
{
    public function all(?int $ttl = 300): array
    {
        return Cache::remember('app_settings:row', $ttl, fn () => (AppSetting::query()->first() ?? new AppSetting)->toArray());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }
}