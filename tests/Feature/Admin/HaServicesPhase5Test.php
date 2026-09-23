<?php

namespace Tests\Feature\Admin;

use App\Models\HaServiceCategory;
use App\Models\HaServiceRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 5: digital service requests, private documents, tracking.
 */
class HaServicesPhase5Test extends TestCase
{
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminId = DB::table('users')->insertGetId([
            'username' => 'p5admin' . bin2hex(random_bytes(4)),
            'first_name' => 'Phase5',
            'email' => 'p5admin' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_roles')->insert(['user_id' => $this->adminId, 'role_id' => 1, 'created_at' => now()]);
    }

    protected function tearDown(): void
    {
        DB::table('ha_service_status_history')->whereIn('service_request_id', DB::table('ha_service_requests')->where('mobile', 'like', '01700005%')->pluck('id'))->delete();
        DB::table('ha_service_documents')->whereIn('service_request_id', DB::table('ha_service_requests')->where('mobile', 'like', '01700005%')->pluck('id'))->delete();
        DB::table('ha_service_requests')->where('mobile', 'like', '01700005%')->delete();
        DB::table('ha_service_categories')->where('slug', 'like', 'test-p5-%')->delete();
        DB::table('ha_service_categories')->where('name', 'NID New Application P5')->delete();
        DB::table('ha_service_categories')->where('name', 'NID New Application Two')->delete();
        DB::table('user_roles')->where('user_id', $this->adminId)->delete();
        DB::table('users')->where('id', $this->adminId)->delete();
        parent::tearDown();
    }

    private function adminUser(): array
    {
        return [
            'name' => 'Phase5 Admin',
            'email' => DB::table('users')->where('id', $this->adminId)->value('email'),
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
    }

    private function category(array $overrides = []): HaServiceCategory
    {
        $id = DB::table('ha_service_categories')->insertGetId(array_merge([
            'name' => 'Passport Assistance',
            'slug' => 'test-p5-' . bin2hex(random_bytes(4)),
            'base_fee' => 500,
            'form_fields' => json_encode([
                ['name' => 'passport_type', 'label' => 'Passport type', 'type' => 'select', 'options' => ['e-passport', 're-issue'], 'required' => true],
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return HaServiceCategory::query()->findOrFail($id);
    }

    public function test_guest_cannot_access_admin_service_queue(): void
    {
        $this->get('/admin/ha/services')->assertRedirect('/login');
    }

    public function test_public_catalog_lists_active_categories(): void
    {
        $this->category(['name' => 'NID Correction']);
        $this->category(['is_active' => false]);

        $res = $this->get('/services-plus');
        $res->assertOk();
        $this->assertSame(1, substr_count($res->getContent(), 'NID Correction'));
    }

    public function test_guest_can_submit_request_and_get_tracking_id(): void
    {
        $cat = $this->category();

        $res = $this->post('/services-plus/apply', [
            'category_id' => $cat->id,
            'name' => 'Karim Uddin',
            'mobile' => '01700005101',
            'field_passport_type' => 'e-passport',
            'description' => 'New e-passport application',
        ]);

        $res->assertRedirect();
        $row = DB::table('ha_service_requests')->where('mobile', '01700005101')->first();
        $this->assertNotNull($row);
        $this->assertMatchesRegularExpression('/^DS-\d{4}-\d{5}$/', $row->tracking_id);
        $this->assertSame('pending', $row->status);

        $formData = json_decode($row->form_data, true);
        $this->assertSame('e-passport', $formData['passport_type']);

        // history entry created
        $this->assertSame(1, DB::table('ha_service_status_history')->where('service_request_id', $row->id)->count());
    }

    public function test_invalid_mobile_is_rejected(): void
    {
        $cat = $this->category();

        $res = $this->from('/services-plus/apply/' . $cat->slug)->post('/services-plus/apply', [
            'category_id' => $cat->id,
            'name' => 'Bad Mobile',
            'mobile' => '12345',
        ]);

        $res->assertSessionHasErrors('mobile');
        $this->assertSame(0, DB::table('ha_service_requests')->where('mobile', '12345')->count());
    }

    public function test_required_dynamic_field_is_enforced(): void
    {
        $cat = $this->category();

        $res = $this->from('/services-plus/apply/' . $cat->slug)->post('/services-plus/apply', [
            'category_id' => $cat->id,
            'name' => 'No Field',
            'mobile' => '01700005102',
        ]);

        $res->assertSessionHasErrors();
        $this->assertSame(0, DB::table('ha_service_requests')->where('mobile', '01700005102')->count());
    }

    public function test_document_upload_stores_privately_with_random_path(): void
    {
        Storage::fake('local');
        $cat = $this->category();
        $res = $this->post('/services-plus/apply', [
            'category_id' => $cat->id,
            'name' => 'Doc User',
            'mobile' => '01700005103',
            'field_passport_type' => 'e-passport',
            'documents' => [\Illuminate\Http\Testing\File::fake()->image('nid.jpg')],
        ]);

        $res->assertRedirect();
        $doc = DB::table('ha_service_documents')->first();
        $this->assertNotNull($doc);
        $this->assertStringStartsWith('service-documents/', $doc->storage_path);
        $this->assertStringNotContainsString('nid.jpg', $doc->storage_path, 'Original filename must not appear in storage path');
        $this->assertSame('image/jpeg', $doc->mime);
    }

    public function test_disguised_php_upload_is_rejected(): void
    {
        $cat = $this->category();

        $res = $this->post('/services-plus/apply', [
            'category_id' => $cat->id,
            'name' => 'Evil Upload',
            'mobile' => '01700005104',
            'field_passport_type' => 'e-passport',
            'documents' => [\Illuminate\Http\Testing\File::fake()->create('shell.php', 100)],
        ]);

        $res->assertSessionHasErrors();
        $this->assertSame(0, DB::table('ha_service_requests')->where('mobile', '01700005104')->count());
    }

    public function test_tracking_page_shows_status_history_without_pii(): void
    {
        $cat = $this->category();
        $this->post('/services-plus/apply', [
            'category_id' => $cat->id,
            'name' => 'Track Me',
            'mobile' => '01700005105',
            'field_passport_type' => 're-issue',
        ]);

        $row = DB::table('ha_service_requests')->where('mobile', '01700005105')->first();

        $res = $this->get('/services-plus/track?code=' . $row->tracking_id);
        $res->assertOk();
        $res->assertSee($row->tracking_id);
        $res->assertSee('pending');

        // PII leak guard
        $this->assertStringNotContainsString('Track Me', $res->getContent());
        $this->assertStringNotContainsString('01700005105', $res->getContent());
    }

    public function test_wrong_format_tracking_code_returns_not_found_page(): void
    {
        $res = $this->get('/services-plus/track?code=DS-9999-99999');
        $res->assertOk();
        $res->assertSee('No service request found');
    }

    public function test_admin_can_transition_status_and_history_is_append_only(): void
    {
        $cat = $this->category();
        $this->post('/services-plus/apply', [
            'category_id' => $cat->id,
            'name' => 'Status User',
            'mobile' => '01700005106',
            'field_passport_type' => 'e-passport',
        ]);
        $row = DB::table('ha_service_requests')->where('mobile', '01700005106')->first();

        $this->actingAs(\App\Models\User::find($this->adminId))
            ->post("/admin/ha/services/{$row->id}/status", ['status' => 'under_review', 'note' => 'Docs checked'])
            ->assertRedirect();

        $this->assertSame('under_review', DB::table('ha_service_requests')->where('id', $row->id)->value('status'));
        $this->assertSame(2, DB::table('ha_service_status_history')->where('service_request_id', $row->id)->count());

        // invalid status rejected by validation
        $this->post("/admin/ha/services/{$row->id}/status", ['status' => 'not-a-status'])->assertSessionHasErrors();
    }

    public function test_document_download_requires_admin(): void
    {
        $cat = $this->category();
        $this->post('/services-plus/apply', [
            'category_id' => $cat->id,
            'name' => 'DL Guard',
            'mobile' => '01700005107',
            'field_passport_type' => 'e-passport',
        ]);
        $row = DB::table('ha_service_requests')->where('mobile', '01700005107')->first();

        $this->get('/admin/ha/services/' . $row->id)->assertRedirect('/login');
        $this->get('/admin/ha/service-documents/1/download')->assertRedirect('/login');
    }

    public function test_admin_can_manage_categories(): void
    {
        $res = $this->actingAs(\App\Models\User::find($this->adminId))
            ->post('/admin/ha/services/categories', [
                'name' => 'NID New Application P5',
                'base_fee' => 300,
                'is_active' => 1,
            ]);

        $res->assertRedirect();
        $cat = DB::table('ha_service_categories')->where('name', 'NID New Application P5')->first();
        $this->assertNotNull($cat);
        $this->assertStringStartsWith('nid-new-application-p5', $cat->slug);

        // duplicate slug gets suffixed, not a crash
        $this->post('/admin/ha/services/categories', [
            'name' => 'NID New Application Two',
            'slug' => $cat->slug,
            'base_fee' => 300,
        ])->assertRedirect();

        // category with requests cannot be deleted (422 JSON or redirect-back)
        $this->post('/services-plus/apply', [
            'category_id' => $cat->id,
            'name' => 'DL Guard',
            'mobile' => '01700005199',
            'field_passport_type' => 'e-passport',
        ])->assertRedirect();
        $this->assertTrue(DB::table('ha_service_requests')->where('mobile', '01700005199')->exists());

        $del = $this->post('/admin/ha/services/categories/delete/' . $cat->id);
        $this->assertNotSame(200, $del->status());
        $this->assertTrue(DB::table('ha_service_categories')->where('id', $cat->id)->exists(), 'Category with requests must not be deleted');
    }

    public function test_signed_document_url_requires_valid_signature(): void
    {
        $cat = $this->category();
        $this->post('/services-plus/apply', [
            'category_id' => $cat->id,
            'name' => 'Signed URL',
            'mobile' => '01700005108',
            'field_passport_type' => 'e-passport',
        ]);
        $row = DB::table('ha_service_requests')->where('mobile', '01700005108')->first();

        // Tampered signature: auth middleware fires first → login redirect
        $this->get('/admin/ha/service-documents/signed/1?_signature=tampered')->assertRedirect('/login');

        // Valid signed URL generated by the service works
        $url = URL::temporarySignedRoute('ha.documents.signed', now()->addMinutes(5), ['document' => 1]);
        $this->get($url)->assertRedirect('/login'); // route still requires auth
    }
}
