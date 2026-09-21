@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <div class="rounded-2xl border border-[rgb(var(--border))] bg-[rgb(var(--surface))] shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-[rgb(var(--border))] bg-[rgb(var(--surface-soft))]">
            <h1 class="text-xl font-bold text-[rgb(var(--text))]">দুই-স্তরের যাচাইকরণ (2FA)</h1>
            <p class="text-sm text-[rgb(var(--muted))] mt-0.5">আপনার অ্যাকাউন্টে একটি অতিরিক্ত সুরক্ষা স্তর যোগ করুন</p>
        </div>

        <div class="px-6 py-6">
            @if (session('status'))
                <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($twofaEnabled)
                <div class="flex items-center gap-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 px-4 py-3">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <div>
                        <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">2FA চালু আছে</p>
                        <p class="text-xs text-emerald-600/80 dark:text-emerald-300/80">
                            পদ্ধতি: TOTP (Google Authenticator সামঞ্জস্যপূর্ণ)
                            @if ($twofaVerifiedAt)
                                · সর্বশেষ যাচাই: {{ \Illuminate\Support\Carbon::parse($twofaVerifiedAt)->format('M j, Y g:i A') }}
                            @endif
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('user.security.2fa.disable') }}" class="mt-6"
                      onsubmit="return confirm('আপনি কি নিশ্চিতভাবে 2FA বন্ধ করতে চান? এটি আপনার অ্যাকাউন্টের নিরাপত্তা কমিয়ে দেবে।')">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-lg border border-red-300 dark:border-red-800 text-red-600 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-semibold px-4 py-2 transition-colors">
                        2FA বন্ধ করুন
                    </button>
                </form>
            @else
                <div class="flex items-start gap-3 rounded-xl bg-amber-50 dark:bg-amber-900/30 px-4 py-3 mb-6">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-300 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.66-.9l7-12A2 2 0 0021 4H3a2 2 0 00-1.66 3l7 12A2 2 0 005 19z"/></svg>
                    <p class="text-sm text-amber-700 dark:text-amber-300">2FA এখনো চালু নেই। চালু করলে লগইনের সময় পাসওয়ার্ডের পাশাপাশি আপনার ফোনের অ্যাপ থেকে ৬ ডিজিটের কোড দিতে হবে।</p>
                </div>

                <a href="{{ route('user.security.2fa.setup') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5 transition-colors">
                    2FA সেটআপ শুরু করুন
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
