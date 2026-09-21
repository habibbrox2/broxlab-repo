@extends('layouts.app')

@section('title', 'Create Account — '.($appSettings['site_name'] ?? 'BroxBhai'))

@section('content')
<div class="relative isolate flex min-h-[70vh] items-center justify-center overflow-hidden bg-slate-50 px-4 py-10 sm:px-6 sm:py-14">
    <div class="pointer-events-none absolute inset-0 -z-10">
        <div class="absolute -top-24 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-violet-500/15 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-cyan-500/10 blur-3xl"></div>
    </div>

    <section class="relative w-full max-w-[480px] overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_24px_80px_-30px_rgba(15,23,42,0.3)]">
        <div class="absolute left-0 right-0 top-0 h-1 bg-gradient-to-r from-violet-500 via-indigo-500 to-violet-400"></div>

        <div class="px-6 pb-2 pt-8 sm:px-8 sm:pt-10">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 text-white shadow-lg shadow-violet-500/20">
                <i class="lucide lucide-user-plus h-6 w-6"></i>
            </div>
            <div class="text-center">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ t('Create your account') }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ t('Join us and start exploring services in minutes.') }}</p>
            </div>
        </div>

        <div class="px-6 pb-8 sm:px-8 sm:pb-10">
            @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="{{ route('register.submit') }}" class="space-y-4" novalidate>
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ t('Email Address') }}</label>
                    <div class="relative">
                        <i class="lucide lucide-mail absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com"
                               autocomplete="email" required
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-11 pr-4 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                    </div>
                </div>

                <div>
                    <label for="username" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ t('Username') }}</label>
                    <div class="relative">
                        <i class="lucide lucide-user absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="{{ t('3-30 letters, numbers, dots, hyphens') }}"
                               autocomplete="username" required
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-11 pr-4 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ t('First Name') }}</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" placeholder="{{ t('First name') }}"
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                    </div>
                    <div>
                        <label for="last_name" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ t('Last Name') }}</label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" placeholder="{{ t('Last name') }}"
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                    </div>
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ t('Password') }}</label>
                    <div class="relative">
                        <i class="lucide lucide-lock absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input type="password" id="password" name="password" placeholder="{{ t('Min 8 chars, upper/lower/number/symbol') }}"
                               autocomplete="new-password" required
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-11 pr-12 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                        <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-indigo-600"
                                @click="$nextTick(() => { const p = $el.previousElementSibling; p.type = p.type === 'password' ? 'text' : 'password'; })"
                                aria-label="{{ t('Show password') }}">
                            <i class="lucide lucide-eye h-5 w-5"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="confirm_password" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ t('Confirm Password') }}</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="{{ t('Repeat password') }}"
                           autocomplete="new-password" required
                           class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>

                <label class="flex items-start gap-2.5 text-xs text-slate-500">
                    <input type="checkbox" name="terms" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" {{ old('terms') ? 'checked' : '' }}>
                    <span>{{ t('I agree to the') }} <a href="/terms" class="font-semibold text-indigo-600 hover:underline">{{ t('Terms of Service') }}</a> and <a href="/privacy" class="font-semibold text-indigo-600 hover:underline">{{ t('Privacy Policy') }}</a>.</span>
                </label>

                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-violet-500/25 transition hover:from-violet-700 hover:to-indigo-700 active:scale-[0.99]">
                    <i class="lucide lucide-user-plus h-4 w-4"></i> {{ t('Create Account') }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
                {{ t('Already have an account?') }}
                <a href="{{ route('login') }}" class="font-bold text-indigo-600 hover:text-indigo-700">{{ t('Sign in') }}</a>
            </p>
        </div>
    </section>
</div>
@endsection