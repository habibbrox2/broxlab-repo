<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Legacy query-string admin routes (?id= form) for users, services and
 * notifications — port parity with the old bridge routes. Every endpoint
 * here exists in both path (/admin/x/{id}) and query (/admin/x?id=) form;
 * these cover the query form plus the matching path form for comparison.
 *
 * Against the real shared DB like the other suites; rows created here are
 * cleaned up in tearDown. CSRF is disabled per-call.
 */
class AdminLegacyQueryRoutesTest extends TestCase
{
    protected int $adminId = 0;

    /** @var array<int> */
    protected array $userIds = [];

    /** @var array<int> */
    protected array $serviceIds = [];

    /** @var array<int> */
    protected array $notificationIds = [];

    protected function tearDown(): void
    {
        foreach ($this->userIds as $id) {
            // A soft-deleted target user must also be purged.
            DB::table('users')->where('id', $id)->delete();
        }
        foreach ($this->serviceIds as $id) {
            DB::table('service_images')->where('service_id', $id)->delete();
            DB::table('services')->where('id', $id)->delete();
        }
        foreach ($this->notificationIds as $id) {
            DB::table('notification_logs')->where('notification_id', $id)->delete();
            DB::table('notifications')->where('id', $id)->delete();
        }
        DB::table('activity_logs')->where('user_id', $this->adminId)->delete();
        DB::table('user_roles')->where('user_id', $this->adminId)->delete();
        DB::table('users')->where('id', $this->adminId)->delete();
        Auth::logout();
        unset($_SESSION);

        parent::tearDown();
    }

