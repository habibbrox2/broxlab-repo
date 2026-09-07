@extends('admin.layout')

@section('title', 'View Permission — '.($appSettings['site_name'] ?? 'BroxLab'))

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
                <h1 class="text-xl font-bold text-white">{{ $permission['name'] }}</h1>
                <p class="text-sm text-white/60 mt-0.5">Module: {{ ucfirst($permission['module']) }} {{ $permission['description'] ? '— ' . $permission['description'] : '' }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/permissions/edit/{{ $permission['id'] }}" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-edit w-4 h-4"></i> Edit
            </a>
            <a href="/admin/permissions" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> Back to Permissions
            </a>
        </div>
    </div>
</div>

{{-- Permission Details --}}
<div class="max-w-6xl">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            {{-- Permission Info --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-cyan-50 dark:bg-cyan-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-lock-open w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Permission Details</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-600">Permission information and metadata</p>
                        </div>
                    </div>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Permission Name</label>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $permission['name'] }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Module</label>
                        <span class="inline-flex items-center rounded-full bg-cyan-100 dark:bg-cyan-900/30 px-2.5 py-1 text-xs font-semibold text-cyan-700 dark:text-cyan-400">
                            {{ ucfirst($permission['module']) }}
                        </span>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Description</label>
                        <p class="text-sm text-slate-900 dark:text-white">{{ $permission['description'] ?: '—' }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Created</label>
                        <p class="text-sm text-slate-900 dark:text-white">{{ $permission['created_at'] ? \Carbon\Carbon::parse($permission['created_at'])->format('M j, Y g:i A') : '—' }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Updated</label>
                        <p class="text-sm text-slate-900 dark:text-white">{{ $permission['updated_at'] ? \Carbon\Carbon::parse($permission['updated_at'])->format('M j, Y g:i A') : '—' }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Permission ID</label>
                        <p class="text-sm text-slate-900 dark:text-white">#{{ $permission['id'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Roles with this permission --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-cyan-50 dark:bg-cyan-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-shield w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Assigned Roles</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-600">{{ count($permission['roles']) }} role(s) have this permission</p>
                        </div>
                    </div>
                    <span class="text-xs text-slate-400 dark:text-slate-600">Click a role to remove this permission</span>
                </div>
                <div class="p-5">
                    @if(count($permission['roles']) > 0)
                        <div class="space-y-2">
                            @foreach($permission['roles'] as $role)
                                <form action="/admin/permissions/remove-role/{{ $permission['id'] }}" method="post" class="inline-flex">
                                    @csrf
                                    <input type="hidden" name="role_id" value="{{ $role['id'] }}">
                                    <button type="submit" class="group flex items-center justify-between rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 px-4 py-3 text-left transition-all duration-150 hover:border-rose-300 hover:bg-rose-50 dark:hover:border-rose-700 dark:hover:bg-rose-900/20 w-full">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                                {{ substr($role['name'], 0, 2) }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $role['name'] }}</p>
                                                <p class="text-xs text-slate-400 dark:text-slate-600">Ranking: {{ $role['ranking'] }}</p>
                                            </div>
                                        </div>
                                        <span class="text-rose-500 group-hover:rotate-180 transition-transform">
                                            <i class="lucide lucide-x w-4 h-4"></i>
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-400 dark:text-slate-600 py-3 text-center bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">No roles have this permission</p>
                    @endif
                </div>
            </div>

            {{-- Assign Role Form --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-cyan-50 dark:bg-cyan-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-plus w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Assign to Role</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-600">Add this permission to a role</p>
                        </div>
                    </div>
                </div>
                <div class="p-5">
                    @foreach($allRoles as $role)
                        @php
                            $assignedRoleIds = collect($permission['roles'])->pluck('id')->all();
                            $isAssigned = in_array($role['id'], $assignedRoleIds);
                        @endphp
                        @if(!$isAssigned)
                            <form action="/admin/permissions/assign-role/{{ $permission['id'] }}" method="post" class="inline-flex mb-2">
                                @csrf
                                <input type="hidden" name="role_id" value="{{ $role['id'] }}">
                                <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-150">
                                    <i class="lucide lucide-plus w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400"></i>
                                    {{ $role['name'] }}
                                </button>
                            </form>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Stats --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Permission Statistics</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Permission ID</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">#{{ $permission['id'] }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Module</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ ucfirst($permission['module']) }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Roles Assigned</span>
                        <span class="text-sm font-medium text-cyan-600 dark:text-cyan-400">{{ count($permission['roles']) }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Created</span>
                        <span class="text-sm text-slate-900 dark:text-white">{{ $permission['created_at'] ? \Carbon\Carbon::parse($permission['created_at'])->format('M j, Y') : '—' }}</span>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="p-5 space-y-3">
                    <a href="/admin/permissions/edit/{{ $permission['id'] }}" class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-edit w-4 h-4 text-cyan-600 dark:text-cyan-400"></i> Edit Permission
                    </a>
                    <a href="/admin/permissions/delete/{{ $permission['id'] }}" class="inline-flex w-full items-center gap-2 rounded-xl border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-trash-2 w-4 h-4"></i> Delete Permission
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
