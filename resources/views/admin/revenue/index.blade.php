@extends('admin.layout')

@section('title', 'Revenue — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-violet-900 to-fuchsia-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(139,92,246,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-trend-up w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Administration</p>
                <h1 class="text-xl font-bold text-white">{{ t('Revenue Management') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Manage advertising, sponsored content, and donations') }}</p>
            </div>
        </div>
    </div>
</div>

<div class="max-w-6xl">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <a href="/admin/revenue/ads" class="group overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150">
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center flex-shrink-0 mb-4 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-advertisement w-6 h-6 text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">{{ t('Advertising') }}</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('Manage ad placements, campaigns, and monetization') }}</p>
            </div>
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-800">
                <span class="text-xs font-medium text-slate-400 dark:text-slate-600 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Go to Advertising →</span>
            </div>
        </a>

        <a href="/admin/revenue/sponsored" class="group overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150">
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center flex-shrink-0 mb-4 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-sparkles w-6 h-6 text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">{{ t('Sponsored Packages') }}</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('Create and manage sponsored content packages') }}</p>
            </div>
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-800">
                <span class="text-xs font-medium text-slate-400 dark:text-slate-600 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Go to Packages →</span>
            </div>
        </a>

        <a href="/admin/revenue/donations" class="group overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150">
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-rose-400 to-pink-500 flex items-center justify-center flex-shrink-0 mb-4 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-heart w-6 h-6 text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Donations</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('View and manage user donations') }}</p>
            </div>
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-800">
                <span class="text-xs font-medium text-slate-400 dark:text-slate-600 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Go to Donations →</span>
            </div>
        </a>
    </div>
</div>

@endsection
