<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Scraper\MobileDetailParser;
use App\Support\Scraper\ScraperClient;
use App\Support\Scraper\ScraperRunner;
use App\Support\Scraper\ScraperStore;
use App\Support\Scraper\SourceCatalog;
use App\Support\Scraper\SitemapReader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileScraperPipelineTest extends TestCase
{
    protected ScraperRunner $runner;
    protected ScraperStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Point the store at a temp dir so tests won't clobber real snapshots.
        $this->store = new ScraperStore(sys_get_temp_dir().'/mobile-scraper-test-'.uniqid());
        $client = new ScraperClient();
        $sitemap = new SitemapReader($client);
        $parser = new MobileDetailParser();

        $this->runner = new ScraperRunner($client, $this->store, null, null, $parser);
    }

    protected function tearDown(): void
    {
        $this->store->sourcePath('mobiledokan'); // ensure dir exists
        $dir = $this->store->basePath();
        $this->recursiveDelete($dir);

        parent::tearDown();
    }

    public function test_it_discovers_and_parses_mobile_detail_pages_from_sitemap(): void
    {
        // The SitemapReader checks /sitemap.xml, then /sitemap_index.xml.
        // We serve a sitemap with product URLs on the first try.
        Http::fake([
            'mobiledokan.co/sitemap.xml' => Http::response(<<<'XML'
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>https://www.mobiledokan.co/product/samsung-s24u</loc><lastmod>2026-09-01</lastmod></url>
              <url><loc>https://www.mobiledokan.co/product/xiaomi-14</loc><lastmod>2026-08-15</lastmod></url>
            </urlset>
            XML, 200),
            'mobiledokan.co/product/samsung-s24u' => Http::response($this->detailHtml('Samsung Galaxy S24 Ultra', 125000), 200),
            'mobiledokan.co/product/xiaomi-14' => Http::response($this->detailHtml('Xiaomi 14', 95000), 200),
        ]);

        $config = [
            'key' => 'mobiledokan',
            'name' => 'MobileDokan',
            'type' => 'mobile',
            'homepage' => 'https://www.mobiledokan.co',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'sitemap',
            'enabled' => true,
        ];

        $result = $this->runner->runSource($config, 10, false);

        $this->assertSame(2, $result['fetched']);  // 2 URLs discovered from sitemap
        $this->assertSame(2, $result['added']);    // 2 new items parsed and stored
        $this->assertSame(0, $result['skipped']);

        // Verify snapshot was written with parsed mobile data.
        $stored = $this->store->readSource('mobiledokan');
        $this->assertSame(2, $stored['count']);
        $this->assertSame(2, count($stored['items']));

        $first = $stored['items'][0];
        $this->assertSame('Samsung Galaxy S24 Ultra', $first['title']);
        $this->assertSame('Samsung', $first['brand_name']);
        $this->assertSame('Galaxy S24 Ultra', $first['model_name']);
        $this->assertEquals(125000.0, $first['official_price']);
        $this->assertSame('mobile', $first['type']);
        $this->assertSame('mobiledokan', $first['source_key']);

        // Verify URL dedup — both URLs should be in the seen set.
        $seen = $this->store->seen();
        $this->assertArrayHasKey('https://www.mobiledokan.co/product/samsung-s24u', $seen);
        $this->assertArrayHasKey('https://www.mobiledokan.co/product/xiaomi-14', $seen);
    }

    public function test_it_skips_already_seen_urls(): void
    {
        // Pre-populate the seen set with one URL.
        $seen = $this->store->seen();
        $seen['https://www.mobiledokan.co/product/seen-phone'] = time();
        $this->store->markSeen(['https://www.mobiledokan.co/product/seen-phone']);

        Http::fake([
            'mobiledokan.co/sitemap.xml' => Http::response(<<<'XML'
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>https://www.mobiledokan.co/product/seen-phone</loc></url>
              <url><loc>https://www.mobiledokan.co/product/new-phone</loc></url>
            </urlset>
            XML, 200),
            'mobiledokan.co/product/seen-phone' => Http::response($this->detailHtml('Seen Phone', 100000), 200),
            'mobiledokan.co/product/new-phone' => Http::response($this->detailHtml('New Phone', 50000), 200),
        ]);

        $config = [
            'key' => 'mobiledokan',
            'name' => 'MobileDokan',
            'type' => 'mobile',
            'homepage' => 'https://www.mobiledokan.co',
            'strategy' => 'sitemap',
            'enabled' => true,
        ];

        $result = $this->runner->runSource($config, 10, false);

        // Both URLs are discovered (fetched = 2), but only the new one is added.
        $this->assertSame(2, $result['fetched']);
        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_it_respects_limit_when_parsing_mobile_pages(): void
    {
        $sitemapUrls = '';
        for ($i = 1; $i <= 5; $i++) {
            $sitemapUrls .= "<url><loc>https://www.mobiledokan.co/product/phone-$i</loc></url>";
        }

        Http::fake([
            'mobiledokan.co/sitemap.xml' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$sitemapUrls.'</urlset>',
                200
            ),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Http::macro("fake-product-$i", function () use ($i) {
                return Http::response($this->detailHtml("Phone $i", 10000 * $i), 200);
            });
        }

        // Register individual fake responses for each detail URL.
        for ($i = 1; $i <= 5; $i++) {
            Http::fake([
                "mobiledokan.co/product/phone-$i" => Http::response($this->detailHtml("Phone $i", 10000 * $i), 200),
            ]);
        }

        $config = [
            'key' => 'mobiledokan',
            'name' => 'MobileDokan',
            'type' => 'mobile',
            'homepage' => 'https://www.mobiledokan.co',
            'strategy' => 'sitemap',
            'enabled' => true,
        ];

        // Limit to 2 — only 2 detail pages should be fetched and parsed.
        $result = $this->runner->runSource($config, 2, false);

        $this->assertSame(2, $result['added']);
    }

    public function test_it_handles_parser_returning_null_gracefully(): void
    {
        Http::fake([
            'mobiledokan.co/sitemap.xml' => Http::response(<<<'XML'
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>https://www.mobiledokan.co/product/non-parseable</loc></url>
            </urlset>
            XML, 200),
            'mobiledokan.co/product/non-parseable' => Http::response('<html><body>no title here</body></html>', 200),
        ]);

        $config = [
            'key' => 'mobiledokan',
            'name' => 'MobileDokan',
            'type' => 'mobile',
            'homepage' => 'https://www.mobiledokan.co',
            'strategy' => 'sitemap',
            'enabled' => true,
        ];

        $result = $this->runner->runSource($config, 10, false);

        // The URL was discovered (fetched = 1) but parsing returned null.
        $this->assertSame(1, $result['fetched']);
        $this->assertSame(0, $result['added']);
        // Skipped is about dedup (seen), not parse failures.
        $this->assertSame(0, $result['skipped']);
    }

    public function test_it_handles_fetch_failure_gracefully(): void
    {
        Http::fake([
            'mobiledokan.co/sitemap.xml' => Http::response(<<<'XML'
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>https://www.mobiledokan.co/product/broken</loc></url>
              <url><loc>https://www.mobiledokan.co/product/good</loc></url>
            </urlset>
            XML, 200),
            'mobiledokan.co/product/broken' => Http::response('', 404),
            'mobiledokan.co/product/good' => Http::response($this->detailHtml('Good Phone', 30000), 200),
        ]);

        $config = [
            'key' => 'mobiledokan',
            'name' => 'MobileDokan',
            'type' => 'mobile',
            'homepage' => 'https://www.mobiledokan.co',
            'strategy' => 'sitemap',
            'enabled' => true,
        ];

        $result = $this->runner->runSource($config, 10, false);

        $this->assertSame(2, $result['fetched']);
        $this->assertSame(1, $result['added']); // only the good one parsed
        // Skipped tracks dedup only — fetch failures just drop from normalized.
        $this->assertSame(0, $result['skipped']);
    }

    public function test_it_produces_valid_item_structure(): void
    {
        Http::fake([
            'mobiledokan.co/sitemap.xml' => Http::response(<<<'XML'
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>https://www.mobiledokan.co/product/test-phone</loc></url>
            </urlset>
            XML, 200),
            'mobiledokan.co/product/test-phone' => Http::response($this->detailHtml('Samsung Galaxy S24 Ultra', 125000), 200),
        ]);

        $config = [
            'key' => 'mobiledokan',
            'name' => 'MobileDokan',
            'type' => 'mobile',
            'homepage' => 'https://www.mobiledokan.co',
            'strategy' => 'sitemap',
            'lang' => 'bn',
            'enabled' => true,
        ];

        $this->runner->runSource($config, 10, false);

        $stored = $this->store->readSource('mobiledokan');
        $item = $stored['items'][0];

        // Verify all required fields from MobilePublisher::publishItem() expectations.
        $this->assertArrayHasKey('title', $item);
        $this->assertArrayHasKey('link', $item);
        $this->assertArrayHasKey('brand_name', $item);
        $this->assertArrayHasKey('model_name', $item);
        $this->assertArrayHasKey('official_price', $item);
        $this->assertArrayHasKey('unofficial_price', $item);
        $this->assertArrayHasKey('is_official', $item);
        $this->assertArrayHasKey('status', $item);
        $this->assertArrayHasKey('release_date', $item);
        $this->assertArrayHasKey('specifications', $item);
        $this->assertArrayHasKey('images', $item);
        $this->assertArrayHasKey('source_key', $item);
        $this->assertArrayHasKey('source_name', $item);
        $this->assertArrayHasKey('type', $item);
        $this->assertArrayHasKey('lang', $item);
        $this->assertArrayHasKey('extracted_at', $item);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Build a MobileDokan-style detail page HTML.
     */
    protected function detailHtml(string $title, float $price, array $specs = []): string
    {
        $priceStr = '৳'.number_format($price, 0, '.', ',');
        if (empty($specs)) {
            $specs = [['key' => 'RAM', 'value' => '8GB'], ['key' => 'Storage', 'value' => '128GB']];
        }

        $specRows = '';
        foreach ($specs as $spec) {
            $specRows .= "<tr><th>{$spec['key']}</th><td>{$spec['value']}</td></tr>";
        }

        return <<<HTML
<html><body>
  <h1 class="product_title">$title</h1>
  <div class="product-info-price"><span class="price">$priceStr</span></div>
  <table class="shop_attributes"><tbody>$specRows</tbody></table>
  <div class="images"><img src="https://www.mobiledokan.co/wp-content/uploads/phone-1.jpg" /></div>
</body></html>
HTML;
    }

    private function recursiveDelete(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir.'/*') ?: [] as $file) {
            if (is_dir($file)) {
                $this->recursiveDelete($file);
            } else {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
}
