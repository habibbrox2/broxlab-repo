<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\BackgroundRemover;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackgroundRemoverTest extends TestCase
{
    protected BackgroundRemover $remover;

    protected function setUp(): void
    {
        parent::setUp();
        $this->remover = new BackgroundRemover();
    }

    /** @test */
    public function it_returns_failure_when_no_api_key_or_ai_is_configured(): void
    {
        config()->set('scraper.remove_bg_api_key', '');
        config()->set('app.ai_enabled', false);

        $result = $this->remover->remove('/tmp/nonexistent.png');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('No background removal', $result['error']);
    }

    /** @test */
    public function it_calls_remove_bg_api_when_key_is_configured(): void
    {
        config()->set('scraper.remove_bg_api_key', 'test-key');

        Http::fake([
            'api.remove.bg/*' => Http::response(['image_b64' => 'iVBORw0KGgoAAAANSUhEUg=='], 200),
        ]);

        $result = $this->remover->remove(
            $this->sampleImage(),
            ['size' => 'auto', 'format' => 'png']
        );

        $this->assertTrue($result['ok']);
        $this->assertEquals('iVBORw0KGgoAAAANSUhEUg==', $result['content']);
    }

    /** @test */
    public function it_handles_remove_bg_api_error_gracefully(): void
    {
        config()->set('scraper.remove_bg_api_key', 'test-key');

        Http::fake([
            'api.remove.bg/*' => Http::response(['errors' => [['title' => 'Invalid image']]], 400),
        ]);

        $result = $this->remover->remove(
            $this->sampleImage(),
            ['size' => 'auto', 'format' => 'png']
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('remove.bg API error', $result['error']);
    }

    /** @test */
    public function it_handles_remove_bg_raw_image_response(): void
    {
        config()->set('scraper.remove_bg_api_key', 'test-key');

        $pngBytes = "\x89PNG\r\n\x1a\n" . random_bytes(100);

        Http::fake([
            'api.remove.bg/*' => Http::response($pngBytes, 200, ['Content-Type' => 'image/png']),
        ]);

        $tempFile = $this->sampleImage();
        file_put_contents($tempFile, $pngBytes);

        $result = $this->remover->remove($tempFile, ['size' => 'auto', 'format' => 'png']);

        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['content']);
        $this->assertEquals(base64_encode($pngBytes), $result['content']);
    }

    /**
     * Create a sample PNG file in temp dir and return its path.
     */
    protected function sampleImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bg_remover_');
        if (! str_ends_with($path, '.png')) {
            $path .= '.png';
        }
        file_put_contents($path, "\x89PNG\r\n\x1a\n" . random_bytes(50));
        return $path;
    }

    /** @test */
    public function it_can_store_result_to_temp_storage(): void
    {
        Storage::fake('public');

        $base64Content = base64_encode('fake-png-data');
        $url = $this->remover->storeResult($base64Content, 'test-bg-removed');

        $this->assertNotNull($url);
        $this->assertStringContainsString('/storage/photo-edit-temp/', $url);
        $this->assertStringContainsString('test-bg-removed-', $url);
    }

    /** @test */
    public function it_returns_null_for_invalid_base64_in_store_result(): void
    {
        Storage::fake('public');

        $url = $this->remover->storeResult('!!!not-valid-base64!!!', 'test');

        $this->assertNull($url);
    }
}
