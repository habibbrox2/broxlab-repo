@extends('admin.layout')

@section('title', 'My Profile — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

@php
    $initials = mb_strtoupper(mb_substr($account->username ?? 'A', 0, 1));
    $detail = function (string $label, $value) {
        return ['label' => $label, 'value' => ($value === null || $value === '') ? null : $value];
    };
    $personal = [
        $detail('First name', $account->first_name ?? null),
        $detail('Last name', $account->last_name ?? null),
        $detail('Gender', $account->gender ?? null),
        $detail('Date of birth', $account->dob ?? null),
    ];
    $contact = [
        $detail('Email', $account->email ?? null),
        $detail('Phone', $account->phone ?? null),
        $detail('Alternate phone', $account->alternate_phone ?? null),
        $detail('Address', $account->address ?? null),
        $detail('City', $account->city ?? null),
        $detail('State', $account->state ?? null),
        $detail('Country', $account->country ?? null),
        $detail('Zip code', $account->zipcode ?? null),
    ];
    $socials = array_filter([
        'Facebook' => $account->facebook_url ?? null,
        'Twitter' => $account->twitter_url ?? null,
        'Instagram' => $account->instagram_url ?? null,
        'LinkedIn' => $account->linkedin_url ?? null,
    ]);
    $permissionCount = count($permissions);
@endphp

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-user-round w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('My Account') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('My Profile') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Your own account details and access') }}</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/profile/edit" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-pencil w-4 h-4"></i> {{ t('Edit Profile') }}
            </a>
            <a href="/admin/dashboard" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Dashboard') }}
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Identity + completeness --}}
    <div class="lg:col-span-1 space-y-5">
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="p-5 text-center">
                @if (!empty($account->profile_pic))
                    <img src="{{ asset($account->profile_pic) }}" alt="{{ t('Profile picture') }}"
                         class="w-20 h-20 rounded-2xl object-cover mx-auto ring-2 ring-white dark:ring-slate-900 shadow-md">
                @else
                    <div class="w-20 h-20 rounded-2xl mx-auto bg-gradient-to-br from-indigo-500 to-violet-600 text-white
                                flex items-center justify-center text-2xl font-bold shadow-md">
                        {{ $initials }}
                    </div>
                @endif

                <h2 class="mt-3 text-base font-bold text-slate-900 dark:text-white">{{ $display_name }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ '@'.($account->username ?? '') }}</p>

                <div class="mt-3 flex flex-wrap items-center justify-center gap-1.5">
                    @forelse ($roles as $role)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold
                                     bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            {{ \Illuminate\Support\Str::title($role['name'] ?? '') }}
                            @if (!empty($role['is_super_admin']))
                                <i class="lucide lucide-shield-check w-3 h-3"></i>
                            @endif
                        </span>
                    @empty
                        <span class="text-xs text-slate-400">{{ t('No roles assigned') }}</span>
                    @endforelse
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-center gap-1.5">
                    @if (!empty($account->email_verified))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300">
                            <i class="lucide lucide-badge-check w-3 h-3"></i> {{ t('Email verified') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">
                            <i class="lucide lucide-alert-triangle w-3 h-3"></i> {{ t('Email not verified') }}
                        </span>
                    @endif
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold capitalize
                                 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                        <i class="lucide lucide-key-round w-3 h-3"></i> {{ $account->auth_provider ?: 'local' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Completeness --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Profile completeness') }}</h3>
            </div>
            <div class="p-5">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                    <span>{{ t('Completed') }}</span>
                    <span>{{ $completeness['completeness'] }}%</span>
                </div>
                <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500"
                         style="width: {{ $completeness['completeness'] }}%"></div>
                </div>

                <ul class="mt-4 space-y-2">
                    @foreach (['name' => 'Full name', 'email' => 'Email address', 'phone' => 'Phone number', 'photo' => 'Profile photo', 'bio' => 'Address'] as $key => $label)
                        @php $done = !empty($completeness['checks'][$key]); @endphp
                        <li class="flex items-center gap-2 text-sm">
                            <i class="lucide {{ $done ? 'lucide-check-circle-2 text-emerald-500' : 'lucide-circle text-slate-300 dark:text-slate-600' }} w-4 h-4 flex-shrink-0"></i>
                            <span class="{{ $done ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500' }}">{{ t($label) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    {{-- Details --}}
    <div class="lg:col-span-2 space-y-5">
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-id-card w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Personal details') }}</h3>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 divide-slate-100 dark:divide-slate-800">
                @foreach ($personal as $row)
                    <div class="px-5 py-3">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-600">{{ t($row['label']) }}</dt>
                        <dd class="mt-0.5 text-sm {{ $row['value'] ? 'text-slate-900 dark:text-slate-100 capitalize' : 'text-slate-400 dark:text-slate-600' }}">
                            {{ $row['value'] ?? '—' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-mail w-4 h-4 text-violet-600 dark:text-violet-400"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Contact & address') }}</h3>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2">
                @foreach ($contact as $row)
                    <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-800 sm:odd:border-r">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-600">{{ t($row['label']) }}</dt>
                        <dd class="mt-0.5 text-sm {{ $row['value'] ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-600' }}">
                            {{ $row['value'] ?? '—' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>

        @if ($socials !== [])
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-link w-4 h-4 text-sky-600 dark:text-sky-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Social profiles') }}</h3>
                </div>
                <div class="p-5 flex flex-wrap gap-2">
                    @foreach ($socials as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5
                                  text-sm font-medium text-slate-700 dark:text-slate-300
                                  hover:bg-slate-100 dark:hover:bg-slate-800 hover:-translate-y-0.5 transition-all duration-150">
                            <i class="lucide lucide-external-link w-3.5 h-3.5"></i> {{ $network }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Access --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-shield w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Roles & permissions') }}</h3>
                </div>
                <a href="/admin/roles" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">{{ t('Manage RBAC') }}</a>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-600 mb-2">{{ t('Roles') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse ($roles as $role)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold
                                         bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                {{ \Illuminate\Support\Str::title($role['name'] ?? '') }}
                                @if (!empty($role['is_super_admin']))
                                    <span class="text-[10px] font-bold text-rose-600 dark:text-rose-400">{{ t('super admin') }}</span>
                                @endif
                            </span>
                        @empty
                            <span class="text-sm text-slate-400">{{ t('No roles assigned') }}</span>
                        @endforelse
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-600 mb-2">
                        {{ t('Permissions') }} · {{ $permissionCount }}
                    </p>
                    @if ($permissionCount === 0)
                        <p class="text-sm text-slate-400 dark:text-slate-600">{{ t('Granted through the roles above — none assigned directly.') }}</p>
                    @else
                        <div class="flex flex-wrap gap-1">
                            @foreach (array_slice($permissions, 0, 24) as $permission)
                                <span class="px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-900/20 text-[11px] font-medium text-indigo-700 dark:text-indigo-300">{{ $permission }}</span>
                            @endforeach
                            @if ($permissionCount > 24)
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[11px] font-medium text-slate-500">+{{ $permissionCount - 24 }} {{ t('more') }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Account activity --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-slate-500 dark:bg-slate-600/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-history w-4 h-4 text-slate-400 dark:text-slate-500"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Account activity') }}</h3>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2">
                @foreach ([
                    'Member since' => $account->created_at ? \Carbon\Carbon::parse($account->created_at)->format('M j, Y') : null,
                    'Last login' => $account->last_login ? \Carbon\Carbon::parse($account->last_login)->diffForHumans() : null,
                    'Last login IP' => $account->login_ip ?? null,
                    'Last device' => $account->login_device ? \Illuminate\Support\Str::limit($account->login_device, 40) : null,
                    'Password changed' => $account->password_changed_at ? \Carbon\Carbon::parse($account->password_changed_at)->diffForHumans() : null,
                    'Last updated' => $account->updated_at ? \Carbon\Carbon::parse($account->updated_at)->diffForHumans() : null,
                ] as $label => $value)
                    <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-800 sm:odd:border-r">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-600">{{ t($label) }}</dt>
                        <dd class="mt-0.5 text-sm {{ $value ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-600' }}">
                            {{ $value ?? '—' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="/profile/password" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-key-round w-4 h-4"></i> {{ t('Change password') }}
            </a>
            <a href="/user/security/2fa" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-shield-check w-4 h-4"></i> {{ t('Two-factor authentication') }}
            </a>
            <a href="/admin/account-settings" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-settings w-4 h-4"></i> {{ t('Account settings') }}
            </a>
        </div>
    </div>
</div>

@endsection
