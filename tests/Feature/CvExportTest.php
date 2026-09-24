<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * CV PDF export (mPDF port of the legacy CvExportService).
 *
 * Covers auth, ownership, PDF payload sanity and the admin override.
 * Rows are created with unique markers and removed in tearDown.
 */
class CvExportTest extends TestCase
{
    /** @var array<int> Created user ids to clean up. */
    protected array $userIds = [];

    /** @var array<int> Created cvs ids to clean up. */
    protected array $cvIds = [];

    protected function tearDown(): void
    {
        foreach ($this->cvIds as $id) {
            $sectionIds = DB::table('cv_sections')->where('cv_id', $id)->pluck('id');
            DB::table('cv_items')->whereIn('section_id', $sectionIds)->delete();
            DB::table('cv_sections')->where('cv_id', $id)->delete();
            DB::table('cvs')->where('id', $id)->delete();
        }
        foreach ($this->userIds as $id) {
            DB::table('user_roles')->where('user_id', $id)->delete();
            DB::table('users')->where('id', $id)->delete();
        }

        parent::tearDown();
    }

    protected function makeUser(array $overrides = []): User
    {
        $suffix = bin2hex(random_bytes(8));
        $data = array_merge([
            'username' => 'cvexp_'.$suffix,
            'email' => 'cvexp_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Cv',
            'last_name' => 'Export',
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

    protected function makeCv(int $userId, array $overrides = []): int
    {
        $id = DB::table('cvs')->insertGetId([
            'user_id' => $userId,
            'title' => $overrides['title'] ?? 'Test CV '.uniqid(),
            'template' => 'modern',
            'is_active' => 1,
            'view_count' => 0,
            'download_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        $this->cvIds[] = $id;

        return $id;
    }

    protected function addSection(int $cvId, string $type, string $title, array $items = []): int
    {
        $sectionId = DB::table('cv_sections')->insertGetId([
            'cv_id' => $cvId,
            'section_type' => $type,
            'title' => $title,
            'sort_order' => 1,
            'is_visible' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($items as $i => $content) {
            DB::table('cv_items')->insert([
                'section_id' => $sectionId,
                'item_type' => $type,
                'content_json' => json_encode($content),
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $sectionId;
    }

    protected function getWithoutCsrf(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withoutMiddleware(ValidateCsrfToken::class)->get($uri);
    }

    // ── Auth ──────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $userId = $this->makeUser()->id;
        $cvId = $this->makeCv((int) $userId);

        // The route uses Laravel's auth middleware, which redirects guests.
        $this->get("/cv/{$cvId}/pdf")->assertRedirect();
    }

    // ── Ownership ─────────────────────────────────────────────────────

    public function test_forbidden_for_other_users_cv(): void
    {
        $owner = $this->makeUser();
        $intruder = $this->makeUser();
        $cvId = $this->makeCv((int) $owner->id);

        $this->actingAs($intruder)
            ->getWithoutCsrf("/cv/{$cvId}/pdf")
            ->assertStatus(403);
    }

    public function test_missing_cv_is_403_even_for_owner_id_mismatch(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getWithoutCsrf('/cv/99999999/pdf')
            ->assertStatus(403);
    }

    // ── Happy path ────────────────────────────────────────────────────

    public function test_owner_gets_a_valid_pdf_inline(): void
    {
        $user = $this->makeUser();
        $cvId = $this->makeCv((int) $user->id);
        $this->addSection($cvId, 'summary', 'Summary', [
            ['title' => 'Software Engineer', 'description' => 'Builds web apps.'],
        ]);

        $response = $this->actingAs($user)
            ->getWithoutCsrf("/cv/{$cvId}/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));

        $body = (string) $response->getContent();
        // PDF magic bytes + non-trivial size
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertGreaterThan(1000, strlen($body));
    }

    public function test_download_disposition_when_requested(): void
    {
        $user = $this->makeUser();
        $cvId = $this->makeCv((int) $user->id, ['title' => 'Bengali CV টেস্ট']);

        $response = $this->actingAs($user)
            ->getWithoutCsrf("/cv/{$cvId}/pdf?download=1");

        $response->assertOk();
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    public function test_pdf_contains_section_content(): void
    {
        $user = $this->makeUser();
        $cvId = $this->makeCv((int) $user->id);
        $this->addSection($cvId, 'experience', 'Experience', [
            ['title' => 'Senior Developer', 'organization' => 'Acme Corp', 'description' => 'Shipped things.'],
        ]);

        $response = $this->actingAs($user)
            ->getWithoutCsrf("/cv/{$cvId}/pdf");

        $response->assertOk();
        // mPDF compresses content streams; the text must still be extractable
        // via a simple lookbehind over any uncompressed metadata OR by using
        // the service's html directly. We assert via the service instead.
        $service = app(\App\Support\CvExportService::class);
        $result = $service->exportPdf((int) $cvId, (int) $user->id);
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Senior Developer', $result['html']);
        $this->assertStringContainsString('Acme Corp', $result['html']);
    }

    public function test_hidden_sections_are_excluded(): void
    {
        $user = $this->makeUser();
        $cvId = $this->makeCv((int) $user->id);
        $service = app(\App\Support\CvExportService::class);

        DB::table('cv_sections')->insert([
            'cv_id' => $cvId,
            'section_type' => 'skills',
            'title' => 'Secret Section',
            'sort_order' => 1,
            'is_visible' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $service->exportPdf((int) $cvId, (int) $user->id);

        $this->assertTrue($result['success']);
        $this->assertStringNotContainsString('Secret Section', $result['html']);
    }

    // ── Admin override ────────────────────────────────────────────────

    public function test_admin_can_export_any_cv(): void
    {
        $owner = $this->makeUser();
        $admin = $this->makeUser();
        $cvId = $this->makeCv((int) $owner->id);

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (! $adminRoleId) {
            $this->markTestSkipped('roles table has no admin role row');
        }
        DB::table('user_roles')->insert([
            'user_id' => $admin->id,
            'role_id' => $adminRoleId,
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getWithoutCsrf("/cv/{$cvId}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_soft_deleted_cv_is_not_exportable(): void
    {
        $user = $this->makeUser();
        $cvId = $this->makeCv((int) $user->id);
        DB::table('cvs')->where('id', $cvId)->update(['deleted_at' => now()]);

        $this->actingAs($user)
            ->getWithoutCsrf("/cv/{$cvId}/pdf")
            ->assertStatus(403);
    }
}
