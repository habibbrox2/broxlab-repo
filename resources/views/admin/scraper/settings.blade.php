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
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Scraping Pipeline</p>
                <h1 class="text-xl font-bold text-white">Scraping Settings</h1>
                <p class="text-sm text-white/60 mt-0.5">Configure scraper preferences, limits, and automation rules</p>
            </div>
        </div>
        <a href="/admin/scraper" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> Back
        </a>
    </div>
</div>

<div class="max-w-4xl">
    <div class="space-y-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-sliders w-4 h-4 text-purple-600 dark:text-purple-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Scraping Limits</h3>
                </div>
                <a href="/admin/scraper/settings/limits" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit →</a>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Max Items per Run</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">100</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Delay Between Requests</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">2 seconds</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Concurrent Jobs</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">3</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Retry on Failure</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">Yes (3 attempts)</span>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-cpu w-4 h-4 text-violet-600 dark:text-violet-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Automation</h3>
                </div>
                <a href="/admin/scraper/settings/automation" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Configure →</a>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Auto Start</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">Disabled</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Schedule</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">Not configured</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Notifications</span>
                    <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400">Enabled</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Duplicate Filtering</span>
                    <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400">Enabled</span>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-hard-drive w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Storage</h3>
                </div>
                <a href="/admin/scraper/settings/storage" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Configure →</a>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Storage Path</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white truncate max-w-[200px]">storage/app/scraping</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Retention</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">7 days</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Cleanup Schedule</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">Daily at 2:00 AM</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Exported Format</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">JSON + CSV</span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
