<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2 leftovers — ports of legacy SecurityManager + AuthManager pieces:
 *
 * - TOTP 2FA: verify2FACode / verifyTOTPCode / base32Decode (exact algorithm:
 *   SHA1 HMAC, 30s window, ±1 drift, 6 digits) + challenge tokens stored as
 *   `twofa_challenge` rows in password_resets (15 min, legacy parity).
 * - Remember-me: remember_tokens rows (SHA-256 hash + token family, 30 days),
 *   cookie `broxbhai_remember` holding JSON {token, family}, rotation on
 *   auto-login, revoke on logout.
 * - Email verification: SHA-256 token in users.email_verification_token with
 *   configurable expiry (default 24h), one-shot verify (sets email_verified=1,
 *   clears token), resend with no-enumeration responses.
 */
class SecurityService
{
    public const REMEMBER_COOKIE = 'broxbhai_remember';

    protected ?array $settingsCache = null;

    public function __construct(
        protected MailService $mailer,
    ) {}

    // ── Settings (port of SecurityManager::getSetting — cached per request) ──

    public function setting(string $key, mixed $default = null): mixed
    {
        if ($this->settingsCache === null) {
            $this->settingsCache = DB::table('app_security_settings')
                ->pluck('setting_value', 'setting_key')
                ->all();
        }

        return $this->settingsCache[$key] ?? $default;
    }

    // ── 2FA (TOTP) ────────────────────────────────────────────────────

    /** Port of SecurityManager::is2FAEnabled (global override + per-user row). */
    public function is2FAEnabled(int $userId): bool
    {
        if ((bool) $this->setting('enable_2fa_global', false)) {
            return true;
        }

        return DB::table('user_security')->where('user_id', $userId)->where('twofa_enabled', 1)->exists();
    }

    /** Port of SecurityManager::is2FARequiredForAdmin (legacy uses users.role which is unused; roles live in user_roles, so only the global/user check applies here). */
    public function is2FARequiredForAdmin(int $userId): bool
    {
        if (! (bool) $this->setting('require_2fa_for_admin', false)) {
            return false;
        }

        // Legacy checked users.role = 'admin' — that column is unused; treat
        // "admin role assignment" via user_roles.
        return DB::table('user_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.name', 'admin')
            ->whereNull('r.deleted_at')
            ->exists();
    }

    /**
     * Port of AuthManager 2FA challenge: challenge row in password_resets, 15 min.
     *
     * Schema note: legacy inserts token_type 'twofa_challenge', but the column is
     * ENUM('password_reset','email_verification'). Legacy's non-strict MySQL
     * silently stored '' for those rows; our strict-mode connection rejects ''.
     * We insert NULL (column is nullable) — the challenge is identified by its
     * SHA-256 token + expiry, so the type label is cosmetic. No schema changes.
     */
    protected const CHALLENGE_TYPE = null;

    public function create2FAChallenge(int $userId): ?string
    {
        $rawToken = bin2hex(random_bytes(32));

        $ok = DB::table('password_resets')->insert([
            'user_id' => $userId,
            'token' => hash('sha256', $rawToken),
            'token_type' => self::CHALLENGE_TYPE,
            'expires_at' => now()->addMinutes(15),
        ]);

        return $ok ? $rawToken : null;
    }

    /** Verify a challenge token issued at login time (consumer of pending_2fa). */
    public function verify2FAChallenge(int $userId, string $rawToken): bool
    {
        if ($rawToken === '') {
            return false;
        }

        return DB::table('password_resets')
            ->where('user_id', $userId)
            ->where('token', hash('sha256', $rawToken))
            ->where(function ($q) {
                $q->whereNull('token_type')->orWhere('token_type', '');
            })
            ->where('expires_at', '>', now())
            ->exists();
    }

    /** Port of SecurityManager::verify2FACode — secret lookup + activity log. */
    public function verify2FACode(int $userId, string $code): bool
    {
        $row = DB::table('user_security')
            ->where('user_id', $userId)
            ->where('twofa_enabled', 1)
            ->first(['twofa_secret']);

        if (! $row || ! $row->twofa_secret) {
            $this->logActivity('2FA Verification Failed - No Secret', $userId, 'failure');

            return false;
        }

        if ($this->verifyTOTPCode($code, (string) $row->twofa_secret)) {
            $this->logActivity('2FA Code Verified', $userId, 'success');
            DB::table('user_security')->where('user_id', $userId)->update(['last_verified_at' => now()]);

            return true;
        }

        $this->logActivity('2FA Verification Failed - Invalid Code', $userId, 'failure');

        return false;
    }

    /**
     * Exact port of SecurityManager::verifyTOTPCode — Google Authenticator
     * compatible: HMAC-SHA1 over a 30s counter, ±1 window for clock drift,
     * dynamic truncation to 6 digits.
     */
    public function verifyTOTPCode(string $code, string $secret): bool
    {
        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return false;
        }

        $secretBinary = $this->base32Decode($secret);
        if ($secretBinary === null || $secretBinary === '') {
            return false;
        }

