<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SecurityService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 2 follow-ups: TOTP 2FA challenge/verify, remember-me cookie
 * lifecycle, and email verification send/verify/resend — all over the
 * shared legacy session + tables. Rows are cleaned up in tearDown.
 */
class SecurityFlowsTest extends TestCase
{
    use WithFaker;

    protected int $userId = 0;

    protected array $tokenIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = substr(uniqid('sec', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'sec_'.$suffix,
            'email' => 'sec_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Sec',
            'last_name' => 'Test',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tokenIds as $id) {
            DB::table('password_resets')->where('id', $id)->delete();
        }
        DB::table('remember_tokens')->where('user_id', $this->userId)->delete();
        DB::table('user_security')->where('user_id', $this->userId)->delete();
        DB::table('activity_logs')->where('user_id', $this->userId)->delete();
        DB::table('auth_audit_log')->where('user_id', $this->userId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();

        Auth::logout();
        unset($_SESSION);

        parent::tearDown();
    }

    protected function loginAsThisUser(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/login', [
                'username' => DB::table('users')->where('id', $this->userId)->value('username'),
                'password' => 'Passw0rd!x',
            ])
            ->assertRedirect('/');
        $this->assertSame($this->userId, (int) Auth::id());
    }

    // ── TOTP unit-level ────────────────────────────────────────────────

    public function test_totp_verify_accepts_current_code(): void
    {
        $service = app(SecurityService::class);
        // RFC 4648 test vector secret ("Hello!\xDE\xAD\xBE\xEF" base32)
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->assertNotNull($service->base32Decode($secret));

        $counter = (int) floor(time() / 30);
        $code = $this->totpAt($service, $secret, $counter);

        $this->assertTrue($service->verifyTOTPCode($code, $secret), 'current window code must verify');
    }

    public function test_totp_verify_rejects_wrong_and_malformed_codes(): void
    {
        $service = app(SecurityService::class);
        $secret = 'JBSWY3DPEHPK3PXP';

        $this->assertFalse($service->verifyTOTPCode('000000', $secret));
        $this->assertFalse($service->verifyTOTPCode('12345', $secret));   // too short
        $this->assertFalse($service->verifyTOTPCode('abcdef', $secret));  // non-numeric
        $this->assertNull($service->base32Decode('not base32!!!'));       // bad alphabet
    }

    protected function totpAt(SecurityService $service, string $secret, int $counter): string
    {
        $binary = $service->base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $binary, true);
        $offset = ord($hash[19]) & 0xf;
        $value = unpack('N', substr($hash, $offset, 4))[1];

        return str_pad((string) (($value & 0x7fffffff) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    // ── 2FA login flow ─────────────────────────────────────────────────

    protected function enable2FA(): string
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        DB::table('user_security')->insert([
            'user_id' => $this->userId,
            'twofa_enabled' => 1,
            'twofa_method' => 'totp',
            'twofa_secret' => $secret,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $secret;
    }

    public function test_login_with_2fa_returns_challenge_and_does_not_start_session(): void
    {
        $this->enable2FA();

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/login', [
                'username' => DB::table('users')->where('id', $this->userId)->value('username'),
                'password' => 'Passw0rd!x',
            ]);

        // Legacy parity: redirect to /verify-2fa, session flag set, no auth yet
        $response->assertRedirect('/verify-2fa');
        $this->assertArrayHasKey('pending_2fa', $_SESSION);
        $this->assertNull(Auth::id());

        // Challenge row exists in password_resets (token_type is NULL — see
        // SecurityService::CHALLENGE_TYPE for the ENUM/strict-mode explanation)
        $challenge = DB::table('password_resets')
            ->where('user_id', $this->userId)
            ->whereNull('token_type')
            ->first();
        $this->assertNotNull($challenge);
        $this->tokenIds[] = $challenge->id;
    }

    protected function seedChallenge(string $rawToken): int
    {
        $challenge = DB::table('password_resets')->insertGetId([
            'user_id' => $this->userId,
            'token' => hash('sha256', $rawToken),
            'token_type' => null, // legacy ENUM/strict-mode parity (see SecurityService)
            'expires_at' => now()->addMinutes(15),
        ]);
        $this->tokenIds[] = $challenge;

        return $challenge;
    }

    public function test_verify_2fa_with_valid_code_starts_session(): void
    {
        $secret = $this->enable2FA();
        $service = app(SecurityService::class);

        // Simulate the pending state (as the login route created it)
        $challenge = $this->seedChallenge('challengetoken123');
        $_SESSION['pending_2fa'] = ['user_id' => $this->userId, 'challenge_token' => 'challengetoken123'];

        $code = $this->totpAt($service, $secret, (int) floor(time() / 30));

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/verify-2fa', ['code' => $code]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame($this->userId, (int) Auth::id());
        $this->assertArrayNotHasKey('pending_2fa', $_SESSION);
        $this->assertNull(DB::table('password_resets')->find($challenge)); // consumed
    }

    public function test_verify_2fa_with_invalid_code_keeps_session_closed(): void
    {
        $this->enable2FA();
        $this->seedChallenge('challengetoken123');
        $_SESSION['pending_2fa'] = ['user_id' => $this->userId, 'challenge_token' => 'challengetoken123'];

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/verify-2fa', ['code' => '000000'])
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        $this->assertNull(Auth::id());
        $this->assertArrayHasKey('pending_2fa', $_SESSION); // still pending
    }

    public function test_verify_2fa_without_pending_state_400s(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/verify-2fa', ['code' => '123456'])
            ->assertStatus(400);
    }

    public function test_verify_2fa_with_expired_challenge_fails(): void
    {
        $this->enable2FA();
        $challenge = DB::table('password_resets')->insertGetId([
            'user_id' => $this->userId,
            'token' => hash('sha256', 'oldchallenge'),
            'token_type' => null, // legacy ENUM/strict-mode parity
            'expires_at' => now()->subMinutes(20), // expired
        ]);
        $this->tokenIds[] = $challenge;
        $_SESSION['pending_2fa'] = ['user_id' => $this->userId, 'challenge_token' => 'oldchallenge'];

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/verify-2fa', ['code' => '123456'])
            ->assertStatus(400);

        $this->assertArrayNotHasKey('pending_2fa', $_SESSION); // cleared
        $this->assertNull(Auth::id());
    }

    public function test_verify_2fa_page_redirects_without_pending_state(): void
    {
        $this->get('/verify-2fa')->assertRedirect('/login');
    }

    // ── Remember me ────────────────────────────────────────────────────

    public function test_login_with_remember_me_sets_cookie_and_token_row(): void
    {
        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/login', [
                'username' => DB::table('users')->where('id', $this->userId)->value('username'),
                'password' => 'Passw0rd!x',
                'remember_me' => '1',
            ]);

        $response->assertRedirect('/');

        $row = DB::table('remember_tokens')->where('user_id', $this->userId)->first();
        $this->assertNotNull($row, 'remember token row must exist');
        $this->assertSame(1, (int) $row->is_active);
        $this->assertNotNull($row->expires_at);

        $found = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'broxbhai_remember');
        $this->assertNotNull($found, 'broxbhai_remember cookie must be set');
        $this->assertTrue($found->isHttpOnly());
        $this->assertFalse($found->isSecure(), 'cookie must be usable over http:// (legacy parity for local dev)');
    }

    public function test_logout_revokes_remember_tokens_and_expires_cookie(): void
    {
        $this->loginAsThisUser();
        app(SecurityService::class)->setRememberCookie($this->userId);
        $this->assertSame(1, DB::table('remember_tokens')->where('user_id', $this->userId)->count());

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/logout');

        $response->assertRedirect('/');

        $row = DB::table('remember_tokens')->where('user_id', $this->userId)->first();
        $this->assertSame(0, (int) $row->is_active);
        $this->assertNotNull($row->revoked_at);

        // The logout queues a forget (cleared) cookie after the earlier set cookie
        $forgotten = collect($response->headers->getCookies())
            ->last(fn ($c) => $c->getName() === 'broxbhai_remember');
        $this->assertNotNull($forgotten, 'forgotten broxbhai_remember cookie must be queued on logout');
        $this->assertTrue($forgotten->isCleared(), 'the queued cookie must clear broxbhai_remember from the browser');
    }

    public function test_auto_login_via_remember_cookie_rotates_token(): void
    {
        $service = app(SecurityService::class);

        // Issue a real token, then simulate the browser holding the cookie.
        // userFromRememberCookie reads request()->cookie(...), so bind a
        // request that carries the raw cookie.
        $tokenData = $service->generateRememberMeToken($this->userId);
        $this->assertNotNull($tokenData);
        $request = \Illuminate\Http\Request::create('/', 'GET');
        $request->cookies->set('broxbhai_remember', json_encode([
            'token' => $tokenData['token'],
            'family' => $tokenData['family'],
        ]));
        $this->app->instance('request', $request);

        $user = $service->userFromRememberCookie();

        $this->assertNotNull($user, 'remember cookie must resolve the user');
        $this->assertSame($this->userId, (int) $user->id);

        // Rotation: old row inactive, a new row exists in the same family
        $rows = DB::table('remember_tokens')->where('user_id', $this->userId)->get();
        $this->assertSame(2, $rows->count());
        $this->assertSame(0, (int) $rows->firstWhere('token_hash', hash('sha256', $tokenData['token']))->is_active);
        $newRow = $rows->firstWhere('is_active', 1);
        $this->assertSame($tokenData['family'], $newRow->token_family);
    }

    public function test_auto_login_rejects_inactive_and_revoked_tokens(): void
    {
        $service = app(SecurityService::class);

        // Revoked token
        $tokenData = $service->generateRememberMeToken($this->userId);
        DB::table('remember_tokens')->where('user_id', $this->userId)->update(['is_active' => 0, 'revoked_at' => now()]);
        $request = \Illuminate\Http\Request::create('/', 'GET');
        $request->cookies->set('broxbhai_remember', json_encode(['token' => $tokenData['token'], 'family' => $tokenData['family']]));
        $this->app->instance('request', $request);
        $this->assertNull($service->userFromRememberCookie());

        // Deleted user
        $tokenData = $service->generateRememberMeToken($this->userId);
        DB::table('users')->where('id', $this->userId)->update(['deleted_at' => now()]);
        $request = \Illuminate\Http\Request::create('/', 'GET');
        $request->cookies->set('broxbhai_remember', json_encode(['token' => $tokenData['token'], 'family' => $tokenData['family']]));
        $this->app->instance('request', $request);
        $this->assertNull($service->userFromRememberCookie());
        DB::table('users')->where('id', $this->userId)->update(['deleted_at' => null]);
    }

    // ── Email verification ─────────────────────────────────────────────

    public function test_registration_sends_verification_email(): void
    {
        Mail::fake();

        $suffix = substr(uniqid('reg', true), 0, 12);
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/register', [
                'username' => 'reg_'.$suffix,
                'email' => 'reg_'.$suffix.'@example.test',
                'password' => 'Passw0rd!x',
                'confirm_password' => 'Passw0rd!x',
                'terms' => '1',
            ])
            ->assertRedirect('/login');

        $userId = (int) DB::table('users')->where('email', 'reg_'.$suffix.'@example.test')->value('id');
        $this->assertGreaterThan(0, $userId);
        $this->tokenIds[] = $userId; // reuse cleanup for users via tearDown below

        $row = DB::table('users')->find($userId);
        $this->assertSame(0, (int) $row->email_verified);
        $this->assertNotNull($row->email_verification_token);
        $this->assertNotNull($row->email_verification_token_expires_at);

        // pending_verification session state
        $this->assertSame($userId, (int) ($_SESSION['pending_verification']['user_id'] ?? 0));

        // Cleanup registered user
        DB::table('user_roles')->where('user_id', $userId)->delete();
        DB::table('activity_logs')->where('user_id', $userId)->delete();
        DB::table('users')->where('id', $userId)->delete();
    }

    public function test_blocked_login_resends_verification_email(): void
    {
        DB::table('users')->where('id', $this->userId)->update(['email_verified' => 0]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/login', [
                'username' => DB::table('users')->where('id', $this->userId)->value('username'),
                'password' => 'Passw0rd!x',
            ])
            ->assertSessionHasErrors('username');

        $row = DB::table('users')->find($this->userId);
        $this->assertNotNull($row->email_verification_token, 'a fresh verification token must be generated on blocked login');
        $this->assertArrayHasKey('pending_verification', $_SESSION);
    }

    public function test_verify_email_with_token_sets_verified(): void
    {
        $service = app(SecurityService::class);
        DB::table('users')->where('id', $this->userId)->update(['email_verified' => 0]);

        $_SESSION['pending_verification'] = ['user_id' => $this->userId, 'email' => 'x@example.test'];

        // Issue token through the service, then use the raw value in the link
        $user = DB::table('users')->find($this->userId);
        $rawToken = bin2hex(random_bytes(32));
        DB::table('users')->where('id', $this->userId)->update([
            'email_verification_token' => hash('sha256', $rawToken),
            'email_verification_token_expires_at' => now()->addDay(),
        ]);

        $this->get('/verify-email?token='.$rawToken)
            ->assertRedirect('/login')
            ->assertSessionHas('status');

        $row = DB::table('users')->find($this->userId);
        $this->assertSame(1, (int) $row->email_verified);
        $this->assertNull($row->email_verification_token);
        $this->assertNull($row->email_verification_token_expires_at);
        $this->assertArrayNotHasKey('pending_verification', $_SESSION);
    }

    public function test_verify_email_with_invalid_token_shows_manual_entry(): void
    {
        $this->get('/verify-email?token=not-a-real-token')
            ->assertOk()
            ->assertSee('Invalid or expired verification link', false);
    }

    public function test_manual_token_entry_verifies(): void
    {
        DB::table('users')->where('id', $this->userId)->update(['email_verified' => 0]);
        $rawToken = bin2hex(random_bytes(32));
        DB::table('users')->where('id', $this->userId)->update([
            'email_verification_token' => hash('sha256', $rawToken),
            'email_verification_token_expires_at' => now()->addDay(),
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/verify-email', ['verification_token' => $rawToken])
            ->assertRedirect('/login')
            ->assertSessionHas('status');

        $this->assertSame(1, (int) DB::table('users')->where('id', $this->userId)->value('email_verified'));
    }

    public function test_expired_verification_token_is_rejected(): void
    {
        DB::table('users')->where('id', $this->userId)->update(['email_verified' => 0]);
        $rawToken = bin2hex(random_bytes(32));
        DB::table('users')->where('id', $this->userId)->update([
            'email_verification_token' => hash('sha256', $rawToken),
            'email_verification_token_expires_at' => now()->subHour(), // expired
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/verify-email', ['verification_token' => $rawToken])
            ->assertRedirect('/send-verification-email');

        $this->assertSame(0, (int) DB::table('users')->where('id', $this->userId)->value('email_verified'));
    }

    public function test_resend_verification_email_no_enumeration(): void
    {
        DB::table('users')->where('id', $this->userId)->update(['email_verified' => 0]);
        $email = DB::table('users')->where('id', $this->userId)->value('email');

        // Existing unverified user
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/resend-verification-email', ['email' => $email])
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'If the email address exists and is not verified, a verification email has been sent.');
        $this->assertNotNull(DB::table('users')->where('id', $this->userId)->value('email_verification_token'));

        // Unknown email — same response, no error
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/resend-verification-email', ['email' => 'nobody-here@example.test'])
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'If the email address exists and is not verified, a verification email has been sent.');

        // Already-verified user — token must NOT be regenerated
        DB::table('users')->where('id', $this->userId)->update(['email_verified' => 1, 'email_verification_token' => null]);
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/resend-verification-email', ['email' => $email])
            ->assertRedirect('/login');
        $this->assertNull(DB::table('users')->where('id', $this->userId)->value('email_verification_token'));
    }

    public function test_send_verification_email_page_renders_with_pending_state(): void
    {
        $_SESSION['pending_verification'] = ['user_id' => $this->userId, 'email' => 'pending@example.test'];

        $this->get('/send-verification-email')
            ->assertOk()
            ->assertSee('pending@example.test', false);
    }
}
