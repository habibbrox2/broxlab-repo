<?php

namespace App\Http\Controllers;

use App\Support\MailService;
use App\Support\SecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/AuthController.php — login, logout and
 * register over the shared legacy native session (LegacySessionGuard).
 *
 * Scope: core email/password auth. 2FA (disabled globally), remember-me,
 * email verification sending, Firebase OAuth, guest CV/FCM token claims are
 * tracked in migration/REMAINING_STEPS.md.
 */
class AuthController extends Controller
{
    public function __construct(
        protected SecurityService $security,
    ) {}

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/');
        }

        // Auto-login via remember-me cookie (legacy GET /login parity).
        $rememberUser = $this->security->userFromRememberCookie();
        if ($rememberUser) {
            $laravelUser = \App\Models\User::query()->find((int) $rememberUser->id);
            if ($laravelUser) {
                Auth::login($laravelUser);

                return redirect()->intended('/');
            }
            $this->security->clearRememberCookie((int) $rememberUser->id);
        }

        return view('auth.login', ['title' => 'Login']);
    }

    public function login(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            $result = $this->attemptLogin($request);
            if ($result['success']) {
                return response()->json(['success' => true, 'message' => 'Login successful', 'user_id' => $result['user_id']]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'require_2fa' => $result['require_2fa'] ?? false,
                'redirect' => ($result['require_2fa'] ?? false) ? '/verify-2fa' : null,
            ], ($result['require_2fa'] ?? false) ? 403 : 401);
        }

        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'username/email']);

        $result = $this->attemptLogin($request);

        if (! $result['success']) {
            // 2FA required → session flag + redirect to the verify page
            if (($result['require_2fa'] ?? false)) {
                return redirect('/verify-2fa')->with('status', 'Please verify your 2FA code');
            }

            return back()->withInput()->withErrors(['username' => $result['error']]);
        }

        $redirect = (string) $request->input('redirect', '');
        if ($redirect !== '' && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            return redirect($redirect);
        }

        return redirect('/');
    }

    protected function attemptLogin(Request $request): array
    {
        $usernameOrEmail = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $rememberMe = $request->boolean('remember_me');

        if ($usernameOrEmail === '' || $password === '') {
            return ['success' => false, 'error' => 'Username/Email and password are required'];
        }

        // Legacy findByUsernameOrEmail: (username = ? OR email = ?) AND deleted_at IS NULL
        $user = DB::table('users')
            ->where(function ($q) use ($usernameOrEmail) {
                $q->where('username', $usernameOrEmail)->orWhere('email', $usernameOrEmail);
            })
            ->whereNull('deleted_at')
            ->first();

        if (! $user) {
            usleep(random_int(100000, 300000)); // anti-timing parity

            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        if ($user->status !== 'active') {
            return ['success' => false, 'error' => "Account is {$user->status}. Please contact support."];
        }

        if (empty($user->password) || ! Hash::check($password, $user->password)) {
            usleep(random_int(100000, 300000)); // anti-timing parity

            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        // Email verification gate (legacy: isEmailVerificationRequired is on).
        // Legacy auto-sends a fresh verification email on every blocked login.
        if (! (int) $user->email_verified) {
            $_SESSION['pending_verification'] = [
                'user_id' => (int) $user->id,
                'email' => $user->email,
            ];

            try {
                $this->security->sendVerificationEmail($user);
                $this->logActivity((int) $user->id, 'Verification Email Sent on Login Attempt', 'success', ['email' => $user->email, 'auto_sent' => true]);
            } catch (\Throwable $e) {
                Log::error('Verification email send failed on login: '.$e->getMessage());
            }

            return [
                'success' => false,
                'error' => 'Please verify your email before logging in. A verification email has been sent to your email address. Check your inbox (and spam folder) for the verification link.',
            ];
        }

        // 2FA gate (legacy: is2FAEnabled || is2FARequiredForAdmin → challenge
        // token in password_resets + pending_2fa session, no session yet).
        if ($this->security->is2FAEnabled((int) $user->id) || $this->security->is2FARequiredForAdmin((int) $user->id)) {
            $challengeToken = $this->security->create2FAChallenge((int) $user->id);
            if ($challengeToken) {
                $_SESSION['pending_2fa'] = [
                    'user_id' => (int) $user->id,
                    'challenge_token' => $challengeToken,
                ];

                return [
                    'success' => false,
                    'require_2fa' => true,
                    'user_id' => (int) $user->id,
                    'error' => '2FA required',
                ];
            }
        }

        // Record a successful login, then open the shared native session.
        try {
            DB::table('auth_audit_log')->insert([
                'user_id' => (int) $user->id,
                'event_type' => 'login',
                'username_attempted' => $usernameOrEmail,
                'login_method' => 'email_password',
                'success' => 1,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'created_at' => now(), // NOTE: auth_audit_log has no updated_at column
            ]);
        } catch (\Throwable $e) {
            Log::warning('login audit failed (non-fatal): '.$e->getMessage());
        }

        $laravelUser = \App\Models\User::query()->find((int) $user->id);
        Auth::login($laravelUser, $rememberMe);

        // Remember-me cookie (legacy parity: setting-gated, 30 days).
        if ($rememberMe && (bool) $this->security->setting('enable_remember_me', true)) {
            $this->security->setRememberCookie((int) $user->id);
        }

        $this->logActivity((int) $user->id, 'User Login', 'success');

        return ['success' => true, 'user_id' => (int) $user->id];
    }

    public function logout(Request $request): RedirectResponse|JsonResponse
    {
        $userId = Auth::id();

        if ($userId) {
            $this->logActivity((int) $userId, 'User Logout', 'success');
        }

        Auth::logout();

        // Revoke remember tokens + expire the cookie (legacy clearRememberMeCookie).
        $this->security->clearRememberCookie((int) $userId);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Logged out']);
        }

        return redirect('/');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('auth.register', ['title' => 'Register']);
    }

    public function register(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'username' => ['required', 'regex:/^[a-zA-Z0-9._-]{3,30}$/'],
            'email' => ['required', 'email'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
            'confirm_password' => ['required', 'same:password'],
            'terms' => ['sometimes', 'accepted'],
        ]);

        $username = trim((string) $request->input('username', ''));
        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $first = trim((string) $request->input('first_name', ''));
        $last = trim((string) $request->input('last_name', ''));
        $password = (string) $request->input('password', '');

        $passwordError = $this->passwordValidationError($password);
        if ($passwordError) {
            return back()->withInput()->withErrors(['password' => $passwordError]);
        }

        if (DB::table('users')->where('username', $username)->exists()) {
            return back()->withInput()->withErrors(['username' => 'This username is already taken']);
        }

        if (DB::table('users')->where('email', $email)->exists()) {
            return back()->withInput()->withErrors(['email' => 'An account with this email already exists']);
        }

        $userId = DB::table('users')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'first_name' => $first !== '' ? $first : null,
            'last_name' => $last !== '' ? $last : null,
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $userId) {
            return back()->withErrors(['error' => 'Registration failed. Please try again.']);
        }

        // Default 'user' role (legacy hardcodes role id 4)
        DB::table('user_roles')->insertOrIgnore([
            'user_id' => $userId,
            'role_id' => 4,
            'created_at' => now(),
        ]);

        $this->logActivity($userId, 'User Registration', 'success', ['email' => $email]);

        // Email verification required (legacy parity): token + template email
        // + pending-verification session state.
        $newUser = DB::table('users')->where('id', $userId)->first();
        $verificationSent = false;
        if ($this->security->isEmailVerificationRequired()) {
            try {
                $verificationSent = $this->security->sendVerificationEmail($newUser);
            } catch (\Throwable $e) {
                Log::error('Registration verification email failed: '.$e->getMessage());
            }

            $_SESSION['pending_verification'] = [
                'user_id' => $userId,
                'email' => $email,
            ];

            $this->logActivity($userId, 'User Registered - Email Verification Pending', 'success', ['email' => $email]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Please check your email to verify your account.',
            ], 201);
        }

        $status = 'Registration successful! Please verify your email before logging in.';
        if (! $verificationSent) {
            $status .= ' (Email sending is not configured — use the resend page after setup.)';
        }

        return redirect('/login')->with('status', $status);
    }

    /**
     * Port of SecurityManager::getPasswordValidationError defaults
     * (min 8 + complexity from app_security_settings).
     */
    protected function passwordValidationError(string $password): ?string
    {
        $min = (int) DB::table('app_security_settings')->where('setting_key', 'min_password_length')->value('setting_value') ?: 8;

        if (mb_strlen($password) < $min) {
            return "Password must be at least {$min} characters long";
        }

        $complexity = json_decode((string) (DB::table('app_security_settings')->where('setting_key', 'password_complexity')->value('setting_value') ?? '{}'), true);
        $complexity = is_array($complexity) ? $complexity : [];

        if (! empty($complexity['uppercase']) && ! preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter';
        }
        if (! empty($complexity['lowercase']) && ! preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter';
        }
        if (! empty($complexity['numbers']) && ! preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number';
        }
        if (! empty($complexity['symbols']) && ! preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must contain at least one symbol';
        }

        return null;
    }

    protected function logActivity(int $userId, string $action, string $status, array $extra = []): void
    {
        try {
            $roles = DB::table('user_roles as ur')
                ->join('roles as r', 'r.id', '=', 'ur.role_id')
                ->where('ur.user_id', $userId)
                ->pluck('r.name')->first();

            DB::table('activity_logs')->insert([
                'user_id' => $userId,
                'role' => $roles ?: 'user',
                'action' => $action,
                'resource_type' => 'auth',
                'resource_id' => $userId,
                'status' => $status,
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
                'details' => json_encode($extra, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('auth activity log failed (non-fatal): '.$e->getMessage());
        }
    }

    // ============================================================
    // 2FA VERIFY (port of legacy GET/POST /verify-2fa)
    // ============================================================

    public function showVerify2FA(): View|RedirectResponse
    {
        if (empty($_SESSION['pending_2fa'])) {
            return redirect('/login')->with('error', 'No 2FA verification in progress');
        }

        return view('auth.verify-2fa', ['title' => 'Two-Factor Authentication']);
    }

    /**
     * Verify the TOTP code from the pending_2fa session, then open the
     * shared session (legacy parity). Challenge token is validated too.
     */
    public function verify2FA(Request $request): JsonResponse|RedirectResponse
    {
        $pending = $_SESSION['pending_2fa'] ?? null;
        if (! is_array($pending)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => 'No 2FA verification in progress'], 400);
            }

            return redirect('/login')->with('error', 'No 2FA verification in progress');
        }

        $code = trim((string) $request->input('code', ''));
        $userId = (int) ($pending['user_id'] ?? 0);
        $challengeToken = (string) ($pending['challenge_token'] ?? '');

        if ($code === '' || $userId <= 0) {
            return $this->verify2FAFail($request, 'Invalid 2FA code');
        }

        // Challenge must still be valid (15-minute window, legacy parity).
        if (! $this->security->verify2FAChallenge($userId, $challengeToken)) {
            unset($_SESSION['pending_2fa']);

            return $this->verify2FAFail($request, 'Your 2FA challenge expired. Please sign in again.');
        }

        if (! $this->security->verify2FACode($userId, $code)) {
            return $this->verify2FAFail($request, 'Invalid or expired 2FA code');
        }

        // Consume the challenge row (legacy ENUM truncation means the type is
        // '' or NULL — see SecurityService::CHALLENGE_TYPE), clear state, open session.
        DB::table('password_resets')
            ->where('user_id', $userId)
            ->where('token', hash('sha256', $challengeToken))
            ->where(function ($q) {
                $q->whereNull('token_type')->orWhere('token_type', '');
            })
            ->delete();
        unset($_SESSION['pending_2fa']);

        $laravelUser = \App\Models\User::query()->find($userId);
        if ($laravelUser) {
            Auth::login($laravelUser);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => '2FA verified successfully', 'redirect' => '/']);
        }

        return redirect('/');
    }

    protected function verify2FAFail(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'error' => $message], 400);
        }

        return back()->withErrors(['code' => $message]);
    }

    // ============================================================
    // EMAIL VERIFICATION (port of legacy /verify-email and
    // /send-verification-email + /resend-verification-email)
    // ============================================================

    public function showVerifyEmail(Request $request): View|RedirectResponse
    {
        $token = trim((string) $request->query('token', ''));
        $email = trim((string) $request->query('email', ''));
        $error = null;

        // Auto-verify when a token is supplied in the link (legacy parity).
        if ($token !== '' && $email === '') {
            if ($this->security->verifyEmailWithToken($token)) {
                unset($_SESSION['pending_verification']);

                return redirect('/login')->with('status', 'Email verified successfully! You can now log in.');
            }
            $error = 'Invalid or expired verification link. Please paste the token manually below.';
        }

        return view('auth.verify-email', [
            'title' => 'Verify Email',
            'token' => $token,
            'email' => $email !== '' ? $email : (string) ($_SESSION['pending_verification']['email'] ?? ''),
            'error' => $error,
        ]);
    }

    /** Manual token entry (legacy POST /verify-email accepts token|verification_token). */
    public function verifyEmail(Request $request): RedirectResponse|JsonResponse
    {
        $token = trim((string) ($request->input('verification_token') ?? $request->input('token', '')));
        if ($token === '') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => 'Verification token is required'], 400);
            }

            return redirect('/verify-email')->with('error', 'Verification token is required');
        }

        if ($this->security->verifyEmailWithToken($token)) {
            unset($_SESSION['pending_verification']);
            $message = 'Email verified successfully! You can now log in.';

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect('/login')->with('status', $message);
        }

        $error = 'Invalid or expired token. Please request a new verification email.';
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'error' => $error], 400);
        }

        return redirect('/send-verification-email')->with('error', $error);
    }

    /** Legacy GET /send-verification-email — pending page with resend form. */
    public function showSendVerificationEmail(): View|RedirectResponse
    {
        $pending = $_SESSION['pending_verification'] ?? null;

        return view('auth.send-verification-email', [
            'title' => 'Verify Email',
            'email' => (string) (is_array($pending) ? ($pending['email'] ?? '') : ''),
        ]);
    }

    /**
     * Legacy POST /resend-verification-email — regenerates + re-sends;
     * response never reveals whether the address exists.
     */
    public function resendVerificationEmail(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = mb_strtolower(trim((string) $request->input('email', '')));

        $user = DB::table('users')->where('email', $email)->whereNull('deleted_at')->first();
        if ($user && ! (int) $user->email_verified) {
            try {
                $this->security->sendVerificationEmail($user);
                $this->logActivity((int) $user->id, 'Verification Email Resent', 'success', ['email' => $email]);
            } catch (\Throwable $e) {
                Log::error('Verification resend failed: '.$e->getMessage());
            }
        }

        $message = 'If the email address exists and is not verified, a verification email has been sent.';
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect('/login')->with('status', $message);
    }

    // ============================================================
    // PASSWORD RESET (port of legacy SecurityManager::generate/
    // verify/resetPasswordWithToken + AuthController routes)
    // ============================================================

    public function showForgotPassword(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('auth.forgot-password', [
            'title' => 'Forgot Password',
            'old_email' => (string) request()->input('email', ''),
        ]);
    }

    public function forgotPassword(Request $request, MailService $mailer): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return $this->handleForgotJson($request, $mailer);
        }

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $this->issuePasswordReset($email, $mailer);

        // Never reveal whether the account exists (legacy parity).
        return redirect('/login')->with('status', 'If an account with that email exists, a password reset link has been sent');
    }

    protected function handleForgotJson(Request $request, MailService $mailer): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = mb_strtolower(trim((string) $validated['email']));
        $this->issuePasswordReset($email, $mailer);

        return response()->json([
            'success' => true,
            'message' => 'If an account with that email exists, a password reset link has been sent',
        ]);
    }

    /**
     * Generate a reset token (if the account exists) and send the
     * template email — mirrors legacy POST /forgot-password.
     */
    protected function issuePasswordReset(string $email, MailService $mailer): void
    {
        $user = DB::table('users')
            ->where('email', $email)
            ->whereNull('deleted_at')
            ->first();

        if (! $user) {
            return;
        }

        $rawToken = $this->generatePasswordResetToken((int) $user->id);
        if (! $rawToken) {
            return;
        }

        try {
            $resetLink = url('/reset-password').'?token='.rawurlencode($rawToken);
            $name = trim((string) ($user->first_name ?? '').' '.($user->last_name ?? ''));
            $name = $name !== '' ? $name : (string) ($user->username ?? 'User');
            $siteName = (string) app(\App\Support\AppSettings::class)->get('site_name', 'BroxBhai');

            $mailer->sendTemplate('password_reset', $user->email, $name, [
                'APP_NAME' => $siteName,
                'USER_NAME' => $name,
                'USER_EMAIL' => $user->email,
                'RESET_LINK' => $resetLink,
                'EXPIRY_TIME' => '60 minutes',
            ]);
        } catch (\Throwable $e) {
            Log::error('password reset email failed: '.$e->getMessage());
        }
    }

    public function showResetPassword(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/');
        }

        $token = trim((string) $request->query('token', ''));
        if ($token === '' || ! $this->verifyPasswordResetToken($token)) {
            return redirect('/forgot-password')->with('error', 'Invalid or expired reset link');
        }

        return view('auth.reset-password', [
            'title' => 'Reset Password',
            'token_valid' => true,
            'reset_token' => $token,
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return $this->handleResetJson($request);
        }

        $request->validate([
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string'],
            'confirm_password' => ['required', 'same:password'],
        ]);

        $token = (string) $request->input('reset_token', '');
        $password = (string) $request->input('password', '');

        $passwordError = $this->passwordValidationError($password);
        if ($passwordError) {
            return redirect('/reset-password?token='.rawurlencode($token))->withErrors(['password' => $passwordError]);
        }

        if ($this->resetPasswordWithToken($token, $password)) {
            return redirect('/login')->with('status', 'Your password has been reset successfully. Please log in with your new password.');
        }

        return redirect('/forgot-password')->with('error', 'Failed to reset password. The link may have expired.');
    }

    protected function handleResetJson(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string'],
            'confirm_password' => ['required', 'same:password'],
        ]);

        $token = (string) $validated['reset_token'];
        $password = (string) $validated['password'];

        $passwordError = $this->passwordValidationError($password);
        if ($passwordError) {
            return response()->json(['success' => false, 'error' => $passwordError], 400);
        }

        if ($this->resetPasswordWithToken($token, $password)) {
            return response()->json(['success' => true, 'message' => 'Password reset successful. Please log in.']);
        }

        return response()->json(['success' => false, 'error' => 'Invalid or expired reset link'], 401);
    }

    /**
     * Generate a reset token and store its SHA-256 hash (legacy parity).
     * Returns the raw token (sent in the email) or null on failure.
     */
    protected function generatePasswordResetToken(int $userId): ?string
    {
        $rawToken = bin2hex(random_bytes(64));

        $inserted = DB::table('password_resets')->insertGetId([
            'user_id' => $userId,
            'token' => hash('sha256', $rawToken),
            'token_type' => 'password_reset',
            'expires_at' => now()->addHour(),
        ]);

        if (! $inserted) {
            return null;
        }

        $this->logActivity($userId, 'Password Reset Token Generated', 'success', ['expires_at' => now()->addHour()->toDateTimeString()]);

        return $rawToken;
    }

    /**
     * Verify an unexpired, unused reset token. Returns token row or null.
     */
    protected function verifyPasswordResetToken(string $token): ?object
    {
        $row = DB::table('password_resets')
            ->where('token', hash('sha256', $token))
            ->where('used', 0)
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            return null;
        }

        if (strtotime((string) $row->expires_at) < time()) {
            return null;
        }

        return $row;
    }

    /**
     * Apply a new password when a valid reset token is supplied (legacy parity:
     * update users + mark token used with ip/user agent).
     */
    protected function resetPasswordWithToken(string $token, string $newPassword): bool
    {
        $tokenData = $this->verifyPasswordResetToken($token);
        if (! $tokenData) {
            return false;
        }

        $userId = (int) $tokenData->user_id;

        DB::table('users')->where('id', $userId)->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
        ]);

        DB::table('password_resets')->where('id', $tokenData->id)->update([
            'used' => 1,
            'used_at' => now(),
            'used_ip' => request()->ip(),
        ]);

        $this->logActivity($userId, 'Password Reset - Success', 'success', ['ip' => request()->ip()]);

        return true;
    }
}
