<?php

namespace Tests\Feature\Admin;

use App\Models\HaProduct;
use App\Support\HaCashRegisterService;
use App\Support\HaSaleService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hero Alif Phase 2 — POS, sales, payments, cash register.
 */
class HaSalesPhase2Test extends TestCase
{
    protected int $userId;

    protected array $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ha_sales', 'ha_sale_items', 'ha_sale_payments', 'ha_cash_registers', 'ha_cash_register_transactions'] as $t) {
            if (! DB::getSchemaBuilder()->hasTable($t)) {
                $this->artisan('migrate', ['--path' => 'database/migrations/2026_09_23_000200_create_hero_alif_sales_tables.php', '--force' => true]);
                break;
            }
        }

        $suffix = substr(uniqid('hapos', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'hapos_'.$suffix,
            'email' => 'hapos_'.$suffix.'@example.test',
            'password' => bcrypt('Passw0rd!x'),
            'first_name' => 'Pos',
            'last_name' => 'Cashier',
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
            'ha_cash_register_transactions', 'ha_cash_registers', 'ha_sale_payments',
            'ha_sale_items', 'ha_sales', 'ha_inventory_movements',
            'ha_products', 'ha_purchases',
            'ha_customer_payments', 'ha_customer_ledger', 'ha_customers',
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

    protected function makeCustomer(): int
    {
        return DB::table('ha_customers')->insertGetId([
            'name' => 'Phase2 Customer',
            'mobile' => '018'.random_int(10000000, 99999999),
            'due_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function product(array $overrides = []): int
    {
        $row = array_merge([
            'sku' => 'HA-S2-'.substr(uniqid('', true), -8),
            'name' => 'Phase2 Product',
            'slug' => 'p2-'.\Illuminate\Support\Str::random(8),
            'module' => 'smart_bazar',
            'unit' => 'pcs',
            'is_physical' => 1,
            'is_active' => 1,
            'cost_price' => 80,
            'retail_price' => 500,
            'stock_qty' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        $pid = DB::table('ha_products')->insertGetId($row);
        $this->productIds[] = $pid;

        return $pid;
    }

    protected function giveStock(int $pid, int $qty, float $cost = 80): void
    {
        DB::table('ha_products')->where('id', $pid)->update(['stock_qty' => $qty]);
    }

    // ── The spec's headline case: mixed payments ────────────────────────

    public function test_spec_mixed_payment_case(): void
    {
        $pid = $this->product();
        $this->giveStock($pid, 10);

        // 3 × ৳500 = ৳1500: Cash ৳500 + bKash ৳700 + Due ৳300
        $svc = app(HaSaleService::class);
        $result = DB::transaction(fn () => $svc->completeSale(
            [['product_id' => $pid, 'qty' => 3]],
            [
                ['method' => 'cash', 'amount' => 500],
                ['method' => 'bkash', 'amount' => 700, 'reference' => 'BKS123'],
                ['method' => 'due', 'amount' => 300], // intent; server computes due
            ],
            ['cashier_id' => $this->userId, 'customer_id' => $this->makeCustomer()]
        ));

        $this->assertSame(1500.0, $result['grand_total']);
        $this->assertSame(1200.0, $result['paid']);
        $this->assertSame(300.0, $result['due']);
        $this->assertSame(0.0, $result['change']);

        $this->assertSame(7, (int) DB::table('ha_products')->where('id', $pid)->value('stock_qty'));

        $methods = DB::table('ha_sale_payments')->where('sale_id', $result['id'])->pluck('amount', 'method');
        $this->assertEquals(500.0, $methods['cash']);
        $this->assertEquals(700.0, $methods['bkash']);
        $this->assertEquals(300.0, $methods['due']);
    }

    public function test_cash_over_tender_becomes_change(): void
    {
        $pid = $this->product();
        $this->giveStock($pid, 5);

        $svc = app(HaSaleService::class);
        $result = DB::transaction(fn () => $svc->completeSale(
            [['product_id' => $pid, 'qty' => 1]],           // ৳500
            [['method' => 'cash', 'amount' => 1000]],        // pays ৳1000
            ['cashier_id' => $this->userId]
        ));

        $this->assertSame(500.0, $result['paid']);
        $this->assertSame(500.0, $result['change']);
        $this->assertSame(0.0, $result['due']);
    }

    public function test_client_price_is_ignored(): void
    {
        $pid = $this->product(['retail_price' => 500]);
        $this->giveStock($pid, 5);

        $svc = app(HaSaleService::class);
        // Client "claims" price 100 — server must use DB price.
        $result = DB::transaction(fn () => $svc->completeSale(
            [['product_id' => $pid, 'qty' => 1, 'unit_price' => 100]],
            [['method' => 'cash', 'amount' => 100]],
            ['cashier_id' => $this->userId, 'customer_id' => $this->makeCustomer()]
        ));

        $this->assertSame(500.0, $result['grand_total']);
        $this->assertSame(400.0, $result['due']); // only ৳100 cash arrived
    }

    public function test_insufficient_stock_aborts_sale(): void
    {
        $pid = $this->product();
        $this->giveStock($pid, 2);

        $svc = app(HaSaleService::class);

        try {
            DB::transaction(fn () => $svc->completeSale(
                [['product_id' => $pid, 'qty' => 5]],
                [['method' => 'cash', 'amount' => 2500]],
                ['cashier_id' => $this->userId]
            ));
            $this->fail('Oversell must fail.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        // Nothing was written.
        $this->assertSame(0, (int) DB::table('ha_sales')->count());
        $this->assertSame(2, (int) DB::table('ha_products')->where('id', $pid)->value('stock_qty'));
    }

    public function test_refund_restores_stock_and_writes_compensating_rows(): void
    {
        $pid = $this->product();
        $this->giveStock($pid, 10);

        $svc = app(HaSaleService::class);
        $sale = DB::transaction(fn () => $svc->completeSale(
            [['product_id' => $pid, 'qty' => 4]],
            [['method' => 'cash', 'amount' => 2000]],
            ['cashier_id' => $this->userId]
        ));

        DB::transaction(fn () => $svc->refundSale($sale['id'], 'damaged box', $this->userId));

        $this->assertSame(10, (int) DB::table('ha_products')->where('id', $pid)->value('stock_qty'));
        $this->assertSame('refunded', DB::table('ha_sales')->where('id', $sale['id'])->value('status'));
        $this->assertSame(1, (int) DB::table('ha_sale_payments')->where('sale_id', $sale['id'])->where('kind', 'refund')->count());
        $this->assertSame(1, (int) DB::table('ha_inventory_movements')->where('reference_id', $sale['id'])->where('type', 'sale_return')->count());
    }

    public function test_hold_and_resume_roundtrip(): void
    {
        $pid = $this->product();
        $this->giveStock($pid, 10);

        $svc = app(HaSaleService::class);
        $heldId = DB::transaction(fn () => $svc->holdSale([['product_id' => $pid, 'qty' => 2]], ['note' => 'customer at ATM']));

        // Held sale does NOT deduct stock.
        $this->assertSame(10, (int) DB::table('ha_products')->where('id', $pid)->value('stock_qty'));

        $resumed = $svc->resumeSale($heldId);
        $this->assertSame([['product_id' => $pid, 'qty' => 2]], $resumed['items']);
    }

    // ── Cash register ───────────────────────────────────────────────────

    public function test_register_open_close_with_difference(): void
    {
        $reg = app(HaCashRegisterService::class);
        $session = $reg->open($this->userId, 500);

        $this->assertSame('open', $session->status);

        // A second open must fail.
        try {
            $reg->open($this->userId, 100);
            $this->fail('Second open must fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already open', $e->getMessage());
        }

        $reg->recordTransaction($session, 'cash_sale', 1500, 'ha_sales', 1, 'test sale', $this->userId);
        $reg->recordTransaction($session, 'cash_out', -200, 'manual', null, 'tea', $this->userId);

        $summary = $reg->summary($session->refresh());
        $this->assertSame(1800.0, $summary['expected_cash']); // 500 + 1500 − 200

        $closed = $reg->close($session->refresh(), 1750, 'counted', $this->userId);
        $this->assertSame('closed', $closed->status);
        $this->assertEquals(-50.0, (float) $closed->difference);
    }

    public function test_guest_cannot_reach_pos(): void
    {
        $this->get('/admin/ha/pos')->assertRedirect('/login');
        $this->get('/admin/ha/register')->assertRedirect('/login');
    }

    public function test_admin_pos_pages_render(): void
    {
        $this->be(\App\Models\User::query()->findOrFail($this->userId));
        $this->get('/admin/ha/pos')->assertOk();
        $this->get('/admin/ha/sales')->assertOk();
        $this->get('/admin/ha/register')->assertOk();
    }
}
