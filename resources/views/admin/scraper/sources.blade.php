@extends('admin.layout')

@section('title', 'Scraping Sources — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-blue-900 to-cyan-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(6,182,212,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-link w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Scraping Pipeline') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Scraping Sources') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Popular Bangladeshi news, jobs and tech sources') }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/scraper" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back') }}
            </a>
            <a href="/admin/scraper/sources/create" class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition-all duration-150">
                <i class="lucide lucide-plus w-4 h-4"></i> {{ t('Add Source') }}
            </a>
        </div>
    </div>
</div>

<div class="max-w-6xl space-y-6">
    @foreach (['news' => 'News', 'jobs' => 'Jobs', 'tech' => 'Technology'] as $type => $label)
        @php $group = collect($sources)->where('type', $type); @endphp
        @if ($group->isNotEmpty())
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-{{ $type === 'jobs' ? 'briefcase' : ($type === 'tech' ? 'cpu' : 'newspaper') }} w-4 h-4 text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t($label) }}</h3>
                    </div>
                    <span class="text-xs text-slate-400 dark:text-slate-500">{{ $group->count() }} {{ t('sources') }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                                <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Source') }}</th>
                                <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Homepage') }}</th>
                                <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Strategy') }}</th>
                                <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Stored') }}</th>
                                <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Last Updated') }}</th>
                                <th class="text-right px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group as $source)
                                <tr class="border-b border-slate-50 dark:border-slate-800/60">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-slate-900 dark:text-white">{{ $source['name'] }}</span>
                                            @if ($source['enabled'] === false)
                                                <span class="rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500">{{ t('disabled') }}</span>
                                            @endif
                                        </div>
                                        @if (!empty($source['note']))
                                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">{{ $source['note'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ $source['homepage'] }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline break-all">{{ $source['homepage'] }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-center text-xs text-slate-500 dark:text-slate-400">{{ $source['strategy'] }}</td>
                                    <td class="px-4 py-3 text-center font-semibold text-slate-700 dark:text-slate-200">{{ number_format($source['stored_count'] ?? 0) }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $source['updated_at'] ?? t('never') }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.scraper.source.show', $source['key']) }}" class="rounded-lg border border-slate-200 dark:border-slate-700 px-2.5 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">{{ t('View') }}</a>
                                            <form method="POST" action="{{ route('admin.scraper.run') }}">
                                                @csrf
                                                <input type="hidden" name="source" value="{{ $source['key'] }}">
                                                <input type="hidden" name="limit" value="25">
                                                <button type="submit" class="rounded-lg bg-blue-600 hover:bg-blue-700 px-2.5 py-1.5 text-xs font-semibold text-white">{{ t('Run') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endforeach
</div>

@endsection
