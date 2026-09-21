@extends('layouts.app')

@section('title', $appSettings['meta_title'] ?? (($appSettings['site_name'] ?? 'BroxLab').' | Official Website'))
@section('meta_description', $appSettings['meta_description'] ?? ('Welcome to '.($appSettings['site_name'] ?? 'BroxLab')))
@section('og_type', 'website')

@php
    $pageImage = $appSettings['site_logo'] ?? url('/assets/images/default-image.png');
    $pageSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $canonicalUrl ?? url()->current(),
        'url' => url()->current(),
        'name' => $appSettings['meta_title'] ?? ($appSettings['site_name'] ?? 'BroxLab'),
        'description' => $appSettings['meta_description'] ?? ('Welcome to '.($appSettings['site_name'] ?? 'BroxLab')),
        'image' => $pageImage,
        'isPartOf' => [
            '@type' => 'WebSite',
            '@id' => url('/'),
            'name' => $appSettings['site_name'] ?? 'BroxLab',
        ],
        'datePublished' => now()->toIso8601String(),
        'dateModified' => now()->toIso8601String(),
    ];
@endphp

@section('schema')
<script type="application/ld+json">
{!! json_encode($pageSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
@endsection

@section('extra_styles')
<link rel="stylesheet" href="{{ asset('/assets/css/weather.css') }}" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="{{ asset('/assets/css/weather.css') }}"></noscript>
<link rel="stylesheet" href="{{ asset('/assets/css/home.css') }}?v={{ $appSettings['asset_version'] ?? 'b'.now()->timestamp }}" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="{{ asset('/assets/css/home.css') }}?v={{ $appSettings['asset_version'] ?? 'b'.now()->timestamp }}"></noscript>
<link rel="stylesheet" href="{{ asset('/assets/css/feed-discovery.css') }}?v={{ $appSettings['asset_version'] ?? 'b'.now()->timestamp }}" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="{{ asset('/assets/css/feed-discovery.css') }}?v={{ $appSettings['asset_version'] ?? 'b'.now()->timestamp }}"></noscript>
@endsection

@section('content')

{{-- ==================== HERO SECTION — Modern SaaS Design ==================== --}}
<section class="home-hero" aria-labelledby="hero-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-12">

            {{-- Hero content --}}
            <div class="relative z-10 text-center lg:text-left">
                <div class="hero-badge">
                    <span>
                        <i class="lucide lucide-sparkles" aria-hidden="true"></i>
                        {{ t("Explore What's New") }}
                    </span>
                </div>

                <h1 id="hero-title" class="hero-title" data-i18n="Discover the Future of Mobile Technology" data-i18n-en="Discover the <span class=&quot;hero-gradient-text&quot;>Future</span> of Mobile Technology" data-i18n-bn="মোবাইল প্রযুক্তির <span class=&quot;hero-gradient-text&quot;>ভবিষ্যৎ</span> আবিষ্কার করুন">
                    {!! t('Discover the Future of Mobile Technology') !!}
                </h1>

                <p class="hero-subtitle" data-i18n="Curated latest updates, in-depth insights, and exclusive services that keep you ahead of the curve.">
                    {{ t('Curated latest updates, in-depth insights, and exclusive services that keep you ahead of the curve.') }}
                </p>

                <div class="mb-4 flex flex-col justify-center gap-3 sm:flex-row lg:justify-start">
                    <a href="#latest-updates" class="hero-cta-primary">
                        <i class="lucide lucide-rocket" aria-hidden="true"></i>
                        {{ t('Explore Now') }}
                    </a>
                    <a href="/services" class="hero-cta-secondary">
                        <i class="lucide lucide-award" aria-hidden="true"></i>
                        {{ t('Browse Services') }}
                    </a>
                </div>

                {{-- Quick stats --}}
                <div class="home-hero-stats">
                    <div class="hero-stat">
                        <div class="stat-icon">
                            <i class="lucide lucide-smartphone" aria-hidden="true"></i>
                        </div>
                        <div>
                            <div class="stat-value">1200+</div>
                            <div class="stat-label" data-i18n="Devices">{{ t('Devices') }}</div>
                        </div>
                    </div>
                    <div class="hero-stat">
                        <div class="stat-icon">
                            <i class="lucide lucide-book-open" aria-hidden="true"></i>
                        </div>
                        <div>
                            <div class="stat-value">850+</div>
                            <div class="stat-label" data-i18n="Articles">{{ t('Articles') }}</div>
                        </div>
                    </div>
                    <div class="hero-stat">
                        <div class="stat-icon">
                            <i class="lucide lucide-award" aria-hidden="true"></i>
                        </div>
                        <div>
                            <div class="stat-value">150+</div>
                            <div class="stat-label" data-i18n="Services">{{ t('Services') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Hero visual: floating glassmorphism cards --}}
            <div class="hero-visual-section">
                <div class="hero-dashboard-preview">
                    <div class="dp-header">
                        <div class="flex items-center gap-2">
                            <div class="dp-dots">
                                <span class="dp-dot red"></span>
                                <span class="dp-dot yellow"></span>
                                <span class="dp-dot green"></span>
                            </div>
                            <span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-left:0.5rem;">{{ t('Platform Overview') }}</span>
                        </div>
                    </div>
                    <div class="dp-grid">
                        <div class="dp-item">
                            <div class="dp-item-label">{{ t('Active Users') }}</div>
                            <div class="dp-item-value">2,847</div>
                            <div class="dp-item-change">+12.5%</div>
                        </div>
                        <div class="dp-item">
                            <div class="dp-item-label">{{ t('Page Views') }}</div>
                            <div class="dp-item-value">18.2K</div>
                            <div class="dp-item-change">+8.3%</div>
                        </div>
                        <div class="dp-item">
                            <div class="dp-item-label">{{ t('Avg. Session') }}</div>
                            <div class="dp-item-value">4m 32s</div>
                            <div class="dp-item-change">+5.1%</div>
                        </div>
                        <div class="dp-item">
                            <div class="dp-item-label">{{ t('Bounce Rate') }}</div>
                            <div class="dp-item-value">24.8%</div>
                            <div class="dp-item-change" style="color:#ef4444;">-2.1%</div>
                        </div>
                    </div>
                </div>

                <div class="hero-floating-card card-1" style="top:0;right:10%;">
                    <div class="fc-icon" style="background:rgba(99,102,241,0.12);color:var(--brand-indigo);">
                        <i class="lucide lucide-smartphone" style="width:20px;height:20px;"></i>
                    </div>
                    <div class="fc-title">{{ t('New Devices Added') }}</div>
                    <div class="fc-desc">{{ t('Latest smartphone models this week') }}</div>
                    <div class="fc-value">{{ t('+24 this week') }}</div>
                </div>

                <div class="hero-floating-card card-2" style="bottom:5%;left:5%;">
                    <div class="fc-icon" style="background:rgba(245,158,11,0.12);color:#d97706;">
                        <i class="lucide lucide-trending-up" style="width:20px;height:20px;"></i>
                    </div>
                    <div class="fc-title">{{ t('Trending Articles') }}</div>
                    <div class="fc-desc">{{ t('Most read tech news today') }}</div>
                    <div class="fc-value" style="color:#d97706;">{{ t('Top 5 picks') }}</div>
                </div>

                <div class="hero-floating-card card-3" style="bottom:30%;right:-5%;">
                    <div class="fc-icon" style="background:rgba(34,197,94,0.12);color:#16a34a;">
                        <i class="lucide lucide-bell-ring" style="width:20px;height:20px;"></i>
                    </div>
                    <div class="fc-title">{{ t('Live Updates') }}</div>
                    <div class="fc-desc">{{ t('Real-time notifications enabled') }}</div>
                    <div class="fc-value" style="color:#16a34a;">{{ t('Active') }}</div>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ==================== SERVICES GRID — Utility Portal Style ==================== --}}
