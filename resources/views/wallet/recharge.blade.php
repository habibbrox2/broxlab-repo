@extends('layouts.app')

@section('title', __('Recharge Wallet') . ' — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800">{{ session('error') }}</div>
    @endif

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
            <i class="lucide lucide-wallet w-7 h-7 text-indigo-600 dark:text-indigo-400"></i>
            {{ __('Recharge your wallet') }}
        </h1>
        <p class="mt-2 text-slate-600 dark:text-slate-400">{{ __('Top up your wallet balance. Requests are reviewed by an administrator before the amount is credited.') }}</p>
    </div>

    <form method="POST" action="{{ route('wallet.recharge.submit') }}" class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-lg space-y-5">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Amount') }}</label>
                <input type="number" name="amount" min="1" max="100000" step="0.01" required
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('বাংলাদেশি টাকা (BDT)') }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Payment Method') }}</label>
                <select name="method" required
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none transition">
                    <option value="bkash">bKash</option>
                    <option value="nagad">Nagad</option>
                    <option value="bank">Bank Transfer</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Sender / Payer Phone') }}</label>
                <input type="text" name="payer_phone" maxlength="30"
                       placeholder="01XXXXXXXXX"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Transaction ID / Reference') }}</label>
                <input type="text" name="transaction_id" maxlength="100"
                       placeholder="bKash TrxID অথবা রেজিস্টার্ন নম্বর"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('wallet.dashboard') }}" class="text-sm text-slate-600 dark:text-slate-400 hover:text-indigo-600">{{ __('Cancel') }}</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none transition">
                <i class="lucide lucide-send w-4 h-4"></i>
                {{ __('Submit Recharge Request') }}
            </button>
        </div>
    </form>
</div>
@endsection
