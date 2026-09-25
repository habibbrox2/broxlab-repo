@extends('admin.layout')

@php
    $isEdit = $package->exists;
@endphp

@section('title', ($isEdit ? 'Edit' : 'Create').' Sponsored Package — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-amber-900 to-orange-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(245,158,11,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-{{ $isEdit ? 'pencil' : 'plus' }} w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Revenue</p>
                <h1 class="text-xl font-bold text-white">{{ t($isEdit ? 'Edit Sponsored Package' : 'Create Sponsored Package') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t($isEdit ? 'Update the package details below' : 'Define a new sponsored content package') }}</p>
            </div>
        </div>
        <a href="/admin/revenue/sponsored" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back to Packages') }}
        </a>
    </div>
</div>

<div class="max-w-3xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-package w-4 h-4 text-amber-600 dark:text-amber-400"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Package Details') }}</h3>
                <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Name, pricing and visible features of the package') }}</p>
            </div>
        </div>
        <div class="p-5 sm:p-6">
            <form method="post" action="{{ $isEdit ? '/admin/revenue/sponsored/edit' : '/admin/revenue/sponsored/create' }}" class="space-y-5">
                @csrf
                @if ($isEdit)
                    <input type="hidden" name="id" value="{{ $package->id }}">
                @endif

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="pkg-name" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                            {{ t('Package Name') }} <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                        </label>
                        <input id="pkg-name" type="text" name="name" value="{{ old('name', $package->name) }}" required maxlength="120"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10">
                    </div>

                    <div class="space-y-1.5">
                        <label for="pkg-slug" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                            {{ t('Slug') }} <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                        </label>
                        <input id="pkg-slug" type="text" name="slug" value="{{ old('slug', $package->slug) }}" required maxlength="140" pattern="[a-z0-9]+(-[a-z0-9]+)*"
                            placeholder="gold-package"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10">
                        <p class="text-xs text-slate-400 dark:text-slate-600 mt-1">{{ t('Lowercase letters, numbers and dashes only') }}</p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="pkg-desc" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Description') }}</label>
                    <textarea id="pkg-desc" name="description" rows="2" maxlength="2000"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10">{{ old('description', $package->description) }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-5 sm:grid-cols-4">
                    <div class="space-y-1.5">
                        <label for="pkg-price" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                            {{ t('Price (৳)') }} <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                        </label>
                        <input id="pkg-price" type="number" name="price" value="{{ old('price', $package->price) }}" required min="0" step="0.01"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10">
                    </div>

                    <div class="space-y-1.5">
                        <label for="pkg-period" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Billing') }}</label>
                        <select id="pkg-period" name="billing_period"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10">
                            <option value="monthly" @selected(old('billing_period', $package->billing_period) === 'monthly')>{{ t('Monthly') }}</option>
                            <option value="yearly" @selected(old('billing_period', $package->billing_period) === 'yearly')>{{ t('Yearly') }}</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="pkg-tier" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Tier') }}</label>
                        <select id="pkg-tier" name="tier"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10">
                            <option value="1" @selected((int) old('tier', $package->tier ?? 1) === 1)>{{ t('Bronze') }}</option>
                            <option value="2" @selected((int) old('tier', $package->tier ?? 1) === 2)>{{ t('Silver') }}</option>
                            <option value="3" @selected((int) old('tier', $package->tier ?? 1) === 3)>{{ t('Gold') }}</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="pkg-icon" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Icon') }}</label>
                        <select id="pkg-icon" name="icon"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10">
                            @foreach (['sparkles', 'package', 'star', 'crown', 'gem', 'zap'] as $icon)
                                <option value="{{ $icon }}" @selected(old('icon', $package->icon ?? 'sparkles') === $icon)>{{ $icon }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="pkg-features" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Features') }}</label>
                    <textarea id="pkg-features" name="features" rows="4" placeholder="{{ t('One feature per line') }}"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10">{{ old('features', isset($package->features) && is_array($package->features) ? implode("\n", $package->features) : '') }}</textarea>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <label class="flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package->is_active ?? true))
                            class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500/30">
                        {{ t('Package is active') }}
                    </label>

                    <div class="space-y-1.5">
                        <label for="pkg-sort" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Sort Order') }}</label>
                        <input id="pkg-sort" type="number" name="sort_order" value="{{ old('sort_order', $package->sort_order ?? 0) }}" min="0" max="65535"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500/10">
                    </div>
                </div>

                <div class="flex items-center gap-3 border-t border-slate-100 dark:border-slate-800 pt-5">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-amber-600 hover:bg-amber-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-amber-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-{{ $isEdit ? 'save' : 'plus' }} w-4 h-4"></i> {{ t($isEdit ? 'Save Changes' : 'Create Package') }}
                    </button>
                    <a href="/admin/revenue/sponsored" class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-700 px-5 py-2.5 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all duration-150">
                        {{ t('Cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
