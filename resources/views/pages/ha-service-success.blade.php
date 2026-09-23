@extends('layouts.app')

@section('title', 'Request Received — Hero Alif')

@section('content')
<section class="mx-auto max-w-2xl px-4 py-16 text-center">
    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-3xl">✅</div>
    <h1 class="mt-4 text-2xl font-bold text-slate-900">{{ t('Your request has been received!') }}</h1>
    <p class="mt-2 text-slate-600">{{ t('Save this tracking ID to check the status anytime:') }}</p>

    <div class="mx-auto mt-6 max-w-sm rounded-2xl border-2 border-dashed border-emerald-300 bg-emerald-50 px-6 py-5">
        <div class="font-mono text-2xl font-bold tracking-wider text-emerald-800">{{ $request->tracking_id }}</div>
        <div class="mt-1 text-xs text-slate-500">{{ $request->category->name ?? '' }} · {{ $request->created_at->format('d M Y') }}</div>
    </div>

    <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="/services-plus/track?code={{ $request->tracking_id }}" class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">{{ t('Track this request') }}</a>
        <a href="/services-plus" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ t('Apply for another service') }}</a>
        <a href="tel:01941159555" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ t('Call hotline') }}</a>
    </div>
</section>
@endsection
