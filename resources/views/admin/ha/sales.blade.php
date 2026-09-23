@extends('admin.layout')

@section('title', 'Sales')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-bold text-slate-900">{{ t('Sales') }}</h1>
    <form method="GET" class="flex gap-2">
        <input name="search" value="{{ $search }}" placeholder="{{ t('Invoice no') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm w-44">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">{{ t('All') }}</option>
            @foreach (\App\Models\HaSale::STATUSES as $s)
                <option value="{{ $s }}" @selected($statusFilter === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ t('Filter') }}</button>
    </form>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ t('Invoice') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Date') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Customer') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Total') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Paid') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Due') }}</th>
                    <th class="px-4 py-3 text-center">{{ t('Status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sales as $s)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs font-semibold">
                            <a href="/admin/ha/pos/receipt/{{ $s->id }}" class="text-indigo-600 hover:underline">{{ $s->invoice_no }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $s->created_at->format('d M, H:i') }}</td>
                        <td class="px-4 py-3">{{ $s->customer->name ?? t('Walk-in') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">৳{{ number_format((float) $s->grand_total, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-emerald-700">৳{{ number_format((float) $s->paid_amount, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums {{ (float) $s->due_amount > 0 ? 'text-red-600 font-semibold' : '' }}">৳{{ number_format((float) $s->due_amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold @if($s->status==='completed') bg-emerald-100 text-emerald-700 @elseif($s->status==='refunded') bg-red-100 text-red-700 @elseif($s->status==='held') bg-amber-100 text-amber-700 @else bg-slate-100 @endif">{{ $s->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($s->status === 'completed')
                                <form method="POST" action="/admin/ha/sales/{{ $s->id }}/refund" class="inline" onsubmit="reason = prompt('{{ t('Refund reason') }}:'); if (!reason) return false; this.querySelector('input[name=reason]').value = reason;">
                                    @csrf
                                    <input type="hidden" name="reason" value="">
                                    <button class="text-xs text-red-600 hover:underline">{{ t('Refund') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">{{ t('No sales yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-slate-100">{{ $sales->links() }}</div>
</div>
@endsection
