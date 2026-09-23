@extends('admin.layout')

@section('title', 'Inventory Movements')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-bold text-slate-900">{{ t('Inventory movements') }} <span class="text-sm font-normal text-slate-500">— {{ t('stock ledger') }}</span></h1>
    <form method="GET" class="flex gap-2">
        <select name="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">{{ t('All types') }}</option>
            @foreach ($types as $t)
                <option value="{{ $t }}" @selected($typeFilter === $t)>{{ str_replace('_', ' ', $t) }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ t('Filter') }}</button>
    </form>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ t('Date') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Product') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Type') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Qty') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Balance') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Unit cost') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Note') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($movements as $mv)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $mv->created_at->format('d M H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $mv->product->name ?? '#' . $mv->product_id }}</div>
                            <div class="text-xs text-slate-500">{{ $mv->product->sku ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ str_replace('_', ' ', $mv->type) }}</span></td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold {{ $mv->qty > 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $mv->qty > 0 ? '+' : '' }}{{ $mv->qty }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $mv->balance_after }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $mv->unit_cost !== null ? '৳'.number_format((float) $mv->unit_cost, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $mv->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">{{ t('No stock movements yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-slate-100">{{ $movements->links() }}</div>
</div>
@endsection
