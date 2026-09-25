@extends('admin.layout')

@section('title', 'Sponsored Packages — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-amber-900 to-orange-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(245,158,11,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-sparkles w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Revenue</p>
                <h1 class="text-xl font-bold text-white">{{ t('Sponsored Packages') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Create and manage sponsored content packages') }}</p>
            </div>
        </div>
        <a href="/admin/revenue" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back to Revenue') }}
        </a>
    </div>
</div>

@php
    $tiers = [1 => 'Bronze', 2 => 'Silver', 3 => 'Gold'];
    $tierColors = [1 => 'from-slate-400 to-slate-500', 2 => 'from-amber-400 to-amber-500', 3 => 'from-yellow-400 to-yellow-500'];
@endphp

<div class="max-w-6xl">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">            @forelse($packages as $pkg)
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 group {{ ($pkg->is_active ?? false) ? '' : 'opacity-60' }} {{ $pkg->trashed() ? 'bg-rose-50/30 dark:bg-rose-900/5' : '' }}">
                <div class="p-6">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $tierColors[$pkg->tier] ?? $tierColors[1] }} flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i class="lucide lucide-{{ $pkg->icon }} w-6 h-6 text-white"></i>
                    </div>
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $pkg->name }}</h3>
                        @if (! $pkg->is_active)
                            <span class="rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ t('Inactive') }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-400 dark:text-slate-600 mb-3">{{ $pkg->description }}</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mb-4">
                        {{ $pkg->currency === 'BDT' ? '৳' : $pkg->currency.' ' }}{{ number_format((float) $pkg->price, 0) }}<span class="text-sm font-medium text-slate-400">/{{ $pkg->billing_period === 'yearly' ? 'yr' : 'mo' }}</span>
                    </p>
                    @if (! empty($pkg->features))
                        <ul class="mb-4 space-y-1">
                            @foreach (array_slice($pkg->features, 0, 4) as $feature)
                                <li class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                    <i class="lucide lucide-check w-3.5 h-3.5 text-emerald-500 flex-shrink-0"></i> {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="flex items-center gap-2">
                        @if ($pkg->trashed())
                            <form method="post" action="/admin/revenue/sponsored/restore">
                                @csrf
                                <input type="hidden" name="id" value="{{ $pkg->id }}">
                                <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-2.5 text-sm font-semibold text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/30 transition-all duration-150">
                                    {{ t('Restore') }} <i class="lucide lucide-refresh-ccw w-4 h-4"></i>
                                </button>
                            </form>
                        @else
                            <a href="/admin/revenue/sponsored/edit?id={{ $pkg->id }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active: scale-[0.98] transition-all duration-150">
                                {{ t('Manage Package') }} <i class="lucide lucide-arrow-right w-4 h-4"></i>
                            </a>
                            <form method="post" action="/admin/revenue/sponsored/delete" onsubmit="return confirm('{{ t('Delete this package?') }}')">
                                @csrf
                                <input type="hidden" name="id" value="{{ $pkg->id }}">
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-rose-200 dark:border-rose-900/50 px-3 py-2.5 text-sm font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-all duration-150" aria-label="{{ t('Delete package') }}">
                                    <i class="lucide lucide-trash-2 w-4 h-4"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="md:col-span-3 overflow-hidden rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-10 text-center">
                <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-4">
                    <i class="lucide lucide-package-open w-6 h-6 text-slate-400"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">{{ t('No packages yet') }}</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600 mb-5">{{ t('Create your first sponsored content package to get started.') }}</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="p-6 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">{{ t('Create New Package') }}</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('Define a new sponsored content package') }}</p>
            </div>
            <a href="/admin/revenue/sponsored/create" class="inline-flex items-center gap-2 rounded-xl bg-amber-600 hover:bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-amber-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-plus w-4 h-4"></i> {{ t('New Package') }}
            </a>
        </div>
    </div>
</div>
@endsection
