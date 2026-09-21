@extends('layouts.app')

@section('title', 'Reset Password — '.($appSettings['site_name'] ?? 'BroxBhai'))

@section('content')
<div class="relative isolate flex min-h-[70vh] items-center justify-center overflow-hidden bg-slate-50 px-4 py-10 sm:px-6 sm:py-14">
    <div class="pointer-events-none absolute inset-0 -z-10">
        <div class="absolute -top-24 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-indigo-500/15 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-violet-500/10 blur-3xl"></div>
    </div>

    <section class="relative w-full max-w-[440px] overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_24px_80px_-30px_rgba(15,23,42,0.3)]">
        <div class="absolute left-0 right-0 top-0 h-1 bg-gradient-to-r from-violet-500 via-indigo-500 to-violet-400"></div>

        <div class="px-6 pb-2 pt-8 sm:px-8 sm:pt-10">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 text-white shadow-lg shadow-violet-500/20">
                <i class="lucide lucide-shield h-6 w-6"></i>
            </div>
            <div class="text-center">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ t('Set a new password') }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ t('Choose a strong password you haven\'t used before.') }}</p>
            </div>
        </div>

        <div class="px-6 pb-8 sm:px-8 sm:pb-10">
            @if (session('error'))
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
                <p class="flex items-center gap-2"><i class="lucide lucide-alert-circle h-4 w-4"></i>{{ session('error') }}</p>
            </div>
            @endif

            @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
                <p class="flex items-center gap-2"><i class="lucide lucide-alert-circle h-4 w-4"></i>{{ $errors->first() }}</p>
            </div>
            @endif

            @if ($token_valid ?? false)
            <form method="POST" action="{{ route('reset-password.submit') }}" class="space-y-5" novalidate>
                @csrf
                <input type="hidden" name="reset_token" value="{{ $reset_token }}">

                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-semibold text-slate-700">{{ t('New Password') }}</label>
                    <div class="relative">
                        <i class="lucide lucide-lock absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="new-password" required
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3.5 pl-11 pr-12 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                        <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-indigo-600"
                                @click="$nextTick(() => { const p = $el.previousElementSibling; p.type = p.type === 'password' ? 'text' : 'password'; })"
                                aria-label="{{ t('Show password') }}">
                            <i class="lucide lucide-eye h-5 w-5"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500">{{ t('Minimum 8 characters with uppercase, lowercase, number, and special character.') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label for="confirm_password" class="block text-sm font-semibold text-slate-700">{{ t('Confirm Password') }}</label>
                    <div class="relative">
                        <i class="lucide lucide-lock absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" autocomplete="new-password" required
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3.5 pl-11 pr-12 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                    </div>
                </div>

                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-violet-500/25 transition hover:from-violet-700 hover:to-indigo-700 active:scale-[0.99]">
                    <i class="lucide lucide-check-circle h-4 w-4"></i> {{ t('Reset Password') }}
                </button>
            </form>

            <div class="mt-5 flex justify-center">
                <a href="/login" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">
                    <i class="lucide lucide-arrow-left h-4 w-4 text-indigo-600"></i> {{ t('Back to Login') }}
                </a>
            </div>
            @else
            <div class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center">
                <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-red-100 text-red-600">
                    <i class="lucide lucide-alert-triangle h-8 w-8"></i>
                </div>
                <h3 class="mb-1 text-lg font-bold text-red-800">{{ t('Invalid or Expired Link') }}</h3>
                <p class="mb-4 text-sm text-red-600">{{ t('This password reset link is invalid or has expired. Password reset links expire after 1 hour.') }}</p>
                <a href="/forgot-password" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                    <i class="lucide lucide-refresh-ccw h-4 w-4"></i> {{ t('Request a New Link') }}
                </a>
            </div>
            @endif
        </div>
    </section>
</div>
@endsection
