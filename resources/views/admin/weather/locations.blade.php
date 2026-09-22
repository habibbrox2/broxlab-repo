@extends('admin.layout')

@section('title', 'Weather Location Settings — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-emerald-900 to-teal-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(16,185,129,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-map-pin w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Weather') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Location Settings') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Default location shown by the weather widget') }}</p>
            </div>
        </div>
        <a href="/admin/weather" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Overview') }}
        </a>
    </div>
</div>

<div class="max-w-2xl">
    @include('admin.security._flash')

    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Default location') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ t('Used when a visitor has not chosen a city. City name or coordinates — coordinates win if both are set.') }}</p>
        </div>

        <form method="post" action="/admin/weather/locations" class="p-5 sm:p-6 space-y-5">
            @csrf

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('City name') }}</label>
                <input type="text" name="weather_default_city" maxlength="190" value="{{ $appSettings['weather_default_city'] ?? '' }}"
                       placeholder="Dhaka"
                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/10">
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Latitude') }} (−90…90)</label>
                    <input type="number" step="0.000001" min="-90" max="90" name="weather_default_lat"
                           value="{{ $appSettings['weather_default_lat'] ?? '' }}" placeholder="23.8103"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 font-mono focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/10">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Longitude') }} (−180…180)</label>
                    <input type="number" step="0.000001" min="-180" max="180" name="weather_default_lon"
                           value="{{ $appSettings['weather_default_lon'] ?? '' }}" placeholder="90.4125"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 font-mono focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/10">
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-emerald-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                    <i class="lucide lucide-save w-4 h-4"></i> {{ t('Save changes') }}
                </button>
                <a href="/weather" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-150">
                    <i class="lucide lucide-external-link w-4 h-4"></i> {{ t('Preview widget') }}
                </a>
                <a href="/admin/weather" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-150">
                    {{ t('Back') }}
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
