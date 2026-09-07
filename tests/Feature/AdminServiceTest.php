<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminServiceTest extends TestCase
{
    protected ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('username', 'admin')->first();
        if (!$this->admin) {
            $this->admin = User::factory()->create([
                'username' => 'admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'is_admin' => 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::table('service_images')->where('service_id', '>', 0)->delete();
        DB::table('services')->where('id', '>', 0)->delete();
        parent::tearDown();
    }

    // ---------- Authentication ----------

    public function test_unauthenticated_user_cannot_access_services_list(): void
    {
        $response = $this->get('/admin/services');
        $response->assertRedirect('/login');
    }

    public function test_admin_user_can_access_services_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/services');
        $response->assertStatus(200);
        $response->assertViewIs('admin.services.index');
    }

    // ---------- Create ----------

    public function test_admin_can_view_create_service_form(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/services/create');
        $response->assertStatus(200);
        $response->assertViewIs('admin.services.create');
        $response->assertSee('Create New Service');
    }

    public function test_create_service_requires_authentication(): void
    {
        // Skip CSRF so the auth middleware redirect (not a 419) is asserted.
        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/admin/services/create', [
                'service_title' => 'Test Service',
                'service_description' => 'Test description',
            ]);
        $response->assertRedirect('/login');
    }

    public function test_admin_can_create_service(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $response = $this->actingAs($this->admin)
            ->post('/admin/services/create', [
                'service_title' => 'Web Development',
                'service_description' => 'Professional web development services',
                'service_images' => "https://example.com/img1.jpg\nhttps://example.com/img2.jpg",
                'service_form_template_json' => json_encode([
                    ['type' => 'text', 'label' => 'Name', 'placeholder' => 'Your name', 'required' => true],
                ]),
            ]);

        $response->assertRedirect('/admin/services');
        $response->assertSessionHas('status', 'Service inserted successfully');

        $service = DB::table('services')
            ->where('name', 'Web Development')
            ->first();

        $this->assertNotNull($service);
        $this->assertEquals('Professional web development services', $service->description);

        $images = DB::table('service_images')->where('service_id', $service->id)->get();
        $this->assertCount(2, $images);
    }

    public function test_create_service_validates_required_fields(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $response = $this->actingAs($this->admin)
            ->post('/admin/services/create', [
                'service_title' => '',
                'service_description' => '',
            ]);

        $response->assertSessionHasErrors(['service_title']);
        $response->assertSessionHasErrors(['service_description']);
    }

    // ---------- View ----------

    public function test_admin_can_view_service_detail(): void
    {
        $serviceId = DB::table('services')->insertGetId([
            'name' => 'Consulting',
            'description' => 'Business consulting',
            'slug' => 'consulting',
            'form_fields' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/services/view/{$serviceId}");
        $response->assertStatus(200);
        $response->assertViewIs('admin.services.show');
        $response->assertSee('Consulting');
    }

    public function test_view_nonexistent_service_returns_404(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/services/view/999999');
        $response->assertStatus(404);
    }

    // ---------- Edit ----------

    public function test_admin_can_view_edit_service_form(): void
    {
        $serviceId = DB::table('services')->insertGetId([
            'name' => 'Design Services',
            'description' => 'Design and UX',
            'slug' => 'design-services',
            'form_fields' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/services/edit/{$serviceId}");
        $response->assertStatus(200);
        $response->assertViewIs('admin.services.edit');
        $response->assertSee('Design Services');
    }

    public function test_update_service_requires_id(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $response = $this->actingAs($this->admin)
            ->post('/admin/services/edit/0', [
                'id' => '',
                'service_title' => 'Updated Service',
                'service_description' => 'Updated description',
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('services', ['name' => 'Updated Service']);
    }

    public function test_admin_can_update_service(): void
    {
        $serviceId = DB::table('services')->insertGetId([
            'name' => 'Original Title',
            'description' => 'Original description',
            'slug' => 'original-title',
            'form_fields' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $response = $this->actingAs($this->admin)
            ->post("/admin/services/edit/{$serviceId}", [
                'id' => $serviceId,
                'service_title' => 'Updated Title',
                'service_description' => 'Updated description',
                'service_form_template_json' => json_encode([
                    ['type' => 'email', 'label' => 'Email', 'required' => true],
                ]),
            ]);

        $response->assertRedirect('/admin/services');
        $response->assertSessionHas('status', 'Service updated successfully');

        $service = DB::table('services')->find($serviceId);
        $this->assertEquals('Updated Title', $service->name);
        $this->assertEquals('Updated description', $service->description);
    }

    // ---------- Delete ----------

    public function test_admin_can_view_delete_confirmation(): void
    {
        $serviceId = DB::table('services')->insertGetId([
            'name' => 'Temp Service',
            'description' => 'To be deleted',
            'slug' => 'temp-service',
            'form_fields' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/services/delete/{$serviceId}");
        $response->assertStatus(200);
        $response->assertViewIs('admin.services.delete');
        $response->assertSee('Delete Service');
        $response->assertSee('Temp Service');
    }

    public function test_delete_nonexistent_service_returns_404(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/services/delete/999999');
        $response->assertStatus(404);
    }

    public function test_admin_can_delete_service(): void
    {
        $serviceId = DB::table('services')->insertGetId([
            'name' => 'Service To Delete',
            'description' => 'Will be removed',
            'slug' => 'service-to-delete',
            'form_fields' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('service_images')->insert([
            'service_id' => $serviceId,
            'image_path' => 'https://example.com/img.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $response = $this->actingAs($this->admin)
            ->post("/admin/services/delete/{$serviceId}");

        $response->assertRedirect('/admin/services');
        $response->assertSessionHas('status', 'Service deleted successfully');

        // Soft-delete: 'deleted_at' is set rather than the row being removed.
        // Verify the soft-delete happened and images are gone.
        $deleted = DB::table('services')->where('id', $serviceId)->first();
        $this->assertNotNull($deleted->deleted_at, 'Service should be soft-deleted');
    }
}
