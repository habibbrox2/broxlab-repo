@extends('admin.layout')

@section('title', 'Roles — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

{{-- Page Header --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-emerald-900 to-teal-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(16,185,129,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-shield w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">RBAC — Role-Based Access Control</p>
                <h1 class="text-xl font-bold text-white">Roles</h1>
                <p class="text-sm text-white/60 mt-0.5">Define roles to group permissions and assign them to users</p>
            </div>
        </div>
        <a href="/admin/roles/create" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-emerald-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-plus w-4 h-4"></i> Create Role
        </a>
    </div>
</div>

{{-- Roles Table --}}
<div class="max-w-6xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-shield w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">All Roles</h3>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Role</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Description</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Rank</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Users</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Permissions</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Super Admin</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                        {{ substr($role['name'], 0, 2) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900 dark:text-white">{{ $role['name'] }}</p>
                                        @if($role['is_super_admin'])
                                            <span class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                                                <i class="lucide lucide-star w-3 h-3"></i> Super Admin
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs">{{ $role['description'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-400">{{ $role['ranking'] }}</td>
                            <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-400">{{ $role['user_count'] }}</td>
                            <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-400">{{ $role['permission_count'] }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($role['is_super_admin'])
                                    <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-xs font-semibold dark:bg-amber-900/30 dark:text-amber-400">Yes</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-500 px-2 py-0.5 text-xs font-semibold dark:bg-slate-800 dark:text-slate-500">No</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="/admin/roles/view/{{ $role['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="View">
                                        <i class="lucide lucide-eye w-3.5 h-3.5"></i>
                                    </a>
                                    <a href="/admin/roles/edit/{{ $role['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="Edit">
                                        <i class="lucide lucide-edit w-3.5 h-3.5"></i>
                                    </a>
                                    <a href="/admin/roles/delete/{{ $role['id'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors" title="Delete">
                                        <i class="lucide lucide-trash-2 w-3.5 h-3.5"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400 dark:text-slate-600">
                                <i class="lucide lucide-shield-off w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                <p class="text-sm font-medium">No roles found</p>
                                <p class="text-xs mt-1">Create your first role to get started</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
