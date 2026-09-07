@extends('admin.layout')

@section('title', 'Security Settings — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-cyan-900 to-sky-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(6,182,212,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-shield-check w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Administration</p>
                <h1 class="text-xl font-bold text-white">Security Settings</h1>
                <p class="text-sm text-white/60 mt-0.5">Configure authentication, 2FA, and security policies</p>
            </div>
        </div>
    </div>
</div>

<div class="max-w-4xl">
    <div class="space-y-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-cyan-50 dark:bg-cyan-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-lock w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Authentication Settings</h3>
                </div>
                <a href="/admin/security/auth" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Configure →</a>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Enable 2FA</span>
                    <span class="text-sm font-medium {{ $appSettings['enable_2fa'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-600' }}">{{ $appSettings['enable_2fa'] ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Email Verification</span>
                    <span class="text-sm font-medium {{ $appSettings['require_email_verification'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-600' }}">{{ $appSettings['require_email_verification'] ? 'Required' : 'Optional' }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">User Registration</span>
                    <span class="text-sm font-medium {{ $appSettings['allow_user_registration'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-600' }}">{{ $appSettings['allow_user_registration'] ? 'Open' : 'Closed' }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Max Login Attempts</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $appSettings['max_login_attempts'] ?? 5 }}</span>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-mail w-4 h-4 text-violet-600 dark:text-violet-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">SMTP Settings</h3>
                </div>
                <a href="/admin/security/smtp" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Configure →</a>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">SMTP Host</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white truncate max-w-[150px]">{{ $appSettings['smtp_host'] ?: '—' }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">SMTP Port</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $appSettings['smtp_port'] ?: '—' }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Encryption</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $appSettings['smtp_encryption'] ?: '—' }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Mail From</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white truncate max-w-[150px]">{{ $appSettings['mail_from_address'] ?: '—' }}</span>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-alert-triangle w-4 h-4 text-rose-600 dark:text-rose-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recaptcha</h3>
                </div>
                <a href="/admin/security/recaptcha" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Configure →</a>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Site Key</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white truncate max-w-[150px]">{{ substr($appSettings['recaptcha_site_key'] ?? '', 0, 20) }}...</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Secret Key</span>
                    <span class="text-sm font-medium text-slate-900 dark:text-white truncate max-w-[150px]">{{ substr($appSettings['recaptcha_secret_key'] ?? '', 0, 20) }}...</span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
