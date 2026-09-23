@extends('admin.layout')

@section('title', 'Hero Alif Products')

@section('content')
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(16,185,129,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-package w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Hero Alif</p>
                <h1 class="text-xl font-bold text-white">{{ t('Products') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Smart Bazar, oil, fuel, machinery and services catalog.') }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/ha/products/create" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition-all">
                <i class="lucide lucide-plus w-4 h-4"></i> {{ t('Add Product') }}
            </a>
        </div>
    </div>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <form class="flex flex-wrap gap-2 p-4 border-b border-slate-100" method="GET">
        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="{{ t('Search name, SKU, barcode') }}"
               class="rounded-lg border border-slate-300 px-3 py-2 text-sm w-64">
        <select name="module" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">{{ t('All modules') }}</option>
            @foreach ($modules as $m)
                <option value="{{ $m }}" @selected($filters['module'] === $m)>{{ str_replace('_', ' ', $m) }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ t('Filter') }}</button>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ t('Product') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Module') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Category') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Cost') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Retail') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Stock') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $p)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a class="font-semibold text-slate-900 hover:text-indigo-600" href="/admin/ha/products/edit/{{ $p->id }}">{{ $p->name }}</a>
                            <div class="text-xs text-slate-500">{{ $p->sku }}@if($p->barcode) · {{ $p->barcode }}@endif</div>
                        </td>
                        <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium">{{ str_replace('_', ' ', $p->module) }}</span></td>
                        <td class="px-4 py-3 text-slate-600">{{ $p->category->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">৳{{ number_format((float) $p->cost_price, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold">৳{{ number_format((float) $p->retail_price, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            @if (! $p->is_physical)
                                <span class="text-slate-400">{{ t('service') }}</span>
                            @elseif ($p->stock_qty <= $p->reorder_level)
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">{{ $p->stock_qty }}</span>
                            @else
                                {{ $p->stock_qty }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a class="text-indigo-600 hover:underline" href="/admin/ha/products/edit/{{ $p->id }}">{{ t('Edit') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">{{ t('No products yet. Add your first product.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4 border-t border-slate-100">{{ $products->links() }}</div>
</div>
@endsection
