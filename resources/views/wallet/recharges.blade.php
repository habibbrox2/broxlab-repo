@extends('layouts.app')

@section('title', __('Recharge History') . ' — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="max-w-5xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
            <i class="lucide lucide-history w-7 h-7 text-indigo-600 dark:text-indigo-400"></i>
            {{ __('Recharge History') }}
        </h1>
        <a href="{{ route('wallet.recharge') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none transition">
            <i class="lucide lucide-plus w-4 h-4"></i>
            {{ __('New Recharge') }}
        </a>
    </div>

    @if ($rows->isNotEmpty())
        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 dark:bg-slate-800/60">
                    <tr>
                        <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Date') }}</th>
                        <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Amount') }}</th>
                        <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Method') }}</th>
                        <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                    @foreach ($rows as $r)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="py-2.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ optional($r->created_at)->format('d M Y, h:i A') }}</td>
                            <td class="py-2.5 px-4 text-slate-700 dark:text-slate-300">{{ $appSettings['currency_symbol'] ?? '৳' }}{{ number_format((float) $r->amount, 2) }}</td>
                            <td class="py-2.5 px-4 text-slate-700 dark:text-slate-300">{{ ucfirst($r->method ?? '') }}</td>
                            <td class="py-2.5 px-4">
                                @php
                                    $badge = [
                                        'pending' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200',
                                        'processing' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200',
                                        'completed' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200',
                                        'failed' => 'bg-rose-100 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200',
                                    ][$r->status] ?? 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200';
                                @endphp
                                <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $badge }}">{{ $r->status_label ?? ucfirst($r->status) }}</span>
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
            <p>{{ __('You have no recharge requests yet.') }}</p>
        </div>
    @endif
</div>
@endsection