<section class="services-dashboard-section py-8"
         aria-labelledby="services-dashboard-title"
         data-services-dashboard>
    <div class="services-section-inner">
        <div class="services-section-header">
            <h2 id="services-dashboard-title" class="services-section-heading">
                <span data-i18n="Our Services" data-i18n-en="Our Services" data-i18n-bn="আমাদের সেবাসমূহ">{{ t('Our Services') }}</span>
            </h2>
        </div>

        {{-- Skeleton loading --}}
        <div data-services-skeleton class="services-skeleton-wrap{{ count($homepage_services ?? []) > 0 ? ' hidden' : '' }}">
            <div class="services-grid">
                @for ($i = 1; $i <= 15; $i++)
                    <div class="services-skeleton-card" aria-hidden="true">
                        <div class="skeleton-icon"></div>
                        <div class="skeleton-title"></div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Actual grid --}}
        <div data-services-grid class="{{ count($homepage_services ?? []) === 0 ? 'hidden' : '' }}">
            <div class="services-grid">
                @foreach ($homepage_services ?? [] as $service)
                    @php
                        $serviceTitle = $service['name'] ?? ($service['title'] ?? 'Service');
                        $serviceSlug = $service['slug'] ?? '';
                        $serviceLink = $service['url'] ?? ($serviceSlug !== '' ? '/services/view/'.$serviceSlug : '/services');
                        $serviceIcon = $service['icon'] ?? 'sparkles';
                        $serviceImgUrl = $service['featured_thumbnail_url'] ?? ($service['featured_image_url'] ?? ($service['featured_image'] ?? null));
                    @endphp
                    <a href="{{ $serviceLink }}"
                       class="service-card"
                       aria-label="{{ $serviceTitle }}"
                       tabindex="0">
                        <div class="service-card-icon">
                            @if (! empty($serviceImgUrl))
                                <img src="{{ $serviceImgUrl }}"
                                     alt="{{ $serviceTitle }}"
                                     class="service-card-img"
                                     loading="lazy"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div class="service-card-icon-fallback" style="display:none;">
                                    <i class="lucide lucide-{{ str_replace('_', '-', $serviceIcon) }}"></i>
                                </div>
                            @elseif (! empty($serviceIcon))
                                <i class="lucide lucide-{{ str_replace('_', '-', $serviceIcon) }}"></i>
                            @else
                                <i class="lucide lucide-sparkles"></i>
                            @endif
                        </div>
                        <div class="service-card-title">{{ $serviceTitle }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- Smooth scroll transition bridge from services to calculator --}}
<div class="section-transition-bridge" aria-hidden="true">
    <div class="bridge-line"></div>
    <div class="bridge-icon">
        <i class="lucide lucide-arrow-down"></i>
    </div>
    <div class="bridge-line"></div>
</div>

{{-- ==================== DATE & TIME WIDGET ==================== --}}
<section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 home-datetime-wrap py-8">
    <div class="home-datetime-card relative overflow-hidden rounded-2xl border border-white/20 bg-[linear-gradient(135deg,rgba(255,255,255,0.95)_0%,rgba(255,255,255,0.9)_100%)] shadow-[0_20px_40px_rgba(0,0,0,0.1),0_0_0_1px_rgba(255,255,255,0.1)] backdrop-blur-[20px]">

        <div class="absolute right-0 top-0 opacity-25" style="transform: translate(20%, -20%);">
            <div class="h-[120px] w-[120px] rounded-full bg-primary blur-[40px]"></div>
        </div>
        <div class="absolute bottom-0 left-0 opacity-25" style="transform: translate(-20%, 20%);">
            <div class="h-[100px] w-[100px] rounded-full bg-emerald-500 blur-[35px]"></div>
        </div>

        <div class="relative p-4 lg:p-5" style="z-index: 2;">
            <div class="grid gap-4 lg:grid-cols-2">
                {{-- Bengali date & time --}}
                <div>
                    <div class="datetime-section rounded-2xl p-4 text-white transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_20px_40px_rgba(0,0,0,0.2)]"
                         style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                        <div class="mb-3 flex items-center">
                            <div class="datetime-icon me-3">
                                <i class="lucide lucide-calendar-days" aria-hidden="true"></i>
                            </div>
                            <h3 class="mb-0 text-lg font-bold">বাংলা তারিখ ও সময়</h3>
                        </div>
                        <div class="datetime-content">
                            <div class="mb-3">
                                <div class="mb-2 flex items-center">
                                    <i class="lucide lucide-calendar-range mr-2 opacity-75"></i>
                                    <small class="font-semibold text-white/50">বাংলা তারিখ</small>
                                </div>
                                <div id="bengali-date" class="font-bold" style="font-family: 'Noto Sans Bengali', sans-serif;">
                                    লোড হচ্ছে...
                                </div>
                            </div>
                            <div class="mb-0">
                                <div class="mb-2 flex items-center">
                                    <i class="lucide lucide-clock mr-2 opacity-75"></i>
                                    <small class="font-semibold text-white/50">বাংলা সময়</small>
                                </div>
                                <div id="bengali-time" class="font-bold font-mono" style="font-family: 'JetBrains Mono', monospace;">
                                    লোড হচ্ছে...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- English date & time --}}
                <div>
                    <div class="datetime-section rounded-2xl p-4 text-white transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_20px_40px_rgba(0,0,0,0.2)]"
                         style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); box-shadow: 0 10px 30px rgba(245, 87, 108, 0.3);">
                        <div class="mb-3 flex items-center">
                            <div class="datetime-icon me-3">
                                <i class="lucide lucide-calendar-days" aria-hidden="true"></i>
                            </div>
                            <h3 class="mb-0 text-lg font-bold">{{ t('English Date & Time') }}</h3>
                        </div>
                        <div class="datetime-content">
                            <div class="mb-3">
                                <div class="mb-2 flex items-center">
                                    <i class="lucide lucide-calendar-range mr-2 opacity-75"></i>
                                    <small class="font-semibold text-white/50">Date</small>
                                </div>
                                <div id="english-date" class="font-bold">
                                    {{ t('Loading...') }}
                                </div>
                            </div>
                            <div class="mb-0">
                                <div class="mb-2 flex items-center">
                                    <i class="lucide lucide-clock mr-2 opacity-75"></i>
                                    <small class="font-semibold text-white/50">Time</small>
                                </div>
                                <div id="english-time" class="font-bold font-mono" style="font-family: 'JetBrains Mono', monospace;">
                                    {{ t('Loading...') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Live indicator --}}
            <div class="mt-4 text-center">
                <div class="inline-flex items-center rounded-full bg-white/20 px-3 py-2 backdrop-blur-[10px]">
                    <div class="live-dot me-2 h-2 w-2 rounded-full bg-rose-500 animate-pulse"></div>
                    <small class="font-semibold text-white/75" data-i18n="Live Update">{{ t('Live Update') }}</small>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== WEATHER WIDGET SECTION ==================== --}}
@include('partials.public.weather-widget')

<style>
/* ==================== SECTION TRANSITION: Services → Calculator ==================== */
.section-transition-bridge {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 2rem 0;
  opacity: 0.6;
  transition: opacity 0.4s ease;
}

.section-transition-bridge .bridge-line {
  flex: 1;
  max-width: 120px;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--brand-indigo, #6366f1), transparent);
}

.section-transition-bridge .bridge-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--brand-indigo, #6366f1);
  color: white;
  font-size: 0.75rem;
  opacity: 0.7;
  animation: bridge-bounce 2s ease-in-out infinite;
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.section-transition-bridge:hover {
  opacity: 1;
}

.section-transition-bridge:hover .bridge-icon {
  opacity: 1;
  transform: scale(1.15);
}

@keyframes bridge-bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(4px); }
}

/* ==================== SCROLL-TRIGGERED FADE-IN ==================== */
.scroll-fade-up {
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1),
              transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  will-change: opacity, transform;
}

.scroll-fade-up.visible {
  opacity: 1;
  transform: translateY(0);
}

@keyframes home-pulse {
  0% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(1.1); }
  100% { opacity: 1; transform: scale(1); }
}

