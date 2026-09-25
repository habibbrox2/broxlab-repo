@extends('admin.layout')

@section('title', 'Photo Studio — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-pink-900 to-rose-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(244,63,94,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-wand-sparkles w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Specialized Modules') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Photo Studio') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Configure AI cutout and photo editing tools') }}</p>
            </div>
        </div>
        <a href="/admin" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back to Dashboard') }}
        </a>
    </div>
</div>

<div class="max-w-6xl">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm group hover:shadow-md hover:-translate-y-0.5 transition-all duration-150">
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-pink-400 to-rose-500 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-wand-2 w-6 h-6 text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">{{ t('AI Cutout') }}</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600 mb-4">{{ t('Configure AI background removal') }}</p>
                <a href="/admin/photo-studio/cutout" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    {{ t('Cutout Settings') }} <i class="lucide lucide-arrow-right w-4 h-4"></i>
                </a>
            </div>
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm group hover:shadow-md hover:-translate-y-0.5 transition-all duration-150">
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-400 to-fuchsia-500 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-palette w-6 h-6 text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">{{ t('Editor Settings') }}</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600 mb-4">{{ t('Configure photo editor options') }}</p>
                <a href="/admin/photo-studio/editor" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    {{ t('Editor Settings') }} <i class="lucide lucide-arrow-right w-4 h-4"></i>
                </a>
            </div>
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm group hover:shadow-md hover:-translate-y-0.5 transition-all duration-150">
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-history w-6 h-6 text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">{{ t('Usage History') }}</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600 mb-4">{{ t('View photo studio usage logs') }}</p>
                <a href="/admin/photo-studio/history" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    {{ t('View History') }} <i class="lucide lucide-arrow-right w-4 h-4"></i>
                </a>
            </div>
        </div>
    </div>
</div>

@endsection
