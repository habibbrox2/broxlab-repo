@extends('admin.layout')

@section('title', 'reCAPTCHA Settings — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-rose-900 to-slate-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(244,63,94,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-bot w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Security') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('reCAPTCHA Settings') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Google reCAPTCHA keys for public forms') }}</p>
            </div>
        </div>
        <a href="/admin/security" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Overview') }}
        </a>
    </div>
</div>

<div class="max-w-2xl">
    @include('admin.security._flash')

    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Google reCAPTCHA') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ t('Keys are stored on the server; the secret is never echoed back in full.') }}</p>
        </div>

        <form method="post" action="/admin/security/recaptcha" class="p-5 sm:p-6 space-y-5">
            @csrf

            <label class="flex items-start justify-between gap-4 py-1 cursor-pointer">
                <span>
                    <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ t('Enable reCAPTCHA') }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ t('Verify submissions on public forms (contact, registration).') }}</span>
                </span>
                <input type="checkbox" name="recaptcha_enabled" value="1" @checked(!empty($appSettings['recaptcha_enabled']))
                       class="mt-0.5 h-5 w-5 rounded border-slate-300 text-rose-600 focus:ring-rose-500/30 flex-shrink-0">
            </label>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Site key') }}</label>
                <input type="text" name="recaptcha_site_key" maxlength="190" value="{{ $appSettings['recaptcha_site_key'] ?? '' }}"
                       placeholder="6L..."
                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 font-mono focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-500/10">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Secret key') }}</label>
                <input type="text" name="recaptcha_secret_key" maxlength="190" value=""
                       placeholder="{{ $recaptcha_secret_masked !== '' ? t('Stored:').' '.$recaptcha_secret_masked.' — '.t('leave blank to keep') : t('not set') }}"
                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 font-mono placeholder:text-slate-400 focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-500/10">
                <p class="text-xs text-slate-400 dark:text-slate-600">
                    {{ $recaptcha_secret_masked !== '' ? t('A secret key is stored. Submit a new one to replace it; leave blank to keep the current value.') : t('No secret key stored yet.') }}
                </p>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Score threshold') }} (0–1)</label>
                <input type="number" step="0.1" min="0" max="1" name="recaptcha_threshold" value="{{ $appSettings['recaptcha_threshold'] ?? '' }}"
                       placeholder="0.5"
                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-500/10">
                <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('v3 only: submissions scoring below this are treated as suspicious. Leave blank if using v2 checkbox.') }}</p>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-rose-600 hover:bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-rose-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                    <i class="lucide lucide-save w-4 h-4"></i> {{ t('Save changes') }}
                </button>
                <a href="/admin/security" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-150">
                    {{ t('Back') }}
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
