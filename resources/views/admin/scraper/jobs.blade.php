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
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Scraping Pipeline</p>
                <h1 class="text-xl font-bold text-white">Scraping Jobs</h1>
                <p class="text-sm text-white/60 mt-0.5">Monitor and manage active scraping tasks</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/scraper" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> Back
            </a>
            <button class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-amber-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-play w-4 h-4"></i> Start Job
            </button>
        </div>
    </div>
</div>

<div class="max-w-6xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-orange-50 dark:bg-orange-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-list w-4 h-4 text-orange-600 dark:text-orange-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Active & Recent Jobs</h3>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Job Name</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Source</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Status</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Progress</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Last Run</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400 dark:text-slate-600">
                            <i class="lucide lucide-bolt-off w-8 h-8 mx-auto mb-2 opacity-50"></i>
                            <p class="text-sm font-medium">No scraping jobs configured</p>
                            <p class="text-xs mt-1">Add sources to start scraping</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
