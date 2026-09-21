<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Ai\AiClient;
use App\Support\Ai\AiProviderRepository;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    protected string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = storage_path('app/testing-ai-' . uniqid() . '.json');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    protected function repo(): AiProviderRepository
    {
        return new AiProviderRepository($this->file);
    }

    public function test_first_provider_becomes_default(): void
    {
        $repo = $this->repo();
        $provider = $repo->upsert([
            'name' => 'OpenRouter',
            'driver' => 'openrouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'model' => 'openai/gpt-4o-mini',
        ]);

        $this->assertNotEmpty($provider['id']);
        $this->assertTrue($provider['is_default']);
        $this->assertCount(1, $repo->all());
        $this->assertSame('OpenRouter', $repo->default()['name']);
    }

    public function test_default_can_be_moved_and_provider_deleted(): void
    {
        $repo = $this->repo();
        $first = $repo->upsert(['name' => 'A', 'driver' => 'openrouter']);
        $second = $repo->upsert(['name' => 'B', 'driver' => 'openai_compatible', 'is_default' => true]);

        $this->assertSame('B', $repo->default()['name']);

        $repo->setDefault($first['id']);
        $this->assertSame('A', $repo->default()['name']);

        $this->assertTrue($repo->delete($second['id']));
        $this->assertCount(1, $repo->all());
    }

    public function test_unknown_driver_falls_back_to_openrouter(): void
    {
        $provider = $this->repo()->upsert(['name' => 'weird', 'driver' => 'not-a-driver']);

        $this->assertSame('openrouter', $provider['driver']);
    }

    public function test_api_keys_are_encrypted_at_rest_and_masked_for_views(): void
    {
        if (! config('app.key')) {
            $this->markTestSkipped('APP_KEY is not configured');
        }

        $repo = $this->repo();
        $provider = $repo->upsert([
            'name' => 'Secret',
            'driver' => 'openrouter',
            'api_key' => 'sk-super-secret-key',
        ]);

        $raw = file_get_contents($this->file);
        $this->assertStringNotContainsString('sk-super-secret-key', (string) $raw);
        $this->assertSame('sk-super-secret-key', $repo->revealKey($provider));

        $masked = $repo->allMasked()[0];
        $this->assertSame('••••••••-key', $masked['api_key']);
        $this->assertTrue($masked['has_key']);
    }

    public function test_client_posts_to_an_openai_compatible_endpoint(): void
    {
        if (! config('app.key')) {
            $this->markTestSkipped('APP_KEY is not configured');
        }

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'pong']]],
                'model' => 'test-model',
                'usage' => ['total_tokens' => 7],
            ], 200),
            '*' => Http::response('', 404),
        ]);

        $repo = $this->repo();
        $provider = $repo->upsert([
            'name' => 'OR',
            'driver' => 'openrouter',
            'model' => 'test-model',
            'api_key' => 'sk-test',
        ]);

        $result = (new AiClient($repo))->chat([['role' => 'user', 'content' => 'ping']], $provider);

        $this->assertTrue($result['ok'], (string) $result['error']);
        $this->assertSame('pong', $result['content']);
        $this->assertSame('test-model', $result['model']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-test')
            && $request['model'] === 'test-model');
    }

    public function test_client_reports_failure_without_a_provider(): void
    {
        $result = (new AiClient($this->repo()))->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('No AI provider', (string) $result['error']);
    }
}
