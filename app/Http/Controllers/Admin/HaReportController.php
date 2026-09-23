<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HaExpense;
use App\Support\ActivityLogger;
use App\Support\HaInvoicePdfService;
use App\Support\HaReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Response as Res;
use Illuminate\View\View;

/**
 * Phase 7: business reports & analytics.
 * GET /admin/ha/reports            — summary + module P&L + charts + top products
 * GET /admin/ha/reports/export/csv — flat CSV of the current filter
 * GET /admin/ha/reports/export/pdf — printable P&L statement
 * POST /admin/ha/expenses          — quick expense entry
 */
class HaReportController extends Controller
{
    public function __construct(
        protected HaReportService $reports,
        protected HaInvoicePdfService $pdf,
    ) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $module = $request->filled('module') ? (string) $request->input('module') : null;

        $summary = $this->reports->summary($from, $to, $module);
        $byModule = $module === null ? $this->reports->byModule($from, $to) : [];
        $series = $this->reports->dailySeries($from, $to);
        $topProducts = $this->reports->topProducts($from, $to);
        $paymentMix = $this->reports->paymentMix($from, $to);
        $expenses = $this->reports->expensesBetween($from, $to);

        return view('admin.ha.reports', [
            'title'       => 'Reports & P&L',
            'from'        => $from,
            'to'          => $to,
            'module'      => $module,
            'summary'     => $summary,
            'byModule'    => $byModule,
            'series'      => $series,
            'topProducts' => $topProducts,
            'paymentMix'  => $paymentMix,
            'expenses'    => $expenses,
            'modules'     => HaReportService::MODULES,
        ]);
    }

    public function exportCsv(Request $request): Response
    {
        [$from, $to] = $this->range($request);
        $module = $request->filled('module') ? (string) $request->input('module') : null;
        $summary = $this->reports->summary($from, $to, $module);
        $byModule = $this->reports->byModule($from, $to);
        $paymentMix = $this->reports->paymentMix($from, $to);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Hero Alif Group — P&L report']);
        fputcsv($out, ['Period', $from . ' to ' . $to, 'Module', $module ?? 'all']);
        fputcsv($out, []);
        fputcsv($out, ['Metric', 'Amount (BDT)']);
        foreach ([
            'Sales revenue' => $summary['revenue'],
            'Service fees' => $summary['service_fees'],
            'Cost of goods sold' => $summary['cogs'],
            'Gross profit' => $summary['gross_profit'],
            'Refunds' => $summary['refunds'],
            'Operating expenses' => $summary['expenses'],
            'Net profit' => $summary['net_profit'],
            'Margin %' => $summary['margin'],
            'Units sold' => $summary['units_sold'],
        ] as $label => $value) {
            fputcsv($out, [$label, $value]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Module', 'Units', 'Revenue', 'Cost', 'Profit', 'Margin %']);
        foreach ($byModule as $m) {
            fputcsv($out, [$m['module'], $m['units'], $m['revenue'], $m['cost'], $m['profit'], $m['margin']]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Payment method', 'Amount']);
        foreach ($paymentMix as $method => $amount) {
            fputcsv($out, [$method, $amount]);
        }
        rewind($out);
        $csv = stream_get_contents($out) ?: '';
        fclose($out);

        ActivityLogger::log('ha_report', 0, 'exported_csv', ['from' => $from, 'to' => $to, 'module' => $module]);

        return Res::make($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="ha-pnl-' . $from . '-' . $to . '.csv"');
    }

    public function exportPdf(Request $request): Response
    {
        [$from, $to] = $this->range($request);
        $summary = $this->reports->summary($from, $to);
        $byModule = $this->reports->byModule($from, $to);
        $expenses = $this->reports->expensesBetween($from, $to);

        ActivityLogger::log('ha_report', 0, 'exported_pdf', ['from' => $from, 'to' => $to]);

        $bytes = $this->pdf->render('admin.ha.report-pdf', [
            'from'     => $from,
            'to'       => $to,
            'summary'  => $summary,
            'byModule' => $byModule,
            'expenses' => $expenses,
        ], 'ha-pnl-' . $from . '-' . $to . '.pdf');

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ha-pnl-' . $from . '-' . $to . '.pdf"',
        ]);
    }

    public function expenseStore(Request $request)
    {
        $data = $request->validate([
            'category'     => ['required', 'in:' . implode(',', HaExpense::CATEGORIES)],
            'amount'       => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'note'         => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $expense = HaExpense::create($data + ['created_by' => auth()->id()]);
        ActivityLogger::log('ha_expense', $expense->id, 'created', $data);

        return redirect('/admin/ha/reports')
            ->with('success', 'Expense recorded.');
    }

    public function expenseDestroy(int $id)
    {
        $expense = HaExpense::findOrFail($id);
        $payload = $expense->only(['category', 'amount', 'expense_date']);
        $expense->delete();
        ActivityLogger::log('ha_expense', $id, 'deleted', $payload);

        return redirect('/admin/ha/reports')->with('success', 'Expense removed.');
    }

    /** Validated date range; defaults to this month. */
    private function range(Request $request): array
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $from = is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : now()->startOfMonth()->toDateString();
        $to = is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : now()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
