@extends('admin.layout')

@section('title', 'Users — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

{{-- Page Header --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-900 to-purple-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-users w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Administration</p>
                <h1 class="text-xl font-bold text-white">Users</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Manage user accounts, roles, and permissions') }}</p>
            </div>
        </div>
    </div>
</div>

{{-- Filter Card --}}
<div class="max-w-6xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm mb-6">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-filter w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Filters</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Search and filter users') }}</p>
                </div>
            </div>
        </div>
        <div class="p-5 space-y-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <x-admin-input label="Search" placeholder="{{ t('Search by username, email, name...') }}" icon="lucide-search" :value="$search" route="{{ '/admin/users?search='.urlencode($search) }}" />
                </div>
                <div class="w-full sm:w-40">
                    <x-admin-select label="Status" :options="['' => 'All Statuses', 'active' => 'Active', 'inactive' => 'Inactive', 'banned' => 'Banned', 'pending' => 'Pending']" :selected="$status_filter" route="{{ '/admin/users?status='.urlencode($status_filter) }}" />
                </div>
            </div>
        </div>
    </div>

{{-- Users Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-users w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('User Accounts') }}</h3>
            </div>
            <span class="text-xs text-slate-400 dark:text-slate-600">{{ $pagination['total'] }} users total</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">User</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Status</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Role</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Created</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">{{ t('Last Login') }}</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                        {{ substr($user['username'] ?? '', 0, 2) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900 dark:text-white">{{ $user['username'] }}</p>
                                        <p class="text-xs text-slate-400 dark:text-slate-600">{{ $user['email'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                                    @if($user['status'] === 'active') bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400
                                    @elseif($user['status'] === 'inactive') bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400
                                    @elseif($user['status'] === 'banned') bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400
                                    @else bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 @endif">
                                    {{ ucfirst($user['status']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $user['role'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $user['created_at'] ? \Carbon\Carbon::parse($user['created_at'])->format('M j, Y') : '—' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $user['last_login'] }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="/admin/users/view/{{ $user['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="View">
                                        <i class="lucide lucide-eye w-3.5 h-3.5"></i>
                                    </a>
                                    <a href="/admin/users/edit/{{ $user['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="{{ t('Edit') }}">
                                        <i class="lucide lucide-edit w-3.5 h-3.5"></i>
                                    </a>
                                    <a href="/admin/users/delete/{{ $user['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors" title="{{ t('Delete') }}">
                                        <i class="lucide lucide-trash-2 w-3.5 h-3.5"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400 dark:text-slate-600">
                                <i class="lucide lucide-users-off w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                <p class="text-sm font-medium">{{ t('No users found') }}</p>
                                <p class="text-xs mt-1">{{ t('Try adjusting your search or filters') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pagination['last_page'] > 1)
            <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20 flex items-center justify-between">
                <span class="text-xs text-slate-400 dark:text-slate-600">
                    Showing {{ ($pagination['current_page'] - 1) * $pagination['per_page'] + 1 }} to {{ min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) }} of {{ $pagination['total'] }} users
                </span>
                <div class="flex items-center gap-2">
                    @if($pagination['current_page'] > 1)
                        <a href="{{ '/admin/users?page=1&sort='.$sort.'&order='.$order }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <i class="lucide lucide-chevron-double-left w-3.5 h-3.5"></i> First
                        </a>
                        <a href="{{ '/admin/users?page='.($pagination['current_page'] - 1).'&sort='.$sort.'&order='.$order }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <i class="lucide lucide-chevron-left w-3.5 h-3.5"></i> Prev
                        </a>
                    @endif
                    <span class="text-xs text-slate-500 dark:text-slate-500 px-2">Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}</span>
                    @if($pagination['current_page'] < $pagination['last_page'])
                        <a href="{{ '/admin/users?page='.($pagination['current_page'] + 1).'&sort='.$sort.'&order='.$order }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            {{ t('Next') }} <i class="lucide lucide-chevron-right w-3.5 h-3.5"></i>
                        </a>
                        <a href="{{ '/admin/users?page='.$pagination['last_page'].'&sort='.$sort.'&order='.$order }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            Last <i class="lucide lucide-chevron-double-right w-3.5 h-3.5"></i>
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@endsection
