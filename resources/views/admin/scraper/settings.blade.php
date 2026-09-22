@extends('admin.layout')

@section('title', 'Scraping Settings — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-purple-900 to-fuchsia-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(168,85,247,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-settings w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Scraping Pipeline') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Scraping Settings') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Configured via config/scraper.php and .env') }}</p>
            </div>
        </div>
        <a href="/admin/scraper" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back') }}
        </a>
    </div>
</div>

<div class="max-w-4xl space-y-6">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-sliders w-4 h-4 text-purple-600 dark:text-purple-400"></i>
            </div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Extraction Limits') }}</h3>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('Max Items per Run') }}</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ $config['max_items'] }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('Request Timeout') }}</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ $config['timeout'] }}s</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('HTTP Retries') }}</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ $config['retries'] }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('Delay Between Requests') }}</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ $config['delay_ms'] }} ms</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('Tracked URL Cap') }}</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ number_format($config['seen_cap']) }}</span>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-cpu w-4 h-4 text-violet-600 dark:text-violet-400"></i>
            </div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Automation') }}</h3>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('Auto Start') }}</span>
                <span class="font-medium {{ $config['enabled'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500' }}">{{ $config['enabled'] ? t('Enabled') : t('Disabled') }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('Queue') }}</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ $stats['queue'] ?? 'scraping' }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('AI Enrichment') }}</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ $config['ai_enrich'] ? t('Enabled') : t('Disabled') }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('Default AI Provider') }}</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ $aiProvider['name'] ?? t('None — add one') }}</span>
            </div>
        </div>
        <div class="px-5 pb-5">
            <a href="/admin/aisystem/providers" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                <i class="lucide lucide-settings-2 w-4 h-4"></i> {{ t('Manage AI Providers') }}
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-hard-drive w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Storage') }}</h3>
        </div>
        <div class="p-5 grid grid-cols-1 gap-4 text-sm">
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('Storage Path') }}</span>
                <span class="font-mono text-xs font-medium text-slate-900 dark:text-white truncate max-w-[60%]" title="{{ $config['storage_path'] }}">{{ $config['storage_path'] }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('User Agent') }}</span>
                <span class="font-mono text-xs font-medium text-slate-900 dark:text-white truncate max-w-[60%]" title="{{ $config['user_agent'] }}">{{ $config['user_agent'] }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('Stored Items') }}</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ number_format($stats['stored_items'] ?? 0) }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-slate-600 dark:text-slate-400">{{ t('Tracked URLs') }}</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ number_format($stats['tracked_urls'] ?? 0) }}</span>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-900/20 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-cloud-upload w-4 h-4 text-sky-600 dark:text-sky-400"></i>
            </div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Auto-Publish Scraped Posts') }}</h3>
        </div>

        <div class="p-5 sm:p-6 space-y-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {!! t('When enabled, every extraction run turns new scraped items into published posts (attributed to their source, tagged, with a canonical link back). Currently published:').' <strong class="text-slate-700 dark:text-slate-200">'.number_format($config['scraped_posts']).'</strong>' !!}
            </p>

            <form method="post" action="/admin/scraper/settings/autopublish" class="space-y-4">
                @csrf

                <label class="flex items-start justify-between gap-4 py-1 cursor-pointer">
                    <span>
                        <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ t('Publish scraped items as posts') }}</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ t('Requires the extraction pipeline to be enabled.').($config['autopublish_override'] === null ? ' '.t('Currently following the config default.') : '') }}
                        </span>
                    </span>
                    <input type="checkbox" name="autopublish_enabled" value="1" @checked($config['autopublish'])
                           class="mt-0.5 h-5 w-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500/30 flex-shrink-0">
                </label>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Per-run publish limit') }} (1–500)</label>
                    <input type="number" min="1" max="500" name="autopublish_limit" value="{{ $config['autopublish_limit'] }}"
                           class="w-full sm:w-40 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/10">
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-sky-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-save w-4 h-4"></i> {{ t('Save changes') }}
                    </button>
                    <span class="text-xs {{ $config['autopublish'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">
                        {{ $config['autopublish'] ? t('Auto-publish is active') : t('Auto-publish is off') }}
                    </span>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
