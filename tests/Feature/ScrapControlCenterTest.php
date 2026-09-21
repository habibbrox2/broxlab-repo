<?php

namespace Tests\Feature;

use App\Support\ScraperPipelineService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ScrapControlCenterTest extends TestCase
{
    private string $validToken = 'test-secret-token-12345';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('scraper.cron_token', $this->validToken);
    }

    private function mockPipelineStatus(array $totals = null, string $message = ''): void
    {
        $pipeline = $this->app->make(ScraperPipelineService::class);
        $this->mock(ScraperPipelineService::class, function ($mock) use ($totals, $message, $pipeline) {
            $mock->shouldReceive('status')
                ->with(false)
                ->andReturn([
                    'totals' => $totals ?? ['added' => 5, 'skipped' => 2, 'failed' => 0],
                    'message' => $message,
                ]);
        });
    }

    /** @test */
    public function it_rejects_requests_with_no_token_configured(): void
    {
        Config::set('scraper.cron_token', '');

        $response = $this->postJson('/internal/api/scrap-control-center/cron-run-pipeline', [
            'type' => 'mobiles',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid token']);
    }

    /** @test */
    public function it_rejects_requests_with_wrong_token(): void
    {
        $response = $this->postJson('/internal/api/scrap-control-center/cron-run-pipeline', [
            'type' => 'mobiles',
        ], [
            'X-Scraper-Cron-Token' => 'wrong-token',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_requests_with_missing_token_header(): void
    {
        $response = $this->postJson('/internal/api/scrap-control-center/cron-run-pipeline', [
            'type' => 'mobiles',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_accepts_mobiles_type_request(): void
    {
        Artisan::shouldReceive('call')
            ->with('scraper:run', [
                '--type' => 'mobile',
                '--limit' => 20,
                '--no-publish' => false,
            ])
            ->andReturn(0);

        Artisan::shouldReceive('output')->andReturn('Pipeline completed successfully');

        $this->mockPipelineStatus();

        $response = $this->postJson('/internal/api/scrap-control-center/cron-run-pipeline', [
            'type' => 'mobiles',
        ], [
            'X-Scraper-Cron-Token' => $this->validToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'type' => 'mobile',
            'limit' => 20,
        ]);
    }

    /** @test */
    public function it_maps_articles_type_to_all_content_types(): void
    {
        Artisan::shouldReceive('call')
            ->with('scraper:run', [
                '--type' => null,
                '--limit' => 50,
                '--no-publish' => false,
            ])
            ->andReturn(0);

        Artisan::shouldReceive('output')->andReturn('');

        $this->mockPipelineStatus(['added' => 10, 'skipped' => 5, 'failed' => 0]);

        $response = $this->postJson('/internal/api/scrap-control-center/cron-run-pipeline', [
            'type' => 'articles',
            'limit' => 50,
        ], [
            'X-Scraper-Cron-Token' => $this->validToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'type' => 'all',
            'limit' => 50,
            'added' => 10,
            'skipped' => 5,
        ]);
    }

    /** @test */
    public function it_enforces_limit_bounds(): void
    {
        // Limit too high — should be clamped to 200
        Artisan::shouldReceive('call')
            ->with('scraper:run', [
                '--type' => 'mobile',
                '--limit' => 200,
                '--no-publish' => false,
            ])
            ->andReturn(0);

        Artisan::shouldReceive('output')->andReturn('');

        $this->mockPipelineStatus();

        $response = $this->postJson('/internal/api/scrap-control-center/cron-run-pipeline', [
            'type' => 'mobiles',
            'limit' => 999,
        ], [
            'X-Scraper-Cron-Token' => $this->validToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['limit' => 200]);
    }

    /** @test */
    public function it_handles_command_failures(): void
    {
        Artisan::shouldReceive('call')
            ->with('scraper:run', [
                '--type' => 'mobile',
                '--limit' => 20,
                '--no-publish' => false,
            ])
            ->andThrow(new \RuntimeException('Command failed'));

        $this->mockPipelineStatus();

        $response = $this->postJson('/internal/api/scrap-control-center/cron-run-pipeline', [
            'type' => 'mobiles',
        ], [
            'X-Scraper-Cron-Token' => $this->validToken,
        ]);

        $response->assertStatus(500);
        $response->assertJson(['status' => 'error']);
    }

    /** @test */
    public function it_supports_no_publish_flag(): void
    {
        Artisan::shouldReceive('call')
            ->with('scraper:run', [
                '--type' => 'mobile',
                '--limit' => 20,
                '--no-publish' => true,
            ])
            ->andReturn(0);

        Artisan::shouldReceive('output')->andReturn('');

        $this->mockPipelineStatus();

        $response = $this->postJson('/internal/api/scrap-control-center/cron-run-pipeline', [
            'type' => 'mobiles',
            'no_publish' => true,
        ], [
            'X-Scraper-Cron-Token' => $this->validToken,
        ]);

        $response->assertStatus(200);
    }
}
