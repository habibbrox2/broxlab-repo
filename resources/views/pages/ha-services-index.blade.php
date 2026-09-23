@extends('layouts.app')

@section('title', 'Digital Services — Hero Alif')

@section('content')
<section class="mx-auto max-w-6xl px-4 py-12">
    <div class="text-center">
        <h1 class="text-3xl font-bold text-slate-900">{{ t('Digital Service Center') }}</h1>
        <p class="mt-2 text-slate-600">{{ t('NID, passport, birth registration, certificates, job applications and printing — apply online, track anywhere.') }}</p>
    </div>

    <div class="mt-8 flex justify-center">
        <form method="GET" action="/services-plus/track" class="flex w-full max-w-md gap-2">
            <input name="code" placeholder="DS-2026-00125" required
                   class="w-full rounded-xl border border-slate-300 px-4 py-2.5 font-mono text-sm uppercase">
            <button class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">{{ t('Track') }}</button>
        </form>
    </div>

    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($categories as $cat)
            <div class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                <div class="text-3xl">{{ $cat->icon ?? '📄' }}</div>
                <h2 class="mt-3 font-bold text-slate-900">{{ $cat->name }}</h2>
                <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $cat->description }}</p>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-sm font-semibold text-emerald-700">{{ (float) $cat->base_fee > 0 ? '৳' . number_format((float) $cat->base_fee, 0) : t('Free inquiry') }}</span>
                    <a href="/services-plus/apply/{{ $cat->slug }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white group-hover:bg-emerald-800">{{ t('Apply') }}</a>
                </div>
            </div>
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">{{ t('Services will appear here soon.') }}</p>
        @endforelse
    </div>
</section>
@endsection
