@extends('admin.layout')

@section('title', 'Kharij — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-rose-900 to-pink-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(244,63,94,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-book-open w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Specialized Modules</p>
                <h1 class="text-xl font-bold text-white">Kharij</h1>
                <p class="text-sm text-white/60 mt-0.5">Manage Kharij records and exam data</p>
            </div>
        </div>
    </div>
</div>

<div class="max-w-6xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="p-6 flex items-center justify-center py-12">
            <div class="text-center">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-4">
                    <i class="lucide lucide-book-open w-8 h-8 text-slate-400 dark:text-slate-600"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Kharij Module</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600 max-w-md mx-auto">Manage Islamic exam records, results, and student data.</p>
                <div class="mt-6 flex gap-3 justify-center">
                    <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <i class="lucide lucide-users w-4 h-4"></i> Records
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <i class="lucide lucide-chart-bar w-4 h-4"></i> Results
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <i class="lucide lucide-school w-4 h-4"></i> Students
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
