@extends('admin.layout')

@section('title', 'Edit Role — '.($appSettings['site_name'] ?? 'BroxLab'))

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
                <h1 class="text-xl font-bold text-white">Edit Role</h1>
                <p class="text-sm text-white/60 mt-0.5">Update role details and permissions</p>
            </div>
        </div>
        <a href="/admin/roles" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> Back to Roles
        </a>
    </div>
</div>

{{-- Role Info Banner --}}
<div class="max-w-2xl mb-6">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                {{ substr($role['name'], 0, 2) }}
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $role['name'] }}</h2>
                <p class="text-sm text-slate-400 dark:text-slate-600">{{ $role['description'] ?: 'No description' }}</p>
            </div>
        </div>
    </div>
</div>

{{-- Form --}}
<div class="max-w-2xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-edit w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Edit Role</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-600">Update the role details</p>
                </div>
            </div>
        </div>
        <div class="p-5 sm:p-6 space-y-5">
            <form method="post" action="/admin/roles/edit/{{ $role['id'] }}" class="space-y-5">
                @csrf
                <input type="hidden" name="id" value="{{ $role['id'] }}">

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                        Role Name <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                    </label>
                    <input type="text" name="name" value="{{ $role['name'] }}" required maxlength="50"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/10">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">Description</label>
                    <textarea name="description" rows="2" maxlength="500"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/10">{{ $role['description'] }}</textarea>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">Ranking</label>
                    <input type="number" name="ranking" value="{{ $role['ranking'] }}" min="0" step="1"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/10">
                </div>

                <div class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_super_admin" value="1" {{ $role['is_super_admin'] ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-10 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-500/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-emerald-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                    </label>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Super Admin Role</p>
                        <p class="text-xs text-slate-400 dark:text-slate-600">Grant all permissions automatically. Use with caution.</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-emerald-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-check-circle w-4 h-4"></i> Update Role
                    </button>
                    <a href="/admin/roles" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-x w-4 h-4"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
