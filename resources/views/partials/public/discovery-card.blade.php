@php
    // Port of legacy partials/discovery-card.twig — feed card for the home
    // discovery dashboard. $item is a unified-feed row (mobile|post|page).
    $itemType = $item['type'] ?? 'content';
    $itemTitle = $item['title'] ?? 'Untitled';

    // Href logic mirrors the legacy twig exactly.
    switch ($itemType) {
        case 'mobile':
            $itemHref = '/mobiles/view/'.($item['id'] ?? 0);
            break;
        case 'post':
            $itemSlug = $item['slug'] ?? ($item['url'] ?? ($item['id'] ?? ''));
            $itemHref = ! empty($item['id'])
                ? '/posts/'.($item['id']).'/'.rawurlencode((string) $itemSlug)
                : '/posts/view/'.rawurlencode((string) $itemSlug);
            break;
        case 'service':
            $itemHref = '/services/view/'.($item['slug'] ?? ($item['id'] ?? ''));
            break;
        case 'page':
            $itemHref = '/pages/view/'.($item['url'] ?? ($item['slug'] ?? ($item['id'] ?? '')));
            break;
        default:
            $itemHref = '/pages/view/'.($item['url'] ?? ($item['slug'] ?? ($item['id'] ?? '')));
            break;
    }

    // Image resolution: images[] first, then image, unwrap arrays.
    $imageSrc = null;
    if (! empty($item['images']) && is_array($item['images'])) {
        $imageSrc = reset($item['images']);
    } elseif (! empty($item['image'])) {
        $imageSrc = $item['image'];
    }
    if (is_array($imageSrc)) {
        $imageSrc = $imageSrc['url'] ?? ($imageSrc['path'] ?? (function ($arr) {
            $v = reset($arr);

            return is_string($v) ? $v : null;
        })($imageSrc));
    }

    $summarySource = (string) ($item['subtitle'] ?? ($item['description'] ?? ''));
    $readingTime = max((int) round(str_word_count(strip_tags($summarySource)) / 200), 1);
    $firstCategory = $item['categories'][0] ?? '';
    $categoryName = is_array($firstCategory) ? ($firstCategory['name'] ?? '') : (string) $firstCategory;
    if ($categoryName === '') {
        $categoryName = ucfirst($itemType);
    }
    $published = $item['published_at'] ?? ($item['created_at'] ?? '');
@endphp

<article class="discovery-card" role="article" aria-label="{{ strip_tags((string) $itemTitle) }}">
    {{-- Image section --}}
    <div class="discovery-card__image-wrap">
        @if ($imageSrc)
            <img src="{{ $imageSrc }}"
                 class="discovery-card__image"
                 alt="{{ strip_tags((string) $itemTitle) }}"
                 loading="lazy" decoding="async" width="400" height="250">
        @else
            <div class="discovery-card__image discovery-card__image--placeholder" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
            </div>
        @endif
        <div class="discovery-card__image-overlay" aria-hidden="true"></div>

        <span class="discovery-card__badge">{{ $categoryName }}</span>

        {{-- Action buttons (bookmark/share/copy — wired by feed-discovery.js) --}}
        <div class="discovery-card__actions">
            <button type="button"
                    class="discovery-card__action-btn"
                    data-bookmark-id="{{ $item['id'] }}"
                    data-bookmark-title="{{ strip_tags((string) $itemTitle) }}"
                    data-bookmark-url="{{ $itemHref }}"
                    aria-label="{{ t('Bookmark this item') }}" title="{{ t('Bookmark') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                </svg>
            </button>
            <button type="button"
                    class="discovery-card__action-btn"
                    data-share-url="{{ $itemHref }}"
                    data-share-title="{{ strip_tags((string) $itemTitle) }}"
                    aria-label="{{ t('Share this item') }}" title="{{ t('Share') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>
            </button>
            <button type="button"
                    class="discovery-card__action-btn"
                    data-copy-url="{{ $itemHref }}"
                    aria-label="{{ t('Copy link') }}" title="{{ t('Copy link') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Body --}}
    <div class="discovery-card__body">
        <div class="discovery-card__meta">
            <span class="discovery-card__category">{{ $categoryName }}</span>
            @if ($published)
                <span class="discovery-card__meta-sep" aria-hidden="true"></span>
                <time datetime="{{ \Illuminate\Support\Carbon::parse($published)->format('Y-m-d') }}">{{ \Illuminate\Support\Carbon::parse($published)->diffForHumans() }}</time>
            @endif
        </div>

        <h3 class="discovery-card__title">
            <a href="{{ $itemHref }}">{{ $itemTitle }}</a>
        </h3>

        @if ($itemType === 'mobile' && ! empty($item['subtitle']))
            <p class="discovery-card__summary">{{ $item['subtitle'] }}</p>
        @elseif (! empty($item['subtitle']))
            <p class="discovery-card__summary">{{ \Illuminate\Support\Str::limit(trim(strip_tags((string) $item['subtitle'])), 150, '') }}...</p>
        @elseif (! empty($item['description']))
            <p class="discovery-card__summary">{{ \Illuminate\Support\Str::limit(trim(strip_tags((string) $item['description'])), 150, '') }}...</p>
        @endif

        @if (! empty($item['tags']) && is_array($item['tags']))
            <div class="discovery-card__tags" role="list" aria-label="{{ t('Tags') }}">
                @foreach (array_slice($item['tags'], 0, 4) as $tag)
                    <span class="discovery-card__tag" role="listitem">{{ is_array($tag) ? ($tag['name'] ?? '') : $tag }}</span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Footer --}}
    <div class="discovery-card__footer">
        <div class="discovery-card__footer-left">
            <span class="discovery-card__readtime">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                {{ $readingTime }} min read
            </span>
            <span class="discovery-card__stat" title="Views">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                {{ $item['views_count'] ?? 0 }}
            </span>
        </div>

        <a href="{{ $itemHref }}" class="discovery-card__cta" aria-label="Read {{ strip_tags((string) $itemTitle) }}">
            @if ($itemType === 'mobile')
                View Specs
            @elseif ($itemType === 'post')
                Read Article
            @elseif ($itemType === 'service')
                View Details
            @else
                Explore
            @endif
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
            </svg>
        </a>
    </div>
</article>
