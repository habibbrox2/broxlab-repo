<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Scraper\FeedParser;
use App\Support\Scraper\HtmlListingExtractor;
use App\Support\Scraper\ScraperClient;
use Tests\TestCase;

class ScraperParsingTest extends TestCase
{
    public function test_it_parses_an_rss_feed(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0">
          <channel>
            <title>Example News</title>
            <item>
              <title>Big story one</title>
              <link>https://example.com/news/big-story-one</link>
              <description><![CDATA[<p>A short summary.</p>]]></description>
              <pubDate>Mon, 21 Sep 2026 08:00:00 GMT</pubDate>
              <guid>https://example.com/news/big-story-one</guid>
            </item>
            <item>
              <title>Second story</title>
              <link>https://example.com/news/second-story</link>
              <pubDate>Sun, 20 Sep 2026 08:00:00 GMT</pubDate>
            </item>
          </channel>
        </rss>
        XML;

        $items = (new FeedParser())->parse($xml, 'https://example.com', new ScraperClient(['delay_ms' => 0]));

        $this->assertCount(2, $items);
        $this->assertSame('Big story one', $items[0]['title']);
        $this->assertSame('https://example.com/news/big-story-one', $items[0]['link']);
        $this->assertSame('A short summary.', $items[0]['summary']);
        $this->assertNotNull($items[0]['published_at']);
    }

    public function test_it_parses_an_atom_feed_and_resolves_links(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <feed xmlns="http://www.w3.org/2005/Atom">
          <entry>
            <title>Atom story</title>
            <link rel="alternate" href="/articles/atom-story"/>
            <summary>Atom summary</summary>
            <updated>2026-09-21T09:00:00Z</updated>
            <id>tag:example.com,2026:1</id>
          </entry>
        </feed>
        XML;

        $items = (new FeedParser())->parse($xml, 'https://example.com/blog', new ScraperClient(['delay_ms' => 0]));

        $this->assertCount(1, $items);
        $this->assertSame('https://example.com/articles/atom-story', $items[0]['link']);
        $this->assertSame('Atom summary', $items[0]['summary']);
    }

    public function test_it_rejects_non_xml_input(): void
    {
        $this->assertSame([], (new FeedParser())->parse('<html><body>not a feed</body></html>'));
    }

    public function test_html_listing_extractor_keeps_article_links_and_drops_nav(): void
    {
        $html = <<<'HTML'
        <html><body>
          <nav>
            <a href="/">Home</a>
            <a href="/contact">Contact</a>
            <a href="/login">Login</a>
          </nav>
          <main>
            <article>
              <a href="/news/2026/09/21/bangladesh-tech-growth-report">Bangladesh tech sector posts record growth this quarter</a>
              <time datetime="2026-09-21T10:00:00Z"></time>
            </article>
            <a href="/news/mobile-phone-prices-drop-ahead-of-festival">Mobile phone prices drop ahead of the festival season</a>
          </main>
        </body></html>
        HTML;

        $items = (new HtmlListingExtractor(new ScraperClient(['delay_ms' => 0])))
            ->extract($html, 'https://example.com');

        $links = array_column($items, 'link');

        $this->assertContains('https://example.com/news/2026/09/21/bangladesh-tech-growth-report', $links);
        $this->assertContains('https://example.com/news/mobile-phone-prices-drop-ahead-of-festival', $links);
        $this->assertNotContains('https://example.com/contact', $links);
        $this->assertNotContains('https://example.com/login', $links);
    }

    public function test_html_listing_extractor_accepts_id_style_slugs(): void
    {
        // bdnews24-style URLs: /section/<10-char-id> with the headline as text.
        $html = <<<'HTML'
        <html><body>
          <a href="/technology/zy6rdlvkhx">Dark web feud erupts as notorious group claims rival site hacked</a>
          <a href="/opinion/features-analysis">Features and Analysis</a>
          <a href="/technology">Technology</a>
        </body></html>
        HTML;

        $items = (new HtmlListingExtractor(new ScraperClient(['delay_ms' => 0])))
            ->extract($html, 'https://bdnews24.com');

        $links = array_column($items, 'link');

        $this->assertContains('https://bdnews24.com/technology/zy6rdlvkhx', $links);
        $this->assertNotContains('https://bdnews24.com/opinion/features-analysis', $links);
        $this->assertNotContains('https://bdnews24.com/technology', $links);
    }

    public function test_html_listing_extractor_uses_the_card_heading_as_the_title(): void
    {
        // Card links wrap the headline AND the excerpt; the <h2> is the title.
        $html = <<<'HTML'
        <html><body>
          <a href="/technology/zy6rdlvkhx">
            <div>
              <h2>Dark web feud erupts as notorious group claims rival site hijack</h2>
              <p>The rival gangs have traded threats to expose each other's members.</p>
            </div>
          </a>
        </body></html>
        HTML;

        $items = (new HtmlListingExtractor(new ScraperClient(['delay_ms' => 0])))
            ->extract($html, 'https://bdnews24.com');

        $this->assertCount(1, $items);
        $this->assertSame('Dark web feud erupts as notorious group claims rival site hijack', $items[0]['title']);
    }
}
