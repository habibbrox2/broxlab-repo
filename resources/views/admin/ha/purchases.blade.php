@extends('admin.layout')

@section('title', 'Hero Alif Purchases')

@section('content')
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-900 text-white shadow-xl">
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Hero Alif</p>
            <h1 class="text-xl font-bold text-white">{{ t('Purchases') }}</h1>
            <p class="text-sm text-white/60 mt-0.5">{{ t('Stock in via supplier purchase receiving.') }}</p>
        </div>
        <a href="/admin/ha/purchases/create" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition-all">
            <i class="lucide lucide-plus w-4 h-4"></i> {{ t('New purchase') }}
        </a>
    </div>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ t('Invoice') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Supplier') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Date') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Total') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Paid') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('Due') }}</th>
                    <th class="px-4 py-3 text-center">{{ t('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($purchases as $p)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-900">{{ $p->invoice_no }}</td>
                        <td class="px-4 py-3">{{ $p->supplier->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $p->purchase_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">৳{{ number_format((float) $p->grand_total, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-emerald-700">৳{{ number_format((float) $p->paid_amount, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums {{ (float) $p->due_amount > 0 ? 'text-red-600 font-semibold' : '' }}">৳{{ number_format((float) $p->due_amount, 2) }}</td>
                        <td class="px-4 py-3 text-center"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $p->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">{{ t('No purchases yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-slate-100">{{ $purchases->links() }}</div>
</div>
@endsection
