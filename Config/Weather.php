<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Weather Provider
    |--------------------------------------------------------------------------
    | Currently supports: openweathermap (or "mock" for deterministic local
    | data — legacy default). Ported from legacy Config/Weather.php.
    */

    'provider' => env('WEATHER_PROVIDER', 'mock'),

    'openweathermap' => [
        'api_key' => env('OPENWEATHER_API_KEY', ''),
        'base_url' => 'https://api.openweathermap.org/data/2.5',
        'geocoding_url' => 'https://api.openweathermap.org/geo/1.0',
        'units' => 'metric',
        'lang' => 'en',
    ],

    'cache' => [
        'enabled' => true,
        'duration' => 600, // 10 minutes
        'prefix' => 'weather_',
    ],

    'default_location' => [
        'city' => 'Dhaka',
        'lat' => 23.8103,
        'lon' => 90.4125,
    ],

];
