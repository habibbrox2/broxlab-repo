<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the public header navigation menu.
 *
 * The canonical menu items live here (mirrors the legacy header-v2.twig
 * `nav_items` and the previous inline defaults in header.blade.php). Admins
 * can persist per-item overrides through the /admin/navigation screen; those
 * overrides are stored as a JSON blob on the single app_settings row
 * (`header_nav_items`) and are applied here so non-developers can reorder or
 * hide menu items (and relabel / retarget them) without a code deploy.
 *
 * NULL (or an empty blob) falls back to the built-in defaults; an override
 * only needs to specify the fields it wants to change.
 */
class HeaderNavService
{
    /** Cache key for the resolved (merged + filtered + sorted) menu. */
    public const CACHE_KEY = 'header_nav:resolved';

    /**
     * Built-in default menu. Each top-level item carries a stable `key`; each
     * submenu item carries a stable numeric `key` (its position in the default
     * list) so overrides remain forward-compatible as the defaults evolve.
     */
    public function defaults(): array
    {
        return [
            ['key' => 'home',        'label' => 'Home',       'url' => '/',          'icon' => 'home',      'match' => '/'],
            ['key' => 'mobiles',     'label' => 'Mobiles',    'url' => '/mobiles',   'icon' => 'smartphone', 'match' => '/mobiles',
             'submenu' => [
                ['key' => 0, 'label' => 'Browse All',     'url' => '/mobiles',                          'icon' => 'list'],
                ['key' => 1, 'label' => 'Mobile Prices',   'url' => '/mobiles/prices',                   'icon' => 'wallet'],
                ['key' => 2, 'label' => 'New Arrivals',    'url' => '/mobiles/new',                      'icon' => 'clock'],
                ['key' => 3, 'label' => 'Top Phones',      'url' => '/mobiles?sort=created_at&order=DESC','icon' => 'star'],
                ['key' => 4, 'label' => 'Brands',          'url' => '/mobiles/brands',                   'icon' => 'tag'],
                ['key' => 5, 'label' => 'Compare Phones',  'url' => '/mobiles/compare',                  'icon' => 'git-compare'],
             ]],
            ['key' => 'articles',    'label' => 'Articles',   'url' => '/posts',    'icon' => 'file-text',  'match' => '/posts',
             'submenu' => [
                ['key' => 0, 'label' => 'All Posts',   'url' => '/posts',             'icon' => 'list'],
                ['key' => 1, 'label' => 'Categories',  'url' => '/categories',        'icon' => 'folder'],
                ['key' => 2, 'label' => 'Latest',      'url' => '/posts?sort=latest', 'icon' => 'clock'],
             ]],
            ['key' => 'categories',  'label' => 'Categories','url' => '/categories','icon' => 'grid',       'match' => '/categories'],
            ['key' => 'jobs',        'label' => 'Jobs',      'url' => '/jobs',     'icon' => 'briefcase',  'match' => '/jobs',
             'submenu' => [
                ['key' => 0, 'label' => 'All Jobs',      'url' => '/jobs',            'icon' => 'list'],
                ['key' => 1, 'label' => 'Govt Jobs',     'url' => '/jobs?tag=govt',    'icon' => 'landmark'],
                ['key' => 2, 'label' => 'Private Jobs',  'url' => '/jobs?tag=private', 'icon' => 'building'],
                ['key' => 3, 'label' => 'Internships',   'url' => '/jobs?tag=intern',  'icon' => 'graduation-cap'],
             ]],
            ['key' => 'services',    'label' => 'Services',  'url' => '/services', 'icon' => 'briefcase',  'match' => '/services',
             'submenu' => [
                ['key' => 0, 'label' => 'All Services', 'url' => '/services',    'icon' => 'list'],
                ['key' => 1, 'label' => 'Tools',        'url' => '/tools',       'icon' => 'wrench'],
                ['key' => 2, 'label' => 'Calculators',  'url' => '/calculators', 'icon' => 'calculator'],
             ]],
            ['key' => 'portfolio',   'label' => 'Portfolio', 'url' => '/portfolio','icon' => 'image',    'match' => '/portfolio'],
            ['key' => 'contact',     'label' => 'Contact',   'url' => '/contact',  'icon' => 'mail',     'match' => '/contact'],
            ['key' => 'cv',          'label' => 'CV Builder','url' => '/cv-builder/templates','icon' => 'file-text','match' => '/cv-builder',
             'submenu' => [
                ['key' => 0, 'label' => 'Templates', 'url' => '/cv-builder/templates', 'icon' => 'layout'],
                ['key' => 1, 'label' => 'My CVs',    'url' => '/cv-builder/guest',   'icon' => 'file'],
             ]],
            ['key' => 'weather',     'label' => 'Weather',   'url' => '/weather','icon' => 'cloud-sun', 'match' => '/weather'],
            ['key' => 'medicines',   'label' => 'Medicines', 'url' => '/medicines','icon' => 'pill',   'match' => '/medicines'],
            ['key' => 'news',        'label' => 'News',      'url' => '/news',   'icon' => 'newspaper','match' => '/news'],
            ['key' => 'ai_tools',    'label' => 'AI Tools',  'url' => '/photo-edit','icon' => 'wand', 'match' => '/photo-edit',
             'submenu' => [
                ['key' => 0, 'label' => 'AI Photo Edit',   'url' => '/photo-edit',    'icon' => 'image-edit'],
                ['key' => 1, 'label' => 'AI চ্যাট সহকারী', 'url' => '/ai-chat',       'icon' => 'message-circle'],
                ['key' => 2, 'label' => 'AI ভয়েস ওডিও',  'url' => '#',               'icon' => 'volume-2'],
             ]],
            ['key' => 'digital_sheba','label' => 'ডিজিটাল সেবা','url' => '/digital-sheba','icon' => 'globe','match' => '/digital-sheba',
             'submenu' => [
                ['key' => 0, 'label' => 'ডিজিটাল রাষ্ট্রসেবা',  'url' => 'https://sheba.gov.bd',          'icon' => 'shield'],
                ['key' => 1, 'label' => 'বাংলাদেশ সার্ভি',      'url' => 'https://www.bangladesh.gov.bd', 'icon' => 'landmark'],
                ['key' => 2, 'label' => 'ই-গভর্ন্যান্স',        'url' => 'https://egov.gov.bd',           'icon' => 'file-check'],
                ['key' => 3, 'label' => 'মোবাইল ব্যাংকিং',       'url' => 'https://www.bangladeshbank.org.bd','icon' => 'banknote'],
                ['key' => 4, 'label' => 'ডিজিটাল শিক্ষা',        'url' => 'https://www.mohe.gov.bd',        'icon' => 'graduation-cap'],
                ['key' => 5, 'label' => 'ই-হেলথ',              'url' => 'https://www.docdidi.com',        'icon' => 'heart-pulse'],
             ]],
        ];
    }

