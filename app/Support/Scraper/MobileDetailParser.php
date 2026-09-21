<?php

declare(strict_types=1);

namespace App\Support\Scraper;

use DOMDocument;
use DOMXPath;

/**
 * Parses a single mobile detail-page HTML document into structured device data.
 *
 * Each supported site (MobileDokan, MuthoPhone, MobileBD, GSMArena BD) has a
 * different HTML layout for specs and pricing, so the parse dispatches to a
 * site-specific method keyed by the source's `parser` config value.
 *
 * Returns an item shaped for the mobile pipeline:
 *
 *   [
 *     'title'          => 'Samsung Galaxy S24 Ultra',
 *     'link'           => 'https://...',
 *     'brand_name'     => 'Samsung',
 *     'model_name'     => 'Galaxy S24 Ultra',
 *     'official_price' => 125000.00,
 *     'unofficial_price'=> 115000.00,
 *     'is_official'    => 1,
 *     'status'         => 'official',
 *     'release_date'   => '2024-01-01',
 *     'specifications' => [['key' => 'RAM', 'value' => '12GB'], ...],
 *     'images'         => ['https://...' .],
 *     'source_key'     => 'mobiledokan',
 *     'source_name'    => 'MobileDokan',
 *     'type'           => 'mobile',
 *     'lang'           => 'bn',
 *   ]
 */
class MobileDetailParser
{
    /**
     * Known mobile phone brands used to split a title like
     * "Samsung Galaxy S24 Ultra" → brand="Samsung", model="Galaxy S24 Ultra".
     * Ordered so longer / multi-word brands are matched before short prefixes
     * (e.g. "Realme" before "Real").
     */
    protected ?string $currentBaseUrl = null;

    protected const BRANDS = [
        'Apple', 'Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Realme', 'OnePlus',
        'Google', 'Nokia', 'Sony', 'LG', 'Motorola', 'Huawei', 'Honor',
        'Tecno', 'Infinix', 'Transcend', 'Lava', 'Micromax', 'Itel',
        'Sharp', 'Meizu', 'Nubia', 'ZTE', 'TCL', 'Alcatel',
    ];

    /**
     * Parse a detail page into a mobile item.
     *
     * @param  string  $html       Raw HTML of the detail page.
     * @param  string  $sourceKey  The config source key (mobiledokan, etc.).
     * @param  string  $sourceName Human-readable source name.
     * @param  string  $detailUrl  Absolute URL of the detail page.
     * @param  string  $lang       Language code from the source config.
     * @return array<string, mixed>|null  Normalized mobile item or null on failure.
     */
    public function parse(
        string $html,
        string $sourceKey,
        string $sourceName,
        string $detailUrl,
        string $lang = 'bn',
    ): ?array {
        if (trim($html) === '') {
            return null;
        }

        $dom = $this->loadHtml($html);
        if ($dom === null) {
            return null;
        }

        $xpath = new DOMXPath($dom);

        switch ($sourceKey) {
            case 'mobiledokan':
                $data = $this->parseMobileDokan($xpath);
                break;
            case 'muthophone':
                $data = $this->parseMuthoPhone($xpath);
                break;
            case 'mobilebd':
                $data = $this->parseMobileBd($xpath);
                break;
            case 'gsmarena_bd':
                $data = $this->parseGsmarenaBd($xpath);
                break;
            default:
                $data = $this->parseGeneric($xpath);
        }

        if ($data === null) {
            return null;
        }

        // Merge in the common fields every item needs.
        $data['link'] = $detailUrl;
        $data['source_key'] = $sourceKey;
        $data['source_name'] = $sourceName;
        $data['type'] = 'mobile';
        $data['lang'] = $lang;

        // Derive brand / model from title when site-specific parsing didn't.
        if (empty($data['brand_name']) || empty($data['model_name'])) {
            $split = $this->splitBrandModel($data['title']);
            $data['brand_name'] = $data['brand_name'] ?: $split['brand'];
            $data['model_name'] = $data['model_name'] ?: $split['model'];
        }

        // Ensure title is populated.
        if (empty($data['title']) && ! empty($data['model_name'])) {
            $data['title'] = $this->buildTitle($data['brand_name'], $data['model_name']);
        }

        return $data;
    }

