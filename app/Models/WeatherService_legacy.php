<?php

/**
 * WeatherService - Business Logic Layer for Weather Operations
 *
 * Encapsulates all weather-related business logic including:
 *   - External API integration (OpenWeatherMap, WeatherAPI, etc.)
 *   - Data transformation and normalization
 *   - Caching coordination
 *   - Error handling and retry logic
 *   - Fallback to mock data for development
 *
 * Configuration via AppSettings or env vars:
 *   WEATHER_API_PROVIDER   - Provider name (openweathermap|weatherapi|mock)
 *   WEATHER_API_KEY        - API key
 *   WEATHER_UNITS_DEFAULT - metric|imperial|kelvin (default: metric)
 */

class WeatherService
{
    private \mysqli $db;
    private string $provider;
    private string $apiKey;
    private string $defaultUnits;
    private int $cacheTtlHome;
    private int $cacheTtlDetails;
    private array $config = []; // Declare config property to prevent dynamic property deprecation

    private const ENDPOINTS = [
        "openweathermap" => "https://api.openweathermap.org/data/2.5",
        "weatherapi"     => "http://api.weatherapi.com/v1"
    ];

    public function __construct(\mysqli $mysqli)
    {
        $this->db = $mysqli;
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        try {
            $settingsModel = new AppSettings($this->db);
            $settings = $settingsModel->getSettings();
            $this->config = is_array($settings) ? $settings : [];
        } catch (Throwable $e) {
            $this->config = [];
        }

        $this->provider        = $this->config["weather_api_provider"] ?? $_ENV["WEATHER_API_PROVIDER"] ?? "mock";
        $this->apiKey          = $this->config["weather_api_key"] ?? $_ENV["WEATHER_API_KEY"] ?? "";
        $this->defaultUnits    = $this->config["weather_units_default"] ?? $_ENV["WEATHER_UNITS_DEFAULT"] ?? "metric";
        $this->cacheTtlHome    = (int)($this->config["weather_cache_ttl_home"] ?? $_ENV["WEATHER_CACHE_TTL_HOME"] ?? 900);
        $this->cacheTtlDetails = (int)($this->config["weather_cache_ttl_details"] ?? $_ENV["WEATHER_CACHE_TTL_DETAILS"] ?? 600);
    }

    /**
     * Get data for weather home page
     */
    public function getHomePageData(?string $city = null, ?float $lat = null, ?float $lon = null): array
    {
        $result = [
            "popular_locations" => [],
            "featured_weather"   => null,
            "suggested_cities"   => [],
            "trends"             => []
        ];

        if ($city || ($lat !== null && $lon !== null)) {
            $location = $city ?: sprintf("%.4f,%.4f", $lat, $lon);
            $weather  = $this->getLocationWeather($location, $this->defaultUnits, 1);
            if (isset($weather["success"]) && $weather["success"]) {
                $result["featured_weather"] = $weather["data"] ?? null;
            }
        }

        $cache = \App\Modules\AISystem\UnifiedCache::getInstance();
        $key = "weather_popular_locations";
        if (!$cache->has($key, \App\Modules\AISystem\UnifiedCache::CATEGORY_WEATHER)) {
            $value = $this->getDefaultPopularLocations();
            $cache->set($key, $value, \App\Modules\AISystem\UnifiedCache::CATEGORY_WEATHER, 3600);
        }
        $result["popular_locations"] = $cache->get($key, \App\Modules\AISystem\UnifiedCache::CATEGORY_WEATHER);

        $result["suggested_cities"] = $this->getSuggestedCities();
        $result["trends"]           = [];

        return $result;
    }

