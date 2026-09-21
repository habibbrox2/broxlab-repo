<?php

namespace Tests\Feature;

use App\Mail\HtmlMail;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 2 auth flows over the shared legacy native session.
 *
 * CSRF is disabled per-call (shared DB + no session token in the test client)
 * exactly like the other feature suites. Users / tokens are created with
 * unique markers and removed in tearDown.
 */
class AuthTest extends TestCase
{
    use WithFaker;

    /** @var array<int> Created user ids to clean up. */
    protected array $userIds = [];

    /** @var array<int> Created password_resets ids to clean up. */
    protected array $tokenIds = [];

    protected function tearDown(): void
    {
        foreach ($this->tokenIds as $id) {
            DB::table('password_resets')->where('id', $id)->delete();
        }
        foreach ($this->userIds as $id) {
            DB::table('user_roles')->where('user_id', $id)->delete();
            DB::table('activity_logs')->where('user_id', $id)->delete();
            DB::table('auth_audit_log')->where('user_id', $id)->delete();
            DB::table('password_resets')->where('user_id', $id)->delete();
            DB::table('users')->where('id', $id)->delete();
        }

        // Do not leak auth state between tests (shared in-process $_SESSION).
        unset($_SESSION);

        parent::tearDown();
    }

    protected function makeUser(array $overrides = []): User
    {
        $suffix = substr(uniqid('auth', true), 0, 14);
        $data = array_merge([
            'username' => 'auth_'.$suffix,
            'email' => 'auth_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Auth',
            'last_name' => 'Test',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
        ], $overrides);

        $id = DB::table('users')->insertGetId([
            ...$data,
            'password' => $data['password'],
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        $this->userIds[] = $id;

        return User::query()->find($id);
    }

    protected function postForm(string $uri, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->withoutMiddleware(ValidateCsrfToken::class)->post($uri, $data);
    }

    // ── Login ────────────────────────────────────────────────────────

    public function test_guest_sees_login_page(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back', false);
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = $this->makeUser();
        $this->assertNotNull($user);

        $this->postForm('/login', [
            'username' => $user->email,
            'password' => 'Passw0rd!x',
        ])->assertRedirect('/');

        $this->assertTrue(Auth::check());
        $this->assertSame((int) $user->id, (int) Auth::id());
        $this->assertDatabaseHas('auth_audit_log', [
            'user_id' => (int) $user->id,
            'event_type' => 'login',
            'success' => 1,
        ]);
    }

    /** Regression: createNativeSession() must propagate real roles into the
     *  shared legacy session — the dead code that overwrote the roles query
     *  with an always-empty explode() used to force role = 'user' for everyone. */
    public function test_login_session_carries_assigned_roles(): void
    {
        $user = $this->makeUser();

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (! $adminRoleId) {
            $this->markTestSkipped('roles table has no admin role row');
        }
        DB::table('user_roles')->insert([
            'user_id' => (int) $user->id,
            'role_id' => (int) $adminRoleId,
            'created_at' => now(),
        ]);

        $this->postForm('/login', [
            'username' => $user->email,
            'password' => 'Passw0rd!x',
        ])->assertRedirect('/');

        $this->assertTrue(Auth::check());
        $this->assertSame('admin', $_SESSION['role'] ?? null);
        $this->assertContains('admin', $_SESSION['roles'] ?? []);
    }

    public function test_login_without_roles_defaults_to_user_role(): void
    {
        $user = $this->makeUser();

        $this->postForm('/login', [
            'username' => $user->email,
            'password' => 'Passw0rd!x',
        ])->assertRedirect('/');

        $this->assertTrue(Auth::check());
        $this->assertSame('user', $_SESSION['role'] ?? null);
        $this->assertSame([], $_SESSION['roles'] ?? null);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = $this->makeUser();

        $this->postForm('/login', [
            'username' => $user->email,
            'password' => 'WrongPass!1',
        ])->assertSessionHasErrors('username');

        $this->assertFalse(Auth::check());
    }

    public function test_login_rejects_unknown_username(): void
    {
        $this->postForm('/login', [
            'username' => 'no-such-user-'.uniqid(),
            'password' => 'Passw0rd!x',
        ])->assertSessionHasErrors('username');

        $this->assertFalse(Auth::check());
    }

    public function test_unverified_user_cannot_log_in(): void
    {
        $user = $this->makeUser(['email_verified' => 0]);

        $this->postForm('/login', [
            'username' => $user->email,
            'password' => 'Passw0rd!x',
        ])->assertSessionHasErrors('username');

        $this->assertFalse(Auth::check());
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->get('/login')->assertRedirect();
        $this->get('/register')->assertRedirect();
    }

    // ── Register ─────────────────────────────────────────────────────

    public function test_register_creates_user_with_default_role(): void
    {
        $suffix = substr(uniqid('reg', true), 0, 14);
        $email = 'reg_'.$suffix.'@example.test';

        $this->postForm('/register', [
            'username' => 'reg_'.$suffix,
            'email' => $email,
            'first_name' => 'Reg',
            'last_name' => 'Test',
            'password' => 'Passw0rd!x',
            'confirm_password' => 'Passw0rd!x',
            'terms' => '1',
        ])->assertRedirect('/login')->assertSessionHas('status');

        $user = DB::table('users')->where('email', $email)->first();
        $this->assertNotNull($user);
        $this->userIds[] = (int) $user->id;

        $this->assertSame('email', $user->auth_provider);
        $this->assertSame(0, (int) $user->email_verified);
        $this->assertTrue(Hash::check('Passw0rd!x', (string) $user->password));

        $this->assertDatabaseHas('user_roles', [
            'user_id' => (int) $user->id,
            'role_id' => 4,
        ]);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $user = $this->makeUser();

        $this->postForm('/register', [
            'username' => 'dup_'.substr(uniqid(), 0, 8),
            'email' => $user->email,
            'password' => 'Passw0rd!x',
            'confirm_password' => 'Passw0rd!x',
        ])->assertSessionHasErrors('email');
    }

    // ── Logout ───────────────────────────────────────────────────────

    public function test_logout_clears_the_shared_session(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->assertTrue(Auth::check());

        $this->postForm('/logout', [])->assertRedirect('/');

        $this->assertFalse(Auth::check());
        $this->assertEmpty($_SESSION['user_id'] ?? null);
    }

    // ── Forgot / reset password ──────────────────────────────────────

    public function test_forgot_password_creates_token_and_never_leaks_account(): void
    {
        Mail::fake();

        // Known account → generic message, token row created, email queued.
        $user = $this->makeUser();
        $this->postForm('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/login')
            ->assertSessionHas('status');

        $tokenRow = DB::table('password_resets')
            ->where('user_id', (int) $user->id)
            ->where('token_type', 'password_reset')
            ->where('used', 0)
            ->first();
        $this->assertNotNull($tokenRow);
        $this->tokenIds[] = (int) $tokenRow->id;

        Mail::assertSent(HtmlMail::class);

        // Unknown email → identical generic message, no row.
        $before = (int) DB::table('password_resets')->count();
        $this->postForm('/forgot-password', ['email' => 'ghost-'.uniqid().'@example.test'])
            ->assertRedirect('/login')
            ->assertSessionHas('status');

        $this->assertSame($before, (int) DB::table('password_resets')->count());
    }

    public function test_reset_password_full_flow(): void
    {
        $user = $this->makeUser();

        // Mint a raw token the way the controller does.
        $raw = bin2hex(random_bytes(64));
        $tokenId = DB::table('password_resets')->insertGetId([
            'user_id' => (int) $user->id,
            'token' => hash('sha256', $raw),
            'token_type' => 'password_reset',
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->tokenIds[] = $tokenId;

        // Valid token renders the form.
        $this->get('/reset-password?token='.rawurlencode($raw))
            ->assertOk()
            ->assertSee('Set a new password', false);

        // Weak password is rejected with the token intact.
        $this->postForm('/reset-password', [
            'reset_token' => $raw,
            'password' => 'short',
            'confirm_password' => 'short',
        ])->assertRedirect();

        // Strong password resets + marks the token used.
        $this->postForm('/reset-password', [
            'reset_token' => $raw,
            'password' => 'NewPassw0rd!9',
            'confirm_password' => 'NewPassw0rd!9',
        ])->assertRedirect('/login')->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewPassw0rd!9', (string) DB::table('users')->where('id', $user->id)->value('password')));
        $this->assertSame(1, (int) DB::table('password_resets')->where('id', $tokenId)->value('used'));

        // The old password no longer works; the new one does.
        $this->assertFalse(Hash::check('Passw0rd!x', (string) DB::table('users')->where('id', $user->id)->value('password')));
    }

    public function test_reset_password_rejects_invalid_or_expired_token(): void
    {
        // Garbage token on GET → redirected to forgot-password with error.
        $this->get('/reset-password?token=not-a-real-token')
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('error');

        // Garbage token on POST → redirected to forgot-password.
        $this->postForm('/reset-password', [
            'reset_token' => 'not-a-real-token',
            'password' => 'NewPassw0rd!9',
            'confirm_password' => 'NewPassw0rd!9',
        ])->assertRedirect('/forgot-password')->assertSessionHas('error');

        // Expired token on GET → same handling.
        $user = $this->makeUser();
        $raw = bin2hex(random_bytes(64));
        $tokenId = DB::table('password_resets')->insertGetId([
            'user_id' => (int) $user->id,
            'token' => hash('sha256', $raw),
            'token_type' => 'password_reset',
            'expires_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->tokenIds[] = $tokenId;

        $this->get('/reset-password?token='.rawurlencode($raw))
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('error');
    }
}
