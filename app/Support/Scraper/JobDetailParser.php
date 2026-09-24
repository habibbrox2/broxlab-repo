<?php

declare(strict_types=1);

namespace App\Support\Scraper;

use DOMDocument;
use DOMXPath;

/**
 * Extracts structured job data from individual job listing pages.
 *
 * Supported sites: jobstationbd.com, workfully.com.bd, bongobdjobs,
 * jobsbangladesh.com — all share a similar card/metadata layout:
 *
 *   - Job title (h1 / page-title)
 *   - Company name (.company, .employer, [itemprop= hiringOrganization])
 *   - Location (.location, [itemprop= jobLocation])
 *   - Salary range (when present)
 *   - Application deadline / posted date
 *   - Job type (full-time, part-time, contract…)
 *   - Full description (stripped from the job-detail body)
 *
 * The parser degrades gracefully — if a field can't be found it's omitted
 * from the returned summary rather than producing null noise.
 */
class JobDetailParser
{
    /** @var string Base URL used for relative→absolute resolution. */
    protected string $baseUrl = '';

    /**
     * @param  string  $html
     * @param  string  $sourceKey
     * @param  string  $url
     * @return array<string, mixed>|null  Normalized job item, or null if the page lacks a title.
     */
    public function parse(string $html, string $sourceKey, string $url): ?array
    {
        if (trim($html) === '') {
            return null;
        }

        $this->baseUrl = $url;
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $doc->documentElement) {
            return null;
        }

        $xpath = new DOMXPath($doc);

        $title = $this->extractTitle($xpath) ?: null;
        if ($title === null || $title === '') {
            return null;
        }

        $company = $this->extractField($xpath, [
            // bdjobstoday: "Organization Information" row holds the org name.
            '//h1[1]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " company ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " employer ")]',
            '//*[@itemprop="hiringOrganization"]',
            '//*[contains(text(), "কোম্পানি") or contains(text(), "Company")]/following-sibling::*[1]',
            '//*[contains(text(), "কোম্পানি") or contains(text(), "Company")]/parent::*/following-sibling::*[1]',
        ]);

        $location = $this->extractField($xpath, [
            '//*[contains(concat(" ", normalize-space(@class), " "), " location ")]',
            '//*[@itemprop="jobLocation"]',
            '//*[contains(text(), "লোকেশন") or contains(text(), "Location")]/following-sibling::*[1]',
        ]);

        $salary = $this->extractField($xpath, [
            '//*[contains(concat(" ", normalize-space(@class), " "), " salary ")]',
            '//*[contains(text(), "বেতন") or contains(text(), "Salary")]/following-sibling::*[1]',
            '//*[contains(text(), "বেতন") or contains(text(), "Salary")]/parent::*/following-sibling::*[1]',
        ]);

        $deadline = $this->extractDate($xpath, [
            // bdjobstoday: "Application Deadline: 01 Oct, 2026" inside a span.
            "//span[contains(text(), 'Application Deadline')]",
            '//td[contains(text(), "Application Deadline")]',
            '//*[contains(text(), "আবেদনের শেষ তারিখ") or contains(text(), "Application Deadline")]/following::*[1]',
            '//*[contains(text(), "ডেডলাইন") or contains(text(), "Deadline")]/following::*[1]',
            '//*[@itemprop="validThrough"]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " deadline ")]',
            '//*[contains(@class, "deadline")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " expiry ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " due-date ")]',
        ]);

        $posted = $this->extractDate($xpath, [
            // bdjobstoday: "Published On: 18 Sep, 2026" inside a span.
            "//span[contains(text(), 'Published On')]",
            '//td[contains(text(), "Published On")]',
            '//*[contains(text(), "প্রকাশিত") or contains(text(), "Posted") or contains(text(), "পোস্টেড")]/following::*[1]',
            '//*[@itemprop="datePosted"]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " posted ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " publish-date ")]',
        ]);

        $jobType = $this->extractField($xpath, [
            '//*[contains(concat(" ", normalize-space(@class), " "), " job-type ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " employment-type ")]',
            '//*[contains(text(), "এমপ্লয়োয়্গের ধরন") or contains(text(), "Employment Type")]/following-sibling::*[1]',
        ]);

        $description = $this->extractDescription($xpath);
        $image = $this->extractImage($xpath);

