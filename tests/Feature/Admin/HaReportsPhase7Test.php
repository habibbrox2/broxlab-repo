<?php

namespace Tests\Feature\Admin;

use App\Models\HaExpense;
use App\Support\HaSaleService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 7: reports & P&L — module revenue, transaction-level cost P&L,
 * expenses, exports, dashboard widget data.
 */
class HaReportsPhase7Test extends TestCase
{
    protected int $userId;

    protected array $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::getSchemaBuilder()->hasTable('ha_expenses')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/2026_09_23_000600_create_hero_alif_expenses_table.php', '--force' => true]);
        }

        $suffix = substr(uniqid('harep', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'harep_'.$suffix,
            'email' => 'harep_'.$suffix.'@example.test',
            'password' => bcrypt('Passw0rd!x'),
            'first_name' => 'Phase7',
            'last_name' => 'Reporter',
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
            'ha_expenses', 'ha_sale_payments', 'ha_sale_items', 'ha_sales',
            'ha_inventory_movements', 'ha_products',
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

    protected function product(array $overrides = []): int
    {
        $row = array_merge([
            'sku' => 'HA-R7-'.substr(uniqid('', true), -8),
            'name' => 'Phase7 Product',
            'slug' => 'r7-'.\Illuminate\Support\Str::random(8),
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

    protected function sale(int $pid, int $qty, array $overrides = []): array
    {
        $svc = app(HaSaleService::class);

        return DB::transaction(fn () => $svc->completeSale(
            [['product_id' => $pid, 'qty' => $qty]],
            [['method' => 'cash', 'amount' => $qty * 500]],
            array_merge(['cashier_id' => $this->userId], $overrides)
        ));
    }

    // ── P&L math from transaction-level cost snapshots ──────────────────

    public function test_summary_computes_profit_from_item_cost_snapshots(): void
    {
        // cost 80, retail 500 → 2 units: revenue 1000, cogs 160, gross 840
        $pid = $this->product();
        DB::table('ha_products')->where('id', $pid)->update(['stock_qty' => 10]);
        $this->sale($pid, 2);

        HaExpense::query()->create([
            'category' => 'transport', 'amount' => 40, 'expense_date' => today(), 'created_by' => $this->userId,
        ]);

        $reports = app(\App\Support\HaReportService::class);
        $s = $reports->summary(today()->toDateString(), today()->toDateString());

        $this->assertSame(1000.0, $s['revenue']);
        $this->assertSame(160.0, $s['cogs']);
        $this->assertSame(840.0, $s['gross_profit']);
        $this->assertSame(40.0, $s['expenses']);
        $this->assertSame(800.0, $s['net_profit']);
        $this->assertSame(2, $s['units_sold']);
    }

    public function test_historical_cost_change_does_not_rewrite_past_profit(): void
    {
        $pid = $this->product();
        DB::table('ha_products')->where('id', $pid)->update(['stock_qty' => 10]);
        $this->sale($pid, 1); // snapshot cost 80 at sale time

        // Change today's catalog cost — historical P&L must not move.
        DB::table('ha_products')->where('id', $pid)->update(['cost_price' => 300]);

        $reports = app(\App\Support\HaReportService::class);
        $s = $reports->summary(today()->toDateString(), today()->toDateString());

        $this->assertSame(80.0, $s['cogs'], 'P&L must use per-item snapshot, not current product cost');
        $this->assertSame(420.0, $s['gross_profit']);
    }

    public function test_module_filter_scopes_summary(): void
    {
        $bazar = $this->product(['module' => 'smart_bazar']);
        $oil = $this->product(['module' => 'mustard_oil']);
        DB::table('ha_products')->whereIn('id', [$bazar, $oil])->update(['stock_qty' => 10]);
        $this->sale($bazar, 1); // 500
        $this->sale($oil, 2);   // 1000

        $reports = app(\App\Support\HaReportService::class);
        $oilOnly = $reports->summary(today()->toDateString(), today()->toDateString(), 'mustard_oil');

        $this->assertSame(1000.0, $oilOnly['revenue']);
        $this->assertSame(2, $oilOnly['units_sold']);

        $byModule = collect($reports->byModule(today()->toDateString(), today()->toDateString()))->pluck('revenue', 'module');
        $this->assertEquals(500.0, $byModule['smart_bazar']);
        $this->assertEquals(1000.0, $byModule['mustard_oil']);
    }

    public function test_refund_reduces_net_profit(): void
    {
        $pid = $this->product();
        DB::table('ha_products')->where('id', $pid)->update(['stock_qty' => 10]);
        $result = $this->sale($pid, 1);

        $svc = app(HaSaleService::class);
        $svc->refundSale($result['id'], 'customer changed mind', $this->userId);

        $reports = app(\App\Support\HaReportService::class);
        $s = $reports->summary(today()->toDateString(), today()->toDateString());

        $this->assertSame(500.0, $s['refunds']);
        // revenue 500, cogs 80, refund 500 → net = 500 - 80 - 0 - 500 = -80
        $this->assertSame(-80.0, $s['net_profit']);
    }

    // ── Series / mix / export ───────────────────────────────────────────

    public function test_daily_series_and_payment_mix(): void
    {
        $pid = $this->product();
        DB::table('ha_products')->where('id', $pid)->update(['stock_qty' => 10]);
        $this->sale($pid, 1);

        $reports = app(\App\Support\HaReportService::class);
        $series = $reports->dailySeries(today()->toDateString(), today()->toDateString());
        $this->assertCount(1, $series);
        $this->assertSame(500.0, $series[0]['revenue']);
        $this->assertSame(420.0, $series[0]['profit']);

        $mix = $reports->paymentMix(today()->toDateString(), today()->toDateString());
        $this->assertEquals(500.0, $mix['cash']);
    }

    public function test_csv_export_downloads_for_admin(): void
    {
        $pid = $this->product();
        DB::table('ha_products')->where('id', $pid)->update(['stock_qty' => 5]);
        $this->sale($pid, 1);

        $res = $this->actingAs(\App\Models\User::find($this->userId))
            ->get('/admin/ha/reports/export/csv');

        $res->assertOk();
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('Content-Type'));
        $this->assertStringContainsString('Net profit', $res->getContent());
    }

    public function test_pdf_export_downloads_for_admin(): void
    {
        $pid = $this->product();
        DB::table('ha_products')->where('id', $pid)->update(['stock_qty' => 5]);
        $this->sale($pid, 1);

        $res = $this->actingAs(\App\Models\User::find($this->userId))
            ->get('/admin/ha/reports/export/pdf');

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $res->headers->get('Content-Type'));
    }

    public function test_reports_require_admin(): void
    {
        $this->get('/admin/ha/reports')->assertRedirect('/login');
        $this->get('/admin/ha/reports/export/csv')->assertRedirect('/login');
        $this->post('/admin/ha/expenses', ['category' => 'rent', 'amount' => 100, 'expense_date' => today()->toDateString()])
            ->assertRedirect('/login');
    }

    public function test_expense_validation_and_delete(): void
    {
        // future date rejected
        $res = $this->actingAs(\App\Models\User::find($this->userId))
            ->post('/admin/ha/expenses', [
                'category' => 'rent', 'amount' => 100, 'expense_date' => today()->addDay()->toDateString(),
            ]);
        $res->assertSessionHasErrors('expense_date');

        $this->actingAs(\App\Models\User::find($this->userId))
            ->post('/admin/ha/expenses', [
                'category' => 'utility', 'amount' => 250.5, 'note' => 'electricity',
                'expense_date' => today()->toDateString(),
            ])->assertRedirect('/admin/ha/reports');

        $exp = HaExpense::query()->where('note', 'electricity')->first();
        $this->assertNotNull($exp);
        $this->assertSame('250.50', (string) $exp->amount);

        $this->actingAs(\App\Models\User::find($this->userId))
            ->post('/admin/ha/expenses/delete/'.$exp->id)
            ->assertRedirect('/admin/ha/reports');
        $this->assertDatabaseMissing('ha_expenses', ['id' => $exp->id]);
    }

    public function test_reports_page_renders_for_admin(): void
    {
        $pid = $this->product();
        DB::table('ha_products')->where('id', $pid)->update(['stock_qty' => 5]);
        $this->sale($pid, 1);

        $res = $this->actingAs(\App\Models\User::find($this->userId))->get('/admin/ha/reports');
        $res->assertOk();
        $res->assertSee('Module-wise');
        $res->assertSee('৳500.00');
    }

    public function test_dashboard_renders_business_widgets(): void
    {
        $res = $this->actingAs(\App\Models\User::find($this->userId))->get('/admin/dashboard');
        $res->assertOk();
        $res->assertSee('Sales Today');
        $res->assertSee('This Month');
        $res->assertSee('Low Stock');
    }
}
