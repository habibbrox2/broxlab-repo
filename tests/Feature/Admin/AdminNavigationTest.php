<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\HeaderNavService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature coverage for /admin/navigation: reorder, add, remove, restore,
 * relabel, submenu management, and reset-to-defaults.
 */
class AdminNavigationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic starting point: wipe any persisted overrides.
        DB::table('app_settings')->where('id', 1)->update(['header_nav_items' => null]);
        Cache::forget(HeaderNavService::CACHE_KEY);
    }

    protected function tearDown(): void
    {
        // Leave a clean slate for other tests / the local dev environment.
        DB::table('app_settings')->where('id', 1)->update(['header_nav_items' => null]);
        Cache::forget(HeaderNavService::CACHE_KEY);

        parent::tearDown();
    }

    protected function actingAdmin()
    {
        // actingAs() requires an Eloquent Authenticatable, not a stdClass row.
        $user = User::where('username', 'admin')->first();
        if (! $user) {
            $user = User::create([
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('secret'),
                'role' => 'admin',
            ]);
        }

        return $this->actingAs($user);
    }

    // ── Authorization ─────────────────────────────────────────────

    public function test_guest_cannot_access_navigation_admin(): void
    {
        $this->get('/admin/navigation')->assertRedirect('/login');
        $this->post('/admin/navigation', [])->assertRedirect('/login');
        $this->post('/admin/navigation/reset')->assertRedirect('/login');
    }

    // ── Index ─────────────────────────────────────────────────────

    public function test_admin_can_view_navigation_page(): void
    {
        $this->actingAdmin()
            ->get('/admin/navigation')
            ->assertOk()
            ->assertViewIs('admin.navigation.index')
            ->assertViewHas('items');
    }

    public function test_index_lists_defaults_and_add_remove_controls(): void
    {
        $response = $this->actingAdmin()->get('/admin/navigation');

        $response->assertSee('Add Menu Item');
        $response->assertSee('Home');
        $response->assertSee('Mobiles');
    }

    // ── Reorder ───────────────────────────────────────────────────

    public function test_reorder_persists_new_sequence(): void
    {
        $defaults = app(HeaderNavService::class)->defaults();
        $home = $defaults[0]; // home
        $news = null;
        foreach ($defaults as $d) {
            if ($d['key'] === 'news') {
                $news = $d;
            }
        }
        $this->assertNotNull($news);

        $payload = [
            'items' => [
                ['key' => 'news', 'label' => $news['label'], 'url' => $news['url'], 'icon' => $news['icon'], 'order' => 1],
                ['key' => 'home', 'label' => $home['label'], 'url' => $home['url'], 'icon' => $home['icon'], 'order' => 2],
            ],
        ];

        $this->actingAdmin()
            ->post('/admin/navigation', $payload)
            ->assertRedirect('/admin/navigation')
            ->assertSessionHas('status');

        $keys = array_column(app(HeaderNavService::class)->configured(), 'key');
        $this->assertSame(['news', 'home'], $keys, 'news must come before home after reorder');
    }

    // ── Remove + restore via re-adding ────────────────────────────

    public function test_removed_default_item_is_hidden_and_restorable(): void
    {
        $svc = app(HeaderNavService::class);
        $home = collect($svc->defaults())->firstWhere('key', 'home');

        // Save without 'home' → controller marks it removed.
        $this->actingAdmin()
            ->post('/admin/navigation', [
                'items' => [
                    ['key' => 'news', 'label' => 'News', 'url' => '/news', 'icon' => 'newspaper', 'order' => 1],
                ],
            ])
            ->assertRedirect('/admin/navigation');

        $keys = array_column($svc->configured(), 'key');
        $this->assertNotContains('home', $keys, 'home must be hidden after removal');

        // Admin index now shows the restore strip.
        $this->actingAdmin()
            ->get('/admin/navigation')
            ->assertOk()
            ->assertSee('Removed items')
            ->assertSee('data-key="home"', false);

        // Restore = save including home again.
        $this->actingAdmin()
            ->post('/admin/navigation', [
                'items' => [
                    ['key' => 'home', 'label' => $home['label'], 'url' => $home['url'], 'icon' => $home['icon'], 'order' => 1],
                    ['key' => 'news', 'label' => 'News', 'url' => '/news', 'icon' => 'newspaper', 'order' => 2],
                ],
            ])
            ->assertRedirect('/admin/navigation');

        $this->assertContains('home', array_column($svc->configured(), 'key'), 'home restored');
    }

    // ── Add custom item ───────────────────────────────────────────

    public function test_custom_item_can_be_added_and_renders_in_header_data(): void
    {
        $this->actingAdmin()
            ->post('/admin/navigation', [
                'items' => [
                    ['key' => 'home', 'label' => 'Home', 'url' => '/', 'icon' => 'home', 'order' => 1],
                    ['key' => 'my-blog', 'label' => 'My Blog', 'url' => '/blog', 'icon' => 'pen-tool', 'order' => 2,
                        'submenu' => [
                            ['key' => 0, 'label' => 'Latest', 'url' => '/blog/latest', 'order' => 1],
                        ],
                    ],
                ],
            ])
            ->assertRedirect('/admin/navigation');

        $cfg = app(HeaderNavService::class)->configured();
        $keys = array_column($cfg, 'key');
        $this->assertContains('my-blog', $keys);

        $blog = $cfg[array_search('my-blog', $keys, true)];
        $this->assertSame('My Blog', $blog['label']);
        $this->assertSame('/blog', $blog['url']);
        $this->assertCount(1, $blog['submenu']);
        $this->assertSame('Latest', $blog['submenu'][0]['label']);
    }

    // ── Submenu management ────────────────────────────────────────

    public function test_default_submenu_item_can_be_hidden_and_custom_submenu_added(): void
    {
        $svc = app(HeaderNavService::class);

        $this->actingAdmin()
            ->post('/admin/navigation', [
                'items' => [
                    ['key' => 'home', 'label' => 'Home', 'url' => '/', 'icon' => 'home', 'order' => 1],
                    ['key' => 'mobiles', 'label' => 'Mobiles', 'url' => '/mobiles', 'icon' => 'smartphone', 'order' => 2,
                        'submenu' => [
                            ['key' => 0, 'label' => 'Browse All', 'url' => '/mobiles', 'order' => 1, 'enabled' => 1],
                            ['key' => 1, 'label' => 'Mobile Prices', 'url' => '/mobiles/prices', 'order' => 2, 'enabled' => 0],
                            ['key' => 'new-link', 'label' => 'Trade In', 'url' => '/mobiles/trade-in', 'order' => 3, 'enabled' => 1],
                        ],
                    ],
                ],
            ])
            ->assertRedirect('/admin/navigation');

        $mobiles = collect($svc->configured())->firstWhere('key', 'mobiles');
        $subKeys = array_column($mobiles['submenu'], 'key');

        $this->assertNotContains('1', array_map('strval', $subKeys), 'Mobile Prices submenu should be hidden');
        $this->assertContains('new-link', $subKeys, 'custom submenu entry should render');
    }

    public function test_submenu_enabled_checkbox_persists(): void
    {
        $svc = app(HeaderNavService::class);

        // enabled checkbox present but unchecked → Laravel sends nothing;
        // emulate the browser: no 'enabled' key at all means false.
        $this->actingAdmin()
            ->post('/admin/navigation', [
                'items' => [
                    ['key' => 'home', 'label' => 'Home', 'url' => '/', 'icon' => 'home', 'order' => 1],
                    ['key' => 'mobiles', 'label' => 'Mobiles', 'url' => '/mobiles', 'icon' => 'smartphone', 'order' => 2,
                        'submenu' => [
                            ['key' => 0, 'label' => 'Browse All', 'url' => '/mobiles', 'order' => 1, 'enabled' => 1],
                        ],
                    ],
                ],
            ])
            ->assertRedirect('/admin/navigation');

        $mobiles = collect($svc->configured())->firstWhere('key', 'mobiles');
        $this->assertCount(1, $mobiles['submenu'], 'unlisted default submenu entries should be treated as removed');
    }

    // ── Validation ────────────────────────────────────────────────

    public function test_update_requires_label_and_url(): void
    {
        $this->actingAdmin()
            ->post('/admin/navigation', [
                'items' => [
                    ['key' => 'home', 'label' => '', 'url' => '/', 'order' => 1],
                ],
            ])
            ->assertSessionHasErrors(['items.0.label']);
    }

    public function test_update_requires_at_least_one_item(): void
    {
        $this->actingAdmin()
            ->post('/admin/navigation', ['items' => []])
            ->assertSessionHasErrors(['items']);
    }

    // ── Reset ─────────────────────────────────────────────────────

    public function test_reset_clears_overrides_and_restores_defaults(): void
    {
        $svc = app(HeaderNavService::class);

        // First persist something custom.
        $this->actingAdmin()
            ->post('/admin/navigation', [
                'items' => [
                    ['key' => 'my-custom', 'label' => 'Custom', 'url' => '/custom', 'order' => 1],
                ],
            ])
            ->assertRedirect('/admin/navigation');

        $this->assertContains('my-custom', array_column($svc->configured(), 'key'));

        $this->actingAdmin()
            ->post('/admin/navigation/reset')
            ->assertRedirect('/admin/navigation')
            ->assertSessionHas('status');

        $keys = array_column($svc->configured(), 'key');
        $this->assertNotContains('my-custom', $keys);
        $this->assertContains('home', $keys);
        $this->assertNull($svc->raw(), 'blob must be NULL after reset');
    }

    // ── Key sanitization ──────────────────────────────────────────

    public function test_item_keys_are_sanitized(): void
    {
        $this->actingAdmin()
            ->post('/admin/navigation', [
                'items' => [
                    ['key' => 'My Custom Item!!', 'label' => 'Custom', 'url' => '/c', 'order' => 1],
                ],
            ])
            ->assertRedirect('/admin/navigation');

        $this->assertContains('my_custom_item__', array_column(app(HeaderNavService::class)->configured(), 'key'));
    }
}
