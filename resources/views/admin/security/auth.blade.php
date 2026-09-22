@extends('admin.layout')

@section('title', 'Authentication Settings — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-cyan-900 to-sky-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(6,182,212,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-lock w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Security') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Authentication Settings') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Registration, email verification and two-factor policies') }}</p>
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
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Authentication policy') }}</h3>
        </div>

        <form method="post" action="/admin/security/auth" class="p-5 sm:p-6 space-y-5">
            @csrf

            <label class="flex items-start justify-between gap-4 py-1 cursor-pointer">
                <span>
                    <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ t('User registration') }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ t('Allow visitors to create accounts.') }}</span>
                </span>
                <input type="checkbox" name="allow_user_registration" value="1" @checked(!empty($appSettings['allow_user_registration']))
                       class="mt-0.5 h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30 flex-shrink-0">
            </label>

            <label class="flex items-start justify-between gap-4 py-1 cursor-pointer">
                <span>
                    <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ t('Require email verification') }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ t('New accounts must confirm their email before signing in.') }}</span>
                </span>
                <input type="checkbox" name="require_email_verification" value="1" @checked(!empty($appSettings['require_email_verification']))
                       class="mt-0.5 h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30 flex-shrink-0">
            </label>

            <label class="flex items-start justify-between gap-4 py-1 cursor-pointer">
                <span>
                    <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ t('Enable two-factor authentication') }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ t('Offer TOTP 2FA to all users (per-user opt-in via security settings).') }}</span>
                </span>
                <input type="checkbox" name="enable_2fa" value="1" @checked(!empty($appSettings['enable_2fa']))
                       class="mt-0.5 h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30 flex-shrink-0">
            </label>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ t('Max login attempts') }}</label>
                <input type="number" name="max_login_attempts" min="1" max="100" value="{{ $appSettings['max_login_attempts'] ?? 5 }}"
                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/10">
                <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Failed sign-ins allowed before the account is temporarily locked.') }}</p>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-cyan-600 hover:bg-cyan-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-cyan-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
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
