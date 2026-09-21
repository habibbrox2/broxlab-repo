@extends('admin.layout')

@section('title', 'Delete Mobile — '.($mobile['brand_name'] ?? '').' '.($mobile['model_name'] ?? ''))

@section('content')

{{-- ── Gradient Page Header ── --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-red-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(239,68,68,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-trash-2 w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Danger Zone') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Delete Mobile') }}</h1>
                <p class="text-sm text-white/60 mt-0.5 max-w-md">{{ t('This action cannot be undone. All specifications, images, and comments will be permanently deleted.') }}</p>
            </div>
        </div>
        <a href="/admin/mobiles" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150 self-start sm:self-auto">
            <i class="lucide lucide-x w-4 h-4"></i> {{ t('Cancel') }}
        </a>
    </div>
</div>

{{-- ── Delete Confirmation Card ── --}}
<div class="max-w-2xl">
    <div class="overflow-hidden rounded-2xl border border-red-200 dark:border-red-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-red-100 dark:border-red-800 bg-red-50/60 dark:bg-red-950/30">
            <div class="w-7 h-7 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-alert-triangle w-4 h-4 text-red-600 dark:text-red-400"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Confirm Deletion') }}</h3>
                <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Please review the details below before proceeding') }}</p>
            </div>
        </div>
        <div class="p-5 space-y-4">

            {{-- Mobile Info --}}
            <div class="grid grid-cols-2 gap-4 p-4 bg-slate-50 dark:bg-slate-800/30 rounded-xl">
                <div>
                    <p class="text-xs text-slate-400 dark:text-slate-600 mb-1">ID</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $mobile['id'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 dark:text-slate-600 mb-1">{{ t('Brand') }}</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $mobile['brand_name'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 dark:text-slate-600 mb-1">{{ t('Model') }}</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $mobile['model_name'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 dark:text-slate-600 mb-1">Status</p>
                    <p class="text-sm font-semibold capitalize @if($mobile['status'] === 'official') text-emerald-600 @elseif($mobile['status'] === 'unofficial') text-amber-600 @else text-slate-600 @endif">
                        {{ ucfirst($mobile['status'] ?? 'unknown') }}
                    </p>
                </div>
            </div>

            {{-- Warning --}}
            <div class="p-4 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-xl flex gap-3">
                <i class="lucide lucide-warning w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0"></i>
                <div class="text-sm text-amber-800 dark:text-amber-300">
                    <p class="font-semibold mb-1">Warning</p>
                    <p>{{ t('This will permanently delete the following associated data:') }}</p>
                    <ul class="mt-2 space-y-1 list-disc list-inside text-amber-700 dark:text-amber-400">
                        <li>All specifications ({{ count($specifications) }} items)</li>
                        <li>All images ({{ count($images) }} items)</li>
                        <li>{{ t('All tag associations') }}</li>
                        <li>{{ t('All comments') }}</li>
                    </ul>
                </div>
            </div>

            {{-- Delete Button --}}
            <form method="post" action="/admin/mobiles/delete/{{ $mobile['id'] }}">
                @csrf
                <div class="pt-2">
                    <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 hover:bg-red-700 px-6 py-3 text-sm font-semibold text-white shadow-sm shadow-red-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150 w-full">
                        <i class="lucide lucide-trash-2 w-4 h-4"></i>
                        {{ t('Yes, Delete This Mobile') }}
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

@endsection
