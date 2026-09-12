<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts the native PHP session exactly like the legacy app does
 * (app/Models/SessionManager.php + the legacy front controller) BEFORE Laravel's own
 * session middleware runs.
 *
 * The legacy stack stores login state in native PHP sessions
 * ($_SESSION['user_id'], $_SESSION['logged_in'], ...), saved to
 * <root>/storage/tmp/sessions. Note the legacy app never calls session_name()
 * — it inherits session.name from php.ini (PHPSESSID here) — so this
 * middleware deliberately does NOT rename the session either; that way both
 * sides always resolve the same cookie + session file in any environment.
 * Because the bridge runs Laravel in the same process the legacy front
 * controller would have used, starting that same native session lets the
 * custom guard (app/Auth/LegacySessionGuard.php) read and write the shared
 * session — so a login via Laravel is a login in the legacy app and vice
 * versa.
 *
 * Laravel's own (file) session keeps powering CSRF + flash for the migrated
 * pages; the two sessions are independent and do not collide.
 */
class StartLegacySession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return $next($request);
        }

        $legacyDir = rtrim((string) config('session.legacy_path', base_path('storage/tmp/sessions')), '/\\');
        $resolved = realpath($legacyDir) ?: $legacyDir;

        if (! is_dir($resolved)) {
            @mkdir($resolved, 0775, true);
        }

        // Best-effort: keep the legacy save path in sync even when Windows ACLs
        // report is_writable() === false (same quirk as bootstrap/cache).
        if (is_dir($resolved)) {
            @session_save_path($resolved);
            if (! is_writable($resolved)) {
                @chmod($resolved, 0775);
            }
        }

        $isHttps = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';

        // No session_name() call here on purpose — legacy relies on the php.ini
        // default, and renaming would split the cookie namespace between the two
        // apps (Laravel would read BROXBHAI_SESSION, legacy would read the
        // php.ini default). Inheriting the default keeps them in sync.

        // Mirror the legacy session_start options + security settings
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_cookies', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.sid_length', '32');
        @ini_set('session.sid_bits_per_character', '6');

        @session_start();

        return $next($request);
    }
}
