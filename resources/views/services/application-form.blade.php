@extends('layouts.app')

@section('title', __('Apply for') . ' ' . ($service->name ?? '') . ' — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ __('Apply for') }}: {{ $service->name ?? '' }}</h1>
        @if (!empty($service->description))
            <p class="mt-2 text-slate-600 dark:text-slate-400">{!! \Illuminate\Support\Str::limit(strip_tags($service->description), 240) !!}</p>
        @endif
    </div>

    {{-- Balance + price summary --}}
    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 mb-6 flex items-center justify-between">
        <div>
            <div class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Your Wallet Balance') }}</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format($balance, 2) }}</div>
        </div>
        <div class="text-right">
            <div class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Service Fee') }}</div>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                @if (($service->price ?? 0) > 0)
                    {{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format((float) $service->price, 2) }}
                @else
                    {{ __('Free') }}
                @endif
            </div>
        </div>
    </div>

    @php
        $formFields = $service->form_fields ? json_decode($service->form_fields, true) : [];
        $formFields = is_array($formFields) ? $formFields : [];
    @endphp

    <form method="POST" action="{{ route('services.apply.submit', ['slug' => $service->slug ?? $service->id]) }}" class="space-y-5">
        @csrf

        @if (!empty($formFields))
            @foreach ($formFields as $field)
                @php $fname = $field['name'] ?? $field['key'] ?? 'field_' . $loop->index; @endphp
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">{{ $field['label'] ?? ucfirst($fname) }}</label>
                    @if (($field['type'] ?? 'text') === 'textarea')
                        <textarea name="data[{{ $fname }}]" rows="3"
                                  class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></textarea>
                    @else
                        <input type="{{ $field['type'] ?? 'text' }}" name="data[{{ $fname }}]"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none transition">
                    @endif
                </div>
            @endforeach
        @else
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Additional Details') }}</label>
                <textarea name="data[notes]" rows="4" placeholder="{{ __('Enter any details relevant to your application…') }}"
                          class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></textarea>
            </div>
        @endif

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('services.index') }}" class="text-sm text-slate-600 dark:text-slate-400 hover:text-indigo-600">{{ __('Cancel') }}</a>
            <button type="submit"
                    @if (($service->price ?? 0) > 0 && $balance < (float) $service->price) disabled
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-slate-400 dark:bg-slate-600 cursor-not-allowed">
                    @else
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none transition">
                    @endif
                <i class="lucide lucide-send w-4 h-4"></i>
                {{ __('Submit Application') }}
            </button>
        </div>
    </form>

    @if (($service->price ?? 0) > 0 && $balance < (float) $service->price)
        <div class="mt-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-sm">
            {{ __('Insufficient balance. Please recharge your wallet before applying.') }}
            <a href="{{ route('wallet.recharge') }}" class="underline font-semibold">{{ __('Recharge now') }}</a>
        </div>
    @endif
</div>
@endsection
