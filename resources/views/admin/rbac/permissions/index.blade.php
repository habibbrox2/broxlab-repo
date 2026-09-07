@extends('admin.layout')

@section('title', 'Permissions — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

{{-- Page Header --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-cyan-900 to-sky-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(6,182,212,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-lock-open w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">RBAC — Role-Based Access Control</p>
                <h1 class="text-xl font-bold text-white">Permissions</h1>
                <p class="text-sm text-white/60 mt-0.5">Define granular permissions that can be assigned to roles</p>
            </div>
        </div>
        <a href="/admin/permissions/create" class="inline-flex items-center gap-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-cyan-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-plus w-4 h-4"></i> Create Permission
        </a>
    </div>
</div>

{{-- Filters --}}
<div class="max-w-6xl mb-6">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-cyan-50 dark:bg-cyan-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-filter w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Filters</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-600">Search and filter permissions</p>
                </div>
            </div>
        </div>
        <div class="p-5 flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <div class="relative">
                    <i class="lucide lucide-search w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-600"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search permissions..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 pl-9 pr-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/10"
                        oninput="this.form.submit()">
                </div>
            </div>
            <div class="w-full sm:w-40">
                <select name="module" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/10">
                    <option value="">All Modules</option>
                    @foreach($modules as $module)
                        <option value="{{ $module }}" {{ $module_filter === $module ? 'selected' : '' }}>{{ ucfirst($module) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

{{-- Permissions Table --}}
<div class="max-w-6xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-cyan-50 dark:bg-cyan-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-lock-open w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">All Permissions</h3>
            </div>
            <span class="text-xs text-slate-400 dark:text-slate-600">{{ $pagination['total'] }} permissions total</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Permission</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Module</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Description</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Roles</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($permissions as $permission)
                        <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-cyan-400 to-sky-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                        {{ substr($permission['name'], 0, 2) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900 dark:text-white">{{ $permission['name'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ ucfirst($permission['module']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs">{{ $permission['description'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $roleCount = $permission['role_count'];
                                @endphp
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 dark:text-slate-400">
                                    <i class="lucide lucide-shield w-3 h-3"></i> {{ $roleCount }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="/admin/permissions/view/{{ $permission['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="View">
                                        <i class="lucide lucide-eye w-3.5 h-3.5"></i>
                                    </a>
                                    <a href="/admin/permissions/edit/{{ $permission['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="Edit">
                                        <i class="lucide lucide-edit w-3.5 h-3.5"></i>
                                    </a>
                                    <a href="/admin/permissions/delete/{{ $permission['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors" title="Delete">
                                        <i class="lucide lucide-trash-2 w-3.5 h-3.5"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-600">
                                <i class="lucide lucide-lock-x w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                <p class="text-sm font-medium">No permissions found</p>
                                <p class="text-xs mt-1">Create your first permission to get started</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pagination['last_page'] > 1)
            <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20 flex items-center justify-between">
                <span class="text-xs text-slate-400 dark:text-slate-600">
                    Showing {{ ($pagination['current_page'] - 1) * $pagination['per_page'] + 1 }} to {{ min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) }} of {{ $pagination['total'] }} permissions
                </span>
                <div class="flex items-center gap-2">
                    @if($pagination['current_page'] > 1)
                        <a href="{{ '/admin/permissions?page=1&module='.urlencode($module_filter) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">First</a>
                        <a href="{{ '/admin/permissions?page='.($pagination['current_page'] - 1).'&module='.urlencode($module_filter) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Prev</a>
                    @endif
                    <span class="text-xs text-slate-500 dark:text-slate-500 px-2">Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}</span>
                    @if($pagination['current_page'] < $pagination['last_page'])
                        <a href="{{ '/admin/permissions?page='.($pagination['current_page'] + 1).'&module='.urlencode($module_filter) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Next</a>
                        <a href="{{ '/admin/permissions?page='.$pagination['last_page'].'&module='.urlencode($module_filter) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Last</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@endsection