.live-dot {
  animation: home-pulse 2s infinite;
}

.hover-scale {
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.hover-scale:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 40px rgba(0,0,0,0.2) !important;
}

.datetime-section {
  position: relative;
  overflow: hidden;
}

.datetime-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 50%, rgba(255,255,255,0.1) 100%);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.datetime-section:hover::before {
  opacity: 1;
}

.datetime-icon {
  background: rgba(255,255,255,0.2);
  padding: 0.5rem;
  border-radius: 50%;
  backdrop-filter: blur(10px);
}

@media (max-width: 991.98px) {
  .datetime-section {
    margin-bottom: 1rem;
  }
}
</style>

<script>
  function updateHomeDateTime() {
    const now = new Date();

    const banglaDate = new Intl.DateTimeFormat('bn-BD', {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    }).format(now);

    const banglaTime = new Intl.DateTimeFormat('bn-BD', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
      timeZone: 'Asia/Dhaka'
    }).format(now);

    const englishDate = new Intl.DateTimeFormat('en-US', {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    }).format(now);

    const englishTime = new Intl.DateTimeFormat('en-US', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
      timeZone: 'Asia/Dhaka'
    }).format(now);

    document.getElementById('bengali-date').textContent = banglaDate;
    document.getElementById('bengali-time').textContent = banglaTime;
    document.getElementById('english-date').textContent = englishDate;
    document.getElementById('english-time').textContent = englishTime;
  }

  updateHomeDateTime();
  setInterval(updateHomeDateTime, 1000);
</script>

{{-- ==================== TOP POSTS & SERVICES CAROUSEL ==================== --}}
<section class="top-carousel-section py-8 scroll-fade-in" aria-labelledby="top-carousel-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="top-carousel-title">
                    <span data-i18n="Top Picks">{{ t('Top Picks') }}</span>
                </h2>
                <p data-i18n="Highest-rated posts and services curated for you">{{ t('Highest-rated posts and services curated for you') }}</p>
            </div>
            {{-- Tab switcher --}}
            <div class="top-carousel-tabs inline-flex gap-1 rounded-xl bg-slate-100 p-1 shadow-inner" role="tablist" aria-label="{{ t('Switch between top posts and top services') }}" x-data="topPicksTabs()">
                <button type="button"
                        :class="['top-carousel-tab rounded-lg px-4 py-2 text-sm font-semibold transition-all duration-200', activeTab === 'posts' ? 'active' : '']"
                        role="tab"
                        id="tab-posts"
                        :aria-selected="activeTab === 'posts'"
                        aria-controls="panel-posts"
                        @click="setTab('posts')">
                    <i class="lucide lucide-file-text inline-block mr-1.5" style="width:16px;height:16px" aria-hidden="true"></i>
                    {{ t('Top Posts') }}
                </button>
                <button type="button"
                        :class="['top-carousel-tab rounded-lg px-4 py-2 text-sm font-semibold transition-all duration-200', activeTab === 'services' ? 'active' : '']"
                        role="tab"
                        id="tab-services"
                        :aria-selected="activeTab === 'services'"
                        aria-controls="panel-services"
                        @click="setTab('services')">
                    <i class="lucide lucide-star inline-block mr-1.5" style="width:16px;height:16px" aria-hidden="true"></i>
                    {{ t('Top Services') }}
                </button>
            </div>
        </div>

        {{-- Top posts panel --}}
        <div id="panel-posts" class="top-carousel-panel" role="tabpanel" aria-labelledby="tab-posts" x-show="activeTab === 'posts'" x-cloak>
            @if (!empty($top_posts))
                <div class="top-carousel-track" role="list" aria-label="{{ t('Top posts') }}">
                    @foreach ($top_posts as $post)
                        <article class="top-carousel-card" role="listitem">
                            <div class="top-carousel-card__inner">
                                <div class="top-carousel-card__image-wrap">
                                    @if (!empty($post['image']))
                                        <img src="{{ $post['image'] }}" alt="{{ strip_tags((string) $post['title']) }}" loading="lazy" width="280" height="180">
                                    @else
                                        <div class="top-carousel-card__image-placeholder">
                                            <i class="lucide lucide-file-text" style="width:32px;height:32px" aria-hidden="true"></i>
                                        </div>
                                    @endif
                                    @if (($post['rating_average'] ?? 0) > 0)
                                        <span class="top-carousel-card__rating">
                                            <i class="lucide lucide-star" style="width:12px;height:12px" aria-hidden="true"></i>
                                            {{ $post['rating_average'] }}
                                        </span>
                                    @endif
                                </div>
                                <div class="top-carousel-card__body">
                                    <h3 class="top-carousel-card__title">
                                        <a href="/posts/{{ $post['id'] }}/{{ rawurlencode((string) $post['slug']) }}">{{ $post['title'] }}</a>
                                    </h3>
                                    <p class="top-carousel-card__excerpt">{{ \Illuminate\Support\Str::limit(strip_tags((string) $post['content']), 100, '') }}...</p>
                                    <div class="top-carousel-card__footer">
                                        <span class="top-carousel-card__meta">
                                            <i class="lucide lucide-star" style="width:12px;height:12px" aria-hidden="true"></i>
                                            {{ $post['rating_total'] ?? 0 }} {{ t('reviews') }}
                                        </span>
                                        <a>
                                            {{ t('Read') }}
                                            <i class="lucide lucide-arrow-right" style="width:14px;height:14px" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="top-carousel-scroll-hint">
                    <i class="lucide lucide-chevron-right" style="width:14px;height:14px" aria-hidden="true"></i>
                    {{ t('Scroll for more') }}
                    <i class="lucide lucide-chevron-right" style="width:14px;height:14px" aria-hidden="true"></i>
                </div>
            @else
                <div class="feed-state">
                    <div class="feed-state__icon">
                        <i class="lucide lucide-file-text" style="width:28px;height:28px" aria-hidden="true"></i>
                    </div>
                    <h3 data-i18n="No top posts yet">{{ t('No top posts yet') }}</h3>
                    <p data-i18n="Top posts will appear here once they receive ratings.">{{ t('Top posts will appear here once they receive ratings.') }}</p>
                </div>
            @endif
        </div>

        {{-- Top services panel --}}
        <div id="panel-services" class="top-carousel-panel" role="tabpanel" aria-labelledby="tab-services" x-show="activeTab === 'services'" x-cloak>
            @if (!empty($top_services))
                <div class="top-carousel-track" role="list" aria-label="{{ t('Top services') }}">
                    @foreach ($top_services as $svc)
                        <article class="top-carousel-card" role="listitem">
                            <div class="top-carousel-card__inner">
                                <div class="top-carousel-card__image-wrap">
                                    @if (!empty($svc['image']))
                                        <img src="{{ $svc['image'] }}" alt="{{ strip_tags((string) $svc['title']) }}" loading="lazy" width="280" height="180">
                                    @else
                                        <div class="top-carousel-card__image-placeholder" style="background: linear-gradient(135deg, #EEF2FF, #C7D2FE);">
                                            <i class="lucide lucide-star" style="width:32px;height:32px;color:#6366F1" aria-hidden="true"></i>
                                        </div>
                                    @endif
                                    <span class="top-carousel-card__status top-carousel-card__status--{{ $svc['status'] ?? 'active' }}">
                                        {{ ucfirst($svc['status'] ?? 'Active') }}
                                    </span>
                                    @if (($svc['rating_average'] ?? 0) > 0)
                                        <span class="top-carousel-card__rating">
                                            <i class="lucide lucide-star" style="width:12px;height:12px" aria-hidden="true"></i>
                                            {{ $svc['rating_average'] }}
                                        </span>
                                    @endif
                                </div>
                                <div class="top-carousel-card__body">
                                    <h3 class="top-carousel-card__title">
                                        <a href="/services/view/{{ $svc['slug'] }}">{{ $svc['title'] }}</a>
                                    </h3>
                                    <p class="top-carousel-card__excerpt">{{ \Illuminate\Support\Str::limit(strip_tags((string) $svc['description']), 100, '') }}...</p>
                                    <div class="top-carousel-card__footer">
                                        <span class="top-carousel-card__meta">
                                            <i class="lucide lucide-star" style="width:12px;height:12px" aria-hidden="true"></i>
                                            {{ $svc['rating_total'] ?? 0 }} {{ t('reviews') }}
                                        </span>
                                        <a href="/services/view/{{ $svc['slug'] }}" class="top-carousel-card__cta" aria-label="View {{ strip_tags((string) $svc['title']) }}">
                                            View
                                            <i class="lucide lucide-arrow-right" style="width:14px;height:14px" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="top-carousel-scroll-hint">
                    <i class="lucide lucide-chevron-right" style="width:14px;height:14px" aria-hidden="true"></i>
                    {{ t('Scroll for more') }}
                    <i class="lucide lucide-chevron-right" style="width:14px;height:14px" aria-hidden="true"></i>
                </div>
            @else
                <div class="feed-state">
                    <div class="feed-state__icon">
                        <i class="lucide lucide-star" style="width:28px;height:28px" aria-hidden="true"></i>
                    </div>
                    <h3 data-i18n="No services yet">{{ t('No services yet') }}</h3>
                    <p data-i18n="Top services will appear here once they receive ratings.">{{ t('Top services will appear here once they receive ratings.') }}</p>
                </div>
            @endif
        </div>
    </div>
