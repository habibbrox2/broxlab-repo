@extends('layouts.app')

@section('title', $title.' — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8"
     x-data="notifications({{ $unread_count }})">

    {{-- Page header --}}
    <div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600/10 via-indigo-50 to-white p-6 sm:p-8">
        <div class="pointer-events-none absolute inset-0 opacity-10" aria-hidden="true">
            <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-indigo-400/30 blur-3xl"></div>
            <div class="absolute -bottom-10 -left-10 h-32 w-32 rounded-full bg-violet-400/20 blur-3xl"></div>
        </div>
        <div class="relative flex items-center justify-between gap-4">
            <div class="flex items-center gap-2.5">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/20">
                    <i class="lucide lucide-bell" style="width:1.25em;height:1.25em;"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">আমার নোটিফিকেশন</h1>
                    <p class="mt-0.5 text-sm text-slate-500">আপনার সকল নোটিফিকেশন এখানে পাবেন</p>
                </div>
            </div>
            @if ($unread_count > 0)
            <button class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:bg-indigo-700"
                    x-on:click="markAllRead()">
                <i class="lucide lucide-check" style="width:0.875em;height:0.875em;"></i> সব পড়া হিসেবে চিহ্নিত করুন
            </button>
            @endif
        </div>
    </div>

    {{-- Unread badge --}}
    @if ($unread_count > 0)
    <div class="mb-4 flex items-center rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-800" role="alert">
        <i class="lucide lucide-info mr-2"></i>
        <span>আপনার <strong x-text="unread"> {{ $unread_count }} </strong> টি অপড়া নোটিফিকেশন আছে</span>
    </div>
    @endif

    {{-- Notifications list --}}
    <div class="space-y-3">
        @forelse ($notifications as $notif)
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm {{ empty($notif['is_read']) ? 'border-l-4 border-l-indigo-500 bg-blue-50/60' : '' }}"
             data-notification-id="{{ $notif['id'] }}">
            <div class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <h5 class="text-sm font-bold text-slate-900">{{ $notif['title'] }}</h5>
                            @if (empty($notif['is_read']))
                                <span class="inline-flex items-center rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-medium text-white">
                                    <i class="lucide lucide-circle mr-1" style="width:0.6em;height:0.6em;"></i>নতুন
                                </span>
                            @endif
                            <span class="inline-flex items-center rounded-full bg-neutral-200 px-2 py-0.5 text-xs font-medium capitalize text-neutral-700">{{ $notif['type'] }}</span>
                        </div>
                        <p class="mb-1 text-sm text-slate-700">{{ $notif['message'] }}</p>
                        <span class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($notif['created_at'])->diffForHumans() }}</span>
                        <div class="mt-3 flex gap-2">
                            @if (!empty($notif['action_url']))
                                <a href="{{ $notif['action_url'] }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-indigo-700">
                                    <i class="lucide lucide-arrow-right mr-1 h-3.5 w-3.5"></i>বিস্তারিত দেখুন
                                </a>
                            @endif
                            @if (empty($notif['is_read']))
                                <button class="inline-flex items-center rounded-lg border border-neutral-300 px-3 py-1.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100"
                                        x-on:click="markRead({{ $notif['id'] }}, $el)">
                                    <i class="lucide lucide-check mr-1 h-3.5 w-3.5"></i>পড়া হিসেবে চিহ্নিত করুন
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                <i class="lucide lucide-bell-off h-5 w-5"></i>
            </div>
            <p class="text-sm font-semibold text-slate-700">কোনো নোটিফিকেশন নেই</p>
            <p class="mt-1 text-sm text-slate-500">নতুন নোটিফিকেশন এখানে দেখা যাবে।</p>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if ($total_pages > 1)
    <nav class="mt-6 flex items-center justify-center gap-1" aria-label="Pagination">
        @for ($p = 1; $p <= $total_pages; $p++)
            @if ($p === $page)
                <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-indigo-600 px-3 text-sm font-bold text-white">{{ $p }}</span>
            @else
                <a href="?page={{ $p }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">{{ $p }}</a>
            @endif
        @endfor
    </nav>
    @endif
</div>
@endsection

@push('scripts')
<script>
function notifications(initialUnread) {
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
                        card.classList.remove('border-l-indigo-500', 'bg-blue-50/60');
                    }
                    el.remove();
                    if (this.unread > 0) this.unread--;
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
