@extends('layouts.app')

@section('title', 'Account Settings — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">Account Settings</h1>
        <p class="mt-0.5 text-sm text-slate-500">Security and account preferences</p>
    </div>

    @if (session('status'))
    <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700" role="status">
        {{ session('status') }}
    </div>
    @endif

    {{-- Password --}}
    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <i class="lucide lucide-key h-5 w-5"></i>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Password</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        @if ($user_has_password)
                            Last changed:
                            @if ($user_data->password_changed_at)
                                {{ \Carbon\Carbon::parse($user_data->password_changed_at)->diffForHumans() }}
                            @else
                                unknown
                            @endif
                        @elseif ($show_password_setup)
                            You signed up with a social account and have no password yet.
                        @else
                            No password set.
                        @endif
                    </p>
                </div>
            </div>
            <a href="/profile/password" class="shrink-0 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                {{ $user_has_password ? 'Change' : 'Set password' }}
            </a>
        </div>
    </div>

    {{-- OAuth / linked accounts --}}
    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                <i class="lucide lucide-link-2 h-5 w-5"></i>
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-900">Linked accounts</h2>
                <p class="mt-0.5 text-sm text-slate-500">
                    Sign-in provider:
                    <span class="font-semibold capitalize text-slate-700">{{ $user_data->auth_provider ?? 'email' }}</span>
                    @if ($user_data->firebase_uid)
                        &middot; Firebase linked
                    @endif
                </p>
                <p class="mt-2 text-xs text-slate-400">OAuth account linking is managed by the legacy app for now (Phase 2 follow-up).</p>
            </div>
        </div>
    </div>

    {{-- Account info --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <i class="lucide lucide-user h-5 w-5"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold text-slate-900">Account information</h2>
                <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-4 sm:block">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Username</dt>
                        <dd class="font-medium text-slate-800">{{ $user_data->username }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 sm:block">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email</dt>
                        <dd class="truncate font-medium text-slate-800">{{ $user_data->email }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 sm:block">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</dt>
                        <dd class="font-medium capitalize text-slate-800">{{ $user_data->status }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 sm:block">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email verified</dt>
                        <dd class="font-medium {{ $user_data->email_verified ? 'text-emerald-600' : 'text-amber-600' }}">
                            {{ $user_data->email_verified ? 'Yes' : 'Pending' }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-3 flex gap-2">
                    <a href="/profile" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">View profile</a>
                    <a href="/profile/edit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit profile</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
