@extends('layouts.app')

@section('title', 'Forgot Password — '.($appSettings['site_name'] ?? 'BroxBhai'))

@section('content')
<div class="relative isolate flex min-h-[70vh] items-center justify-center overflow-hidden bg-slate-50 px-4 py-10 sm:px-6 sm:py-14">
    <div class="pointer-events-none absolute inset-0 -z-10">
        <div class="absolute -top-24 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-indigo-500/15 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-cyan-500/10 blur-3xl"></div>
        <div class="absolute left-0 top-1/3 h-64 w-64 rounded-full bg-violet-500/10 blur-3xl"></div>
    </div>

    <section class="relative w-full max-w-[440px] overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_24px_80px_-30px_rgba(15,23,42,0.3)]">
        <div class="absolute left-0 right-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-400"></div>

        <div class="px-6 pb-2 pt-8 sm:px-8 sm:pt-10">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-500/20">
                <i class="lucide lucide-key h-6 w-6"></i>
            </div>
            <div class="text-center">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ t('Reset your password') }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ t('Enter your email and we\'ll send you a password reset link.') }}</p>
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

            <form method="POST" action="{{ route('forgot-password.submit') }}" class="space-y-5" novalidate>
                @csrf

                <div class="space-y-1.5">
                    <label for="email" class="block text-sm font-semibold text-slate-700">{{ t('Email Address') }}</label>
                    <div class="relative">
                        <i class="lucide lucide-mail absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="{{ t('Enter your email address') }}"
                               autocomplete="email" required
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3.5 pl-11 pr-4 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                    </div>
                    <p class="text-xs text-slate-500">{{ t('Enter the email address associated with your account.') }}</p>
                </div>

                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-700 hover:to-violet-700 active:scale-[0.99]">
                    <i class="lucide lucide-send h-4 w-4"></i> {{ t('Send Reset Link') }}
                </button>
            </form>

            <div class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                <a href="/login" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">
                    <i class="lucide lucide-arrow-left h-4 w-4 text-indigo-600"></i> {{ t('Back to Login') }}
                </a>
                <a href="/register" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">
                    <i class="lucide lucide-user-plus h-4 w-4 text-indigo-600"></i> {{ t('Create Account') }}
                </a>
            </div>

            <div class="mt-5 rounded-2xl border border-sky-200 bg-sky-50 p-4">
                <h6 class="mb-2 flex items-center gap-2 text-sm font-semibold text-sky-800">
                    <i class="lucide lucide-info h-4 w-4"></i> {{ t('What happens next?') }}
                </h6>
                <ul class="list-inside list-disc space-y-1 pl-4 text-sm text-sky-700">
                    <li>{{ t('We\'ll send a password reset link to your email') }}</li>
                    <li>{{ t('The link expires in') }} <strong>{{ t('1 hour') }}</strong> {{ t('for security') }}</li>
                    <li>{{ t('Click the link and create a new password') }}</li>
                    <li>{{ t('You can then log in with your new password') }}</li>
                </ul>
            </div>
        </div>
    </section>
</div>
@endsection
