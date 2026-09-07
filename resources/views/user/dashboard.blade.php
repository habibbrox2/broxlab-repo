@extends('layouts.app')

@section('title', 'My Dashboard — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

    {{-- Profile header --}}
    <div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-900 to-violet-900 p-6 text-white shadow-2xl shadow-indigo-900/20 sm:p-8">
        <div class="pointer-events-none absolute inset-0 opacity-10" aria-hidden="true">
            <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/20 blur-3xl"></div>
            <div class="absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-violet-300/20 blur-3xl"></div>
        </div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="relative shrink-0">
                    @if (!empty($user->profile_pic))
                        <img src="{{ asset($user->profile_pic) }}" alt="Profile"
                             class="h-16 w-16 rounded-full border-2 border-white/20 object-cover shadow-lg ring-2 ring-white/10 sm:h-20 sm:w-20" width="80" height="80">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-full border-2 border-white/20 bg-indigo-600 text-2xl font-black shadow-lg sm:h-20 sm:w-20">
                            {{ mb_strtoupper(mb_substr($user->username ?? 'U', 0, 1)) }}
                        </span>
                    @endif
                    <div class="absolute -bottom-0.5 -right-0.5 flex h-5 w-5 items-center justify-center rounded-full border-2 border-slate-900 bg-emerald-500 shadow-sm" aria-label="Online">
                        <i class="lucide lucide-check h-2.5 w-2.5 text-white"></i>
                    </div>
                </div>
                <div>
                    <h1 class="text-xl font-black tracking-tight sm:text-2xl">Hello, {{ $display_name }}</h1>
                    <p class="mt-1 text-sm text-indigo-200/80">Track your applications and updates</p>
                    @if ($user->email)
                        <p class="mt-0.5 text-xs text-indigo-300/60">{{ $user->email }}</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="/cv-builder" class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2 text-xs font-semibold shadow-sm backdrop-blur transition-all duration-200 hover:-translate-y-0.5 hover:bg-white/20 active:scale-[0.97]">
                    <i class="lucide lucide-file-text h-4 w-4"></i> My CVs
                </a>
                <a href="/user/settings" class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2 text-xs font-semibold shadow-sm backdrop-blur transition-all duration-200 hover:-translate-y-0.5 hover:bg-white/20 active:scale-[0.97]">
                    <i class="lucide lucide-settings h-4 w-4"></i> Settings
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2 text-xs font-semibold shadow-sm backdrop-blur transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-400/30 active:scale-[0.97]">
                        <i class="lucide lucide-log-out h-4 w-4"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Profile completeness --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-2 flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                <i class="lucide lucide-user-check h-4 w-4 text-indigo-600"></i> Profile Completeness
            </h2>
            <span class="text-sm font-black {{ $profile['completeness'] >= 80 ? 'text-emerald-600' : 'text-indigo-600' }}">{{ $profile['completeness'] }}%</span>
        </div>
        <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-all duration-500" style="width: {{ $profile['completeness'] }}%"></div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            @if ($profile['needs_photo'])
                <a href="/profile/edit" class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                    <i class="lucide lucide-image h-3.5 w-3.5"></i> Add a photo
                </a>
            @endif
            @if ($profile['needs_phone'])
                <a href="/profile/edit" class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                    <i class="lucide lucide-phone h-3.5 w-3.5"></i> Add a phone number
                </a>
            @endif
            @if ($profile['completeness'] >= 100)
                <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    <i class="lucide lucide-check-circle h-3.5 w-3.5"></i> Complete
                </span>
            @endif
        </div>
    </div>

    {{-- Stats --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ([
            ['label' => 'Total', 'value' => $mystats['total'], 'icon' => 'layers', 'color' => 'indigo'],
            ['label' => 'Pending', 'value' => $mystats['pending'], 'icon' => 'clock', 'color' => 'amber'],
            ['label' => 'Approved', 'value' => $mystats['approved'], 'icon' => 'check-circle', 'color' => 'emerald'],
            ['label' => 'Rejected', 'value' => $mystats['rejected'], 'icon' => 'x-circle', 'color' => 'rose'],
            ['label' => 'My CVs', 'value' => $mystats['cvs'], 'icon' => 'file-text', 'color' => 'violet'],
        ] as $stat)
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex items-center justify-between">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-{{ $stat['color'] }}-50 text-{{ $stat['color'] }}-600">
                    <i class="lucide lucide-{{ $stat['icon'] }} h-4.5 w-4.5"></i>
                </span>
                <span class="text-2xl font-black text-slate-900">{{ (int) $stat['value'] }}</span>
            </div>
            <p class="mt-2 text-xs font-semibold text-slate-500">{{ $stat['label'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Notices --}}
        <div class="lg:col-span-2">
            <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900">
                    <i class="lucide lucide-megaphone h-4 w-4 text-sky-600"></i> Notices & Announcements
                </h2>
                @if (empty($notices))
                    <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No announcements right now.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($notices as $notice)
                        <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-sm font-bold text-slate-800">{{ $notice['title'] }}</h3>
                                <span class="shrink-0 text-xs text-slate-400">{{ \Carbon\Carbon::parse($notice['created_at'])->diffForHumans() }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ $notice['message'] }}</p>
                            @if (!empty($notice['action_url']))
                                <a href="{{ $notice['action_url'] }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                                    Details <i class="lucide lucide-arrow-right h-3 w-3"></i>
                                </a>
                            @endif
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Recent activity --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900">
                    <i class="lucide lucide-activity h-4 w-4 text-indigo-600"></i> Recent Activity
                </h2>
                @if (empty($recent_activity))
                    <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Nothing here yet.</p>
                @else
                    <ol class="relative space-y-3 border-l border-slate-200 pl-4">
                        @foreach ($recent_activity as $item)
                        <li class="relative">
                            <span class="absolute -left-[22px] flex h-4 w-4 items-center justify-center rounded-full bg-{{ $item['color'] }}-100 ring-2 ring-white">
                                <i class="lucide lucide-{{ $item['icon'] }} h-2.5 w-2.5 text-{{ $item['color'] }}-600"></i>
                            </span>
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">
                                        @if ($item['url'])<a href="{{ $item['url'] }}" class="hover:text-indigo-600">{{ $item['title'] }}</a>
                                        @else{{ $item['title'] }}@endif
                                    </p>
                                    <p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($item['description'], 90) }}</p>
                                </div>
                                <span class="shrink-0 text-xs text-slate-400">{{ \Carbon\Carbon::parse($item['time'])->diffForHumans() }}</span>
                            </div>
                        </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>

        {{-- Side: roles + quick links --}}
        <div class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900">
                    <i class="lucide lucide-shield h-4 w-4 text-emerald-600"></i> Roles
                </h2>
                <div class="flex flex-wrap gap-2">
                    @forelse ($user_roles as $role)
                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-slate-700">{{ $role['name'] }}</span>
                    @empty
                        <span class="text-sm text-slate-500">No roles assigned</span>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900">
                    <i class="lucide lucide-link h-4 w-4 text-indigo-600"></i> Quick Links
                </h2>
                <div class="space-y-1.5">
                    <a href="/profile" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i class="lucide lucide-user h-4 w-4 text-slate-400"></i> View profile
                    </a>
                    <a href="/profile/edit" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i class="lucide lucide-pencil h-4 w-4 text-slate-400"></i> Edit profile
                    </a>
                    <a href="/user/notifications" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i class="lucide lucide-bell h-4 w-4 text-slate-400"></i> Notifications
                    </a>
                    <a href="/user/settings" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i class="lucide lucide-settings h-4 w-4 text-slate-400"></i> Account settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
