<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Scraper\ScraperClient;
use App\Support\Scraper\ScraperRunner;
use App\Support\Scraper\ScraperStore;
use App\Support\Scraper\SourceCatalog;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScraperPipelineTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('app/testing-scraping-' . uniqid());
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

    protected function runner(): ScraperRunner
    {
        return new ScraperRunner(
            new ScraperClient(['delay_ms' => 0, 'retries' => 0]),
            new ScraperStore($this->dir),
            new SourceCatalog(),
        );
    }

    protected function fakeProthomAlo(): void
    {
        $homepage = '<html><head><link rel="alternate" type="application/rss+xml" href="https://www.prothomalo.com/feed"></head><body></body></html>';
        $rss = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0"><channel>
          <item><title>প্রথম খবর শিরোনাম এক</title><link>https://www.prothomalo.com/bangladesh/story-one</link><pubDate>Mon, 21 Sep 2026 08:00:00 GMT</pubDate></item>
          <item><title>দ্বিতীয় খবর শিরোনাম দুই</title><link>https://www.prothomalo.com/bangladesh/story-two</link><pubDate>Sun, 20 Sep 2026 08:00:00 GMT</pubDate></item>
        </channel></rss>
        XML;

        Http::fake([
            'https://www.prothomalo.com/feed' => Http::response($rss, 200),
            'https://www.prothomalo.com' => Http::response($homepage, 200),
            'https://www.prothomalo.com/*' => Http::response('', 404),
            '*' => Http::response('', 404),
        ]);
    }

    public function test_it_writes_a_snapshot_for_a_single_source(): void
    {
        $this->fakeProthomAlo();

        $result = $this->runner()->run(sourceId: 'prothomalo', limit: 10);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['sources']);
        $this->assertSame(2, $result['totals']['fetched']);
        $this->assertSame(2, $result['totals']['added']);
        $this->assertSame('feed', $result['results'][0]['strategy']);

        $store = new ScraperStore($this->dir);
        $snapshot = $store->readSource('prothomalo');

        $this->assertCount(2, $snapshot['items']);
        $this->assertSame('prothomalo', $snapshot['items'][0]['source_key']);
        $this->assertFileExists($store->sourcePath('prothomalo'));
    }

    public function test_a_second_run_skips_already_seen_urls(): void
    {
        $this->fakeProthomAlo();
        $runner = $this->runner();

        $first = $runner->run(sourceId: 'prothomalo', limit: 10);
        $second = $runner->run(sourceId: 'prothomalo', limit: 10);

        $this->assertSame(2, $first['totals']['added']);
        $this->assertSame(0, $second['totals']['added']);
        $this->assertSame(2, $second['totals']['skipped']);
    }

    public function test_it_falls_back_to_html_listing_when_no_feed_exists(): void
    {
        $html = <<<'HTML'
        <html><body>
          <a href="/jobs/senior-software-engineer-dhaka">Senior Software Engineer, Dhaka — apply now</a>
          <a href="/jobs/marketing-executive-chittagong">Marketing Executive, Chittagong — apply now</a>
          <a href="/about">About us</a>
        </body></html>
        HTML;

        Http::fake([
            'https://www.bdjobs.com' => Http::response($html, 200),
            'https://www.bdjobs.com/*' => Http::response('', 404),
            '*' => Http::response('', 404),
        ]);

        $result = $this->runner()->run(sourceId: 'bdjobs', limit: 10);

        $this->assertSame('html', $result['results'][0]['strategy']);
        $this->assertSame(2, $result['totals']['added']);
    }

    public function test_it_records_run_history(): void
    {
        $this->fakeProthomAlo();
        $this->runner()->run(sourceId: 'prothomalo', limit: 5);

        $runs = (new ScraperStore($this->dir))->runs();

        $this->assertNotEmpty($runs);
        $this->assertSame('prothomalo', $runs[0]['source_id']);
        $this->assertSame(2, $runs[0]['totals']['added']);
    }

    public function test_unknown_source_returns_an_error_without_writing(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $result = $this->runner()->run(sourceId: 'does-not-exist');

        $this->assertSame('error', $result['status']);
        $this->assertFileDoesNotExist($this->dir . '/sources/does-not-exist.json');
    }

    public function test_the_command_no_ops_when_the_pipeline_is_disabled(): void
    {
        config(['scraper.enabled' => false]);

        Http::fake(['*' => Http::response('', 404)]);

        $this->artisan('scraper:run')->assertExitCode(0);

        Http::assertNothingSent();
    }
}
