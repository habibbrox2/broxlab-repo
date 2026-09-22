@extends('admin.layout')

@section('title', 'Wallet — Recharges — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="content-wrapper">
    @if (session('status'))
        <div class="mb-4 p-3 rounded bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200">{{ session('status') }}</div>
    @endif

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <i class="lucide lucide-wallet w-6 h-6 text-indigo-600 dark:text-indigo-400"></i>
            Wallet Recharges
        </h1>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="w-full text-sm">
            <thead class="bg-slate-100 dark:bg-slate-800/60">
                <tr>
                    <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Date') }}</th>
                    <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('User') }}</th>
                    <th class="text-right py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Amount') }}</th>
                    <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Method') }}</th>
                    <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Status') }}</th>
                    <th class="text-right py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                @forelse ($rows as $r)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="py-2.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ optional($r->created_at)->format('d M Y, h:i A') }}</td>
                        <td class="py-2.5 px-4 text-slate-700 dark:text-slate-300">{{ $r->user->username ?? '—' }}</td>
                        <td class="py-2.5 px-4 text-right text-slate-900 dark:text-white">৳{{ number_format((float) $r->amount, 2) }}</td>
                        <td class="py-2.5 px-4 text-slate-700 dark:text-slate-300">{{ ucfirst($r->method ?? '') }}</td>
                        <td class="py-2.5 px-4">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full
                                @if($r->status === 'completed') bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200
                                @elseif($r->status === 'pending') bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200
                                @elseif($r->status === 'failed') bg-rose-100 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200
                                @else bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 @endif">{{ $r->status_label ?? ucfirst($r->status) }}</span>
                        </td>
                        <td class="py-2.5 px-4 text-right">
                            @if ($r->status === 'pending')
                                <form method="POST" action="{{ route('admin.wallet.recharge.approve', $r->id) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="note" value="">
                                    <button type="submit" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 font-medium">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.wallet.recharge.reject', $r->id) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="note" value="Rejected by admin">
                                    <button type="submit" class="text-rose-600 dark:text-rose-400 hover:text-rose-700 font-medium">{{ __('Reject') }}</button>
                                </form>
                            @else
                                <span class="text-slate-400 dark:text-slate-500">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500 dark:text-slate-400">{{ __('No recharge requests.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $rows->withQueryString()->links() }}
    </div>
</div>
@endsection
