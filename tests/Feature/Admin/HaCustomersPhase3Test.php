<?php

namespace Tests\Feature\Admin;

use App\Models\HaCustomer;
use App\Support\HaCustomerLedgerService;
use App\Support\HaSaleService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hero Alif Phase 3 — CRM + customer due ledger.
 */
class HaCustomersPhase3Test extends TestCase
{
    protected int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::getSchemaBuilder()->hasTable('ha_customers')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/2026_09_23_000300_create_hero_alif_customers_tables.php', '--force' => true]);
        }

        $suffix = substr(uniqid('hacrm', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'hacrm_'.$suffix,
            'email' => 'hacrm_'.$suffix.'@example.test',
            'password' => bcrypt('Passw0rd!x'),
            'first_name' => 'Crm',
            'last_name' => 'Tester',
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
            'ha_customer_payments', 'ha_customer_ledger', 'ha_customers',
            'ha_sale_payments', 'ha_sale_items', 'ha_sales',
            'ha_inventory_movements', 'ha_products', 'ha_cash_register_transactions', 'ha_cash_registers',
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

    protected function customer(array $overrides = []): HaCustomer
    {
        return HaCustomer::query()->create(array_merge([
            'name' => 'Test Customer',
            'mobile' => '017'.random_int(10000000, 99999999),
            'customer_type' => 'retail',
            'due_balance' => 0,
        ], $overrides));
    }

    protected function productWithStock(int $qty = 10, float $price = 500): int
    {
        $pid = DB::table('ha_products')->insertGetId([
            'sku' => 'HA-P3-'.substr(uniqid('', true), -8),
            'name' => 'P3 Product',
            'slug' => 'p3-'.\Illuminate\Support\Str::random(8),
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

        return $pid;
    }

    public function test_ledger_requires_transaction(): void
    {
        $svc = app(HaCustomerLedgerService::class);
        $c = $this->customer();

        $this->expectException(\RuntimeException::class);
        $svc->postEntry($c->id, 'credit_sale', 100);
    }

    public function test_credit_sale_creates_ledger_entry_and_balance(): void
    {
        $c = $this->customer();
        $pid = $this->productWithStock(10, 500);

        $svc = app(HaSaleService::class);
        DB::transaction(fn () => $svc->completeSale(
            [['product_id' => $pid, 'qty' => 2]],                 // ৳1000
            [['method' => 'cash', 'amount' => 600]],              // 600 paid
            ['cashier_id' => $this->userId, 'customer_id' => $c->id]
        ));

        $c->refresh();
        $this->assertEquals(400.0, (float) $c->due_balance);

        $entry = DB::table('ha_customer_ledger')->where('customer_id', $c->id)->first();
        $this->assertSame('credit_sale', $entry->type);
        $this->assertEquals(400.0, (float) $entry->amount);
        $this->assertEquals(400.0, (float) $entry->balance_after);
    }

    public function test_collection_reduces_due_and_is_audited(): void
    {
        $c = $this->customer();
        $svc = app(HaCustomerLedgerService::class);

        DB::transaction(fn () => $svc->postEntry($c->id, 'opening_due', 1000, 'manual', null, 'old due', $this->userId));

        $result = $svc->collectPayment($c->id, 650, 'bkash', null, 'BKS-99', 'part payment', $this->userId);

        $this->assertSame(650.0, $result['amount']);
        $this->assertSame(350.0, $result['remaining_due']);

        $c->refresh();
        $this->assertEquals(350.0, (float) $c->due_balance);
        $this->assertSame(1, (int) DB::table('ha_customer_payments')->where('customer_id', $c->id)->count());
    }

    public function test_ledger_guard_blocks_negative_due(): void
    {
        $c = $this->customer();
        $svc = app(HaCustomerLedgerService::class);

        $this->expectException(\RuntimeException::class);
        DB::transaction(fn () => $svc->collectPayment($c->id, 100, 'cash'));
    }

    public function test_full_payment_flow_roundtrip(): void
    {
        $c = $this->customer();
        $pid = $this->productWithStock(10, 500);

        $sales = app(HaSaleService::class);
        $ledger = app(HaCustomerLedgerService::class);

        $sale = DB::transaction(fn () => $sales->completeSale(
            [['product_id' => $pid, 'qty' => 4]],                 // ৳2000
            [['method' => 'due', 'amount' => 2000]],              // full credit
            ['cashier_id' => $this->userId, 'customer_id' => $c->id]
        ));

        $c->refresh();
        $this->assertEquals(2000.0, (float) $c->due_balance);

        // Two partial collections clear it.
        $ledger->collectPayment($c->id, 1200, 'cash', $sale['id'], null, null, $this->userId);
        $final = $ledger->collectPayment($c->id, 800, 'bkash', $sale['id'], 'BKS-1', null, $this->userId);

        $this->assertSame(0.0, $final['remaining_due']);
        $c->refresh();
        $this->assertEquals(0.0, (float) $c->due_balance);

        // Ledger history intact: 1 credit + 2 payments, balances monotonic.
        $rows = DB::table('ha_customer_ledger')->where('customer_id', $c->id)->orderBy('id')->get();
        $this->assertSame(3, $rows->count());
        $this->assertEquals(2000.0, (float) $rows[0]->balance_after);
        $this->assertEquals(800.0, (float) $rows[1]->balance_after);
        $this->assertEquals(0.0, (float) $rows[2]->balance_after);
    }

    public function test_guest_and_customers_pages_render(): void
    {
        $this->get('/admin/ha/customers')->assertRedirect('/login');

        $this->be(\App\Models\User::query()->findOrFail($this->userId));
        $c = $this->customer();
        $this->get('/admin/ha/customers')->assertOk();
        $this->get('/admin/ha/customers/'.$c->id)->assertOk();
    }
}
