@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <div class="rounded-2xl border border-[rgb(var(--border))] bg-[rgb(var(--surface))] shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-[rgb(var(--border))] bg-[rgb(var(--surface-soft))]">
            <h1 class="text-xl font-bold text-[rgb(var(--text))]">2FA সেটআপ করুন</h1>
            <p class="text-sm text-[rgb(var(--muted))] mt-0.5">Google Authenticator বা যেকোনো TOTP অ্যাপ দিয়ে QR কোডটি স্ক্যান করুন</p>
        </div>

        <div class="px-6 py-6">
            @if (session('error'))
                <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 px-4 py-3 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <ol class="text-sm text-[rgb(var(--muted))] space-y-1 mb-6 list-decimal list-inside">
                <li>ফোনে Google Authenticator / Authy / Microsoft Authenticator ইনস্টল করুন</li>
                <li>অ্যাপের "স্ক্যান কোড" দিয়ে নিচের QR স্ক্যান করুন (অথবা কী হাতে লিখুন)</li>
                <li>অ্যাপে দেখা ৬ ডিজিটের কোড নিচের ফর্মে দিয়ে নিশ্চিত করুন</li>
            </ol>

            <div class="flex flex-col sm:flex-row items-center gap-6 mb-6">
                @if ($qrDataUri)
                    <div class="shrink-0 rounded-xl bg-white p-3 border border-[rgb(var(--border))]">
                        <img src="{{ $qrDataUri }}" alt="2FA QR কোড" class="w-44 h-44">
                    </div>
                @else
                    <div class="shrink-0 rounded-xl bg-white p-3 border border-[rgb(var(--border))] w-44 h-44 flex items-center justify-center text-xs text-slate-400 text-center px-2">
                        QR তৈরি করা যায়নি — নিচের কী টাইপ করুন
                    </div>
                @endif

                <div class="text-sm">
                    <p class="font-semibold text-[rgb(var(--text))] mb-1">ম্যানুয়াল এন্ট্রি কী:</p>
                    <code class="block rounded-lg bg-[rgb(var(--surface-soft))] border border-[rgb(var(--border))] px-3 py-2 font-mono text-base tracking-widest text-[rgb(var(--text))] select-all">{{ $secret }}</code>
                    <p class="text-xs text-[rgb(var(--muted))] mt-2 break-all">URI: {{ $otpauthUri }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('user.security.2fa.verify') }}" class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end">
                @csrf
                <div class="flex-1">
                    <label for="code" class="block text-sm font-semibold text-[rgb(var(--text))] mb-1">৬ ডিজিটের কোড</label>
                    <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required
                           class="w-full rounded-lg border border-[rgb(var(--border))] bg-[rgb(var(--surface))] px-3 py-2.5 font-mono text-lg tracking-[0.4em] text-center text-[rgb(var(--text))] focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                           placeholder="──────">
                </div>
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-6 py-2.5 transition-colors">
                    যাচাই ও চালু করুন
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
