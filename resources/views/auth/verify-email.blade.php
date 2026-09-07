@extends('layouts.app')

@section('title', 'Verify Email — '.($appSettings['site_name'] ?? 'BroxBhai'))

@section('content')
<div class="relative isolate flex min-h-[70vh] items-center justify-center overflow-hidden bg-slate-50 px-4 py-10 sm:px-6 sm:py-14">
    <div class="pointer-events-none absolute inset-0 -z-10">
        <div class="absolute -top-24 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-emerald-500/10 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-indigo-500/10 blur-3xl"></div>
    </div>

    <section class="relative w-full max-w-[480px] overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_24px_80px_-30px_rgba(15,23,42,0.3)]">
        <div class="absolute left-0 right-0 top-0 h-1 bg-gradient-to-r from-emerald-500 via-indigo-500 to-emerald-400"></div>

        <div class="px-6 pb-2 pt-8 sm:px-8 sm:pt-10">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20">
                <i class="lucide lucide-mail-check h-6 w-6"></i>
            </div>
            <div class="text-center">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Verify your email</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    @if (!empty($email))
                        We sent a verification link to <span class="font-semibold text-slate-700">{{ $email }}</span>.
                    @else
                        Open the verification link from your inbox, or paste the token below.
                    @endif
                </p>
            </div>
        </div>

        <div class="px-6 pb-8 sm:px-8 sm:pb-10">
            @if (!empty($error))
            <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700" role="alert">
                <p class="flex items-center gap-2"><i class="lucide lucide-alert-circle h-4 w-4"></i>{{ $error }}</p>
            </div>
            @endif

            <form method="POST" action="{{ route('verify-email.submit') }}" class="space-y-5" novalidate>
                @csrf

                <div class="space-y-1.5">
                    <label for="verification_token" class="block text-sm font-semibold text-slate-700">Verification token</label>
                    <input type="text" id="verification_token" name="verification_token" value="{{ $token }}" required
                           placeholder="Paste the token from your email"
                           class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 font-mono text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>

                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-500/25 transition hover:from-emerald-700 hover:to-teal-700 active:scale-[0.99]">
                    <i class="lucide lucide-check-circle h-4 w-4"></i> Verify email
                </button>
            </form>

            <div class="mt-6 border-t border-slate-100 pt-5 text-center">
                <p class="text-sm text-slate-500">Didn't get the email?</p>
                <a href="/send-verification-email" class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-700">
                    <i class="lucide lucide-refresh-cw h-4 w-4"></i> Resend verification email
                </a>
            </div>
        </div>
    </section>
</div>
@endsection
