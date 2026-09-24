<?php

declare(strict_types=1);

namespace App\Support\Scraper;

use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP wrapper for the extraction pipeline.
 *
 * Uses the Laravel HTTP client (Guzzle is already installed) with the
 * configured user agent, timeout and bounded retries. Every call returns a
 * structured result instead of throwing, so one dead source never aborts a run.
 */
class ScraperClient
{
    public function __construct(
        protected array $options = [],
    ) {
        $this->options = array_merge([
            'timeout' => (int) config('scraper.timeout', 15),
            'retries' => (int) config('scraper.retries', 2),
            'user_agent' => (string) config('scraper.user_agent', 'BroxLabBot/1.0'),
            'delay_ms' => (int) config('scraper.delay_ms', 400),
        ], $options);
    }

    /**
     * @return array{ok:bool,status:int|null,body:string|null,url:string,error:string|null}
     */
    public function get(string $url): array
    {
        $url = $this->normalizeUrl($url);
        $result = [
            'ok' => false,
            'status' => null,
            'body' => null,
            'url' => $url,
            'error' => null,
        ];

        try {
            $pending = Http::withHeaders([
                'User-Agent' => $this->options['user_agent'],
                'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/html;q=0.8, */*;q=0.5',
            ])->timeout($this->options['timeout']);

            if ($this->options['retries'] > 0) {
                $pending = $pending->retry($this->options['retries'], 500, throw: false);
            }

            $response = $pending->get($url);

            $result['status'] = $response->status();
            if ($response->successful()) {
                $result['ok'] = true;
                $result['body'] = (string) $response->body();
            } else {
                $result['error'] = 'HTTP ' . $response->status();
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    public function sleep(): void
    {
        $ms = (int) $this->options['delay_ms'];
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    public function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        return $url;
    }

    /**
     * Resolve a possibly relative href against a base URL.
     */
    public function resolveUrl(string $href, string $base): string
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

        $resolved = "{$scheme}://{$host}{$port}{$dir}/" . ltrim($href, '/');

        // Collapse relative segments (/../ and /./) that pages like
        // bdjobstoday emit in hrefs ("../job_details.php?id=…").
        $p = parse_url($resolved);
        if ($p !== false && isset($p['path']) && str_contains($p['path'], '/../') || str_starts_with($p['path'] ?? '', '/../')) {
            $out = [];
            foreach (explode('/', $p['path']) as $seg) {
                if ($seg === '..') {
                    array_pop($out);
                } elseif ($seg !== '.') {
                    $out[] = $seg;
                }
            }
            $p['path'] = implode('/', $out);
            if (! str_starts_with((string) $p['path'], '/')) {
                $p['path'] = '/' . $p['path'];
            }
            $resolved = (string) $this->buildUrl($p);
        }

        return $resolved;
    }

    /** Rebuild a URL string from its parse_url parts. */
    protected function buildUrl(array $parts): string
    {
        $url = ($parts['scheme'] ?? 'https') . '://';
        if (isset($parts['user'])) {
            $url .= $parts['user'];
            if (isset($parts['pass'])) {
                $url .= ':' . $parts['pass'];
            }
            $url .= '@';
        }
        $url .= $parts['host'] ?? '';
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        $url .= $parts['path'] ?? '/';
        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }
}
