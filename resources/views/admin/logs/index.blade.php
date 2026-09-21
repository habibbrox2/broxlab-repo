@extends('admin.layout')

@section('title', 'Activity Logs — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(100,116,139,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-clipboard-list w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Administration</p>
                <h1 class="text-xl font-bold text-white">{{ t('Activity Logs') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Track admin actions and system events') }}</p>
            </div>
        </div>
    </div>
</div>

<div class="max-w-6xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-slate-500 dark:bg-slate-600/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-clock w-4 h-4 text-slate-400 dark:text-slate-600"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Recent Activity') }}</h3>
            </div>
            <span class="text-xs text-slate-400 dark:text-slate-600">{{ count($logs) }} events</span>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($logs as $log)
                <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                    <div class="w-2 h-2 rounded-full flex-shrink-0
                        @if($log['domain'] === 'users') bg-emerald-500
                        @elseif($log['domain'] === 'notifications') bg-amber-500
                        @elseif($log['domain'] === 'posts') bg-blue-500
                        @elseif($log['domain'] === 'services') bg-violet-500
                        @elseif($log['domain'] === 'roles') bg-indigo-500
                        @elseif($log['domain'] === 'permissions') bg-cyan-500
                        @elseif($log['domain'] === 'mobiles') bg-pink-500
                        @else bg-slate-400 dark:bg-slate-600 @endif"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-slate-900 dark:text-white">
                            <span class="font-medium">{{ $log['username'] }}</span>
                            <span class="text-slate-500 dark:text-slate-400"> {{ $log['activity'] }}</span>
                        </p>
                        <p class="text-xs text-slate-400 dark:text-slate-600 mt-0.5">
                            {{ $log['domain'] ?? 'system' }} · {{ $log['action'] ?? '' }}
                            @if($log['item_id'])
                                <span class="text-slate-300 dark:text-slate-700">#{{ $log['item_id'] }}</span>
                            @endif
                        </p>
                    </div>
                    <span class="text-xs text-slate-400 dark:text-slate-600 flex-shrink-0">
                        {{ $log['created_at'] ? \Carbon\Carbon::parse($log['created_at'])->format('M j, Y g:i A') : '—' }}
                    </span>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-slate-400 dark:text-slate-600">
                    <i class="lucide lucide-clipboard-list-off w-8 h-8 mx-auto mb-2 opacity-50"></i>
                    <p class="text-sm font-medium">{{ t('No activity logs yet') }}</p>
                    <p class="text-xs mt-1">{{ t('Admin actions will appear here') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

@endsection
