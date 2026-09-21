@extends('admin.layout')

@section('title', 'AI Providers — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-amber-900 to-orange-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(245,158,11,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-settings-2 w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('AI System') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('AI Providers') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Add OpenRouter or any OpenAI-compatible endpoint') }}</p>
            </div>
        </div>
        <a href="/admin/aisystem" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back') }}
        </a>
    </div>
</div>

<div class="max-w-6xl grid grid-cols-1 gap-6 lg:grid-cols-5">
    <div class="lg:col-span-3 space-y-4">
        @forelse ($providers as $provider)
            <div class="overflow-hidden rounded-2xl border {{ ($provider['is_default'] ?? false) ? 'border-indigo-300 dark:border-indigo-700' : 'border-slate-200 dark:border-slate-800' }} bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-5 py-4 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $provider['name'] }}</h3>
                            <span class="rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-600 dark:text-slate-300">{{ $drivers[$provider['driver']] ?? $provider['driver'] }}</span>
                            @if ($provider['is_default'] ?? false)
                                <span class="rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 text-[10px] font-semibold uppercase text-indigo-700 dark:text-indigo-300">{{ t('default') }}</span>
                            @endif
                            @if (($provider['enabled'] ?? true) === false)
                                <span class="rounded-full bg-amber-50 dark:bg-amber-900/30 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-700 dark:text-amber-300">{{ t('disabled') }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 break-all">
                            {{ $provider['base_url'] }} · {{ $provider['model'] ?: t('no model set') }} · {{ $provider['api_key'] ?: t('no key') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="/admin/aisystem/providers?edit={{ $provider['id'] }}" class="rounded-lg border border-slate-200 dark:border-slate-700 px-2.5 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">{{ t('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.aisystem.providers.test', $provider['id']) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-700 px-2.5 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">{{ t('Test') }}</button>
                        </form>
                        @if (!($provider['is_default'] ?? false))
                            <form method="POST" action="{{ route('admin.aisystem.providers.default', $provider['id']) }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-indigo-200 dark:border-indigo-800 px-2.5 py-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20">{{ t('Set default') }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.aisystem.providers.delete', $provider['id']) }}" onsubmit="return confirm('Delete this provider?')">
                            @csrf
                            <button type="submit" class="rounded-lg border border-red-200 dark:border-red-900 px-2.5 py-1.5 text-xs font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">{{ t('Delete') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm px-6 py-12 text-center text-slate-400 dark:text-slate-600">
                <i class="lucide lucide-plug w-8 h-8 mx-auto mb-2 opacity-50"></i>
                <p class="text-sm font-medium">{{ t('No AI providers yet') }}</p>
                <p class="text-xs mt-1">{{ t('Add one on the right to enable AI enrichment and future AI features.') }}</p>
            </div>
        @endforelse
    </div>

    <div class="lg:col-span-2">
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                    {{ $edit ? t('Edit provider') : t('Add provider') }}
                </h3>
            </div>
            <form method="POST" action="{{ $edit ? route('admin.aisystem.providers.update', $edit['id']) : route('admin.aisystem.providers.store') }}" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">{{ t('Name') }}</label>
                    <input type="text" name="name" required value="{{ old('name', $edit['name'] ?? '') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">{{ t('Driver') }}</label>
                    <select name="driver" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                        @foreach ($drivers as $value => $label)
                            <option value="{{ $value }}" @selected(old('driver', $edit['driver'] ?? 'openrouter') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">{{ t('Base URL') }}</label>
                    <input type="text" name="base_url" value="{{ old('base_url', $edit['base_url'] ?? 'https://openrouter.ai/api/v1') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">{{ t('Model') }}</label>
                    <input type="text" name="model" value="{{ old('model', $edit['model'] ?? '') }}" placeholder="openai/gpt-4o-mini" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">{{ t('API Key') }}</label>
                    <input type="password" name="api_key" autocomplete="new-password" placeholder="{{ $edit ? t('Leave blank to keep current key') : 'sk-...' }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">{{ t('Stored encrypted with the application key.') }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">{{ t('Temperature') }}</label>
                        <input type="number" step="0.1" min="0" max="2" name="temperature" value="{{ old('temperature', $edit['temperature'] ?? 0.3) }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">{{ t('Max tokens') }}</label>
                        <input type="number" min="1" max="32768" name="max_tokens" value="{{ old('max_tokens', $edit['max_tokens'] ?? 1024) }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">{{ t('Extra headers (JSON)') }}</label>
                    <textarea name="headers" rows="2" placeholder='{"X-Custom": "value"}' class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-mono text-slate-700 dark:text-slate-200">{{ old('headers', $edit && !empty($edit['headers']) ? json_encode($edit['headers']) : '') }}</textarea>
                </div>
                <div class="flex items-center gap-5">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="enabled" value="1" class="rounded border-slate-300 dark:border-slate-600" @checked(old('enabled', $edit['enabled'] ?? true))>
                        {{ t('Enabled') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="is_default" value="1" class="rounded border-slate-300 dark:border-slate-600" @checked(old('is_default', $edit['is_default'] ?? false))>
                        {{ t('Default') }}
                    </label>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-amber-500/20">
                        <i class="lucide lucide-save w-4 h-4"></i> {{ $edit ? t('Save changes') : t('Add provider') }}
                    </button>
                    @if ($edit)
                        <a href="/admin/aisystem/providers" class="rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-600 dark:text-slate-300">{{ t('Cancel') }}</a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
