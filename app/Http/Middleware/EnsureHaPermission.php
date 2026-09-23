<?php

namespace App\Http\Middleware;

use App\Support\HaPermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 8: route-level permission guard for the Hero Alif admin modules.
 * Usage: ->middleware('ha.perm:ha.sales.refund')
 *
 * Guests go to /login (EnsureAdmin already ran), authorised-but-missing
 * permission gets 403 (JSON-aware) and is audited. UI hiding is never
 * trusted — every write route carries this guard.
 */
class EnsureHaPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $userId = (int) Auth::id();

        if ($userId <= 0) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => 'Not authenticated'], 401);
            }

            return redirect('/login');
        }

        if (! HaPermissions::allows($permission, $userId)) {
            HaPermissions::auditDenial($permission);

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => "Missing permission: {$permission}"], 403);
            }

            abort(403, "Missing permission: {$permission}");
        }

        return $next($request);
    }
}
