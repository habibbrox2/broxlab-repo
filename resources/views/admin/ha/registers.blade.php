@extends('admin.layout')

@section('title', 'Cash Register')

@section('content')
<div class="mb-6">
    <h1 class="text-xl font-bold text-slate-900">{{ t('Cash Register') }}</h1>
    <p class="text-sm text-slate-500">{{ t('Opening → expected → actual → difference. Every session is logged.') }}</p>
</div>

@if ($current)
    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">{{ t('Open session') }} · #{{ $current->id }}</p>
                    <p class="text-sm text-emerald-800">{{ t('Opened') }} {{ $current->opened_at->format('d M Y, H:i') }}</p>
                </div>
                <span class="rounded-full bg-emerald-600 px-3 py-1 text-xs font-bold text-white">{{ t('OPEN') }}</span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                <div class="rounded-xl bg-white p-3"><div class="text-xs text-slate-500">{{ t('Opening') }}</div><div class="font-bold tabular-nums">৳{{ number_format((float) $summary['expected_cash'] - $summary['cash_sales'] - $summary['cash_in'] - $summary['due_collection'] + $summary['change_given'] + $summary['cash_out'] + $summary['refunds_cash'], 2) }}</div></div>
                <div class="rounded-xl bg-white p-3"><div class="text-xs text-slate-500">{{ t('Cash sales') }}</div><div class="font-bold tabular-nums text-emerald-700">৳{{ number_format($summary['cash_sales'], 2) }}</div></div>
                <div class="rounded-xl bg-white p-3"><div class="text-xs text-slate-500">{{ t('Due collection') }}</div><div class="font-bold tabular-nums">৳{{ number_format($summary['due_collection'], 2) }}</div></div>
                <div class="rounded-xl bg-white p-3"><div class="text-xs text-slate-500">{{ t('Cash out') }}</div><div class="font-bold tabular-nums text-red-600">৳{{ number_format($summary['cash_out'], 2) }}</div></div>
                <div class="rounded-xl bg-white p-3"><div class="text-xs text-slate-500">{{ t('Refunds (cash)') }}</div><div class="font-bold tabular-nums text-red-600">৳{{ number_format($summary['refunds_cash'], 2) }}</div></div>
                <div class="rounded-xl bg-white p-3 border-2 border-emerald-300"><div class="text-xs text-slate-500">{{ t('Expected cash') }}</div><div class="font-bold tabular-nums text-lg">৳{{ number_format($summary['expected_cash'], 2) }}</div></div>
            </div>
        </div>
        <div class="space-y-4">
            <form method="POST" action="/admin/ha/register/close" class="rounded-2xl border border-slate-200 bg-white p-6 space-y-3">
                @csrf
                <h2 class="font-semibold">{{ t('Close register') }}</h2>
                <label class="block text-sm">{{ t('Actual cash counted') }} ৳
                    <input type="number" name="actual_cash" step="0.01" min="0" required value="{{ $summary['expected_cash'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums">
                </label>
                <label class="block text-sm">{{ t('Note') }}
                    <input name="note" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <button class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ t('Close & count difference') }}</button>
            </form>
            <form method="POST" action="/admin/ha/register/cash-movement" class="rounded-2xl border border-slate-200 bg-white p-6 space-y-3">
                @csrf
                <h2 class="font-semibold">{{ t('Cash in / out') }}</h2>
                <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="cash_in">{{ t('Cash in') }}</option>
                    <option value="cash_out">{{ t('Cash out (expense/draw)') }}</option>
                </select>
                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="{{ t('Amount') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="note" required placeholder="{{ t('Reason') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">{{ t('Record') }}</button>
            </form>
        </div>
    </div>
@else
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 max-w-md">
        <h2 class="mb-3 font-semibold">{{ t('Open register') }}</h2>
        <form method="POST" action="/admin/ha/register/open" class="space-y-3">
            @csrf
            <label class="block text-sm">{{ t('Opening cash balance') }} ৳
                <input type="number" name="opening_balance" step="0.01" min="0" required value="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums">
            </label>
            <label class="block text-sm">{{ t('Note') }}
                <input name="note" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <button class="w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">{{ t('Open session') }}</button>
        </form>
    </div>
@endif

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">{{ t('Opened') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Closed') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Opening') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Expected') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Actual') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Difference') }}</th>
                    <th class="px-4 py-3 text-center">{{ t('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($registers as $reg)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-semibold">{{ $reg->id }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $reg->opened_at->format('d M H:i') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $reg->closed_at?->format('d M H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">৳{{ number_format((float) $reg->opening_balance, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $reg->expected_cash !== null ? '৳'.number_format((float) $reg->expected_cash, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $reg->actual_cash !== null ? '৳'.number_format((float) $reg->actual_cash, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold @if((float) $reg->difference != 0) text-red-600 @endif">{{ $reg->difference !== null ? '৳'.number_format((float) $reg->difference, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-center"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $reg->status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100' }}">{{ $reg->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">{{ t('No register sessions yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-slate-100">{{ $registers->links() }}</div>
</div>
@endsection
