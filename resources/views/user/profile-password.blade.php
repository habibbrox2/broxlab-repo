@extends('layouts.app')

@section('title', 'Change Password — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="mx-auto max-w-md px-4 py-8 sm:px-6">
    <div class="mb-6 text-center">
        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg">
            <i class="lucide lucide-key h-5 w-5"></i>
        </div>
        <h1 class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">{{ $header_title }}</h1>
        <p class="mt-1 text-sm text-slate-500">Use at least 8 characters with upper, lower, number & symbol</p>
    </div>

    @if (session('status'))
    <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700" role="status">
        <p class="flex items-center gap-2"><i class="lucide lucide-check-circle h-4 w-4"></i>{{ session('status') }}</p>
    </div>
    @endif

    @if ($errors->any())
    <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
        <p class="flex items-center gap-2"><i class="lucide lucide-alert-circle h-4 w-4"></i>{{ $errors->first() }}</p>
    </div>
    @endif

    <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        <div>
            <label for="current_password" class="mb-1 block text-sm font-semibold text-slate-700">Current password</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
        </div>

        <div>
            <label for="new_password" class="mb-1 block text-sm font-semibold text-slate-700">New password</label>
            <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password"
                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
        </div>

        <div>
            <label for="new_password_confirmation" class="mb-1 block text-sm font-semibold text-slate-700">Confirm new password</label>
            <input type="password" id="new_password_confirmation" name="new_password_confirmation" required minlength="8" autocomplete="new-password"
                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
        </div>

        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 active:scale-[0.99]">
            <i class="lucide lucide-shield-check h-4 w-4"></i> Update password
        </button>
    </form>
</div>
@endsection
