<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Per-user admin sidebar width preference endpoints.
 *
 * Uses direct DB inserts (like the existing admin smoke patterns in this
 * repo) because the shared users table has non-standard required columns.
 */
class AdminSidebarWidthTest extends TestCase
{
    protected int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = substr(uniqid('sidey', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'sidey_' . $suffix,
            'email' => 'sidey_' . $suffix . '@example.test',
            'password' => bcrypt('Passw0rd!x'),
            'first_name' => 'Sidey',
            'last_name' => 'Bar',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->insert(['user_id' => $this->userId, 'role_id' => 1, 'created_at' => now()]);
    }

    protected function tearDown(): void
    {
        DB::table('user_roles')->where('user_id', $this->userId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();

        parent::tearDown();
    }

    protected function actingAsAdmin(): static
    {
        // Eloquent model (Authenticatable) — be() rejects stdClass rows.
        $this->be(\App\Models\User::query()->findOrFail($this->userId));

        return $this;
    }

    public function test_guest_gets_unauthenticated(): void
    {
        $this->getJson('/admin/api/sidebar-width')->assertStatus(401);
        $this->putJson('/admin/api/sidebar-width', ['width' => 300])->assertStatus(401);
    }

    public function test_defaults_to_220_when_never_set(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/api/sidebar-width')
            ->assertOk()
            ->assertJson(['width' => 220]);
    }

    public function test_update_persists_width_and_get_roundtrips(): void
    {
        $this->actingAsAdmin()
            ->putJson('/admin/api/sidebar-width', ['width' => 360])
            ->assertOk()
            ->assertJson(['width' => 360]);

        $this->assertDatabaseHas('users', ['id' => $this->userId, 'admin_sidebar_width' => 360]);

        $this->actingAsAdmin()
            ->getJson('/admin/api/sidebar-width')
            ->assertOk()
            ->assertJson(['width' => 360]);
    }

    public function test_validation_rejects_out_of_bounds_widths(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/admin/api/sidebar-width', ['width' => 100])->assertStatus(422);
        $this->putJson('/admin/api/sidebar-width', ['width' => 900])->assertStatus(422);
        $this->putJson('/admin/api/sidebar-width', ['width' => 'wide'])->assertStatus(422);

        // Nothing was written
        $this->assertDatabaseHas('users', ['id' => $this->userId, 'admin_sidebar_width' => null]);
    }

    public function test_model_helper_clamps_legacy_garbage(): void
    {
        DB::table('users')->where('id', $this->userId)->update(['admin_sidebar_width' => 5000]);
        $user = DB::table('users')->find($this->userId);

        $this->assertSame(220, (new \App\Models\User)->forceFill((array) $user)->sidebarWidth());
    }
}