    protected function makeAdmin(): void
    {
        $suffix = substr(uniqid('lqr', true), 0, 14);
        $this->adminId = DB::table('users')->insertGetId([
            'username' => 'lqr_'.$suffix,
            'email' => 'lqr_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Legacy',
            'last_name' => 'Query',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Role id 1 = admin in the shared DB (EnsureAdmin checks user_roles).
        DB::table('user_roles')->insertOrIgnore(['user_id' => $this->adminId, 'role_id' => 1, 'created_at' => now()]);

        $this->actingAs(User::query()->findOrFail($this->adminId));
    }

    protected function withoutCsrf(): static
    {
        return $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function makeUser(array $overrides = []): int
    {
        $suffix = substr(uniqid('tg', true), 0, 14);
        $id = DB::table('users')->insertGetId(array_merge([
            'username' => 'tg_'.$suffix,
            'email' => 'tg_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Target',
            'last_name' => 'User',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
        $this->userIds[] = $id;

        return $id;
    }

    protected function makeService(array $overrides = []): int
    {
        $suffix = substr(uniqid('svc', true), 0, 10);
        $id = DB::table('services')->insertGetId(array_merge([
            'name' => 'Legacy Svc '.$suffix,
            'description' => 'Created by legacy route test',
            'slug' => 'legacy-svc-'.$suffix,
            'form_fields' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
        $this->serviceIds[] = $id;

        return $id;
    }

    protected function makeNotification(array $overrides = []): int
    {
        $id = DB::table('notifications')->insertGetId(array_merge([
            'user_id' => $this->adminId,
            'title' => 'Legacy query test notification',
            'message' => 'Hello from the legacy route suite',
            'type' => 'announcement',
            'data' => json_encode(['user_id' => $this->adminId]),
            'action_url' => '',
            'status' => 'sent',
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
        $this->notificationIds[] = $id;

        return $id;
    }

    // ── Gates ──────────────────────────────────────────────────────────

    public function test_query_routes_require_auth(): void
    {
        $this->get('/admin/users/view?id=1')->assertRedirect('/login');
        $this->get('/admin/services/view?id=1')->assertRedirect('/login');
        $this->get('/admin/notifications/view?id=1')->assertRedirect('/login');
    }

    public function test_query_routes_require_admin_role(): void
    {
        $plainId = $this->makeUser();
        $this->actingAs(User::query()->findOrFail($plainId));

        $this->get('/admin/users/view?id=1')->assertRedirect('/');
        $this->get('/admin/services/view?id=1')->assertRedirect('/');
        $this->get('/admin/notifications/view?id=1')->assertRedirect('/');
    }

    // ── Users ──────────────────────────────────────────────────────────

    public function test_users_view_query_form_renders(): void
    {
        $this->makeAdmin();
        $targetId = $this->makeUser(['first_name' => 'Queryview']);

        $this->get('/admin/users/view?id='.$targetId)
            ->assertOk()
            ->assertViewIs('admin.users.view')
            ->assertSee('Queryview', false);
    }

    public function test_users_view_without_id_returns_404(): void
    {
        $this->makeAdmin();

        $this->get('/admin/users/view')->assertNotFound();
    }

    public function test_users_view_nonexistent_id_returns_404(): void
    {
        $this->makeAdmin();

        $this->get('/admin/users/view?id=99999999')->assertNotFound();
    }

    public function test_users_edit_query_form_renders(): void
    {
        $this->makeAdmin();
        $targetId = $this->makeUser(['first_name' => 'Queryedit']);

        $this->get('/admin/users/edit?id='.$targetId)
            ->assertOk()
            ->assertViewIs('admin.users.edit')
            ->assertSee('Queryedit', false);
    }

    public function test_users_edit_without_id_returns_404(): void
    {
        $this->makeAdmin();

        $this->get('/admin/users/edit')->assertNotFound();
    }

    public function test_users_update_via_query_id(): void
    {
        $this->makeAdmin();
        $targetId = $this->makeUser(['city' => 'Old City']);

        $this->withoutCsrf()
            ->post('/admin/users/edit?id='.$targetId, [
                'first_name' => 'Queried',
                'last_name' => 'Update',
                'city' => 'New City',
                'status' => 'active',
            ])
            ->assertRedirect('/admin/users/view/'.$targetId)
            ->assertSessionHas('status');

        $row = DB::table('users')->where('id', $targetId)->first();
        $this->assertSame('New City', $row->city);
        $this->assertSame('Queried', $row->first_name);
    }

    public function test_users_update_without_any_id_redirects_back_with_error(): void
    {
        $this->makeAdmin();

        $this->withoutCsrf()
            ->post('/admin/users/edit', ['first_name' => 'Nobody'])
            ->assertSessionHas('error');
    }

    public function test_users_delete_confirm_query_form_renders(): void
    {
        $this->makeAdmin();
        $targetId = $this->makeUser();

        $this->get('/admin/users/delete?id='.$targetId)
            ->assertOk()
            ->assertViewIs('admin.users.delete');
    }

    public function test_users_delete_without_id_returns_404(): void
    {
        $this->makeAdmin();

        $this->get('/admin/users/delete')->assertNotFound();
    }

    public function test_users_destroy_via_query_id_soft_deletes(): void
    {
        $this->makeAdmin();
        $targetId = $this->makeUser();

        $this->withoutCsrf()
            ->post('/admin/users/delete?id='.$targetId)
            ->assertRedirect('/admin/users')
            ->assertSessionHas('status', 'User deleted successfully');

        $row = DB::table('users')->where('id', $targetId)->first();
        $this->assertNotNull($row->deleted_at, 'User should be soft-deleted');
    }

    public function test_users_destroy_via_posted_id_soft_deletes(): void
    {
        $this->makeAdmin();
        $targetId = $this->makeUser();

        $this->withoutCsrf()
            ->post('/admin/users/delete', ['id' => $targetId])
            ->assertRedirect('/admin/users')
            ->assertSessionHas('status', 'User deleted successfully');

        $this->assertNotNull(DB::table('users')->where('id', $targetId)->value('deleted_at'));
    }

    // ── Services ───────────────────────────────────────────────────────

    public function test_services_view_query_form_renders(): void
    {
        $this->makeAdmin();
        $serviceId = $this->makeService(['name' => 'Query View Svc']);

        $this->get('/admin/services/view?id='.$serviceId)
            ->assertOk()
            ->assertViewIs('admin.services.show')
            ->assertSee('Query View Svc', false);
    }

    public function test_services_view_without_id_returns_404(): void
    {
        $this->makeAdmin();

        $this->get('/admin/services/view')->assertNotFound();
    }

    public function test_services_edit_query_form_renders(): void
    {
        $this->makeAdmin();
        $serviceId = $this->makeService(['name' => 'Query Edit Svc']);

        $this->get('/admin/services/edit?id='.$serviceId)
            ->assertOk()
            ->assertViewIs('admin.services.edit')
            ->assertSee('Query Edit Svc', false);
    }

    public function test_services_edit_without_id_returns_404(): void
    {
        $this->makeAdmin();

        $this->get('/admin/services/edit')->assertNotFound();
    }

    public function test_services_update_via_query_id(): void
    {
        $this->makeAdmin();
        $serviceId = $this->makeService(['name' => 'Before Update']);

        $this->withoutCsrf()
            ->post('/admin/services/edit?id='.$serviceId, [
                'service_title' => 'After Update',
                'service_description' => 'Updated through the legacy query route',
            ])
            ->assertRedirect('/admin/services')
            ->assertSessionHas('status', 'Service updated successfully');

        $row = DB::table('services')->where('id', $serviceId)->first();
        $this->assertSame('After Update', $row->name);
        $this->assertSame('Updated through the legacy query route', $row->description);
    }

    public function test_services_update_without_any_id_redirects_back_with_error(): void
    {
        $this->makeAdmin();

        $this->withoutCsrf()
            ->post('/admin/services/edit', [
                'service_title' => 'Orphan Update',
                'service_description' => 'No id anywhere',
            ])
            ->assertSessionHas('error');
    }

    public function test_services_delete_confirm_query_form_renders(): void
    {
        $this->makeAdmin();
        $serviceId = $this->makeService(['name' => 'Query Delete Svc']);

        $this->get('/admin/services/delete?id='.$serviceId)
            ->assertOk()
            ->assertViewIs('admin.services.delete')
            ->assertSee('Query Delete Svc', false);
    }

    public function test_services_destroy_via_query_id_soft_deletes(): void
    {
        $this->makeAdmin();
        $serviceId = $this->makeService();

        $this->withoutCsrf()
            ->post('/admin/services/delete?id='.$serviceId)
            ->assertRedirect('/admin/services')
            ->assertSessionHas('status', 'Service deleted successfully');

        $this->assertNotNull(DB::table('services')->where('id', $serviceId)->value('deleted_at'));
    }

    // ── Notifications ──────────────────────────────────────────────────

    public function test_notifications_view_query_form_renders(): void
    {
        $this->makeAdmin();
        $notifId = $this->makeNotification(['title' => 'Query View Notif']);

        $this->get('/admin/notifications/view?id='.$notifId)
            ->assertOk()
            ->assertViewIs('admin.notifications.view')
            ->assertSee('Query View Notif', false);
    }

    public function test_notifications_view_without_id_returns_404(): void
    {
        $this->makeAdmin();

        $this->get('/admin/notifications/view')->assertNotFound();
    }

    public function test_notifications_delete_confirm_query_form_renders(): void
    {
        $this->makeAdmin();
        $notifId = $this->makeNotification(['title' => 'Query Delete Notif']);

        $this->get('/admin/notifications/delete?id='.$notifId)
            ->assertOk()
            ->assertViewIs('admin.notifications.delete')
            ->assertSee('Query Delete Notif', false);
    }

    public function test_notifications_destroy_via_query_id_soft_deletes(): void
    {
        $this->makeAdmin();
        $notifId = $this->makeNotification();

        $this->withoutCsrf()
            ->post('/admin/notifications/delete?id='.$notifId)
            ->assertRedirect('/admin/notifications')
            ->assertSessionHas('status', 'Notification deleted successfully');

        $this->assertNotNull(DB::table('notifications')->where('id', $notifId)->value('deleted_at'));
    }

    public function test_notifications_destroy_without_any_id_redirects_back_with_error(): void
    {
        $this->makeAdmin();

        $this->withoutCsrf()
            ->post('/admin/notifications/delete')
            ->assertSessionHas('error');
    }

    // ── Path forms (regression: ?int $id = null signatures) ────────────

    public function test_users_view_path_form_still_renders(): void
    {
        $this->makeAdmin();
        $targetId = $this->makeUser();

        $this->get('/admin/users/view/'.$targetId)->assertOk();
    }

    public function test_users_update_path_form_still_works(): void
    {
        $this->makeAdmin();
        $targetId = $this->makeUser(['city' => 'Path Old']);

        $this->withoutCsrf()
            ->post('/admin/users/edit/'.$targetId, [
                'first_name' => 'Path',
                'last_name' => 'Update',
                'city' => 'Path New',
                'status' => 'active',
            ])
            ->assertRedirect('/admin/users/view/'.$targetId)
            ->assertSessionHas('status');

        $this->assertSame('Path New', DB::table('users')->where('id', $targetId)->value('city'));
    }

    public function test_services_view_path_form_still_renders(): void
    {
        $this->makeAdmin();
        $serviceId = $this->makeService();

        $this->get('/admin/services/view/'.$serviceId)->assertOk();
    }

    public function test_notifications_view_path_form_still_renders(): void
    {
        $this->makeAdmin();
        $notifId = $this->makeNotification();

        $this->get('/admin/notifications/view/'.$notifId)->assertOk();
    }
}
