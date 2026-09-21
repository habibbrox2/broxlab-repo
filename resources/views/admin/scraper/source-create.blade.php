@extends('admin.layout')

@section('title', 'Add Scraping Source — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-blue-900 to-cyan-900 text-white shadow-xl shadow-slate-900/20">
    <div class="relative px-6 py-5 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-plus w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Scraping Pipeline') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Add a Source') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Sources are declared in config/scraper.php') }}</p>
            </div>
        </div>
        <a href="/admin/scraper/sources" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Sources') }}
        </a>
    </div>
</div>

<div class="max-w-4xl space-y-6">
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">{{ t('How it works') }}</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
            {{ t('The pipeline auto-discovers an RSS/Atom feed from the homepage, falls back to sitemap.xml, then to HTML listing heuristics — entirely in PHP (Laravel HTTP client + DOMDocument).') }}
        </p>
        <pre class="overflow-x-auto rounded-xl bg-slate-900 text-slate-100 p-4 text-xs leading-relaxed"><code>// config/scraper.php → 'sources' => [ ... ]
[
    'key'        => 'my_source',        // stable id, used for the snapshot filename
    'name'       => 'My Source',
    'type'       => 'news',             // news | jobs | tech
    'homepage'   => 'https://example.com',
    'feed'       => 'https://example.com/feed', // optional explicit feed
    'lang'       => 'bn',               // bn | en
    'strategy'   => 'auto',             // auto | feed | sitemap | html
    'link_pattern' => '/(news|story)/i', // optional, html strategy only
    'enabled'    => true,
],</code></pre>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">{{ t('Environment flags') }}</h3>
        <ul class="text-sm text-slate-600 dark:text-slate-400 space-y-1.5 font-mono text-xs">
            <li>CONTENT_EXTRACT_ENABLED=true</li>
            <li>CONTENT_EXTRACT_MAX_ITEMS=25</li>
            <li>CONTENT_EXTRACT_TIMEOUT=15</li>
            <li>CONTENT_EXTRACT_DELAY_MS=400</li>
            <li>CONTENT_EXTRACT_AI_ENRICH=true</li>
            <li>CONTENT_EXTRACT_AI_MODEL=openai/gpt-4o-mini</li>
        </ul>
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
            {{ t('These are namespaced separately from the legacy MedEx SCRAPER_* flags so the two pipelines stay independent.') }}
        </p>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">{{ t('Currently configured') }}</h3>
        <div class="flex flex-wrap gap-2">
            @foreach ($sources as $source)
                <span class="rounded-full border border-slate-200 dark:border-slate-700 px-3 py-1 text-xs text-slate-600 dark:text-slate-300">{{ $source['name'] }}</span>
            @endforeach
        </div>
    </div>
</div>

@endsection
