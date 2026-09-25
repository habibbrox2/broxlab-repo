@extends('admin.layout')

@section('title', 'Setup Wizard — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-purple-900 to-fuchsia-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(168,85,247,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-wrench w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Administration</p>
                <h1 class="text-xl font-bold text-white">{{ t('Setup Wizard') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Configure your site settings and preferences') }}</p>
            </div>
        </div>
        <a href="/admin/dashboard" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back to Dashboard') }}
        </a>
    </div>
</div>

<div class="max-w-3xl">
    <div class="space-y-4">
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 group">
            <div class="p-5 flex items-center gap-4 cursor-pointer" onclick="window.location='/admin/security'">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-shield-check w-6 h-6 text-white"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('Security & Authentication') }}</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('Configure 2FA, email verification, login attempts, SMTP, and Recaptcha') }}</p>
                </div>
                <i class="lucide lucide-arrow-right w-5 h-5 text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors"></i>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 group">
            <div class="p-5 flex items-center gap-4 cursor-pointer" onclick="window.location='/admin/revenue'">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-trending-up w-6 h-6 text-white"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('Revenue & Monetization') }}</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('Configure advertising, sponsored packages, and donation gateways') }}</p>
                </div>
                <i class="lucide lucide-arrow-right w-5 h-5 text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors"></i>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 group">
            <div class="p-5 flex items-center gap-4 cursor-pointer" onclick="window.location='/admin/revenue/donations'">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-credit-card w-6 h-6 text-white"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('Payment Gateways') }}</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('Configure bKash, Nagad, and Rocket payment settings') }}</p>
                </div>
                <i class="lucide lucide-arrow-right w-5 h-5 text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors"></i>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 group">
            <div class="p-5 flex items-center gap-4 cursor-pointer" onclick="window.location='/admin/settings'">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-400 to-purple-500 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-settings w-6 h-6 text-white"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('Site Settings') }}</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('Configure site name, logo, timezone, language, and general preferences') }}</p>
                </div>
                <i class="lucide lucide-arrow-right w-5 h-5 text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors"></i>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 group">
            <div class="p-5 flex items-center gap-4 cursor-pointer" onclick="window.location='/admin/security/ai'">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-pink-400 to-rose-500 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-brain w-6 h-6 text-white"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('AI Configuration') }}</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('Configure AI provider, API key, and model settings') }}</p>
                </div>
                <i class="lucide lucide-arrow-right w-5 h-5 text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors"></i>
            </div>
        </div>
    </div>
</div>

@endsection