    // ── Site-specific parsers ─────────────────────────────────────────

    /**
     * MobileDokan (mobiledokan.co) — WordPress-based.
     *
     * Price is typically in a span with class "price" or "amount".
     * Specs are in a table with class "shop_attributes" or "specifications".
     * Images are in a .woocommerce-main-image or .slick-list gallery.
     */
    protected function parseMobileDokan(DOMXPath $xpath): ?array
    {
        $title = $this->text($xpath, '//h1[contains(@class,"product_title") or contains(@class,"entry-title")]//text()');
        if ($title === '') {
            $title = $this->text($xpath, '//h1');
        }

        [$official, $unofficial, $rawPriceText] = $this->extractPrices($xpath);
        $isOfficial = $this->extractIsOfficial($xpath);
        $status = $this->normalizeStatus($isOfficial);
        $releaseDate = $this->extractReleaseDate($xpath);
        $specs = $this->extractSpecTable($xpath);

        // Images — MobileDokan uses WooCommerce gallery or a main product image.
        $images = $this->extractImages($xpath, '//div[contains(@class,"woocommerce-main-image")]//img | //div[contains(@class,"images")]//img | //div[contains(@class,"gallery")]//img/@src | //img[contains(@class,"wp-post-image")][@src]');

        return $this->buildItem($title, $official, $unofficial, $isOfficial, $status, $releaseDate, $specs, $images);
    }

    /**
     * MuthoPhone (muthophone.com.bd) — custom site.
     *
     * Price in elements with class containing "price" or "amount".
     * Specs in a definition table or dl/dt/dd structure.
     * Images in a product-image or gallery container.
     */
    protected function parseMuthoPhone(DOMXPath $xpath): ?array
    {
        $title = $this->text($xpath, '//h1[contains(@class,"product-title") or contains(@class,"page-title")]//text() | //h1');
        [$official, $unofficial] = $this->extractPrices($xpath);
        $isOfficial = $this->extractIsOfficial($xpath);
        $status = $this->normalizeStatus($isOfficial);
        $releaseDate = $this->extractReleaseDate($xpath);
        $specs = $this->extractSpecTable($xpath) ?: $this->extractDlSpecs($xpath);

        $images = $this->extractImages($xpath, '//div[contains(@class,"product-image")]//img | //div[contains(@class,"gallery")]//img | //figure//img[@src]');

        return $this->buildItem($title, $official, $unofficial, $isOfficial, $status, $releaseDate, $specs, $images);
    }

    /**
     * MobileBD (mobilebd.co) — WordPress-based.
     * Similar to MobileDokan but with slightly different class names.
     */
    protected function parseMobileBd(DOMXPath $xpath): ?array
    {
        $title = $this->text($xpath, '//h1[contains(@class,"product_title") or contains(@class,"entry-title")]//text() | //h1[@class="entry-title"]');
        [$official, $unofficial] = $this->extractPrices($xpath);
        $isOfficial = $this->extractIsOfficial($xpath);
        $status = $this->normalizeStatus($isOfficial);
        $releaseDate = $this->extractReleaseDate($xpath);
        $specs = $this->extractSpecTable($xpath);

        $images = $this->extractImages($xpath, '//div[contains(@class,"product-image")]//img | //div[contains(@class,"images")]//img | //img[contains(@class,"wp-post-image")][@src] | //div[contains(@class,"gallery")]//img');

        return $this->buildItem($title, $official, $unofficial, $isOfficial, $status, $releaseDate, $specs, $images);
    }

