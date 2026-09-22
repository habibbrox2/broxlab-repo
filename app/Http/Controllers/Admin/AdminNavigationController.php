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
 * /admin/navigation — lets an admin reorder, hide, and relabel the public
 * header menu items. The persisted overrides live on app_settings.header_nav_items
 * (JSON blob) and are applied by HeaderNavService::configured() when the header
 * renders. Each override only needs to specify the fields it changes.
 */
class AdminNavigationController extends Controller
{
    /** @var array<string,bool> Keys of default items that carry a submenu. */
    protected array $submenuKeys;

    public function __construct(
        protected HeaderNavService $nav,
    ) {
        $this->submenuKeys = [];
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
        foreach ($defaults as $item) {
            $key = $item['key'];
            $ov = $overrides[$key] ?? null;

            $items[] = [
                'key' => $key,
                'label' => $ov['label'] ?? $item['label'],
                'url' => $ov['url'] ?? $item['url'],
                'icon' => $ov['icon'] ?? $item['icon'],
                'match' => $ov['match'] ?? $item['match'],
                'order' => $ov['order'] ?? $this->defaultOrder($key),
                'enabled' => $ov['enabled'] ?? true,
                'has_submenu' => isset($item['submenu']),
                'submenu' => $this->submenuState($item['submenu'] ?? [], $ov['submenu'] ?? []),
            ];
        }

        return view('admin.navigation.index', [
            'title' => 'Header Navigation',
            'header_title' => 'Header Navigation',
            'items' => $items,
        ]);
    }

    /** Persist the reordered/hidden/relabelled items. */
    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.key'                => ['required', 'string'],
            'items.*.label'              => ['required', 'string', 'max:60'],
            'items.*.url'                => ['required', 'string', 'max:255'],
            'items.*.icon'               => ['nullable', 'string', 'max:60'],
            'items.*.order'              => ['required', 'integer', 'min:0'],
            'items.*.enabled'            => ['sometimes', 'boolean'],
            'items.*.submenu'            => ['sometimes', 'array'],
            'items.*.submenu.*'          => ['sometimes', 'array'],
            'items.*.submenu.*.key'      => ['required', 'integer'],
            'items.*.submenu.*.label'     => ['required', 'string', 'max:60'],
            'items.*.submenu.*.url'       => ['required', 'string', 'max:255'],
            'items.*.submenu.*.order'     => ['required', 'integer', 'min:0'],
            'items.*.submenu.*.enabled'   => ['sometimes', 'boolean'],
        ]);

        $build = [];
        foreach ($payload['items'] as $i => $entry) {
            $key = $entry['key'];

            $item = [
                'label' => $entry['label'],
                'url' => $entry['url'],
                'icon' => $entry['icon'] ?? '',
                'order' => $entry['order'],
                'enabled' => $request->boolean("items.{$i}.enabled"),
            ];

            if (isset($this->submenuKeys[$key]) && isset($entry['submenu'])) {
                $sub = [];
                foreach ($entry['submenu'] as $j => $s) {
                    $sub[] = [
                        'label' => $s['label'],
                        'url' => $s['url'],
                        'order' => $s['order'],
                        'enabled' => $request->boolean("items.{$i}.submenu.{$j}.enabled"),
                    ];
                }
                $item['submenu'] = $sub;
            }

            $build[$key] = $item;
        }

        $this->nav->save($build);
        $this->nav->forget();

        $this->logActivity('Header navigation updated', $build);

        return redirect('/admin/navigation')->with('status', 'Navigation menu saved.');
    }

    /**
     * Build the submenu rows for the form, preserving any custom items
     * that don't exist in the defaults (forward-compatible).
     */
    protected function submenuState(array $defaults, array $overrides): array
    {
        $out = [];
        foreach ($defaults as $sub) {
            $sKey = $sub['key'];
            $ov = $overrides[$sKey] ?? null;
            $out[] = [
                'key' => $sKey,
                'label' => $ov['label'] ?? ($sub['label'] ?? ''),
                'url' => $ov['url'] ?? ($sub['url'] ?? '/'),
                'order' => $ov['order'] ?? ($sub['order'] ?? 0),
                'enabled' => $ov['enabled'] ?? true,
            ];
        }
        usort($out, fn ($a, $b) => $a['order'] <=> $b['order']);
        return $out;
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
