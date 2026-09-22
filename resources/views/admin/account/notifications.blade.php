@extends('admin.layout')

@section('title', 'My Notifications — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div x-data="adminNotifications({{ $unread_count }})">

    <div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-amber-900 to-orange-900 text-white shadow-xl shadow-slate-900/20">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(245,158,11,0.25),transparent_55%)]" aria-hidden="true"></div>
        <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-bell w-5 h-5 text-white"></i>
                </div>
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('My Account') }}</p>
                    <h1 class="text-xl font-bold text-white">{{ t('My Notifications') }}</h1>
                    <p class="text-sm text-white/60 mt-0.5">
                        {{ t('System messages, announcements and account alerts addressed to you') }}
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($unread_count > 0)
                    <button type="button" x-on:click="markAllRead()"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 border border-white/20 px-4 py-2 text-sm font-semibold text-white hover:bg-white/25 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-check-check w-4 h-4"></i> {{ t('Mark all read') }}
                    </button>
                @endif
                <a href="/admin/account-settings" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                    <i class="lucide lucide-settings w-4 h-4"></i> {{ t('Settings') }}
                </a>
            </div>
        </div>
    </div>

    @if ($unread_count > 0)
        <div class="mb-4 flex items-center gap-2.5 rounded-2xl border border-indigo-200 dark:border-indigo-900/50 bg-indigo-50 dark:bg-indigo-950/30 px-4 py-3 text-sm text-indigo-800 dark:text-indigo-300" role="status">
            <i class="lucide lucide-info w-4 h-4 flex-shrink-0"></i>
            <span>{{ t('You have') }} <strong x-text="unread">{{ $unread_count }}</strong> {{ t('unread notifications') }}</span>
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($notifications as $notif)
            <div data-notification-id="{{ $notif['id'] }}"
                 class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm
                        {{ empty($notif['is_read']) ? 'border-l-4 border-l-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/20' : '' }}">
                <div class="p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="mb-1.5 flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $notif['title'] }}</h3>
                                @if (empty($notif['is_read']))
                                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-semibold text-white">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white"></span> {{ t('New') }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-semibold capitalize text-slate-600 dark:text-slate-400">
                                    {{ $notif['type'] ?? 'info' }}
                                </span>
                                @if (!empty($notif['status']))
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize
                                                 {{ $notif['status'] === 'scheduled' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300' : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300' }}">
                                        {{ $notif['status'] }}
                                    </span>
                                @endif
                            </div>

                            <p class="text-sm text-slate-700 dark:text-slate-300">{{ $notif['message'] ?? '' }}</p>

                            <p class="mt-1.5 text-xs text-slate-400 dark:text-slate-600">
                                {{ !empty($notif['created_at']) ? \Carbon\Carbon::parse($notif['created_at'])->diffForHumans() : '' }}
                                @if (!empty($notif['scheduled_at']))
                                    · {{ t('scheduled for') }} {{ \Carbon\Carbon::parse($notif['scheduled_at'])->format('M j, Y g:i A') }}
                                @endif
                            </p>

                            <div class="mt-3 flex flex-wrap gap-2">
                                @if (!empty($notif['action_url']))
                                    <a href="{{ $notif['action_url'] }}"
                                       class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white transition-all duration-150">
                                        <i class="lucide lucide-arrow-right w-3.5 h-3.5"></i> {{ t('View details') }}
                                    </a>
                                @endif
                                @if (empty($notif['is_read']))
                                    <button type="button" x-on:click="markRead({{ $notif['id'] }}, $el)"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all duration-150">
                                        <i class="lucide lucide-check w-3.5 h-3.5"></i> {{ t('Mark as read') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-10 text-center shadow-sm">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400">
                    <i class="lucide lucide-bell-off w-5 h-5"></i>
                </div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ t('No notifications yet') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('New notifications addressed to you will appear here.') }}</p>
            </div>
        @endforelse
    </div>

    @if ($total_pages > 1)
        <nav class="mt-6 flex items-center justify-center gap-1 flex-wrap" aria-label="{{ t('Page navigation') }}">
            @if ($page > 1)
                <a href="?page={{ $page - 1 }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 px-3 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <i class="lucide lucide-chevron-left w-4 h-4"></i>
                </a>
            @endif
            @for ($p = 1; $p <= $total_pages; $p++)
                @if ($p === $page)
                    <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-indigo-600 px-3 text-sm font-bold text-white">{{ $p }}</span>
                @else
                    <a href="?page={{ $p }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 px-3 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">{{ $p }}</a>
                @endif
            @endfor
            @if ($page < $total_pages)
                <a href="?page={{ $page + 1 }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 px-3 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <i class="lucide lucide-chevron-right w-4 h-4"></i>
                </a>
            @endif
        </nav>
        <p class="mt-3 text-center text-xs text-slate-400 dark:text-slate-600">{{ $total }} {{ t('notifications') }}</p>
    @endif
</div>

@endsection

@push('scripts')
<script>
// Owner-scoped mark-read: both endpoints filter on Auth::id() server-side, so
// this can only ever touch the signed-in admin's own rows.
function adminNotifications(initialUnread) {
    return {
        unread: initialUnread,
        async markRead(id, el) {
            try {
                const res = await fetch('/api/notification/mark-read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ notification_id: id }),
                });
                const data = await res.json();
                if (data.success) {
                    const card = el.closest('[data-notification-id]');
                    if (card) {
                        card.classList.remove('border-l-indigo-500', 'bg-indigo-50/40', 'dark:bg-indigo-950/20');
                    }
                    el.remove();
                    if (this.unread > 0) { this.unread--; }
                }
            } catch (e) { /* non-fatal */ }
        },
        async markAllRead() {
            try {
                const res = await fetch('/api/notification/mark-all-read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                }
            } catch (e) { /* non-fatal */ }
        },
    };
}
</script>
@endpush
