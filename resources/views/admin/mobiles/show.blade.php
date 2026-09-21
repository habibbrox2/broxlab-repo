@extends('admin.layout')

@section('title', 'View Mobile — '.($mobile['brand_name'] ?? '').' '.($mobile['model_name'] ?? ''))

@section('content')

{{-- ── Gradient Page Header ── --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            @if (!empty($mobile['image_path']))
                <img src="{{ $mobile['image_path'] }}" alt="{{ $mobile['brand_name'] }}" class="w-14 h-14 rounded-2xl object-cover border-2 border-white/20 bg-slate-100">
            @else
                <div class="w-14 h-14 rounded-2xl bg-white/10 border border-white/10 flex items-center justify-center">
                    <i class="lucide lucide-smartphone w-6 h-6 text-white"></i>
                </div>
            @endif
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Mobile Details') }}</p>
                <h1 class="text-xl font-bold text-white">{{ $mobile['brand_name'] ?? '' }} {{ $mobile['model_name'] ?? '' }}</h1>
                <p class="text-sm text-white/60 mt-0.5">ID: {{ $mobile['id'] }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/mobiles/edit/{{ $mobile['id'] }}" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-pencil w-4 h-4"></i> {{ t('Edit') }}
            </a>
            <a href="/admin/mobiles/delete/{{ $mobile['id'] }}"
               onclick="return confirm('Are you sure you want to delete this mobile? This action cannot be undone.')"
               class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-red-600/20 px-4 py-2 text-sm font-semibold text-red-200 hover:bg-red-600/30 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-trash-2 w-4 h-4"></i> {{ t('Delete') }}
            </a>
            <a href="/admin/mobiles" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> Back
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3 xl:items-start">

    {{-- ── Main Column ── --}}
    <div class="xl:col-span-2 space-y-6">

        {{-- Stats Row --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <i class="lucide lucide-hash w-4 h-4 text-slate-400"></i>
                </div>
                <div class="px-4 py-4">
                    <p class="text-xs text-slate-400 dark:text-slate-600 mb-0.5">ID</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $mobile['id'] }}</p>
                </div>
            </div>
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <i class="lucide lucide-tag w-4 h-4 text-slate-400"></i>
                </div>
                <div class="px-4 py-4">
                    <p class="text-xs text-slate-400 dark:text-slate-600 mb-0.5">{{ t('Brand') }}</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white truncate">{{ $mobile['brand_name'] ?? '' }}</p>
                </div>
            </div>
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <i class="lucide lucide-noun-project w-4 h-4 text-slate-400"></i>
                </div>
                <div class="px-4 py-4">
                    <p class="text-xs text-slate-400 dark:text-slate-600 mb-0.5">Status</p>
                    <p class="text-lg font-bold capitalize
                        @if($mobile['status'] === 'official') text-emerald-600 dark:text-emerald-400
                        @elseif($mobile['status'] === 'unofficial') text-amber-600 dark:text-amber-400
                        @else text-slate-600 dark:text-slate-400 @endif
                    ">{{ $mobile['status'] ?? '' }}</p>
                </div>
            </div>
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <i class="lucide lucide-calendar w-4 h-4 text-slate-400"></i>
                </div>
                <div class="px-4 py-4">
                    <p class="text-xs text-slate-400 dark:text-slate-600 mb-0.5">Released</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $mobile['release_date'] ?? '' }}</p>
                </div>
            </div>
        </div>

        {{-- Images Gallery --}}
        @if (!empty($images))
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-image w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Images</h3>
                    <span class="text-xs text-slate-400 dark:text-slate-600">({{ count($images) }})</span>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach ($images as $image)
                            <div class="group relative rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700">
                                <img src="{{ $image['image_url'] }}" alt="{{ t('Mobile image') }}" class="w-full aspect-square object-cover">
                                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <i class="lucide lucide-zoom-in w-5 h-5 text-white"></i>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Specifications Table --}}
        @if (!empty($specifications))
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-list-details w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Specifications') }}</h3>
                </div>
                <div class="p-5">
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($specifications as $spec)
                            <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $spec['spec_key'] }}</span>
                                <span class="text-sm text-slate-900 dark:text-white font-semibold">{{ $spec['spec_value'] ?? '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Comments --}}
        @if (!empty($comments))
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-message-circle w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Comments') }}</h3>
                    <span class="text-xs text-slate-400 dark:text-slate-600">({{ count($comments) }})</span>
                </div>
                <div class="p-5 space-y-4">
                    @foreach ($comments as $comment)
                        <div class="flex gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0">
                                <i class="lucide lucide-user w-4 h-4 text-slate-500"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $comment['author'] ?? 'Anonymous' }}</span>
                                    @if($comment['is_admin'] ?? false)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 px-2 py-0.5 text-xs font-semibold">
                                            <i class="lucide lucide-shield-check w-3 h-3"></i> Admin
                                        </span>
                                    @endif
                                    <span class="text-xs text-slate-400 dark:text-slate-600">{{ $comment['created_at'] ?? '' }}</span>
                                </div>
                                <p class="text-sm text-slate-700 dark:text-slate-300 mt-1">{{ $comment['content_html'] ?? $comment['content'] ?? '' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

    {{-- ── Sidebar Column ── --}}
    <div class="space-y-6">
        <div class="xl:sticky xl:top-20 space-y-4">

            {{-- Quick Info Card --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-information w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Quick Info') }}</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400 dark:text-slate-600">{{ t('Model Name') }}</span>
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $mobile['model_name'] ?? '' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400 dark:text-slate-600">{{ t('Official Price') }}</span>
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">
                                @if($mobile['official_price'] > 0)
                                    ৳{{ number_format($mobile['official_price'], 0) }}
                                @else
                                    <span class="text-slate-400">{{ t('Not set') }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400 dark:text-slate-600">{{ t('Unofficial Price') }}</span>
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">
                                @if($mobile['unofficial_price'] > 0)
                                    ৳{{ number_format($mobile['unofficial_price'], 0) }}
                                @else
                                    <span class="text-slate-400">{{ t('Not set') }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400 dark:text-slate-600">{{ t('Official Product') }}</span>
                            <span class="text-sm @if(!empty($mobile['is_official'])) text-emerald-600 dark:text-emerald-400 @else text-slate-400 @endif">
                                @if(!empty($mobile['is_official'])) Yes @else No @endif
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400 dark:text-slate-600">Created</span>
                            <span class="text-sm text-slate-900 dark:text-white">{{ $mobile['created_at'] ?? '' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tags Card --}}
            @if (!empty($tags))
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-tags w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Tags') }}</h3>
                    </div>
                    <div class="p-5">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($tags as $tag)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1.5 text-xs font-semibold text-indigo-700 dark:text-indigo-400">
                                    <i class="lucide lucide-hash w-3 h-3"></i> {{ $tag['name'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>

@endsection
