<?php

namespace App\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auth guard over the shared legacy native PHP session.
 *
 * Reads $_SESSION exactly like the legacy SessionManager/AuthManager
 * (KEY_LOGGED_IN = 'logged_in', KEY_USER_ID = 'user_id', plus the cached
 * profile keys), and login() reproduces legacy AuthManager::createSession():
 * session-regenerate + profile keys + a row in user_sessions.
 *
 * Because StartLegacySession boots the native session before Laravel's own
 * session, auth()->check()/user() reflect the state BOTH apps share: a user
 * logged in through the legacy app is immediately authenticated here, and a
 * login through Laravel is immediately authenticated in the legacy app.
 */
class LegacySessionGuard implements Guard, StatefulGuard
{
    use GuardHelpers;

    protected ?Authenticatable $cachedUser = null;

    protected Request $request;

    public function __construct(UserProvider $provider, Request $request)
    {
        // $provider comes from the GuardHelpers trait (untyped there) — assign
        // rather than re-declare to avoid a PHP trait-composition conflict.
        $this->provider = $provider;
        $this->request = $request;
    }

    public function name(): string
    {
        return 'legacy_session';
    }

    public function user(): ?Authenticatable
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser ?: null;
        }

        if (! $this->check()) {
            return $this->cachedUser = null;
        }

        $id = (int) ($_SESSION['user_id'] ?? 0);

        // Legacy AuthManager::findById joins roles; we fetch the base user and
        // keep the Eloquent provider contract (User model → users table).
        $this->cachedUser = $this->provider->retrieveById($id);

        return $this->cachedUser ?: null;
    }

    public function check(): bool
    {
        if ($this->cachedUser !== null) {
            return true;
        }

        return ! empty($_SESSION['logged_in'] ?? null)
            && ! empty($_SESSION['user_id'] ?? null)
            && (int) $_SESSION['user_id'] > 0;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function id()
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser->getAuthIdentifier();
        }

        return $this->check() ? (int) ($_SESSION['user_id'] ?? 0) : null;
    }

    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->setUser($user);

            return true;
        }

        return false;
    }

    public function attempt(array $credentials = [], $remember = false)
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->login($user, $remember);

            return true;
        }

        return false;
    }

    public function once(array $credentials = [])
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->setUser($user);

            return true;
        }

        return false;
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        $this->setUser($user);
        $this->createNativeSession((int) $user->getAuthIdentifier());
    }

    /**
     * Set the user for the current request (used by actingAs(), Auth::once()
     * and validate()). The shared session state is only touched by login() /
     * logout(); an in-memory user makes Laravel auth helpers work even when no
     * native session is active (e.g. CLI tests).
     */
    public function setUser(Authenticatable $user)
    {
        $this->cachedUser = $user;

        return $this;
    }

    public function loginUsingId($id, $remember = false)
    {
        $user = $this->provider->retrieveById($id);

        if ($user) {
            $this->login($user, $remember);

            return $user;
        }

        return false;
    }

    public function onceUsingId($id)
    {
        $user = $this->provider->retrieveById($id);

        if ($user) {
            $this->setUser($user);

            return true;
        }

        return false;
    }

    public function viaRemember()
    {
        return false;
    }

    public function logout(): void
    {
        // Legacy SessionManager::destroySession parity: wipe the shared native
        // session, expire the cookie, and destroy the session file.
        $this->cachedUser = null;

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Legacy AuthManager::createSession() parity: regenerate id, write the
     * shared session keys, and record the row in user_sessions.
     */
    protected function createNativeSession(int $userId): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        $row = DB::table('users')
            ->select('id', 'username', 'email', 'first_name', 'last_name')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if (! $row) {
            throw new \RuntimeException('User not found');
        }

        $roles = DB::table('user_roles as ur')
            ->join('roles as r', function ($j) {
                $j->on('r.id', '=', 'ur.role_id')->whereNull('r.deleted_at');
            })
            ->where('ur.user_id', $userId)
            ->orderByDesc('r.ranking')
            ->pluck('r.name')
            ->all();

        $firstName = (string) ($row->first_name ?? '');
        $lastName = (string) ($row->last_name ?? '');
        $roles = array_values(array_filter(explode(',', (string) ($row->roles ?? ''))));

        $_SESSION['user_id'] = (int) $row->id;
        $_SESSION['username'] = (string) ($row->username ?? 'User');
        $_SESSION['email'] = (string) ($row->email ?? '');
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $_SESSION['full_name'] = trim($firstName.' '.$lastName) ?: ($row->username ?? 'User');
        $_SESSION['role'] = $roles[0] ?? 'user';
        $_SESSION['roles'] = $roles;
        $_SESSION['permissions'] = [];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['last_activity'] = time();
        $_SESSION['last_regen'] = time();

        // CSRF token for the shared session (legacy generateCsrfToken parity)
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }

        try {
            $expiresAt = date('Y-m-d H:i:s', time() + (int) config('auth.session_timeout', 3600));

            DB::table('user_sessions')->insert([
                'user_id' => (int) $row->id,
                'session_id' => session_id(),
                'ip_address' => $_SESSION['ip_address'],
                'user_agent' => mb_substr($_SESSION['user_agent'], 0, 500),
                'last_activity' => now(),
                'expires_at' => $expiresAt,
                'is_active' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::warning('user_sessions insert failed (non-fatal): '.$e->getMessage());
        }
    }
}