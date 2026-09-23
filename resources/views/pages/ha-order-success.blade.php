@extends('layouts.app')

@section('title', 'Order Placed — Hero Alif')

@section('content')
<section class="mx-auto max-w-2xl px-4 py-16 text-center">
    @if ($order)
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
            <i class="lucide lucide-check-check h-8 w-8 text-emerald-700"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-900">{{ t('অর্ডার সফল হয়েছে!') }}</h1>
        <p class="mt-2 text-slate-600">{{ t('আপনার ট্র্যাকিং আইডি সংরক্ষণ করুন:') }}</p>
        <div class="mx-auto mt-3 w-fit rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50 px-6 py-3 font-mono text-xl font-bold text-emerald-800">{{ $order->tracking_id }}</div>
        <p class="mt-3 text-sm text-slate-500">{{ t('অর্ডার নম্বর') }}: {{ $order->order_no }} · {{ t('মোট') }}: ৳{{ number_format((float) $order->grand_total, 2) }}</p>
        <div class="mt-6 flex justify-center gap-3">
            <a href="/track?code={{ $order->tracking_id }}" class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">{{ t('অর্ডার ট্র্যাক করুন') }}</a>
            <a href="/shop" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold hover:bg-slate-50">{{ t('শপ করুন') }}</a>
        </div>
    @else
        <h1 class="text-2xl font-bold text-slate-900">{{ t('অর্ডার পাওয়া যায়নি') }}</h1>
        <a href="/shop" class="mt-4 inline-block rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white">{{ t('শপ করুন') }}</a>
    @endif
</section>
@endsection
