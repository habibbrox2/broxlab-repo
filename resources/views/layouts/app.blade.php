@php use App\Support\I18n\LanguageService; @endphp
<!DOCTYPE html>
<html lang="{{ app(LanguageService::class)->current() }}" data-lang="{{ app(LanguageService::class)->current() }}" data-theme="light" id="app-html">
<script>
// Apply saved theme immediately to prevent flash (runs before rendering)
(function(){try{var t=localStorage.getItem('broxbhai-theme');if(!t){t=window.matchMedia&&window.matchMatchMedia('(prefers-color-scheme:dark)').matches?'dark':'light'}document.documentElement.setAttribute('data-theme',t);document.documentElement.style.colorScheme=t}catch(e){}})();
</script>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title>@yield('title', $appSettings['meta_title'] ?? ($appSettings['site_name'] ?? 'BroxLab').' | Official Website')</title>

    {{-- SEO --}}
    <meta name="description" content="@yield('meta_description', $appSettings['meta_description'] ?? 'Welcome to '.($appSettings['site_name'] ?? 'BroxLab'))">
    <meta name="keywords" content="{{ $appSettings['meta_keywords'] ?? ($appSettings['site_name'] ?? 'BroxLab').', mobile, tech, news' }}">
    <meta name="author" content="{{ $appSettings['site_name'] ?? '' }}">
    <meta name="creator" content="{{ $appSettings['site_name'] ?? '' }}">
    <meta name="publisher" content="{{ $appSettings['site_name'] ?? '' }}">

    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="bingbot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">

    <link rel="canonical" href="{{ $canonicalUrl ?? url()->current() }}">
    @if(isset($paginationNextUrl))
        <link rel="next" href="{{ $paginationNextUrl }}">
    @endif
    @if(isset($paginationPrevUrl))
        <link rel="prev" href="{{ $paginationPrevUrl }}">
    @endif

    {{-- Favicon & PWA --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('/assets/favicon.ico') }}">
    <link rel="shortcut icon" href="{{ asset('/assets/favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ $appSettings['site_logo'] ?? asset('/assets/images/default-image.png') }}">
    <meta name="theme-color" content="#0d6efd">
    <meta name="msapplication-TileColor" content="#0d6efd">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $appSettings['site_name'] ?? 'BroxLab' }}">
    <meta name="application-name" content="{{ $appSettings['site_name'] ?? 'BroxLab' }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ $canonicalUrl ?? url()->current() }}">
    <meta property="og:site_name" content="{{ $appSettings['site_name'] ?? 'BroxLab' }}">
    <meta property="og:title" content="@yield('title', $appSettings['site_name'] ?? 'BroxLab')">
    <meta property="og:description" content="{{ $appSettings['meta_description'] ?? 'Welcome to '.($appSettings['site_name'] ?? 'BroxLab') }}">
    <meta property="og:image" content="{{ $appSettings['site_logo'] ?? asset('/assets/images/default-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/png">
    <meta property="og:locale" content="{{ app(LanguageService::class)->current() == 'bn' ? 'bn_BD' : 'en_US' }}">
    <meta property="og:locale:alternate" content="bn_BD">
    @if(!empty($appSettings['facebook_app_id']))
        <meta property="fb:app_id" content="{{ $appSettings['facebook_app_id'] }}">
    @endif

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', $appSettings['site_name'] ?? 'BroxLab')">
    <meta name="twitter:description" content="{{ $appSettings['meta_description'] ?? 'Welcome to '.($appSettings['site_name'] ?? 'BroxLab') }}">
    <meta name="twitter:image" content="{{ $appSettings['site_logo'] ?? asset('/assets/images/default-image.png') }}">
    @if(!empty($appSettings['twitter_handle']))
        <meta name="twitter:creator" content="@{{ $appSettings['twitter_handle'] }}">
    @endif
    @if(!empty($appSettings['twitter_site']))
        <meta name="twitter:site" content="@{{ $appSettings['twitter_site'] }}">
    @endif

    {{-- Language & Localisation --}}
    <meta name="language" content="{{ app(LanguageService::class)->current() == 'bn' ? 'Bengali' : 'English' }}">
    <link rel="alternate" hreflang="en" href="{{ $canonicalUrl ?? url()->current() }}">
    <link rel="alternate" hreflang="bn" href="{{ url()->current() }}" lang="bn">
    <link rel="alternate" hreflang="x-default" href="{{ $canonicalUrl ?? url()->current() }}">

    {{-- CSRF + auth --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth<meta name="user-id" content="{{ auth()->id() }}">@endauth

    {{-- Preconnect --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://www.googletagmanager.com">
    <link rel="preconnect" href="https://www.gstatic.com" crossorigin="anonymous">
    <link rel="dns-prefetch" href="https://fcm.googleapis.com">
    <link rel="dns-prefetch" href="https://firebaseinstallations.googleapis.com">

    {{-- Google Fonts --}}
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap"
          onload="this.onload=null;this.rel='stylesheet'" fetchpriority="low">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    </noscript>

    {{-- CSS: Critical + deferred --}}
    @php
        $assetsVersion = $appSettings['asset_version'] ?? ('b'.now()->timestamp);
    @endphp
    <link rel="stylesheet" href="{{ asset('/assets/css/dist/tailwind-public.css') }}?v={{ $assetsVersion }}">

    <link rel="stylesheet" href="{{ asset('/cdn/css/lucide/lucide.css') }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ asset('/cdn/css/lucide/lucide.css') }}"></noscript>
    <link rel="stylesheet" href="{{ asset('/assets/css/brox-polish.css') }}?v={{ $assetsVersion }}">
    <link rel="stylesheet" href="{{ asset('/assets/ai/css/assistant.css') }}">
    <link rel="stylesheet" href="{{ asset('/assets/datepicker/datepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('/assets/cdn/css/sweetalert2.min.css') }}">

    @yield('extra_styles')
    @stack('styles')
</head>

<body role="application" class="min-h-screen flex flex-col">

    {{-- JSON-LD BreadcrumbList --}}
    @if(isset($breadcrumbs) && count($breadcrumbs) > 0)
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Home", "item": "{{ url('/') }}" }
        @foreach($breadcrumbs as $i => $crumb)
        ,{
          "@type": "ListItem",
          "position": {{ $i + 2 }},
          "name": {{ json_encode($crumb['label'] ?? '') }},
          "item": {{ json_encode(($i === count($breadcrumbs) - 1 ? ($canonicalUrl ?? url()->current()) : ($crumb['url'] ?? ''))) }}
        }
        @endforeach
      ]
    }
    </script>
    @endif

    @yield('schema')

    {{-- Preloader --}}
    <div id="sitePreloader" role="status" aria-label="Loading"
         class="fixed inset-0 z-[99999] flex flex-col items-center justify-center
                bg-white dark:bg-slate-950
                transition-all duration-500 ease-out
                motion-reduce:transition-none">
        <div class="relative flex flex-col items-center gap-5">
            <div class="relative flex items-center justify-center w-16 h-16">
                <div class="absolute inset-0 rounded-full border-[3px] border-slate-100 dark:border-slate-800"></div>
                <div class="absolute inset-0 rounded-full border-[3px] border-transparent border-t-indigo-500
                            animate-[preloader-spin_0.8s_linear_infinite]
                            motion-reduce:animate-none"></div>
                <div class="absolute inset-[3px] rounded-full border-[3px] border-transparent border-r-violet-400
                            animate-[preloader-spin_1.2s_linear_infinite_reverse]
                            motion-reduce:animate-none"></div>
                <i class="lucide lucide-sparkles w-6 h-6 text-indigo-600 dark:text-indigo-400"></i>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-[preloader-dot_1.4s_ease-in-out_infinite_both] motion-reduce:animate-none"></span>
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-[preloader-dot_1.4s_ease-in-out_infinite_both_0.16s] motion-reduce:animate-none"></span>
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-[preloader-dot_1.4s_ease-in-out_infinite_both_0.32s] motion-reduce:animate-none"></span>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-slate-100 dark:bg-slate-800">
            <div id="preloaderBar"
                 class="h-full bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-500
                        transition-all duration-[3s] ease-out motion-reduce:transition-none"
                 style="width:0%"></div>
        </div>
    </div>

    {{-- Accessibility: Skip link --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 focus:z-50 focus:px-4 focus:py-3 focus:bg-white focus:text-indigo-600 focus:outline-none focus:rounded-br-lg"
       tabindex="0">
        {{ t('Skip to main content') }}
    </a>

    {{-- Header --}}
    @include('partials.public.header')

    {{-- Main content --}}
    <main id="main-content" role="main" class="relative isolate flex-1">

        {{-- Breadcrumb --}}
        @if(isset($breadcrumbs) && count($breadcrumbs) > 0)
            @include('partials.public.breadcrumb')
        @endif

        {{-- Flash messages --}}
        @if(session('status'))
            <div class="mb-4 rounded-2xl border border-emerald-200/70 dark:border-emerald-800/40 bg-gradient-to-r from-emerald-50 via-white to-emerald-50/60 dark:from-emerald-950/30 dark:via-slate-900 dark:to-emerald-950/20 shadow-sm">
                <div class="relative px-5 py-4 flex items-start gap-3.5">
                    <div class="mt-0.5 flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex-shrink-0">
                        <i class="lucide lucide-check-circle w-5 h-5 text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">{{ session('status') }}</div>
                    </div>
                    <button type="button" class="flex items-center justify-center w-8 h-8 rounded-lg text-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/30 transition-all duration-200 flex-shrink-0"
                            data-brox-dismiss="alert" aria-label="Dismiss">
                        <i class="lucide lucide-x w-4 h-4"></i>
                    </button>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-2xl border border-red-200/70 dark:border-red-800/40 bg-gradient-to-r from-red-50 via-white to-red-50/60 dark:from-red-950/30 dark:via-slate-900 dark:to-red-950/20 shadow-sm">
                <div class="relative px-5 py-4 flex items-start gap-3.5">
                    <div class="mt-0.5 flex items-center justify-center w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30 flex-shrink-0">
                        <i class="lucide lucide-x-circle w-5 h-5 text-red-600 dark:text-red-400"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-red-900 dark:text-red-100">{{ session('error') }}</div>
                    </div>
                    <button type="button" class="flex items-center justify-center w-8 h-8 rounded-lg text-red-400 hover:text-red-600 dark:hover:text-red-300 hover:bg-red-100 dark:hover:bg-red-900/30 transition-all duration-200 flex-shrink-0"
                            data-brox-dismiss="alert" aria-label="Dismiss">
                        <i class="lucide lucide-x w-4 h-4"></i>
                    </button>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Footer --}}
    @include('partials.public.footer')

    {{-- Scroll-to-top button --}}
    <button id="scrollTopBtn"
            type="button"
            title="{{ t('Scroll to top of page') }}"
            aria-label="{{ t('Scroll to top of page') }}"
            data-i18n-title="{{ t('Scroll to top of page') }}"
            data-i18n-aria-label="{{ t('Scroll to top of page') }}"
            x-data="{}"
            x-show="window.pageYOffset > 180"
            x-transition
            @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            class="scroll-top-btn fixed bottom-20 right-5 z-40 items-center justify-center w-11 h-11 rounded-full bg-indigo-600 text-white shadow-lg shadow-indigo-500/25 border border-indigo-400/30 hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none">
        <i class="lucide lucide-arrow-up w-5 h-5" aria-hidden="true"></i>
    </button>

    {{-- Push notification button --}}
    <button id="enableNotificationsBtn"
            type="button"
            title="{{ t('Enable push notifications') }}"
            aria-label="{{ t('Enable notifications') }}"
            data-i18n-title="{{ t('Enable push notifications') }}"
            data-i18n-aria-label="{{ t('Enable notifications') }}"
            class="notif-permission-btn fixed bottom-[140px] right-5 z-40 hidden items-center justify-center w-11 h-11 rounded-full bg-indigo-600 text-white shadow-lg shadow-indigo-500/25 border border-indigo-400/30 hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none">
        <i class="lucide lucide-bell w-5 h-5" aria-hidden="true"></i>
    </button>

    {{-- Reading progress bar --}}
    <div id="scrollProgress"
         role="progressbar"
         aria-label="{{ t('Page scroll progress') }}"
         aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
         x-data="scrollProgress()"
         :style="`width: ${progress}%`"
         :aria-valuenow="Math.round(progress)"
         class="scroll-progress fixed top-0 left-0 z-[9999] h-[3px] bg-indigo-500 transition-none">
    </div>

    {{-- Page transition overlay --}}
    <div id="pageTransitionOverlay"
         aria-hidden="true"
         class="fixed inset-0 z-[99998] pointer-events-none
                bg-white dark:bg-slate-950
                transition-all duration-300 ease-out motion-reduce:transition-none"
         style="opacity:0">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 via-transparent to-violet-500/5 dark:from-indigo-500/10 dark:to-violet-500/10"></div>
        <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 flex flex-col items-center gap-3">
            <div class="w-8 h-8 rounded-full border-2 border-slate-200 dark:border-slate-700 border-t-indigo-500 animate-spin motion-reduce:animate-none"></div>
            <span class="text-xs font-medium text-slate-400 dark:text-slate-500">{{ t('Loading...') }}</span>
        </div>
    </div>

    {{-- Inline core UI behaviours --}}
    <script src="{{ asset('/assets/js/shared/dom-helpers.js') }}"></script>
    <script src="{{ asset('/assets/js/shared/events.js') }}"></script>
    <script src="{{ asset('/assets/datepicker/datepicker.js') }}" defer></script>
    <script>
    (function () {
        'use strict';

        if (typeof window.debounce !== 'function') {
            window.debounce = function debounce(fn, ms) {
                var t;
                return function () {
                    var args = arguments;
                    clearTimeout(t);
                    t = setTimeout(function () { fn.apply(this, args); }.bind(this), ms);
                }.bind(this);
            };
        }

        if (typeof window.throttle !== 'function') {
            window.throttle = function throttle(fn, ms) {
                var last = 0;
                return function () {
                    var now = Date.now();
                    if (now - last >= ms) {
                        last = now;
                        fn.apply(this, arguments);
                    }
                };
            };
        }

        function scrollTop() { return window.pageYOffset || document.documentElement.scrollTop || 0; }

        function initScrollTopBtn() {
            var btn = document.getElementById('scrollTopBtn');
            if (!btn) return;
            function sync() { var visible = scrollTop() > 180; btn.classList.toggle('flex', visible); btn.classList.toggle('hidden', !visible); btn.setAttribute('aria-hidden', visible ? 'false' : 'true'); }
            btn.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
            window.addEventListener('scroll', sync, { passive: true });
            sync();
        }

        function initProgressBar() {
            var bar = document.getElementById('scrollProgress');
            if (!bar) return;
            function update() {
                var scrolled = scrollTop();
                var docH = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
                var winH = window.innerHeight;
                var maxScroll = docH - winH;
                var pct = maxScroll > 0 ? Math.min((scrolled / maxScroll) * 100, 100) : 0;
                bar.style.width = pct + '%';
                bar.setAttribute('aria-valuenow', Math.round(pct));
            }
            window.addEventListener('scroll', throttle(update, 50), { passive: true });
            window.addEventListener('resize', debounce(update, 100), { passive: true });
            update();
        }

        function initScrollAnimations() {
            var els = document.querySelectorAll('.scroll-fade-in, .scroll-slide-up, .scroll-scale-in');
            if (!els.length) return;
            if (!('IntersectionObserver' in window)) { els.forEach(function (el) { el.classList.add('visible'); }); return; }
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) { if (entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); } });
            }, { rootMargin: '0px 0px -60px 0px', threshold: 0.08 });
            els.forEach(function (el) { observer.observe(el); });
        }

        function initSmoothAnchors() {
            var header = document.querySelector('header[role="banner"]');
            document.addEventListener('click', function (e) {
                var link = e.target.closest('a[href^="#"]');
                if (!link) return;
                var id = link.getAttribute('href');
                if (!id || id === '#') return;
                var target = document.querySelector(id);
                if (!target) return;
                e.preventDefault();
                var offset = (header ? header.offsetHeight : 0) + 12;
                var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                window.scrollTo({ top: top, behavior: 'smooth' });
                target.setAttribute('tabindex', '-1');
                target.focus({ preventScroll: true });
            });
        }

        var preloaderHidden = false;
        function hidePreloader() {
            if (preloaderHidden) return;
            preloaderHidden = true;
            var preloader = document.getElementById('sitePreloader');
            if (!preloader) return;
            preloader.classList.add('preloader-hidden');
            setTimeout(function () { preloader.style.display = 'none'; preloader.removeAttribute('role'); }, 600);
        }

        function initPageTransitions() {
            var overlay = document.getElementById('pageTransitionOverlay');
            if (!overlay) return;
            document.addEventListener('click', function (e) {
                var link = e.target.closest('a:not([href^="#"])'); if (!link) return;
                var href = link.getAttribute('href');
                if (!href || href.startsWith('mailto:') || href.startsWith('tel:') || href.indexOf('://') > -1 || href.startsWith('//')) return;
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
                try { if (new URL(href, window.location.origin).origin !== window.location.origin) return; } catch (_) { return; }
                overlay.classList.add('is-active');
            });
            window.addEventListener('pageshow', function () { overlay.classList.remove('is-active'); hidePreloader(); });
        }

        function init() {
            var preloaderBar = document.getElementById('preloaderBar');
            if (preloaderBar) { requestAnimationFrame(function () { preloaderBar.style.width = '70%'; }); }
            initScrollTopBtn();
            initProgressBar();
            initScrollAnimations();
            initSmoothAnchors();
            initPageTransitions();
            hidePreloader();
        }

        (function earlyProgress() {
            var bar = document.getElementById('preloaderBar');
            if (bar) { requestAnimationFrame(function () { bar.style.width = '30%'; }); }
        })();

        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init, { once: true }); } else { init(); }
    })();
    </script>

    {{-- App config --}}
    @php
        $broxCurrentLang = app(LanguageService::class)->current();
        $broxSiteConfig = [
            'lang' => $broxCurrentLang,
            'logo' => $appSettings['site_logo'] ?? '',
            'translations' => [
                'en' => $siteTranslations['en'] ?? [],
                'bn' => $siteTranslations['bn'] ?? [],
                $broxCurrentLang => $siteTranslations[$broxCurrentLang] ?? [],
            ],
        ];
    @endphp
    <script>
    window.__APP_JS_CONFIG = window.__APP_JS_CONFIG || {};
    window.__APP_JS_CONFIG.notifications = window.__APP_JS_CONFIG.notifications || {};
    window.__APP_JS_CONFIG.notifications.websocketUrl = '';
    window.puter = window.puter || {};
    window.puter.quiet = true;

    window.__broxSiteConfig = {!! json_encode($broxSiteConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
    window.__broxSiteLang = window.__broxSiteConfig.lang;
    window.__broxSiteLogo = window.__broxSiteConfig.logo;
    window.__broxSiteTranslations = Object.assign(window.__broxSiteTranslations || {}, window.__broxSiteConfig.translations);
    </script>

    {{-- Module scripts --}}
    <script type="module" src="{{ asset('/assets/js/dist/brox-ui.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js" defer></script>
    <script type="module" src="{{ asset('/assets/js/dist/sweetalert2-handler.js') }}"></script>
    <script type="module" src="{{ asset('/assets/js/dist/theme-manager.js') }}"></script>
    <script type="module" src="{{ asset('/assets/js/dist/script.js') }}"></script>
    <script type="module" src="{{ asset('/assets/js/dist/assistant-shell.js') }}"></script>
    <script type="module" src="{{ asset('/assets/js/dist/assistant-runtime.js') }}"></script>
    <script type="module" src="{{ asset('/assets/js/dist/brox-i18n.js') }}"></script>
    <script type="module" src="{{ asset('/assets/js/dist/lucide-compat.js') }}"></script>
    <script type="module" src="{{ asset('/assets/laravel/dist/app.js') }}"></script>

    {{-- AI Assistant --}}
    @php
        // $authUser / $isAuthenticated / $isAdmin / $isSuperAdmin / $authRoles all
        // come from the shared view composer (AppServiceProvider), resolved from
        // the RBAC roles/user_roles tables. Reading $authUser->role or
        // ->is_super_admin always returned null — those columns do not exist.
        $authRoleList = $authRoles ?? [];
        $isAdminUser = (bool) ($isAdmin ?? false);
        $isRegularUser = (bool) ($isAuthenticated ?? false) && ! $isAdminUser;
    @endphp
    @include('partials.public.ai-assistant')

    {{-- Page-specific scripts --}}
    @yield('extra_scripts')

</body>
</html>