    /**
     * GSMArena BD (gsmarena.com.bd) — GSMArena-style layout.
     *
     * Price in a span with class "price" or "amount".
     * Specs in tables with class "prices" and "spec-table" or article-body
     * with spec divs.
     * Images in a #spec__pics or .phone-gallery container.
     */
    protected function parseGsmarenaBd(DOMXPath $xpath): ?array
    {
        $title = $this->text($xpath, '//h1[contains(@class,"specs-phone-name")]//text() | //div[contains(@class,"model")]//a | //h1');

        [$official, $unofficial] = $this->extractPrices($xpath);
        $isOfficial = $this->extractIsOfficial($xpath);
        $status = $this->normalizeStatus($isOfficial);
        $releaseDate = $this->extractReleaseDate($xpath);
        $specs = $this->extractSpecTable($xpath) ?: $this->extractGsmarenaSpecs($xpath);

        $images = $this->extractImages($xpath, '//div[contains(@id,"spec__pics")]//img | //div[contains(@class,"gallery")]//img | //div[contains(@class,"phone-gallery")]//img | //div[contains(@class,"phone-pics")]//img');

        return $this->buildItem($title, $official, $unofficial, $isOfficial, $status, $releaseDate, $specs, $images);
    }

    /**
     * Generic fallback parser — tries to extract common patterns from any
     * mobile detail page markup.
     */
    protected function parseGeneric(DOMXPath $xpath): ?array
    {
        $title = $this->text($xpath, '//h1//text() | //title//text()');
        [$official, $unofficial] = $this->extractPrices($xpath);
        $isOfficial = $this->extractIsOfficial($xpath);
        $status = $this->normalizeStatus($isOfficial);
        $releaseDate = $this->extractReleaseDate($xpath);
        $specs = $this->extractSpecTable($xpath);

        $images = $this->extractImages($xpath, '//div[contains(@class,"gallery")]//img | //figure//img[@src] | //img[contains(@class,"product")]');

        return $this->buildItem($title, $official, $unofficial, $isOfficial, $status, $releaseDate, $specs, $images);
    }

    // ── Shared extraction helpers ──────────────────────────────────────

    /**
     * Extract prices from the page. Returns [officialPrice, unofficialPrice]
     * as floats (0.0 when not found).
     */
    protected function extractPrices(DOMXPath $xpath): array
    {
        $officialText = $this->text($xpath, "//*[contains(@class,'price')] | //*[contains(@class,'amount')] | //*[contains(translate(text(),'ABC','abc'),'৳') or contains(text(),'৳')][text()]");
        $unofficialText = '';

        // Look for separate official/unofficial price elements.
        $unofficialNode = $this->text($xpath, "//*[contains(@class,'unofficial-price')] | //*[contains(@class,'unofficial') and contains(text(),'৳')]");
        if ($unofficialNode !== '') {
            $unofficialText = $unofficialNode;
        }

        $official = $this->parsePrice($officialText);
        $unofficial = $this->parsePrice($unofficialText);

        return [$official, $unofficial, $officialText];
    }

    /**
     * Parse a price string like "৳125,000" or "125,000 BDT" into a float.
     */
    protected function parsePrice(string $text): float
    {
        $text = trim($text);
        if ($text === '') {
            return 0.0;
        }

        // Extract the first number with optional comma separators.
        if (preg_match('/([\d,]+\.?\d*)/', $text, $matches)) {
            $numeric = str_replace(',', '', $matches[1]);
            $value = (float) $numeric;
            return $value > 0 ? $value : 0.0;
        }

        return 0.0;
    }

    /**
     * Determine is_official from the page content.
     *
     * Some sites mark official vs. unofficial prices explicitly; we also
     * check for keywords in the page.
     */
    protected function extractIsOfficial(DOMXPath $xpath): ?int
    {
        // Direct class/ID hints.
        $hasClass = $this->text($xpath, '//*[contains(@class,"official")][text()]');
        if ($hasClass !== '') {
            return 1;
        }

        // Text-based heuristics.
        $allText = $this->allText($xpath);
        $lower = mb_strtolower($allText);

        if (mb_strpos($lower, 'আনঔফিশিয়াল') !== false || mb_strpos($lower, 'unofficial') !== false) {
            // If "unofficial" is the dominant label near prices.
            if (mb_strpos($lower, 'অফিশিয়াল') === false && mb_strpos($lower, 'official') === false) {
                return 0;
            }
        }

        if (mb_strpos($lower, 'অফিশিয়াল') !== false || mb_strpos($lower, 'official') !== false) {
            return 1;
        }

        // Default: if we found a price, assume official.
        return null;
    }

