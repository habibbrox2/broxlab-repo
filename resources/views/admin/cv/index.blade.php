@extends('admin.layout')

@section('title', 'CV Builder — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-900 to-purple-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-file-text w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Specialized Modules') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('CV Builder') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Manage user CVs, templates, and analytics') }}</p>
            </div>
        </div>
    </div>
</div>

<div class="max-w-6xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-layers w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('All CVs') }}</h3>
            </div>
            <span class="text-xs text-slate-400 dark:text-slate-600">{{ $pagination['total'] }} CVs total</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Name') }}</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Job Title') }}</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">User</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Status</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Views</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Downloads</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cvs as $cv)
                        <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900 dark:text-white">{{ $cv['full_name'] }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $cv['job_title'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $cv['username'] }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                                    @if($cv['is_active']) bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400
                                    @else bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 @endif">
                                    {{ $cv['is_active'] ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-center">{{ $cv['view_count'] }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-center">{{ $cv['download_count'] }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="/admin/cv/view/{{ $cv['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                    <i class="lucide lucide-eye w-3.5 h-3.5"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400 dark:text-slate-600">
                                <i class="lucide lucide-file-text-off w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                <p class="text-sm font-medium">{{ t('No CVs found') }}</p>
                                <p class="text-xs mt-1">{{ t('User CVs will appear here') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pagination['last_page'] > 1)
            <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20 flex items-center justify-between">
                <span class="text-xs text-slate-400 dark:text-slate-600">Showing {{ ($pagination['current_page'] - 1) * $pagination['per_page'] + 1 }} to {{ min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) }} of {{ $pagination['total'] }} CVs</span>
                <div class="flex items-center gap-2">
                    @if($pagination['current_page'] > 1)
                        <a href="{{ '/admin/cv?page=1' }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">First</a>
                        <a href="{{ '/admin/cv?page='.($pagination['current_page'] - 1) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Prev</a>
                    @endif
                    <span class="text-xs text-slate-500 dark:text-slate-500 px-2">Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}</span>
                    @if($pagination['current_page'] < $pagination['last_page'])
                        <a href="{{ '/admin/cv?page='.($pagination['current_page'] + 1) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">{{ t('Next') }}</a>
                        <a href="{{ '/admin/cv?page='.$pagination['last_page'] }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Last</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@endsection
