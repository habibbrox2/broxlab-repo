<?php

declare(strict_types=1);

namespace App\Support\Scraper;

/**
 * Read-only accessor over the curated source list in config/scraper.php.
 * Sources are addressed by their stable `key` (or a legacy numeric index).
 */
class SourceCatalog
{
    /** Default per-source values so callers never have to null-check. */
    protected const DEFAULTS = [
        'type' => 'news',
        'lang' => 'bn',
        'strategy' => 'auto',
        'enabled' => true,
        'feed' => null,
        'link_pattern' => null,
        'note' => null,
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $sources = config('scraper.sources', []);
        if (! is_array($sources)) {
            return [];
        }

        return array_map(fn ($source) => array_merge(self::DEFAULTS, (array) $source), array_values($sources));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function enabled(?string $type = null): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $source) => $source['enabled'] !== false
                && ($type === null || $source['type'] === $type)
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string|int|null $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        foreach ($this->all() as $index => $source) {
            if ((string) $source['key'] === (string) $id) {
                return $source;
            }
            if (is_numeric($id) && (int) $id === $index) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        return array_values(array_unique(array_map(
            fn (array $source) => (string) $source['type'],
            $this->all()
        )));
    }

    /**
     * @return array<int, array{key:string,type:string}>
     */
    public function keys(bool $enabledOnly = true): array
    {
        $sources = $enabledOnly ? $this->enabled() : $this->all();

        return array_map(fn (array $s) => ['key' => (string) $s['key'], 'type' => (string) $s['type']], $sources);
    }
}
