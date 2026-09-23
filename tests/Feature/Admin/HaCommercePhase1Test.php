<?php

namespace Tests\Feature\Admin;

use App\Support\HaInventoryService;
use App\Support\HaPurchaseService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hero Alif Phase 1 — commerce foundation.
 *
 * Tests run against the shared local schema (no RefreshDatabase — see
 * phpunit.xml): every row this suite inserts is removed in tearDown().
 * The migration is run first in setUp() if the tables are missing (the
 * shared dev DB may not have them yet); they are dropped afterwards.
 */
class HaCommercePhase1Test extends TestCase
{
    protected int $userId;

    protected bool $weCreatedTables = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::getSchemaBuilder()->hasTable('ha_products')) {
            $this->artisan('migrate', [
                '--path' => 'database/migrations/2026_09_23_000100_create_hero_alif_commerce_tables.php',
                '--force' => true,
            ]);
            $this->weCreatedTables = true;
        }

        $suffix = substr(uniqid('haadm', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'haadm_'.$suffix,
            'email' => 'haadm_'.$suffix.'@example.test',
            'password' => bcrypt('Passw0rd!x'),
            'first_name' => 'HA',
            'last_name' => 'Admin',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_roles')->insert(['user_id' => $this->userId, 'role_id' => 1, 'created_at' => now()]);
    }

    protected function tearDown(): void
    {
        foreach ([
            'ha_inventory_movements', 'ha_purchase_items', 'ha_purchases',
            'ha_products', 'ha_brands', 'ha_categories', 'ha_suppliers',
        ] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        DB::table('user_roles')->where('user_id', $this->userId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        DB::table('activity_logs')->where('user_id', $this->userId)->delete();

        parent::tearDown();
    }

    protected function admin(): static
    {
        $this->be(\App\Models\User::query()->findOrFail($this->userId));

        return $this;
    }

    // ── Schema & guards ─────────────────────────────────────────────────

    public function test_tables_exist_after_migration(): void
    {
        foreach (['ha_categories', 'ha_brands', 'ha_products', 'ha_inventory_movements', 'ha_suppliers', 'ha_purchases', 'ha_purchase_items'] as $t) {
            $this->assertSchemaHas($t);
        }
    }

    public function test_guest_cannot_access_admin_ha_pages(): void
    {
        $this->get('/admin/ha/products')->assertRedirect('/login');
        $this->post('/admin/ha/products/create', [])->assertRedirect('/login');
        $this->get('/admin/ha/purchases')->assertRedirect('/login');
    }

    // ── Inventory ledger integrity ──────────────────────────────────────

    public function test_movement_requires_transaction(): void
    {
        $pid = $this->createProduct();
        $svc = app(HaInventoryService::class);

        $this->expectException(\RuntimeException::class);
        $svc->recordMovement($pid, 'purchase', 5);
    }

    public function test_opening_movement_sets_balance(): void
    {
        $pid = $this->createProduct();
        $svc = app(HaInventoryService::class);

        DB::transaction(fn () => $svc->recordOpening($pid, 25, $this->userId));

        $this->assertSame(25, (int) DB::table('ha_products')->where('id', $pid)->value('stock_qty'));

        $mv = DB::table('ha_inventory_movements')->where('product_id', $pid)->first();
        $this->assertSame('opening', $mv->type);
        $this->assertSame(25, (int) $mv->balance_after);
    }

    public function test_movement_rejects_negative_stock(): void
    {
        $pid = $this->createProduct(['initial_stock_qty' => 0]);
        $svc = app(HaInventoryService::class);

        $this->expectException(\RuntimeException::class);
        DB::transaction(fn () => $svc->recordMovement($pid, 'sale', -3));
    }

    public function test_movement_direction_must_match_type(): void
    {
        $pid = $this->createProduct();
        $svc = app(HaInventoryService::class);

        try {
            DB::transaction(fn () => $svc->recordMovement($pid, 'purchase', -5));
            $this->fail('Negative purchase qty should be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('positive', $e->getMessage());
        }
    }

    // ── Purchase flow (transactional stock-in) ──────────────────────────

    public function test_purchase_creates_rows_movements_and_stock(): void
    {
        $pid = $this->createProduct();
        $svc = app(HaPurchaseService::class);

        $result = DB::transaction(fn () => $svc->createPurchase(
            ['purchase_date' => now()->toDateString(), 'payment_method' => 'cash', 'paid_amount' => 900, 'discount' => 100, 'transport_cost' => 50],
            [['product_id' => $pid, 'qty' => 10, 'unit_cost' => 100]],
            $this->userId
        ));

        $this->assertSame('PINV-'.now()->format('ymd').'-0001', $result['invoice_no']);
        $this->assertSame(950.0, $result['grand_total']); // 1000 - 100 + 50
        $this->assertSame(50.0, $result['due_amount']);   // 950 - 900

        $this->assertSame(10, (int) DB::table('ha_products')->where('id', $pid)->value('stock_qty'));
        $this->assertSame(1, (int) DB::table('ha_purchases')->count());
        $this->assertSame(1, (int) DB::table('ha_purchase_items')->count());
        $this->assertSame(1, (int) DB::table('ha_inventory_movements')->where('type', 'purchase')->count());
    }

    public function test_purchase_rolls_back_on_bad_item(): void
    {
        $pid = $this->createProduct();
        $svc = app(HaPurchaseService::class);

        try {
            DB::transaction(fn () => $svc->createPurchase(
                ['purchase_date' => now()->toDateString(), 'payment_method' => 'cash'],
                [['product_id' => $pid, 'qty' => 5, 'unit_cost' => 10], ['product_id' => $pid, 'qty' => 0, 'unit_cost' => 10]],
                $this->userId
            ));
            $this->fail('Zero-qty item should abort the purchase.');
        } catch (\InvalidArgumentException $e) {
            // expected
        }

        $this->assertSame(0, (int) DB::table('ha_purchases')->count());
        $this->assertSame(0, (int) DB::table('ha_products')->where('id', $pid)->value('stock_qty'));
    }

    // ── Admin CRUD via HTTP ─────────────────────────────────────────────

    public function test_admin_can_create_product_via_form(): void
    {
        $this->admin()
            ->post('/admin/ha/products/create', [
                'sku' => 'HA-TEST-001',
                'name' => 'Test Gadget',
                'module' => 'smart_bazar',
                'unit' => 'pcs',
                'cost_price' => '150',
                'retail_price' => '200',
                'initial_stock_qty' => '7',
                'is_physical' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/ha/products');

        $p = DB::table('ha_products')->where('sku', 'HA-TEST-001')->first();
        $this->assertNotNull($p);
        $this->assertSame(7, (int) $p->stock_qty);

        $opening = DB::table('ha_inventory_movements')->where('product_id', $p->id)->where('type', 'opening')->first();
        $this->assertNotNull($opening);
    }

    public function test_admin_pages_render(): void
    {
        $this->createProduct();

        $this->admin();
        $this->get('/admin/ha/products')->assertOk();
        $this->get('/admin/ha/products/create')->assertOk();
        $this->get('/admin/ha/categories')->assertOk();
        $this->get('/admin/ha/brands')->assertOk();
        $this->get('/admin/ha/suppliers')->assertOk();
        $this->get('/admin/ha/purchases')->assertOk();
        $this->get('/admin/ha/inventory/movements')->assertOk();
    }

    // ── Storefront ──────────────────────────────────────────────────────

    public function test_storefront_lists_active_products_and_detail(): void
    {
        $this->createProduct(['name' => 'Public Oil Bottle', 'module' => 'mustard_oil']);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('Public Oil Bottle', false);

        $this->get('/shop/mustard-oil')->assertOk();

        $slug = DB::table('ha_products')->where('name', 'Public Oil Bottle')->value('slug');
        $this->get('/shop/mustard-oil/'.$slug)->assertOk()->assertSee('Public Oil Bottle');

        $this->get('/shop/mustard-oil/does-not-exist')->assertNotFound();
    }

    public function test_storefront_hides_inactive_products(): void
    {
        $this->createProduct(['name' => 'Hidden Item', 'is_active' => 0]);

        $this->get('/shop/smart-bazar')->assertOk()->assertDontSee('Hidden Item');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function createProduct(array $overrides = []): int
    {
        $row = array_merge([
            'sku' => 'HA-MV-'.substr(uniqid('', true), -8),
            'name' => 'Movement Test Product',
            'slug' => 'mv-'.\Illuminate\Support\Str::random(8),
            'module' => 'smart_bazar',
            'unit' => 'pcs',
            'is_physical' => 1,
            'is_active' => 1,
            'cost_price' => 10,
            'retail_price' => 20,
            'stock_qty' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        if (isset($row['initial_stock_qty'])) {
            $initial = (int) \Illuminate\Support\Arr::pull($row, 'initial_stock_qty');
        }

        $pid = DB::table('ha_products')->insertGetId($row);

        if (isset($initial) && $initial > 0) {
            DB::table('ha_inventory_movements')->insert([
                'product_id' => $pid, 'type' => 'opening', 'qty' => $initial,
                'balance_after' => $initial, 'reference_type' => 'manual',
                'created_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ha_products')->where('id', $pid)->update(['stock_qty' => $initial]);
        }

        return $pid;
    }

    private function assertSchemaHas(string $table): void
    {
        $this->assertTrue(DB::getSchemaBuilder()->hasTable($table), "Table {$table} should exist.");
    }
}
