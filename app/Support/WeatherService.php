<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Port of legacy app/Services/WeatherService.php (OpenWeatherMap-backed,
 * mock provider for local/dev). Used by the home weather widget via
 * GET /weather/details?location=...&format=json.
 */
class WeatherService
{
    /**
     * Effective weather config: config/weather.php defaults overridden by the
     * admin-managed values on the app_settings row (weather_* columns), when set.
     */
    public function config(): array
    {
        $config = config('weather', []);

        try {
            $row = (array) (\Illuminate\Support\Facades\DB::table('app_settings')->first() ?? []);
        } catch (Throwable) {
            return $config; // e.g. during early bootstrap / migrations
        }

        $dbProvider = trim((string) ($row['weather_provider'] ?? ''));
        $dbApiKey = trim((string) ($row['weather_api_key'] ?? ''));
        $dbUnits = trim((string) ($row['weather_units'] ?? ''));
        $dbCity = trim((string) ($row['weather_default_city'] ?? ''));
        $dbLat = $row['weather_default_lat'] ?? null;
        $dbLon = $row['weather_default_lon'] ?? null;

        if ($dbProvider !== '') {
            $config['provider'] = $dbProvider;
        }
        if ($dbApiKey !== '') {
            $config['openweathermap']['api_key'] = $dbApiKey;
        }
        if (in_array($dbUnits, ['metric', 'imperial'], true)) {
            $config['openweathermap']['units'] = $dbUnits;
        }
        if ($dbCity !== '') {
            $config['default_location']['city'] = $dbCity;
        }
        if ($dbLat !== null && $dbLat !== '') {
            $config['default_location']['lat'] = (float) $dbLat;
        }
        if ($dbLon !== null && $dbLon !== '') {
            $config['default_location']['lon'] = (float) $dbLon;
        }

        return $config;
    }
    public function getCurrentWeather(string $location, string $units = 'metric', int $forecastDays = 0): array
    {
        $config = $this->config();

        // Mock provider: deterministic data for local tests/dev (legacy parity).
        if (($config['provider'] ?? 'mock') === 'mock') {
            return $this->mockResponse($location, $forecastDays);
        }

        $apiKey = (string) ($config['openweathermap']['api_key'] ?? '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'Weather provider is not configured'];
        }

        $ttl = (int) ($config['cache']['duration'] ?? 600);
        $cacheKey = 'weather_'.md5($location.'|'.$units.'|'.$forecastDays);

        try {
            return Cache::remember($cacheKey, $ttl, function () use ($location, $units, $forecastDays, $config, $apiKey) {
                return $this->fetch($location, $units, $forecastDays, $config, $apiKey);
            });
        } catch (Throwable $e) {
            report($e);

            return ['success' => false, 'error' => 'Unable to fetch weather data. Please try again later.'];
        }
    }

