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

<div class="max-w-6xl">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        @foreach([
            ['icon' => 'package', 'title' => ' Bronze Package', 'desc' => 'Basic sponsored content listing', 'price' => '৳2,000/mo', 'color' => 'from-slate-400 to-slate-500'],
            ['icon' => 'star', 'title' => ' Silver Package', 'desc' => 'Featured sponsored content with highlight', 'price' => '৳5,000/mo', 'color' => 'from-amber-400 to-amber-500'],
            ['icon' => 'crown', 'title' => ' Gold Package', 'desc' => 'Premium featured + homepage banner', 'price' => '৳10,000/mo', 'color' => 'from-yellow-400 to-yellow-500'],
        ] as $i => $pkg)
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 group">
                <div class="p-6">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $pkg['color'] }} flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i class="lucide lucide-{{ $pkg['icon'] }} w-6 h-6 text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">{{ $pkg['title'] }}</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-600 mb-3">{{ $pkg['desc'] }}</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mb-4">{{ $pkg['price'] }}</p>
                    <a href="/admin/revenue/sponsored/edit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        {{ t('Manage Package') }} <i class="lucide lucide-arrow-right w-4 h-4"></i>
                    </a>
                </div>
            </div>
        @endforeach
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