</section>

{{-- ==================== LATEST UPDATES — MODERN DISCOVERY DASHBOARD ==================== --}}
<section class="discovery-feed-section py-8" id="latest-updates" aria-labelledby="discovery-feed-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-5">
            <h2 id="discovery-feed-title">
                <span data-i18n="Content Discovery">{{ t('Content Discovery') }}</span>
            </h2>
            <p data-i18n="Explore our latest posts, articles, and services">{{ t('Explore our latest posts, articles, and services') }}</p>
        </div>

        <div id="discovery-feed" class="discovery-feed-container">
            <div class="discovery-toolbar" role="toolbar" aria-label="{{ t('Feed controls') }}" x-data="feedToolbar()">
                {{-- Row 1: search + filters --}}
                <div class="toolbar-row">
                    <div class="toolbar-search-wrap">
                        <i class="lucide lucide-search search-icon" style="width:18px;height:18px" aria-hidden="true"></i>
                        <input type="search" x-model="searchQuery" placeholder="{{ t('Search content...') }}" aria-label="{{ t('Search content') }}" autocomplete="off">
                        <button type="button" class="search-clear-btn" aria-label="{{ t('Clear search') }}" x-show="searchQuery" @click="searchQuery = ''">
                            <i class="lucide lucide-x" style="width:16px;height:16px" aria-hidden="true"></i>
                        </button>
                    </div>

                    <select class="toolbar-select" aria-label="{{ t('Filter by category') }}">
                        <option data-i18n="All Categories">{{ t('All Categories') }}</option>
                        @foreach ($feed_categories ?? [] as $cat)
                            <option value="{{ $cat['slug'] }}">{{ $cat['name'] }}</option>
                        @endforeach
                    </select>

                    <select class="toolbar-select" aria-label="{{ t('Sort content') }}">
                        <option data-i18n="Newest">{{ t('Newest') }}</option>
                        <option data-i18n="Oldest">{{ t('Oldest') }}</option>
                        <option data-i18n="Most Viewed">{{ t('Most Viewed') }}</option>
                        <option data-i18n="Trending">{{ t('Trending') }}</option>
                    </select>
                </div>

                {{-- Row 2: actions --}}
                <div class="toolbar-row">
                    <div class="toolbar-row-actions" style="display:flex;align-items:center;gap:0.5rem;margin-left:auto;">
                        <button type="button" class="toolbar-btn" aria-label="{{ t('Toggle grid/list view') }}" title="{{ t('Toggle view') }}" @click="toggleView()">
                            <i :class="['lucide', viewMode === 'grid' ? 'lucide-grid-3x3' : 'lucide-list', 'inline-block mr-1.5']" style="width:18px;height:18px" aria-hidden="true"></i>
                            <span x-text="viewMode === 'grid' ? '{{ t('Grid') }}' : '{{ t('List') }}'"></span>
                        </button>

                        <div class="toolbar-toggle-wrap">
                            <label class="toggle-switch">
                                <input type="checkbox" x-model="autoRefresh" aria-label="{{ t('Toggle auto-refresh') }}">
                                <span class="toggle-slider"></span>
                            </label>
                            <span data-i18n="Auto-refresh off">{{ t('Auto-refresh off') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div id="feed-filter-badges" role="status" aria-live="polite" aria-label="{{ t('Active filters') }}"></div>

            {{-- Feed grid --}}
            <div id="discovery-feed-grid" class="is-grid-view" role="feed" aria-label="{{ t('Content feed') }}">
                @include('partials.public.home-feed-items', [
                    'items' => $contents,
                    'startIndex' => (($current_page - 1) * $homepage_feed_limit) + 1,
                    'emptyText' => 'No content available at the moment. Adjust your filters or check back later.',
                    'emptyTitle' => 'No content found',
                ])
            </div>

            {{-- Skeleton loading (hidden by default, shown by JS) --}}
            <div id="feed-skeleton" class="hidden discovery-skeleton-grid" aria-hidden="true">
                @for ($i = 1; $i <= 6; $i++)
                    <div class="discovery-skeleton">
                        <div class="skeleton-image skeleton-pulse"></div>
                        <div class="skeleton-body">
                            <div class="skeleton-line skeleton-pulse" style="width: 30%"></div>
                            <div class="skeleton-line skeleton-pulse" style="width: 85%"></div>
                            <div class="skeleton-line skeleton-pulse" style="width: 65%"></div>
                            <div class="skeleton-line skeleton-pulse" style="width: 40%"></div>
                            <div class="skeleton-btn skeleton-pulse"></div>
                        </div>
                    </div>
                @endfor
            </div>

            {{-- Error state (hidden) --}}
            <div id="feed-error" class="feed-state hidden" role="alert">
                <div class="feed-state__icon">
                    <i class="lucide lucide-alert-circle" style="width:28px;height:28px" aria-hidden="true"></i>
                </div>
                <h3 data-i18n="Something went wrong">{{ t('Something went wrong') }}</h3>
                <p data-i18n="Failed to load content. Please try again.">{{ t('Failed to load content. Please try again.') }}</p>
                <button type="button" class="feed-state__action" data-action="reload-page" aria-label="Retry">
                    <i class="lucide lucide-refresh-ccw" style="width:16px;height:16px" aria-hidden="true"></i>
                    {{ t('Try Again') }}
                </button>
            </div>

            {{-- Empty state (hidden, shown when no results) --}}
            <div id="feed-empty" class="feed-state hidden" role="status">
                <div class="feed-state__icon">
                    <i class="lucide lucide-file-text" style="width:28px;height:28px" aria-hidden="true"></i>
                </div>
                <h3 data-i18n="No results found">{{ t('No results found') }}</h3>
                <p data-i18n="Try adjusting your search or filter criteria.">{{ t('Try adjusting your search or filter criteria.') }}</p>
                <button>
                    {{ t('Reset Filters') }}
                </button>
            </div>

            {{-- Load more / infinite scroll --}}
            <div class="mt-6 text-center">
                <div id="feed-scroll-sentinel" class="h-4" aria-hidden="true"></div>
                <button type="button" id="feed-load-more" class="toolbar-btn" aria-label="{{ t('Load more content') }}">
                    <i class="lucide lucide-arrow-down" style="width:16px;height:16px" aria-hidden="true"></i>
                    <span data-i18n="Load More">{{ t('Load More') }}</span>
                </button>
                <div id="feed-status" class="mt-2 text-sm text-slate-500">
                    @if (count($contents) > 0)
                        Showing <span id="feed-total-count">{{ count($contents) }}</span> items
                    @endif
                </div>
                <div id="feed-end" class="mt-3 hidden text-sm text-slate-500">
                    <i class="lucide lucide-check inline-block mr-1" style="width:16px;height:16px" aria-hidden="true"></i>
                    {{ t("You've viewed all available content") }}
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Share modal --}}
<div id="discovery-share-modal" class="discovery-modal" role="dialog" aria-labelledby="share-modal-title" aria-hidden="true" x-data="shareModal()" x-show="open" @keydown.escape.window="close()" @click.outside="close()" x-cloak>
    <div class="discovery-modal__backdrop" @click="close()"></div>
    <div class="discovery-modal__content">
        <button type="button" class="discovery-modal__close" @click="close()" aria-label="{{ t('Close') }}">
            <i class="lucide lucide-x" style="width:18px;height:18px" aria-hidden="true"></i>
        </button>
        <h3 data-i18n="Share">{{ t('Share') }}</h3>
        <input type="text" class="w-full rounded-lg border border-slate-200 bg-slate-50 p-2 text-sm" x-model="url" readonly>
        <button type="button" @click="copyToClipboard()" x-ref="copyBtn">
            {{ t('Copy Link') }}
        </button>
    </div>
