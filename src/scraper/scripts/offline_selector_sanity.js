/**
 * Offline selector sanity checks (no network).
 *
 * Run:
 *   node src/scraper/scripts/offline_selector_sanity.js
 */

import HtmlParser from '../utils/HtmlParser.js';
import TickerScraper from '../agents/TickerScraper.js';
import ArticleScraper from '../agents/ArticleScraper.js';

const assert = (condition, message) => {
    if (!condition) {
        throw new Error(message);
    }
};

const ittefaqListHtml = `
<div class="contents_listing widget">
  <div id="contents_476_ajax_container" class="contents summery_view">
    <div class="row">
      <div class="col col1">
        <div class="each col_in">
          <div class="info has_ai">
            <div class="title_holder">
              <div class="tag_title_holder">
                <h2 class="title">
                  <a class="link_overlay" title="সিরিয়ায় ইসরায়েলি বিমান হামলাকে ‘নগ্ন আগ্রাসন’ বললো সৌদি আরব" href="//www.ittefaq.com.bd/780504/test-slug">সিরিয়ায় ইসরায়েলি বিমান হামলাকে ‘নগ্ন আগ্রাসন’ বললো সৌদি আরব</a>
                </h2>
              </div>
            </div>
            <div class="additional">
              <span class="time aitm" data-published="2026-03-21T10:18:32+06:00">৭ মিনিট আগে</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
`;

const ittefaqArticleHtml = `
<div class="content_detail_left">
    <div itemscope itemtype="http://schema.org/NewsArticle" class="content_detail detail">
      <h1 itemprop="headline" class="title mb10">সিরিয়ায় ইসরায়েলি বিমান হামলাকে ‘নগ্ন আগ্রাসন’ বললো সৌদি আরব</h1>
    <meta itemprop="image" content="https://cdn.ittefaqbd.com/contents/cache/images/800x450x1/uploads/media/2026/03/21/example.jpg">
    <div class="additional_info_container">
      <div class="author no_propic"><span class="name">ইত্তেফাক ডিজিটাল ডেস্ক</span></div>
      <div class="time">
        <span class="tts_time" itemprop="datePublished" content="2026-03-21T10:18:32+06:00">প্রকাশ : ২১ মার্চ ২০২৬, ১০:১৮</span>
      </div>
    </div>
    <article class="jw_detail_content_holder content">
      <div itemprop="articleBody" class="viewport jw_article_body">
        <p>প্রথম প্যারাগ্রাফ—এটি কন্টেন্ট এক্সট্রাকশন টেস্টের জন্য যথেষ্ট লম্বা।</p>
        <p>দ্বিতীয় প্যারাগ্রাফ—এটিও ২০ অক্ষরের বেশি, তাই ফিল্টার হবে না।</p>
      </div>
    </article>
  </div>
</div>
`;

// --- Ittefaq list ---
{
    const $ = HtmlParser.parse(ittefaqListHtml);
    assert($, 'Failed to parse Ittefaq list HTML');

    const scraper = new TickerScraper('ittefaq');
    const items = scraper.extractTickerLinks($);

    assert(items.length > 0, 'Ittefaq ticker extraction returned 0 items');
    assert(items[0].link.includes('ittefaq.com.bd/780504'), 'Ittefaq first item link looks wrong');
    assert(items[0].title.includes('সিরিয়ায়'), 'Ittefaq first item title looks wrong');
}

// --- Ittefaq article ---
{
    const $ = HtmlParser.parse(ittefaqArticleHtml);
    assert($, 'Failed to parse Ittefaq article HTML');

    const scraper = new ArticleScraper('ittefaq');

    const title = scraper.extractTitle($);
    const author = scraper.extractAuthor($);
    const published = scraper.extractPublishedDate($);
    const image = scraper.extractImage($);
    const content = scraper.extractContent($);

    assert(title.includes('সিরিয়ায়'), 'Ittefaq article title extraction failed');
    assert(author.includes('ইত্তেফাক'), 'Ittefaq article author extraction failed');
    assert(!!published, 'Ittefaq article published_at is null');
    assert(new Date(published).toISOString() === '2026-03-21T04:18:32.000Z', 'Ittefaq article published_at parse mismatch');
    assert(image.includes('/uploads/'), 'Ittefaq article image extraction failed');
    assert(content.includes('প্রথম প্যারাগ্রাফ'), 'Ittefaq article content extraction failed');
}

console.log('OK: offline selector sanity checks passed');
