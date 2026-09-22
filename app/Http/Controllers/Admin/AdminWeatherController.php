<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\WeatherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * Weather settings — API configuration and default location, persisted to the
 * single app_settings row (weather_* columns). WeatherService merges these over
 * config/weather.php at runtime, so saving here changes the live widget without
 * a deploy.
 */
class AdminWeatherController extends Controller
{
    /** Whitelist: which request keys may be persisted. */
    protected const FIELDS = [
        'weather_provider' => 'string',
        'weather_api_key' => 'string',
        'weather_units' => 'string',
        'weather_default_city' => 'string',
        'weather_default_lat' => 'float',
        'weather_default_lon' => 'float',
    ];

    public function __construct(protected WeatherService $weather) {}

    public function index(): View
    {
        $appSettings = $this->settings();

        return view('admin.weather.index', [
            'title' => 'Weather',
            'header_title' => 'Weather',
            'appSettings' => $appSettings,
        ]);
    }

    /** API configuration: provider, key, units + a live connectivity test. */
    public function api(): View
    {
        return view('admin.weather.api', [
            'title' => 'Weather API Settings',
            'header_title' => 'Weather API Settings',
            'appSettings' => $this->settings(),
            'provider' => $this->weather->config()['provider'] ?? 'mock',
        ]);
    }

    /** Default location used by the public widget when none is requested. */
    public function locations(): View
    {
        return view('admin.weather.locations', [
            'title' => 'Weather Location Settings',
            'header_title' => 'Weather Location Settings',
            'appSettings' => $this->settings(),
        ]);
    }

    /** Persist one screen's fields onto the app_settings row. */
    public function update(Request $request): RedirectResponse
    {
        $section = str_replace('admin/weather/', '', $request->path());
        $allowed = match ($section) {
            'api' => ['weather_provider', 'weather_api_key', 'weather_units'],
            'locations' => ['weather_default_city', 'weather_default_lat', 'weather_default_lon'],
            default => null,
        };

        if ($allowed === null) {
            return redirect('/admin/weather')->with('error', 'Unknown weather settings section.');
        }

        $rules = [
            'weather_provider' => ['nullable', 'in:mock,openweathermap'],
            'weather_api_key' => ['nullable', 'string', 'max:190'],
            'weather_units' => ['nullable', 'in:metric,imperial'],
            'weather_default_city' => ['nullable', 'string', 'max:190'],
            'weather_default_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'weather_default_lon' => ['nullable', 'numeric', 'between:-180,180'],
        ];

        $data = [];
        foreach ($allowed as $field) {
            $value = $request->input($field);
            $type = self::FIELDS[$field];

            if ($type === 'float') {
                if (($value ?? '') === '' || $value === null) {
                    $data[$field] = null;
                } else {
                    $data[$field] = (float) $value;
                }
            } else {
                $data[$field] = trim((string) ($value ?? ''));
            }

            if (isset($rules[$field])) {
                validator([$field => $value], [$field => $rules[$field]])->validate();
            }
        }

        DB::table('app_settings')->where('id', 1)->update($data + ['updated_at' => now()]);
        Cache::forget('app_settings:row');

        $this->logActivity('Weather Settings Updated', $section);

        return redirect("/admin/weather/{$section}")
            ->with('status', ucfirst($section === 'api' ? 'API' : 'Location').' settings saved.');
    }

    /**
     * Live connectivity test: calls the effective provider for Dhaka and
     * reports whether real data came back. No settings are changed.
     */
    public function test(): RedirectResponse
    {
        $result = $this->weather->getCurrentWeather('Dhaka', 'metric', 0);

        if (($result['success'] ?? false) && ! isset($result['error'])) {
            $temp = isset($result['current']['temp_c']) ? ' ('.$result['current']['temp_c'].'°C)' : '';
            return redirect('/admin/weather/api')
                ->with('status', 'Weather provider is responding'.$temp.'.');
        }

        return redirect('/admin/weather/api')
            ->with('error', 'Weather test failed — '.($result['error'] ?? 'unknown error').'. Check the provider and API key.');
    }

    /** @return array<string, mixed> */
    protected function settings(): array
    {
        return (array) (DB::table('app_settings')->where('id', 1)->first() ?? []);
    }

    protected function logActivity(string $action, string $section): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => (int) auth()->id(),
                'role' => app(\App\Support\UserProfileService::class)->rbacFor((int) auth()->id())['roles'][0] ?? 'admin',
                'action' => $action,
                'resource_type' => 'weather_settings',
                'resource_id' => 0,
                'status' => 'success',
                'ip_address' => request()?->ip() ?? '0.0.0.0',
                'user_agent' => mb_substr((string) (request()?->userAgent() ?? ''), 0, 500),
                'details' => json_encode(['section' => $section]),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Logging must never break the save.
        }
    }
}
