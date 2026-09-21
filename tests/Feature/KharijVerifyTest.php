<?php

namespace Tests\Feature;

use App\Support\KharijService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kharij QR verification (port of the legacy public KharijController routes).
 *
 * Covers record lookup by hash, the HTML verify page, the JSON endpoint and
 * QR generation. Rows use unique hashes and are removed in tearDown.
 */
class KharijVerifyTest extends TestCase
{
    /** @var array<string> Created hashes to clean up. */
    protected array $hashes = [];

    protected function tearDown(): void
    {
        DB::table('kharij_records')->whereIn('hash', $this->hashes)->delete();

        parent::tearDown();
    }

    protected function makeRecord(array $data = [], ?string $hash = null, bool $deleted = false): string
    {
        $hash = $hash ?? substr(bin2hex(random_bytes(16)), 0, 32);
        $this->hashes[] = $hash;

        DB::table('kharij_records')->insert([
            'hash' => $hash,
            'data_json' => json_encode(array_merge([
                'mouza' => 'টেস্ট মৌজা',
                'upazila' => 'টেস্ট উপজেলা',
                'district' => 'টেস্ট জেলা',
                'khata_number' => '১২৩',
                'application_no' => 'APP-001',
                'owner_name' => 'রহিম উদ্দিন',
            ], $data)),
            'generated_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deleted ? now() : null,
        ]);

        return $hash;
    }

    // ── Service: lookup ───────────────────────────────────────────────

    public function test_find_by_hash_returns_record_with_data(): void
    {
        $hash = $this->makeRecord(['khata_number' => '৯৯৯']);
        $service = app(KharijService::class);

        $record = $service->findByHash($hash);

        $this->assertNotNull($record);
        $this->assertSame($hash, $record->hash);
        $this->assertSame('৯৯৯', $record->data['khata_number']);
    }

    public function test_find_by_hash_rejects_deleted_records(): void
    {
        $hash = $this->makeRecord([], null, true);

        $this->assertNull(app(KharijService::class)->findByHash($hash));
    }

    public function test_find_by_hash_sanitizes_input(): void
    {
        $hash = $this->makeRecord();
        $service = app(KharijService::class);

        // legacy sanitizes to alphanumerics — injection-shaped input still resolves
        $this->assertNotNull($service->findByHash("/{$hash}' OR 1=1--"));
        $this->assertNull($service->findByHash("'; DROP TABLE kharij_records;--"));
    }

    // ── Service: QR generation ────────────────────────────────────────

    public function test_qr_data_uri_is_valid_png(): void
    {
        $uri = app(KharijService::class)->qrDataUri('testhash123');

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $binary = base64_decode(substr($uri, strlen('data:image/png;base64,')));
        $this->assertStringStartsWith("\x89PNG", $binary);
        $this->assertGreaterThan(500, strlen($binary));
    }

    public function test_verification_url_uses_gov_path_structure(): void
    {
        $service = app(KharijService::class);

        $mutation = $service->verificationUrl('abc123', 'qr-vk', 'mutation');
        $dakhila = $service->verificationUrl('abc123', 'dakhila-print', 'dakhila');

        $this->assertStringContainsString('/mutation-land-gov-bd/qr-vk/abc123', $mutation);
        $this->assertStringContainsString('/ldtax-gov-bd/dakhila-print/abc123', $dakhila);
    }

    // ── HTML verify page ──────────────────────────────────────────────

    public function test_verify_page_shows_record_details(): void
    {
        $hash = $this->makeRecord();

        $this->get("/mutation-land-gov-bd/qr-vk/{$hash}")
            ->assertOk()
            ->assertSee('খারিজ যাচাইকরণ')
            ->assertSee('সত্যায়িত')
            ->assertSee('টেস্ট মৌজা')
            ->assertSee('রহিম উদ্দিন')
            ->assertSee('data:image/png;base64,', false);
    }

    public function test_verify_page_shows_not_found_for_unknown_hash(): void
    {
        $this->get('/mutation-land-gov-bd/qr-vk/doesnotexist123')
            ->assertOk()
            ->assertSee('রশিদ পাওয়া যায়নি');
    }

    // ── JSON endpoint ─────────────────────────────────────────────────

    public function test_json_verify_returns_record(): void
    {
        $hash = $this->makeRecord();

        $this->getJson("/api/kharij/verify/{$hash}")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'valid' => true,
                'hash' => $hash,
            ]);
    }

    public function test_json_verify_returns_404_for_unknown_hash(): void
    {
        $this->getJson('/api/kharij/verify/nope123456')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'valid' => false,
            ]);
    }
}
