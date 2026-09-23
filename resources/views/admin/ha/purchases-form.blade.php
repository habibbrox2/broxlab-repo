@extends('admin.layout')

@section('title', 'New Purchase')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-bold text-slate-900">{{ t('New purchase') }}</h1>
    <a href="/admin/ha/purchases" class="text-sm text-indigo-600 hover:underline">← {{ t('Back') }}</a>
</div>

<form method="POST" action="/admin/ha/purchases/create" x-data="{
    rows: [0],
    products: @js($products),
    cost(id) { const p = this.products.find(p => p.id == id); return p ? parseFloat(p.cost_price) : 0; }
}" class="grid gap-6 lg:grid-cols-3">
    @csrf
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Supplier') }}</label>
                    <select name="supplier_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— {{ t('No supplier') }} —</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s['id'] }}">{{ $s['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Purchase date') }} *</label>
                    <input type="date" name="purchase_date" required value="{{ date('Y-m-d') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold">{{ t('Items') }}</h2>
            <template x-for="(row, idx) in rows" :key="idx">
                <div class="mb-3 grid gap-2 sm:grid-cols-12 items-end">
                    <div class="sm:col-span-5">
                        <select :name="'items['+idx+'][product_id]'" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">{{ t('Select product') }}</option>
                            <template x-for="p in products" :key="p.id">
                                <option :value="p.id" x-text="p.sku+' — '+p.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <input type="number" :name="'items['+idx+'][qty]'" min="1" placeholder="{{ t('Qty') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-3">
                        <input type="number" step="0.01" min="0" :name="'items['+idx+'][unit_cost]'" placeholder="{{ t('Unit cost') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="button" x-on:click="rows.length > 1 && rows.pop()" class="w-full rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 hover:bg-red-100">{{ t('Remove') }}</button>
                    </div>
                </div>
            </template>
            <button type="button" x-on:click="rows.push(rows.length)" class="mt-1 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">+ {{ t('Add item row') }}</button>
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Discount') }} ৳</label>
                <input type="number" step="0.01" min="0" name="discount" value="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Transport cost') }} ৳</label>
                <input type="number" step="0.01" min="0" name="transport_cost" value="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Payment method') }} *</label>
                <select name="payment_method" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach (['cash','bkash','nagad','bank','due','mixed'] as $m)
                        <option value="{{ $m }}">{{ ucfirst($m) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Paid amount') }} ৳</label>
                <input type="number" step="0.01" min="0" name="paid_amount" value="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">{{ t('Rest becomes supplier due.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('Note') }}</label>
                <textarea name="note" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            </div>
            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">{{ t('Receive purchase') }}</button>
            <p class="text-xs text-slate-500">{{ t('Stock increases atomically with this action; failures roll back everything.') }}</p>
        </div>
    </div>
</form>
@endsection
