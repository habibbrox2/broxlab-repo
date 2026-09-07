@extends('admin.layout')

@section('title', 'Notifications — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-amber-900 to-orange-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(245,158,11,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-bell w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Administration</p>
                <h1 class="text-xl font-bold text-white">Notifications</h1>
                <p class="text-sm text-white/60 mt-0.5">Send, schedule, and manage user notifications</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/notifications/create" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-amber-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-send w-4 h-4"></i> Send
            </a>
            <a href="/admin/notifications/schedule" class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 border border-white/20 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-calendar w-4 h-4"></i> Schedule
            </a>
        </div>
    </div>
</div>

<div class="max-w-6xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm mb-6">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <i class="lucide lucide-search w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-600"></i>
                    <input type="text" value="{{ $search }}" placeholder="Search notifications..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 pl-9 pr-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10"
                        oninput="this.form.submit()">
                </div>
                <div class="w-full sm:w-36">
                    <select onchange="this.form.submit()" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10">
                        <option value="">All Statuses</option>
                        <option value="sent" {{ $status_filter === 'sent' ? 'selected' : '' }}>Sent</option>
                        <option value="scheduled" {{ $status_filter === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                        <option value="failed" {{ $status_filter === 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="draft" {{ $status_filter === 'draft' ? 'selected' : '' }}>Draft</option>
                    </select>
                </div>
                <div class="w-full sm:w-36">
                    <select onchange="this.form.submit()" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10">
                        <option value="">All Types</option>
                        <option value="info" {{ $type_filter === 'info' ? 'selected' : '' }}>Info</option>
                        <option value="alert" {{ $type_filter === 'alert' ? 'selected' : '' }}>Alert</option>
                        <option value="promotion" {{ $type_filter === 'promotion' ? 'selected' : '' }}>Promotion</option>
                        <option value="system" {{ $type_filter === 'system' ? 'selected' : '' }}>System</option>
                        <option value="maintenance" {{ $type_filter === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Notification</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Type</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Status</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Scheduled</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Sent</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $notification)
                        <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900 dark:text-white">{{ $notification['title'] }}</p>
                                <p class="text-xs text-slate-400 dark:text-slate-600 truncate max-w-xs">{{ Str::limit($notification['message'] ?? '', 80) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                            <i class="lucide lucide-tag w-3 h-3 mr-1"></i>{{ ucfirst($notification['type']) }}
                        </span>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $status = $notification['status'];
                                    $statusClass = match($status) {
                                        'sent' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                        'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                        'failed' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
                                        default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs">{{ $notification['scheduled_at'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs">{{ $notification['sent_to_all_at'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="/admin/notifications/view/{{ $notification['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                        <i class="lucide lucide-eye w-3.5 h-3.5"></i>
                                    </a>
                                    <a href="/admin/notifications/delete/{{ $notification['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors">
                                        <i class="lucide lucide-trash-2 w-3.5 h-3.5"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400 dark:text-slate-600">
                                <i class="lucide lucide-bell-off w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                <p class="text-sm font-medium">No notifications found</p>
                                <p class="text-xs mt-1">Send your first notification to get started</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
