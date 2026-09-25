@extends('admin.layout')

@section('title', 'Admin - All Mobiles — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

{{-- ── Gradient Page Header ── --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-smartphone w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Catalog</p>
                <h1 class="text-xl font-bold text-white">{{ t('All Mobiles') }}</h1>
                <p class="text-sm text-white/60 mt-0.5 max-w-md">{{ t('Manage mobile devices in your catalog.') }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/mobiles/create" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-plus w-4 h-4"></i> {{ t('Add New Mobile') }}
            </a>
        </div>
    </div>
</div>

{{-- ── Filter Section ── --}}
<section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
        <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
            <i class="lucide lucide-filter w-4 h-4 text-amber-600 dark:text-amber-400"></i>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Filter Mobiles') }}</h3>
            <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Search by brand/model, sort, filter by status, and adjust page size') }}</p>
        </div>
    </div>
    <form class="p-5" method="get" action="">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <div class="space-y-1.5 xl:col-span-2">
                <label for="mobile-search" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">{{ t('Search') }}</label>
                <div class="relative">
                    <i class="lucide lucide-search absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-600"></i>
                    <input id="mobile-search" type="text" name="search" value="{{ $pagination['search'] }}" placeholder="{{ t('Search brand or model...') }}"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 pl-9 pr-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                </div>
            </div>
            <div class="space-y-1.5">
                <label for="mobile-sort" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">{{ t('Sort By') }}</label>
                <select id="mobile-sort" name="sort"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    <option value="brand_name" {{ $pagination['sort'] === 'brand_name' ? 'selected' : '' }}>{{ t('Brand Name') }}</option>
                    <option value="model_name" {{ $pagination['sort'] === 'model_name' ? 'selected' : '' }}>{{ t('Model Name') }}</option>
                    <option value="official_price" {{ $pagination['sort'] === 'official_price' ? 'selected' : '' }}>{{ t('Official Price') }}</option>
                    <option value="status" {{ $pagination['sort'] === 'status' ? 'selected' : '' }}>Status</option>
                    <option value="created_at" {{ $pagination['sort'] === 'created_at' ? 'selected' : '' }}>{{ t('Created At') }}</option>
                    <option value="id" {{ $pagination['sort'] === 'id' ? 'selected' : '' }}>ID</option>
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="mobile-order" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">Order</label>
                <select id="mobile-order" name="order"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    <option value="ASC" {{ $pagination['order'] === 'ASC' ? 'selected' : '' }}>{{ t('A-Z / Low-High') }}</option>
                    <option value="DESC" {{ $pagination['order'] === 'DESC' ? 'selected' : '' }}>{{ t('Z-A / High-Low') }}</option>
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="mobile-status" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">Status</label>
                <select id="mobile-status" name="status"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    <option value="">{{ t('All Statuses') }}</option>
                    <option value="official" {{ ($pagination['status'] ?? '') === 'official' ? 'selected' : '' }}>Official</option>
                    <option value="unofficial" {{ ($pagination['status'] ?? '') === 'unofficial' ? 'selected' : '' }}>Unofficial</option>
                    <option value="both" {{ ($pagination['status'] ?? '') === 'both' ? 'selected' : '' }}>Both</option>
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="mobile-limit" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">{{ t('Per Page') }}</label>
                <select id="mobile-limit" name="per_page"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    @foreach ([10, 20, 50, 100, 200] as $opt)
                        <option value="{{ $opt }}" {{ ($pagination['per_page'] ?? 50) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150 w-full">
                    <i class="lucide lucide-filter w-4 h-4"></i> Apply
                </button>
            </div>
        </div>
    </form>
</section>

@if (!empty($mobiles))
{{-- ── Mobiles List Table ── --}}
<section class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
            <i class="lucide lucide-smartphone w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('All Mobiles') }}</h3>
            <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Brand, model, pricing, status, and management actions') }}</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-slate-100 dark:border-slate-800">
                    <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap">#</th>
                    <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap">{{ t('Brand') }}</th>
                    <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap">{{ t('Model') }}</th>
                    <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap">Pricing</th>
                    <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($mobiles as $mobile)
                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors duration-100">
                    <td class="px-4 py-3"><span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:text-slate-300">{{ $mobile['id'] }}</span></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            @if (!empty($mobile['image_path']))
                                <img src="{{ $mobile['image_path'] }}" alt="{{ $mobile['brand_name'] }}" class="w-8 h-8 rounded-lg object-cover bg-slate-100 dark:bg-slate-800">
                            @else
                                <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                                    <i class="lucide lucide-smartphone w-4 h-4 text-slate-400"></i>
                                </div>
                            @endif
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $mobile['brand_name'] }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ $mobile['model_name'] }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                            @if($mobile['status'] === 'official') bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400
                            @elseif($mobile['status'] === 'unofficial') bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400
                            @else bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 @endif
                        ">
                            @if($mobile['status'] === 'official')
                                <i class="lucide lucide-check-circle w-3 h-3 mr-1"></i> Official
                            @elseif($mobile['status'] === 'unofficial')
                                <i class="lucide lucide-help-circle w-3 h-3 mr-1"></i> Unofficial
                            @else
                                <i class="lucide lucide-equal w-3 h-3 mr-1"></i> Both
                            @endif
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm">
                            @if($mobile['official_price'] > 0)
                                <span class="font-semibold text-slate-900 dark:text-white">৳{{ number_format($mobile['official_price'], 0) }}</span>
                                @if($mobile['unofficial_price'] > 0)
                                    <span class="text-slate-400 dark:text-slate-600 ml-2">/ ৳{{ number_format($mobile['unofficial_price'], 0) }} (unofficial)</span>
                                @endif
                            @elseif($mobile['unofficial_price'] > 0)
                                <span class="text-slate-500 dark:text-slate-400">৳{{ number_format($mobile['unofficial_price'], 0) }} (unofficial)</span>
                            @else
                                <span class="text-slate-400 dark:text-slate-600">{{ t('Not set') }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center gap-1 justify-end">
                            <a href="/admin/mobiles/view/{{ $mobile['id'] }}"
                               class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 transition-all duration-150" title="View">
                                <i class="lucide lucide-eye w-3.5 h-3.5"></i>
                            </a>
                            <a href="/admin/mobiles/edit/{{ $mobile['id'] }}"
                               class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 transition-all duration-150" title="{{ t('Edit') }}">
                                <i class="lucide lucide-pencil w-3.5 h-3.5"></i>
                            </a>
                            <a href="/admin/mobiles/delete/{{ $mobile['id'] }}"
                               onclick="return confirm('Are you sure you want to delete this mobile? This action cannot be undone.')"
                               class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 transition-all duration-150" title="{{ t('Delete') }}">
                                <i class="lucide lucide-trash-2 w-3.5 h-3.5"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between px-5 py-3.5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
        @include('admin.partials.pagination')
    </div>
</section>
@else
{{-- ── Empty State ── --}}
<section class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
            <i class="lucide lucide-smartphone w-7 h-7 text-slate-400 dark:text-slate-600"></i>
        </div>
        <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">{{ t('No mobiles found') }}</h3>
        <p class="text-sm text-slate-400 dark:text-slate-600 mb-5 max-w-xs">{{ t('Start by adding your first mobile device to the catalog!') }}</p>
        <a href="/admin/mobiles/create" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:-translate-y-0.5 transition-all duration-150">
            <i class="lucide lucide-plus w-4 h-4"></i> {{ t('Add First Mobile') }}
        </a>
    </div>
</section>
@endif

@endsection
