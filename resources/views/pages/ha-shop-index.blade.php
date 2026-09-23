@extends('layouts.app')

@section('title', 'Hero Alif Shop — Gadgets, Pure Mustard Oil, Fuel & Machinery')
@section('meta_description', 'Shop electronics, gadgets, khan-ghani pure mustard oil, fuel and lubricants, and machinery — all in one place at Hero Alif Group.')

@section('content')
<section class="relative overflow-hidden bg-gradient-to-br from-emerald-900 via-emerald-800 to-teal-700 py-14 text-white">
    <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold">
            <i class="lucide lucide-store h-3.5 w-3.5"></i> {{ t('Hero Alif Group') }}
        </div>
        <h1 class="max-w-3xl text-3xl font-bold tracking-tight md:text-4xl">
            {{ t('ডিজিটাল সেবা, প্রযুক্তি পণ্য, খাঁটি সরিষার তেল ও মেশিনারি — সব এক ছাদের নিচে।') }}
        </h1>
        <p class="mt-3 max-w-2xl text-white/80">{{ t('আপনার দৈনন্দিন প্রয়োজনীয় পণ্য এখন অনলাইনে।') }}</p>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <h2 class="mb-6 text-2xl font-bold text-slate-900">{{ t('Shop by category') }}</h2>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($modules as $slug => $module)
            <a href="/shop/{{ $slug }}" class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
                <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                    <i class="lucide @if($module === 'smart_bazar') lucide-smartphone @elseif($module === 'mustard_oil') lucide-droplets @elseif($module === 'fuel') lucide-fuel @elseif($module === 'machinery') lucide-cog @else lucide-printer @endif w-5 h-5"></i>
                </div>
                <h3 class="font-semibold text-slate-900 group-hover:text-emerald-700">
                    @if($module === 'smart_bazar') {{ t('Smart Bazar') }}
                    @elseif($module === 'mustard_oil') {{ t('Pure Mustard Oil') }}
                    @elseif($module === 'fuel') {{ t('Fuel & Lubricants') }}
                    @elseif($module === 'machinery') {{ t('Machinery & Hardware') }}
                    @else {{ t('Printing') }}
                    @endif
                </h3>
                <p class="mt-1 text-sm text-slate-500">{{ $counts[$module] }} {{ t('products') }}</p>
            </a>
        @endforeach
    </div>
</section>

@if ($featured->isNotEmpty())
<section class="bg-slate-50 py-12">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 class="mb-6 text-2xl font-bold text-slate-900">{{ t('Latest products') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($featured as $p)
                <a href="/shop/{{ array_search($p->module, \App\Http\Controllers\HaShopController::MODULE_MAP) }}/{{ $p->slug }}"
                   class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                    <div class="mb-3 flex h-40 items-center justify-center rounded-xl bg-slate-100 overflow-hidden">
                        @if ($p->image_path)
                            <img src="{{ asset($p->image_path) }}" alt="{{ $p->name }}" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <i class="lucide lucide-package w-10 h-10 text-slate-300"></i>
                        @endif
                    </div>
                    <h3 class="line-clamp-2 font-medium text-slate-900 group-hover:text-emerald-700">{{ $p->name }}</h3>
                    <p class="mt-1 text-lg font-bold text-slate-900">৳{{ number_format((float) $p->retail_price, 0) }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
