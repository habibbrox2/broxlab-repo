<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Per-user admin UI preference: sidebar width.
 *
 * GET  /admin/api/sidebar-width  → { width: int }  (clamped 200–480)
 * PUT  /admin/api/sidebar-width  { width }         (clamped, one UPDATE)
 *
 * The client keeps a localStorage copy for instant paint; the server value
 * wins on load so the preference follows the admin across devices.
 */
class AdminSidebarPreferenceController extends Controller
{
    public const MIN = 200;

    public const MAX = 480;

    public const DEFAULT = 220;

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'width' => $user ? $user->sidebarWidth() : self::DEFAULT,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'width' => ['required', 'integer', 'min:' . self::MIN, 'max:' . self::MAX],
        ]);

        $updated = DB::table('users')
            ->where('id', $request->user()->id)
            ->update(['admin_sidebar_width' => (int) $validated['width']]);

        if ($updated === 0 && ! DB::table('users')->where('id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return response()->json(['width' => (int) $validated['width']]);
    }
}
