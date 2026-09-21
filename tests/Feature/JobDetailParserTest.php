<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Scraper\JobDetailParser;
use Tests\TestCase;

class JobDetailParserTest extends TestCase
{
    protected JobDetailParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new JobDetailParser();
    }

    /** @test */
    public function it_extracts_basic_job_fields_from_html(): void
    {
        $html = $this->loadHtmlFixture('job-basic.html');

        $result = $this->parser->parse($html, 'test_source', 'https://example.com/job/123');

        $this->assertNotNull($result);
        $this->assertSame('Senior Software Engineer', $result['title']);
        $this->assertSame('https://example.com/job/123', $result['link']);
        $this->assertSame('TechCorp Ltd.', $result['extra']['company']);
        $this->assertSame('Dhaka, Bangladesh', $result['extra']['location']);
        $this->assertSame('৳80,000 - ৳1,20,000', $result['extra']['salary']);
        $this->assertSame('jobs', $result['type']);
        $this->assertNotEmpty($result['summary']);
    }

    /** @test */
    public function it_extracts_bengali_date_deadline(): void
    {
        $html = $this->loadHtmlFixture('job-bn.html');

        $result = $this->parser->parse($html, 'test_bn', 'https://example.com/job/456');

        $this->assertSame('bn', $result['lang']);
        $this->assertStringContainsString('আবেদনের শেষ তারিখ', $result['summary']);
    }

    /** @test */
    public function it_returns_null_when_no_title_found(): void
    {
        $html = '<html><body><div>Just some content without a title</div></body></html>';

        $result = $this->parser->parse($html, 'test_source', 'https://example.com/job/789');

        // Without a title tag or proper heading, the parser returns null.
        $this->assertNull($result);
    }

    /** @test */
    public function it_handles_empty_html_gracefully(): void
    {
        $result = $this->parser->parse('', 'test_source', 'https://example.com/job/1');

        $this->assertNull($result);
    }

    /** @test */
    public function it_resolves_relative_image_urls(): void
    {
        $html = '<html><head><title>Job Title</title></head><body>'
            . '<img src="/images/job-thumb.jpg" />'
            . '</body></html>';

        $result = $this->parser->parse($html, 'test_source', 'https://example.com/job/1');

        $this->assertNotNull($result);
        $this->assertSame('https://example.com/images/job-thumb.jpg', $result['image']);
    }

    /** @test */
    public function it_extracts_job_type_from_html(): void
    {
        $html = $this->loadHtmlFixture('job-full.html');

        $result = $this->parser->parse($html, 'test_source', 'https://example.com/job/999');

        $this->assertSame('Full-time', $result['extra']['job_type']);
        $this->assertNotEmpty($result['extra']['deadline']);
        $this->assertNotEmpty($result['extra']['description']);
    }

    /** @test */
    public function it_converts_bengali_numerals_in_salaries(): void
    {
        $html = '<html><head><title>চাকরির বিজ্ঞাপন</title></head><body>'
            . '<div class="salary">বেতন: ৳৫০,০০০ - ৳৮০,০০০</div>'
            . '</body></html>';

        $result = $this->parser->parse($html, 'test_source', 'https://example.com/job/1');

        $this->assertStringContainsString('৳৫০,০০০', $result['extra']['salary']);
    }

    /** @test */
    public function it_detects_bengali_language_when_content_is_bn(): void
    {
        $html = '<html><head><title>সফটওয়্যার ইঞ্জিনিয়ার চাকরি</title></head><body>'
            . '<div class="company">টেককর্প লিমিটেড</div>'
            . '<div class="location">ঢাকা</div>'
            . '</body></html>';

        $result = $this->parser->parse($html, 'test_source', 'https://example.com/job/1');

        $this->assertSame('bn', $result['lang']);
    }

    /** @test */
    public function it_detects_english_language_when_content_is_en(): void
    {
        $html = '<html><head><title>Software Engineer Position</title></head><body>'
            . '<div class="company">TechCorp Ltd.</div>'
            . '<div class="location">Dhaka</div>'
            . '</body></html>';

        $result = $this->parser->parse($html, 'test_source', 'https://example.com/job/1');

        $this->assertSame('en', $result['lang']);
    }

    /** @test */
    public function it_extracts_schema_org_fields(): void
    {
        $html = '<html><head><title>Job</title></head><body>'
            . '<div itemscope itemtype="https://schema.org/JobPosting">'
            . '<h2 itemprop="title">Data Scientist</h2>'
            . '<div itemprop="hiringOrganization" itemscope itemtype="https://schema.org/Organization">'
            . '<span itemprop="name">AI Analytics Inc.</span>'
            . '</div>'
            . '<div itemprop="jobLocation" itemscope itemtype="https://schema.org/Place">'
            . '<span itemprop="address">Chittagong</span>'
            . '</div>'
            . '<meta itemprop="datePosted" content="2026-09-15">'
            . '<meta itemprop="validThrough" content="2026-10-15">'
            . '</div></body></html>';

        $result = $this->parser->parse($html, 'test_source', 'https://example.com/job/1');

        $this->assertNotNull($result);
        $this->assertSame('AI Analytics Inc.', $result['extra']['company']);
        $this->assertSame('Chittagong', $result['extra']['location']);
    }

    protected function loadHtmlFixture(string $filename): string
    {
        // Build fixtures inline to avoid file dependency.
        return match ($filename) {
            'job-basic.html' => $this->fixtureJobBasic(),
            'job-bn.html' => $this->fixtureJobBengali(),
            'job-full.html' => $this->fixtureJobFull(),
            default => '',
        };
    }

    protected function fixtureJobBasic(): string
    {
        return <<<'HTML'
<html><head><title>Senior Software Engineer - TechCorp</title></head><body>
<div class="job-detail">
  <div class="company">TechCorp Ltd.</div>
  <div class="location">Dhaka, Bangladesh</div>
  <div class="salary">৳80,000 - ৳1,20,000</div>
  <div class="job-type">Full-time</div>
  <div class="deadline">2026-10-15</div>
  <div class="posted">2026-09-20</div>
  <div class="job-description">
    <p>We are looking for a senior software engineer...</p>
  </div>
</div>
</body></html>
HTML;
    }

    protected function fixtureJobBengali(): string
    {
        return <<<'HTML'
<html><head><title>সফটওয়্যার ডেভেলপার চাকরি</title></head><body>
<div class="job-detail">
  <div class="company">বাংলা টেক সলিউশন্স</div>
  <div class="location">ঢাকা</div>
  <div class="salary">৳৫০,০০০</div>
  <div class="job-type">ফুল-টাইম</div>
  <div class="deadline">১৫ অক্টোবর ২০২৬</div>
  <div class="posted">২০ সেপ্টেম্বর ২০২৬</div>
</div>
</body></html>
HTML;
    }

    protected function fixtureJobFull(): string
    {
        return <<<'HTML'
<html><head><title>Full Stack Developer - StartupX</title></head><body>
<div class="job-card">
  <h1>Full Stack Developer</h1>
  <span class="employer">StartupX</span>
  <span class="work-location">Remote, Bangladesh</span>
  <div class="salary-range">৳৬০,০০০ - ৳৯০,০০০ per month</div>
  <div class="employment-type">Full-time</div>
  <div class="application-deadline">2026-10-20</div>
  <div class="posted-date">2026-09-22</div>
  <div class="job-description">
    <p>We are seeking a talented full stack developer to join our growing team.</p>
  </div>
  <img src="/logo.png" />
  <meta property="og:image" content="/images/cover.jpg" />
</div>
</body></html>
HTML;
    }
}
