@extends('admin.layout')

@section('title', 'View CV — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-900 to-purple-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-file-text w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">CV Builder</p>
                <h1 class="text-xl font-bold text-white">{{ $cv['full_name'] }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ $cv['job_title'] ?: 'No job title' }} — Created by {{ $cv['username'] }}</p>
            </div>
        </div>
        <a href="/admin/cv" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> Back to CVs
        </a>
    </div>
</div>

<div class="max-w-4xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="p-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Full Name</span>
                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $cv['full_name'] }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Email</span>
                    <p class="text-sm text-slate-900 dark:text-white">{{ $cv['email'] }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Phone</span>
                    <p class="text-sm text-slate-900 dark:text-white">{{ $cv['phone'] ?: '—' }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Job Title</span>
                    <p class="text-sm text-slate-900 dark:text-white">{{ $cv['job_title'] ?: '—' }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Address</span>
                    <p class="text-sm text-slate-900 dark:text-white">{{ $cv['address'] ?: '—' }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Nationality</span>
                    <p class="text-sm text-slate-900 dark:text-white">{{ $cv['nationality'] ?: '—' }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Gender</span>
                    <p class="text-sm text-slate-900 dark:text-white">{{ ucfirst($cv['gender'] ?? 'Not set') }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Date of Birth</span>
                    <p class="text-sm text-slate-900 dark:text-white">{{ $cv['date_of_birth'] ? Carbon\Carbon::parse($cv['date_of_birth'])->format('F j, Y') : '—' }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">LinkedIn</span>
                    <p class="text-sm text-slate-900 dark:text-white truncate">{{ $cv['linkedin'] ?: '—' }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">GitHub</span>
                    <p class="text-sm text-slate-900 dark:text-white truncate">{{ $cv['github'] ?: '—' }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Twitter</span>
                    <p class="text-sm text-slate-900 dark:text-white truncate">{{ $cv['twitter'] ?: '—' }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Portfolio</span>
                    <p class="text-sm text-slate-900 dark:text-white truncate">{{ $cv['portfolio'] ?: '—' }}</p>
                </div>
            </div>

            <hr class="border-slate-100 dark:border-slate-800">

            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-slate-600 dark:text-slate-400">CV ID</span>
                <span class="text-sm font-medium text-slate-900 dark:text-white">#{{ $cv['id'] }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-slate-600 dark:text-slate-400">Created</span>
                <span class="text-sm text-slate-900 dark:text-white">{{ $cv['created_at'] ? Carbon\Carbon::parse($cv['created_at'])->format('M j, Y g:i A') : '—' }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-slate-600 dark:text-slate-400">Last Updated</span>
                <span class="text-sm text-slate-900 dark:text-white">{{ $cv['updated_at'] ? Carbon\Carbon::parse($cv['updated_at'])->format('M j, Y g:i A') : '—' }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-slate-600 dark:text-slate-400">Views</span>
                <span class="text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ $cv['view_count'] }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-slate-600 dark:text-slate-400">Downloads</span>
                <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400">{{ $cv['download_count'] }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-slate-600 dark:text-slate-400">Last Viewed</span>
                <span class="text-sm text-slate-900 dark:text-white">{{ $cv['last_viewed_at'] }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-slate-600 dark:text-slate-400">Status</span>
                <span class="text-sm font-medium {{ $cv['is_active'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-600' }}">{{ $cv['is_active'] ? 'Active' : 'Inactive' }}</span>
            </div>
        </div>
    </div>
</div>

@endsection
