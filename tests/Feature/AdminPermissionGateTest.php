<?php

namespace Tests\Feature;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 9 core RBAC: the `perm:` middleware on the admin routes.
 *
 * - super_admin (roles.is_super_admin = 1) sees and does everything;
 * - a scoped role holding only RBAC slugs sees RBAC, gets 403 elsewhere;
 * - the sidebar hides items the user lacks;
 * - role changes made after the permission cache is warmed are picked up.
 */
class AdminPermissionGateTest extends TestCase
{
    protected int $userId = 0;

    protected int $roleId = 0;

    protected function tearDown(): void
    {
        if ($this->userId) {
            DB::table('user_roles')->where('user_id', $this->userId)->delete();
            DB::table('activity_logs')->where('user_id', $this->userId)->delete();
            DB::table('users')->where('id', $this->userId)->delete();
        }
        if ($this->roleId) {
            DB::table('role_permissions')->where('role_id', $this->roleId)->delete();
            DB::table('roles')->where('id', $this->roleId)->delete();
        }
        Auth::logout();
        unset($_SESSION);

        parent::tearDown();
    }

    /** Create a user, and optionally a throw-away role holding exactly $slugs. */
    protected function makeUser(array $slugs = []): void
    {
        $suffix = substr(uniqid('rbac', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'rbac_'.$suffix,
            'email' => 'rbac_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Rbac',
            'last_name' => 'Test',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($slugs !== []) {
            $this->roleId = DB::table('roles')->insertGetId([
                'name' => 'rbac_test_'.$suffix,
                'ranking' => 99,
                'is_super_admin' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $permIds = DB::table('permissions')->whereIn('name', $slugs)->pluck('id');
            foreach ($permIds as $pid) {
                DB::table('role_permissions')->insert([
                    'role_id' => $this->roleId, 'permission_id' => $pid, 'created_at' => now(),
                ]);
            }
            DB::table('user_roles')->insert(['user_id' => $this->userId, 'role_id' => $this->roleId, 'created_at' => now()]);
        }

        $this->actingAs(\App\Models\User::query()->find($this->userId));
    }

    public function test_super_admin_sees_everything(): void
    {
        // Role 1 carries is_super_admin = 1 in the shared DB.
        $this->makeUser([]);
        DB::table('user_roles')->insert(['user_id' => $this->userId, 'role_id' => 1, 'created_at' => now()]);
        AdminPermissions::flush();
        $this->actingAs(\App\Models\User::query()->find($this->userId));

        foreach (['/admin/roles', '/admin/permissions', '/admin/posts', '/admin/users'] as $url) {
            $this->get($url)->assertOk();
        }

        // A mutation route whose slug does not even exist in permissions —
        // super_admin bypasses the fail-closed check.
        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/navigation', ['items' => '[]']);
        $this->assertNotEquals(403, $response->status());
    }

    public function test_scoped_role_with_only_rbac_permissions_is_forbidden_elsewhere(): void
    {
        // role.create (a write slug) is what makes this custom role an
        // admin-chrome user at all; list/view slugs alone do not.
        $this->makeUser(['role.list', 'role.view', 'role.create', 'permission.list']);

        // RBAC pages they hold role.list / permission.list for → OK.
        $this->get('/admin/roles')->assertOk();
        $this->get('/admin/permissions')->assertOk();

        // Modules outside their grants → 403.
        foreach (['/admin/revenue/sponsored/create', '/admin/notifications/create', '/admin/security/auth'] as $url) {
            $this->get($url)->assertForbidden();
        }

        // Mutations they lack (nav.* doesn't even exist → fail-closed) → 403.
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/navigation', ['items' => '[]'])
            ->assertForbidden();
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/wallet/recharges/999999/approve', [])
            ->assertForbidden();
    }

    public function test_scoped_role_403s_are_audited(): void
    {
        $this->makeUser(['role.list', 'role.create']);

        $this->get('/admin/security/auth')->assertForbidden();

        $this->assertGreaterThan(0, DB::table('activity_logs')
            ->where('user_id', $this->userId)
            ->where('action', 'permission_denied')
            ->count());
    }

    public function test_sidebar_hides_unpermitted_items_from_scoped_role(): void
    {
        $this->makeUser(['role.list', 'role.create', 'permission.list', 'dashboard.admin']);

        $html = $this->get('/admin/dashboard')->assertOk()->getContent();

        // Granted: Roles + Permissions sidebar entries present.
        $this->assertStringContainsString('/admin/roles', $html);
        $this->assertStringContainsString('/admin/permissions', $html);

        // Not granted (no setting.* on the temp role): Security section hidden.
        $this->assertStringNotContainsString('/admin/security/auth', $html);
    }

    public function test_permission_cache_picks_up_role_changes(): void
    {
        $this->makeUser([]); // no roles at all → nothing granted

        AdminPermissions::allows('setting.edit'); // warm the cache
        $this->assertFalse(AdminPermissions::allows('setting.edit'));

        // Grant through a fresh role (as the RBAC UI would).
        $permId = DB::table('permissions')->where('name', 'setting.edit')->value('id');
        $this->roleId = DB::table('roles')->insertGetId([
            'name' => 'rbac_grant_'.substr(uniqid('', true), 0, 10),
            'ranking' => 99,
            'is_super_admin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insertOrIgnore([
            'role_id' => $this->roleId, 'permission_id' => $permId, 'created_at' => now(),
        ]);
        DB::table('user_roles')->insert(['user_id' => $this->userId, 'role_id' => $this->roleId, 'created_at' => now()]);

        // The RBAC UI flushes on every mutation; the >1s staleness TTL is the
        // safety net for processes that mutate roles directly.
        AdminPermissions::flush();

        $this->assertTrue(AdminPermissions::allows('setting.edit'));
    }
}
