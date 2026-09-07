<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 5 admin layout + dashboard: gate behaviour (guest, non-admin,
 * admin), dashboard rendering with real shared-DB stats, and the
 * sidebar-counts JSON endpoint.
 */
class AdminDashboardTest extends TestCase
{
    use WithFaker;

    protected int $userId = 0;

    protected bool $isAdmin = false;

    protected function tearDown(): void
    {
        if ($this->userId) {
            DB::table('user_roles')->where('user_id', $this->userId)->delete();
            DB::table('activity_logs')->where('user_id', $this->userId)->delete();
            DB::table('users')->where('id', $this->userId)->delete();
        }
        Auth::logout();
        unset($_SESSION);

        parent::tearDown();
    }

    protected function makeAdmin(): void
    {
        $suffix = substr(uniqid('adm', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'adm_'.$suffix,
            'email' => 'adm_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Role id 1 = admin in the shared DB (user role is 4)
        DB::table('user_roles')->insert(['user_id' => $this->userId, 'role_id' => 1, 'created_at' => now()]);
        $this->isAdmin = true;

        $user = User::query()->find($this->userId);
        $this->actingAs($user);
    }

    // ── Gates ──────────────────────────────────────────────────────────

    public function test_dashboard_redirects_guests_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_redirects_regular_users_home(): void
    {
        $suffix = substr(uniqid('usr', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'usr_'.$suffix,
            'email' => 'usr_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs(User::query()->find($this->userId));

        $this->get('/admin/dashboard')->assertRedirect('/')->assertSessionHas('error');
    }

    public function test_sidebar_counts_401_for_guests(): void
    {
        $this->getJson('/api/admin/sidebar-counts')->assertStatus(401);
    }

    public function test_sidebar_counts_403_for_non_admins(): void
    {
        $suffix = substr(uniqid('usr', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'usr_'.$suffix,
            'email' => 'usr_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs(User::query()->find($this->userId));

        $this->getJson('/api/admin/sidebar-counts')->assertStatus(403);
    }

    // ── Dashboard rendering ────────────────────────────────────────────

    public function test_admin_redirects_to_dashboard(): void
    {
        $this->makeAdmin();

        $this->get('/admin')->assertRedirect('/admin/dashboard');
    }

    public function test_dashboard_renders_for_admin(): void
    {
        $this->makeAdmin();

        $response = $this->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('Welcome back, Admin Test', false);
        $response->assertSee('Total Posts', false);
        $response->assertSee('Service Application', false);
        $response->assertSee('Recent Posts', false);
        $response->assertSee('Recent Comments', false);
        $response->assertSee('Your Access Level', false);
    }

    public function test_dashboard_renders_for_super_admin(): void
    {
        $this->makeAdmin();
        // Add a super-admin-flagged role (isSuperAdmin checks roles.is_super_admin = 1;
        // ignore when it is the same admin role already assigned)
        $superRoleId = DB::table('roles')->where('is_super_admin', 1)->value('id');
        if ($superRoleId) {
            DB::table('user_roles')->insertOrIgnore(['user_id' => $this->userId, 'role_id' => $superRoleId, 'created_at' => now()]);
        }

        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_sidebar_counts_json_shape(): void
    {
        $this->makeAdmin();

        $response = $this->getJson('/api/admin/sidebar-counts');

        $response->assertOk()->assertJson(['success' => true]);
        $counts = $response->json('counts');
        $this->assertIsInt($counts['applications']);
        $this->assertIsInt($counts['posts']);
        $this->assertIsInt($counts['comments']);
        $this->assertIsInt($counts['contact']);
    }

    public function test_dashboard_stats_match_shared_db(): void
    {
        $this->makeAdmin();

        $response = $this->get('/admin/dashboard');

        $response->assertOk();
        $expectedPosts = (int) DB::table('posts')->where('published', 1)->count();
        $this->seeInRenderedStats($response, $expectedPosts);
    }

    protected function seeInRenderedStats($response, int $value): void
    {
        $html = $response->getContent();
        // The total posts figure appears in the stat card
        $this->assertStringContainsString('>'.$value.'<', $html);
    }
}
