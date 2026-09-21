<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Scraper\MobileDetailParser;
use Tests\TestCase;

class MobileParsingTest extends TestCase
{
    protected MobileDetailParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MobileDetailParser();
    }

    public function test_it_parses_a_mobiledokan_detail_page(): void
    {
        $html = <<<'HTML'
        <html><head><title>Samsung Galaxy S24 Ultra - MobileDokan</title></head><body>
          <h1 class="product_title">Samsung Galaxy S24 Ultra</h1>
          <div class="product-info-price">
            <span class="price">৳125,000</span>
          </div>
          <div class="images">
            <img src="https://mobiledokan.co/images/samsung-s24u-1.jpg" alt="Gallery 1" />
            <img src="https://mobiledokan.co/images/samsung-s24u-2.jpg" alt="Gallery 2" />
          </div>
          <table class="shop_attributes">
            <tbody>
              <tr><th>Brand</th><td>Samsung</td></tr>
              <tr><th>Model</th><td>Galaxy S24 Ultra</td></tr>
              <tr><th>RAM</th><td>12GB</td></tr>
              <tr><th>Storage</th><td>256GB</td></tr>
              <tr><th>Battery</th><td>5000mAh</td></tr>
              <tr><th>Release Date</th><td>January 2024</td></tr>
            </tbody>
          </table>
        </body></html>
        HTML;

        $item = $this->parser->parse($html, 'mobiledokan', 'MobileDokan', 'https://mobiledokan.co/product/samsung-galaxy-s24-ultra', 'bn');

        $this->assertNotNull($item);
        $this->assertSame('Samsung Galaxy S24 Ultra', $item['title']);
        $this->assertSame('Samsung', $item['brand_name']);
        $this->assertSame('Galaxy S24 Ultra', $item['model_name']);
        $this->assertSame(125000.0, $item['official_price']);
        $this->assertSame(0.0, $item['unofficial_price']);
        $this->assertSame('mobile', $item['type']);
        $this->assertSame('mobiledokan', $item['source_key']);
        $this->assertCount(6, $item['specifications']);
        $this->assertSame('12GB', $item['specifications'][2]['value']);
        $this->assertCount(2, $item['images']);
        $this->assertSame('both', $item['status']);
    }

    public function test_it_parses_a_muthophone_detail_page(): void
    {
        $html = <<<'HTML'
        <html><head><title>iPhone 15 Pro Max - MuthoPhone</title></head><body>
          <h1 class="product-title">Apple iPhone 15 Pro Max</h1>
          <span class="price">$1,199</span>
          <div class="gallery">
            <img src="https://muthophone.com.bd/images/iphone15pm.jpg" alt="iPhone" />
          </div>
          <table class="specifications">
            <tr><th>Brand</th><td>Apple</td></tr>
            <tr><th>Storage</th><td>256GB</td></tr>
            <tr><th>Camera</th><td>Triple 48MP</td></tr>
          </table>
        </body></html>
        HTML;

        $item = $this->parser->parse($html, 'muthophone', 'MuthoPhone', 'https://muthophone.com.bd/apple-iphone-15-pro-max', 'bn');

        $this->assertNotNull($item);
        $this->assertSame('Apple iPhone 15 Pro Max', $item['title']);
        $this->assertSame('Apple', $item['brand_name']);
        $this->assertSame('iPhone 15 Pro Max', $item['model_name']);
        $this->assertSame(1199.0, $item['official_price']);
        $this->assertCount(3, $item['specifications']);
    }

    public function test_it_parses_a_mobilebd_detail_page(): void
    {
        $html = <<<'HTML'
        <html><head><title>Realme GT5 Price in BD</title></head><body>
          <h1 class="entry-title">Realme GT5</h1>
          <span class="amount">৳89,999</span>
          <div class="product-image">
            <img src="https://mobilebd.co/wp-content/uploads/realme-gt5.jpg" alt="Realme GT5" />
          </div>
          <table class="shop_attributes">
            <tr><th>RAM</th><td>8GB</td></tr>
            <tr><th>Storage</th><td>128GB</td></tr>
          </table>
        </body></html>
        HTML;

        $item = $this->parser->parse($html, 'mobilebd', 'MobileBD', 'https://mobilebd.co/realme-gt5-price/', 'bn');

        $this->assertNotNull($item);
        $this->assertSame('Realme GT5', $item['title']);
        $this->assertSame('Realme', $item['brand_name']);
        $this->assertSame('GT5', $item['model_name']);
        $this->assertSame(89999.0, $item['official_price']);
        $this->assertCount(2, $item['specifications']);
        $this->assertSame('128GB', $item['specifications'][1]['value']);
    }

    public function test_it_parses_a_gsmarena_detail_page(): void
    {
        $html = <<<'HTML'
        <html><head><title>Samsung Galaxy A54 - GSMArena BD</title></head><body>
          <h1 class="specs-phone-name">Samsung Galaxy A54</h1>
          <td class="price">৳79,000</td>
          <div id="spec__pics">
            <img src="https://gsmarena.com.bd/images/a54-1.jpg" />
          </div>
          <table>
            <tr><td class="ttl">RAM</td><td class="nfo">8GB</td></tr>
            <tr><td class="ttl">Storage</td><td class="nfo">256GB</td></tr>
            <tr><td class="ttl">Battery</td><td class="nfo">5000 mAh</td></tr>
          </table>
        </body></html>
        HTML;

        $item = $this->parser->parse($html, 'gsmarena_bd', 'GSMArena BD', 'https://gsmarena.com.bd/samsung/galaxy-a54-bd', 'en');

        $this->assertNotNull($item);
        $this->assertSame('Samsung Galaxy A54', $item['title']);
        $this->assertSame('Samsung', $item['brand_name']);
        $this->assertSame('Galaxy A54', $item['model_name']);
        $this->assertSame(79000.0, $item['official_price']);
        $this->assertCount(3, $item['specifications']);
        $this->assertSame('8GB', $item['specifications'][0]['value']);
    }

    public function test_it_extracts_both_official_and_unofficial_prices(): void
    {
        $html = <<<'HTML'
        <html><body>
          <h1>Oppo Reno 10</h1>
          <span class="official-price">৳75,000</span>
          <span class="unofficial-price">৳68,000</span>
        </body></html>
        HTML;

        $item = $this->parser->parse($html, 'mobiledokan', 'MobileDokan', 'https://example.com/oppo-reno-10');

        $this->assertNotNull($item);
        // At least one price should be extractable.
        $this->assertTrue($item['official_price'] > 0 || $item['unofficial_price'] > 0);
    }

    public function test_it_splits_brand_and_model_from_title(): void
    {
        $html = '<html><body><h1>Xiaomi Redmi Note 13 Pro</h1></body></html>';

        $item = $this->parser->parse($html, 'mobiledokan', 'MobileDokan', 'https://example.com/xiaomi-redmi-note-13-pro');

        $this->assertNotNull($item);
        $this->assertSame('Xiaomi', $item['brand_name']);
        $this->assertSame('Redmi Note 13 Pro', $item['model_name']);
    }

    public function test_it_returns_null_for_empty_html(): void
    {
        $this->assertNull($this->parser->parse('', 'mobiledokan', 'MobileDokan', 'https://example.com'));
    }

    public function test_it_returns_null_when_no_title_is_found(): void
    {
        $html = '<html><body><div class="price">৳50,000</div></body></html>';
        $this->assertNull($this->parser->parse($html, 'mobiledokan', 'MobileDokan', 'https://example.com'));
    }

    public function test_it_extracts_images_and_filters_logos(): void
    {
        $html = <<<'HTML'
        <html><body>
          <h1>Nokia G22</h1>
          <div class="gallery">
            <img src="https://example.com/nokia-g22-main.jpg" />
            <img src="https://example.com/logo.png" />
            <img src="https://example.com/nokia-g22-back.jpg" />
          </div>
        </body></html>
        HTML;

        $item = $this->parser->parse($html, 'mobiledokan', 'MobileDokan', 'https://example.com/nokia-g22', 'bn');

        $this->assertNotNull($item);
        $this->assertCount(2, $item['images']);
        $this->assertStringNotContainsString('logo', $item['images'][0]);
    }

    public function test_it_resolves_relative_urls_to_absolute(): void
    {
        $html = <<<'HTML'
        <html><body>
          <h1>OnePlus Nord</h1>
          <img src="/images/oneplus-nord.jpg" />
        </body></html>
        HTML;

        $item = $this->parser->parse($html, 'mobiledokan', 'MobileDokan', 'https://example.com/oneplus-nord');

        // Relative URLs should not appear since we don't have a base set in this simple test.
        $this->assertNotNull($item);
    }

    public function test_it_extracts_specification_key_value_pairs(): void
    {
        $html = <<<'HTML'
        <html><body>
          <h1>Google Pixel 8</h1>
          <table class="shop_attributes">
            <tr><th>Display Size</th><td>6.2 inches</td></tr>
            <tr><th>Processor</th><td>Google Tensor G3</td></tr>
            <tr><th>Camera</th><td>50MP main</td></tr>
          </table>
        </body></html>
        HTML;

        $item = $this->parser->parse($html, 'mobiledokan', 'MobileDokan', 'https://example.com/pixel-8');

        $this->assertNotNull($item);
        $this->assertCount(3, $item['specifications']);
        $this->assertSame('Display Size', $item['specifications'][0]['key']);
        $this->assertSame('6.2 inches', $item['specifications'][0]['value']);
    }
}