    /**
     * Normalize is_official (null/0/1) into a status string.
     */
    protected function normalizeStatus(?int $isOfficial): string
    {
        return match ($isOfficial) {
            1 => 'official',
            0 => 'unofficial',
            default => 'both',
        };
    }

    /**
     * Extract a release date from common markup patterns.
     */
    protected function extractReleaseDate(DOMXPath $xpath): ?string
    {
        // Try common class names.
        $text = $this->text($xpath, "//*[contains(@class,'release-date') or contains(@class,'launch-date') or contains(@class,'announced')]");

        if ($text !== '') {
            $parsed = $this->parseDate($text);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        // Look for date-like strings anywhere in spec tables.
        $specText = $this->allText($xpath);
        if (preg_match('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/u', $specText, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        // Month name + year pattern.
        if (preg_match('/([a-zA-Z]+|\p{Bengali}{3,15})\s*(\d{4})/u', $specText, $m)) {
            $parsed = $this->parseDate($m[0]);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Parse a date string into YYYY-MM-DD format.
     */
    protected function parseDate(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Try strtotime first.
        $ts = strtotime($text);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    /**
     * Extract specifications from an HTML table (key-value pairs in <tr>).
     * Handles both:
     *   <tr><th>Key</th><td>Value</td></tr>
     *   <tr><td>Key</td><td>Value</td></tr>
     *
     * @return array<int, array{key: string, value: string}>
     */
    protected function extractSpecTable(DOMXPath $xpath): array
    {
        $specs = [];
        $rows = $xpath->query('//table[contains(@class,"spec")]//tr | //table[contains(@class,"shop_attributes")]//tr | //table[contains(@class,"specs")]//tr | //table[contains(@class,"product")]//tr');

        if ($rows === false) {
            return [];
        }

        foreach ($rows as $row) {
            $cells = $xpath->query('.//td | .//th', $row);
            if ($cells === false || $cells->length < 2) {
                continue;
            }

            $cells = iterator_to_array($cells);
            $key = '';
            $value = '';

            // First cell is the key (th), second is the value (td).
            foreach ($cells as $i => $cell) {
                $cellText = trim($this->cleanText($cell->textContent));
                if ($cell->nodeName === 'th') {
                    $key = $cellText;
                } elseif ($i === 0) {
                    $key = $cellText;
                } else {
                    $value = $cellText;
                }
            }

            // Skip empty, rowspans that are just labels, etc.
            if ($key === '' || $value === '' || mb_strtolower($key) === mb_strtolower($value)) {
                continue;
            }

            // Skip generic headers.
            $lower = mb_strtolower($key);
            if (in_array($lower, ['price', 'specifications', 'details', 'general'], true)) {
                continue;
            }

            $specs[] = ['key' => $key, 'value' => $value];
        }

        return $specs;
    }

    /**
     * Extract specs from a dl/dt/dd structure (used by some sites).
     *
     * @return array<int, array{key: string, value: string}>
     */
    protected function extractDlSpecs(DOMXPath $xpath): array
    {
        $specs = [];
        $dts = $xpath->query('//dl//dt | //div[contains(@class,"spec-item")]');

        if ($dts === false) {
            return [];
        }

        foreach ($dts as $dt) {
            $key = trim($this->cleanText($dt->textContent));
            if ($key === '') {
                continue;
            }

            // Get the next sibling's text as the value.
            $next = $dt->nextSibling;
            while ($next !== null && ! ($next instanceof \DOMElement)) {
                $next = $next->nextSibling;
            }

            $value = $next !== null ? trim($this->cleanText($next->textContent)) : $this->getAttributeAfterLabel($dt, $key, $xpath);

            if ($value !== '') {
                $specs[] = ['key' => $key, 'value' => $value];
            }
        }

        return $specs;
    }

    /**
     * Fallback: search the whole document for a value associated with a label text.
     */
    protected function getAttributeAfterLabel(\DOMNode $node, string $label, DOMXPath $xpath): string
    {
        // Look for a following sibling that's a dd or div with text.
        $sibling = $xpath->evaluate('string(following-sibling::*[1])', $node);
        return trim($sibling);
    }

    /**
     * GSMArena-style: specs are in <div class="specs-item"> with a
     * "Key: Value" text format, or in tables with thead sections.
     */
    protected function extractGsmarenaSpecs(DOMXPath $xpath): array
    {
        $specs = [];

        // Pattern: <td class="ttl">Key</td><td class="nfo">Value</td>
        $ttls = $xpath->query('//td[contains(@class,"ttl")]');
        if ($ttls !== false && $ttls->length > 0) {
            foreach ($ttls as $ttl) {
                $key = trim($this->cleanText($ttl->textContent));
                if ($key === '') {
                    continue;
                }
                $next = $ttl->nextSibling;
                while ($next !== null && ! ($next instanceof \DOMElement)) {
                    $next = $next->nextSibling;
                }
                $value = $next !== null ? trim($this->cleanText($next->textContent)) : '';
                if ($value !== '' && mb_strtolower($key) !== mb_strtolower($value)) {
                    $specs[] = ['key' => $key, 'value' => $value];
                }
            }
        }

        // Pattern: spec-item divs with "Key: Value" format.
        if (empty($specs)) {
            $items = $xpath->query('//div[contains(@class,"specs-item")] | //li[contains(@class,"spec-item")]');
            if ($items !== false) {
                foreach ($items as $item) {
                    $text = trim($this->cleanText($item->textContent));
                    if (preg_match('/^([^:]+):\s*(.+)$/u', $text, $m)) {
                        $specs[] = ['key' => $m[1], 'value' => $m[2]];
                    }
                }
            }
        }

        return $specs;
    }

    /**
     * Extract image URLs from nodes matching the given XPath expressions.
     * Resolves relative URLs to absolute using the page's base URL.
     *
     * @return array<int, string>
     */
    protected function extractImages(DOMXPath $xpath, string $expression): array
    {
        $images = [];
        $nodes = $xpath->query('(' . $expression . ')');

        if ($nodes === false) {
            return [];
        }

        $baseUrl = $this->currentBaseUrl ?? '';
        $seen = [];

        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                // For attribute results (e.g. /@src), handle directly.
                if (property_exists($node, 'nodeValue') || $node instanceof \DOMAttr) {
                    $src = trim((string) $node->nodeValue);
                } else {
                    continue;
                }
            } else {
                $src = trim($node->getAttribute('src') ?: $node->getAttribute('data-src') ?: $node->getAttribute('data-original'));
            }

            if ($src === '' || isset($seen[$src])) {
                continue;
            }

            // Resolve relative URLs.
            if ($baseUrl !== '' && ! preg_match('#^https?://#i', $src)) {
                $src = $this->resolveUrl($src, $baseUrl);
            }

            // Skip tiny images / placeholders.
            if ($this->plausibleImage($src)) {
                $seen[$src] = true;
                $images[] = $src;
            }
        }

        return $images;
    }

    // ── Build / normalize helpers ──────────────────────────────────────

    /**
     * Build the final mobile item array from extracted components.
     */
    protected function buildItem(
        string $title,
        float $officialPrice,
        float $unofficialPrice,
        ?int $isOfficial,
        string $status,
        ?string $releaseDate,
        array $specs,
        array $images,
    ): ?array {
        $title = $this->cleanText($title);
        if ($title === '') {
            return null;
        }

        // If we only got one price, it's the official one.
        if ($unofficialPrice > 0 && $officialPrice === 0.0) {
            $officialPrice = $unofficialPrice;
        }

        // Determine is_official: if unspecified, default based on status.
        $isOfficial = $isOfficial ?? ($status === 'official' ? 1 : 0);

        // Split brand / model from the title if not already set.
        $split = $this->splitBrandModel($title);

        // If only one price found and is_official is true, that's the official price.
        $official = $officialPrice > 0 ? $officialPrice : 0.0;
        $unofficial = $unofficialPrice > 0 ? $unofficialPrice : 0.0;

        return [
            'title' => $title,
            'brand_name' => $split['brand'],
            'model_name' => $split['model'],
            'official_price' => $official,
            'unofficial_price' => $unofficial,
            'is_official' => $isOfficial,
            'status' => $status,
            'release_date' => $releaseDate,
            'specifications' => $specs,
            'images' => $images,
        ];
    }

    /**
     * Split a device title into brand and model.
     *
     * @return array{brand: string, model: string}
     */
    protected function splitBrandModel(string $title): array
    {
        $title = $this->cleanText($title);

        foreach (self::BRANDS as $brand) {
            // Match brand as the first word(s) in the title.
            if (stripos($title, $brand) === 0) {
                $rest = ltrim(substr($title, strlen($brand)));
                // Clean common prefixes from the model.
                $rest = preg_replace('/^[:\-\s|]+/u', '', $rest);
                $rest = preg_replace('/\s*(' . preg_quote($brand, '/') . ')\s*/iu', '', $rest, 1);
                return ['brand' => $brand, 'model' => $rest !== '' ? $rest : $title];
            }
        }

        // If no known brand matched, assume the first word is the brand.
        $parts = preg_split('/\s+/u', $title);
        if (count($parts) >= 2) {
            return ['brand' => $parts[0], 'model' => implode(' ', array_slice($parts, 1))];
        }

        return ['brand' => $title, 'model' => ''];
    }

    /**
     * Build a title from brand + model.
     */
    protected function buildTitle(string $brand, string $model): string
    {
        $parts = array_filter([$brand, $model]);

        return implode(' ', $parts);
    }

    // ── DOM helpers ────────────────────────────────────────────────────

    /**
     * Load HTML into a DOMDocument, handling encoding and errors.
     */
    protected function loadHtml(string $html): ?DOMDocument
    {
        // Handle UTF-8 encoding issues.
        if (function_exists('mb_convert_encoding')) {
            // Ensure the HTML is valid UTF-8.
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $loaded = $doc->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $doc : null;
    }

    /**
     * Set the base URL for resolving relative image URLs.
     */
    public function setBaseUrl(string $url): self
    {
        $this->currentBaseUrl = $url;
        return $this;
    }

    /**
     * Get the first text node matching an XPath expression.
     */
    protected function text(DOMXPath $xpath, string $expression): string
    {
        $nodes = $xpath->query($expression);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        $node = $nodes->item(0);
        if ($node instanceof \DOMAttr) {
            return $this->cleanText($node->value);
        }

        return $this->cleanText($node->textContent);
    }

    /**
     * Get all text content from the document body.
     */
    protected function allText(DOMXPath $xpath): string
    {
        $nodes = $xpath->query('//body//text()');
        if ($nodes === false) {
            return '';
        }

        $texts = [];
        foreach ($nodes as $node) {
            $text = trim($node->textContent);
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return implode(' ', $texts);
    }

    /**
     * Clean a text string: decode HTML entities, normalize whitespace.
     */
    protected function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Resolve a relative URL against a base URL.
     */
    protected function resolveUrl(string $href, string $base): string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($href === '' || str_starts_with($href, '#')) {
            return '';
        }

        foreach (['javascript:', 'mailto:', 'tel:', 'data:'] as $scheme) {
            if (stripos($href, $scheme) === 0) {
                return '';
            }
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $href;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = parse_url($base);
        if ($parts === false || empty($parts['host'])) {
            return $href;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$port}{$href}";
        }

        $path = $parts['path'] ?? '/';
        $dir = rtrim(substr($path, 0, (int) strrpos($path, '/')), '/');

        return "{$scheme}://{$host}{$port}{$dir}/" . ltrim($href, '/');
    }

    /**
     * Filter out obvious non-product images (icons, spacers, logos).
     */
    protected function plausibleImage(string $url): bool
    {
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return false;
        }

        if (preg_match('/(logo|icon|sprite|avatar|pixel|spacer|1x1|placeholder)/i', $url)) {
            return false;
        }

        return true;
    }
}
