<?php

declare(strict_types=1);

namespace App\Support\Scraper;

/**
 * File-backed store for the extraction pipeline. Everything lives under
 * storage/app/<scraper.storage_path>/ so the extracted snapshots are portable
 * and can be read by any process without a database migration:
 *
 *   sources/<key>.json  — latest normalised items per source
 *   seen.json           — url => first-seen timestamp (dedupe set)
 *   runs.json           — bounded run history
 */
class ScraperStore
{
    protected string $base;

    public function __construct(?string $basePath = null)
    {
        $this->base = $basePath ?? storage_path('app/' . config('scraper.storage_path', 'scraping'));
    }

    public function basePath(): string
    {
        return $this->base;
    }

    // ------------------------------------------------------------------ sources

    public function sourcePath(string $key): string
    {
        return $this->base . '/sources/' . $this->safeKey($key) . '.json';
    }

    /**
     * @return array{key:string,updated_at:?string,count:int,items:array<int, array<string, mixed>>}
     */
    public function readSource(string $key): array
    {
        $data = $this->readJson($this->sourcePath($key));

        return [
            'key' => $key,
            'updated_at' => $data['updated_at'] ?? null,
            'count' => (int) ($data['count'] ?? (is_array($data['items'] ?? null) ? count($data['items']) : 0)),
            'items' => is_array($data['items'] ?? null) ? $data['items'] : [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function writeSource(string $key, array $items, ?string $updatedAt = null): void
    {
        $this->writeJson($this->sourcePath($key), [
            'key' => $key,
            'updated_at' => $updatedAt ?? gmdate('c'),
            'count' => count($items),
            'items' => $items,
        ]);
    }

    /**
     * Lightweight per-source metadata for admin listings (no item payload).
     *
     * @param  array<int, string>  $keys
     * @return array<string, array{updated_at:?string,count:int}>
     */
    public function summaries(array $keys): array
    {
        $summary = [];
        foreach ($keys as $key) {
            $data = $this->readJson($this->sourcePath($key));
            $summary[$key] = [
                'updated_at' => $data['updated_at'] ?? null,
                'count' => (int) ($data['count'] ?? (is_array($data['items'] ?? null) ? count($data['items']) : 0)),
            ];
        }

        return $summary;
    }

    public function forgetSource(string $key): void
    {
        $path = $this->sourcePath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    // --------------------------------------------------------------------- seen

    /**
     * @return array<string, int>
     */
    public function seen(): array
    {
        $data = $this->readJson($this->base . '/seen.json');

        return is_array($data) ? $data : [];
    }

    /**
     * Filters items down to those whose link has not been seen before.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function filterUnseen(array $items): array
    {
        $seen = $this->seen();

        return array_values(array_filter($items, function (array $item) use ($seen): bool {
            $link = (string) ($item['link'] ?? '');
            if ($link === '') {
                return false;
            }

            return ! isset($seen[$link]);
        }));
    }

    /**
     * @param  array<int, string>  $links
     */
    public function markSeen(array $links, ?int $cap = null): void
    {
        $cap ??= (int) config('scraper.seen_cap', 20000);
        $seen = $this->seen();
        $now = time();

        foreach ($links as $link) {
            if ($link !== '') {
                $seen[$link] = $now;
            }
        }

        if (count($seen) > $cap) {
            arsort($seen);
            $seen = array_slice($seen, 0, $cap, true);
        }

        $this->writeJson($this->base . '/seen.json', $seen);
    }

    // --------------------------------------------------------------------- runs

    /**
     * @param  array<string, mixed>  $run
     */
    public function appendRun(array $run): void
    {
        $runs = $this->runs();
        array_unshift($runs, array_merge(['at' => gmdate('c')], $run));

        $limit = (int) config('scraper.run_history', 100);
        $this->writeJson($this->base . '/runs.json', array_slice($runs, 0, $limit));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function runs(int $limit = 50): array
    {
        $runs = $this->readJson($this->base . '/runs.json');

        return is_array($runs) ? array_slice(array_values($runs), 0, $limit) : [];
    }

    public function lastRun(): ?array
    {
        return $this->runs(1)[0] ?? null;
    }

    /**
     * Aggregate counters for the admin dashboard.
     *
     * @return array{stored_items:int,tracked_urls:int,runs:int,last_run:?array}
     */
    public function stats(): array
    {
        $stored = 0;
        // seenKeysByFile() already returns the source keys — array_keys() here
        // yielded int list offsets and tripped the string type on sourcePath().
        foreach ($this->summaries($this->seenKeysByFile()) as $summary) {
            $stored += $summary['count'];
        }

        $runs = $this->runs();

        return [
            'stored_items' => $stored,
            'tracked_urls' => count($this->seen()),
            'runs' => count($runs),
            'last_run' => $runs[0] ?? null,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function seenKeysByFile(): array
    {
        $dir = $this->base . '/sources';
        if (! is_dir($dir)) {
            return [];
        }

        $keys = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $keys[] = basename($file, '.json');
        }

        return $keys;
    }

    // ---------------------------------------------------------------- internals

    protected function safeKey(string $key): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_\-]/', '_', $key);
    }

    protected function readJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function writeJson(string $path, array $data): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return;
        }

        // Atomic-ish write so concurrent readers never see a truncated file.
        $tmp = $path . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $json) !== false) {
            @rename($tmp, $path);
        }
    }
}