        $summaryParts = [];
        if ($company !== null) {
            $summaryParts[] = "কোম্পানি: {$company}";
        }
        if ($location !== null) {
            $summaryParts[] = "অবস্থান: {$location}";
        }
        if ($salary !== null) {
            $summaryParts[] = "বেতন: {$salary}";
        }
        if ($jobType !== null) {
            $summaryParts[] = "ধরণ: {$jobType}";
        }
        if ($deadline !== null) {
            $summaryParts[] = "আবেদনের শেষ তারিখ: {$deadline}";
        }
        if ($posted !== null) {
            $summaryParts[] = "প্রকাশনা তারিখ: {$posted}";
        }
        if ($description !== null && $description !== '') {
            $summaryParts[] = "বিস্তারিত: {$description}";
        }

        return [
            'title' => $title,
            'link' => $url,
            'summary' => $summaryParts !== [] ? implode(' | ', $summaryParts) : null,
            'published_at' => $this->normalizeDate($posted),
            'guid' => $url,
            'image' => $image,
            'source_key' => $sourceKey,
            'type' => 'jobs',
            'lang' => $this->detectLanguage($title, $company, $location, $description),
            'extracted_at' => gmdate('c'),
            'extra' => [
                'company' => $company,
                'location' => $location,
                'salary' => $salary,
                'deadline' => $deadline,
                'posted_at' => $posted,
                'job_type' => $jobType,
                'description' => $description,
            ],
        ];
    }

    /** @param array<int, string> $expressions */
    protected function extractField(DOMXPath $xpath, array $expressions): ?string
    {
        foreach ($expressions as $expr) {
            $nodes = @$xpath->query($expr);
            if ($nodes !== false && $nodes->length > 0) {
                $text = trim((string) $nodes->item(0)->textContent);
                if ($text !== '') {
                    return $this->cleanText($text);
                }
            }
        }

        return null;
    }

    /** @param array<int, string> $expressions */
    protected function extractDate(DOMXPath $xpath, array $expressions): ?string
    {
        foreach ($expressions as $expr) {
            $nodes = @$xpath->query($expr);
            if ($nodes !== false && $nodes->length > 0) {
                $node = $nodes->item(0);
                // Prefer datetime attribute, then text content.
                $value = '';
                if ($node instanceof \DOMElement && ($attr = $node->getAttribute('datetime')) !== '') {
                    $value = $attr;
                }
                if ($value === '') {
                    $value = trim((string) $node->textContent);
                }
                $cleaned = $this->parseDateValue($value);
                if ($cleaned !== null) {
                    return $cleaned;
                }
            }
        }

        return null;
    }

    protected function parseDateValue(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        // Bengali numeral conversion.
        $value = $this->convertBengaliNumerals($value);

        // Try ISO / common formats first.
        $ts = strtotime($value);
        if ($ts > 0) {
            return gmdate('Y-m-d', $ts);
        }

        // Strip a label prefix ("Published On: 18 Sep, 2026" → "18 Sep, 2026")
        // and retry — strtotime chokes on the "Label:" prefix.
        $stripped = preg_replace('/^[^:\d]*:\s*/u', '', $value);
        if ($stripped !== null && $stripped !== $value) {
            $ts = strtotime(trim($stripped));
            if ($ts > 0) {
                return gmdate('Y-m-d', $ts);
            }
        }

        // Bengali month names.
        $bnMonths = [
            'জানুয়ারি' => 'January', 'ফেব্রুয়ারি' => 'February', 'মার্চ' => 'March',
            'এপ্রিল' => 'April', 'মে' => 'May', 'জুন' => 'June',
            'জুলাই' => 'July', 'আগস্ট' => 'August', 'সেপ্টেম্বর' => 'September',
            'অক্টোবর' => 'October', 'নভেম্বর' => 'November', 'ডিসেম্বর' => 'December',
            'পহেলা জানুয়ারি' => 'January',
        ];
        foreach ($bnMonths as $bn => $en) {
            if (mb_stripos($value, $bn) !== false) {
                $ts = strtotime(str_replace($bn, $en, $value));
                if ($ts > 0) {
                    return gmdate('Y-m-d', $ts);
                }
            }
        }

        return null;
    }

    /** Convert Bengali digits (০-৯ → 0-9). */
    protected function convertBengaliNumerals(string $text): string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            for ($i = 0; $i <= 9; $i++) {
                $map[mb_chr(0x09E6 + $i)] = (string) $i;
            }
        }

        return strtr($text, $map);
    }

    /** Detect whether content uses Bengali (বাংলা) or English. */
    protected function detectLanguage(?string ...$texts): string
    {
        $combined = implode(' ', array_filter($texts, fn ($t) => $t !== null && $t !== ''));
        $bnChars = preg_match_all('/[\x{0980}-\x{09FF}]/u', $combined);
        $enChars = preg_match_all('/[a-zA-Z]/', $combined);

        return $bnChars >= $enChars ? 'bn' : 'en';
    }

    protected function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    protected function extractTitle(DOMXPath $xpath): string
    {
        // <title> tag is the most reliable on job pages.
        $titleNodes = $xpath->query('//*[local-name()="title"]');
        if ($titleNodes !== false && $titleNodes->length > 0) {
            $text = trim((string) $titleNodes->item(0)->textContent);
            if ($text !== '') {
                // Strip the site suffix (e.g. " | BdJobs" or " - JobStationBD").
                $text = (string) preg_replace('/\s*[\|\-][^|\-]*$/', '', $text);

                return $this->cleanText($text);
            }
        }

        foreach (['h1', 'h2', 'h3'] as $tag) {
            $nodes = $xpath->query('//*[local-name()="' . $tag . '"]');
            if ($nodes !== false && $nodes->length > 0) {
                $text = trim((string) $nodes->item(0)->textContent);
                if ($text !== '' && mb_strlen($text) >= 15 && mb_strlen($text) <= 220) {
                    return $this->cleanText($text);
                }
            }
        }

        // Schema.org headline.
        $headlineNodes = $xpath->query('//*[@itemprop="headline"] | //*[@property="og:title"]/@content');
        if ($headlineNodes !== false && $headlineNodes->length > 0) {
            $text = trim((string) $headlineNodes->item(0)->textContent);
            if ($text !== '') {
                return $this->cleanText($text);
            }
        }

        return '';
    }

    protected function extractDescription(DOMXPath $xpath): ?string
    {
        $descriptions = [
            '//*[@itemprop="description"]//text()',
            '//*[contains(concat(" ", normalize-space(@class), " "), " job-description ")]//text()',
            '//*[contains(concat(" ", normalize-space(@class), " "), " description ")]//text()',
            '//*[contains(concat(" ", normalize-space(@class), " "), " details ")]//text()',
            '//*[contains(concat(" ", normalize-space(@class), " "), " content ")]//text()',
        ];

        foreach ($descriptions as $expr) {
            $nodes = @$xpath->query($expr);
            if ($nodes !== false && $nodes->length > 0) {
                $text = '';
                foreach ($nodes as $node) {
                    $text .= ' ' . trim((string) $node->textContent);
                }
                $text = (string) preg_replace('/\s+/u', ' ', $text);
                $text = trim($text);
                if ($text !== '' && mb_strlen($text) >= 20) {
                    return mb_substr($text, 0, 1000);
                }
            }
        }

        return null;
    }

    protected function extractImage(DOMXPath $xpath): ?string
    {
        // Company logo / featured image.
        $images = $xpath->query('//img[@src]');
        if ($images === false) {
            return null;
        }

        foreach ($images as $img) {
            if (! $img instanceof \DOMElement) {
                continue;
            }
            $src = $img->getAttribute('data-src') ?: $img->getAttribute('src');
            if ($src === '') {
                continue;
            }
            $resolved = $this->resolveUrl($src);
            if ($resolved !== '' && ! preg_match('/(logo|icon|sprite|avatar|pixel|spacer)/i', $resolved)) {
                return $resolved;
            }
        }

        // OpenGraph image.
        $ogImage = @$xpath->query('//*[@property="og:image"]/@content');
        if ($ogImage !== false && $ogImage->length > 0) {
            $resolved = $this->resolveUrl(trim((string) $ogImage->item(0)->textContent));
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return null;
    }

    protected function resolveUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // Absolute URL.
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        // Absolute path (starts with /) — resolve against domain root.
        if (str_starts_with($url, '/')) {
            $parsed = parse_url($this->baseUrl);
            return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . $url;
        }

        // Relative path.
        return rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');
    }

    /** Map a parsed date string into a normalized ISO date for published_at. */
    protected function normalizeDate(?string $date): ?string
    {
        return $date !== null ? $date : null;
    }
}
