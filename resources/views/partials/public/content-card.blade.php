@php
    $itemType = $item['type'] ?? 'content';
    $itemTitle = $item['title'] ?? 'Untitled';

    // Determine href (mirrors legacy content-card.twig link logic)
    switch ($itemType) {
        case 'mobile':
            $itemHref = '/mobiles/view/' . ($item['id'] ?? 0);
            break;
        case 'post':
            $itemSlug = $item['slug'] ?? ($item['url'] ?? ($item['id'] ?? ''));
            $itemHref = $item['id']
                ? '/posts/' . $item['id'] . '/' . Str::slug($itemSlug)
                : '/posts/view/' . Str::slug($itemSlug);
            break;
        case 'service':
            $itemSlug = ($item['slug'] ?? ($item['url'] ?? ($item['id'] ?? '')));
            if (is_array($itemSlug)) { $itemSlug = reset($itemSlug); }
            $itemSlug = trim($itemSlug ?? '');
            if (str_contains($itemSlug, '/')) {
                $parts = explode('/', $itemSlug);
                $itemSlug = end($parts);
            }
            $itemHref = '/services/view/' . $itemSlug;
            break;
        case 'category':
            $itemHref = '/category/' . ($item['slug'] ?? ($item['id'] ?? 0));
            break;
        case 'tag':
            $itemHref = '/tag/' . ($item['slug'] ?? ($item['id'] ?? 0));
            break;
        default:
            $itemHref = '/pages/view/' . ($item['url'] ?? ($item['slug'] ?? ($item['id'] ?? 0)));
            break;
    }

    // Resolve image src
    $imageSrc = null;
    if (!empty($item['images']) && count($item['images']) > 0) {
        $imageSrc = reset($item['images']);
        if (is_array($imageSrc)) {
            $imageSrc = $imageSrc['url'] ?? ($imageSrc['path'] ?? reset($imageSrc));
        }
    } elseif (!empty($item['image'])) {
        $imageSrc = $item['image'];
    }

    $images = [];
    if (!empty($item['images']) && count($item['images']) > 0) {
        $images = $item['images'];
    }
@endphp