        $timeCounter = (int) floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            $counter = $timeCounter + $i;
            $hash = hash_hmac('sha1', pack('N*', 0, $counter), $secretBinary, true);
            $offset = ord($hash[19]) & 0xf;
            $value = unpack('N', substr($hash, $offset, 4))[1];
            $value = ($value & 0x7fffffff) % 1000000;
            $generatedCode = str_pad((string) $value, 6, '0', STR_PAD_LEFT);

            if (hash_equals($generatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /** Exact port of SecurityManager::base32Decode (RFC 4648 alphabet). */
    public function base32Decode(string $input): ?string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper($input);
        $input = rtrim($input, '=');

        if ($input === '') {
            return null;
        }

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            if (strpos($alphabet, $input[$i]) === false) {
                return null;
            }
        }

        $output = '';
        $bits = 0;
        $buffer = 0;

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $buffer = ($buffer << 5) | strpos($alphabet, $input[$i]);
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xff);
            }
        }

        return $output;
    }

    // ── Remember me ───────────────────────────────────────────────────

    /** Port of SecurityManager::generateRememberMeToken → remember_tokens row. */
    public function generateRememberMeToken(int $userId): ?array
    {
        if (! (bool) $this->setting('enable_remember_me', true)) {
            return null;
        }

        $rawToken = bin2hex(random_bytes(64)); // legacy TOKEN_LENGTH = 64 bytes
        $tokenFamily = bin2hex(random_bytes(16));
        $duration = (int) $this->setting('remember_me_duration', 2592000);
        $expiresAt = now()->addSeconds($duration);

        $ok = DB::table('remember_tokens')->insert([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $rawToken),
            'token_family' => $tokenFamily,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'expires_at' => $expiresAt,
            'is_active' => 1,
        ]);

        if (! $ok) {
            return null;
        }

        $this->logActivity('Remember Me Token Generated', $userId, 'success', ['expires_at' => $expiresAt->toDateTimeString()]);

        return [
            'token' => $rawToken,
            'family' => $tokenFamily,
            'expires' => $expiresAt->toDateTimeString(),
        ];
    }

    /** Port of AuthManager::createRememberMeCookie — queues the shared cookie (unencrypted, legacy-readable). */
    public function setRememberCookie(int $userId): bool
    {
        $tokenData = $this->generateRememberMeToken($userId);
        if (! $tokenData) {
            return false;
        }

        Cookie::queue(Cookie::make(
            self::REMEMBER_COOKIE,
            json_encode(['token' => $tokenData['token'], 'family' => $tokenData['family']]),
            now()->diffInMinutes($tokenData['expires'], true),
            '/',
            null,
            false, // secure=false so http:// localhost keeps working (legacy hardcodes true)
            true,  // httpOnly
            false,
            'Lax',
        ));

        return true;
    }

    /** Read the raw remember cookie from the request (legacy sets it unencrypted). */
    protected function rememberCookieValue(): ?string
    {
        $value = request()?->cookie(self::REMEMBER_COOKIE);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Read + verify the remember cookie; returns user row or null (and clears invalid cookies). */
    public function userFromRememberCookie(): ?object
    {
        $raw = $this->rememberCookieValue();
        if ($raw === null) {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || empty($data['token'])) {
            $this->clearRememberCookie();

            return null;
        }

        $user = $this->userByRememberToken((string) $data['token']);
        if (! $user) {
            $this->clearRememberCookie();

            return null;
        }

        if (! empty($user->deleted_at) || $user->status !== 'active') {
            $this->clearRememberCookie();

            return null;
        }

        // Rotation (legacy parity: rotate on every auto-login)
        if ((bool) $this->setting('remember_me_rotation', true)) {
            $this->rotateRememberMeToken((string) $data['token'], $user->id, (string) ($data['family'] ?? ''));
        }

        $this->logActivity('Auto-login via Remember Me', (int) $user->id, 'success');

        return $user;
    }

    /** Port of SecurityManager::verifyRememberMeToken + getUserByRememberToken. */
    public function userByRememberToken(string $rawToken): ?object
    {
        $row = DB::table('remember_tokens')
            ->where('token_hash', hash('sha256', $rawToken))
            ->where('is_active', 1)
            ->whereNull('revoked_at')
            ->first(['id', 'user_id', 'token_family', 'expires_at']);

        if (! $row) {
            return null;
        }

        if (strtotime((string) $row->expires_at) < time()) {
            DB::table('remember_tokens')->where('user_id', $row->user_id)->update([
                'is_active' => 0,
                'revoked_at' => now(),
            ]);

            return null;
        }

        return DB::table('users')
            ->where('id', $row->user_id)
            ->whereNull('deleted_at')
            ->first();
    }

    /** Port of SecurityManager::rotateRememberMeToken (same family, new token). */
    public function rotateRememberMeToken(string $oldToken, int $userId, string $family): ?array
    {
        if (! (bool) $this->setting('remember_me_rotation', true)) {
            return null;
        }

        DB::table('remember_tokens')->where('token_hash', hash('sha256', $oldToken))->update([
            'is_active' => 0,
            'last_used_at' => now(),
        ]);

        $rawToken = bin2hex(random_bytes(64));
        $duration = (int) $this->setting('remember_me_duration', 2592000);
        $expiresAt = now()->addSeconds($duration);

        DB::table('remember_tokens')->insert([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $rawToken),
            'token_family' => $family !== '' ? $family : bin2hex(random_bytes(16)),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'expires_at' => $expiresAt,
            'is_active' => 1,
        ]);

        Cookie::queue(Cookie::make(
            self::REMEMBER_COOKIE,
            json_encode(['token' => $rawToken, 'family' => $family]),
            now()->diffInMinutes($expiresAt, true),
            '/',
            null,
            false,
            true,
            false,
            'Lax',
        ));

        return ['token' => $rawToken, 'family' => $family, 'expires' => $expiresAt->toDateTimeString()];
    }

    /** Port of AuthManager::clearRememberMeCookie — expire cookie + revoke rows. */
    public function clearRememberCookie(int $userId = 0): void
    {
        Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE));

        if ($userId > 0) {
            DB::table('remember_tokens')->where('user_id', $userId)->update([
                'is_active' => 0,
                'revoked_at' => now(),
            ]);
        }
    }

    // ── Email verification ────────────────────────────────────────────

    /** Port of SecurityManager::generateEmailVerificationToken (SHA-256 in users row). */
    public function generateEmailVerificationToken(int $userId): ?string
    {
        $rawToken = bin2hex(random_bytes(32));
        $expirySeconds = (int) $this->setting('email_verification_token_expiry', 86400);
        $expiresAt = now()->addSeconds($expirySeconds);

        $ok = DB::table('users')->where('id', $userId)->update([
            'email_verification_token' => hash('sha256', $rawToken),
            'email_verification_token_expires_at' => $expiresAt,
        ]);

        if (! $ok) {
            return null;
        }

        $this->logActivity('Email Verification Token Generated', $userId, 'success');

        return $rawToken;
    }

    /**
     * Send the verification email through the shared `email_verification`
     * template (VERIFY_LINK / EXPIRY_TIME placeholders), same as legacy
     * sendEmailVerificationEmail().
     *
     * The actual send is dispatched to the `notifications` queue so the
     * registration/login HTTP request does not wait on SMTP. The token is
     * generated before dispatch so the link is valid even if the worker is
     * delayed; if dispatch itself fails the caller still gets a clear false.
     */
    public function sendVerificationEmail(object $user): bool
    {
        $rawToken = $this->generateEmailVerificationToken((int) $user->id);
        if (! $rawToken) {
            return false;
        }

        $name = trim((string) ($user->first_name ?? ''));
        if ($name === '') {
            $name = (string) ($user->username ?? 'User');
        }

        $siteName = (string) app(AppSettings::class)->get('site_name', 'BroxBhai');
        $verifyLink = url('/verify-email').'?token='.rawurlencode($rawToken);
        $expiryMinutes = ((int) $this->setting('email_verification_token_expiry', 86400) / 60);

        try {
            SendVerificationEmailJob::dispatch(
                (int) $user->id,
                $rawToken,
                (string) $user->email,
                $name,
                $siteName,
                $verifyLink,
                $expiryMinutes.' minutes',
            )->onConnection('notifications')->onQueue('notifications');

            return true;
        } catch (\Throwable $e) {
            Log::error('Verification email dispatch failed: '.$e->getMessage());

            return false;
        }
    }

    /** Port of SecurityManager::verifyEmailWithToken — one-shot consume. */
    public function verifyEmailWithToken(string $rawToken): bool
    {
        $row = DB::table('users')
            ->where('email_verification_token', hash('sha256', $rawToken))
            ->where('email_verification_token_expires_at', '>', now())
            ->first(['id']);

        if (! $row) {
            $this->logActivity('Email Verification Failed - Invalid Token', 0, 'failure');

            return false;
        }

        $ok = DB::table('users')->where('id', $row->id)->update([
            'email_verified' => 1,
            'email_verification_token' => null,
            'email_verification_token_expires_at' => null,
        ]) >= 0;

        if ($ok) {
            $this->logActivity('Email Verified', (int) $row->id, 'success');
        }

        return $ok;
    }

    /** Port of SecurityManager::isEmailVerificationRequired. */
    public function isEmailVerificationRequired(): bool
    {
        return (bool) $this->setting('require_email_verification', true);
    }

    // ── Shared ────────────────────────────────────────────────────────

    protected function logActivity(string $action, int $userId, string $status, array $details = []): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => $userId,
                'role' => 'user',
                'action' => $action,
                'resource_type' => 'auth',
                'resource_id' => $userId,
                'status' => $status,
                'ip_address' => request()?->ip() ?? '0.0.0.0',
                'user_agent' => mb_substr((string) (request()?->userAgent() ?? ''), 0, 500),
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('security activity log failed (non-fatal): '.$e->getMessage());
        }
    }
}
