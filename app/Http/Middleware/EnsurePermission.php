<?php

namespace App\Http\Middleware;

use App\Support\ActivityLogger;
use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Route-level RBAC guard for the non-`ha.*` admin modules (core parity with
 * EnsureHaPermission). Usage: ->middleware('perm:post.delete')
 *
 * - super_admin bypasses (AdminPermissions handles the shortcut);
 * - authorised-but-missing permission → 403 (JSON-aware) + audited denial;
 * - guests → 401 JSON / redirect to /login (EnsureAdmin already ran).
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $userId = (int) Auth::id();

        if ($userId <= 0) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => 'Not authenticated'], 401);
            }

            return redirect('/login');
        }

        if (! AdminPermissions::allows($permission, $userId)) {
            try {
                ActivityLogger::log('rbac', 0, 'permission_denied', [
                    'permission' => $permission,
                    'path' => $request->path(),
                ], 'denied');
            } catch (\Throwable) {
                // Auditing must never take the request down.
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => "Missing permission: {$permission}"], 403);
            }

            abort(403, "Missing permission: {$permission}");
        }

        return $next($request);
    }
}
