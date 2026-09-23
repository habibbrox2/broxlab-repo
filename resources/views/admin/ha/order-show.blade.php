@extends('admin.layout')

@section('title', 'Order '.$order->order_no)

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ $order->order_no }}</h1>
        <p class="text-sm text-slate-500">{{ t('Tracking') }}: <span class="font-mono">{{ $order->tracking_id }}</span></p>
    </div>
    <a href="/admin/ha/orders" class="text-sm text-indigo-600 hover:underline">← {{ t('All orders') }}</a>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <h2 class="px-4 py-3 font-semibold border-b border-slate-100">{{ t('Items') }}</h2>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="px-4 py-2">{{ $item->name }}<div class="text-xs text-slate-400">{{ $item->sku }}</div></td>
                            <td class="px-4 py-2 text-right">{{ $item->qty }} × ৳{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums font-semibold">৳{{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 text-sm">
                    <tr><td colspan="2" class="px-4 py-1.5 text-right text-slate-500">{{ t('Subtotal') }}</td><td class="px-4 py-1.5 text-right tabular-nums">৳{{ number_format((float) $order->subtotal, 2) }}</td></tr>
                    <tr><td colspan="2" class="px-4 py-1.5 text-right text-slate-500">{{ t('Shipping') }}</td><td class="px-4 py-1.5 text-right tabular-nums">৳{{ number_format((float) $order->shipping_fee, 2) }}</td></tr>
                    <tr><td colspan="2" class="px-4 py-2 text-right font-bold">{{ t('Grand total') }}</td><td class="px-4 py-2 text-right tabular-nums font-bold">৳{{ number_format((float) $order->grand_total, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-4">
            <h2 class="mb-3 font-semibold">{{ t('Status timeline') }}</h2>
            <ol class="space-y-3">
                @foreach ($order->statusHistory as $h)
                    <li class="flex gap-3 text-sm">
                        <div class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-indigo-500"></div>
                        <div>
                            <span class="font-semibold capitalize">{{ str_replace('_', ' ', $h->to_status) }}</span>
                            <span class="text-xs text-slate-400">— {{ $h->created_at->format('d M H:i') }}@if($h->note) · {{ $h->note }}@endif</span>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-1 font-semibold">{{ t('Customer') }}</h2>
            <p class="text-sm">{{ $order->customer_name }}</p>
            <p class="text-sm text-slate-500">{{ $order->customer_mobile }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $order->customer_address }}</p>
            <p class="mt-3 text-xs text-slate-400">{{ t('Payment') }}: {{ $order->payment_method }} · {{ $order->payment_status }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 font-semibold">{{ t('Move status') }} <span class="text-xs font-normal text-slate-400">{{ t('now') }}: {{ $order->status }}</span></h2>
            @foreach (($flow[$order->status] ?? []) as $next)
                <form method="POST" action="/admin/ha/orders/{{ $order->id }}/transition" class="mb-2 flex gap-2">
                    @csrf
                    <input type="hidden" name="to_status" value="{{ $next }}">
                    <input name="note" placeholder="{{ t('Note (optional)') }}" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded-lg {{ $next === 'cancelled' || $next === 'returned' ? 'bg-red-600' : 'bg-indigo-600' }} px-4 py-2 text-sm font-semibold text-white capitalize">{{ $next }}</button>
                </form>
            @endforeach
            @if (($flow[$order->status] ?? []) === [])
                <p class="text-sm text-slate-400">{{ t('Terminal state — no transitions.') }}</p>
            @endif
            @if ($order->status === 'confirmed' && $order->sale_id)
                <p class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-700">{{ t('Linked sale') }}: {{ \App\Models\HaSale::find($order->sale_id)?->invoice_no }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
