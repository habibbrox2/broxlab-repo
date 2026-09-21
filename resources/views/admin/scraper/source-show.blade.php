@extends('admin.layout')

@section('title', $source['name'].' — Scraping Source')

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-blue-900 to-cyan-900 text-white shadow-xl shadow-slate-900/20">
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-file-json w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ $source['type'] }}</p>
                <h1 class="text-xl font-bold text-white">{{ $source['name'] }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ count($snapshot['items']) }} {{ t('stored items') }} · {{ $path }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/scraper/sources" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Sources') }}
            </a>
            <form method="POST" action="{{ route('admin.scraper.run') }}">
                @csrf
                <input type="hidden" name="source" value="{{ $source['key'] }}">
                <input type="hidden" name="limit" value="25">
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2 text-sm font-semibold text-white">
                    <i class="lucide lucide-zap w-4 h-4"></i> {{ t('Run') }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.scraper.source.clear', $source['key']) }}" onsubmit="return confirm('Clear this snapshot?')">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    <i class="lucide lucide-trash-2 w-4 h-4"></i> {{ t('Clear') }}
                </button>
            </form>
        </div>
    </div>
</div>

<div class="max-w-6xl overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                    <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Title') }}</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Published') }}</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('AI Summary') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($snapshot['items'] as $item)
                    <tr class="border-b border-slate-50 dark:border-slate-800/60 align-top">
                        <td class="px-4 py-3">
                            <a href="{{ $item['link'] ?? '#' }}" target="_blank" rel="noopener" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $item['title'] ?? '—' }}</a>
                            @if (!empty($item['ai_category']))
                                <span class="ml-2 rounded-full bg-violet-50 dark:bg-violet-900/30 px-2 py-0.5 text-[10px] font-semibold text-violet-700 dark:text-violet-300">{{ $item['ai_category'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $item['published_at'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ $item['ai_summary'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-12 text-center text-slate-400 dark:text-slate-600">
                            <p class="text-sm font-medium">{{ t('No snapshot stored yet') }}</p>
                            <p class="text-xs mt-1">{{ t('Run this source to extract the latest content.') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