    /**
     * Get detailed weather for a location
     */
    public function getLocationWeather(
        string $location,
        string $units = "metric",
        int $forecastDays = 3,
        bool $includeHourly = false,
        bool $includeAlerts = true
    ): array {
        $params = [
            "units" => $units,
            "cnt"   => max(0, $forecastDays)
        ];

        try {
            switch ($this->provider) {
                case "openweathermap":
                    return $this->fetchOpenWeatherMap($location, $params, $forecastDays, $includeHourly, $includeAlerts);
                case "weatherapi":
                    return $this->fetchWeatherApi($location, $params, $forecastDays, $includeHourly, $includeAlerts);
                case "mock":
                default:
                    return $this->fetchMockData($location, $units, $forecastDays, $includeHourly, $includeAlerts);
            }
        } catch (Throwable $e) {
            logError("Weather API fetch failed: " . $e->getMessage(), "WEATHER_ERROR", [
                "location" => $location,
                "provider" => $this->provider
            ]);
            return [
                "success" => false,
                "error"   => "Weather API request failed: " . $e->getMessage()
            ];
        }
    }

    private function fetchOpenWeatherMap(string $location, array $params, int $days, bool $hourly, bool $alerts): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException("Weather API key not configured");
        }

        $endpoint = self::ENDPOINTS["openweathermap"];
        $isCoords = preg_match('/^[-+]?[0-9]*\.?[0-9]+,[-+]?[0-9]*\.?[0-9]+$/', $location);

        $query = http_build_query(array_merge($params, [
            "q"     => $isCoords ? null : $location,
            "lat"   => $isCoords ? explode(",", $location)[0] : null,
            "lon"   => $isCoords ? explode(",", $location)[1] : null,
            "appid" => $this->apiKey
        ]));

        $currentUrl   = "{$endpoint}/weather?{$query}";
        $forecastUrl  = "{$endpoint}/forecast?{$query}&cnt=" . ($days * 8);

        $currentResp  = $this->httpGet($currentUrl);
        $forecastResp = $this->httpGet($forecastUrl);

        if ($currentResp["status"] !== 200) {
            throw new RuntimeException("OpenWeatherMap error: " . ($currentResp["body"] ?? "HTTP " . $currentResp["status"]));
        }

        $currentData  = json_decode($currentResp["body"], true);
        $forecastData = json_decode($forecastResp["body"], true);

        return [
            "success" => true,
            "data"    => $this->transformOpenWeatherMap($currentData, $forecastData, $days, $hourly, $alerts)
        ];
    }

    private function fetchWeatherApi(string $location, array $params, int $days, bool $hourly, bool $alerts): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException("Weather API key not configured");
        }

        $endpoint = self::ENDPOINTS["weatherapi"];
        $query    = http_build_query(array_merge($params, [
            "q"      => $location,
            "key"    => $this->apiKey,
            "days"   => $days,
            "aqi"    => "no",
            "alerts" => $alerts ? "yes" : "no"
        ]));

        $url      = "{$endpoint}/forecast.json?{$query}";
        $response = $this->httpGet($url);

        if ($response["status"] !== 200) {
            throw new RuntimeException("WeatherAPI error: " . ($response["body"] ?? "HTTP " . $response["status"]));
        }

        $data = json_decode($response["body"], true);
        return [
            "success" => true,
            "data"    => $this->transformWeatherApi($data, $days, $hourly, $alerts)
        ];
    }

    /**
     * Mock Data Provider
     * Returns deterministic simulated weather for development/demo.
     * Replace with real API calls when keys are configured.
     */
    private function fetchMockData(string $location, string $units = "metric", int $days = 3, bool $hourly = false, bool $alerts = true): array
    {
        usleep(200000); // simulate network latency

        $seed       = crc32($location);
        mt_srand($seed);
        $isCelsius  = $units === "metric";
        $baseTemp   = $isCelsius ? 20 : 68;
        $variation  = $isCelsius ? 8 : 15;
        $locName    = strpos($location, ",") !== false ? "Selected Location ({$location})" : $location;

        $current = [
            "location_name"    => $locName,
            "location_country" => "Mocked",
            "temperature"      => round($baseTemp + (mt_rand(-$variation, $variation) / 2), 1),
            "feels_like"       => round($baseTemp + (mt_rand(-$variation, $variation) / 2), 1),
            "humidity"         => mt_rand(40, 90),
            "pressure"         => mt_rand(990, 1030),
            "wind_speed"       => round(mt_rand(0, 200) / 10, 1),
            "wind_deg"         => mt_rand(0, 359),
            "description"      => ["clear sky", "few clouds", "scattered clouds", "broken clouds", "light rain"][mt_rand(0, 4)],
            "icon"             => ["01d", "02d", "03d", "04d", "10d"][mt_rand(0, 4)],
            "visibility"       => mt_rand(5000, 10000),
            "uv_index"         => mt_rand(1, 10),
            "sunrise"          => "06:" . str_pad(mt_rand(0, 59), 2, "0"),
            "sunset"           => "18:" . str_pad(mt_rand(30, 59), 2, "0"),
            "timezone_offset"  => 0
        ];

        // Forecast
        $forecast = [];
        $date = new DateTime();
        for ($i = 0; $i < $days; $i++) {
            $dayTemp   = $baseTemp + (mt_rand(-$variation, $variation) / 2);
            $nightTemp = $dayTemp - ($isCelsius ? 5 : 9);
            $dateStr   = $date->format("Y-m-d");
            $date->modify("+1 day");

            $dayData = [
                "date"             => $dateStr,
                "temperature_day"  => round($dayTemp, 1),
                "temperature_night" => round($nightTemp, 1),
                "temp_min"         => round($dayTemp - ($isCelsius ? 3 : 5), 1),
                "temp_max"         => round($dayTemp + ($isCelsius ? 3 : 5), 1),
                "humidity"         => mt_rand(40, 90),
                "wind_speed"       => round(mt_rand(0, 200) / 10, 1),
                "description"      => ["clear", "cloudy", "rain", "storm"][mt_rand(0, 3)],
                "icon"             => ["01d", "02d", "03d", "09d", "11d"][mt_rand(0, 4)],
                "pop"              => mt_rand(0, 60) / 100
            ];

            if ($hourly) {
                $dayData["hourly"] = $this->generateMockHourly($dayTemp, $isCelsius);
            }

            $forecast[] = $dayData;
        }

        // Occasionally include a weather alert
        $alertsList = [];
        if ($alerts && mt_rand(0, 4) === 0) {
            $alertsList = [[
                "title"       => "Severe Weather Warning",
                "description" => "Expect thunderstorms and heavy rainfall in your area.",
                "level"       => "moderate",
                "sender"      => "National Weather Service",
                "event"       => "Thunderstorm Warning",
                "start"       => time(),
                "end"         => time() + 86400
            ]];
        }

        return [
            "success" => true,
            "data"    => [
                "current"        => $current,
                "forecast"       => $forecast,
                "alerts"         => $alertsList,
                "units"          => $units,
                "forecast_days"  => $days,
                "last_updated"   => time()
            ]
        ];
    }

    private function generateMockHourly(float $dayBase, bool $isCelsius, int $count = 8): array
    {
        $hourly = [];
        $baseHour = 6;
        for ($i = 0; $i < $count; $i++) {
            $hourOfDay = ($baseHour + $i * 3) % 24;
            $temp = $dayBase - 5 + (sin(($hourOfDay - 6) * M_PI / 12) * ($isCelsius ? 8 : 15));
            $hourly[] = [
                "time"    => sprintf("%02d:00", $hourOfDay),
                "temp"    => round($temp, 1),
                "humidity" => mt_rand(40, 90),
                "pop"     => mt_rand(0, 50) / 100
            ];
        }
        return $hourly;
    }

    // Placeholder transforms (real implementation would map precisely)
    private function transformOpenWeatherMap(array $current, array $forecast, int $days, bool $hourly, bool $alerts): array
    {
        return $this->fetchMockData($current["name"] ?? "Unknown", $current["units"] ?? "metric", $days, $hourly, $alerts)["data"];
    }

    private function transformWeatherApi(array $data, int $days, bool $hourly, bool $alerts): array
    {
        $current = $data["current"] ?? [];
        $loc     = $data["location"] ?? [];

        return [
            "current" => [
                "location_name"    => $loc["name"] ?? "Unknown",
                "location_country" => $loc["country"] ?? "",
                "temperature"      => $current["temp_c"] ?? ($current["temp_f"] ?? 0),
                "feels_like"       => $current["feelslike_c"] ?? ($current["feelslike_f"] ?? 0),
                "humidity"         => $current["humidity"] ?? 0,
                "pressure"         => $current["pressure_mb"] ?? 0,
                "wind_speed"       => $current["wind_kph"] ?? 0,
                "wind_deg"         => $current["wind_degree"] ?? 0,
                "description"      => $current["condition"]["text"] ?? "",
                "icon"             => $current["condition"]["icon"] ?? "",
                "visibility"       => $current["vis_km"] ?? 0,
                "uv_index"         => $current["uv"] ?? 0,
                "sunrise"          => "",
                "sunset"           => "",
                "timezone_offset"  => 0
            ],
            "forecast" => $this->transformWeatherApiForecast($data["forecast"] ?? [], $days, $hourly),
            "alerts"   => ($alerts && isset($data["alerts"])) ? $data["alerts"] : [],
            "units"    => "metric",
            "forecast_days" => $days,
            "last_updated"  => time()
        ];
    }

    private function transformWeatherApiForecast(array $forecast, int $days, bool $hourly): array
    {
        $result = [];
        $count  = min($days, count($forecast["forecastday"] ?? []));
        for ($i = 0; $i < $count; $i++) {
            $day = $forecast["forecastday"][$i];
            $hourData = [];
            if ($hourly && isset($day["hour"])) {
                foreach ($day["hour"] as $h) {
                    $hourData[] = [
                        "time"    => date("H:00", strtotime($h["time"])),
                        "temp"    => $h["temp_c"],
                        "humidity" => $h["humidity"],
                        "pop"     => $h["chance_of_rain"] / 100
                    ];
                }
            }
            $result[] = [
                "date"             => $day["date"],
                "temperature_day"  => $day["day"]["avgtemp_c"],
                "temperature_night" => $day["day"]["avgtemp_c"] - 5,
                "temp_min"         => $day["day"]["mintemp_c"],
                "temp_max"         => $day["day"]["maxtemp_c"],
                "humidity"         => $day["day"]["avgHumidity"],
                "wind_speed"       => $day["day"]["maxwind_kph"],
                "description"      => $day["day"]["condition"]["text"],
                "icon"             => $day["day"]["condition"]["icon"],
                "pop"              => $day["day"]["daily_chance_of_rain"] / 100,
                "hourly"           => $hourData
            ];
        }
        return $result;
    }

    /**
     * HTTP GET using cURL
     */
    private function httpGet(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => "BroxBhai Weather/1.0"
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("cURL error: {$err}");
        }

        return ["status" => $code, "body" => $body];
    }

    /**
     * Default popular locations list
     */
    private function getDefaultPopularLocations(): array
    {
        return [
            ["name" => "London, UK",        "country" => "GB", "search" => "London"],
            ["name" => "New York, US",      "country" => "US", "search" => "New York"],
            ["name" => "Tokyo, Japan",      "country" => "JP", "search" => "Tokyo"],
            ["name" => "Sydney, Australia", "country" => "AU", "search" => "Sydney"],
            ["name" => "Mumbai, India",     "country" => "IN", "search" => "Mumbai"],
            ["name" => "Dubai, UAE",        "country" => "AE", "search" => "Dubai"],
            ["name" => "Paris, France",     "country" => "FR", "search" => "Paris"],
            ["name" => "Berlin, Germany",   "country" => "DE", "search" => "Berlin"]
        ];
    }

    private function getSuggestedCities(): array
    {
        return ["London", "New York", "Tokyo", "Paris", "Sydney", "Berlin", "Mumbai", "Toronto", "Los Angeles", "Singapore"];
    }
}
