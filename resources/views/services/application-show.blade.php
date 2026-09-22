@extends('layouts.app')

@section('title', 'Application #' . ($application->id ?? '') . ' — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="max-w-3xl mx-auto px-4 py-12">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
            <i class="lucide lucide-briefcase w-6 h-6 text-indigo-600 dark:text-indigo-400"></i>
            {{ __('Service Application') }} #{{ $application->id ?? '' }}
        </h1>
        <a href="{{ route('services.applications') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">{{ __('Back to applications') }} →</a>
    </div>

    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-lg space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Service') }}</div>
                <div class="text-slate-900 dark:text-white font-medium">{{ $application->service_name ?? '' }}</div>
            </div>
            <div>
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Fee') }}</div>
                <div class="text-slate-900 dark:text-white">
                    @if (($application->service_price ?? 0) > 0)
                        {{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format((float) $application->service_price, 2) }}
                    @else
                        {{ __('Free') }}
                    @endif
                </div>
            </div>
            <div>
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Status') }}</div>
                <div class="text-slate-900 dark:text-white">{{ $application->status ?? '' }}</div>
            </div>
            <div>
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Submitted') }}</div>
                <div class="text-slate-900 dark:text-white">{{ optional($application->created_at)->format('d M Y, h:i A') }}</div>
            </div>
        </div>

        @if (!empty($application->application_data))
            @php $data = json_decode($application->application_data, true) ?: []; @endphp
            @if (!empty($data))
                <div>
                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('Details you provided') }}</div>
                    <ul class="list-disc list-inside space-y-1 text-slate-700 dark:text-slate-300">
                        @foreach ($data as $k => $v)
                            <li><span class="font-medium">{{ ucfirst($k) }}:</span> {{ is_string($v) ? $v : json_encode($v) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        @if (!empty($payment))
            <div class="pt-2 border-t border-slate-200 dark:border-slate-700 text-sm text-slate-600 dark:text-slate-400">
                {{ __('Payment:') }} {{ $payment->payment_method ?? '' }} — {{ $payment->status ?? '' }} —
                {{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format((float) ($payment->amount ?? 0), 2) }}
            </div>
        @else
            <div class="pt-2 border-t border-slate-200 dark:border-slate-700 text-sm text-slate-600 dark:text-slate-400">
                {{ __('No payment (free service).') }}
            </div>
        @endif
    </div>

    @if (in_array($application->status ?? '', ['pending','processing']))
        <form method="POST" action="{{ route('services.application.cancel', $application->id) }}" class="mt-6"
              onsubmit="return confirm('{{ __("Are you sure you want to cancel this application? Any charged service fee will be refunded to your wallet.") }}');">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-600 text-white font-semibold hover:bg-rose-500 focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:outline-none transition">
                <i class="lucide lucide-trash-2 w-4 h-4"></i>
                {{ __('Cancel Application') }}
            </button>
        </form>
    @endif

    @if (session('error'))
        <div class="mt-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800 text-sm">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <div class="mt-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800 text-sm">{{ session('status') }}</div>
    @endif
</div>
@endsection
