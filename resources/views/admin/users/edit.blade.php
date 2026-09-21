@extends('admin.layout')

@section('title', 'Edit User — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

{{-- Page Header --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-900 to-purple-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-user w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Users</p>
                <h1 class="text-xl font-bold text-white">{{ t('Edit User') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Update user profile information') }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/users/view/{{ $user['id'] }}" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-eye w-4 h-4"></i> View
            </a>
            <a href="/admin/users" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back to Users') }}
            </a>
        </div>
    </div>
</div>

{{-- Form --}}
<div class="max-w-3xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-user w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('User Details') }}</h3>
                <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Update the user\'s profile information') }}</p>
            </div>
        </div>
        <div class="p-5 sm:p-6">
            <form method="post" action="/admin/users/edit/{{ $user['id'] }}" class="space-y-5">
                @csrf
                <input type="hidden" name="id" value="{{ $user['id'] }}">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('First Name') }}</label>
                        <input type="text" name="first_name" value="{{ $user['first_name'] }}" placeholder="{{ t('First name') }}"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Last Name') }}</label>
                        <input type="text" name="last_name" value="{{ $user['last_name'] }}" placeholder="{{ t('Last name') }}"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Phone') }}</label>
                        <input type="text" name="phone" value="{{ $user['phone'] }}" placeholder="+1 234 567 890"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Alternate Phone') }}</label>
                        <input type="text" name="alternate_phone" value="{{ $user['alternate_phone'] }}" placeholder="+1 234 567 890"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">Address</label>
                        <input type="text" name="address" value="{{ $user['address'] }}" placeholder="{{ t('Full address') }}"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">City</label>
                        <input type="text" name="city" value="{{ $user['city'] }}" placeholder="City"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">State</label>
                        <input type="text" name="state" value="{{ $user['state'] }}" placeholder="State"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">Country</label>
                        <input type="text" name="country" value="{{ $user['country'] }}" placeholder="Country"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('ZIP Code') }}</label>
                        <input type="text" name="zipcode" value="{{ $user['zipcode'] }}" placeholder="12345"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Profile Picture URL') }}</label>
                        <input type="url" name="profile_pic" value="{{ $user['profile_pic'] }}" placeholder="https://..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                </div>

                <hr class="border-slate-100 dark:border-slate-800">

                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Social Links') }}</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Facebook URL') }}</label>
                        <input type="url" name="facebook_url" value="{{ $user['facebook_url'] }}" placeholder="https://facebook.com/..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Twitter URL') }}</label>
                        <input type="url" name="twitter_url" value="{{ $user['twitter_url'] }}" placeholder="https://twitter.com/..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Instagram URL') }}</label>
                        <input type="url" name="instagram_url" value="{{ $user['instagram_url'] }}" placeholder="https://instagram.com/..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('LinkedIn URL') }}</label>
                        <input type="url" name="linkedin_url" value="{{ $user['linkedin_url'] }}" placeholder="https://linkedin.com/..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>
                </div>

                <hr class="border-slate-100 dark:border-slate-800">

                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Status</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach(['active', 'inactive', 'pending'] as $status)
                        <label class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-xl border px-3.5 py-2.5 text-xs font-semibold shadow-sm transition-all duration-200 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-600 has-[:checked]:text-white {{ $user['status'] === $status ? 'has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-600 has-[:checked]:text-white' : '' }}">
                            <input type="radio" name="status" value="{{ $status }}" {{ $user['status'] === $status ? 'checked' : '' }} class="hidden">
                            {{ ucfirst($status) }}
                        </label>
                    @endforeach
                    <label class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-xl border px-3.5 py-2.5 text-xs font-semibold shadow-sm transition-all duration-200 has-[:checked]:border-rose-500 has-[:checked]:bg-rose-600 has-[:checked]:text-white {{ $user['status'] === 'banned' ? 'has-[:checked]:border-rose-500 has-[:checked]:bg-rose-600 has-[:checked]:text-white' : '' }}">
                        <input type="radio" name="status" value="banned" {{ $user['status'] === 'banned' ? 'checked' : '' }} class="hidden">
                        🚫 Banned
                    </label>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-check-circle w-4 h-4"></i> {{ t('Update User') }}
                    </button>
                    <a href="/admin/users/view/{{ $user['id'] }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-x w-4 h-4"></i> {{ t('Cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