</div>

{{-- ==================== FEATURED HIGHLIGHTS ==================== --}}
<section class="py-8 home-featured-section" aria-labelledby="featured-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 home-feed-surface">
        <div class="mb-12 text-center">
            <h2>
                {{ t('Featured Highlights') }}
            </h2>
            <p data-i18n="Hand-picked content curated just for you">{{ t('Hand-picked content curated just for you') }}</p>
        </div>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3" role="region" aria-label="{{ t('Featured content highlights') }}">
            <div>
                <article class="home-featured-card flex h-full flex-col rounded-2xl border border-slate-200 bg-white shadow-lg transition-all duration-300 hover:-translate-y-1" role="article">
                    <div class="relative bg-primary/10 p-8 text-center">
                        <i class="lucide lucide-smartphone text-5xl text-primary" aria-hidden="true"></i>
                    </div>
                    <div class="flex flex-1 flex-col p-6">
                        <h3 data-i18n="Latest Phones">{{ t('Latest Phones') }}</h3>
                        <p data-i18n="Explore the newest smartphones and their specifications">{{ t('Explore the newest smartphones and their specifications') }}</p>
                        <a>
                            {{ t('Browse Phones') }}
                        </a>
                    </div>
                </article>
            </div>

            <div>
                <article class="home-featured-card flex h-full flex-col rounded-2xl border border-slate-200 bg-white shadow-lg transition-all duration-300 hover:-translate-y-1" role="article">
                    <div class="relative bg-emerald-100 p-8 text-center">
                        <i class="lucide lucide-book-open text-5xl text-emerald-600" aria-hidden="true"></i>
                    </div>
                    <div class="flex flex-1 flex-col p-6">
                        <h3 data-i18n="Tech News">{{ t('Tech News') }}</h3>
                        <p data-i18n="Stay updated with the latest technology news and trends">{{ t('Stay updated with the latest technology news and trends') }}</p>
                        <a>
                            {{ t('Read News') }}
                        </a>
                    </div>
                </article>
            </div>

            <div>
                <article class="home-featured-card flex h-full flex-col rounded-2xl border border-slate-200 bg-white shadow-lg transition-all duration-300 hover:-translate-y-1" role="article">
                    <div class="relative bg-amber-100 p-8 text-center">
                        <i class="lucide lucide-briefcase text-5xl text-amber-600" aria-hidden="true"></i>
                    </div>
                    <div class="flex flex-1 flex-col p-6">
                        <h3 data-i18n="Job Opportunities">{{ t('Job Opportunities') }}</h3>
                        <p data-i18n="Find exciting career opportunities in tech industry">{{ t('Find exciting career opportunities in tech industry') }}</p>
                        <a>
                            {{ t('View Jobs') }}
                        </a>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>

{{-- ==================== LATEST MOBILES SECTION ==================== --}}
<section class="py-8 home-latest-mobiles-section" aria-labelledby="latest-mobiles-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 home-feed-surface">
        <div class="mb-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="latest-mobiles-title">
                    <span data-i18n="Latest">{{ t('Latest') }}</span> {{ t('Mobile Phones') }}
                </h2>
                <p data-i18n="Discover the newest smartphones with detailed specifications and pricing">{{ t('Discover the newest smartphones with detailed specifications and pricing') }}</p>
            </div>
            <a href="/mobiles" class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700" aria-label="{{ t('View all mobile phones') }}">
                <i class="lucide lucide-arrow-right mr-2"></i>{{ t('View All') }}
            </a>
        </div>

        <div class="feed-grid" role="feed" aria-label="{{ t('Latest mobiles feed') }}">
            @include('partials.public.home-feed-items', [
                'items' => $latest_mobiles ?? [],
                'startIndex' => 1,
                'emptyText' => 'No mobile phones available at the moment.',
            ])
        </div>
    </div>
</section>

