@extends('admin.layout')

@section('title', 'Receipt '.$sale->invoice_no)

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="mb-4 flex items-center justify-between no-print">
        <a href="/admin/ha/pos" class="text-sm text-indigo-600 hover:underline">← {{ t('New sale') }}</a>
        <div class="flex gap-2">
            <a href="/admin/ha/invoices/{{ $sale->id }}/pdf" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">{{ t('PDF') }}</a>
            <button x-data x-on:click="window.print()" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white">{{ t('Print') }}</button>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm font-mono text-sm receipt-80mm">
        <div class="text-center">
            <h2 class="font-bold text-base">{{ t('Hiru Alif Service Center') }}</h2>
            <p class="text-xs text-slate-500">খড়ারচর বাজার, রোয়াইল, ধামরাই, ঢাকা</p>
            <p class="text-xs text-slate-500">হটলাইন: 01941-159555, 01819-083961</p>
        </div>
        <div class="my-3 border-t border-dashed border-slate-300"></div>
        <div class="flex justify-between text-xs">
            <span>{{ $sale->invoice_no }}</span>
            <span>{{ $sale->completed_at?->format('d M Y, H:i') }}</span>
        </div>
        @if ($sale->customer)
            <div class="text-xs mt-1">{{ t('Customer') }}: {{ $sale->customer->name }} ({{ $sale->customer->mobile }})</div>
        @endif
        @if ($sale->cashier)
            <div class="text-xs">{{ t('Cashier') }}: {{ trim(($sale->cashier->first_name ?? '').' '.($sale->cashier->last_name ?? '')) }}</div>
        @endif

        <div class="my-3 border-t border-dashed border-slate-300"></div>
        <table class="w-full text-xs">
            @foreach ($sale->items as $item)
                <tr>
                    <td class="py-0.5">{{ $item->name }}<br><span class="text-slate-400">{{ $item->qty }} × ৳{{ number_format((float) $item->unit_price, 2) }}</span></td>
                    <td class="py-0.5 text-right align-top tabular-nums">৳{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </table>
        <div class="my-3 border-t border-dashed border-slate-300"></div>
        <div class="space-y-0.5 text-xs">
            <div class="flex justify-between"><span>{{ t('Subtotal') }}</span><span class="tabular-nums">৳{{ number_format((float) $sale->subtotal, 2) }}</span></div>
            @if ((float) $sale->discount > 0)<div class="flex justify-between"><span>{{ t('Discount') }}</span><span class="tabular-nums">−৳{{ number_format((float) $sale->discount, 2) }}</span></div>@endif
            @if ((float) $sale->vat_amount > 0)<div class="flex justify-between"><span>{{ t('VAT') }} ({{ $sale->vat_percent }}%)</span><span class="tabular-nums">৳{{ number_format((float) $sale->vat_amount, 2) }}</span></div>@endif
            <div class="flex justify-between font-bold text-sm"><span>{{ t('Grand total') }}</span><span class="tabular-nums">৳{{ number_format((float) $sale->grand_total, 2) }}</span></div>
            @foreach ($sale->payments->where('kind', 'payment') as $pay)
                <div class="flex justify-between"><span>{{ ucfirst($pay->method) }}@if($pay->reference) ({{ $pay->reference }})@endif</span><span class="tabular-nums">৳{{ number_format((float) $pay->amount, 2) }}</span></div>
            @endforeach
            @if ((float) $sale->due_amount > 0)<div class="flex justify-between text-red-600 font-semibold"><span>{{ t('Due') }}</span><span class="tabular-nums">৳{{ number_format((float) $sale->due_amount, 2) }}</span></div>@endif
            @if ((float) $sale->change_amount > 0)<div class="flex justify-between"><span>{{ t('Change') }}</span><span class="tabular-nums">৳{{ number_format((float) $sale->change_amount, 2) }}</span></div>@endif
        </div>
        <div class="my-3 border-t border-dashed border-slate-300"></div>
        <p class="text-center text-xs">{{ t('ধন্যবাদ! আবার আসবেন।') }}</p>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    .receipt-80mm { border: 0 !important; box-shadow: none !important; max-width: 80mm; margin: 0 auto; padding: 0; }
}
</style>
@endsection
