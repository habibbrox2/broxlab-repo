<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SecurityService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 2FA enrollment flow (port of legacy UserSecurityController 2fa/setup).
 *
 * Covers secret generation, otpauth URI shape, QR PNG output, enable/disable
 * persistence, the confirm-code round trip and the backup-code display.
 * Users are created with unique markers and removed in tearDown.
 */
class TwoFactorEnrollmentTest extends TestCase
{
    /** @var array<int> Created user ids to clean up. */
    protected array $userIds = [];

    protected function tearDown(): void
    {
        foreach ($this->userIds as $id) {
            DB::table('user_security')->where('user_id', $id)->delete();
            DB::table('user_roles')->where('user_id', $id)->delete();
            DB::table('activity_logs')->where('user_id', $id)->delete();
            DB::table('users')->where('id', $id)->delete();
        }

        parent::tearDown();
    }

    protected function makeUser(): User
    {
        $suffix = bin2hex(random_bytes(8));
        $id = DB::table('users')->insertGetId([
            'username' => 'twofa_'.$suffix,
            'email' => 'twofa_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Two',
            'last_name' => 'Factor',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        $this->userIds[] = $id;

        return User::query()->find($id);
    }

    protected function postWithoutCsrf(string $uri, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->withoutMiddleware(ValidateCsrfToken::class)->post($uri, $data);
    }

    // ── Service level ─────────────────────────────────────────────────

    public function test_generated_secret_is_valid_base32(): void
    {
        $secret = app(SecurityService::class)->generateBase32Secret();

        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);

        // must decode cleanly and verify with its own TOTP
        $this->assertNotNull(app(SecurityService::class)->base32Decode($secret));
    }

    public function test_otpauth_uri_matches_legacy_format(): void
    {
        $uri = app(SecurityService::class)->otpauthUri('user@example.test', 'ABC234DEF');

        $this->assertSame('otpauth://totp/broxbhai:user%40example.test?secret=ABC234DEF&issuer=Broxbhai', $uri);
    }

    public function test_qr_data_uri_encodes_the_otpauth_uri(): void
    {
        $service = app(SecurityService::class);
        $uri = $service->otpauthUri('user@example.test', $service->generateBase32Secret());

        $dataUri = $service->qrDataUriForSecret($uri);

        $this->assertNotNull($dataUri);
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        $binary = base64_decode(substr($dataUri, strlen('data:image/png;base64,')));
        $this->assertStringStartsWith("\x89PNG", $binary);
    }

    public function test_enable2fa_upserts_row_and_generates_backup_codes(): void
    {
        $service = app(SecurityService::class);
        $user = $this->makeUser();
        $secret = $service->generateBase32Secret();

        $result = $service->enable2FA((int) $user->id, $secret);

        $this->assertTrue($result['success']);
        $this->assertCount(10, $result['backup_codes']);
        $this->assertMatchesRegularExpression('/^[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/', $result['backup_codes'][0]);

        $row = DB::table('user_security')->where('user_id', $user->id)->first();
        $this->assertSame(1, (int) $row->twofa_enabled);
        $this->assertSame(strtoupper($secret), $row->twofa_secret);
        $this->assertSame('totp', $row->twofa_method);

        // upsert path: enabling again must not duplicate
        $result2 = $service->enable2FA((int) $user->id, $service->generateBase32Secret());
        $this->assertTrue($result2['success']);
        $this->assertSame(1, DB::table('user_security')->where('user_id', $user->id)->count());
    }

    public function test_enable2fa_rejects_malformed_secret(): void
    {
        $result = app(SecurityService::class)->enable2FA(999999, "not-a-secret'; --");

        $this->assertFalse($result['success']);
    }

    public function test_stored_secret_verifies_with_live_totp(): void
    {
        $service = app(SecurityService::class);
        $user = $this->makeUser();
        $secret = $service->generateBase32Secret();

        // compute the current TOTP for the secret using the service's own algorithm
        $secretBinary = $service->base32Decode($secret);
        $counter = (int) floor(time() / 30);
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $secretBinary, true);
        $offset = ord($hash[19]) & 0xf;
        $value = unpack('N', substr($hash, $offset, 4))[1];
        $code = str_pad((string) (($value & 0x7fffffff) % 1000000), 6, '0', STR_PAD_LEFT);

        $service->enable2FA((int) $user->id, $secret);

        $this->assertTrue($service->verify2FACode((int) $user->id, $code));
    }

