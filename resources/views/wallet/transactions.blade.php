@extends('layouts.app')

@section('title', __('My Transactions') . ' — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="max-w-5xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
            <i class="lucide lucide-history w-7 h-7 text-indigo-600 dark:text-indigo-400"></i>
            {{ __('My Transactions') }}
        </h1>
        <a href="{{ route('wallet.dashboard') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">{{ __('Back to Wallet') }} →</a>
    </div>

    @if ($rows->isNotEmpty())
        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 dark:bg-slate-800/60">
                    <tr>
                        <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Date') }}</th>
                        <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Type') }}</th>
                        <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Description') }}</th>
                        <th class="text-right py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Amount') }}</th>
                        <th class="text-right py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Balance After') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                    @php
                        $typeLabel = [
                            'recharge' => __('Recharge'),
                            'service_payment' => __('Service Payment'),
                            'refund' => __('Refund'),
                            'admin_credit' => __('Admin Credit'),
                            'admin_debit' => __('Admin Debit'),
                            'recharge_requested' => __('Recharge Requested'),
                        ];
                    @endphp
                    @foreach ($rows as $tx)
                        @php
                            $isOut = in_array($tx->type, ['service_payment','admin_debit'], true);
                            $sign = $isOut ? '-' : '+';
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="py-2.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ optional($tx->created_at)->format('d M Y, h:i A') }}</td>
                            <td class="py-2.5 px-4 text-slate-700 dark:text-slate-300">{{ $typeLabel[$tx->type] ?? $tx->type }}</td>
                            <td class="py-2.5 px-4 text-slate-600 dark:text-slate-400">{{ $tx->description ?? '—' }}</td>
                            <td class="py-2.5 px-4 text-right font-medium {{ $isOut ? 'text-rose-600' : 'text-emerald-600' }}">{{ $sign }}{{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format((float) $tx->amount, 2) }}</td>
                            <td class="py-2.5 px-4 text-right text-slate-600 dark:text-slate-300">{{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format((float) $tx->balance_after, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $rows->withQueryString()->links() }}
        </div>
    @else
        <div class="text-center py-16 text-slate-500 dark:text-slate-400">
            <i class="lucide lucide-inbox w-12 h-12 mx-auto mb-3 opacity-40"></i>
            <p>{{ __('No transactions yet.') }}</p>
        </div>
    @endif
</div>
@endsection
