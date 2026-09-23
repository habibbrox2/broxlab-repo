@extends('admin.layout')

@section('title', 'Customers')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-bold text-slate-900">{{ t('Customers') }}</h1>
    <form method="GET" class="flex gap-2">
        <input name="search" value="{{ $search }}" placeholder="{{ t('Name / mobile') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm w-48">
        <label class="inline-flex items-center gap-1.5 text-sm"><input type="checkbox" name="due" value="1" @checked($dueOnly)> {{ t('Has due') }}</label>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ t('Filter') }}</button>
    </form>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ t('Customer') }}</th>
                        <th class="px-4 py-3 text-left">{{ t('Type') }}</th>
                        <th class="px-4 py-3 text-right">{{ t('Due') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $c)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="/admin/ha/customers/{{ $c->id }}" class="font-semibold text-slate-900 hover:text-indigo-600">{{ $c->name }}</a>
                                <div class="text-xs text-slate-500">{{ $c->mobile }}</div>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $c->customer_type }}</span></td>
                            <td class="px-4 py-3 text-right tabular-nums {{ (float) $c->due_balance > 0 ? 'text-red-600 font-semibold' : 'text-slate-400' }}">৳{{ number_format((float) $c->due_balance, 2) }}</td>
                            <td class="px-4 py-3 text-right"><a class="text-indigo-600 hover:underline text-xs" href="/admin/ha/customers/{{ $c->id }}">{{ t('Ledger') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">{{ t('No customers yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">{{ $customers->links() }}</div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm h-fit space-y-3">
        <h2 class="font-semibold">{{ t('Add customer') }}</h2>
        <form method="POST" action="/admin/ha/customers" class="space-y-3">
            @csrf
            <input name="name" required placeholder="{{ t('Name') }} *" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="mobile" required placeholder="{{ t('Mobile') }} *" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="email" type="email" placeholder="{{ t('Email') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <select name="customer_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="retail">{{ t('Retail') }}</option>
                <option value="wholesale">{{ t('Wholesale') }}</option>
            </select>
            <textarea name="address" rows="2" placeholder="{{ t('Address') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <input name="opening_due" type="number" step="0.01" min="0" value="0" placeholder="{{ t('Opening due') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="w-full rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ t('Add') }}</button>
        </form>
    </div>
</div>
@endsection
