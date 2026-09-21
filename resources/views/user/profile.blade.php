@extends('layouts.app')

@section('title', 'Your Profile — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">{{ $header_title }}</h1>
            <p class="mt-0.5 text-sm text-slate-500">{{ t('Your account details') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="/profile/edit" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                <i class="lucide lucide-pencil h-4 w-4"></i> {{ t('Edit') }}
            </a>
            <a href="/profile/password" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-indigo-300 hover:text-indigo-700">
                <i class="lucide lucide-key h-4 w-4"></i> {{ t('Password') }}
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-4 border-b border-slate-100 bg-slate-50/70 px-6 py-5">
            @if (!empty($user->profile_pic))
                <img src="{{ asset($user->profile_pic) }}" alt="{{ t('Profile picture') }}" class="h-16 w-16 rounded-full border border-slate-200 object-cover">
            @else
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-xl font-black text-white">
                    {{ mb_strtoupper(mb_substr($user->username ?? 'U', 0, 1)) }}
                </span>
            @endif
            <div>
                <p class="text-lg font-bold text-slate-900">{{ trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->username }}</p>
                <p class="text-sm text-slate-500">{{ '@'.$user->username }} &middot; joined {{ \Carbon\Carbon::parse($user->created_at)->format('M Y') }}</p>
            </div>
        </div>

        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 sm:grid-cols-2">
            @foreach ([
                ['Email', $user->email, 'mail'],
                ['Phone', $user->phone, 'phone'],
                ['Alternate phone', $user->alternate_phone, 'phone-call'],
                ['Gender', $user->gender ? ucfirst($user->gender) : null, 'user'],
                ['Date of birth', $user->dob ? \Carbon\Carbon::parse($user->dob)->format('d M Y') : null, 'calendar'],
                ['Address', $user->address, 'map-pin'],
                ['City', $user->city, 'building'],
                ['State', $user->state, 'map'],
                ['Country', $user->country, 'globe'],
                ['Zipcode', $user->zipcode, 'hash'],
            ] as [$label, $value, $icon])
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                    <i class="lucide lucide-{{ $icon }} h-4 w-4"></i>
                </span>
                <div class="min-w-0">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                    <dd class="truncate text-sm font-medium text-slate-800">{{ $value ?? '—' }}</dd>
                </div>
            </div>
            @endforeach
            <div class="flex items-start gap-3 sm:col-span-2">
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                    <i class="lucide lucide-shield h-4 w-4"></i>
                </span>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Roles</dt>
                    <dd class="mt-1 flex flex-wrap gap-1.5">
                        @forelse ($roles as $role)
                            <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-slate-700">{{ $role['name'] }}</span>
                        @empty
                            <span class="text-sm text-slate-500">{{ t('No roles assigned') }}</span>
                        @endforelse
                    </dd>
                </div>
            </div>
        </dl>
    </div>
</div>
@endsection