<article class="relative flex h-full flex-col overflow-hidden rounded-2xl bg-white shadow-[0_2px_16px_rgba(0,0,0,0.06)] ring-1 ring-black/5 transition-all duration-500 hover:-translate-y-1.5 hover:shadow-[0_20px_48px_rgba(99,102,241,0.18)] hover:ring-indigo-300/60"
         role="article"
         aria-label="Content item: {{ $itemTitle }}">

    {{-- Image Zone --}}
    <div class="relative overflow-hidden bg-slate-100">
        {{-- Hover scrim --}}
        <div class="absolute inset-0 z-10 bg-gradient-to-t from-black/60 via-black/10 to-transparent opacity-0 transition-opacity duration-500 group-hover/card:opacity-100 pointer-events-none"></div>

        {{-- Carousel (lazy port — single image + prev/next if multiple) --}}
        @if(count($images) > 0)
            <div id="carousel{{ $index ?? 'card' }}" class="relative" data-brox-ride="carousel" aria-label="Image gallery for {{ $itemTitle }}">
                @foreach($images as $img)
                    <div class="{{ $loop->first ? 'block' : 'hidden' }}">
                        <div class="aspect-[16/10] overflow-hidden">
                            <img src="{{ is_array($img) ? ($img['url'] ?? ($img['path'] ?? reset($img))) : $img }}"
                                 class="h-full w-full object-cover transition-transform duration-700 group-hover/card:scale-105"
                                 alt="{{ $itemTitle }} – Image {{ $loop->index + 1 }}"
                                 loading="lazy" decoding="async">
                        </div>
                    </div>
                @endforeach

                @if(count($images) > 1)
                    <button class="absolute left-3 top-1/2 z-20 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-white active:scale-95 opacity-0 group-hover/card:opacity-100"
                            type="button" data-brox-target="#carousel{{ $index ?? 'card' }}" data-brox-slide="prev" aria-label="{{ t('Previous image') }}">
                        <i class="lucide lucide-chevron-left h-4 w-4" aria-hidden="true"></i>
                    </button>
                    <button class="absolute right-3 top-1/2 z-20 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-white active:scale-95 opacity-0 group-hover/card:opacity-100"
                            type="button" data-brox-target="#carousel{{ $index ?? 'card' }}" data-brox-slide="next" aria-label="{{ t('Next image') }}">
                        <i class="lucide lucide-chevron-right h-4 w-4" aria-hidden="true"></i>
                    </button>
                @endif
            </div>

        @elseif($imageSrc)
            <div class="aspect-[16/10] overflow-hidden">
                <img src="{{ $imageSrc }}"
                     class="h-full w-full object-cover transition-transform duration-700 group-hover/card:scale-105"
                     alt="Featured image for {{ $itemTitle }}"
                     loading="lazy" decoding="async">
            </div>

        @else
            <div class="flex aspect-[16/10] items-center justify-center bg-gradient-to-br from-slate-100 via-indigo-50/40 to-purple-50/40">
                <div class="text-center">
                    <div class="mx-auto mb-2 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/60 shadow-sm ring-1 ring-black/5">
                        <i class="lucide lucide-image-off h-7 w-7 text-slate-300" aria-hidden="true"></i>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400">{{ t('No image') }}</p>
                </div>
            </div>
        @endif

        {{-- Top-left badges --}}
        <div class="absolute left-3 top-3 z-20 flex flex-wrap gap-1.5">
            @if($itemType === 'service' && isset($item['is_premium']))
                @if($item['is_premium'] == 1)
                    <span class="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-amber-400 to-orange-500 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow-md">
                        <i class="lucide lucide-crown w-2.5 h-2.5"></i>Premium
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700 shadow-md ring-1 ring-emerald-200/60">
                        <i class="lucide lucide-gift w-2.5 h-2.5"></i>Free
                    </span>
                @endif
            @elseif($itemType === 'mobile' && isset($item['is_official']))
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide shadow-md {{ $item['is_official'] ? 'bg-emerald-500 text-white' : 'bg-white/90 text-slate-600 ring-1 ring-black/10' }}">
                    <i class="lucide lucide-{{ $item['is_official'] ? 'badge-check' : 'help-circle' }} w-2.5 h-2.5"></i>
                    {{ $item['is_official'] ? 'Official' : 'Unofficial' }}
                </span>
            @endif
        </div>

        {{-- Top-right status badge --}}
        @if($itemType === 'service' && isset($item['status']))
            <div class="absolute right-3 top-3 z-20">
                @php $s = strtolower($item['status']); @endphp
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow-md {{ $s === 'active' ? 'bg-emerald-500' : ($s === 'inactive' ? 'bg-red-500' : 'bg-amber-500') }}">
                    <span class="h-1.5 w-1.5 rounded-full bg-white/70 animate-pulse"></span>
                    {{ ucfirst($item['status']) }}
                </span>
            </div>
        @endif

        {{-- Bottom category strip (visible on hover) --}}
        <div class="absolute bottom-3 left-3 z-20 flex flex-wrap gap-1.5 translate-y-2 opacity-0 transition-all duration-300 group-hover/card:translate-y-0 group-hover/card:opacity-100">
            @if(isset($item['categories']) && count($item['categories']) > 0)
                @php $primaryCategory = reset($item['categories']); @endphp
                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-600 px-2.5 py-1 text-[10px] font-bold text-white shadow-md">
                    <i class="lucide lucide-tag w-2.5 h-2.5"></i>{{ $primaryCategory['name'] ?? $primaryCategory ?? '' }}
                </span>
                @if(count($item['categories']) > 1)
                    <span class="inline-flex items-center rounded-full bg-white/90 px-2 py-1 text-[10px] font-bold text-slate-700 shadow-md">
                        +{{ count($item['categories']) - 1 }}
                    </span>
                @endif
            @else
                <span class="inline-flex items-center gap-1 rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-bold text-slate-700 shadow-md">
                    <i class="lucide lucide-folder w-2.5 h-2.5"></i>{{ ucfirst($itemType) }}
                </span>
            @endif
        </div>
    </div>

    {{-- Body --}}
    <div class="flex flex-1 flex-col gap-3 p-4">
        {{-- Type label --}}
        <div class="flex items-center gap-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
            <span class="text-[9px] font-black uppercase tracking-widest text-indigo-500">{{ $itemType }}</span>
            @if(isset($item['categories']) && count($item['categories']) > 0)
                <span class="text-[9px] text-slate-300">·</span>
                <span class="text-[9px] font-semibold uppercase tracking-widest text-slate-400 truncate">
                    {{ reset($item['categories'])['name'] ?? reset($item['categories']) ?? '' }}
                </span>
            @endif
        </div>

        {{-- Title --}}
        <h3 class="text-sm font-bold text-slate-900 group-hover:text-indigo-700 line-clamp-2">
            <a href="{{ $itemHref }}" class="hover:text-indigo-700">{{ $itemTitle }}</a>
        </h3>

        {{-- Excerpt --}}
        @if(!empty($item['excerpt']) || !empty($item['content']))
            <p class="text-xs leading-5 text-slate-500 line-clamp-2">
                {{ strip_tags((string) ($item['excerpt'] ?? $item['content'])) }}
            </p>
        @endif

        {{-- Meta --}}
        <div class="flex items-center gap-3 text-[11px] text-slate-400">
            @if(!empty($item['published_at']) || !empty($item['created_at']))
                <span>{{ \Carbon\Carbon::parse($item['published_at'] ?? $item['created_at'] ?? now())->format('M d, Y') }}</span>
            @endif
            @if(isset($item['author']))
                <span>by {{ $item['author'] }}</span>
            @endif
            <div class="flex items-center gap-1">
                <i class="lucide lucide-eye w-3 h-3"></i>
                <span>{{ $item['views'] ?? 0 }}</span>
            </div>
        </div>
    </div>
</article>
