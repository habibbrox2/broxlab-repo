<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Scraper\ScraperClient;
use App\Support\Scraper\ScraperRunner;
use App\Support\Scraper\ScraperStore;
use App\Support\Scraper\SourceCatalog;
use App\Support\ScraperPipelineService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScraperAdminPagesTest extends TestCase
{
    protected ?User $admin = null;

    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('app/testing-scraper-admin-' . uniqid());
        $store = new ScraperStore($this->dir);

        // Bind a temp store + runner so the admin actions never touch real storage.
        $this->app->instance(ScraperStore::class, $store);
        $this->app->instance(ScraperRunner::class, new ScraperRunner(
            new ScraperClient(['delay_ms' => 0, 'retries' => 0]),
            $store,
            new SourceCatalog(),
        ));

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
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '/sources/*.json') ?: [] as $file) {
                @unlink($file);
            }
            foreach (glob($this->dir . '/*.json') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->dir . '/sources');
            @rmdir($this->dir);
        }
        parent::tearDown();
    }

    public function test_guests_are_redirected_from_the_scraper_pages(): void
    {
        $this->get('/admin/scraper')->assertRedirect('/login');
        $this->get('/admin/scraper/sources')->assertRedirect('/login');
    }

    public function test_admin_pages_render(): void
    {
        $pages = [
            '/admin/scraper' => 'admin.scraper.index',
            '/admin/scraper/jobs' => 'admin.scraper.jobs',
            '/admin/scraper/sources' => 'admin.scraper.sources',
            '/admin/scraper/sources/create' => 'admin.scraper.source-create',
            '/admin/scraper/settings' => 'admin.scraper.settings',
            '/admin/scraper/settings/automation' => 'admin.scraper.settings',
            '/admin/scraper/logs' => 'admin.scraper.logs',
        ];

        foreach ($pages as $uri => $view) {
            $this->actingAs($this->admin)
                ->get($uri)
                ->assertStatus(200)
                ->assertViewIs($view);
        }
    }

    public function test_source_detail_renders_for_a_known_source(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/scraper/source/prothomalo')
            ->assertStatus(200)
            ->assertViewIs('admin.scraper.source-show');
    }

    public function test_unknown_source_detail_returns_404(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/scraper/source/nope')
            ->assertStatus(404);
    }

    public function test_run_action_executes_the_pipeline_and_reports_back(): void
    {
        $homepage = '<html><head><link rel="alternate" type="application/rss+xml" href="https://www.prothomalo.com/feed"></head></html>';
        $rss = <<<'XML'
        <?xml version="1.0"?>
        <rss version="2.0"><channel>
          <item><title>প্রথম খবর শিরোনাম</title><link>https://www.prothomalo.com/bd/run-one</link><pubDate>Mon, 21 Sep 2026 08:00:00 GMT</pubDate></item>
        </channel></rss>
        XML;

        Http::fake([
            'https://www.prothomalo.com/feed' => Http::response($rss, 200),
            'https://www.prothomalo.com' => Http::response($homepage, 200),
            '*' => Http::response('', 404),
        ]);

        $response = $this->actingAs($this->admin)->post('/admin/scraper/run', [
            'source' => 'prothomalo',
            'limit' => 5,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $snapshot = (new ScraperStore($this->dir))->readSource('prothomalo');
        $this->assertCount(1, $snapshot['items']);
    }
}
