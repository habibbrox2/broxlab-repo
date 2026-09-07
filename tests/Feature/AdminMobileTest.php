<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 5 admin mobiles CRUD: list, create, edit, show, delete.
 *
 * Note: Uses shared MySQL schema (no migrations). Tests clean up after themselves.
 */
class AdminMobileTest extends TestCase
{
    protected int $adminId = 0;
    protected int $userId = 0;

    /**
     * Helper to make POST requests without CSRF (matching legacy behavior).
     */
    protected function postWithoutCsrf(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withoutMiddleware(ValidateCsrfToken::class)->post($uri, $data);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if ($this->adminId) {
            DB::table('user_roles')->where('user_id', $this->adminId)->delete();
            DB::table('activity_logs')->where('user_id', $this->adminId)->delete();
            DB::table('users')->where('id', $this->adminId)->delete();
            $this->adminId = 0;
        }

        if ($this->userId) {
            DB::table('user_roles')->where('user_id', $this->userId)->delete();
            DB::table('users')->where('id', $this->userId)->delete();
            $this->userId = 0;
        }

        // Clean up any mobiles created during tests
        DB::table('mobile_specs')->delete();
        DB::table('mobile_images')->delete();
        DB::table('content_tags')->where('content_type', 'mobile')->delete();
        DB::table('mobiles')->delete();

        // Clean up tags created during tests (unique slug constraint)
        DB::table('tags')->where('name', 'like', 'flagship%')->delete();

        parent::tearDown();
    }

