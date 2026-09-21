@extends('layouts.app')

@section('title', 'Verify Email — '.($appSettings['site_name'] ?? 'BroxBhai'))

@section('content')
<div class="relative isolate flex min-h-[70vh] items-center justify-center overflow-hidden bg-slate-50 px-4 py-10 sm:px-6 sm:py-14">
    <div class="pointer-events-none absolute inset-0 -z-10">
        <div class="absolute -top-24 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-indigo-500/10 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-emerald-500/10 blur-3xl"></div>
    </div>

    <section class="relative w-full max-w-[480px] overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_24px_80px_-30px_rgba(15,23,42,0.3)]">
        <div class="absolute left-0 right-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-emerald-500 to-indigo-400"></div>

        <div class="px-6 pb-2 pt-8 sm:px-8 sm:pt-10">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-500/20">
                <i class="lucide lucide-mail h-6 w-6"></i>
            </div>
            <div class="text-center">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ t('Check your inbox') }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    @if (!empty($email))
                        A verification link was sent to <span class="font-semibold text-slate-700">{{ $email }}</span>. Click the link in the email to verify your address.
                    @else
                        Enter your email below and we'll send a verification link.
                    @endif
                </p>
            </div>
        </div>

        <div class="px-6 pb-8 sm:px-8 sm:pb-10">
            @if (session('status'))
            <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700" role="status">
                {{ session('status') }}
            </div>
            @endif

            @if (session('error'))
            <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700" role="alert">
                {{ session('error') }}
            </div>
            @endif

            <form method="POST" action="{{ route('resend-verification-email') }}" class="space-y-5" novalidate>
                @csrf

                <div class="space-y-1.5">
                    <label for="email" class="block text-sm font-semibold text-slate-700">{{ t('Email address') }}</label>
                    <div class="relative">
                        <i class="lucide lucide-mail absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input type="email" id="email" name="email" value="{{ $email }}" required
                               placeholder="you@example.com" autocomplete="email"
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3.5 pl-11 pr-4 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                    </div>
                </div>

                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-700 hover:to-violet-700 active:scale-[0.99]">
                    <i class="lucide lucide-refresh-cw h-4 w-4"></i> {{ t('Resend verification email') }}
                </button>
            </form>

            <div class="mt-6 border-t border-slate-100 pt-5 text-center">
                <a href="/login" class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-700">
                    <i class="lucide lucide-arrow-left h-4 w-4"></i> {{ t('Back to login') }}
                </a>
            </div>
        </div>
    </section>
</div>
@endsection
