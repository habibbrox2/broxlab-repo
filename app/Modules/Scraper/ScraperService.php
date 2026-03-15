<?php
declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * ScraperService.php
 * Module for scraping basic metadata (Title, Description, Links) from a public URL.
 */
class ScraperService
{
    private const DEFAULT_TIMEOUT = 10;
    private const DEFAULT_UA = 'Mozilla/5.0 (compatible; BroxBot/1.0)';

    /**
     * Scrape a URL and return its metadata.
     *
     * @return array{url: string, title: string, description: string, image: string, links: list<string>, timestamp: string}|array{error: string}
     */
    public function scrape(string $url): array
    {
        $url = trim($url);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['error' => 'Invalid URL. Please provide a valid URL (e.g. https://example.com).'];
        }

        $html = $this->fetchHtml($url);

        if ($html === null) {
            return ['error' => 'Failed to fetch URL. Check that the URL is publicly accessible.'];
        }

        $data = $this->parseHtml($html, $url);
        $data['timestamp'] = date('Y-m-d H:i:s');

        return $data;
    }

    private function fetchHtml(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => self::DEFAULT_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => self::DEFAULT_UA,
                CURLOPT_ENCODING       => '',   // Accept all encodings
            ]);
            $body = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($body === false || $err) {
                return null;
            }

            return (string)$body;
        }

        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => self::DEFAULT_TIMEOUT,
                'user_agent'      => self::DEFAULT_UA,
                'ignore_errors'   => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        return $body !== false ? (string)$body : null;
    }

    private function parseHtml(string $html, string $baseUrl): array
    {
        $doc = new \DOMDocument();
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // Title
        $title = '';
        $titleNode = $xpath->query('//title');
        if ($titleNode && $titleNode->length > 0) {
            $title = trim($titleNode->item(0)->textContent);
        }

        // OG Title fallback
        if ($title === '') {
            $og = $xpath->query('//meta[@property="og:title"]/@content');
            if ($og && $og->length > 0) {
                $title = trim($og->item(0)->nodeValue ?? '');
            }
        }

        // Meta description
        $description = '';
        $descNode = $xpath->query('//meta[@name="description"]/@content');
        if ($descNode && $descNode->length > 0) {
            $description = trim($descNode->item(0)->nodeValue ?? '');
        }
        // OG description fallback
        if ($description === '') {
            $og = $xpath->query('//meta[@property="og:description"]/@content');
            if ($og && $og->length > 0) {
                $description = trim($og->item(0)->nodeValue ?? '');
            }
        }

        // OG Image
        $image = '';
        $imgNode = $xpath->query('//meta[@property="og:image"]/@content');
        if ($imgNode && $imgNode->length > 0) {
            $image = trim($imgNode->item(0)->nodeValue ?? '');
        }

        // Collect top-level links (up to 5)
        $links   = [];
        $linkNodes = $xpath->query('//a[@href]/@href');
        if ($linkNodes) {
            foreach ($linkNodes as $link) {
                $href = trim($link->nodeValue ?? '');
                if ($href === '' || $href === '#' || str_starts_with($href, 'javascript:')) {
                    continue;
                }
                // Resolve relative URLs
                if (!str_starts_with($href, 'http')) {
                    $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
                }
                $links[] = $href;
                if (count($links) >= 5) {
                    break;
                }
            }
        }

        return [
            'url'         => $baseUrl,
            'title'       => $title !== '' ? $title : '(No title found)',
            'description' => $description !== '' ? $description : '(No description found)',
            'image'       => $image,
            'links'       => $links,
        ];
    }
}
