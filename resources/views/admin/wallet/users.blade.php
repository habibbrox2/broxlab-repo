@extends('admin.layout')

@section('title', 'Wallet — Users — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="content-wrapper">
    @if (session('status'))
        <div class="mb-4 p-3 rounded bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 rounded bg-rose-50 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <i class="lucide lucide-users w-6 h-6 text-indigo-600 dark:text-indigo-400"></i>
            {{ t('Wallet Balances') }}
        </h1>
        <a href="{{ route('admin.wallet.transactions') }}"
           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 dark:border-slate-700 px-3.5 py-2 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition">
            <i class="lucide lucide-history w-4 h-4"></i>
            {{ t('View Ledger') }}
        </a>
    </div>

    {{-- Search --}}
    <form method="get" action="" class="mb-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[16rem]">
                <label for="wallet-user-search" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Search') }}</label>
                <div class="relative">
                    <i class="lucide lucide-search absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input id="wallet-user-search" type="text" name="q" value="{{ request('q') }}" placeholder="{{ t('Username or email...') }}"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 pl-9 pr-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                </div>
            </div>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                <i class="lucide lucide-filter w-4 h-4"></i> {{ t('Search') }}
            </button>
            <a href="{{ route('admin.wallet.users') }}" class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition">
                {{ t('Reset') }}
            </a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="w-full text-sm">
            <thead class="bg-slate-100 dark:bg-slate-800/60">
                <tr>
                    <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ t('User') }}</th>
                    <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ t('Email') }}</th>
                    <th class="text-right py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ t('Balance') }}</th>
                    <th class="text-left py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300">{{ t('Adjust Balance') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                @forelse ($rows as $u)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="py-2.5 px-4">
                            <div class="font-medium text-slate-900 dark:text-white">{{ $u->username ?? '—' }}</div>
                            <div class="text-xs text-slate-400 dark:text-slate-500">#{{ $u->id }}</div>
                        </td>
                        <td class="py-2.5 px-4 text-slate-500 dark:text-slate-400">{{ $u->email ?? '—' }}</td>
                        <td class="py-2.5 px-4 text-right whitespace-nowrap font-semibold {{ ((float) ($u->balance ?? 0)) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' }}">
                            ৳{{ number_format((float) ($u->balance ?? 0), 2) }}
                        </td>
                        <td class="py-2.5 px-4">
                            <form method="POST" action="{{ route('admin.wallet.users.adjust', $u->id) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="number" step="0.01" name="amount" required placeholder="{{ t('e.g. 100 or -50') }}"
                                       class="w-32 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-2.5 py-1.5 text-sm text-slate-900 dark:text-slate-100 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                                <input type="text" name="note" maxlength="512" placeholder="{{ t('Note (optional)') }}"
                                       class="flex-1 min-w-[10rem] rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-2.5 py-1.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-slate-800 dark:bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700 dark:hover:bg-slate-600 transition">
                                    {{ t('Apply') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500 dark:text-slate-400">{{ t('No users found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $rows->withQueryString()->links() }}
    </div>
</div>
@endsection
