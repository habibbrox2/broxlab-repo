<?php

/**
 * Weather Service
 * Handles weather API calls with caching
 * 
 * @package BroxLab
 */

namespace App\Services;

use App\Modules\AISystem\UnifiedCache;
use Exception;

class WeatherService
{
    private $config;
    private $cache;

    public function __construct()
    {
        // Config and cache are loaded on-demand in methods
    }

    private function getConfig()
    {
        static $config = null;
        if ($config === null) {
            $config = require BASE_PATH . 'Config/Weather.php';
        }
        return $config;
    }

    private function getCache()
    {
        static $cache = null;
        if ($cache === null) {
            $cache = \App\Modules\AISystem\UnifiedCache::getInstance();
        }
        return $cache;
    }

    /**
     * Get current weather for a location
     * 
     * @param string $location City name or "lat,lon" coordinates
     * @param string $units metric or imperial
     * @param int $forecastDays Number of forecast days (0-16)
     * @return array
     */
    public function getCurrentWeather(string $location, string $units = 'metric', int $forecastDays = 0): array
    {
        error_log("[WeatherService] getCurrentWeather() called with location={$location}, units={$units}, forecastDays={$forecastDays}");
        @file_put_contents(BASE_PATH . 'build/weather_debug.log', date('c') . " - getCurrentWeather called: location={$location}, units={$units}, forecastDays={$forecastDays}\n", FILE_APPEND);
        $config = $this->getConfig();
        // If provider is set to mock in config, return deterministic mock data for local tests
        if (!empty($config['provider']) && $config['provider'] === 'mock') {
            $mockTemp = 29.5;
            $mock = [
                'success' => true,
                'data' => [
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
                        'updated_at' => date('c')
                    ]
                ]
            ];

            if ($forecastDays > 0) {
                $mock['data']['forecast'] = [];
                for ($i = 0; $i < $forecastDays; $i++) {
                    $date = date('Y-m-d', strtotime("+{$i} days"));
                    $mock['data']['forecast'][] = [
                        'date' => $date,
                        'day_name' => date('l', strtotime($date)),
                        'temp_min' => $mockTemp - 2,
                        'temp_max' => $mockTemp + 2,
                        'humidity_avg' => 60,
                        'description' => 'Partly cloudy',
                        'icon' => '02d',
                    ];
                }
            }

            return $mock;
        }
        $cache = $this->getCache();

        // Generate cache key
        $cacheKey = $config['cache']['prefix'] . md5($location . $units . $forecastDays);

