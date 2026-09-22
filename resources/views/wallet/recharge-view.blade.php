@extends('layouts.app')

@section('title', __('Recharge') . ' — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <i class="lucide lucide-receipt w-6 h-6 text-indigo-600 dark:text-indigo-400"></i>
            {{ __('Recharge Request #{{0}}', ['0' => $recharge->id]) }}
        </h1>
        <a href="{{ route('wallet.recharges') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">{{ __('Back') }} →</a>
    </div>

    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-lg space-y-4">
        <div class="flex items-center justify-between">
            <div class="text-slate-600 dark:text-slate-400">{{ __('Amount') }}</div>
            <div class="text-xl font-bold text-slate-900 dark:text-white">{{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format((float) $recharge->amount, 2) }}</div>
        </div>
        <div class="flex items-center justify-between">
            <div class="text-slate-600 dark:text-slate-400">{{ __('Method') }}</div>
            <div class="text-slate-900 dark:text-white">{{ ucfirst($recharge->method ?? '') }}</div>
        </div>
        <div class="flex items-center justify-between">
            <div class="text-slate-600 dark:text-slate-400">{{ __('Status') }}</div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full
                @if($recharge->status === 'completed') bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200
                @elseif($recharge->status === 'pending') bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200
                @elseif($recharge->status === 'failed') bg-rose-100 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200
                @else bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200 @endif">
                {{ $recharge->status_label ?? ucfirst($recharge->status) }}
            </span>
        </div>
        @if ($recharge->payer_phone)
            <div class="flex items-center justify-between">
                <div class="text-slate-600 dark:text-slate-400">{{ __('Payer Phone') }}</div>
                <div class="text-slate-900 dark:text-white">{{ $recharge->payer_phone }}</div>
            </div>
        @endif
        @if ($recharge->transaction_id)
            <div class="flex items-center justify-between">
                <div class="text-slate-600 dark:text-slate-400">{{ __('Transaction ID / Reference') }}</div>
                <div class="text-slate-900 dark:text-white">{{ $recharge->transaction_id }}</div>
            </div>
        @endif
        @if ($recharge->admin_note)
            <div class="flex items-start justify-between gap-4">
                <div class="text-slate-600 dark:text-slate-400">{{ __('Admin Note') }}</div>
                <div class="text-slate-900 dark:text-white text-right">{{ $recharge->admin_note }}</div>
            </div>
        @endif
        <div class="flex items-center justify-between">
            <div class="text-slate-600 dark:text-slate-400">{{ __('Submitted') }}</div>
            <div class="text-slate-500 dark:text-slate-400">{{ optional($recharge->created_at)->format('d M Y, h:i A') }}</div>
        </div>
        @if ($recharge->processed_at)
            <div class="flex items-center justify-between">
                <div class="text-slate-600 dark:text-slate-400">{{ __('Processed') }}</div>
                <div class="text-slate-500 dark:text-slate-400">{{ optional($recharge->processed_at)->format('d M Y, h:i A') }}</div>
            </div>
        @endif
    </div>
</div>
@endsection
