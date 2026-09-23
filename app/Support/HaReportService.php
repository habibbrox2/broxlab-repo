<?php

namespace App\Support;

use App\Models\HaExpense;
use App\Models\HaProduct;
use App\Models\HaSale;
use App\Models\HaSaleItem;
use App\Models\HaSalePayment;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7: business reports & analytics.
 *
 * All revenue numbers come from posted payments (ha_sale_payments.amount,
 * refunds stored negative) — never from recalculated item totals. Cost comes
 * from the per-item unit_cost snapshot taken at sale time, so historical P&L
 * stays correct even when product costs change later (spec §46).
 */
class HaReportService
{
    public const MODULES = HaProduct::MODULES;

    public function __construct()
    {
    }

    /** Sales + service fees in [from, to], optionally scoped to one module. */
    public function summary(string $from, string $to, ?string $module = null): array
    {
        $items = HaSaleItem::query()
            ->join('ha_sales as s', 's.id', '=', 'ha_sale_items.sale_id')
            ->whereBetween(DB::raw('date(s.created_at)'), [$from, $to])
            ->when($module !== null, fn ($q) => $q->where('ha_sale_items.module', $module));

        $revenue = (float) (clone $items)->selectRaw('COALESCE(SUM(ha_sale_items.line_total),0) as agg')->value('agg');
        $cogs    = (float) (clone $items)->selectRaw('COALESCE(SUM(ha_sale_items.qty * COALESCE(ha_sale_items.unit_cost, 0)),0) as agg')->value('agg');
        $units   = (int) (clone $items)->selectRaw('COALESCE(SUM(ha_sale_items.qty),0) as agg')->value('agg');

        // Service fees: sales with no items are fee-only (digital/repair jobs).
        // Their value sits in grand_total; item sales keep it in line totals.
        $serviceFees = (float) HaSale::query()
            ->whereBetween(DB::raw('date(created_at)'), [$from, $to])
            ->whereDoesntHave('items')
            ->where('status', 'completed')
            ->sum('grand_total');

        $refunds = (float) HaSalePayment::query()
            ->whereBetween(DB::raw('date(created_at)'), [$from, $to])
            ->where('kind', 'refund')
            ->sum('amount');

        $expenses = (float) HaExpense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        $grossProfit = $revenue + $serviceFees - $cogs;
        $netProfit   = $grossProfit - $refunds - $expenses;

        return [
            'revenue'       => $revenue,
            'service_fees'  => $serviceFees,
            'cogs'          => $cogs,
            'gross_profit'  => $grossProfit,
            'refunds'       => $refunds,
            'expenses'      => $expenses,
            'net_profit'    => $netProfit,
            'margin'        => $revenue + $serviceFees > 0 ? round($netProfit / ($revenue + $serviceFees) * 100, 2) : 0.0,
            'units_sold'    => $units,
        ];
    }

    /** Per-module revenue/cost/profit breakdown for the period. */
    public function byModule(string $from, string $to): array
    {
        $rows = HaSaleItem::query()
            ->join('ha_sales as s', 's.id', '=', 'ha_sale_items.sale_id')
            ->whereBetween(DB::raw('date(s.created_at)'), [$from, $to])
            ->groupBy('ha_sale_items.module')
            ->orderByDesc(DB::raw('SUM(ha_sale_items.line_total)'))
            ->get([
                'ha_sale_items.module',
                DB::raw('SUM(ha_sale_items.qty) as units'),
                DB::raw('SUM(ha_sale_items.line_total) as revenue'),
                DB::raw('SUM(ha_sale_items.qty * COALESCE(ha_sale_items.unit_cost,0)) as cost'),
            ])
            ->map(function ($r) {
                $rev = (float) $r->revenue;

                return [
                    'module'  => $r->module,
                    'units'   => (int) $r->units,
                    'revenue' => $rev,
                    'cost'    => (float) $r->cost,
                    'profit'  => $rev - (float) $r->cost,
                    'margin'  => $rev > 0 ? round(($rev - (float) $r->cost) / $rev * 100, 1) : 0.0,
                ];
            })
            ->all();

        // Module comes from the per-item snapshot column on ha_sale_items,
        // so item sales are fully attributed here. Fee-only sales (no items)
        // have no module snapshot yet — they surface in summary()['service_fees']
        // until service billing stores one on the sale row.

        return $rows;
    }

