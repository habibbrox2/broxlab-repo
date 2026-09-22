<?php

namespace App\Http\Middleware;

use App\Support\UserProfileService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Port of legacy `admin_only` / `admin_or_super_only` middleware: the user
 * must be authenticated and hold the admin or super_admin role.
 *
 * - API/JSON requests get a 401 JSON body (legacy json_response parity).
 * - Page requests redirect to /login (guests) or / (denied).
 */
class EnsureAdmin
{
    public function __construct(
        protected UserProfileService $users,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = Auth::id();

        if (! $userId) {
            if ($this->isApiRequest($request)) {
                return response()->json(['success' => false, 'error' => 'Not authenticated'], 401);
            }

            return redirect('/login');
        }

        if (! $this->users->isAdmin((int) $userId)) {
            if ($this->isApiRequest($request)) {
                return response()->json(['success' => false, 'error' => 'Admin access required'], 403);
            }

            return redirect('/')->with('error', 'Admin or super admin only.');
        }

        return $next($request);
    }

    protected function isApiRequest(Request $request): bool
    {
        return $request->expectsJson()
            || str_starts_with($request->path(), 'api/')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }
}
