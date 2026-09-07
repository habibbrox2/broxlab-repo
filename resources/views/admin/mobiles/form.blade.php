@extends('admin.layout')

@section('title', $title.' — '.($appSettings['site_name'] ?? 'BroxLab'))

@php
    $isCreate = empty($mobile);
    $currentStatus = $mobile['status'] ?? 'official';
@endphp

@section('content')

{{-- ── Gradient Page Header ── --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-smartphone w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Catalog</p>
                <h1 class="text-xl font-bold text-white">{{ $title }}</h1>
                <p class="text-sm text-white/60 mt-0.5 max-w-md">{{ $isCreate ? 'Add a new mobile device to your catalog with specifications, images, and pricing.' : 'Update the mobile device details, specifications, and pricing.' }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/mobiles" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> Back to Mobiles
            </a>
        </div>
    </div>
</div>

{{-- ── Main Form ── --}}
<form method="post"
      action="{{ $isCreate ? '/admin/mobiles/create' : '/admin/mobiles/edit/'.$mobile['id'] }}"
      class="space-y-6 max-w-5xl">
    @csrf

    @if (!$isCreate)
        <input type="hidden" name="id" value="{{ $mobile['id'] }}">
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.8fr)] xl:items-start">

        {{-- ── Main Column ── --}}
        <div class="space-y-6 xl:space-y-8">

            {{-- Basic Information Card --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-tag w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Basic Information</h3>
                </div>
                <div class="p-5 space-y-4">

                    {{-- Brand Name --}}
                    <div class="space-y-1.5">
                        <label for="brand_name" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                            Brand Name <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="brand_name" name="brand_name" placeholder="e.g., Samsung, iPhone, Xiaomi"
                            value="{{ $mobile['brand_name'] ?? '' }}" required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">The manufacturer or brand of the mobile device</p>
                    </div>

                    {{-- Model Name --}}
                    <div class="space-y-1.5">
                        <label for="model_name" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                            Model Name <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="model_name" name="model_name" placeholder="e.g., Galaxy S24, iPhone 15 Pro"
                            value="{{ $mobile['model_name'] ?? '' }}" required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">The specific model name or number</p>
                    </div>

                    {{-- Release Date --}}
                    <div class="space-y-1.5">
                        <label for="release_date" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                            Release Date <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                        </label>
                        <input type="date" id="release_date" name="release_date"
                            value="{{ $mobile['release_date'] ?? '' }}" required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    </div>

                </div>
            </div>

            {{-- Specifications Card --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-list-details w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Specifications</h3>
                </div>
                <div class="p-5 space-y-4">
                    <p class="text-sm text-slate-400 dark:text-slate-600">Add technical specifications for this mobile device.</p>

                    @if (!empty($specifications))
                        <div class="space-y-2">
                            @php
                                $specMap = [];
                                if (!empty($mobile_specs)) {
                                    foreach ($mobile_specs as $spec) {
                                        $specMap[$spec['spec_key']] = $spec['spec_value'];
                                    }
                                }
                            @endphp
                            @foreach ($specifications as $spec)
                                <div class="flex items-center gap-3">
                                    <label class="flex-1 text-sm text-slate-700 dark:text-slate-300">{{ $spec['spec_key'] }}</label>
                                    <input type="text"
                                        name="specifications[key][]"
                                        value="{{ $specMap[$spec['spec_key']] ?? '' }}"
                                        placeholder="Value"
                                        class="flex-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-amber-600 dark:text-amber-400">No specification keys defined yet.</p>
                    @endif

                </div>
            </div>

            {{-- Images Card --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-image w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Images</h3>
                </div>
                <div class="p-5 space-y-4">
                    <p class="text-sm text-slate-400 dark:text-slate-600">Upload images for this mobile device.</p>

                    <div class="space-y-3">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
                            Upload New Images
                        </label>
                        <input type="file" name="images[]" multiple accept="image/*"
                            class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-slate-800 dark:file:text-indigo-400 dark:file:border-slate-700 cursor-pointer">
                        <p class="text-xs text-slate-400 dark:text-slate-600">PNG, JPG, or WebP. Multiple files allowed.</p>
                    </div>

                    @if (!empty($mobile_images))
                        <div class="grid grid-cols-3 gap-3">
                            @foreach ($mobile_images as $image)
                                <div class="relative group">
                                    <img src="{{ $image['image_url'] }}" alt="Mobile image" class="w-full aspect-square object-cover rounded-xl border border-slate-200 dark:border-slate-700">
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-xl flex items-center justify-center">
                                        <label class="cursor-pointer">
                                            <input type="checkbox" name="deleted_images[]" value="{{ $image['id'] }}"
                                                class="sr-only">
                                            <i class="lucide lucide-trash-2 w-5 h-5 text-white"></i>
                                        </label>
                                    </div>
                                    <div class="absolute bottom-2 left-2 text-xs text-white bg-black/50 px-2 py-1 rounded-lg">
                                        <input type="checkbox" name="deleted_images[]" value="{{ $image['id'] }}"
                                            class="sr-only peer">
                                            <span class="peer-checked:hidden">Delete</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-slate-400 dark:text-slate-600">Check images to delete them.</p>
                    @endif

                </div>
            </div>

        </div>

        {{-- ── Sidebar Column ── --}}
        <div class="space-y-6">
            <div class="xl:sticky xl:top-20 space-y-4">

                {{-- Pricing & Status Card --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-dollar-sign w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Pricing & Status</h3>
                    </div>
                    <div class="p-5 space-y-4">

                        {{-- Official Price --}}
                        <div class="space-y-1.5">
                            <label for="official_price" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                                Official Price (৳)
                            </label>
                            <input type="number" id="official_price" name="official_price" min="0" step="0.01"
                                value="{{ $mobile['official_price'] ?? '' }}"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                        </div>

                        {{-- Unofficial Price --}}
                        <div class="space-y-1.5">
                            <label for="unofficial_price" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                                Unofficial Price (৳)
                            </label>
                            <input type="number" id="unofficial_price" name="unofficial_price" min="0" step="0.01"
                                value="{{ $mobile['unofficial_price'] ?? '' }}"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">Market/gray market price if different</p>
                        </div>

                        {{-- Status --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                                Status <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                            </label>
                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Mobile status">
                                <label class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-xl border px-3.5 py-2.5 text-xs font-semibold shadow-sm transition-all duration-200 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-600 has-[:checked]:text-white">
                                    <input type="radio" name="status" value="official" id="status-official"
                                        {{ $currentStatus === 'official' ? 'checked' : '' }} aria-label="Official" class="hidden">
                                    <i class="lucide lucide-check-circle text-xs"></i> Official
                                </label>
                                <label class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-xl border px-3.5 py-2.5 text-xs font-semibold shadow-sm transition-all duration-200 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-600 has-[:checked]:text-white">
                                    <input type="radio" name="status" value="unofficial" id="status-unofficial"
                                        {{ $currentStatus === 'unofficial' ? 'checked' : '' }} aria-label="Unofficial" class="hidden">
                                    <i class="lucide lucide-help-circle text-xs"></i> Unofficial
                                </label>
                                <label class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-xl border px-3.5 py-2.5 text-xs font-semibold shadow-sm transition-all duration-200 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-600 has-[:checked]:text-white">
                                    <input type="radio" name="status" value="both" id="status-both"
                                        {{ $currentStatus === 'both' ? 'checked' : '' }} aria-label="Both" class="hidden">
                                    <i class="lucide lucide-equals text-xs"></i> Both
                                </label>
                            </div>
                        </div>

                        {{-- is_official checkbox --}}
                        <div class="flex items-center gap-3 pt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_official" value="1"
                                    id="is_official"
                                    {{ (!empty($mobile['is_official']) && $mobile['is_official'] == 1) ? 'checked' : '' }}
                                    class="sr-only peer">
                                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-500/20 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                            <label for="is_official" class="text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                                Official product
                            </label>
                        </div>

                    </div>
                </div>

                {{-- Tags Card --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-tags w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Tags</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                            Assign Tags
                        </label>
                        @php
                            $selectedTagIds = collect($selected_tags)->map(fn ($t) => (string) ($t['id'] ?? $t))->all();
                        @endphp
                        <select multiple name="tags[]" id="tags"
                            class="block w-full min-h-[8rem] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                            @foreach ($tags as $tag)
                                <option value="{{ $tag['id'] }}" {{ in_array((string) $tag['id'], $selectedTagIds, true) ? 'selected' : '' }}>{{ $tag['name'] }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-400 dark:text-slate-600">Hold Ctrl/Cmd to select multiple tags.</p>
                    </div>
                </div>

                {{-- Quick Actions Card --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    <div class="p-5 space-y-3">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="lucide lucide-zap w-4 h-4 text-indigo-600"></i>
                            Ready to {{ $isCreate ? 'add' : 'update' }}?
                        </p>
                        <div class="space-y-2.5">
                            <button type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                                <i class="lucide lucide-save w-4 h-4"></i>
                                {{ $isCreate ? 'Add Mobile' : 'Update Mobile' }}
                            </button>
                            <a href="/admin/mobiles"
                               class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                                <i class="lucide lucide-x w-4 h-4"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</form>

@endsection
