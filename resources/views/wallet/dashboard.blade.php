@extends('layouts.app')

@section('title', __('Wallet') . ' — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="max-w-5xl mx-auto px-4 py-10">
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
            <i class="lucide lucide-wallet w-7 h-7 text-indigo-600 dark:text-indigo-400"></i>
            {{ __('Wallet') }}
        </h1>
        <a href="{{ route('wallet.recharge') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold shadow-lg shadow-indigo-500/30 hover:from-indigo-500 hover:to-purple-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none transition-all duration-200">
            <i class="lucide lucide-plus w-4 h-4"></i>
            {{ __('Recharge') }}
        </a>
    </div>

    {{-- Balance card --}}
    <div class="p-8 rounded-2xl bg-gradient-to-br from-indigo-50 via-purple-50 to-indigo-50 dark:from-slate-800 dark:via-slate-800 dark:to-indigo-900/20 border border-indigo-100 dark:border-slate-700 mb-8">
        <div class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Current Balance') }}</div>
        <div class="text-5xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-700 dark:text-white my-2">
            {{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format($balance, 2) }}
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('BDT — Bangladeshi Taka') }}</div>
    </div>

    {{-- Pending recharges --}}
    @if ($pending && $pending->isNotEmpty())
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200 mb-3">{{ __('Pending Recharge Requests') }}</h2>
            <div class="space-y-2">
                @foreach ($pending as $r)
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <div>
                            <div class="font-medium text-slate-900 dark:text-white">{{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format($r->amount, 2) }} — {{ $r->method }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('Submitted') }}: {{ optional($r->created_at)->format('d M Y, h:i A') }}</div>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200">{{ $r->status_label ?? __('Pending') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recent transactions --}}
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200">{{ __('Recent Transactions') }}</h2>
            <a href="{{ route('wallet.transactions') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">{{ __('See all') }} →</a>
        </div>

        @if ($recent && $recent->isNotEmpty())
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-800/60">
                        <tr>
                            <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Date') }}</th>
                            <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Type') }}</th>
                            <th class="text-right py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Amount') }}</th>
                            <th class="text-right py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Balance After') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        @php
                            $typeLabels = [
                                'recharge' => __('Recharge'),
                                'service_payment' => __('Service Payment'),
                                'refund' => __('Refund'),
                                'admin_credit' => __('Admin Credit'),
                                'admin_debit' => __('Admin Debit'),
                                'recharge_requested' => __('Recharge Requested'),
                            ];
                        @endphp
                        @foreach ($recent as $tx)
                            @php
                                $isOut = in_array($tx->type, ['service_payment','admin_debit'], true);
                                $sign = $isOut ? '-' : '+';
                                $typeLabel = $typeLabels[$tx->type] ?? ucfirst(str_replace('_', ' ', (string) $tx->type));
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="py-2.5 px-4 text-slate-500 dark:text-slate-400">{{ optional($tx->created_at)->format('d M Y, h:i A') }}</td>
                                <td class="py-2.5 px-4 text-slate-700 dark:text-slate-300">{{ $typeLabel }}</td>
                                <td class="py-2.5 px-4 text-right font-medium {{ $isOut ? 'text-rose-600' : 'text-emerald-600' }}">{{ $sign }}{{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format((float) $tx->amount, 2) }}</td>
                                <td class="py-2.5 px-4 text-right text-slate-600 dark:text-slate-300">{{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format((float) $tx->balance_after, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12 text-slate-500 dark:text-slate-400">{{ __('No transactions yet.') }}</div>
        @endif
    </div>
</div>
@endsection