    protected function fetch(string $location, string $units, int $forecastDays, array $config, string $apiKey): array
    {
        $baseUrl = rtrim((string) ($config['openweathermap']['base_url'] ?? 'https://api.openweathermap.org/data/2.5'), '/');
        $geoUrl = rtrim((string) ($config['openweathermap']['geocoding_url'] ?? 'https://api.openweathermap.org/geo/1.0'), '/');
        $lang = (string) ($config['openweathermap']['lang'] ?? 'en');

        if (preg_match('/^(-?\d+\.?\d*),(-?\d+\.?\d*)$/', $location, $m)) {
            $lat = $m[1];
            $lon = $m[2];
            $locationName = null;
        } else {
            $geo = Http::timeout(10)->get($geoUrl.'/direct', ['q' => $location, 'limit' => 1, 'appid' => $apiKey]);
            $geoData = $geo->json() ?? [];
            if (empty($geoData[0]['lat'])) {
                return ['success' => false, 'error' => 'Location not found. Please check the city name.'];
            }
            $lat = $geoData[0]['lat'];
            $lon = $geoData[0]['lon'];
            $locationName = $geoData[0]['name'].', '.($geoData[0]['country'] ?? '');
        }

        $current = Http::timeout(10)->get($baseUrl.'/weather', [
            'lat' => $lat, 'lon' => $lon, 'appid' => $apiKey, 'units' => $units, 'lang' => $lang,
        ])->json() ?? [];

        if (! isset($current['main'])) {
            return ['success' => false, 'error' => 'Weather data not available for this location.'];
        }

        $result = [
            'success' => true,
            'data' => [
                'current' => [
                    'location_name' => $locationName ?? (($current['name'] ?? '').', '.($current['sys']['country'] ?? '')),
                    'temperature' => $current['main']['temp'],
                    'feels_like' => $current['main']['feels_like'],
                    'humidity' => $current['main']['humidity'],
                    'pressure' => $current['main']['pressure'],
                    'wind_speed' => $current['wind']['speed'] ?? 0,
                    'wind_deg' => $current['wind']['deg'] ?? 0,
                    'description' => $current['weather'][0]['description'] ?? 'Clear',
                    'icon' => $current['weather'][0]['icon'] ?? '01d',
                    'main' => $current['weather'][0]['main'] ?? 'Clear',
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
        ];

        if ($forecastDays > 0) {
            $forecast = Http::timeout(10)->get($baseUrl.'/forecast', [
                'lat' => $lat, 'lon' => $lon, 'appid' => $apiKey, 'units' => $units,
                'cnt' => min($forecastDays * 8, 40), 'lang' => $lang,
            ])->json() ?? [];

            if (isset($forecast['list'])) {
                $result['data']['forecast'] = $this->processForecast($forecast['list'], $forecastDays);
            }
        }

        return $result;
    }

    /** Port of legacy processForecast(). */
    protected function processForecast(array $forecastList, int $days): array
    {
        $grouped = [];
        foreach ($forecastList as $item) {
            $date = date('Y-m-d', (int) $item['dt']);
            $grouped[$date][] = $item;
        }

        $out = [];
        $count = 0;
        foreach ($grouped as $date => $items) {
            if ($count >= $days) {
                break;
            }
            $temps = array_column(array_column($items, 'main'), 'temp');
            $humidity = array_column(array_column($items, 'main'), 'humidity');
            $out[] = [
                'date' => $date,
                'day_name' => date('l', strtotime($date)),
                'temp_min' => min($temps),
                'temp_max' => max($temps),
                'humidity_avg' => (int) round(array_sum($humidity) / max(1, count($humidity))),
                'description' => $items[0]['weather'][0]['description'] ?? '',
                'icon' => $items[0]['weather'][0]['icon'] ?? '01d',
            ];
            $count++;
        }

        return $out;
    }

    /** Deterministic mock payload (legacy mock provider parity). */
    protected function mockResponse(string $location, int $forecastDays): array
    {
        $mockTemp = 29.5;
        $data = [
            'current' => [
                'location_name' => $location,
                'temperature' => $mockTemp,
                'feels_like' => $mockTemp - 1.0,
                'humidity' => 65,
                'pressure' => 1012,
                'wind_speed' => 3.5,
                'wind_deg' => 90,
                'description' => 'Partly cloudy',
                'icon' => '02d',
                'main' => 'Clouds',
                'updated_at' => now()->toIso8601String(),
            ],
        ];

        if ($forecastDays > 0) {
            $data['forecast'] = [];
            for ($i = 0; $i < $forecastDays; $i++) {
                $date = now()->addDays($i)->toDateString();
                $data['forecast'][] = [
                    'date' => $date,
                    'day_name' => now()->addDays($i)->dayName,
                    'temp_min' => $mockTemp - 2,
                    'temp_max' => $mockTemp + 2,
                    'humidity_avg' => 60,
                    'description' => 'Partly cloudy',
                    'icon' => '02d',
                ];
            }
        }

        return ['success' => true, 'data' => $data];
    }
}
