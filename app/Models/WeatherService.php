<?php
// Compatibility entrypoint for legacy code that expects `app/Models/WeatherService.php`
// This file delegates to the compat shim which forwards calls to the new namespaced service.

require_once __DIR__ . '/WeatherServiceCompat.php';
