@extends('admin.layout')

@section('title', 'Account Settings — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

@php
    $permissionCount = count($permissions);
@endphp

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(100,116,139,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-settings w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('My Account') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Account Settings') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Sign-in, security and notification preferences for your own account') }}</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/admin/profile" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-user-round w-4 h-4"></i> {{ t('My Profile') }}
            </a>
            <a href="/admin/dashboard" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Dashboard') }}
            </a>
        </div>
    </div>
</div>

@if ($needs_password)
    <div class="mb-5 rounded-2xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-950/30 px-5 py-4 flex items-start gap-3">
        <i class="lucide lucide-alert-triangle w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5"></i>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ t('No password is set on your account yet') }}</p>
            <p class="mt-0.5 text-sm text-amber-700 dark:text-amber-300">{{ t('You signed in through a linked provider. Set a local password to be able to sign in without it.') }}</p>
        </div>
        <a href="/profile/password" class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 px-4 py-2 text-sm font-semibold text-white transition-all duration-150">
            {{ t('Set password') }}
        </a>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    {{-- Account --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-id-card w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
            </div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Account') }}</h3>
        </div>
        <dl class="divide-y divide-slate-100 dark:divide-slate-800">
            <div class="px-5 py-3 flex items-center justify-between gap-4">
                <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('Username') }}</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $account->username ?? '—' }}</dd>
            </div>
            <div class="px-5 py-3 flex items-center justify-between gap-4">
                <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('Email') }}</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ $account->email ?? '—' }}</dd>
            </div>
            <div class="px-5 py-3 flex items-center justify-between gap-4">
                <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('Email verified') }}</dt>
                <dd>
                    @if (!empty($account->email_verified))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300">
                            <i class="lucide lucide-badge-check w-3 h-3"></i> {{ t('Verified') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">
                            <i class="lucide lucide-alert-triangle w-3 h-3"></i> {{ t('Not verified') }}
                        </span>
                    @endif
                </dd>
            </div>
            <div class="px-5 py-3 flex items-center justify-between gap-4">
                <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('Sign-in provider') }}</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-slate-100 capitalize">{{ $account->auth_provider ?: 'local' }}</dd>
            </div>
            <div class="px-5 py-3 flex items-center justify-between gap-4">
                <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('Member since') }}</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $account->created_at ? \Carbon\Carbon::parse($account->created_at)->format('M j, Y') : '—' }}
                </dd>
            </div>
        </dl>
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-800">
            <a href="/profile/edit" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-pencil w-4 h-4"></i> {{ t('Edit profile details') }}
            </a>
        </div>
    </div>

    {{-- Security --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-lock w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Sign-in & security') }}</h3>
        </div>
        <dl class="divide-y divide-slate-100 dark:divide-slate-800">
            <div class="px-5 py-3 flex items-center justify-between gap-4">
                <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('Password') }}</dt>
                <dd>
                    @if ($has_password)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300">
                            <i class="lucide lucide-check w-3 h-3"></i> {{ t('Set') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300">
                            <i class="lucide lucide-x w-3 h-3"></i> {{ t('Not set') }}
                        </span>
                    @endif
                </dd>
            </div>
            <div class="px-5 py-3 flex items-center justify-between gap-4">
                <dt class="text-sm text-slate-500 dark:text-slate-400">
                    {{ t('Password changed') }}
                    @if ($account->password_changed_at)
                        <span class="block text-xs text-slate-400 dark:text-slate-600">{{ \Carbon\Carbon::parse($account->password_changed_at)->diffForHumans() }}</span>
                    @endif
                </dt>
                <dd>
                    <a href="/profile/password" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">{{ t('Change') }}</a>
                </dd>
            </div>
            <div class="px-5 py-3 flex items-center justify-between gap-4">
                <dt class="text-sm text-slate-500 dark:text-slate-400">
                    {{ t('Two-factor authentication') }}
                    @if ($two_factor_required)
                        <span class="block text-xs text-amber-600 dark:text-amber-400">{{ t('Required for admin accounts') }}</span>
                    @endif
                </dt>
                <dd>
                    @if ($two_factor_enabled)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300">
                            <i class="lucide lucide-shield-check w-3 h-3"></i> {{ t('Enabled') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                            <i class="lucide lucide-shield-off w-3 h-3"></i> {{ t('Disabled') }}
                        </span>
                    @endif
                </dd>
            </div>
            <div class="px-5 py-3 flex items-center justify-between gap-4">
                <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('Last login') }}</dt>
                <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $account->last_login ? \Carbon\Carbon::parse($account->last_login)->diffForHumans() : '—' }}
                </dd>
            </div>
        </dl>
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-800 flex flex-wrap gap-2">
            <a href="/user/security/2fa" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-shield-check w-4 h-4"></i>
                {{ $two_factor_enabled ? t('Manage 2FA') : t('Enable 2FA') }}
            </a>
            <a href="/profile/password" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-key-round w-4 h-4"></i> {{ t('Change password') }}
            </a>
        </div>
    </div>

    {{-- Notifications --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-bell w-4 h-4 text-amber-600 dark:text-amber-400"></i>
            </div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Notifications') }}</h3>
        </div>
        <div class="p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0
                        {{ $unread_count > 0 ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-400' }}">
                <span class="text-lg font-bold">{{ $unread_count }}</span>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900 dark:text-white">
                    {{ $unread_count > 0 ? t('unread notifications') : t('No unread notifications') }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ t('System messages, announcements and account alerts addressed to you.') }}</p>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-800">
            <a href="/admin/my/notifications" class="inline-flex items-center gap-2 rounded-xl bg-amber-600 hover:bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-amber-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-inbox w-4 h-4"></i> {{ t('Open inbox') }}
            </a>
        </div>
    </div>

    {{-- Access --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-shield w-4 h-4 text-violet-600 dark:text-violet-400"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Access level') }}</h3>
            </div>
            <a href="/admin/roles" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">{{ t('Manage RBAC') }}</a>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-600 mb-2">{{ t('Your roles') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    @forelse ($roles as $role)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                            {{ \Illuminate\Support\Str::title($role['name'] ?? '') }}
                            @if (!empty($role['is_super_admin']))
                                <span class="text-[10px] font-bold text-rose-600 dark:text-rose-400">{{ t('super admin') }}</span>
                            @endif
                        </span>
                    @empty
                        <span class="text-sm text-slate-400">{{ t('No roles assigned') }}</span>
                    @endforelse
                </div>
            </div>
            <div class="flex items-center justify-between gap-4">
                <span class="text-sm text-slate-500 dark:text-slate-400">{{ t('Effective permissions') }}</span>
                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $permissionCount }}</span>
            </div>
            @if ($permissionCount > 0)
                <div class="max-h-28 overflow-y-auto flex flex-wrap gap-1 pt-1">
                    @foreach (array_slice($permissions, 0, 18) as $permission)
                        <span class="px-2 py-0.5 rounded-md bg-violet-50 dark:bg-violet-900/20 text-[11px] font-medium text-violet-700 dark:text-violet-300">{{ $permission }}</span>
                    @endforeach
                    @if ($permissionCount > 18)
                        <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[11px] font-medium text-slate-500">+{{ $permissionCount - 18 }} {{ t('more') }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Session --}}
<div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
    <div class="px-5 py-3.5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/20 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-log-out w-4 h-4 text-red-500 dark:text-red-400"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Sign out of this session') }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ t('You will be returned to the login page.') }}</p>
            </div>
        </div>
        <form method="POST" action="/logout">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/20 px-4 py-2.5 text-sm font-semibold text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 transition-all duration-150">
                <i class="lucide lucide-log-out w-4 h-4"></i> {{ t('Log out') }}
            </button>
        </form>
    </div>
</div>

@endsection
