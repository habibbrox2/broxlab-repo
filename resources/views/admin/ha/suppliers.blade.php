@extends('admin.layout')

@section('title', 'Hero Alif Suppliers')

@section('content')
<div class="mb-6">
    <h1 class="text-xl font-bold text-slate-900">{{ t('Suppliers') }} <span class="text-sm font-normal text-slate-500">— {{ t('Hero Alif procurement') }}</span></h1>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <form class="flex gap-2 p-4 border-b border-slate-100" method="GET">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ t('Search supplier') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm w-64">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ t('Search') }}</button>
        </form>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ t('Supplier') }}</th>
                        <th class="px-4 py-3 text-left">{{ t('Mobile') }}</th>
                        <th class="px-4 py-3 text-right">{{ t('Opening due') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('Active') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($suppliers as $s)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ $s->name }}</div>
                                @if ($s->shop_name)<div class="text-xs text-slate-500">{{ $s->shop_name }}</div>@endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $s->mobile ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">৳{{ number_format((float) $s->opening_due, 2) }}</td>
                            <td class="px-4 py-3 text-center">{{ $s->is_active ? '✓' : '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="/admin/ha/suppliers/delete/{{ $s->id }}" class="inline" onsubmit="return confirm('{{ t('Delete supplier?') }}')">
                                    @csrf
                                    <button class="text-red-600 hover:underline text-xs">{{ t('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">{{ t('No suppliers yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">{{ $suppliers->links() }}</div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm h-fit space-y-3">
        <h2 class="font-semibold">{{ t('Add supplier') }}</h2>
        <form method="POST" action="/admin/ha/suppliers" class="space-y-3">
            @csrf
            <input name="name" required placeholder="{{ t('Name') }} *" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="shop_name" placeholder="{{ t('Shop name') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="mobile" placeholder="{{ t('Mobile') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="email" type="email" placeholder="{{ t('Email') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <textarea name="address" rows="2" placeholder="{{ t('Address') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <input name="opening_due" type="number" step="0.01" min="0" value="0" placeholder="{{ t('Opening due') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="w-full rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ t('Add') }}</button>
        </form>
    </div>
</div>
@endsection
