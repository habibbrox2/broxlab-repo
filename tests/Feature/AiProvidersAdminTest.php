<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Ai\AiProviderRepository;
use Tests\TestCase;

class AiProvidersAdminTest extends TestCase
{
    protected ?User $admin = null;

    protected string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = storage_path('app/testing-ai-admin-' . uniqid() . '.json');
        $this->app->instance(AiProviderRepository::class, new AiProviderRepository($this->file));

        $this->admin = User::where('username', 'admin')->first();
        if (! $this->admin) {
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
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_guests_cannot_manage_providers(): void
    {
        $this->get('/admin/aisystem/providers')->assertRedirect('/login');
    }

    public function test_admin_can_view_the_providers_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/aisystem/providers')
            ->assertStatus(200)
            ->assertViewIs('admin.aisystem.providers');
    }

    public function test_admin_can_create_a_provider(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/aisystem/providers', [
                'name' => 'OpenRouter Primary',
                'driver' => 'openrouter',
                'base_url' => 'https://openrouter.ai/api/v1',
                'model' => 'openai/gpt-4o-mini',
                'max_tokens' => 512,
                'enabled' => 1,
            ])
            ->assertRedirect('/admin/aisystem/providers');

        $providers = $this->app->make(AiProviderRepository::class)->all();
        $this->assertCount(1, $providers);
        $this->assertSame('OpenRouter Primary', $providers[0]['name']);
        $this->assertTrue($providers[0]['is_default']);
    }

    public function test_admin_can_delete_a_provider(): void
    {
        $repo = $this->app->make(AiProviderRepository::class);
        $provider = $repo->upsert(['name' => 'Temp', 'driver' => 'openai_compatible']);

        $this->actingAs($this->admin)
            ->post('/admin/aisystem/providers/' . $provider['id'] . '/delete')
            ->assertRedirect('/admin/aisystem/providers');

        $this->assertCount(0, $repo->all());
    }
}
