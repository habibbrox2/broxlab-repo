@extends('layouts.app')

@section('title', 'Two-Factor Authentication — '.($appSettings['site_name'] ?? 'BroxBhai'))

@section('content')
<div class="relative isolate flex min-h-[70vh] items-center justify-center overflow-hidden bg-slate-50 px-4 py-10 sm:px-6 sm:py-14">
    <div class="pointer-events-none absolute inset-0 -z-10">
        <div class="absolute -top-24 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-violet-500/15 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-indigo-500/10 blur-3xl"></div>
    </div>

    <section class="relative w-full max-w-[440px] overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_24px_80px_-30px_rgba(15,23,42,0.3)]">
        <div class="absolute left-0 right-0 top-0 h-1 bg-gradient-to-r from-violet-500 via-indigo-500 to-violet-400"></div>

        <div class="px-6 pb-2 pt-8 sm:px-8 sm:pt-10">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 text-white shadow-lg shadow-violet-500/20">
                <i class="lucide lucide-shield-check h-6 w-6"></i>
            </div>
            <div class="text-center">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Two-Factor Authentication</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">Enter the 6-digit code from your authenticator app to complete sign-in.</p>
            </div>
        </div>

        <div class="px-6 pb-8 sm:px-8 sm:pb-10">
            @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
                <p class="flex items-center gap-2"><i class="lucide lucide-alert-circle h-4 w-4"></i>{{ $errors->first() }}</p>
            </div>
            @endif

            <form method="POST" action="{{ route('verify-2fa.submit') }}" class="space-y-5" novalidate
                  x-data="{ code: '' }">
                @csrf

                <div class="space-y-1.5">
                    <label for="code" class="block text-center text-sm font-semibold text-slate-700">Authentication code</label>
                    <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                           placeholder="000000" autocomplete="one-time-code" autofocus required
                           x-model="code"
                           class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-4 text-center text-2xl font-black tracking-[0.5em] text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>

                <button type="submit" :disabled="code.length !== 6"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-violet-500/25 transition hover:from-violet-700 hover:to-indigo-700 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-50">
                    <i class="lucide lucide-shield-check h-4 w-4"></i> Verify code
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-slate-400">
                Lost your device? Use a backup code from your 2FA setup.
            </p>
        </div>
    </section>
</div>
@endsection
