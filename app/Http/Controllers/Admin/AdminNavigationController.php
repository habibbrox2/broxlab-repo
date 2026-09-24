<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\HeaderNavService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * /admin/navigation — lets an admin reorder, hide, add, remove, and relabel
 * the public header menu items. The persisted overrides live on
 * app_settings.header_nav_items (JSON blob) and are applied by
 * HeaderNavService::configured() when the header renders.
 *
 * Data model (per key): {label,url,icon,match,order,enabled,submenu[]}.
 * Removed default items are persisted with `enabled: false` + `removed: true`
 * so they stay absent from the header without being lost (restorable from the
 * admin UI's "removed items" strip).
 */
class AdminNavigationController extends Controller
{
    /** @var array<string,bool> Keys of default items that carry a submenu. */
    protected array $submenuKeys = [];

    public function __construct(
        protected HeaderNavService $nav,
    ) {
        foreach ($this->nav->defaults() as $item) {
            if (isset($item['submenu'])) {
                $this->submenuKeys[$item['key']] = true;
            }
        }
    }

    /** Show the menu items with their current overrides. */
    public function index(): View
    {
        $defaults = $this->nav->defaults();
        $overrides = $this->nav->raw() ?? [];

        $items = [];
        $removed = [];

        foreach ($defaults as $item) {
            $key = $item['key'];
            $ov = $overrides[$key] ?? null;

            if (is_array($ov) && (($ov['removed'] ?? false) === true || ($ov['enabled'] ?? true) === false)) {
                // Persisted-removed (or hidden) default -> restorable strip.
                $removed[] = [
                    'key' => $key,
                    'label' => $ov['label'] ?? $item['label'],
                    'url' => $ov['url'] ?? $item['url'],
                    'icon' => $ov['icon'] ?? $item['icon'],
                    'match' => $ov['match'] ?? $item['match'],
                    'order' => $ov['order'] ?? $this->defaultOrder($key),
                    'enabled' => false,
                    'is_default' => true,
                    'has_submenu' => isset($item['submenu']),
                    'submenu' => $this->submenuState($item['submenu'] ?? [], $ov['submenu'] ?? []),
                ];
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => $ov['label'] ?? $item['label'],
                'url' => $ov['url'] ?? $item['url'],
                'icon' => $ov['icon'] ?? $item['icon'],
                'match' => $ov['match'] ?? $item['match'],
                'order' => $ov['order'] ?? $this->defaultOrder($key),
                'enabled' => true,
                'is_default' => true,
                'has_submenu' => isset($item['submenu']),
                'submenu' => $this->submenuState($item['submenu'] ?? [], $ov['submenu'] ?? []),
            ];
        }

        // Custom (admin-added) items stored in the blob with no default twin.
        foreach ($overrides as $key => $ov) {
            if (! is_array($ov) || (($ov['removed'] ?? false) === true)) {
                continue;
            }
            if (array_key_exists($key, $this->submenuKeys) || in_array($key, array_column($defaults, 'key'), true)) {
                continue; // default keys handled above
            }
            $items[] = [
                'key' => $key,
                'label' => $ov['label'] ?? $key,
                'url' => $ov['url'] ?? '/',
                'icon' => $ov['icon'] ?? '',
                'match' => $ov['match'] ?? ($ov['url'] ?? '/'),
                'order' => $ov['order'] ?? 999,
                'enabled' => true,
                'is_default' => false,
                'has_submenu' => isset($ov['submenu']) && is_array($ov['submenu']) && count($ov['submenu']) > 0,
                'submenu' => $this->customSubmenuState($ov['submenu'] ?? []),
            ];
        }

        usort($items, fn ($a, $b) => $a['order'] <=> $b['order']);
        usort($removed, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return view('admin.navigation.index', [
            'title' => 'Header Navigation',
            'header_title' => 'Header Navigation',
            'items' => $items,
            'removed' => $removed,
        ]);
    }

    /** Persist the reordered/hidden/relabelled/added/removed items. */
    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.key' => ['required', 'string', 'max:60'],
            'items.*.label' => ['required', 'string', 'max:60'],
            'items.*.url' => ['required', 'string', 'max:255'],
            'items.*.icon' => ['nullable', 'string', 'max:60'],
            'items.*.order' => ['required', 'integer', 'min:0'],
            'items.*.submenu' => ['sometimes', 'array'],
            'items.*.submenu.*.key' => ['nullable'],
            'items.*.submenu.*.label' => ['required', 'string', 'max:60'],
            'items.*.submenu.*.url' => ['required', 'string', 'max:255'],
            'items.*.submenu.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $build = [];
        $postedKeys = [];

        foreach ($payload['items'] as $i => $entry) {
            $key = $this->sanitizeKey($entry['key']);
            if ($key === '' || in_array($key, $postedKeys, true)) {
                continue; // skip empty/duplicate keys
            }
            $postedKeys[] = $key;

            $item = [
                'label' => $entry['label'],
                'url' => $entry['url'],
                'icon' => $entry['icon'] ?? '',
                'order' => (int) $entry['order'],
                'enabled' => true,
            ];

            if (isset($entry['submenu']) && is_array($entry['submenu'])) {
                $sub = [];
                foreach ($entry['submenu'] as $j => $s) {
                    $sub[] = [
                        'key' => $s['key'] ?? $j,
                        'label' => $s['label'],
                        'url' => $s['url'],
                        'order' => (int) $s['order'],
                        'enabled' => $request->boolean("items.{$i}.submenu.{$j}.enabled"),
                    ];
                }
                $item['submenu'] = $sub;
            }

            $build[$key] = $item;
        }

        // Removals: any default key absent from the posted list is persisted
        // as removed so it stays hidden (and restorable) without being lost.
        foreach ($this->nav->defaults() as $default) {
            if (! in_array($default['key'], $postedKeys, true)) {
                $build[$default['key']] = ['enabled' => false, 'removed' => true];
            }
        }

        $this->nav->save($build);
        $this->nav->forget();

        $this->logActivity('Header navigation updated', $build);

        return redirect('/admin/navigation')->with('status', 'Navigation menu saved.');
    }

    /** Wipe all overrides so the built-in defaults render again. */
    public function reset(): RedirectResponse
    {
        $this->nav->reset();

        $this->logActivity('Header navigation reset to defaults', []);

        return redirect('/admin/navigation')->with('status', 'Navigation reset to built-in defaults.');
    }

    /**
     * Build the submenu rows for a default item's form, preserving custom
     * entries that don't exist in the defaults (forward-compatible).
     */
    protected function submenuState(array $defaults, array $overrides): array
    {
        $out = [];
        foreach (array_values($defaults) as $idx => $sub) {
            $sKey = $sub['key'];
            $ov = is_array($overrides) ? ($overrides[$sKey] ?? null) : null;

            if (is_array($ov) && (($ov['removed'] ?? false) === true || ($ov['enabled'] ?? true) === false)) {
                continue; // removed/hidden submenu entry stays off the form
            }

            $out[] = [
                'key' => $sKey,
                'label' => $ov['label'] ?? ($sub['label'] ?? ''),
                'url' => $ov['url'] ?? ($sub['url'] ?? '/'),
                'order' => $ov['order'] ?? ($sub['order'] ?? $idx),
                'enabled' => true,
            ];
        }

        if (is_array($overrides)) {
            $defaultKeys = array_column($out, 'key');
            foreach ($overrides as $sKey => $ov) {
                if (! is_array($ov) || in_array($sKey, $defaultKeys, true)) {
                    continue;
                }
                if (($ov['removed'] ?? false) === true || ($ov['enabled'] ?? true) === false) {
                    continue;
                }
                $out[] = [
                    'key' => $sKey,
                    'label' => $ov['label'] ?? (is_string($sKey) ? $sKey : ''),
                    'url' => $ov['url'] ?? '/',
                    'order' => $ov['order'] ?? 999,
                    'enabled' => true,
                ];
            }
        }

        usort($out, fn ($a, $b) => $a['order'] <=> $b['order']);
        return $out;
    }

    /** Build submenu rows for a custom (non-default) top-level item. */
    protected function customSubmenuState(array $submenu): array
    {
        $out = [];
        foreach ($submenu as $i => $sub) {
            if (! is_array($sub) || (($sub['removed'] ?? false) === true) || (($sub['enabled'] ?? true) === false)) {
                continue;
            }
            $out[] = [
                'key' => $sub['key'] ?? $i,
                'label' => $sub['label'] ?? '',
                'url' => $sub['url'] ?? '/',
                'order' => $sub['order'] ?? 0,
                'enabled' => true,
            ];
        }
        usort($out, fn ($a, $b) => $a['order'] <=> $b['order']);
        return $out;
    }

    /** Keep item keys filesystem/JSON/url-safe: lowercase, [a-z0-9_-]. */
    protected function sanitizeKey(string $raw): string
    {
        $key = mb_strtolower(trim($raw));
        $key = preg_replace('/[^a-z0-9_\-]/', '_', $key) ?? '';
        return mb_substr($key, 0, 60);
    }

    protected function defaultOrder(string $key): int
    {
        foreach ($this->nav->defaults() as $i => $item) {
            if ($item['key'] === $key) {
                return $i + 1;
            }
        }
        return PHP_INT_MAX;
    }

    protected function logActivity(string $action, array $data): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => (int) auth()->id(),
                'role' => app(\App\Support\UserProfileService::class)
                    ->rbacFor((int) auth()->id())['roles'][0]['name'] ?? 'admin',
                'action' => $action,
                'resource_type' => 'header_navigation',
                'resource_id' => 0,
                'status' => 'success',
                'ip_address' => request()?->ip() ?? '0.0.0.0',
                'user_agent' => mb_substr((string) (request()?->userAgent() ?? ''), 0, 500),
                'details' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('navigation activity log failed: ' . $e->getMessage());
        }
    }
}
