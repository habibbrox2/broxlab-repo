@extends('admin.layout')

@section('title', 'Delete Permission — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

{{-- Page Header --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-red-900 to-rose-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(239,68,68,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-trash-2 w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">RBAC — Role-Based Access Control</p>
                <h1 class="text-xl font-bold text-white">Delete Permission</h1>
                <p class="text-sm text-white/60 mt-0.5">This action is irreversible. The permission will be permanently deleted.</p>
            </div>
        </div>
        <a href="/admin/permissions" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> Back to Permissions
        </a>
    </div>
</div>

{{-- Delete Confirmation Card --}}
<div class="max-w-3xl">
    <div class="overflow-hidden rounded-2xl border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-rose-100 dark:border-rose-800 bg-rose-50/60 dark:bg-rose-900/20">
            <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-alert-triangle w-4 h-4 text-rose-600 dark:text-rose-400"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Confirm Deletion</h3>
                <p class="text-xs text-slate-400 dark:text-slate-600">Please review before proceeding</p>
            </div>
        </div>
        <div class="p-5 sm:p-6 space-y-5">
            <div class="rounded-xl border border-rose-100 dark:border-rose-800 bg-rose-50/40 dark:bg-rose-900/10 px-4 py-3">
                <p class="text-sm text-slate-700 dark:text-slate-300">
                    Are you sure you want to permanently delete the permission
                    <span class="font-semibold text-rose-600 dark:text-rose-400">{{ $permission['name'] }}</span>?
                    This action cannot be undone.
                </p>
            </div>

            <div class="p-4 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-2">
                <h4 class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Permission Details</h4>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-slate-400 dark:text-slate-500">Name:</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $permission['name'] }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500">Module:</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ ucfirst($permission['module']) }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500">Description:</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $permission['description'] ?: '—' }}</span>
                    </div>
                </div>
            </div>

            @if(count($permission['roles']) > 0)
                <div class="p-4 bg-amber-50 dark:bg-amber-900/10 rounded-xl border border-amber-200 dark:border-amber-800">
                    <p class="text-sm text-amber-800 dark:text-amber-400 flex items-start gap-2">
                        <i class="lucide lucide-alert-circle w-4 h-4 flex-shrink-0 mt-0.5"></i>
                        <span><strong>Warning:</strong> This permission is currently assigned to <strong>{{ count($permission['roles']) }} role(s)</strong>. Deleting this permission will remove it from these roles.</span>
                    </p>
                </div>
                <div class="p-4 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700">
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-2">Roles that will be affected:</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($permission['roles'] as $role)
                            <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                                {{ $role['name'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                <form action="/admin/permissions/delete/{{ $permission['id'] }}" method="post" onsubmit="return confirm('Are you absolutely sure? This action cannot be undone.');">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-rose-600 hover:bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-rose-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-trash-2 w-4 h-4"></i> Delete Permission
                    </button>
                </form>
                <a href="/admin/permissions" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                    <i class="lucide lucide-x w-4 h-4"></i> Cancel
                </a>
            </div>
        </div>
    </div>
</div>

@endsection
