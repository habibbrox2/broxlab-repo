{{-- Hero Alif P&L statement — mPDF rendered (utf-8, inline styles only). --}}
<html>
<head>
<style>
    body { font-family: dejavusans; font-size: 11pt; color: #1e293b; }
    h2 { margin: 0; font-size: 16pt; }
    .muted { color: #64748b; font-size: 9pt; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background-color: #f1f5f9; font-size: 9pt; text-align: left; padding: 6px; border-bottom: 1px solid #cbd5e1; }
    td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; font-size: 10pt; }
    .right { text-align: right; }
    .grand { font-weight: bold; background-color: #ecfdf5; }
    .neg { color: #b91c1c; }
</style>
</head>
<body>
<table style="width:100%; margin-bottom: 4px;">
    <tr>
        <td>
            <h2>Hero Alif Group</h2>
            <div class="muted">খড়ারচর বাজার, রোয়াইল, ধামরাই, ঢাকা</div>
        </td>
        <td class="right">
            <div style="font-size:14pt; font-weight:bold;">P&amp;L STATEMENT</div>
            <div class="muted">{{ $from }} to {{ $to }}</div>
        </td>
    </tr>
</table>

<table>
    <tr><th colspan="2">Summary (BDT)</th></tr>
    <tr><td>Sales revenue</td><td class="right">{{ number_format($summary['revenue'], 2) }}</td></tr>
    <tr><td>Service fees</td><td class="right">{{ number_format($summary['service_fees'], 2) }}</td></tr>
    <tr><td>Cost of goods sold</td><td class="right neg">({{ number_format($summary['cogs'], 2) }})</td></tr>
    <tr class="grand"><td>Gross profit</td><td class="right">{{ number_format($summary['gross_profit'], 2) }}</td></tr>
    <tr><td>Refunds</td><td class="right neg">({{ number_format($summary['refunds'], 2) }})</td></tr>
    <tr><td>Operating expenses</td><td class="right neg">({{ number_format($summary['expenses'], 2) }})</td></tr>
    <tr class="grand"><td>Net profit</td><td class="right">{{ number_format($summary['net_profit'], 2) }}</td></tr>
    <tr><td>Net margin</td><td class="right">{{ $summary['margin'] }}%</td></tr>
</table>

<table>
    <tr>
        <th>Module</th><th class="right">Units</th><th class="right">Revenue</th>
        <th class="right">Cost</th><th class="right">Profit</th><th class="right">Margin</th>
    </tr>
    @foreach ($byModule as $m)
        <tr>
            <td>{{ ucfirst(str_replace('_', ' ', $m['module'])) }}</td>
            <td class="right">{{ number_format($m['units']) }}</td>
            <td class="right">{{ number_format($m['revenue'], 2) }}</td>
            <td class="right">{{ number_format($m['cost'], 2) }}</td>
            <td class="right">{{ number_format($m['profit'], 2) }}</td>
            <td class="right">{{ $m['margin'] }}%</td>
        </tr>
    @endforeach
</table>

@if (count($expenses) > 0)
<table>
    <tr><th colspan="4">Operating expenses</th></tr>
    <tr><th>Date</th><th>Category</th><th>Note</th><th class="right">Amount</th></tr>
    @foreach ($expenses as $exp)
        <tr>
            <td>{{ $exp->expense_date->format('d M Y') }}</td>
            <td>{{ ucfirst($exp->category) }}</td>
            <td>{{ Str::limit($exp->note, 48) }}</td>
            <td class="right">{{ number_format((float) $exp->amount, 2) }}</td>
        </tr>
    @endforeach
</table>
@endif

<p class="muted" style="margin-top:14px;">Profit is computed from per-item cost snapshots captured at sale time. Generated {{ now()->format('d M Y, H:i') }}.</p>
</body>
</html>
