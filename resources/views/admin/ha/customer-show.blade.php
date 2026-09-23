@extends('admin.layout')

@section('title', 'Customer '.$customer->name)

@section('content')
<div class="mb-6 flex items-start justify-between">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ $customer->name }}</h1>
        <p class="text-sm text-slate-500">{{ $customer->mobile }} @if($customer->email) · {{ $customer->email }}@endif · {{ $customer->customer_type }}</p>
    </div>
    <a href="/admin/ha/customers" class="text-sm text-indigo-600 hover:underline">← {{ t('All customers') }}</a>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ t('Current due') }}</div>
        <div class="mt-1 text-2xl font-bold tabular-nums {{ (float) $customer->due_balance > 0 ? 'text-red-600' : 'text-emerald-600' }}">৳{{ number_format((float) $customer->due_balance, 2) }}</div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ t('Total sales') }}</div>
        <div class="mt-1 text-2xl font-bold tabular-nums">{{ $customer->sales()->count() }}</div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ t('Last purchase') }}</div>
        <div class="mt-1 text-sm font-semibold">{{ optional($customer->sales()->latest('id')->first())->created_at?->format('d M Y') ?? '—' }}</div>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <h2 class="px-4 py-3 font-semibold border-b border-slate-100">{{ t('Ledger statement') }}</h2>
            <div class="overflow-x-auto max-h-96">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left">{{ t('Date') }}</th>
                            <th class="px-4 py-2 text-left">{{ t('Type') }}</th>
                            <th class="px-4 py-2 text-left">{{ t('Note') }}</th>
                            <th class="px-4 py-2 text-right">{{ t('Amount') }}</th>
                            <th class="px-4 py-2 text-right">{{ t('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($statement as $e)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $e->created_at->format('d M Y') }}</td>
                                <td class="px-4 py-2"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ str_replace('_', ' ', $e->type) }}</span></td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $e->note }}</td>
                                <td class="px-4 py-2 text-right tabular-nums {{ $e->amount > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $e->amount > 0 ? '+' : '' }}{{ number_format((float) $e->amount, 2) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold">{{ number_format((float) $e->balance_after, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ t('No ledger entries yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <h2 class="px-4 py-3 font-semibold border-b border-slate-100">{{ t('Recent sales') }}</h2>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sales as $s)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs"><a href="/admin/ha/pos/receipt/{{ $s->id }}" class="text-indigo-600 hover:underline">{{ $s->invoice_no }}</a></td>
                            <td class="px-4 py-2 text-slate-600">{{ $s->created_at->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">৳{{ number_format((float) $s->grand_total, 2) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums {{ (float) $s->due_amount > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ (float) $s->due_amount > 0 ? 'due ৳'.number_format((float) $s->due_amount, 2) : 'paid' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">{{ t('No sales yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-3">
            <h2 class="font-semibold">{{ t('Collect due payment') }}</h2>
            <form method="POST" action="/admin/ha/customers/{{ $customer->id }}/collect" class="space-y-3">
                @csrf
                <input type="number" name="amount" step="0.01" min="0.01" max="{{ $customer->due_balance }}" required placeholder="{{ t('Amount') }} *" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums">
                <select name="method" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach (\App\Models\HaCustomerPayment::METHODS as $m)
                        <option value="{{ $m }}">{{ ucfirst($m) }}</option>
                    @endforeach
                </select>
                <input name="reference" placeholder="{{ t('TrxID / reference') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="note" placeholder="{{ t('Note') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ t('Receive payment') }}</button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-3">
            <h2 class="font-semibold">{{ t('Edit customer') }}</h2>
            <form method="POST" action="/admin/ha/customers/{{ $customer->id }}" class="space-y-3">
                @csrf
                <input name="name" required value="{{ $customer->name }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="mobile" required value="{{ $customer->mobile }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="email" type="email" value="{{ $customer->email }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="customer_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="retail" @selected($customer->customer_type === 'retail')>{{ t('Retail') }}</option>
                    <option value="wholesale" @selected($customer->customer_type === 'wholesale')>{{ t('Wholesale') }}</option>
                </select>
                <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $customer->notes }}</textarea>
                <button class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ t('Save') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