    /** Daily net-payment revenue + profit series for charts. */
    public function dailySeries(string $from, string $to): array
    {
        $days = [];
        $cur = strtotime($from);
        $end = strtotime($to);
        while ($cur <= $end) {
            $days[date('Y-m-d', $cur)] = ['date' => date('Y-m-d', $cur), 'revenue' => 0.0, 'profit' => 0.0];
            $cur = strtotime('+1 day', $cur);
        }

        $pay = HaSalePayment::query()
            ->whereBetween(DB::raw('date(created_at)'), [$from, $to])
            ->selectRaw('date(created_at) as d, SUM(amount) as total')
            ->groupBy(DB::raw('date(created_at)'))
            ->pluck('total', 'd');

        $profit = HaSaleItem::query()
            ->join('ha_sales as s', 's.id', '=', 'ha_sale_items.sale_id')
            ->whereBetween(DB::raw('date(s.created_at)'), [$from, $to])
            ->selectRaw('date(s.created_at) as d, SUM(ha_sale_items.qty * (ha_sale_items.unit_price - COALESCE(ha_sale_items.unit_cost,0))) as total')
            ->groupBy(DB::raw('date(s.created_at)'))
            ->pluck('total', 'd');

        foreach ($days as $day => &$row) {
            $row['revenue'] = (float) ($pay[$day] ?? 0);
            $row['profit'] = (float) ($profit[$day] ?? 0);
        }
        unset($row);

        return array_values($days);
    }

    /** Best sellers by revenue for the period. */
    public function topProducts(string $from, string $to, int $limit = 8): array
    {
        return HaSaleItem::query()
            ->join('ha_sales as s', 's.id', '=', 'ha_sale_items.sale_id')
            ->join('ha_products as p', 'p.id', '=', 'ha_sale_items.product_id')
            ->whereBetween(DB::raw('date(s.created_at)'), [$from, $to])
            ->groupBy('ha_sale_items.product_id', 'p.name')
            ->orderByDesc(DB::raw('SUM(ha_sale_items.line_total)'))
            ->limit($limit)
            ->get([
                'ha_sale_items.product_id',
                'p.name',
                DB::raw('SUM(ha_sale_items.qty) as units'),
                DB::raw('SUM(ha_sale_items.line_total) as revenue'),
                DB::raw('SUM(ha_sale_items.qty * (ha_sale_items.unit_price - COALESCE(ha_sale_items.unit_cost,0))) as profit'),
            ])
            ->map(fn ($r) => [
                'product_id' => (int) $r->product_id,
                'name'       => $r->name,
                'units'      => (int) $r->units,
                'revenue'    => (float) $r->revenue,
                'profit'     => (float) $r->profit,
            ])
            ->all();
    }

    /** Payment-method split (cash, bkash, nagad, due...) for the period. */
    public function paymentMix(string $from, string $to): array
    {
        return HaSalePayment::query()
            ->whereBetween(DB::raw('date(created_at)'), [$from, $to])
            ->selectRaw('method, COALESCE(SUM(amount),0) as total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->pluck('total', 'method')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    public function expensesBetween(string $from, string $to)
    {
        return HaExpense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();
    }

    /** Business widget numbers for the admin dashboard. */
    public function dashboardWidgets(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $todaySummary = $this->summary($today, $today);
        $monthSummary = $this->summary($monthStart, $today);

        $lowStock = HaProduct::query()
            ->where('is_physical', 1)
            ->whereColumn('stock_qty', '<=', 'reorder_level')
            ->orderBy('stock_qty')
            ->limit(5)
            ->get(['id', 'name', 'stock_qty', 'reorder_level', 'module']);
        $pendingServices = DB::table('ha_service_requests')
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        $recentSales = HaSale::query()->with('customer')
            ->latest('id')->limit(5)->get();

        return [
            'today'            => $todaySummary,
            'month'            => $monthSummary,
            'low_stock'        => $lowStock,
            'pending_services' => $pendingServices,
            'recent_sales'     => $recentSales,
        ];
    }
}
