<?php

namespace App\Http\Controllers;

use App\Support\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Weather widget endpoint for the home page — port of the legacy
 * GET /weather/details JSON branch (AJAX / format=json responses only).
 * The full details PAGE remains on the legacy-routes backlog.
 */
class WeatherApiController extends Controller
{
    public function __construct(
        protected WeatherService $weather,
    ) {}

    public function details(Request $request): JsonResponse
    {
        $location = trim((string) $request->query('location', ''));
        if ($location === '') {
            return response()->json(['success' => false, 'error' => 'Location required'], 400);
        }

        // Legacy weather_validateUnits(): metric | imperial | default(→metric)
        $units = strtolower(trim((string) $request->query('units', 'metric')));
        if (! in_array($units, ['metric', 'imperial'], true)) {
            $units = 'metric';
        }

        $forecastDays = min(max(0, (int) $request->query('forecast_days', 1)), 16);

        $result = $this->weather->getCurrentWeather($location, $units, $forecastDays);

        if (empty($result['success'])) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to fetch weather data',
            ], 502);
        }

        return response()->json(['success' => true, 'data' => $result['data'] ?? []]);
    }
}
