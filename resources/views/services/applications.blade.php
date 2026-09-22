@extends('layouts.app')

@section('title', __('My Applications') . ' — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="max-w-5xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
            <i class="lucide lucide-briefcase w-7 h-7 text-indigo-600 dark:text-indigo-400"></i>
            {{ __('My Service Applications') }}
        </h1>
        <a href="{{ route('wallet.dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">{{ __('Wallet') }} ({{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format($authUser->balance ?? 0, 2) }})</a>
    </div>

    @php
        $statusBadge = [
            'pending' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200',
            'processing' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200',
            'approved' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200',
            'rejected' => 'bg-rose-100 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200',
            'cancelled' => 'bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200',
        ];
        $statusLabel = [
            'pending' => __('Pending'),
            'processing' => __('Processing'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            'cancelled' => __('Cancelled'),
        ];
    @endphp

    @if ($rows->isNotEmpty())
        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 dark:bg-slate-800/60">
                    <tr>
                        <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Service') }}</th>
                        <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Price') }}</th>
                        <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Status') }}</th>
                        <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Submitted') }}</th>
                        <th class="text-right py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                    @foreach ($rows as $app)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="py-2.5 px-4 text-slate-900 dark:text-white font-medium">{{ $app->service_name }}</td>
                            <td class="py-2.5 px-4 text-slate-600 dark:text-slate-400">{{ ($app->service_price ?? 0) > 0 ? ($appSettings['currency_symbol'] ?? '৳').number_format((float) $app->service_price, 2) : __('Free') }}</td>
                            <td class="py-2.5 px-4">
                                <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $statusBadge[$app->status] ?? '' }}">{{ $statusLabel[$app->status] ?? $app->status }}</span>
                            </td>
                            <td class="py-2.5 px-4 text-slate-500 dark:text-slate-400">{{ optional($app->created_at)->format('d M Y, h:i A') }}</td>
                            <td class="py-2.5 px-4 text-right">
                                <a href="{{ route('services.application.show', $app->id) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 font-medium">{{ __('View') }}</a>
                                @if (in_array($app->status, ['pending','processing']))
                                    <form method="POST" action="{{ route('services.application.cancel', $app->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" onclick="return confirm('{{ __("Are you sure you want to cancel this application? Any charged service fee will be refunded to your wallet.") }}')"
                                                class="ml-2 text-rose-600 dark:text-rose-400 hover:text-rose-700 font-medium">{{ __('Cancel') }}</button>
                                    </form>
                                @endif
                            </td>
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
            <p class="mb-4">{{ __('You have not applied for any service yet.') }}</p>
            <a href="{{ route('services.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-500">{{ __('Browse Services') }}</a>
        </div>
    @endif
</div>
@endsection