        // Check cache
        if ($config['cache']['enabled']) {
            $cached = $cache->get($cacheKey);
            if ($cached !== null) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                // Cached data invalid -> fall through and refresh cache
                error_log('[WeatherService] Invalid cache payload, refreshing.');
            }
        }

        try {
            // Parse location (coordinates or city name)
            if (preg_match('/^(-?\d+\.?\d*),(-?\d+\.?\d*)$/', $location, $matches)) {
                $lat = $matches[1];
                $lon = $matches[2];
                $weatherData = $this->fetchByCoordinates($lat, $lon, $units, $forecastDays);
            } else {
                $weatherData = $this->fetchByCity($location, $units, $forecastDays);
            }

            // Cache the result
            if ($config['cache']['enabled']) {
                try {
                    $cache->set($cacheKey, json_encode($weatherData), $config['cache']['duration']);
                } catch (\Throwable $e) {
                    error_log('[WeatherService] Cache set failed: ' . $e->getMessage());
                }
            }

            if (!is_array($weatherData)) {
                error_log('[WeatherService] getCurrentWeather(): delegate returned non-array or null');
                return [
                    'success' => false,
                    'error' => 'Weather service returned unexpected response'
                ];
            }

            return $weatherData;
        } catch (Exception $e) {
            error_log('Weather API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to fetch weather data. Please try again later.'
            ];
        }
    }

    /**
     * Fetch weather by city name
     */
    private function fetchByCity(string $city, string $units, int $forecastDays): array
    {
        $config = $this->getConfig()['openweathermap'];

        // First, geocode the city name
        $geoUrl = $config['geocoding_url'] . '/direct?' . http_build_query([
            'q' => $city,
            'limit' => 1,
            'appid' => $config['api_key']
        ]);

        $geoData = $this->makeApiCall($geoUrl);

        if (empty($geoData) || !isset($geoData[0])) {
            return ['success' => false, 'error' => 'Location not found. Please check the city name.'];
        }

        $lat = $geoData[0]['lat'];
        $lon = $geoData[0]['lon'];
        $cityName = $geoData[0]['name'] . ', ' . ($geoData[0]['country'] ?? '');

        return $this->fetchByCoordinates($lat, $lon, $units, $forecastDays, $cityName);
    }

    /**
     * Fetch weather by coordinates
     */
    private function fetchByCoordinates(float $lat, float $lon, string $units, int $forecastDays, string $locationName = null): array
    {
        $config = $this->getConfig()['openweathermap'];

        // Current weather
        $currentUrl = $config['base_url'] . '/weather?' . http_build_query([
            'lat' => $lat,
            'lon' => $lon,
            'appid' => $config['api_key'],
            'units' => $units,
            'lang' => $config['lang']
        ]);

        $currentData = $this->makeApiCall($currentUrl);

        if (!isset($currentData['main'])) {
            return ['success' => false, 'error' => 'Weather data not available for this location.'];
        }

        // Build response
        $result = [
            'success' => true,
            'data' => [
                'current' => [
                    'location_name' => $locationName ?? ($currentData['name'] . ', ' . ($currentData['sys']['country'] ?? '')),
                    'temperature' => $currentData['main']['temp'],
                    'feels_like' => $currentData['main']['feels_like'],
                    'humidity' => $currentData['main']['humidity'],
                    'pressure' => $currentData['main']['pressure'],
                    'wind_speed' => $currentData['wind']['speed'] ?? 0,
                    'wind_deg' => $currentData['wind']['deg'] ?? 0,
                    'description' => $currentData['weather'][0]['description'] ?? 'Clear',
                    'icon' => $currentData['weather'][0]['icon'] ?? '01d',
                    'main' => $currentData['weather'][0]['main'] ?? 'Clear',
                    'updated_at' => date('c')
                ]
            ]
        ];

        // Add forecast if requested
        if ($forecastDays > 0) {
            $forecastUrl = $config['base_url'] . '/forecast?' . http_build_query([
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $config['api_key'],
                'units' => $units,
                'cnt' => min($forecastDays * 8, 40), // API returns 3-hour intervals
                'lang' => $config['lang']
            ]);

            $forecastData = $this->makeApiCall($forecastUrl);

            if (isset($forecastData['list'])) {
                $result['data']['forecast'] = $this->processForecast($forecastData['list'], $forecastDays);
            }
        }

        return $result;
    }

    /**
     * Process forecast data
     */
    private function processForecast(array $forecastList, int $days): array
    {
        $dailyForecasts = [];
        $groupedByDay = [];

        foreach ($forecastList as $item) {
            $date = date('Y-m-d', $item['dt']);
            if (!isset($groupedByDay[$date])) {
                $groupedByDay[$date] = [];
            }
            $groupedByDay[$date][] = $item;
        }

        $count = 0;
        foreach ($groupedByDay as $date => $items) {
            if ($count >= $days) break;

            $temps = array_column(array_column($items, 'main'), 'temp');
            $humidity = array_column(array_column($items, 'main'), 'humidity');

            $dailyForecasts[] = [
                'date' => $date,
                'day_name' => date('l', strtotime($date)),
                'temp_min' => min($temps),
                'temp_max' => max($temps),
                'humidity_avg' => round(array_sum($humidity) / count($humidity)),
                'description' => $items[0]['weather'][0]['description'] ?? '',
                'icon' => $items[0]['weather'][0]['icon'] ?? '01d',
            ];

            $count++;
        }

        return $dailyForecasts;
    }

    /**
     * Make API call with error handling
     */
    private function makeApiCall(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('API request failed');
        }

        $data = json_decode($response, true);

        if (isset($data['cod']) && $data['cod'] != 200) {
            throw new Exception($data['message'] ?? 'API error');
        }

        return $data ?? [];
    }

    /**
     * Get default location weather
     */
    public function getDefaultWeather(): array
    {
        $default = $this->getConfig()['default_location'];
        return $this->getCurrentWeather("{$default['lat']},{$default['lon']}");
    }
}
