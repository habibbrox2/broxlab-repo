@extends('admin.layout')

@section('title', 'Admin - Services — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

{{-- ── Gradient Page Header ── --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-briefcase w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Services') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('All Services') }}</h1>
                <p class="text-sm text-white/60 mt-0.5 max-w-md">{{ t('Manage your service offerings.') }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/services/create" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-plus w-4 h-4"></i> {{ t('Add New Service') }}
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
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Filter Services') }}</h3>
            <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Search by name, sort, filter by status, and adjust page size') }}</p>
        </div>
    </div>
    <form class="p-5" method="get" action="">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <div class="space-y-1.5 xl:col-span-2">
                <label for="service-search" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">{{ t('Search') }}</label>
                <div class="relative">
                    <i class="lucide lucide-search absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-600"></i>
                    <input id="service-search" type="text" name="search" value="{{ $pagination['search'] }}" placeholder="{{ t('Search by name or description...') }}"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 pl-9 pr-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                </div>
            </div>
            <div class="space-y-1.5">
                <label for="service-sort" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">{{ t('Sort By') }}</label>
                <select id="service-sort" name="sort"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    <option value="name" {{ $pagination['sort'] === 'name' ? 'selected' : '' }}>{{ t('Name') }}</option>
                    <option value="created_at" {{ $pagination['sort'] === 'created_at' ? 'selected' : '' }}>{{ t('Created At') }}</option>
                    <option value="id" {{ $pagination['sort'] === 'id' ? 'selected' : '' }}>ID</option>
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="service-order" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">Order</label>
                <select id="service-order" name="order"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    <option value="ASC" {{ $pagination['order'] === 'ASC' ? 'selected' : '' }}>{{ t('A-Z / Oldest') }}</option>
                    <option value="DESC" {{ $pagination['order'] === 'DESC' ? 'selected' : '' }}>{{ t('Z-A / Newest') }}</option>
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="service-status" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">Status</label>
                <select id="service-status" name="status"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    <option value="">{{ t('All Statuses') }}</option>
                    <option value="active" {{ $pagination['status'] === 'active' ? 'selected' : '' }}>{{ t('Active') }}</option>
                    <option value="inactive" {{ $pagination['status'] === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="archived" {{ $pagination['status'] === 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="service-limit" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">{{ t('Per Page') }}</label>
                <select id="service-limit" name="per_page"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    @foreach ([10, 20, 50, 100] as $opt)
                        <option value="{{ $opt }}" {{ $pagination['per_page'] === $opt ? 'selected' : '' }}>{{ $opt }}</option>
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

@if (!empty($services))
{{-- ── Services List Table ── --}}
<section class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
            <i class="lucide lucide-briefcase w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('All Services') }}</h3>
            <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Name, status, form fields, images, and management actions') }}</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-slate-100 dark:border-slate-800">
                    <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap">#</th>
                    <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap">{{ t('Name') }}</th>
                    <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap">{{ t('Details') }}</th>
                    <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($services as $service)
                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors duration-100">
                    <td class="px-4 py-3"><span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:text-slate-300">{{ $service['id'] }}</span></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            @if(!empty($service['icon']))
                                <i class="{{ trim($service['icon']) }} w-4 h-4 text-slate-500"></i>
                            @else
                                <div class="w-4 h-4 rounded bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                                    <i class="lucide lucide-briefcase w-3 h-3 text-slate-400"></i>
                                </div>
                            @endif
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $service['name'] }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                            @if($service['status'] === 'active') bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400
                            @elseif($service['status'] === 'inactive') bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400
                            @else bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 @endif
                        ">{{ ucfirst($service['status']) }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-xs text-slate-500 dark:text-slate-400 space-y-1">
                            @if(!empty($service['form_field_count']))
                                <span class="flex items-center gap-1">
                                    <i class="lucide lucide-template w-3 h-3"></i> {{ $service['form_field_count'] }} fields
                                </span>
                            @endif
                            @if(!empty($service['image_count']))
                                <span class="flex items-center gap-1">
                                    <i class="lucide lucide-image w-3 h-3"></i> {{ $service['image_count'] }} images
                                </span>
                            @endif
                            @if(!empty($service['is_premium']))
                                <span class="flex items-center gap-1">
                                    <i class="lucide lucide-star w-3 h-3"></i> Premium
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center gap-1 justify-end">
                            <a href="/admin/services/view/{{ $service['id'] }}"
                               class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 transition-all duration-150" title="View">
                                <i class="lucide lucide-eye w-3.5 h-3.5"></i>
                            </a>
                            <a href="/admin/services/edit/{{ $service['id'] }}"
                               class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 transition-all duration-150" title="{{ t('Edit') }}">
                                <i class="lucide lucide-pencil w-3.5 h-3.5"></i>
                            </a>
                            <a href="/admin/services/delete/{{ $service['id'] }}"
                               onclick="return confirm('Are you sure you want to delete this service? This action cannot be undone.')"
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
        <div class="text-sm text-slate-500 dark:text-slate-400">
            Showing {{ $pagination['from'] }} to {{ $pagination['to'] }} of {{ $pagination['total'] }} services
        </div>
        @include('admin.partials.pagination')
    </div>
</section>
@else
{{-- ── Empty State ── --}}
<section class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
            <i class="lucide lucide-briefcase w-7 h-7 text-slate-400 dark:text-slate-600"></i>
        </div>
        <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">{{ t('No services found') }}</h3>
        <p class="text-sm text-slate-400 dark:text-slate-600 mb-5 max-w-xs">{{ t('Start by creating your first service offering!') }}</p>
        <a href="/admin/services/create" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:-translate-y-0.5 transition-all duration-150">
            <i class="lucide lucide-plus w-4 h-4"></i> {{ t('Add First Service') }}
        </a>
    </div>
</section>
@endif

@endsection
