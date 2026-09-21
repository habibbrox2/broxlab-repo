@extends('admin.layout')

@section('title', 'Scraping Logs — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-emerald-900 to-teal-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(16,185,129,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-history w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Scraping Pipeline') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Scraping Logs') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Run history and exported content files') }}</p>
            </div>
        </div>
        <a href="/admin/scraper" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back') }}
        </a>
    </div>
</div>

<div class="max-w-6xl grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Exported Files') }}</h3>
        </div>
        <div class="divide-y divide-slate-50 dark:divide-slate-800/60">
            @forelse ($files as $file)
                <div class="px-5 py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ $file['name'] }}</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">{{ $file['modified'] }}</p>
                    </div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ number_format($file['size'] / 1024, 1) }} KB</span>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-slate-400 dark:text-slate-600">
                    <p class="text-sm">{{ t('No exported files yet.') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Run History') }}</h3>
        </div>
        <div class="overflow-x-auto max-h-[32rem] overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-white dark:bg-slate-900">
                    <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                        <th class="text-left px-4 py-2.5 font-semibold text-slate-500 dark:text-slate-400">{{ t('When') }}</th>
                        <th class="text-center px-4 py-2.5 font-semibold text-slate-500 dark:text-slate-400">{{ t('New') }}</th>
                        <th class="text-center px-4 py-2.5 font-semibold text-slate-500 dark:text-slate-400">{{ t('Skipped') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr class="border-b border-slate-50 dark:border-slate-800/60">
                            <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $run['at'] ?? '' }}</td>
                            <td class="px-4 py-2.5 text-center font-semibold text-emerald-600 dark:text-emerald-400">{{ (int) data_get($run, 'totals.added', 0) }}</td>
                            <td class="px-4 py-2.5 text-center text-slate-500 dark:text-slate-400">{{ (int) data_get($run, 'totals.skipped', 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-center text-slate-400 dark:text-slate-600">{{ t('No runs recorded.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
