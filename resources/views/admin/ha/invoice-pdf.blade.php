{{-- Hero Alif invoice — mPDF rendered (utf-8). Kept inline-styled; mPDF has limited CSS support. --}}
<html>
<head>
<style>
    body { font-family: dejavusans; font-size: 11pt; color: #1e293b; }
    h2 { margin: 0; font-size: 16pt; }
    .muted { color: #64748b; font-size: 9pt; }
    table { width: 100%; border-collapse: collapse; }
    th { background-color: #f1f5f9; font-size: 9pt; text-align: left; padding: 6px; border-bottom: 1px solid #cbd5e1; }
    td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; font-size: 10pt; }
    .right { text-align: right; }
    .totals td { border-bottom: none; padding: 3px 6px; }
    .grand { font-size: 12pt; font-weight: bold; background-color: #ecfdf5; }
</style>
</head>
<body>
<table style="width:100%; margin-bottom: 6px;">
    <tr>
        <td>
            <h2>Hiru Alif Service Center</h2>
            <div class="muted">খড়ারচর বাজার, রোয়াইল, ধামরাই, ঢাকা</div>
            <div class="muted">হটলাইন: 01941-159555, 01819-083961, 01841-159555</div>
        </td>
        <td class="right">
            <div style="font-size:14pt; font-weight:bold;">INVOICE</div>
            <div class="muted">{{ $sale->invoice_no }}</div>
            <div class="muted">{{ $sale->created_at->format('d M Y, H:i') }}</div>
        </td>
    </tr>
</table>

<div style="margin: 8px 0; font-size: 10pt;">
    @if ($sale->customer)
        <strong>Customer:</strong> {{ $sale->customer->name }} ({{ $sale->customer->mobile }})
    @else
        <strong>Customer:</strong> Walk-in
    @endif
    @if ($sale->cashier)
        &nbsp;·&nbsp; <strong>Cashier:</strong> {{ trim(($sale->cashier->first_name ?? '').' '.($sale->cashier->last_name ?? '')) }}
    @endif
</div>

<table>
    <tr>
        <th style="width:5%">#</th>
        <th>Item</th>
        <th style="width:10%" class="right">Qty</th>
        <th style="width:18%" class="right">Price</th>
        <th style="width:20%" class="right">Total</th>
    </tr>
    @foreach ($sale->items as $i => $item)
    <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $item->name }}<br><span class="muted">{{ $item->sku }}</span></td>
        <td class="right">{{ $item->qty }}</td>
        <td class="right">৳{{ number_format((float) $item->unit_price, 2) }}</td>
        <td class="right">৳{{ number_format((float) $item->line_total, 2) }}</td>
    </tr>
    @endforeach
</table>

<table class="totals" style="margin-top: 8px; width: 45%; margin-left: 55%;">
    <tr><td>Subtotal</td><td class="right">৳{{ number_format((float) $sale->subtotal, 2) }}</td></tr>
    @if ((float) $sale->discount > 0)<tr><td>Discount</td><td class="right">−৳{{ number_format((float) $sale->discount, 2) }}</td></tr>@endif
    @if ((float) $sale->vat_amount > 0)<tr><td>VAT ({{ $sale->vat_percent }}%)</td><td class="right">৳{{ number_format((float) $sale->vat_amount, 2) }}</td></tr>@endif
    <tr class="grand"><td>Grand Total</td><td class="right">৳{{ number_format((float) $sale->grand_total, 2) }}</td></tr>
    @foreach ($sale->payments->where('kind', 'payment') as $pay)
        <tr><td>{{ ucfirst($pay->method) }}@if($pay->reference) ({{ $pay->reference }})@endif</td><td class="right">৳{{ number_format((float) $pay->amount, 2) }}</td></tr>
    @endforeach
    @if ((float) $sale->due_amount > 0)<tr><td style="color:#b91c1c; font-weight:bold;">Due</td><td class="right" style="color:#b91c1c; font-weight:bold;">৳{{ number_format((float) $sale->due_amount, 2) }}</td></tr>@endif
    @if ((float) $sale->change_amount > 0)<tr><td>Change</td><td class="right">৳{{ number_format((float) $sale->change_amount, 2) }}</td></tr>@endif
</table>

<p class="muted" style="text-align:center; margin-top: 18px;">ধন্যবাদ! আবার আসবেন। — Hero Alif Group</p>
</body>
</html>
