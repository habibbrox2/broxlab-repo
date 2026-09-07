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
 * Phase 3 user-area flows over the shared legacy session: dashboard,
 * profile view/edit, password change, settings and the notifications inbox
 * (incl. the migrated mark-read endpoints). CSRF is disabled per-call like
 * the other suites; created rows are cleaned up in tearDown.
 */
class UserAreaTest extends TestCase
{
    use WithFaker;

    protected int $userId = 0;

    protected array $notificationIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = substr(uniqid('area', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'area_'.$suffix,
            'email' => 'area_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Area',
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
        foreach ($this->notificationIds as $id) {
            DB::table('notification_logs')->where('notification_id', $id)->delete();
            DB::table('notifications')->where('id', $id)->delete();
        }
        if ($this->userId) {
            DB::table('user_roles')->where('user_id', $this->userId)->delete();
            DB::table('activity_logs')->where('user_id', $this->userId)->delete();
            DB::table('users')->where('id', $this->userId)->delete();
        }
        Auth::logout();
        unset($_SESSION);

        parent::tearDown();
    }

    protected function actingAsUser(): User
    {
        $user = User::query()->find($this->userId);
        $this->assertNotNull($user, 'Test user missing');
        $this->actingAs($user);

        return $user;
    }

    // ── Dashboard ──────────────────────────────────────────────────────

    public function test_dashboard_requires_auth(): void
    {
        $this->get('/user/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_renders_for_regular_user(): void
    {
        $this->actingAsUser();

        $response = $this->get('/user/dashboard');

        $response->assertOk();
        $response->assertSee('Hello, Area Test', false);
        $response->assertSee('Profile Completeness', false);
        $response->assertSee('Recent Activity', false);
        $response->assertSee('Notices', false);
    }

    public function test_dashboard_redirects_admins_to_admin_dashboard(): void
    {
        $this->actingAsUser();
        // Legacy parity: user_dashboard_only middleware blocks admins.
        DB::table('user_roles')->insert(['user_id' => $this->userId, 'role_id' => 2, 'created_at' => now()]);

        $this->get('/user/dashboard')->assertRedirect('/admin/dashboard');
    }

    // ── Profile ────────────────────────────────────────────────────────

    public function test_profile_show_renders(): void
    {
        $this->actingAsUser();

        $this->get('/profile')
            ->assertOk()
            ->assertSee('Profile Details', false)
            ->assertSee('area_', false);
    }

    public function test_profile_edit_renders_form(): void
    {
        $this->actingAsUser();

        $this->get('/profile/edit')
            ->assertOk()
            ->assertSee('Update Your Information', false)
            ->assertSee('name="username"', false);
    }

    public function test_profile_update_changes_fields(): void
    {
        $this->actingAsUser();

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/profile/edit', [
                'username' => 'area_user_'.substr(md5(uniqid('', true)), 0, 8),
                'email' => 'area_'.substr(md5(uniqid('', true)), 0, 8).'@example.test',
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'phone' => '01700000000',
                'city' => 'Dhaka',
            ]);

        $response->assertRedirect('/profile/edit')->assertSessionHas('status');

        $row = DB::table('users')->where('id', $this->userId)->first();
        $this->assertSame('Updated', $row->first_name);
        $this->assertSame('Dhaka', $row->city);

        // Cleanup the profile-update notification (+ delivery log)
        $notifId = DB::table('notifications')->where('user_id', $this->userId)->where('type', 'update')->value('id');
        if ($notifId) {
            DB::table('notification_logs')->where('notification_id', $notifId)->delete();
            DB::table('notifications')->where('id', $notifId)->delete();
        }
    }

    public function test_profile_update_rejects_reserved_username(): void
    {
        $this->actingAsUser();

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/profile/edit', [
                'username' => 'admin',
                'email' => DB::table('users')->where('id', $this->userId)->value('email'),
            ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_profile_update_rejects_duplicate_username(): void
    {
        $this->actingAsUser();
        $otherId = DB::table('users')->insertGetId([
            'username' => 'taken_'.substr(md5(uniqid('', true)), 0, 10),
            'email' => 'taken_'.substr(md5(uniqid('', true)), 0, 10).'@example.test',
            'password' => 'x',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $response = $this->withoutMiddleware(ValidateCsrfToken::class)
                ->post('/profile/edit', [
                    'username' => DB::table('users')->where('id', $otherId)->value('username'),
                    'email' => DB::table('users')->where('id', $this->userId)->value('email'),
                ]);

            $response->assertSessionHasErrors('username');
        } finally {
            DB::table('users')->where('id', $otherId)->delete();
        }
    }

    public function test_password_change_flow(): void
    {
        $this->actingAsUser();

        // Wrong current password
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/profile/password', [
                'current_password' => 'WrongPass1!',
                'new_password' => 'NewPass1!x',
                'new_password_confirmation' => 'NewPass1!x',
            ])
            ->assertSessionHasErrors('current_password');

        // Missing complexity
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/profile/password', [
                'current_password' => 'Passw0rd!x',
                'new_password' => 'onlylowercase1',
                'new_password_confirmation' => 'onlylowercase1',
            ])
            ->assertSessionHasErrors('new_password');

