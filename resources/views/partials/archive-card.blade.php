@php
    $type = $item['type'] ?? 'content';
    $itemHref = '#';
    if ($type === 'mobile') {
        $itemHref = '/mobiles/view/'.($item['id'] ?? 0);
    } elseif ($type === 'post') {
        $slug = $item['slug'] ?? ($item['url'] ?? $item['id'] ?? '');
        $itemHref = !empty($item['id']) ? '/posts/'.$item['id'].'/'.urlencode((string) $slug) : '/posts/view/'.urlencode((string) $slug);
    } elseif ($type === 'service') {
        $itemHref = '/services/'.urlencode((string) ($item['slug'] ?? ($item['url'] ?? '')));
    } elseif ($type === 'page') {
        $itemHref = '/pages/view/'.urlencode((string) ($item['url'] ?? ($item['slug'] ?? $item['id'] ?? '')));
    } elseif ($type === 'category') {
        $itemHref = '/category/'.urlencode((string) ($item['slug'] ?? $item['id'] ?? ''));
    } elseif ($type === 'tag') {
        $itemHref = '/tag/'.urlencode((string) ($item['slug'] ?? $item['id'] ?? ''));
    }
    $image = $item['image'] ?? '';
    $categories = $item['categories'] ?? [];
    $tags = $item['tags'] ?? [];
    $createdAt = $item['created_at'] ?? null;
@endphp
<a href="{{ $itemHref }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
    @if (!empty($image))
    <img src="{{ $image }}" alt="{{ $item['title'] ?? '' }}" loading="lazy" class="h-40 w-full object-cover">
    @else
    <div class="flex h-40 w-full items-center justify-center bg-gradient-to-br from-indigo-100 to-purple-100 text-indigo-400">
        <i class="lucide lucide-{{ $type === 'mobile' ? 'smartphone' : ($type === 'service' ? 'briefcase' : 'file-text') }} h-9 w-9" aria-hidden="true"></i>
    </div>
    @endif
    <div class="p-4">
        <div class="mb-2 flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-indigo-700">{{ $type }}</span>
            @if ($createdAt)
            <span class="text-[11px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($createdAt)->format('M d, Y') }}</span>
            @endif
        </div>
        <h3 class="line-clamp-2 text-sm font-bold text-slate-900 group-hover:text-indigo-700">{{ $item['title'] ?? '' }}</h3>
        @if (!empty($item['content']))
        <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ Str::limit(strip_tags((string) $item['content']), 110) }}</p>
        @endif
        @if (!empty($categories) || !empty($tags))
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach (array_slice($categories, 0, 2) as $cat)
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $cat['name'] ?? '' }}</span>
            @endforeach
            @foreach (array_slice($tags, 0, 2) as $tg)
            <span class="rounded-full bg-purple-50 px-2 py-0.5 text-[10px] font-semibold text-purple-600">#{{ $tg['name'] ?? '' }}</span>
            @endforeach
        </div>
        @endif
    </div>
</a>