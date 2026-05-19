<?php

/**
 * Compatibility shim for WeatherService
 *
 * Provides legacy methods expected by controllers and delegates to
 * `App\Services\WeatherService` where possible.
 */

declare(strict_types=1);

use App\Modules\AISystem\UnifiedCache;

if (!class_exists('WeatherService', false)) {
    class WeatherService
    {
        /** @var \App\Services\WeatherService|null */
        private $delegate;

        public function __construct($mysqli = null)
        {
            // Instantiate namespaced service (no mysqli required)
            $this->delegate = new \App\Services\WeatherService();
        }

        /**
         * Legacy: getLocationWeather(...) -> delegate to getCurrentWeather
         */
        public function getLocationWeather(string $location, string $units = 'metric', int $forecastDays = 3, bool $includeHourly = false, bool $includeAlerts = true): array
        {
            if (is_object($this->delegate) && method_exists($this->delegate, 'getCurrentWeather')) {
                try {
                    $res = $this->delegate->getCurrentWeather($location, $units, $forecastDays);
                    if (!is_array($res)) {
                        return [
                            'success' => false,
                            'error' => 'Weather service returned unexpected response'
                        ];
                    }
                    return $res;
                } catch (Throwable $e) {
                    return [
                        'success' => false,
                        'error' => 'Weather service error: ' . $e->getMessage()
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Weather service not available'
            ];
        }

        /**
         * Legacy: getHomePageData -> provide a compatible structure using delegate
         */
        public function getHomePageData(?string $city = null, ?float $lat = null, ?float $lon = null): array
        {
            $result = [
                'popular_locations' => [],
                'featured_weather'  => null,
                'suggested_cities'  => [],
                'trends'            => []
            ];

            $location = null;
            if ($city) {
                $location = $city;
            } elseif ($lat !== null && $lon !== null) {
                $location = sprintf('%.6f,%.6f', $lat, $lon);
            }

            if ($location !== null) {
                $weather = $this->getLocationWeather($location, 'metric', 1);
                if (isset($weather['success']) && $weather['success']) {
                    $result['featured_weather'] = $weather['data'] ?? null;
                }
            }

            // Try to load cached popular locations if available
            try {
                $cache = UnifiedCache::getInstance();
                $key = 'weather_popular_locations';
                if ($cache && $cache->has($key, UnifiedCache::CATEGORY_WEATHER)) {
                    $result['popular_locations'] = $cache->get($key, UnifiedCache::CATEGORY_WEATHER);
                }
            } catch (Throwable $e) {
                // ignore cache errors
            }

            return $result;
        }

        public function getDefaultWeather(): array
        {
            if (is_object($this->delegate) && method_exists($this->delegate, 'getDefaultWeather')) {
                return $this->delegate->getDefaultWeather();
            }

            return [
                'success' => false,
                'error' => 'Weather service not available'
            ];
        }

        public function __call($name, $args)
        {
            if (is_object($this->delegate) && method_exists($this->delegate, $name)) {
                return $this->delegate->{$name}(...$args);
            }

            throw new BadMethodCallException("Method {$name} does not exist on WeatherService delegate");
        }
    }
}
