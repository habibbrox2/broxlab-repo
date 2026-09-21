@extends('admin.layout')

@section('title', 'Scraping Jobs — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-orange-900 to-red-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(234,88,12,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-bolt w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Scraping Pipeline') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Scraping Jobs') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Per-source results from every extraction run') }}</p>
            </div>
        </div>
        <a href="/admin/scraper" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back') }}
        </a>
    </div>
</div>

<div class="max-w-6xl space-y-6">
    @forelse ($runs as $index => $run)
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-orange-50 dark:bg-orange-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-list w-4 h-4 text-orange-600 dark:text-orange-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $run['at'] ?? '' }}
                            <span class="text-slate-400 dark:text-slate-500 font-normal">
                                · {{ $run['type'] ?? t('all') }}{{ !empty($run['source_id']) ? ' · '.$run['source_id'] : '' }}
                                · limit {{ $run['limit'] ?? '-' }}
                                @if (!empty($run['enriched'])) · {{ t('AI enriched') }} @endif
                            </span>
                        </h3>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <span class="text-slate-500 dark:text-slate-400">{{ t('Fetched') }}: <strong class="text-slate-800 dark:text-slate-200">{{ (int) data_get($run, 'totals.fetched', 0) }}</strong></span>
                    <span class="text-emerald-600 dark:text-emerald-400">{{ t('New') }}: <strong>{{ (int) data_get($run, 'totals.added', 0) }}</strong></span>
                    <span class="text-slate-500 dark:text-slate-400">{{ t('Skipped') }}: <strong class="text-slate-800 dark:text-slate-200">{{ (int) data_get($run, 'totals.skipped', 0) }}</strong></span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                            <th class="text-left px-4 py-2.5 font-semibold text-slate-500 dark:text-slate-400">{{ t('Source') }}</th>
                            <th class="text-center px-4 py-2.5 font-semibold text-slate-500 dark:text-slate-400">{{ t('Strategy') }}</th>
                            <th class="text-center px-4 py-2.5 font-semibold text-slate-500 dark:text-slate-400">{{ t('Status') }}</th>
                            <th class="text-center px-4 py-2.5 font-semibold text-slate-500 dark:text-slate-400">{{ t('Fetched') }}</th>
                            <th class="text-center px-4 py-2.5 font-semibold text-slate-500 dark:text-slate-400">{{ t('New') }}</th>
                            <th class="text-center px-4 py-2.5 font-semibold text-slate-500 dark:text-slate-400">{{ t('Skipped') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($run['results'] ?? [] as $result)
                            <tr class="border-b border-slate-50 dark:border-slate-800/60">
                                <td class="px-4 py-2.5 text-slate-700 dark:text-slate-200">
                                    {{ $result['name'] ?? $result['key'] ?? '—' }}
                                    @if (!empty($result['error']))
                                        <p class="text-xs text-amber-600 dark:text-amber-400">{{ \Illuminate\Support\Str::limit($result['error'], 120) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center text-xs text-slate-500 dark:text-slate-400">{{ $result['strategy'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ ($result['status'] ?? '') === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">
                                        {{ $result['status'] ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center text-slate-600 dark:text-slate-300">{{ (int) ($result['fetched'] ?? 0) }}</td>
                                <td class="px-4 py-2.5 text-center font-semibold text-emerald-600 dark:text-emerald-400">{{ (int) ($result['added'] ?? 0) }}</td>
                                <td class="px-4 py-2.5 text-center text-slate-500 dark:text-slate-400">{{ (int) ($result['skipped'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm px-4 py-12 text-center text-slate-400 dark:text-slate-600">
            <i class="lucide lucide-bolt-off w-8 h-8 mx-auto mb-2 opacity-50"></i>
            <p class="text-sm font-medium">{{ t('No scraping jobs yet') }}</p>
            <p class="text-xs mt-1">{{ t('Run the pipeline from the overview page.') }}</p>
        </div>
    @endforelse
</div>

@endsection
