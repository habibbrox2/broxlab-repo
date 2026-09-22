@extends('admin.layout')

@section('title', 'SMTP Settings — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

@php
    $configured = ($appSettings['smtp_host'] ?? '') !== '' && ($appSettings['smtp_username'] ?? '') !== '';
@endphp

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-violet-900 to-slate-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(139,92,246,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-mail w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Security') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('SMTP Settings') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Outgoing mail server used for verification, notifications and alerts') }}</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold
                         {{ $configured ? 'bg-emerald-500/20 text-emerald-200' : 'bg-amber-500/20 text-amber-200' }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $configured ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                {{ $configured ? t('Configured') : t('Not configured — falling back to default mailer') }}
            </span>
            <a href="/admin/security" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Overview') }}
            </a>
        </div>
    </div>
</div>

<div class="max-w-2xl">
    @include('admin.security._flash')

    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Mail server') }}</h3>
        </div>

        <form method="post" action="/admin/security/smtp" class="p-5 sm:p-6 space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2 space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('SMTP host') }}</label>
                    <input type="text" name="smtp_host" maxlength="190" value="{{ $appSettings['smtp_host'] ?? '' }}" placeholder="smtp.example.com"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-500/10">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Port') }}</label>
                    <input type="number" name="smtp_port" min="1" max="65535" value="{{ $appSettings['smtp_port'] ?? '' }}" placeholder="587"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-500/10">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Username') }}</label>
                    <input type="text" name="smtp_username" maxlength="190" value="{{ $appSettings['smtp_username'] ?? '' }}" autocomplete="off"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-500/10">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Password') }}</label>
                    <input type="password" name="smtp_password" maxlength="190" value="" autocomplete="new-password"
                           placeholder="{{ $smtp_password_masked !== '' ? t('Stored:').' '.$smtp_password_masked.' — '.t('leave blank to keep') : t('not set') }}"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-500/10">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Encryption') }}</label>
                    <select name="smtp_encryption"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-500/10">
                        @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None', '' => '—'] as $value => $label)
                            <option value="{{ $value }}" @selected(($appSettings['smtp_encryption'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('From address') }}</label>
                    <input type="email" name="mail_from_address" maxlength="190" value="{{ $appSettings['mail_from_address'] ?? '' }}" placeholder="noreply@example.com"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-500/10">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('From name') }}</label>
                <input type="text" name="mail_from_name" maxlength="190" value="{{ $appSettings['mail_from_name'] ?? '' }}" placeholder="{{ $appSettings['site_name'] ?? 'BroxLab' }}"
                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-500/10">
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-violet-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                    <i class="lucide lucide-save w-4 h-4"></i> {{ t('Save changes') }}
                </button>
                <a href="/admin/security" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-150">
                    {{ t('Back') }}
                </a>
            </div>
        </form>
    </div>

    {{-- Test email --}}
    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-send w-4 h-4 text-sky-600 dark:text-sky-400"></i>
            </div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Send a test email') }}</h3>
        </div>
        <form method="post" action="/admin/security/smtp/test" class="p-5 sm:p-6 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <div class="flex-1 space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Deliver to') }}</label>
                <input type="email" name="test_email" required maxlength="190" value="{{ auth()->user()->email ?? '' }}" placeholder="you@example.com"
                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/10">
                <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Uses the exact same mail path as production (MailService), so success means the settings above work.') }}</p>
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-sky-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-send w-4 h-4"></i> {{ t('Send test') }}
            </button>
        </form>
    </div>
</div>

@endsection
