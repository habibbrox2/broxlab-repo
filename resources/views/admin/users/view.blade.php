@extends('admin.layout')

@section('title', 'View User — '.($appSettings['site_name'] ?? 'BroxLab'))

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
                <h1 class="text-xl font-bold text-white">{{ $user['username'] }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ $user['email'] }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/users/edit/{{ $user['id'] }}" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-edit w-4 h-4"></i> {{ t('Edit') }}
            </a>
            <a href="/admin/users" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back to Users') }}
            </a>
        </div>
    </div>
</div>

{{-- Profile Card --}}
<div class="max-w-6xl">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            {{-- Personal Info --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-user w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Personal Information') }}</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('User profile details') }}</p>
                    </div>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">{{ t('Full Name') }}</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['full_name'] ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">{{ t('Username') }}</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['username'] }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">{{ t('Email') }}</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['email'] }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Status</label>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                                @if($user['status'] === 'active') bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400
                                @elseif($user['status'] === 'inactive') bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400
                                @elseif($user['status'] === 'banned') bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400
                                @else bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 @endif">
                                {{ ucfirst($user['status']) }}
                            </span>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Role</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['role'] ?? '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Admin</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['is_admin'] ? 'Yes' : 'No' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Gender</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ ucfirst($user['gender'] ?? 'Not set') }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">{{ t('Date of Birth') }}</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['dob'] ? \Carbon\Carbon::parse($user['dob'])->format('F j, Y') : '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">{{ t('Phone') }}</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['phone'] ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">{{ t('Alternate Phone') }}</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['alternate_phone'] ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Address</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['address'] ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">City</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['city'] ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">State</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['state'] ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Country</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['country'] ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">{{ t('ZIP Code') }}</label>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['zipcode'] ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">{{ t('Profile Picture') }}</label>
                            <p class="text-sm text-slate-900 dark:text-white truncate">{{ $user['profile_pic'] ?: '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Social Links --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-share-2 w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Social Links') }}</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('User social media profiles') }}</p>
                    </div>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Facebook</label>
                            <p class="text-sm text-slate-900 dark:text-white truncate">{{ $user['facebook_url'] ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Twitter</label>
                            <p class="text-sm text-slate-900 dark:text-white truncate">{{ $user['twitter_url'] ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">Instagram</label>
                            <p class="text-sm text-slate-900 dark:text-white truncate">{{ $user['instagram_url'] ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 mb-1 block">LinkedIn</label>
                            <p class="text-sm text-slate-900 dark:text-white truncate">{{ $user['linkedin_url'] ?: '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Roles & Permissions --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-shield w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Roles & Permissions') }}</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Access control assignments') }}</p>
                    </div>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Assigned Roles ({{ count($user['roles']) }})</h4>
                        @if(count($user['roles']) > 0)
                            <div class="space-y-2">
                                @foreach($user['roles'] as $role)
                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 px-4 py-2.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                                <i class="lucide lucide-shield w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium text-slate-900 dark:text-white">{{ $role['name'] }}</p>
                                                <p class="text-xs text-slate-400 dark:text-slate-600">{{ $role['description'] ?: 'No description' }}</p>
                                            </div>
                                        </div>
                                        <span class="text-xs text-slate-400 dark:text-slate-600">Rank: {{ $role['ranking'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-slate-400 dark:text-slate-600 py-3 text-center bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">{{ t('No roles assigned') }}</p>
                        @endif
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Permissions ({{ count($user['permissions']) }})</h4>
                        @if(count($user['permissions']) > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($user['permissions'] as $permission)
                                    <div class="flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
                                        <i class="lucide lucide-lock-open w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400"></i>
                                        <span class="text-xs font-medium text-slate-900 dark:text-white">{{ $permission['name'] }}</span>
                                        <span class="text-xs text-slate-400 dark:text-slate-600 ml-auto">{{ $permission['module'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-slate-400 dark:text-slate-600 py-3 text-center bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">{{ t('No permissions assigned') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Account Stats --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-stats w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Account Statistics') }}</h3>
                    </div>
                </div>
                <div class="p-5 space-y-4">
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ t('Account ID') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">#{{ $user['id'] }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Created</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['created_at'] ? \Carbon\Carbon::parse($user['created_at'])->format('M j, Y g:i A') : '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ t('Last Login') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['last_login'] }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ t('Failed Logins') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $user['failed_login_attempts'] }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ t('Email Verified') }}</span>
                        <span class="text-sm font-medium {{ $user['email_verified'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-600' }}">{{ $user['email_verified'] ? 'Yes' : 'No' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ t('Phone Verified') }}</span>
                        <span class="text-sm font-medium {{ $user['phone_verified'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-600' }}">{{ $user['phone_verified'] ? 'Yes' : 'No' }}</span>
                    </div>
                    @if($user['account_locked_until'])
                        <div class="flex items-center justify-between py-2 border-t border-slate-100 dark:border-slate-800 pt-4 mt-2">
                            <span class="text-sm text-rose-600 dark:text-rose-400 font-medium">🔒 Account Locked</span>
                            <span class="text-sm font-medium text-rose-600 dark:text-rose-400">{{ $user['account_locked_until'] }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Quick Actions') }}</h3>
                </div>
                <div class="p-5 space-y-3">
                    <a href="/admin/users/edit/{{ $user['id'] }}" class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-edit w-4 h-4 text-indigo-600 dark:text-indigo-400"></i> {{ t('Edit User Details') }}
                    </a>
                    <a href="/admin/users/delete/{{ $user['id'] }}" class="inline-flex w-full items-center gap-2 rounded-xl border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-trash-2 w-4 h-4"></i> {{ t('Delete User') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
