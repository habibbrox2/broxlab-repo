<?php

/**
 * Weather API Configuration
 * 
 * @package BroxLab
 */

return [
    /**
     * Weather API Provider
     * Currently supports: openweathermap
     */
    'provider' => env('WEATHER_PROVIDER', 'mock'),

    /**
     * OpenWeatherMap API Configuration
     * Get your free API key from: https://openweathermap.org/api
     */
    'openweathermap' => [
        'api_key' => env('OPENWEATHER_API_KEY', '9637bfc83ce329bd1056ccfa0cffe593'),
        'base_url' => 'https://api.openweathermap.org/data/2.5',
        'geocoding_url' => 'https://api.openweathermap.org/geo/1.0',
        'units' => 'metric', // metric (Celsius) or imperial (Fahrenheit)
        'lang' => 'en', // Language for descriptions
    ],

    /**
     * Cache Configuration
     * Weather data is cached to reduce API calls (free tier: 60 calls/minute)
     */
    'cache' => [
        'enabled' => true,
        'duration' => 600, // 10 minutes in seconds
        'prefix' => 'weather_',
    ],

    /**
     * Default Location (fallback when geolocation fails)
     */
    'default_location' => [
        'city' => env('WEATHER_DEFAULT_CITY', 'Dhaka'),
        'lat' => env('WEATHER_DEFAULT_LAT', 23.8103),
        'lon' => env('WEATHER_DEFAULT_LON', 90.4125),
        'country' => 'BD',
    ],

    /**
     * Rate Limiting
     */
    'rate_limit' => [
        'enabled' => true,
        'max_requests' => 30, // per IP per minute
        'window' => 60, // seconds
    ],
];