    /**
     * Build the menu the header actually renders: defaults with any persisted
     * overrides applied (reorder, hide, relabel/retarget), then filtered and
     * sorted. Cached for a short window; the admin save handler clears it.
     */
    public function configured(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            $stored = $this->raw();

            if ($stored === null || $stored === []) {
                return $this->defaults();
            }

            $defaults = $this->defaults();
            $out = [];

            foreach ($defaults as $item) {
                $key = $item['key'];
                $override = $stored[$key] ?? null;

                if (
                    is_array($override)
                    && array_key_exists('enabled', $override)
                    && $override['enabled'] === false
                ) {
                    continue; // hidden
                }

                $merged = $this->mergeItem($item, $override);
                $out[] = $merged;
            }

            // Append any stored items that don't match a known default key
            // (e.g. a custom entry added by an earlier deploy). They're
            // rendered as-is so the header doesn't silently drop them.
            foreach ($stored as $key => $override) {
                if (! is_array($override) || array_key_exists($key, array_column($defaults, null, 'key'))) {
                    continue;
                }
                if (isset($override['enabled']) && $override['enabled'] === false) {
                    continue;
                }
                $out[] = $this->normalizeItem($override, $key);
            }

            usort($out, fn ($a, $b) => ($a['order'] <=> $b['order']));

            return $out;
        });
    }

    /**
     * The raw stored overrides (keyed by item key) or null when none persisted.
     */
    public function raw(): ?array
    {
        $row = DB::table('app_settings')->where('id', 1)->first();

        if (! $row || ! isset($row->header_nav_items) || $row->header_nav_items === null) {
            return null;
        }

        $decoded = is_string($row->header_nav_items)
            ? json_decode($row->header_nav_items, true)
            : $row->header_nav_items;

        return is_array($decoded) ? $decoded : null;
    }

    /** Persist the override map and invalidate the cached menu. */
    public function save(array $items): void
    {
        $normalized = [];

        foreach ($items as $key => $item) {
            if (! is_array($item) || ! is_string($key)) {
                continue;
            }

            $normalized[$key] = $this->normalizeItem($item, $key);
        }

        DB::table('app_settings')->where('id', 1)->update([
            'header_nav_items' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);

        Cache::forget(self::CACHE_KEY);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Merge a persisted override onto a default item (recursively for submenus). */
    protected function mergeItem(array $item, ?array $override): array
    {
        if ($override === null) {
            $item['order'] = $item['order'] ?? $this->defaultOrder($item);
            return $item;
        }

        $item = array_merge($item, array_filter($override, static fn ($v, $k) => ! in_array($k, ['submenu', 'enabled'], true), ARRAY_FILTER_USE_BOTH));

        $order = $override['order'] ?? $this->defaultOrder($item);
        $item['order'] = (int) $order;
        $item['enabled'] = $override['enabled'] ?? true;

        if (array_key_exists('submenu', $override) && is_array($override['submenu'])) {
            $defaults = $item['submenu'] ?? [];
            $newSub = [];
            foreach ($defaults as $sub) {
                $subOverride = $override['submenu'][$sub['key']] ?? null;
                if (is_array($subOverride) && ($subOverride['enabled'] ?? true) === false) {
                    continue;
                }
                $sub = array_merge($sub, array_filter($subOverride ?? [], static fn ($v, $k) => $k !== 'enabled', ARRAY_FILTER_USE_BOTH));
                $sub['order'] = (int) ($subOverride['order'] ?? ($sub['order'] ?? 0));
                $sub['enabled'] = $subOverride['enabled'] ?? true;
                $newSub[] = $sub;
            }
            usort($newSub, fn ($a, $b) => ($a['order'] <=> $b['order']));
            $item['submenu'] = $newSub;
        }

        return $item;
    }

    /** Normalize an override (or custom) item so the header can render it. */
    protected function normalizeItem(array $item, string|int $key): array
    {
        $item['key'] = $key;
        $item['label'] = (string) ($item['label'] ?? '');
        $item['url'] = (string) ($item['url'] ?? '/');
        $item['icon'] = (string) ($item['icon'] ?? '');
        $item['match'] = (string) ($item['match'] ?? $item['url']);
        $item['enabled'] = (bool) ($item['enabled'] ?? true);
        $item['order'] = (int) ($item['order'] ?? 0);

        if (isset($item['submenu']) && is_array($item['submenu'])) {
            $sub = array_values(array_map(function ($s) {
                $s['order'] = (int) ($s['order'] ?? 0);
                $s['enabled'] = (bool) ($s['enabled'] ?? true);
                return $s;
            }, $item['submenu']));
            usort($sub, fn ($a, $b) => ($a['order'] <=> $b['order']));
            $item['submenu'] = array_values(array_filter($sub, fn ($s) => $s['enabled']));
        }

        return $item;
    }

    /** Stable default order = the item's position in the defaults list + 1. */
    protected function defaultOrder(array $item): int
    {
        foreach ($this->defaults() as $i => $default) {
            if ($default['key'] === $item['key']) {
                return $i + 1;
            }
        }
        return PHP_INT_MAX;
    }
}
