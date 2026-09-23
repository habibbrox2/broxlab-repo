@extends('layouts.app')

@section('title', 'Track Your Order — Hero Alif')

@section('content')
<section class="mx-auto max-w-2xl px-4 py-12">
    <h1 class="text-2xl font-bold text-slate-900">{{ t('Track Your Order') }}</h1>
    <form method="GET" action="/track" class="mt-4 flex gap-2">
        <input name="code" value="{{ $code }}" placeholder="HA-XXXXXXXX" required
               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 font-mono text-sm uppercase">
        <button class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">{{ t('Track') }}</button>
    </form>

    @if ($code !== '')
        @if ($order)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="font-mono text-sm font-bold">{{ $order->tracking_id }}</span>
                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase
                        @if(in_array($order->status, ['delivered'])) bg-emerald-100 text-emerald-700
                        @elseif(in_array($order->status, ['cancelled', 'returned'])) bg-red-100 text-red-700
                        @else bg-sky-100 text-sky-700 @endif">{{ $order->status }}</span>
                </div>

                <ol class="mt-6 space-y-4">
                    @foreach ($order->statusHistory as $h)
                        <li class="flex gap-3">
                            <div class="mt-1 h-2.5 w-2.5 flex-shrink-0 rounded-full bg-emerald-500"></div>
                            <div>
                                <div class="text-sm font-semibold capitalize">{{ str_replace('_', ' ', $h->to_status) }}</div>
                                <div class="text-xs text-slate-500">{{ $h->created_at->format('d M Y, H:i') }}@if($h->note) — {{ $h->note }}@endif</div>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-6 border-t border-slate-100 pt-4">
                    <h2 class="mb-2 text-sm font-semibold">{{ t('Items') }}</h2>
                    <ul class="text-sm text-slate-600">
                        @foreach ($order->items as $item)
                            <li class="flex justify-between py-0.5"><span>{{ $item->name }} × {{ $item->qty }}</span><span class="tabular-nums">৳{{ number_format((float) $item->line_total, 0) }}</span></li>
                        @endforeach
                    </ul>
                    <div class="mt-2 flex justify-between border-t border-slate-100 pt-2 text-sm font-bold">
                        <span>{{ t('Grand total') }}</span><span class="tabular-nums">৳{{ number_format((float) $order->grand_total, 2) }}</span>
                    </div>
                </div>
            </div>
        @else
            <div class="mt-6 rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
                <i class="lucide lucide-package-search mx-auto mb-2 h-8 w-8 text-slate-300"></i>
                {{ t('এই কোডে কোনো অর্ডার পাওয়া যায়নি।') }}
            </div>
        @endif
    @endif
</section>
@endsection
