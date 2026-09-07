@extends('layouts.app')

@section('title', 'Edit Profile — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">{{ $header_title }}</h1>
        <p class="mt-0.5 text-sm text-slate-500">Keep your account information up to date</p>
    </div>

    @if (session('status'))
    <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700" role="status">
        <p class="flex items-center gap-2"><i class="lucide lucide-check-circle h-4 w-4"></i>{{ session('status') }}</p>
    </div>
    @endif

    @if ($errors->any())
    <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
        <p class="flex items-center gap-2"><i class="lucide lucide-alert-circle h-4 w-4"></i>{{ $errors->first() }}</p>
    </div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data"
          class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
          x-data="{ preview: null }">
        @csrf

        {{-- Profile picture --}}
        <div class="flex items-center gap-4">
            <template x-if="!preview">
                @if (!empty($user->profile_pic))
                    <img src="{{ asset($user->profile_pic) }}" alt="Current picture" class="h-16 w-16 rounded-full border border-slate-200 object-cover">
                @else
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-xl font-black text-white">
                        {{ mb_strtoupper(mb_substr($user->username ?? 'U', 0, 1)) }}
                    </span>
                @endif
            </template>
            <img x-show="preview" :src="preview" alt="Preview" class="h-16 w-16 rounded-full border border-slate-200 object-cover" x-cloak>
            <div>
                <label for="profile_pic" class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:border-indigo-300 hover:text-indigo-700">
                    <i class="lucide lucide-image h-4 w-4"></i> Change photo
                </label>
                <input type="file" id="profile_pic" name="profile_pic" accept="image/png,image/jpeg,image/webp" class="hidden"
                       x-on:change="preview = URL.createObjectURL($event.target.files[0])">
                <p class="mt-1 text-xs text-slate-400">JPG, PNG or WebP, max 2 MB</p>
            </div>
        </div>

        {{-- Account --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-bold text-slate-900">Account</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="username" class="mb-1 block text-sm font-semibold text-slate-700">Username <span class="text-red-500">*</span></label>
                    <input type="text" id="username" name="username" value="{{ old('username', $user->username) }}" required
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
                <div>
                    <label for="email" class="mb-1 block text-sm font-semibold text-slate-700">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
            </div>
        </fieldset>

        {{-- Personal --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-bold text-slate-900">Personal</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="first_name" class="mb-1 block text-sm font-semibold text-slate-700">First name</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
                <div>
                    <label for="last_name" class="mb-1 block text-sm font-semibold text-slate-700">Last name</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
                <div>
                    <label for="gender" class="mb-1 block text-sm font-semibold text-slate-700">Gender</label>
                    <select id="gender" name="gender"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                        <option value="">— Select —</option>
                        @foreach (['male', 'female', 'other'] as $g)
                            <option value="{{ $g }}" @selected(old('gender', $user->gender) === $g)>{{ ucfirst($g) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="dob" class="mb-1 block text-sm font-semibold text-slate-700">Date of birth</label>
                    <input type="date" id="dob" name="dob" value="{{ old('dob', $user->dob ? \Carbon\Carbon::parse($user->dob)->format('Y-m-d') : '') }}" max="{{ now()->toDateString() }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
            </div>
        </fieldset>

        {{-- Contact --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-bold text-slate-900">Contact</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="phone" class="mb-1 block text-sm font-semibold text-slate-700">Phone</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
                <div>
                    <label for="alternate_phone" class="mb-1 block text-sm font-semibold text-slate-700">Alternate phone</label>
                    <input type="tel" id="alternate_phone" name="alternate_phone" value="{{ old('alternate_phone', $user->alternate_phone) }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
            </div>
            <div>
                <label for="address" class="mb-1 block text-sm font-semibold text-slate-700">Address</label>
                <textarea id="address" name="address" rows="2"
                          class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">{{ old('address', $user->address) }}</textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-4">
                <div>
                    <label for="city" class="mb-1 block text-sm font-semibold text-slate-700">City</label>
                    <input type="text" id="city" name="city" value="{{ old('city', $user->city) }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
                <div>
                    <label for="state" class="mb-1 block text-sm font-semibold text-slate-700">State</label>
                    <input type="text" id="state" name="state" value="{{ old('state', $user->state) }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
                <div>
                    <label for="country" class="mb-1 block text-sm font-semibold text-slate-700">Country</label>
                    <input type="text" id="country" name="country" value="{{ old('country', $user->country) }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
                <div>
                    <label for="zipcode" class="mb-1 block text-sm font-semibold text-slate-700">Zipcode</label>
                    <input type="text" id="zipcode" name="zipcode" value="{{ old('zipcode', $user->zipcode) }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
            </div>
        </fieldset>

        {{-- Social --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-bold text-slate-900">Social profiles</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    'facebook_url' => ['Facebook', 'facebook'],
                    'twitter_url' => ['Twitter / X', 'twitter'],
                    'instagram_url' => ['Instagram', 'instagram'],
                    'linkedin_url' => ['LinkedIn', 'linkedin'],
                ] as $field => [$label, $icon])
                <div>
                    <label for="{{ $field }}" class="mb-1 flex items-center gap-1.5 block text-sm font-semibold text-slate-700">
                        <i class="lucide lucide-{{ $icon }} h-4 w-4 text-slate-400"></i> {{ $label }}
                    </label>
                    <input type="url" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $user->{$field}) }}" placeholder="https://..."
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                </div>
                @endforeach
            </div>
        </fieldset>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="/profile" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</a>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 active:scale-[0.99]">
                <i class="lucide lucide-save h-4 w-4"></i> Save changes
            </button>
        </div>
    </form>
</div>
@endsection