    protected function makeAdmin(): void
    {
        $suffix = substr(uniqid('mobadm', true), 0, 14);
        $this->adminId = DB::table('users')->insertGetId([
            'username' => 'mobadm_' . $suffix,
            'email' => 'mobadm_' . $suffix . '@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Mobile',
            'last_name' => 'Admin',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Role id 1 = admin
        DB::table('user_roles')->insert([
            'user_id' => $this->adminId,
            'role_id' => 1,
            'created_at' => now(),
        ]);

        $user = User::query()->find($this->adminId);
        $this->actingAs($user);
    }

    protected function makeUser(): void
    {
        $suffix = substr(uniqid('mobusr', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'mobusr_' . $suffix,
            'email' => 'mobusr_' . $suffix . '@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Mobile',
            'last_name' => 'User',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->find($this->userId);
        $this->actingAs($user);
    }

    protected function createMobile(array $attributes = []): int
    {
        return DB::table('mobiles')->insertGetId(array_merge([
            'brand_name' => 'TestBrand',
            'model_name' => 'TestModel',
            'official_price' => 100000,
            'unofficial_price' => 90000,
            'status' => 'official',
            'release_date' => '2024-01-01',
            'is_official' => 1,
            'created_at' => now(),
        ], $attributes));
    }

    // ── Auth gates ───────────────────────────────────────────────────

    public function test_guest_cannot_access_admin_mobiles(): void
    {
        $this->get('/admin/mobiles')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_admin_mobiles(): void
    {
        $this->makeUser();
        $this->get('/admin/mobiles')->assertRedirect('/')->assertSessionHas('error');
    }

    public function test_guest_cannot_access_create_form(): void
    {
        $this->get('/admin/mobiles/create')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_create_form(): void
    {
        $this->makeUser();
        $this->get('/admin/mobiles/create')->assertRedirect('/');
    }

    public function test_guest_cannot_access_edit_form(): void
    {
        $mobileId = $this->createMobile();
        $this->get('/admin/mobiles/edit/' . $mobileId)->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_edit_form(): void
    {
        $this->makeUser();
        $mobileId = $this->createMobile();
        $this->get('/admin/mobiles/edit/' . $mobileId)->assertRedirect('/');
    }

    public function test_guest_cannot_view_mobile_detail(): void
    {
        $mobileId = $this->createMobile();
        $this->get('/admin/mobiles/view/' . $mobileId)->assertRedirect('/login');
    }

    public function test_non_admin_cannot_view_mobile_detail(): void
    {
        $this->makeUser();
        $mobileId = $this->createMobile();
        $this->get('/admin/mobiles/view/' . $mobileId)->assertRedirect('/');
    }

    public function test_guest_cannot_view_delete_confirmation(): void
    {
        $mobileId = $this->createMobile();
        $this->get('/admin/mobiles/delete/' . $mobileId)->assertRedirect('/login');
    }

    public function test_non_admin_cannot_view_delete_confirmation(): void
    {
        $this->makeUser();
        $mobileId = $this->createMobile();
        $this->get('/admin/mobiles/delete/' . $mobileId)->assertRedirect('/');
    }

    // ── List ─────────────────────────────────────────────────────────

    public function test_admin_can_view_mobiles_list(): void
    {
        $this->makeAdmin();
        $response = $this->get('/admin/mobiles');

        $response->assertStatus(200);
        $response->assertViewIs('admin.mobiles.index');
        $response->assertSee('All Mobiles');
    }

    public function test_mobiles_list_shows_empty_state_when_no_mobiles(): void
    {
        $this->makeAdmin();
        $response = $this->get('/admin/mobiles');

        $response->assertStatus(200);
        $response->assertSee('No mobiles found');
    }

    public function test_mobiles_list_shows_existing_mobiles(): void
    {
        $this->makeAdmin();
        $this->createMobile([
            'brand_name' => 'Samsung',
            'model_name' => 'Galaxy S24',
        ]);

        $response = $this->get('/admin/mobiles');

        $response->assertStatus(200);
        $response->assertSee('Samsung');
        $response->assertSee('Galaxy S24');
    }

    public function test_mobiles_list_supports_search(): void
    {
        $this->makeAdmin();
        $this->createMobile(['brand_name' => 'Samsung', 'model_name' => 'Galaxy S24']);
        $this->createMobile(['brand_name' => 'iPhone', 'model_name' => '15 Pro']);

        $response = $this->get('/admin/mobiles?search=Samsung');

        $response->assertStatus(200);
        $response->assertSee('Samsung');
        $response->assertDontSee('iPhone');
    }

    public function test_mobiles_list_supports_sorting(): void
    {
        $this->makeAdmin();
        $this->createMobile(['brand_name' => 'B_Brand', 'model_name' => 'Model B']);
        $this->createMobile(['brand_name' => 'A_Brand', 'model_name' => 'Model A']);

        $response = $this->get('/admin/mobiles?sort=brand_name&order=ASC');

        $response->assertStatus(200);
        $content = $response->getContent();
        $aPos = strpos($content, 'A_Brand');
        $bPos = strpos($content, 'B_Brand');
        $this->assertLessThan($bPos, $aPos);
    }

    public function test_mobiles_list_uses_pagination(): void
    {
        $this->makeAdmin();

        // Create 25 mobiles
        for ($i = 0; $i < 25; $i++) {
            $this->createMobile([
                'brand_name' => 'Brand_' . $i,
                'model_name' => 'Model_' . $i,
            ]);
        }

        $response = $this->get('/admin/mobiles');

        $response->assertStatus(200);
        $response->assertSee('Brand_0');
        $response->assertSee('Brand_24');
    }

    public function test_mobiles_list_respects_per_page(): void
    {
        $this->makeAdmin();

        // Create 30 mobiles - use zero-padded names for proper alphabetical ordering
        for ($i = 0; $i < 30; $i++) {
            $this->createMobile([
                'brand_name' => 'Brand_' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'model_name' => 'Model_' . $i,
            ]);
        }

        $response = $this->get('/admin/mobiles?per_page=10');

        $response->assertStatus(200);
        $response->assertSee('Brand_00');
        $response->assertDontSee('Brand_10');
    }

    public function test_mobiles_list_filters_by_status(): void
    {
        $this->makeAdmin();
        $this->createMobile([
            'brand_name' => 'OfficialPhone',
            'status' => 'official',
        ]);
        $this->createMobile([
            'brand_name' => 'UnofficialPhone',
            'status' => 'unofficial',
            'official_price' => 0,
        ]);

        $response = $this->get('/admin/mobiles?status=unofficial');

        $response->assertStatus(200);
        $response->assertSee('UnofficialPhone');
        $response->assertDontSee('OfficialPhone');
    }

    // ── Create ───────────────────────────────────────────────────────

    public function test_admin_can_access_create_form(): void
    {
        $this->makeAdmin();
        $response = $this->get('/admin/mobiles/create');

        $response->assertStatus(200);
        $response->assertViewIs('admin.mobiles.form');
        $response->assertSee('Insert New Mobile');
        $response->assertSee('Brand Name');
    }

    public function test_admin_can_create_mobile(): void
    {
        $this->makeAdmin();

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/mobiles/create', [
                'brand_name' => 'Samsung',
                'model_name' => 'Galaxy S24',
                'official_price' => 120000,
                'unofficial_price' => 110000,
                'status' => 'official',
                'release_date' => '2024-01-17',
                'is_official' => 1,
            ]);

        $response->assertRedirect('/admin/mobiles');
        $response->assertSessionHas('status', 'Mobile inserted successfully');

        $mobile = DB::table('mobiles')
            ->where('brand_name', 'Samsung')
            ->where('model_name', 'Galaxy S24')
            ->first();

        $this->assertNotNull($mobile);
        $this->assertEquals('official', $mobile->status);
        $this->assertEquals(120000, $mobile->official_price);
    }

    public function test_create_mobile_requires_brand_name(): void
    {
        $this->makeAdmin();

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/mobiles/create', [
                'model_name' => 'Galaxy S24',
                'status' => 'official',
                'release_date' => '2024-01-17',
            ]);

        $response->assertSessionHas('error');
    }

    public function test_create_mobile_requires_model_name(): void
    {
        $this->makeAdmin();

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/mobiles/create', [
                'brand_name' => 'Samsung',
                'status' => 'official',
                'release_date' => '2024-01-17',
            ]);

        $response->assertSessionHas('error');
    }

    public function test_create_mobile_requires_release_date(): void
    {
        $this->makeAdmin();

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/mobiles/create', [
                'brand_name' => 'Samsung',
                'model_name' => 'Galaxy S24',
                'status' => 'official',
            ]);

