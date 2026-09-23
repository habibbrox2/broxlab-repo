@extends('admin.layout')

@section('title', 'Online Orders')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-bold text-slate-900">{{ t('Online Orders') }}</h1>
    <form method="GET" class="flex gap-2">
        <input name="search" value="{{ $search }}" placeholder="{{ t('Order no / tracking / mobile') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm w-56">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">{{ t('All') }}</option>
            @foreach ($statuses as $s)
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
                    <th class="px-4 py-3 text-left">{{ t('Order') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Customer') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Total') }}</th>
                    <th class="px-4 py-3 text-center">{{ t('Payment') }}</th>
                    <th class="px-4 py-3 text-center">{{ t('Status') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Placed') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $o)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="/admin/ha/orders/{{ $o->id }}" class="font-mono text-xs font-bold text-indigo-600 hover:underline">{{ $o->order_no }}</a>
                            <div class="text-xs text-slate-400">{{ $o->tracking_id }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $o->customer_name }}<div class="text-xs text-slate-500">{{ $o->customer_mobile }}</div></td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold">৳{{ number_format((float) $o->grand_total, 2) }}</td>
                        <td class="px-4 py-3 text-center text-xs">{{ $o->payment_method }}<div class="text-slate-400">{{ $o->payment_status }}</div></td>
                        <td class="px-4 py-3 text-center"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $o->status === 'delivered' ? 'bg-emerald-100 text-emerald-700' : ($o->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700') }}">{{ $o->status }}</span></td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $o->created_at->format('d M, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">{{ t('No online orders yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-slate-100">{{ $orders->links() }}</div>
</div>
@endsection
