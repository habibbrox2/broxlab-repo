<?php

namespace Tests\Feature\Admin;

use App\Models\HaOrder;
use App\Support\HaOrderService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hero Alif Phase 4 — online orders.
 */
class HaOrdersPhase4Test extends TestCase
{
    protected int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::getSchemaBuilder()->hasTable('ha_orders')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/2026_09_23_000400_create_hero_alif_orders_tables.php', '--force' => true]);
        }

        $suffix = substr(uniqid('haord', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'haord_'.$suffix,
            'email' => 'haord_'.$suffix.'@example.test',
            'password' => bcrypt('Passw0rd!x'),
            'first_name' => 'Ord',
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
            'ha_order_status_history', 'ha_order_items', 'ha_orders',
            'ha_sale_payments', 'ha_sale_items', 'ha_sales',
            'ha_customer_ledger', 'ha_customers',
            'ha_inventory_movements', 'ha_products',
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

    protected function productWithStock(int $qty, float $price = 500): int
    {
        return DB::table('ha_products')->insertGetId([
            'sku' => 'HA-P4-'.substr(uniqid('', true), -8),
            'name' => 'P4 Product',
            'slug' => 'p4-'.\Illuminate\Support\Str::random(8),
            'module' => 'smart_bazar',
            'unit' => 'pcs',
            'is_physical' => 1,
            'is_active' => 1,
            'cost_price' => 100,
            'retail_price' => $price,
            'stock_qty' => $qty,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_place_order_resolves_server_prices_and_flags_oversell(): void
    {
        $pid = $this->productWithStock(5, 500);
        $svc = app(HaOrderService::class);

        $order = $svc->placeOrder(
            [['product_id' => $pid, 'qty' => 2, 'unit_price' => 1]], // client price ignored
            ['name' => 'Web Customer', 'mobile' => '01712345678', 'address' => 'Dhaka'],
            ['shipping_fee' => 60]
        );

        $row = HaOrder::query()->findOrFail($order['id']);
        $this->assertEquals(1060.0, (float) $row->grand_total); // 1000 + 60 shipping
        $this->assertSame('pending', $row->status);
        $this->assertMatchesRegularExpression('/^HA[A-Z0-9]{8}$/', $row->tracking_id);

        // Oversell must fail.
        $this->expectException(\InvalidArgumentException::class);
        $svc->placeOrder(
            [['product_id' => $pid, 'qty' => 50]],
            ['name' => 'X', 'mobile' => '01799999999', 'address' => 'X']
        );
    }

    public function test_status_flow_enforced_and_confirm_creates_sale(): void
    {
        $pid = $this->productWithStock(10, 500);
        $svc = app(HaOrderService::class);

        $order = $svc->placeOrder(
            [['product_id' => $pid, 'qty' => 3]],
            ['name' => 'Flow Customer', 'mobile' => '01700000001', 'address' => 'Dhaka']
        );

        // Illegal jump: pending → delivered must fail.
        try {
            $svc->transition($order['id'], 'delivered', 'skip', $this->userId);
            $this->fail('Illegal transition must fail.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot move order', $e->getMessage());
        }

        // Stock NOT deducted while pending.
        $this->assertSame(10, (int) DB::table('ha_products')->where('id', $pid)->value('stock_qty'));

        $svc->transition($order['id'], 'confirmed', 'verified by phone', $this->userId);

        // Confirm deducts stock via the sale engine.
        $this->assertSame(7, (int) DB::table('ha_products')->where('id', $pid)->value('stock_qty'));

        $row = HaOrder::query()->findOrFail($order['id']);
        $this->assertSame('confirmed', $row->status);
        $this->assertNotNull($row->sale_id);
        $this->assertSame('online', DB::table('ha_sales')->where('id', $row->sale_id)->value('sale_type'));

        // Double-confirm must not double-deduct (idempotent).
        $svc->transition($row->id, 'processing', 'packing', $this->userId);
        $this->assertSame(7, (int) DB::table('ha_products')->where('id', $pid)->value('stock_qty'));
    }

    public function test_tracking_page_shows_status_without_admin_pages(): void
    {
        $pid = $this->productWithStock(3, 200);
        $svc = app(HaOrderService::class);
        $order = $svc->placeOrder(
            [['product_id' => $pid, 'qty' => 1]],
            ['name' => 'Trk Customer', 'mobile' => '01700000002', 'address' => 'Dhaka']
        );

        $row = HaOrder::query()->findOrFail($order['id']);

        $this->get('/track?code='.$row->tracking_id)
            ->assertOk()
            ->assertSee($row->tracking_id)
            ->assertSee('pending');

        $this->get('/track?code=NOPE1234')->assertOk()->assertSee('পাওয়া যায়নি');

        // Admin pages guard.
        $this->get('/admin/ha/orders')->assertRedirect('/login');
    }

    public function test_admin_order_pages_render(): void
    {
        $this->be(\App\Models\User::query()->findOrFail($this->userId));
        $this->get('/admin/ha/orders')->assertOk();

        $pid = $this->productWithStock(2, 100);
        $order = app(HaOrderService::class)->placeOrder(
            [['product_id' => $pid, 'qty' => 1]],
            ['name' => 'A', 'mobile' => '01700000003', 'address' => 'B']
        );
        $this->get('/admin/ha/orders/'.$order['id'])->assertOk();
    }
}