{{-- ==================== SERVICES SECTION ==================== --}}
<section class="py-8 home-services-section tw-bg-slate-50/50 dark:tw-bg-slate-900/20" aria-labelledby="services-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 home-feed-surface">
        <div class="mb-12 text-center">
            <h2>
                {{ t('Explore Our Services') }}
            </h2>
            <p data-i18n="Discover and apply for exclusive services tailored to your needs">{{ t('Discover and apply for exclusive services tailored to your needs') }}</p>
        </div>

        <div class="flex flex-col gap-6 lg:flex-row lg:items-center">
            <div class="w-full lg:w-1/2">
                <div class="services-card-left flex h-full flex-col justify-between rounded-2xl border border-white/20 bg-gradient-to-br from-indigo-600 to-purple-700 p-6 text-white shadow-xl transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-4 inline-flex rounded-2xl bg-white/20 p-4">
                        <i class="lucide lucide-award text-4xl text-white"></i>
                    </div>
                    <h3 data-i18n="Professional Services">{{ t('Professional Services') }}</h3>
                    <p data-i18n="Browse our comprehensive catalog of professional services designed to help you achieve your goals. Each service is carefully curated with specific eligibility requirements and application processes.">{{ t('Browse our comprehensive catalog of professional services designed to help you achieve your goals. Each service is carefully curated with specific eligibility requirements and application processes.') }}</p>
                    <ul class="mb-4 space-y-2 text-white/80">
                        <li><i class="lucide lucide-check-circle mr-2 text-emerald-400"></i><strong>{{ t('Easy Application Process') }}</strong></li>
                        <li><i class="lucide lucide-check-circle mr-2 text-emerald-400"></i><strong>{{ t('Real-time Status Tracking') }}</strong></li>
                        <li><i class="lucide lucide-check-circle mr-2 text-emerald-400"></i><strong>{{ t('Expert Support Team') }}</strong></li>
                        <li><i class="lucide lucide-check-circle mr-2 text-emerald-400"></i><strong>{{ t('Quick Approval Process') }}</strong></li>
                    </ul>
                    <a href="/services" class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        <i class="lucide lucide-arrow-right mr-2"></i>{{ t('Explore Services') }}
                    </a>
                </div>
            </div>

            <div class="w-full lg:w-1/2">
                <div class="flex flex-col gap-4">
                    <div class="card border-0 bg-white rounded-2xl shadow-md transition-all duration-300 hover:-translate-y-1 services-card-right blue">
                        <div class="flex items-start gap-4 p-4">
                            <div class="flex-shrink-0 rounded-2xl bg-primary/15 p-3">
                                <i class="lucide lucide-search text-primary text-lg"></i>
                            </div>
                            <div>
                                <h5 data-i18n="Browse Services">{{ t('Browse Services') }}</h5>
                                <p data-i18n="Browse all available services by category and filter by your preferences">{{ t('Browse all available services by category and filter by your preferences') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 bg-white rounded-2xl shadow-md transition-all duration-300 hover:-translate-y-1 services-card-right green">
                        <div class="flex items-start gap-4 p-4">
                            <div class="flex-shrink-0 rounded-2xl bg-emerald-100 p-3">
                                <i class="lucide lucide-clipboard-check text-emerald-600 text-lg"></i>
                            </div>
                            <div>
                                <h5 data-i18n="Apply Easily">{{ t('Apply Easily') }}</h5>
                                <p data-i18n="Submit applications with simple forms and instant confirmation">{{ t('Submit applications with simple forms and instant confirmation') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 bg-white rounded-2xl shadow-md transition-all duration-300 hover:-translate-y-1 services-card-right yellow">
                        <div class="flex items-start gap-4 p-4">
                            <div class="flex-shrink-0 rounded-2xl bg-amber-100 p-3">
                                <i class="lucide lucide-history text-amber-600 text-lg"></i>
                            </div>
                            <div>
                                <h5 data-i18n="Track Status">{{ t('Track Status') }}</h5>
                                <p data-i18n="Monitor progress with real-time status updates and notifications">{{ t('Monitor progress with real-time status updates and notifications') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (auth()->check())
        <div class="mt-12">
            <div class="rounded-2xl bg-sky-500/10 p-6">
                <div class="flex items-center gap-4">
                    <i class="lucide lucide-info text-2xl text-sky-600"></i>
                    <div>
                        <h6 class="mb-1 font-bold text-sky-800">{{ t('Logged In User') }}</h6>
                        <p class="text-sm text-sky-700">{{ t('You can view') }} <a href="/services/my-applications" class="font-bold text-sky-600 underline">{{ t('your applications') }}</a> {{ t('and apply for new services anytime.') }}</p>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</section>

{{-- ==================== CATEGORIES SECTION ==================== --}}
<section class="py-8 home-categories-section tw-bg-gradient-to-b tw-from-slate-50/70 tw-to-transparent dark:tw-bg-slate-900/20" id="categories" aria-labelledby="categories-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 home-feed-surface">
        <div class="mb-12 text-center">
            <h2>
                {{ t('Browse by Category') }}
            </h2>
            <p data-i18n="Explore content across different categories">{{ t('Explore content across different categories') }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" role="region" aria-label="{{ t('Content categories') }}">
            <a href="/category/news" class="text-decoration-none" aria-label="{{ t('Browse breaking news category') }}">
                <div class="home-category-card news rounded-2xl bg-gradient-to-br from-red-500 to-orange-500 p-4 text-white transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                     role="button" tabindex="0">
                    <i class="lucide lucide-flame mb-2 text-3xl" aria-hidden="true"></i>
                    <h3 data-i18n="Breaking News">{{ t('Breaking News') }}</h3>
                    <p data-i18n="Latest updates from the tech world">{{ t('Latest updates from the tech world') }}</p>
                </div>
            </a>

            <a href="/category/reviews" class="text-decoration-none" aria-label="{{ t('Browse reviews category') }}">
                <div class="home-category-card reviews rounded-2xl bg-gradient-to-br from-amber-500 to-pink-500 p-4 text-white transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                     role="button" tabindex="0">
                    <i class="lucide lucide-star mb-2 text-3xl" aria-hidden="true"></i>
                    <h3 data-i18n="Reviews">{{ t('Reviews') }}</h3>
                    <p data-i18n="In-depth product reviews and analysis">{{ t('In-depth product reviews and analysis') }}</p>
                </div>
            </a>

            <a href="/category/tips" class="text-decoration-none" aria-label="{{ t('Browse tips and tricks category') }}">
                <div class="home-category-card tips rounded-2xl bg-gradient-to-br from-lime-500 to-emerald-500 p-4 text-white transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                     role="button" tabindex="0">
                    <i class="lucide lucide-lightbulb mb-2 text-3xl" aria-hidden="true"></i>
                    <h3 data-i18n="Tips & Tricks">{{ t('Tips & Tricks') }}</h3>
                    <p data-i18n="Helpful tips to maximize your devices">{{ t('Helpful tips to maximize your devices') }}</p>
                </div>
            </a>

            <a href="/category/tutorials" class="text-decoration-none" aria-label="{{ t('Browse tutorials category') }}">
                <div class="home-category-card tutorials rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 p-4 text-white transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                     role="button" tabindex="0">
                    <i class="lucide lucide-graduation-cap mb-2 text-3xl" aria-hidden="true"></i>
                    <h3 data-i18n="Tutorials">{{ t('Tutorials') }}</h3>
                    <p data-i18n="Step-by-step guides and tutorials">{{ t('Step-by-step guides and tutorials') }}</p>
                </div>
            </a>

            <a href="/category/comparison" class="text-decoration-none" aria-label="{{ t('Browse comparison category') }}">
                <div class="home-category-card comparison rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-600 p-4 text-white transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                     role="button" tabindex="0">
                    <i class="lucide lucide-code mb-2 text-3xl" aria-hidden="true"></i>
                    <h3 data-i18n="Comparison">{{ t('Comparison') }}</h3>
                    <p data-i18n="Compare devices side by side">{{ t('Compare devices side by side') }}</p>
                </div>
            </a>

            <a href="/category/trending" class="text-decoration-none" aria-label="{{ t('Browse trending category') }}">
                <div class="home-category-card trending rounded-2xl bg-gradient-to-br from-fuchsia-600 to-pink-600 p-4 text-white transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                     role="button" tabindex="0">
                    <i class="lucide lucide-trending-up mb-2 text-3xl" aria-hidden="true"></i>
                    <h3 data-i18n="Trending">{{ t('Trending') }}</h3>
                    <p data-i18n="What's trending in the mobile world">{{ t("What's trending in the mobile world") }}</p>
                </div>
            </a>
        </div>
    </div>
</section>

{{-- ==================== STATS SECTION ==================== --}}
<section class="py-8 bg-primary text-white relative overflow-hidden" aria-labelledby="stats-title">
    <div class="absolute inset-0 opacity-10" aria-hidden="true">
        <div class="absolute -top-16 -right-16 h-96 w-96 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-8 -left-8 h-72 w-72 rounded-full bg-white/10"></div>
    </div>

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 id="stats-title" class="sr-only">{{ t('Platform Statistics') }}</h2>
        <div class="grid gap-6 text-center md:grid-cols-4" role="region" aria-label="{{ t('Platform statistics') }}">
            <div class="stat-item">
                <p class="mb-2 text-4xl font-bold md:text-5xl" aria-label="{{ t('Active users') }}">{{ $stats['active_users'] ?? '500' }}+</p>
                <p data-i18n="Active Users">{{ t('Active Users') }}</p>
            </div>
            <div class="stat-item">
                <p class="mb-2 text-4xl font-bold md:text-5xl" aria-label="{{ t('Device specifications') }}">{{ $stats['device_specs'] ?? '1200' }}+</p>
                <p data-i18n="Device Specs">{{ t('Device Specs') }}</p>
            </div>
            <div class="stat-item">
                <p class="mb-2 text-4xl font-bold md:text-5xl" aria-label="{{ t('Published articles') }}">{{ $stats['articles'] ?? '850' }}+</p>
                <p class="mb-0 opacity-75">{{ t('Articles') }}</p>
            </div>
            <div class="stat-item">
                <p class="mb-2 text-4xl font-bold md:text-5xl" aria-label="{{ t('Job postings') }}">{{ $stats['job_posts'] ?? '320' }}+</p>
                <p data-i18n="Job Posts">{{ t('Job Posts') }}</p>
            </div>
        </div>
    </div>
</section>

{{-- ==================== CALCULATOR SECTION ==================== --}}
<section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 home-calculator-section scroll-fade-up py-8" data-scroll-animate>
    <div class="grid items-center gap-4 lg:grid-cols-2">
        <div class="grid items-center gap-4 lg:grid-cols-2">
            <div>
                <div class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <span data-i18n="Calculator categories">{{ t('Calculator categories') }}</span>
                    <h2 data-i18n="Quick access to calculators and converters">{{ t('Quick access to calculators and converters') }}</h2>
                    <p data-i18n="Solve finance, math, health, and unit conversion tasks instantly with our curated calculator hub.">{{ t('Solve finance, math, health, and unit conversion tasks instantly with our curated calculator hub.') }}</p>
                    <a href="/calculators" class="inline-flex w-fit items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        <i class="lucide lucide-calculator mr-2"></i>{{ t('Browse all calculators') }}
                    </a>
                </div>
            </div>
            <div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <div class="card h-full rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="p-4">
                                <div class="mb-3">
                                    <span class="inline-flex rounded-full bg-primary/10 p-2 text-primary">
                                        <i class="lucide lucide-calculator"></i>
                                    </span>
                                </div>
                                <h3 data-i18n="Math Calculators">{{ t('Math Calculators') }}</h3>
                                <p data-i18n="Standard, scientific, graphing, and programmer tools.">{{ t('Standard, scientific, graphing, and programmer tools.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="card h-full rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="p-4">
                                <div class="mb-3">
                                    <span class="inline-flex rounded-full bg-emerald-500/10 p-2 text-emerald-600">
                                        <i class="lucide lucide-dollar-sign"></i>
                                    </span>
                                </div>
                                <h3 data-i18n="Finance Tools">{{ t('Finance Tools') }}</h3>
                                <p data-i18n="Loans, interest, mortgage, percentage, and GPA calculators.">{{ t('Loans, interest, mortgage, percentage, and GPA calculators.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="card h-full rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="p-4">
                                <div class="mb-3">
                                    <span class="inline-flex rounded-full bg-amber-500/10 p-2 text-amber-500">
                                        <i class="lucide lucide-heart-pulse"></i>
                                    </span>
                                </div>
                                <h3 data-i18n="Health & Lifestyle">{{ t('Health & Lifestyle') }}</h3>
                                <p data-i18n="BMI, date calculations, and personal planning tools.">{{ t('BMI, date calculations, and personal planning tools.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="card h-full rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="p-4">
                                <div class="mb-3">
                                    <span class="inline-flex rounded-full bg-sky-500/10 p-2 text-sky-600">
                                        <i class="lucide lucide-expand mr-2 opacity-75"></i>
                                    </span>
                                </div>
                                <h3 data-i18n="Converters">{{ t('Converters') }}</h3>
                                <p data-i18n="Currency, length, weight, temperature and more unit conversions.">{{ t('Currency, length, weight, temperature and more unit conversions.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== SHARE SECTION ==================== --}}
<section class="py-8" aria-labelledby="share-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex justify-center">
            <div class="w-full max-w-5xl rounded-3xl border border-slate-200 bg-white p-8 shadow-xl home-share-card">
                <h2 id="share-title">
                    <i class="lucide lucide-share-2 mr-2" aria-hidden="true"></i>{{ t('Share This Page') }}
                </h2>

                <div class="flex flex-wrap justify-center gap-3 mb-4" role="group" aria-label="{{ t('Share on social media') }}">
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}&text={{ urlencode('Check out this amazing content!') }}"
                       target="_blank" rel="noopener noreferrer"
                       class="rounded-full bg-[#1DA1F2] px-5 py-2 text-sm font-medium text-white shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg"
                       aria-label="{{ t('Share on Twitter/X') }}">
                        <i class="lucide lucide-share-2 mr-2" aria-hidden="true"></i>Twitter/X
                    </a>

                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(url()->current()) }}"
                       target="_blank" rel="noopener noreferrer"
                       class="rounded-full bg-[#0077B5] px-5 py-2 text-sm font-medium text-white shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg"
                       aria-label="{{ t('Share on LinkedIn') }}">
                        <i class="lucide lucide-briefcase mr-2" aria-hidden="true"></i>LinkedIn
                    </a>

                    <a href="https://wa.me/?text={{ urlencode('Check out this amazing content') }}%20{{ urlencode(url()->current()) }}"
                       target="_blank" rel="noopener noreferrer"
                       class="rounded-full bg-[#25D366] px-5 py-2 text-sm font-medium text-white shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg"
                       aria-label="{{ t('Share on WhatsApp') }}">
                        <i class="lucide lucide-message-circle mr-2" aria-hidden="true"></i>WhatsApp
                    </a>

                    <a href="mailto:?subject={{ urlencode('Check out this amazing website') }}&body={{ urlencode('I found this amazing website that you might like:') }}%0D%0A{{ urlencode(url()->current()) }}"
                       class="rounded-full bg-[#EA4335] px-5 py-2 text-sm font-medium text-white shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg"
                       aria-label="{{ t('Share via Email') }}">
                        <i class="lucide lucide-mail mr-2" aria-hidden="true"></i>{{ t('Email') }}
                    </a>

                    <button type="button"
                            class="rounded-full border border-slate-300 bg-white px-5 py-2 text-sm font-medium text-slate-700 shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:bg-slate-50"
                            id="copyLinkBtnHome"
                            aria-label="{{ t('Copy link to clipboard') }}"
                            data-action="copy-page-url">
                        <i class="lucide lucide-link mr-2" aria-hidden="true"></i>{{ t('Copy Link') }}
                    </button>
                </div>

                <p class="text-center text-sm text-slate-500">
                    <i class="lucide lucide-info mr-1" aria-hidden="true"></i>{{ t('Help us spread the word by sharing this page with your friends and followers') }}
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ==================== NEWSLETTER SECTION ==================== --}}
<section class="py-8 bg-slate-50" aria-labelledby="newsletter-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex justify-center">
            <div class="w-full max-w-3xl rounded-3xl border border-slate-200 bg-white p-8 shadow-xl newsletter-card home-newsletter-card text-center">
                <div class="mb-4 inline-flex rounded-full bg-primary p-4">
                    <i class="lucide lucide-mail-plus text-3xl text-white" aria-hidden="true"></i>
                </div>
                <h2 data-i18n="Get Latest Updates">{{ t('Get Latest Updates') }}</h2>
                <p data-i18n="Subscribe for the latest news, reviews, and exclusive offers">{{ t('Subscribe for the latest news, reviews, and exclusive offers') }}</p>
                <form id="home-newsletter-form" class="flex flex-col gap-3 sm:flex-row sm:justify-center sm:gap-2" aria-label="{{ t('Newsletter subscription form') }}">
                    <div class="flex-1 sm:max-w-md">
                        <label for="newsletter-email" class="sr-only">{{ t('Email address') }}</label>
                        <input type="email"
                               id="newsletter-email"
                               name="email"
                               class="w-full rounded-full border border-slate-300 px-5 py-3 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                               placeholder="{{ t('Your email address') }}"
                               aria-label="{{ t('Enter your email address') }}"
                               required>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700">
                        <i class="lucide lucide-send mr-2" aria-hidden="true"></i>{{ t('Subscribe') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

{{-- ==================== RECOMMENDED SECTION ==================== --}}
<section class="py-8 home-recommended-section" aria-labelledby="recommended-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-12 text-center">
            <h2 id="recommended-title" class="mb-3 text-4xl font-bold md:text-5xl">
                <span data-i18n="Recommended for You">{{ t('Recommended for You') }}</span>
            </h2>
            <p data-i18n="Hand-picked content just for your interests">{{ t('Hand-picked content just for your interests') }}</p>
        </div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" role="region" aria-label="{{ t('Recommended content') }}">
            @if (!empty($featured_content))
                @foreach (array_slice($featured_content, 0, 3) as $item)
                <article class="recommended-card flex h-full flex-col rounded-2xl border border-slate-200 bg-white shadow-lg transition-all duration-300 hover:-translate-y-1" role="article" aria-label="Recommended item: {{ $item['title'] }}">
                    @if (!empty($item['image']))
                    <div class="relative aspect-video overflow-hidden rounded-t-2xl">
                        <img src="{{ $item['image'] }}"
                             class="absolute inset-0 h-full w-full object-cover"
                             alt="{{ $item['title'] }}"
                             loading="lazy" decoding="async" width="400" height="225">
                    </div>
                    @endif
                    <div class="flex flex-1 flex-col p-5">
                        <div class="mb-2">
                            <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">{{ $item['category'] ?? 'Featured' }}</span>
                        </div>
                        <h3 class="mb-2 line-clamp-2 text-lg font-bold">{{ strip_tags((string) $item['title']) }}</h3>
                        <p class="mb-0 line-clamp-2 flex-1 text-sm text-slate-500">{{ strip_tags((string) ($item['description'] ?? '')) }}</p>
                        <a href="{{ $item['link'] ?? '#' }}"
                           class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 mt-4 w-fit"
                           aria-label="Read more about {{ strip_tags((string) $item['title']) }}">
                            {{ t('Read More') }}
                        </a>
                    </div>
                </article>
                @endforeach
            @else
                @php
                    $defaultCategories = [
                        ['name' => 'News', 'slug' => 'news', 'icon' => 'lucide lucide-book-open', 'gradient' => 'from-indigo-600 to-purple-700', 'description' => 'Latest tech news'],
                        ['name' => 'Reviews', 'slug' => 'reviews', 'icon' => 'lucide lucide-star', 'gradient' => 'from-pink-500 to-rose-500', 'description' => 'Product reviews'],
                        ['name' => 'Tutorials', 'slug' => 'tutorials', 'icon' => 'lucide lucide-graduation-cap', 'gradient' => 'from-sky-500 to-blue-600', 'description' => 'Learn new skills'],
                    ];
                    $catGradients = ['from-indigo-600 to-purple-700', 'from-pink-500 to-rose-500', 'from-sky-500 to-blue-600'];
                    $recommendedCats = !empty($categories) ? $categories : $defaultCategories;
                @endphp
                @foreach ($recommendedCats as $cat)
                <a href="/category/{{ $cat['slug'] }}" class="text-decoration-none" aria-label="Browse {{ $cat['name'] }} category">
                    <div class="rounded-2xl bg-gradient-to-br p-5 text-white transition-all duration-300 hover:-translate-y-1 hover:shadow-lg {{ $catGradients[$loop->index % count($catGradients)] }}"
                         role="button" tabindex="0">
                        <i class="{{ $cat['icon'] ?? 'lucide lucide-folder' }} mb-3 text-3xl" aria-hidden="true"></i>
                        <h3 class="mb-2 font-bold text-lg">{{ $cat['name'] }}</h3>
                        <p class="text-sm opacity-75">{{ $cat['description'] ?? 'Explore this category' }}</p>
                    </div>
                </a>
                @endforeach
            @endif
        </div>
    </div>
</section>

{{-- ==================== PAGE JAVASCRIPT (legacy inline port) ==================== --}}
<script>
  // Auto-switch Top Picks between posts and services
  function initTopPicksAutoSwitch() {
    const topCarouselSection = document.querySelector('.top-carousel-section');
    if (!topCarouselSection) return;

    const tabPosts = document.getElementById('tab-posts');
    const tabServices = document.getElementById('tab-services');

    if (!tabPosts || !tabServices) return;

    let isShowingPosts = true;
    const switchInterval = 6000; // Switch every 6 seconds

    setInterval(function() {
      if (isShowingPosts) {
        tabServices.click();
        isShowingPosts = false;
      } else {
        tabPosts.click();
        isShowingPosts = true;
      }
    }, switchInterval);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTopPicksAutoSwitch);
  } else {
    initTopPicksAutoSwitch();
  }

  // ==================== SCROLL ANIMATION OBSERVER ====================
  function initScrollAnimationsHome() {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: '0px 0px -40px 0px' }
    );

    document.querySelectorAll('[data-scroll-animate]').forEach((el) => {
      observer.observe(el);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initScrollAnimationsHome);
  } else {
    initScrollAnimationsHome();
  }

  // Event delegation — replaces inline onclick handlers
  document.addEventListener('click', function(e) {
    var target = e.target.closest('[data-action]');
    if (!target) return;
    var action = target.getAttribute('data-action');

    switch (action) {
    case 'top-picks-tab':
      (function(btn) {
        var section = btn.closest('.top-carousel-section');
        section.querySelectorAll('.top-carousel-tab').forEach(function(t) {
          t.classList.remove('active');
          t.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');
        section.querySelectorAll('.top-carousel-panel').forEach(function(pan) {
          pan.classList.add('hidden');
        });
        document.getElementById(target.getAttribute('data-panel')).classList.remove('hidden');
      })(target);
      break;
    case 'reload-page':
      window.location.reload();
      break;
    case 'reset-feed':
      window.FeedDiscovery && FeedDiscovery.resetFeed();
      break;
    case 'close-share-modal':
      window.FeedDiscovery && FeedDiscovery.closeModal();
      break;
    case 'select-input':
      target.select();
      break;
    case 'copy-share-url':
      var urlInput = document.querySelector('[data-share-url]');
      window.FeedDiscovery && FeedDiscovery.copyToClipboard(urlInput.value, target);
      break;
    case 'copy-page-url':
      copyToClipboardHome();
      break;
    }
  });

  function copyToClipboardHome() {
    const url = window.location.href;
    const btn = document.getElementById('copyLinkBtnHome');

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url)
        .then(function() { showCopySuccess(btn); })
        .catch(function(err) {
          console.error('Clipboard API failed:', err);
          fallbackCopy(url, btn);
        });
    } else {
      fallbackCopy(url, btn);
    }
  }

  function fallbackCopy(text, btn) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    textarea.setAttribute('aria-hidden', 'true');
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      showCopySuccess(btn);
    } catch (err) {
      console.error('Failed to copy:', err);
      alert('{{ t('Failed to copy link. Please try again.') }}');
    }
    document.body.removeChild(textarea);
  }

  function showCopySuccess(btn) {
    if (!btn) return;
    const originalHtml = btn.innerHTML;
    const originalBg = btn.style.backgroundColor;
    const originalBorder = btn.style.borderColor;

    btn.innerHTML = '<i class="lucide lucide-check mr-2" aria-hidden="true"></i>' + '{{ t('Copied!') }}';
    btn.style.backgroundColor = '#28a745';
    btn.style.borderColor = '#28a745';
    btn.style.color = '#fff';
    btn.setAttribute('aria-label', 'Link copied to clipboard');

    setTimeout(function() {
      btn.innerHTML = originalHtml;
      btn.style.backgroundColor = originalBg;
      btn.style.borderColor = originalBorder;
      btn.style.color = '';
      btn.setAttribute('aria-label', 'Copy link to clipboard');
    }, 2000);
  }

  // ==================== FEED LOAD-MORE WIRING ====================
  // feed-discovery.js dispatches a `feed:load-more` CustomEvent; the legacy
  // page never registered a listener (latent legacy bug). Wire it to the
  // legacy /api/feed/load-more endpoint contract here so Load More,
  // infinite scroll, search and category filters actually work.
  (function initFeedLoadMore() {
    var container = document.getElementById('discovery-feed');
    if (!container || !window.FeedDiscovery) return;

    container.addEventListener('feed:load-more', function(e) {
      var d = e.detail || {};
      var params = new URLSearchParams({
        page: d.page || 1,
        sort: d.sort || 'latest'
      });
      if (d.search) params.set('search', d.search);
      if (d.category) params.set('category', d.category);

      fetch('/api/feed/load-more?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            window.FeedDiscovery.showError && window.FeedDiscovery.showError(data.error);
            window.FeedDiscovery.config.isLoading = false;
            return;
          }
          window.FeedDiscovery.appendItems(data.html || '', data.has_more);
          var end = document.getElementById('feed-end');
          if (end && !data.has_more) end.classList.remove('hidden');
        })
        .catch(function () {
          window.FeedDiscovery.showError && window.FeedDiscovery.showError();
          window.FeedDiscovery.config.isLoading = false;
        });
    });
  })();
</script>
@endsection

@push('scripts')
<script type="module" src="{{ asset('/assets/js/dist/feed-discovery.js') }}?v={{ $appSettings['asset_version'] ?? 'b'.now()->timestamp }}"></script>
@endpush
