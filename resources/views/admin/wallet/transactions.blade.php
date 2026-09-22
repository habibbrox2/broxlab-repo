@extends('admin.layout')

@section('title', 'Wallet — Ledger — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
@php
    // Signed display: these types move money out of the wallet.
    $isDebit = fn ($type) => in_array($type, ['service_payment', 'admin_debit', 'cancellation_fee'], true);

    $typeLabels = [
        'recharge' => 'Recharge',
        'recharge_requested' => 'Recharge Requested',
        'service_payment' => 'Service Payment',
        'refund' => 'Refund',
        'admin_credit' => 'Admin Credit',
        'admin_debit' => 'Admin Debit',
        'cancellation_fee' => 'Cancellation Fee',
    ];
@endphp

<div class="content-wrapper">
    @if (session('status'))
        <div class="mb-4 p-3 rounded bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <i class="lucide lucide-history w-6 h-6 text-indigo-600 dark:text-indigo-400"></i>
            {{ t('Wallet Ledger') }}
        </h1>
        <a href="{{ route('admin.wallet.recharges') }}"
           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 dark:border-slate-700 px-3.5 py-2 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition">
            <i class="lucide lucide-wallet w-4 h-4"></i>
            {{ t('Recharge Requests') }}
        </a>
    </div>

    {{-- Filters --}}
    <form method="get" action="" class="mb-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label for="ledger-user" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('User ID') }}</label>
                <input id="ledger-user" type="number" name="user_id" value="{{ request('user_id') }}" placeholder="{{ t('Any user') }}"
                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
            </div>
            <div>
                <label for="ledger-type" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Type') }}</label>
                <select id="ledger-type" name="type"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    <option value="">{{ t('All types') }}</option>
                    @foreach ($typeLabels as $value => $label)
                        <option value="{{ $value }}" {{ request('type') === $value ? 'selected' : '' }}>{{ t($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                    <i class="lucide lucide-filter w-4 h-4"></i> {{ t('Filter') }}
                </button>
                <a href="{{ route('admin.wallet.transactions') }}" class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition">
                    {{ t('Reset') }}
                </a>
            </div>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="w-full text-sm">
            <thead class="bg-slate-100 dark:bg-slate-800/60">
                <tr>
                    <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ t('Date') }}</th>
                    <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ t('User') }}</th>
                    <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ t('Type') }}</th>
                    <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ t('Description') }}</th>
                    <th class="text-right py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ t('Amount') }}</th>
                    <th class="text-right py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ t('Balance After') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                @forelse ($rows as $tx)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="py-2.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ optional($tx->created_at)->format('d M Y, h:i A') }}</td>
                        <td class="py-2.5 px-4 text-slate-700 dark:text-slate-300">{{ $tx->user->username ?? '—' }}</td>
                        <td class="py-2.5 px-4">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                {{ $typeLabels[$tx->type] ?? ucfirst(str_replace('_', ' ', (string) $tx->type)) }}
                            </span>
                        </td>
                        <td class="py-2.5 px-4 text-slate-500 dark:text-slate-400 max-w-xs truncate">{{ $tx->description ?? '—' }}</td>
                        <td class="py-2.5 px-4 text-right whitespace-nowrap font-medium {{ $isDebit($tx->type) ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ $isDebit($tx->type) ? '−' : '+' }}{{ number_format((float) $tx->amount, 2) }}
                        </td>
                        <td class="py-2.5 px-4 text-right text-slate-900 dark:text-white whitespace-nowrap">৳{{ number_format((float) $tx->balance_after, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500 dark:text-slate-400">{{ t('No wallet transactions found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $rows->withQueryString()->links() }}
    </div>
</div>
@endsection
