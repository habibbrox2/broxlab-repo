@extends('admin.layout')

@section('title', 'View Role — '.($appSettings['site_name'] ?? 'BroxLab'))

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
                <h1 class="text-xl font-bold text-white">{{ $role['name'] }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ $role['description'] ?: 'No description' }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/roles/edit/{{ $role['id'] }}" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-edit w-4 h-4"></i> Edit
            </a>
            <a href="/admin/roles" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> Back to Roles
            </a>
        </div>
    </div>
</div>

{{-- Role Details --}}
<div class="max-w-6xl">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            {{-- Role Info --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-shield w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Role Details</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-600">Role information and metadata</p>
                        </div>
                    </div>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Role Name</label>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $role['name'] }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Description</label>
                        <p class="text-sm text-slate-900 dark:text-white">{{ $role['description'] ?: '—' }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Ranking</label>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $role['ranking'] }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Super Admin</label>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                            @if($role['is_super_admin']) bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400
                            @else bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 @endif">
                            {{ $role['is_super_admin'] ? 'Yes — All Permissions' : 'No' }}
                        </span>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Created</label>
                        <p class="text-sm text-slate-900 dark:text-white">{{ $role['created_at'] ? \Carbon\Carbon::parse($role['created_at'])->format('M j, Y g:i A') : '—' }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Updated</label>
                        <p class="text-sm text-slate-900 dark:text-white">{{ $role['updated_at'] ? \Carbon\Carbon::parse($role['updated_at'])->format('M j, Y g:i A') : '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Permissions --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-lock-open w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Permissions</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-600">{{ count($role['permissions']) }} permission(s) assigned</p>
                        </div>
                    </div>
                    <span class="text-xs text-slate-400 dark:text-slate-600">Click a permission to remove it from this role</span>
                </div>
                <div class="p-5">
                    @if(count($role['permissions']) > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($role['permissions'] as $permission)
                                <form action="/admin/roles/remove-permission/{{ $role['id'] }}" method="post" class="inline-flex">
                                    @csrf
                                    <input type="hidden" name="permission_id" value="{{ $permission['id'] }}">
                                    <button type="submit" class="group flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 px-3 py-2.5 text-left transition-all duration-150 hover:border-rose-300 hover:bg-rose-50 dark:hover:border-rose-700 dark:hover:bg-rose-900/20">
                                        <i class="lucide lucide-lock-open w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 group-hover:text-rose-600 group-hover:dark:text-rose-400"></i>
                                        <span class="text-xs font-medium text-slate-900 dark:text-white">{{ $permission['name'] }}</span>
                                        <span class="text-xs text-slate-400 dark:text-slate-600">({{ $permission['module'] }})</span>
                                        <span class="ml-auto text-rose-500 group-hover:rotate-180 transition-transform">
                                            <i class="lucide lucide-x w-3.5 h-3.5"></i>
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-400 dark:text-slate-600 py-3 text-center bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">No permissions assigned to this role</p>
                    @endif
                </div>
            </div>

            {{-- Assign Permission Form --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-plus w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Assign Permission</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-600">Add a permission to this role</p>
                        </div>
                    </div>
                </div>
                <div class="p-5">
                    @foreach($allPermissions as $permission)
                        @php
                            $assignedIds = collect($role['permissions'])->pluck('id')->all();
                            $isAssigned = in_array($permission['id'], $assignedIds);
                        @endphp
                        @if(!$isAssigned)
                            <form action="/admin/roles/assign-permission/{{ $role['id'] }}" method="post" class="inline-flex mb-2">
                                @csrf
                                <input type="hidden" name="permission_id" value="{{ $permission['id'] }}">
                                <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-150">
                                    <i class="lucide lucide-plus w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400"></i>
                                    {{ $permission['name'] }} <span class="text-slate-400 dark:text-slate-600">({{ $permission['module'] }})</span>
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
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Role Statistics</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Role ID</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">#{{ $role['id'] }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Users Assigned</span>
                        <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400">{{ count($role['users']) }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Permissions</span>
                        <span class="text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ count($role['permissions']) }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Super Admin</span>
                        <span class="text-sm font-medium {{ $role['is_super_admin'] ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400 dark:text-slate-600' }}">{{ $role['is_super_admin'] ? 'Yes' : 'No' }}</span>
                    </div>
                </div>
            </div>

            {{-- Users with this role --}}
            @if(count($role['users']) > 0)
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Users with this Role</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-600">{{ count($role['users']) }} user(s)</p>
                    </div>
                    <div class="p-5 space-y-2">
                        @foreach($role['users'] as $user)
                            <a href="/admin/users/view/{{ $user['id'] }}" class="flex items-center gap-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 px-3 py-2.5 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                    {{ substr($user['username'], 0, 2) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ $user['username'] }}</p>
                                    <p class="text-xs text-slate-400 dark:text-slate-600 truncate">{{ $user['email'] }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Quick Actions --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="p-5 space-y-3">
                    <a href="/admin/roles/edit/{{ $role['id'] }}" class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-edit w-4 h-4 text-indigo-600 dark:text-indigo-400"></i> Edit Role
                    </a>
                    <a href="/admin/roles/delete/{{ $role['id'] }}" class="inline-flex w-full items-center gap-2 rounded-xl border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-trash-2 w-4 h-4"></i> Delete Role
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
