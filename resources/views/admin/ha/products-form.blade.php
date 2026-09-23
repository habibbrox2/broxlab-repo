@extends('admin.layout')

@section('title', $product ? 'Edit Product' : 'Add Product')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-bold text-slate-900">{{ $product ? t('Edit Product') : t('Add Product') }}</h1>
    <a href="/admin/ha/products" class="text-sm text-indigo-600 hover:underline">← {{ t('Back to products') }}</a>
</div>

<form method="POST" action="{{ $product ? '/admin/ha/products/edit/'.$product->id : '/admin/ha/products/create' }}" class="grid gap-6 lg:grid-cols-3">
    @csrf
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Product name') }} *</label>
                    <input name="name" required value="{{ old('name', $product->name ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">SKU *</label>
                    <input name="sku" required value="{{ old('sku', $product->sku ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Barcode') }}</label>
                    <input name="barcode" value="{{ old('barcode', $product->barcode ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Module') }} *</label>
                    <select name="module" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($modules as $m)
                            <option value="{{ $m }}" @selected(old('module', $product->module ?? 'smart_bazar') === $m)>{{ str_replace('_', ' ', $m) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Category') }}</label>
                    <select name="category_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected(old('category_id', $product->category_id ?? null) == $c->id)>{{ $c->name }} ({{ str_replace('_', ' ', $c->module) }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Brand') }}</label>
                    <select name="brand_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b->id }}" @selected(old('brand_id', $product->brand_id ?? null) == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Unit') }} *</label>
                    <select name="unit" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($units as $u)
                            <option value="{{ $u }}" @selected(old('unit', $product->unit ?? 'pcs') === $u)>{{ $u }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-4 pb-1">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_physical" value="0">
                        <input type="checkbox" name="is_physical" value="1" @checked(old('is_physical', $product->is_physical ?? true)) class="rounded border-slate-300">
                        {{ t('Physical product (tracks stock)') }}
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Description') }}</label>
                <textarea name="description" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('description', $product->description ?? '') }}</textarea>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold text-slate-900">{{ t('Pricing & stock') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Cost price') }} ৳ *</label>
                    <input type="number" step="0.01" min="0" name="cost_price" required value="{{ old('cost_price', $product->cost_price ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Retail price') }} ৳ *</label>
                    <input type="number" step="0.01" min="0" name="retail_price" required value="{{ old('retail_price', $product->retail_price ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Wholesale price') }} ৳</label>
                    <input type="number" step="0.01" min="0" name="wholesale_price" value="{{ old('wholesale_price', $product->wholesale_price ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                @unless ($product)
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Opening stock') }}</label>
                        <input type="number" min="0" name="initial_stock_qty" value="{{ old('initial_stock_qty', 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">{{ t('Recorded as an `opening` ledger movement.') }}</p>
                    </div>
                @endunless
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Reorder level') }}</label>
                    <input type="number" min="0" name="reorder_level" value="{{ old('reorder_level', $product->reorder_level ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Min stock') }}</label>
                    <input type="number" min="0" name="min_stock" value="{{ old('min_stock', $product->min_stock ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Max stock') }}</label>
                    <input type="number" min="0" name="max_stock" value="{{ old('max_stock', $product->max_stock ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            @if ($product)
                <p class="mt-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                    {{ t('Current stock') }}: <strong>{{ $product->stock_qty }}</strong> — {{ t('changes go through the stock-adjustment form or purchases, never direct edit.') }}
                </p>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) class="rounded border-slate-300">
                {{ t('Active (visible in shop)') }}
            </label>
            <button type="submit" class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ $product ? t('Save changes') : t('Create product') }}
            </button>
        </div>

        @if ($product && count($movements))
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-3 font-semibold text-slate-900">{{ t('Recent stock movements') }}</h2>
                <ul class="space-y-2 text-sm">
                    @foreach ($movements as $mv)
                        <li class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2 last:border-0">
                            <span class="text-slate-600">{{ $mv->created_at->format('d M') }} · {{ str_replace('_', ' ', $mv->type) }}</span>
                            <span class="{{ $mv->qty > 0 ? 'text-emerald-600' : 'text-red-600' }} font-semibold tabular-nums">{{ $mv->qty > 0 ? '+' : '' }}{{ $mv->qty }}</span>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="/admin/ha/products/{{ $product->id }}/stock" class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                    @csrf
                    <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="adjustment">{{ t('Adjustment (±)') }}</option>
                        <option value="damage">{{ t('Damage (−)') }}</option>
                        <option value="purchase_return">{{ t('Purchase return (−)') }}</option>
                    </select>
                    <input type="number" name="qty" placeholder="{{ t('Qty (use minus for reduction)') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="note" placeholder="{{ t('Reason/note') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">{{ t('Apply stock change') }}</button>
                </form>
            </div>
        @endif
    </div>
</form>
@endsection
