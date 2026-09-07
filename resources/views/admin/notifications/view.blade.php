@extends('admin.layout')

@section('title', 'View Notification — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-amber-900 to-orange-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(245,158,11,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-bell w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Notifications</p>
                <h1 class="text-xl font-bold text-white">{{ $notification['title'] }}</h1>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/notifications" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> Back
            </a>
        </div>
    </div>
</div>

<div class="max-w-3xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm mb-6">
        <div class="p-5 space-y-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Notification ID</span>
                <p class="text-sm text-slate-900 dark:text-white">#{{ $notification['id'] }}</p>
            </div>
            <div>
                <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Message</span>
                <p class="text-sm text-slate-900 dark:text-white whitespace-pre-wrap">{{ $notification['message'] }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4 pt-2">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Type</span>
                    <p class="text-sm text-slate-900 dark:text-white">{{ ucfirst($notification['type'] ?? 'info') }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Status</span>
                    <p class="text-sm text-slate-900 dark:text-white">{{ ucfirst($notification['status'] ?? 'unknown') }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Sent At</span>
                    <p class="text-sm text-slate-900 dark:text-white">{{ $notification['sent_to_all_at'] ? \Carbon\Carbon::parse($notification['sent_to_all_at'])->format('M j, Y g:i A') : '—' }}</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Read Count</span>
                    <p class="text-sm text-slate-900 dark:text-white">{{ $notification['read_count'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="flex gap-3">
        <a href="/admin/notifications" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> Back to Notifications
        </a>
        <form action="/admin/notifications/delete/{{ $notification['id'] }}" method="post" onsubmit="return confirm('Delete this notification?');">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-trash-2 w-4 h-4"></i> Delete
            </button>
        </form>
    </div>
</div>

@endsection