        // Success
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/profile/password', [
                'current_password' => 'Passw0rd!x',
                'new_password' => 'NewPass1!x',
                'new_password_confirmation' => 'NewPass1!x',
            ])
            ->assertRedirect('/profile/password')
            ->assertSessionHas('status');

        $row = DB::table('users')->where('id', $this->userId)->first();
        $this->assertTrue(Hash::check('NewPass1!x', $row->password));
        $this->assertNotNull($row->password_changed_at);
    }

    // ── Settings ───────────────────────────────────────────────────────

    public function test_settings_renders(): void
    {
        $this->actingAsUser();

        $this->get('/user/settings')
            ->assertOk()
            ->assertSee('Account Settings', false)
            ->assertSee('Linked accounts', false);
    }

    // ── Notifications ──────────────────────────────────────────────────

    protected function makeNotification(array $overrides = []): int
    {
        $id = DB::table('notifications')->insertGetId(array_merge([
            'user_id' => $this->userId,
            'title' => 'Test notification',
            'message' => 'Hello from the test suite',
            'type' => 'announcement',
            'data' => json_encode(['user_id' => $this->userId]),
            'action_url' => '',
            'status' => 'sent',
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
        $this->notificationIds[] = $id;

        return $id;
    }

    public function test_notifications_inbox_renders_with_unread(): void
    {
        $this->actingAsUser();
        $id = $this->makeNotification();

        $response = $this->get('/user/notifications');

        $response->assertOk();
        $response->assertSee('Test notification', false);
        $response->assertSee('data-notification-id="'.$id.'"', false);
    }

    public function test_notifications_empty_state(): void
    {
        $this->actingAsUser();

        $this->get('/user/notifications')
            ->assertOk()
            ->assertSee('কোনো নোটিফিকেশন নেই', false);
    }

    public function test_mark_one_read(): void
    {
        $this->actingAsUser();
        $id = $this->makeNotification();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/notification/mark-read', ['notification_id' => $id])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, (int) DB::table('notifications')->where('id', $id)->value('is_read'));
    }

    public function test_mark_read_rejects_foreign_notification(): void
    {
        $this->actingAsUser();
        // Belongs to someone else — must not be markable
        $foreignId = DB::table('notifications')->insertGetId([
            'user_id' => $this->userId + 1000000,
            'title' => 'Not yours',
            'message' => 'x',
            'type' => 'announcement',
            'status' => 'sent',
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->notificationIds[] = $foreignId;

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/notification/mark-read', ['notification_id' => $foreignId])
            ->assertOk()
            ->assertJson(['success' => false]);

        $this->assertSame(0, (int) DB::table('notifications')->where('id', $foreignId)->value('is_read'));
    }

    public function test_mark_all_read(): void
    {
        $this->actingAsUser();
        $a = $this->makeNotification(['title' => 'A']);
        $b = $this->makeNotification(['title' => 'B']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/notification/mark-all-read')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, (int) DB::table('notifications')->where('id', $a)->value('is_read'));
        $this->assertSame(1, (int) DB::table('notifications')->where('id', $b)->value('is_read'));
    }

    public function test_mark_endpoints_require_auth(): void
    {
        // JSON requests get 401 from the auth middleware (not a redirect)
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/notification/mark-read', ['notification_id' => 1])
            ->assertStatus(401);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/notification/mark-all-read')
            ->assertStatus(401);
    }
}