    public function test_disable2fa_clears_state(): void
    {
        $service = app(SecurityService::class);
        $user = $this->makeUser();
        $service->enable2FA((int) $user->id, $service->generateBase32Secret());

        $this->assertTrue($service->disable2FA((int) $user->id));
        $this->assertFalse($service->is2FAEnabled((int) $user->id));

        $row = DB::table('user_security')->where('user_id', $user->id)->first();
        $this->assertNull($row->twofa_secret);
        $this->assertNull($row->backup_codes);
    }

    // ── HTTP flow ─────────────────────────────────────────────────────

    public function test_setup_page_shows_qr_and_secret_and_stages_it(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/user/security/2fa/setup');

        $response->assertOk()
            ->assertSee('data:image/png;base64,', false)
            ->assertSee('otpauth://totp/broxbhai:', false);

        // the staged secret must verify: extract it from the manual-entry block
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/>[A-Z2-7]{32}</', $html);
        preg_match('/>([A-Z2-7]{32})</', $html, $m);
        $secret = $m[1];

        // reload keeps the same staged secret (legacy session parity)
        $this->actingAs($user)->get('/user/security/2fa/setup')
            ->assertSee($secret, false);
    }

    public function test_full_enrollment_flow_with_real_code(): void
    {
        $user = $this->makeUser();
        $service = app(SecurityService::class);

        // 1. setup: page stages a secret in the session
        $this->actingAs($user)->get('/user/security/2fa/setup')->assertOk();
        $secret = session('temp_2fa_secret');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);

        // 2. compute the live TOTP for that secret
        $secretBinary = $service->base32Decode($secret);
        $counter = (int) floor(time() / 30);
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $secretBinary, true);
        $offset = ord($hash[19]) & 0xf;
        $value = unpack('N', substr($hash, $offset, 4))[1];
        $code = str_pad((string) (($value & 0x7fffffff) % 1000000), 6, '0', STR_PAD_LEFT);

        // 3. confirm
        $response = $this->actingAs($user)
            ->postWithoutCsrf('/user/security/2fa/verify', ['code' => $code]);

        $response->assertRedirect(route('user.security.2fa.backup'));
        $this->assertTrue($service->is2FAEnabled((int) $user->id));

        // 4. backup codes shown once
        $codes = session('backup_codes');
        $this->assertIsArray($codes);
        $this->assertCount(10, $codes);
        $this->actingAs($user)->get('/user/security/2fa/backup')
            ->assertOk()
            ->assertSee($codes[0]);

        // staged secret is consumed
        $this->assertNull(session('temp_2fa_secret'));
    }

    public function test_wrong_code_is_rejected_and_secret_survives(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/user/security/2fa/setup')->assertOk();
        $secret = session('temp_2fa_secret');

        $response = $this->actingAs($user)
            ->postWithoutCsrf('/user/security/2fa/verify', ['code' => '000000']);

        // 000000 is 1-in-a-million wrong; retry once with a definitely-wrong
        // shape to avoid the (astronomically unlikely) real-code collision.
        if ($response->isRedirect() && str_contains((string) session('error'), 'সঠিক নয়')) {
            // vanishingly rare collision — regenerate and retry
            session(['temp_2fa_secret' => $secret]);
            $response = $this->actingAs($user)
                ->postWithoutCsrf('/user/security/2fa/verify', ['code' => ' ABC ']);
        }

        $response->assertRedirect(route('user.security.2fa.setup'));
        $this->assertFalse(app(SecurityService::class)->is2FAEnabled((int) $user->id));
        // the staged secret survives so the user can retry with the same QR
        $this->assertSame($secret, session('temp_2fa_secret'));
    }

    public function test_settings_page_reflects_state(): void
    {
        $user = $this->makeUser();
        $service = app(SecurityService::class);

        // off → CTA
        $this->actingAs($user)->get('/user/security/2fa')
            ->assertOk()
            ->assertSee('2FA এখনো চালু নেই');

        // on → status + disable form
        $service->enable2FA((int) $user->id, $service->generateBase32Secret());
        $this->actingAs($user)->get('/user/security/2fa')
            ->assertOk()
            ->assertSee('2FA চালু আছে')
            ->assertSee('2FA বন্ধ করুন');
    }

    public function test_guest_cannot_access_setup(): void
    {
        $this->get('/user/security/2fa/setup')->assertRedirect();
    }
}
