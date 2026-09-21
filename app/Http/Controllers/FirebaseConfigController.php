<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Firebase Web SDK config — legacy GET /api/firebase-config.
 *
 * The values returned here are public by design: a Firebase Web API key is an
 * identifier, not a secret (access is gated by Firebase Security Rules). The
 * frontend (assets/firebase/v2/dist/init.js) fetches this endpoint to bootstrap
 * the Web SDK instead of hardcoding the values.
 *
 * Config is read through config() (not env()) so the values survive
 * `php artisan config:cache` in production. Falls back to the
 * firebase.googleapis.com webConfig lookup shape used by the legacy app when
 * nothing is configured locally.
 */
class FirebaseConfigController extends Controller
{
    public function show(): JsonResponse
    {
        $config = [
            'apiKey' => config('services.firebase.api_key', (string) env('FIREBASE_API_KEY', '')),
            'authDomain' => config('services.firebase.auth_domain', (string) env('FIREBASE_AUTH_DOMAIN_LIVE', '')),
            'projectId' => config('services.firebase.project_id', (string) env('FIREBASE_PROJECT_ID', '')),
            'storageBucket' => config('services.firebase.storage_bucket', (string) env('FIREBASE_STORAGE_BUCKET', '')),
            'messagingSenderId' => config('services.firebase.messaging_sender_id', (string) env('FIREBASE_MESSAGING_SENDER_ID', '')),
            'appId' => config('services.firebase.app_id', (string) env('FIREBASE_APP_ID', '')),
        ];

        if (empty($config['apiKey']) || empty($config['projectId'])) {
            return response()->json([
                'success' => false,
                'error' => 'Firebase config not set',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'config' => $config,
        ]);
    }

    /**
     * The endpoint is read-only: writes come from the admin UI / service env,
     * never from the public API. Legacy parity rejected them with 405.
     */
    public function rejectWrite(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Method not allowed',
        ], 405);
    }
}
