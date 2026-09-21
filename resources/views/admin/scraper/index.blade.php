@extends('admin.layout')

@section('title', 'Scraping Pipeline — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-rose-900 to-pink-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(244,63,94,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-robot w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Administration</p>
                <h1 class="text-xl font-bold text-white">{{ t('Scraping Pipeline') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Extract the latest Bangladesh news, jobs and tech content') }}</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-xl border px-4 py-2 text-sm font-semibold {{ ($stats['enabled'] ?? false) ? 'border-emerald-300/40 bg-emerald-400/20 text-emerald-50' : 'border-white/20 bg-white/10 text-white' }}">
            <i class="lucide {{ ($stats['enabled'] ?? false) ? 'lucide-toggle-right' : 'lucide-toggle-left' }} w-4 h-4"></i>
            {{ ($stats['enabled'] ?? false) ? t('Auto runs enabled') : t('Auto runs disabled') }}
        </span>
    </div>
</div>

<div class="max-w-6xl">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-4 mb-6">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-6">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ t('Configured Sources') }}</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ count($sources) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-6">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ t('Stored Items') }}</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['stored_items'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-6">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ t('Tracked URLs') }}</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['tracked_urls'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-6">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ t('AI Enrichment') }}</p>
            <p class="mt-2 text-lg font-bold text-slate-900 dark:text-white truncate">
                {{ $aiProvider['name'] ?? t('No provider') }}
            </p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm mb-6">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-play w-4 h-4 text-rose-600 dark:text-rose-400"></i>
            </div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Run Extraction Now') }}</h3>
        </div>
        <form method="POST" action="{{ route('admin.scraper.run') }}" class="p-5 grid grid-cols-1 sm:grid-cols-5 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">{{ t('Category') }}</label>
                <select name="type" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                    <option value="">{{ t('All categories') }}</option>
                    <option value="news">{{ t('News') }}</option>
                    <option value="jobs">{{ t('Jobs') }}</option>
                    <option value="tech">{{ t('Technology') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">{{ t('Single source') }}</label>
                <select name="source" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                    <option value="">{{ t('All sources') }}</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source['key'] }}">{{ $source['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">{{ t('Max per source') }}</label>
                <input type="number" name="limit" min="1" max="200" value="25" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="enrich" value="1" class="rounded border-slate-300 dark:border-slate-600" @disabled(!$aiProvider)>
                {{ t('AI enrich') }}
            </label>
            <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-rose-500/20 transition-all duration-150">
                <i class="lucide lucide-zap w-4 h-4"></i> {{ t('Run Now') }}
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
        <a href="/admin/scraper/jobs" class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 p-6">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <i class="lucide lucide-bolt w-6 h-6 text-white"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">{{ t('Scraping Jobs') }}</h3>
            <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('View and manage recent runs') }}</p>
        </a>
        <a href="/admin/scraper/sources" class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 p-6">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-400 to-cyan-500 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <i class="lucide lucide-link w-6 h-6 text-white"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">{{ t('Sources') }}</h3>
            <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('News, jobs and tech sources') }}</p>
        </a>
        <a href="/admin/scraper/settings" class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 p-6">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-400 to-pink-500 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <i class="lucide lucide-settings w-6 h-6 text-white"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">{{ t('Settings') }}</h3>
            <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('Limits, politeness and storage') }}</p>
        </a>
        <a href="/admin/scraper/logs" class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 p-6">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <i class="lucide lucide-history w-6 h-6 text-white"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">{{ t('Scraping Logs') }}</h3>
            <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('Run history and exported files') }}</p>
        </a>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Recent Runs') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('When') }}</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Scope') }}</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Fetched') }}</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('New') }}</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Skipped') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentRuns as $run)
                        <tr class="border-b border-slate-50 dark:border-slate-800/60">
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $run['at'] ?? '' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $run['type'] ?? t('all') }} {{ !empty($run['source_id']) ? '· '.$run['source_id'] : '' }}</td>
                            <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-300">{{ (int) data_get($run, 'totals.fetched', 0) }}</td>
                            <td class="px-4 py-3 text-center font-semibold text-emerald-600 dark:text-emerald-400">{{ (int) data_get($run, 'totals.added', 0) }}</td>
                            <td class="px-4 py-3 text-center text-slate-500 dark:text-slate-400">{{ (int) data_get($run, 'totals.skipped', 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-600">
                                <p class="text-sm font-medium">{{ t('No runs yet') }}</p>
                                <p class="text-xs mt-1">{{ t('Use “Run Now” to extract content for the first time.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
