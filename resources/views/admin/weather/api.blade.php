@extends('admin.layout')

@section('title', 'Weather API Settings — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-blue-900 to-sky-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(14,165,233,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-cloud-sun w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Weather') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('API Configuration') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Provider, API key and units for the live weather widget') }}</p>
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
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Provider') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ t('The API key is stored on the server and never exposed to the browser.') }}</p>
        </div>

        <form method="post" action="/admin/weather/api" class="p-5 sm:p-6 space-y-5">
            @csrf

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Weather provider') }}</label>
                <select name="weather_provider"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/10">
                    <option value="mock" @selected(($appSettings['weather_provider'] ?? config('weather.provider')) === 'mock')>Mock — deterministic local data (no API calls)</option>
                    <option value="openweathermap" @selected(($appSettings['weather_provider'] ?? '') === 'openweathermap')>OpenWeatherMap — live data</option>
                </select>
                <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Currently active:').' '.t($provider === 'mock' ? 'mock data' : 'OpenWeatherMap') }}</p>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('OpenWeatherMap API key') }}</label>
                <input type="text" name="weather_api_key" maxlength="190" value="{{ $appSettings['weather_api_key'] ?? '' }}"
                       placeholder="{{ t('32-character key from openweathermap.org') }}"
                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 font-mono placeholder:text-slate-400 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/10">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Units') }}</label>
                <select name="weather_units"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/10">
                    <option value="metric" @selected(($appSettings['weather_units'] ?? 'metric') === 'metric')>Metric (°C, m/s)</option>
                    <option value="imperial" @selected(($appSettings['weather_units'] ?? '') === 'imperial')>Imperial (°F, mph)</option>
                </select>
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-sky-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                    <i class="lucide lucide-save w-4 h-4"></i> {{ t('Save changes') }}
                </button>
                <button type="submit" formaction="/admin/weather/api/test" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-150">
                    <i class="lucide lucide-plug-zap w-4 h-4"></i> {{ t('Test connection') }}
                </button>
                <a href="/admin/weather" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-150">
                    {{ t('Back') }}
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