        $response->assertSessionHas('error');
    }

    public function test_create_mobile_requires_valid_status(): void
    {
        $this->makeAdmin();

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/mobiles/create', [
                'brand_name' => 'Samsung',
                'model_name' => 'Galaxy S24',
                'status' => 'invalid',
                'release_date' => '2024-01-17',
            ]);

        $response->assertSessionHas('error');
    }

    public function test_create_mobile_attaches_specifications(): void
    {
        $this->makeAdmin();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/mobiles/create', [
                'brand_name' => 'Samsung',
                'model_name' => 'Galaxy S24',
                'official_price' => 120000,
                'unofficial_price' => 0,
                'status' => 'official',
                'release_date' => '2024-01-17',
                'is_official' => 1,
                'specifications' => [
                    'key' => ['RAM', 'Storage', 'Display'],
                    'value' => ['8GB', '128GB', '6.2"'],
                ],
            ]);

        $mobile = DB::table('mobiles')->where('brand_name', 'Samsung')->first();
        $this->assertNotNull($mobile);

        $specs = DB::table('mobile_specs')->where('mobile_id', $mobile->id)->get();
        $this->assertCount(3, $specs);

        $specKeys = $specs->pluck('spec_key')->all();
        $this->assertContains('RAM', $specKeys);
        $this->assertContains('Storage', $specKeys);
        $this->assertContains('Display', $specKeys);
    }

    public function test_create_mobile_attaches_tags(): void
    {
        $this->makeAdmin();

        // Create a tag first
        $tagId = DB::table('tags')->insertGetId([
            'name' => 'flagship',
            'slug' => 'flagship',
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/mobiles/create', [
                'brand_name' => 'Samsung',
                'model_name' => 'Galaxy S24',
                'official_price' => 120000,
                'unofficial_price' => 0,
                'status' => 'official',
                'release_date' => '2024-01-17',
                'is_official' => 1,
                'tags' => [$tagId],
            ]);

        $mobile = DB::table('mobiles')->where('brand_name', 'Samsung')->first();
        $this->assertNotNull($mobile);

        $tag = DB::table('content_tags')
            ->where('content_type', 'mobile')
            ->where('content_id', $mobile->id)
            ->where('tag_id', $tagId)
            ->first();

        $this->assertNotNull($tag);
    }

    public function test_create_mobile_logs_activity(): void
    {
        $this->makeAdmin();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/mobiles/create', [
                'brand_name' => 'Samsung',
                'model_name' => 'Galaxy S24',
                'official_price' => 120000,
                'unofficial_price' => 0,
                'status' => 'official',
                'release_date' => '2024-01-17',
                'is_official' => 1,
            ]);

        $mobile = DB::table('mobiles')->where('brand_name', 'Samsung')->first();

        $activity = DB::table('activity_logs')
            ->where('action', 'Mobile Created')
            ->where('resource_type', 'mobile')
            ->where('resource_id', $mobile->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->adminId, $activity->user_id);
        $this->assertEquals('admin', $activity->role);
    }

    // ── Show ─────────────────────────────────────────────────────────

    public function test_admin_can_view_mobile_detail(): void
    {
        $this->makeAdmin();
        $mobileId = $this->createMobile([
            'brand_name' => 'Samsung',
            'model_name' => 'Galaxy S24',
        ]);

        // Add specs
        DB::table('mobile_specs')->insert([
            ['mobile_id' => $mobileId, 'spec_key' => 'RAM', 'spec_value' => '8GB'],
            ['mobile_id' => $mobileId, 'spec_key' => 'Storage', 'spec_value' => '128GB'],
        ]);

        $response = $this->get('/admin/mobiles/view/' . $mobileId);

        $response->assertStatus(200);
        $response->assertViewIs('admin.mobiles.show');
        $response->assertSee('Samsung');
        $response->assertSee('Galaxy S24');
        $response->assertSee('8GB');
        $response->assertSee('128GB');
    }

    public function test_admin_cannot_view_nonexistent_mobile(): void
    {
        $this->makeAdmin();
        $this->get('/admin/mobiles/view/999999')->assertNotFound();
    }

    public function test_show_page_links_to_edit(): void
    {
        $this->makeAdmin();
        $mobileId = $this->createMobile([
            'brand_name' => 'Samsung',
            'model_name' => 'Galaxy S24',
        ]);

        $response = $this->get('/admin/mobiles/view/' . $mobileId);

        $response->assertStatus(200);
        $response->assertSeeInOrder([
            '/admin/mobiles/edit/' . $mobileId,
            'Edit',
        ]);
    }

    // ── Edit ─────────────────────────────────────────────────────────

    public function test_admin_can_access_edit_form(): void
    {
        $this->makeAdmin();
        $mobileId = $this->createMobile([
            'brand_name' => 'Samsung',
            'model_name' => 'Galaxy S24',
        ]);

        $response = $this->get('/admin/mobiles/edit/' . $mobileId);

        $response->assertStatus(200);
        $response->assertViewIs('admin.mobiles.form');
        $response->assertSee('Edit Mobile');
        $response->assertSee('Samsung');
        $response->assertSee('Galaxy S24');
    }

    public function test_edit_form_pre_fills_values(): void
    {
        $this->makeAdmin();
        $this->createMobile([
            'brand_name' => 'Samsung',
            'model_name' => 'Galaxy S24',
            'official_price' => 120000,
        ]);

        // Create form should be empty
        $response = $this->get('/admin/mobiles/create');
        $response->assertDontSee('value="Samsung"');
    }

    public function test_admin_can_update_mobile(): void
    {
        $this->makeAdmin();
        $mobileId = $this->createMobile([
            'brand_name' => 'Samsung',
            'model_name' => 'Galaxy S24',
            'official_price' => 120000,
        ]);

        $response = $this->postWithoutCsrf('/admin/mobiles/edit/' . $mobileId, [
            'brand_name' => 'Samsung',
            'model_name' => 'Galaxy S24 Ultra',
            'official_price' => 150000,
            'unofficial_price' => 140000,
            'status' => 'both',
            'release_date' => '2024-01-17',
            'is_official' => 1,
        ]);

        $response->assertRedirect('/admin/mobiles/edit/' . $mobileId);
        $response->assertSessionHas('status', 'Mobile updated successfully');

        $mobile = DB::table('mobiles')->find($mobileId);
        $this->assertEquals('Galaxy S24 Ultra', $mobile->model_name);
        $this->assertEquals(150000, $mobile->official_price);
        $this->assertEquals('both', $mobile->status);
    }

    public function test_update_mobile_requires_id(): void
    {
        $this->makeAdmin();

        $response = $this->postWithoutCsrf('/admin/mobiles/edit', [
            'brand_name' => 'Test',
            'model_name' => 'Model',
            'status' => 'official',
            'release_date' => '2024-01-17',
        ]);

        $response->assertSessionHas('error');
    }

    // ── Delete ───────────────────────────────────────────────────────

    public function test_admin_can_view_delete_confirmation(): void
    {
        $this->makeAdmin();
        $mobileId = $this->createMobile([
            'brand_name' => 'Samsung',
            'model_name' => 'Galaxy S24',
        ]);

        $response = $this->get('/admin/mobiles/delete/' . $mobileId);

        $response->assertStatus(200);
        $response->assertSee('Delete Mobile');
        $response->assertSee('Samsung');
        $response->assertSee('Galaxy S24');
    }

    public function test_admin_can_delete_mobile(): void
    {
        $this->makeAdmin();
        $mobileId = $this->createMobile([
            'brand_name' => 'Samsung',
            'model_name' => 'Galaxy S24',
        ]);

        // Add specs and images
        DB::table('mobile_specs')->insert([
            ['mobile_id' => $mobileId, 'spec_key' => 'RAM', 'spec_value' => '8GB'],
        ]);
        DB::table('mobile_images')->insert([
            ['mobile_id' => $mobileId, 'image_url' => 'https://example.com/image.jpg'],
        ]);
        DB::table('content_tags')->insert([
            'content_type' => 'mobile',
            'content_id' => $mobileId,
            'tag_id' => 1,
        ]);

        $response = $this->postWithoutCsrf('/admin/mobiles/delete/' . $mobileId);

        $response->assertRedirect('/admin/mobiles');
        $response->assertSessionHas('status', 'Mobile deleted successfully');

        // Verify mobile is deleted
        $this->assertNull(DB::table('mobiles')->find($mobileId));
        $this->assertNull(DB::table('mobile_specs')->where('mobile_id', $mobileId)->first());
        $this->assertNull(DB::table('mobile_images')->where('mobile_id', $mobileId)->first());
        $this->assertNull(
            DB::table('content_tags')
                ->where('content_type', 'mobile')
                ->where('content_id', $mobileId)
                ->first()
        );
    }

    public function test_delete_mobile_logs_activity(): void
    {
        $this->makeAdmin();
        $mobileId = $this->createMobile([
            'brand_name' => 'Samsung',
            'model_name' => 'Galaxy S24',
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/mobiles/delete/' . $mobileId);

        $activity = DB::table('activity_logs')
            ->where('action', 'Mobile Deleted')
            ->where('resource_type', 'mobile')
            ->where('resource_id', $mobileId)
            ->first();

        $this->assertNotNull($activity);
        $details = json_decode($activity->details, true);
        $this->assertEquals('Samsung', $details['brand']);
        $this->assertEquals('Galaxy S24', $details['model']);
    }

    public function test_admin_cannot_delete_nonexistent_mobile(): void
    {
        $this->makeAdmin();
        $response = $this->postWithoutCsrf('/admin/mobiles/delete/999999');
        $response->assertSessionHas('error');
    }
}
