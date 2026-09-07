@extends('layouts.app')

@section('title', 'Login — '.($appSettings['site_name'] ?? 'BroxBhai'))

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
                <i class="lucide lucide-log-in h-6 w-6"></i>
            </div>
            <div class="text-center">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Welcome back</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">Sign in to your account and continue where you left off.</p>
            </div>
        </div>

        <div class="px-6 pb-8 sm:px-8 sm:pb-10">
            @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
                <p class="flex items-center gap-2"><i class="lucide lucide-alert-circle h-4 w-4"></i>{{ $errors->first() }}</p>
            </div>
            @endif

            @if (session('status'))
            <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700" role="status">
                {{ session('status') }}
            </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}" class="space-y-5" novalidate>
                @csrf

                <div class="space-y-1.5">
                    <label for="username" class="block text-sm font-semibold text-slate-700">Email or Username</label>
                    <div class="relative">
                        <i class="lucide lucide-user absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="Enter email or username"
                               autocomplete="username" required
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3.5 pl-11 pr-4 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                        <a href="/forgot-password" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <i class="lucide lucide-lock absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3.5 pl-11 pr-12 text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                        <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-indigo-600"
                                @click="$nextTick(() => { const p = $el.previousElementSibling; p.type = p.type === 'password' ? 'text' : 'password'; })"
                                aria-label="Show password">
                            <i class="lucide lucide-eye h-5 w-5"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <label class="flex items-center gap-3 text-sm font-medium text-slate-700">
                        <input type="checkbox" name="remember_me" value="1"
                               class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Remember me for 30 days
                    </label>
                </div>

                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-700 hover:to-violet-700 active:scale-[0.99]">
                    <i class="lucide lucide-log-in h-4 w-4"></i> Sign In
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
                Don't have an account?
                <a href="{{ route('register') }}" class="font-bold text-indigo-600 hover:text-indigo-700">Create one</a>
            </p>
        </div>
    </section>
</div>
@endsection