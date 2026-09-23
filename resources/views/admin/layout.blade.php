<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('title', 'Admin — '.($appSettings['site_name'] ?? 'BroxLab'))</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth<meta name="user-id" content="{{ auth()->id() }}">@endauth

    <script>
        (function () {
            try {
                var root = document.documentElement;
                var saved = localStorage.getItem('broxbhai-theme') || localStorage.getItem('app-theme');
                var valid = saved === 'dark' || saved === 'light';
                var theme = valid ? saved : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                root.setAttribute('data-theme', theme);
                root.style.colorScheme = theme;
            } catch (e) {}
        })();
    </script>

    <link rel="icon" type="image/x-icon" href="{{ asset('/assets/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Noto+Sans+Bengali:wght@100..900&display=swap"
          onload="this.onload=null;this.rel='stylesheet'">

    {{-- Restore saved sidebar width before first paint (avoids layout jump).
         Server-side per-user preference wins; localStorage is the offline fallback. --}}
    <script>
        (function () {
            try {
                var serverW = @js($sidebarWidth ?? null);
                var w = (serverW >= 200 && serverW <= 480) ? serverW
                    : parseInt(localStorage.getItem('admin.sidebar.width'), 10);
                if (!isNaN(w) && w >= 200 && w <= 480) {
                    var s = document.documentElement.style;
                    s.setProperty('--admin-sidebar-w', w + 'px');
                    s.setProperty('--admin-sidebar-ml', w + 'px');
                }
            } catch (e) {}
        })();
    </script>

    {{-- Legacy admin Tailwind bundle (same as legacy admin layout) --}}
    <link rel="stylesheet" href="@assetVersion('/assets/css/dist/tailwind-admin.css')">
    <link rel="stylesheet" href="{{ asset('/assets/css/admin/modules/admin-sidebar-polish.css') }}" onerror="this.remove()">
    <link rel="stylesheet" href="{{ asset('/cdn/css/lucide/lucide.css') }}">

    @stack('styles')
</head>
<body data-admin-dir="/admin"
      class="bg-slate-50 dark:bg-slate-950 antialiased text-slate-900 dark:text-slate-100">

@php
    // Grouped nav mirroring legacy admin/layout.twig — migrated pages get
    // their Laravel route names; everything else still points at legacy.
    // Every entry below is a real registered route — an item may carry a
    // 'children' list, which renders as a collapsible submenu (the submenu/
    // chevron CSS is already in the compiled tailwind-admin bundle). Items with
    // children are toggles; the first submenu entry always links to the section
    // index so the parent page is still one click away.
    //
    // Deliberately NOT listed: the /create, /edit, /delete and /view CRUD
    // variants — those are reached from the buttons on their list page, and
    // putting them in the nav would bury the real pages in noise.
    $adminNav = [
        ['label' => null, 'items' => [
            ['name' => 'Dashboard', 'icon' => 'lucide-gauge', 'url' => '/admin/dashboard', 'key' => 'dashboard'],
        ]],
        ['label' => 'Content', 'items' => [
            ['name' => 'Posts', 'icon' => 'lucide-file-text', 'url' => '/admin/posts', 'key' => 'posts', 'badge' => 'posts'],
            ['name' => 'Categories', 'icon' => 'lucide-folder', 'url' => '/admin/categories', 'key' => 'categories'],
            ['name' => 'Tags', 'icon' => 'lucide-hash', 'url' => '/admin/tags', 'key' => 'tags'],
            ['name' => 'Pages', 'icon' => 'lucide-file', 'url' => '/admin/pages', 'key' => 'pages'],
        ]],
        ['label' => 'Catalog', 'items' => [
            ['name' => 'Mobiles', 'icon' => 'lucide-smartphone', 'url' => '/admin/mobiles', 'key' => 'mobiles'],
            ['name' => 'Services', 'icon' => 'lucide-briefcase', 'url' => '/admin/services', 'key' => 'services'],
            ['name' => 'CV Builder', 'icon' => 'lucide-file-user', 'url' => '/admin/cv', 'key' => 'cv'],
        ]],
        ['label' => 'Hero Alif', 'items' => [
            ['name' => 'POS', 'icon' => 'lucide-scan-barcode', 'url' => '/admin/ha/pos', 'key' => 'ha-pos', 'perm' => 'ha.pos.operate'],
            ['name' => 'Customers', 'icon' => 'lucide-contact', 'url' => '/admin/ha/customers', 'key' => 'ha-customers', 'perm' => 'ha.customers.view'],
            ['name' => 'Sales', 'icon' => 'lucide-receipt', 'url' => '/admin/ha/sales', 'key' => 'ha-sales', 'perm' => 'ha.sales.view'],
            ['name' => 'Online Orders', 'icon' => 'lucide-truck', 'url' => '/admin/ha/orders', 'key' => 'ha-orders', 'perm' => 'ha.orders.view'],
            ['name' => 'Cash Register', 'icon' => 'lucide-calculator', 'url' => '/admin/ha/register', 'key' => 'ha-register', 'perm' => 'ha.registers.manage'],
            ['name' => 'Service Requests', 'icon' => 'lucide-file-clock', 'url' => '/admin/ha/services', 'key' => 'ha-services', 'perm' => 'ha.services.view'],
            ['name' => 'Service Categories', 'icon' => 'lucide-list-tree', 'url' => '/admin/ha/services/categories', 'key' => 'ha-services-categories', 'perm' => 'ha.services.categories'],
            ['name' => 'Products', 'icon' => 'lucide-package', 'url' => '/admin/ha/products', 'key' => 'ha-products', 'perm' => 'ha.products.manage'],
            ['name' => 'Categories', 'icon' => 'lucide-folder-tree', 'url' => '/admin/ha/categories', 'key' => 'ha-categories', 'perm' => 'ha.products.manage'],
            ['name' => 'Brands', 'icon' => 'lucide-badge', 'url' => '/admin/ha/brands', 'key' => 'ha-brands', 'perm' => 'ha.products.manage'],
            ['name' => 'Suppliers', 'icon' => 'lucide-truck', 'url' => '/admin/ha/suppliers', 'key' => 'ha-suppliers', 'perm' => 'ha.purchases.manage'],
            ['name' => 'Purchases', 'icon' => 'lucide-shopping-cart', 'url' => '/admin/ha/purchases', 'key' => 'ha-purchases', 'perm' => 'ha.purchases.manage'],
            ['name' => 'Stock Ledger', 'icon' => 'lucide-arrow-down-up', 'url' => '/admin/ha/inventory/movements', 'key' => 'ha-movements', 'perm' => 'ha.purchases.view'],
            ['name' => 'Reports & P&L', 'icon' => 'lucide-chart-line', 'url' => '/admin/ha/reports', 'key' => 'ha-reports', 'perm' => 'ha.reports.view'],
        ]],
        ['label' => 'Wallet', 'items' => [
            ['name' => 'Recharges', 'icon' => 'lucide-wallet', 'url' => '/admin/wallet/recharges', 'key' => 'wallet-recharges'],
            ['name' => 'Ledger', 'icon' => 'lucide-history', 'url' => '/admin/wallet/transactions', 'key' => 'wallet-ledger'],
            ['name' => 'User Balances', 'icon' => 'lucide-users', 'url' => '/admin/wallet/users', 'key' => 'wallet-users'],
        ]],
        ['label' => 'People', 'items' => [
            ['name' => 'Users', 'icon' => 'lucide-users', 'url' => '/admin/users', 'key' => 'users'],
            ['name' => 'Roles', 'icon' => 'lucide-shield', 'url' => '/admin/roles', 'key' => 'roles'],
            ['name' => 'Permissions', 'icon' => 'lucide-key-round', 'url' => '/admin/permissions', 'key' => 'permissions'],
        ]],
        ['label' => 'Revenue', 'items' => [
            ['name' => 'Revenue', 'icon' => 'lucide-trending-up', 'url' => '/admin/revenue', 'key' => 'revenue', 'children' => [
                ['name' => 'Sponsored', 'url' => '/admin/revenue/sponsored'],
            ]],
            ['name' => 'Ads', 'icon' => 'lucide-megaphone', 'url' => '/admin/revenue/ads', 'key' => 'revenue-ads', 'children' => [
                ['name' => 'Analytics', 'url' => '/admin/revenue/ads/analytics'],
                ['name' => 'Campaigns', 'url' => '/admin/revenue/ads/campaigns'],
                ['name' => 'Placements', 'url' => '/admin/revenue/ads/placements'],
                ['name' => 'Settings', 'url' => '/admin/revenue/ads/settings'],
            ]],
            ['name' => 'Donations', 'icon' => 'lucide-hand-heart', 'url' => '/admin/revenue/donations', 'key' => 'revenue-donations', 'children' => [
                ['name' => 'bKash', 'url' => '/admin/revenue/donations/bkash'],
                ['name' => 'Nagad', 'url' => '/admin/revenue/donations/nagad'],
                ['name' => 'Rocket', 'url' => '/admin/revenue/donations/rocket'],
            ]],
        ]],
        ['label' => 'Engagement', 'items' => [
            ['name' => 'Notifications', 'icon' => 'lucide-bell', 'url' => '/admin/notifications', 'key' => 'notifications', 'children' => [
                ['name' => 'Schedule', 'url' => '/admin/notifications/schedule'],
            ]],
            ['name' => 'Live TV', 'icon' => 'lucide-tv', 'url' => '/admin/livetv', 'key' => 'livetv', 'children' => [
                ['name' => 'Channels', 'url' => '/admin/livetv/channels'],
                ['name' => 'Proxy', 'url' => '/admin/livetv/proxy'],
            ]],
            ['name' => 'Kharij', 'icon' => 'lucide-graduation-cap', 'url' => '/admin/kharij', 'key' => 'kharij'],
            ['name' => 'Weather', 'icon' => 'lucide-cloud-sun', 'url' => '/admin/weather', 'key' => 'weather', 'children' => [
                ['name' => 'API', 'url' => '/admin/weather/api'],
                ['name' => 'Locations', 'url' => '/admin/weather/locations'],
            ]],
        ]],
        ['label' => 'Tools', 'items' => [
            ['name' => 'AI System', 'icon' => 'lucide-brain', 'url' => '/admin/aisystem', 'key' => 'aisystem', 'children' => [
                ['name' => 'Chat', 'url' => '/admin/aisystem/chat'],
                ['name' => 'Writer', 'url' => '/admin/aisystem/writer'],
                ['name' => 'Knowledge', 'url' => '/admin/aisystem/knowledge'],
                ['name' => 'Providers', 'url' => '/admin/aisystem/providers'],
                ['name' => 'Analytics', 'url' => '/admin/aisystem/analytics'],
            ]],
            ['name' => 'OCR', 'icon' => 'lucide-scan-text', 'url' => '/admin/ocr', 'key' => 'ocr', 'children' => [
                ['name' => 'History', 'url' => '/admin/ocr/history'],
                ['name' => 'Settings', 'url' => '/admin/ocr/settings'],
                ['name' => 'Test', 'url' => '/admin/ocr/test'],
            ]],
            ['name' => 'Photo Studio', 'icon' => 'lucide-image', 'url' => '/admin/photo-studio', 'key' => 'photo-studio', 'children' => [
                ['name' => 'Editor', 'url' => '/admin/photo-studio/editor'],
                ['name' => 'Cutout', 'url' => '/admin/photo-studio/cutout'],
                ['name' => 'History', 'url' => '/admin/photo-studio/history'],
            ]],
            ['name' => 'Calculator', 'icon' => 'lucide-calculator', 'url' => '/admin/calculator', 'key' => 'calculator', 'children' => [
                ['name' => 'GPA', 'url' => '/admin/calculator/gpa'],
                ['name' => 'Loan', 'url' => '/admin/calculator/loan'],
                ['name' => 'Widgets', 'url' => '/admin/calculator/widgets'],
            ]],
            ['name' => 'API Proxy', 'icon' => 'lucide-plug', 'url' => '/admin/api-proxy', 'key' => 'api-proxy', 'children' => [
                ['name' => 'Firebase', 'url' => '/admin/api-proxy/firebase'],
                ['name' => 'Pexels', 'url' => '/admin/api-proxy/pexels'],
                ['name' => 'Pixabay', 'url' => '/admin/api-proxy/pixabay'],
                ['name' => 'Puter', 'url' => '/admin/api-proxy/puter'],
            ]],
            ['name' => 'Scraper', 'icon' => 'lucide-download', 'url' => '/admin/scraper', 'key' => 'scraper', 'children' => [
                ['name' => 'Sources', 'url' => '/admin/scraper/sources'],
                ['name' => 'Jobs', 'url' => '/admin/scraper/jobs'],
                ['name' => 'Logs', 'url' => '/admin/scraper/logs'],
                ['name' => 'Settings', 'url' => '/admin/scraper/settings'],
                ['name' => 'Automation', 'url' => '/admin/scraper/settings/automation'],
                ['name' => 'Limits', 'url' => '/admin/scraper/settings/limits'],
                ['name' => 'Storage', 'url' => '/admin/scraper/settings/storage'],
            ]],
        ]],
        ['label' => 'Insights', 'items' => [
            ['name' => 'Logs', 'icon' => 'lucide-scroll-text', 'url' => '/admin/logs', 'key' => 'logs'],
            ['name' => 'Sitemap', 'icon' => 'lucide-map', 'url' => '/admin/sitemap', 'key' => 'sitemap', 'children' => [
                ['name' => 'History', 'url' => '/admin/sitemap/history'],
            ]],
        ]],
        ['label' => 'Settings', 'items' => [
            ['name' => 'Security', 'icon' => 'lucide-lock', 'url' => '/admin/security', 'key' => 'security', 'children' => [
                ['name' => 'Authentication', 'url' => '/admin/security/auth'],
                ['name' => 'reCAPTCHA', 'url' => '/admin/security/recaptcha'],
                ['name' => 'SMTP', 'url' => '/admin/security/smtp'],
            ]],
            ['name' => 'Navigation', 'icon' => 'lucide-menu', 'url' => '/admin/navigation', 'key' => 'navigation'],
            ['name' => 'Setup', 'icon' => 'lucide-wrench', 'url' => '/admin/setup', 'key' => 'setup'],
            // The admin's own account (owner-scoped, admin chrome).
            ['name' => 'My Profile', 'icon' => 'lucide-user-round', 'url' => '/admin/profile', 'key' => 'profile'],
            ['name' => 'My Account', 'icon' => 'lucide-settings', 'url' => '/admin/account-settings', 'key' => 'account-settings'],
            ['name' => 'My Notifications', 'icon' => 'lucide-bell', 'url' => '/admin/my/notifications', 'key' => 'my-notifications'],
        ]],
    ];
    $currentPath = request()->path() === '/' ? '/' : '/'.request()->path();

    // Groups stay collapsed unless the current path is inside them, so the
    // active page is always visible on load.
    $navChildrenActive = function (array $children) use ($currentPath): bool {
        foreach ($children as $child) {
            if ($currentPath === $child['url'] || str_starts_with($currentPath, rtrim($child['url'], '/').'/')) {
                return true;
            }
        }

        return false;
    };
@endphp

<div class="min-h-screen flex flex-col">

    {{-- Top header --}}
    <header class="sticky top-0 z-40 h-14 flex items-center
                   border-b border-slate-200/70 dark:border-slate-800/70
                   bg-white/95 dark:bg-slate-950/95 backdrop-blur-xl">
        <div class="flex items-center w-full px-4 sm:px-5 gap-3">

            <button type="button" aria-label="{{ t('Open navigation') }}"
                    class="lg:hidden w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-500 hover:bg-slate-100"
                    x-data x-on:click="$dispatch('toggle-sidebar')">
                <i class="lucide lucide-menu w-4 h-4"></i>
            </button>

            <a href="/admin/dashboard" class="group flex items-center gap-2.5 flex-shrink-0 mr-1">
                <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shadow-md shadow-indigo-500/25 group-hover:bg-indigo-700 transition-all">
                    <i class="lucide lucide-gauge w-4 h-4 text-white"></i>
                </div>
                <div class="hidden sm:block leading-none">
                    <div class="text-[13px] font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 transition-colors">{{ $appSettings['site_name'] ?? 'BroxLab' }}</div>
                    <div class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-0.5">{{ t('Admin Panel') }}</div>
                </div>
            </a>

            <div class="ml-auto flex items-center gap-1">
                {{-- Notifications bell --}}
                <div class="relative">
                    <button id="adminNotificationBell" type="button" aria-expanded="false" aria-label="{{ t('Notifications') }}"
                            title="{{ t('Notifications') }}"
                            class="relative inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-700
                                   bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400
                                   hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-indigo-600 dark:hover:text-indigo-400
                                   focus-visible:ring-2 focus-visible:ring-indigo-500/30">
                        <i class="lucide lucide-bell w-4 h-4"></i>
                        @php $adminUnreadCount = $admin_unread_count ?? 0; @endphp
                        @if($adminUnreadCount > 0)
                            <span class="badge-pulse absolute -top-1 -right-1 flex items-center justify-center
                                         min-w-[1.125rem] h-[1.125rem] text-[9px] font-bold text-white
                                         bg-rose-500 rounded-full px-1 shadow-sm">
                                <span id="adminNotificationCount">{{ $adminUnreadCount }}</span>
                            </span>
                        @endif
                    </button>
                </div>

                {{-- Theme toggle --}}
                <button type="button" title="{{ t('Toggle theme') }}"
                        x-data="{ t: document.documentElement.getAttribute('data-theme') }"
                        x-on:click="t = t === 'dark' ? 'light' : 'dark'; document.documentElement.setAttribute('data-theme', t); document.documentElement.style.colorScheme = t; localStorage.setItem('broxbhai-theme', t);"
                        class="w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-700
                               bg-white dark:bg-slate-900
                               text-amber-500 dark:text-amber-400
                               hover:bg-amber-50 dark:hover:bg-amber-950/30
                               focus-visible:ring-2 focus-visible:ring-amber-500/30">
                    <i class="lucide lucide-sun dark:hidden block w-4 h-4"></i>
                    <i class="lucide lucide-moon hidden dark:block w-4 h-4"></i>
                </button>

                {{-- Server status --}}
                <div class="relative">
                    <button id="serverStatusIndicator" type="button" aria-expanded="false"
                            title="{{ t('Server status') }}" aria-label="{{ t('Server status') }}"
                            class="inline-flex items-center gap-1.5 h-9 px-2.5 rounded-xl
                                   border border-slate-200 dark:border-slate-700
                                   bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400
                                   hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-indigo-600 dark:hover:text-indigo-400
                                   focus-visible:ring-2 focus-visible:ring-indigo-500/30">
                        <i class="lucide lucide-server w-3.5 h-3.5" id="serverStatusIcon"></i>
                        <span class="hidden md:inline text-xs font-medium" id="serverStatusText">Checking</span>
                        <i class="lucide lucide-chevron-down w-3 h-3 text-slate-400/70"></i>
                    </button>
                </div>

                <div class="hidden lg:block h-5 w-px bg-slate-200 dark:bg-slate-800 mx-1" aria-hidden="true"></div>

                {{-- User menu --}}
                @if(auth()->check())
                <div class="relative" data-admin-user-menu>
                    <div class="hidden lg:block h-5 w-px bg-slate-200 dark:bg-slate-800 mx-1" aria-hidden="true"></div>
                    <button type="button" id="adminUserMenu" aria-expanded="false" aria-haspopup="true"
                            class="inline-flex items-center gap-2 h-9 px-2 rounded-xl
                                   hover:bg-slate-100 dark:hover:bg-slate-800/80
                                   transition-all duration-150 group
                                   focus-visible:ring-2 focus-visible:ring-indigo-500/30 focus-visible:outline-none">
                        @if (!empty($admin_user->profile_pic))
                            <img src="{{ asset($admin_user->profile_pic) }}" alt="{{ t('Profile') }}" width="28" height="28"
                                 class="w-7 h-7 rounded-lg object-cover ring-1 ring-slate-200 dark:ring-slate-700
                                        group-hover:ring-indigo-300 dark:group-hover:ring-indigo-700 transition-all">
                        @else
                            <span class="flex w-7 h-7 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-bold text-white">
                                {{ mb_strtoupper(mb_substr($admin_user->username ?? 'A', 0, 1)) }}
                            </span>
                        @endif
                        <div class="hidden sm:block text-left min-w-0 max-w-[100px]">
                            <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate leading-tight
                                        group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{{ $admin_user->username ?? '' }}</div>
                            @if (!empty($user_roles))
                                <div class="text-[10px] font-medium text-indigo-500 dark:text-indigo-400 truncate leading-tight">{{ \Illuminate\Support\Str::upper($user_roles[0]['name'] ?? '') }}</div>
                            @endif
                        </div>
                        <i class="lucide lucide-chevron-down w-3 h-3 text-slate-400 transition-transform duration-200 group-aria-expanded:rotate-180"></i>
                    </button>

                    <div data-admin-user-dropdown
                         class="absolute right-0 top-full mt-2 w-60
                                bg-white dark:bg-slate-900
                                rounded-2xl shadow-xl shadow-black/10 dark:shadow-black/40
                                border border-slate-200/80 dark:border-slate-700/60
                                divide-y divide-slate-100 dark:divide-slate-800
                                opacity-0 invisible pointer-events-none z-50 overflow-hidden">
                        <div class="px-4 py-4 bg-gradient-to-br from-indigo-50 to-slate-50 dark:from-indigo-950/30 dark:to-slate-900/30">
                            <div class="flex items-center gap-3">
                                <div class="relative flex-shrink-0">
                                    <img src="{{ $admin_user->profile_pic ?? asset('/assets/images/default-avatar.png') }}"
                                         alt="{{ t('Profile') }}" width="40" height="40"
                                         class="w-10 h-10 rounded-xl object-cover ring-2 ring-white dark:ring-slate-800 shadow-sm"
                                         loading="lazy">
                                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500
                                             border-2 border-white dark:border-slate-900"></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $admin_user->username ?? '' }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $admin_user->email ?? '' }}</div>
                                    @if (!empty($user_roles))
                                        <span class="mt-1.5 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md
                                                     text-[10px] font-semibold
                                                     bg-indigo-100 dark:bg-indigo-900/30
                                                     text-indigo-700 dark:text-indigo-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            {{ \Illuminate\Support\Str::title($user_roles[0]['name'] ?? '') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="p-1.5 space-y-0.5">
                            <a href="/admin/account-settings" class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 dark:text-slate-300
                                                                  rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800
                                                                  hover:text-indigo-700 dark:hover:text-indigo-400 transition-all duration-150">
                                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                    <i class="lucide lucide-settings w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400"></i>
                                </div>
                                <span class="font-medium">{{ t('Account Settings') }}</span>
                            </a>
                            <a href="/admin/profile" class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 dark:text-slate-300
                                                                  rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800
                                                                  hover:text-indigo-700 dark:hover:text-indigo-400 transition-all duration-150">
                                <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                                    <i class="lucide lucide-user-circle w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400"></i>
                                </div>
                                <span class="font-medium">{{ t('My Profile') }}</span>
                            </a>
                            <a href="/admin/my/notifications" class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 dark:text-slate-300
                                                                  rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800
                                                                  hover:text-indigo-700 dark:hover:text-indigo-400 transition-all duration-150">
                                <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                                    <i class="lucide lucide-bell w-3.5 h-3.5 text-amber-600 dark:text-amber-400"></i>
                                </div>
                                <span class="font-medium">{{ t('My Notifications') }}</span>
                            </a>
                        </div>
                        <div class="p-1.5">
                            <form method="POST" action="/logout">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm font-medium
                                                             text-red-600 dark:text-red-400 rounded-xl
                                                             hover:bg-red-50 dark:hover:bg-red-950/20 transition-all duration-150 group">
                                    <div class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/20 flex items-center justify-center flex-shrink-0
                                                group-hover:bg-red-100 dark:group-hover:bg-red-900/30 transition-colors">
                                        <i class="lucide lucide-log-out w-3.5 h-3.5 text-red-500 dark:text-red-400"></i>
                                    </div>
                                    <span>{{ t('Sign out') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">

        {{-- Mobile/tablet overlay: dims the page behind the sidebar drawer
             (<1024px) and closes the drawer on tap. Visibility follows the
             body.admin-sidebar-open class toggled by the aside's x-effect. --}}
    <div id="sidebarOverlay"
         x-data
         x-on:click="$dispatch('toggle-sidebar')"
         role="presentation"
         class="fixed inset-0 bg-black/50 backdrop-blur-sm z-30 lg:hidden transition-opacity duration-300"
         style="opacity:0; pointer-events:none;">
    </div>

    {{-- Sidebar --}}
    <aside id="adminSidebar" x-data="{ open: false, mini: false }" x-on:toggle-sidebar.window="open = !open"
           x-effect="document.body.classList.toggle('admin-sidebar-open', open)"
           class="w-[220px] flex-shrink-0 bg-white dark:bg-slate-950
                  border-r border-slate-200/70 dark:border-slate-800/70
                  overflow-y-auto overflow-x-hidden transition-all duration-300 ease-out
              fixed left-0 top-0 h-screen z-40
              -translate-x-full lg:translate-x-0"
           :class="open && '!translate-x-0'"
           aria-label="{{ t('Primary navigation') }}">

        <div class="flex items-center justify-between h-10 px-3.5 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/60 dark:bg-slate-900/30">
            <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-600 select-none flex items-center gap-1.5">
                <i class="lucide lucide-layout-dashboard w-3 h-3"></i> Navigation
            </span>
            <button type="button" id="sidebarMiniToggle" aria-label="{{ t('Collapse sidebar') }}" aria-pressed="false"
                    class="hidden lg:inline-flex items-center justify-center w-6 h-6 rounded-lg
                           hover:bg-slate-200 dark:hover:bg-slate-800
                           text-slate-400 dark:text-slate-600
                           hover:text-indigo-600 dark:hover:text-indigo-400
                           transition-all duration-150
                           focus-visible:ring-2 focus-visible:ring-indigo-500/30 focus-visible:outline-none">
                <i class="lucide lucide-chevrons-left w-3.5 h-3.5"></i>
            </button>
        </div>

            <nav class="px-2 py-2 space-y-1">
                @foreach ($adminNav as $group)
                    @if ($group['label'])
                        <div class="sidebar-group-label px-2 pt-2 pb-1 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-600">{{ $group['label'] }}</div>
                    @endif
                    @foreach ($group['items'] as $item)
                        @php
                            $children = $item['children'] ?? [];
                            $hasChildren = $children !== [];
                            // Phase 8: hide sidebar items for which the current user lacks the permission.
                            // Super admins see everything. Non-super users only see items whose `perm`
                            // (if defined) they hold — keeps the sidebar clean for scoped roles.
                            if (isset($item['perm']) && ! \App\Support\HaPermissions::for(auth()->user())->has($item['perm'] ?? null)) {
                                continue;
                            }
                            $childActive = $hasChildren && $navChildrenActive($children);
                            $itemActive = $currentPath === $item['url']
                                || str_starts_with($currentPath, rtrim($item['url'], '/').'/')
                                || ($item['key'] ?? '') === request()->get('tab');
                        @endphp

                        @if (! $hasChildren)
                            <a href="{{ $item['url'] }}"
                               class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-sm font-medium transition-all duration-150
                                      {{ $itemActive
                                          ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300'
                                          : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-indigo-600 dark:hover:text-indigo-400' }}">
                                <i class="lucide {{ $item['icon'] }} w-4 h-4 flex-shrink-0"></i>
                                <span class="truncate">{{ $item['name'] }}</span>
                                @if (!empty($item['badge']))
                                    <span class="sidebar-badge ml-auto hidden items-center justify-center min-w-[1.1rem] h-[1.1rem] px-1 text-[9px] font-bold text-white bg-rose-500 rounded-full" data-badge-key="{{ $item['badge'] }}"></span>
                                @endif
                            </a>
                        @else
                            <button type="button" data-sidebar-group
                                    aria-expanded="{{ $childActive ? 'true' : 'false' }}"
                                    class="flex w-full items-center gap-2.5 px-2.5 py-2 rounded-xl text-sm font-medium transition-all duration-150 text-left
                                           {{ $itemActive || $childActive
                                               ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300'
                                               : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-indigo-600 dark:hover:text-indigo-400' }}">
                                <i class="lucide {{ $item['icon'] }} w-4 h-4 flex-shrink-0"></i>
                                <span class="truncate">{{ $item['name'] }}</span>
                                <i class="lucide lucide-chevron-down sidebar-chevron"></i>
                            </button>
                            <div class="sidebar-submenu {{ $childActive ? 'open' : '' }}">
                                <a href="{{ $item['url'] }}"
                                   class="sidebar-sublink {{ $currentPath === $item['url'] ? 'active' : '' }}">{{ t('Overview') }}</a>
                                @foreach ($children as $child)
                                    <a href="{{ $child['url'] }}"
                                       class="sidebar-sublink {{ $currentPath === $child['url'] || str_starts_with($currentPath, rtrim($child['url'], '/').'/') ? 'active' : '' }}">{{ $child['name'] }}</a>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                @endforeach

                <div class="pt-3 mt-2 border-t border-slate-100 dark:border-slate-800/80">
                    <div class="px-2 pb-1 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-600">{{ t('Back to site') }}</div>
                    <a href="/" class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-indigo-600">
                        <i class="lucide lucide-arrow-left w-4 h-4 flex-shrink-0"></i>
                        <span>{{ t('Public site') }}</span>
                    </a>
                </div>
            </nav>
        </aside>

        {{-- Desktop drag-to-resize handle (hidden on mobile; styled in plain CSS below) --}}
        <div id="adminSidebarResizer"
             role="separator" aria-orientation="vertical" tabindex="0"
             aria-valuemin="200" aria-valuemax="480"
             aria-label="{{ t('Resize sidebar') }}"
             title="{{ t('Drag to resize sidebar') }}">
            <span id="adminSidebarResizerChip" aria-hidden="true"></span>
        </div>

        {{-- Snap guides at default (220) and comfortable (320) widths —
             visible only while dragging, highlighted when snapped --}}
        <div class="admin-snap-guide" id="adminSnapGuideDefault" style="left:220px" aria-hidden="true"></div>
        <div class="admin-snap-guide" id="adminSnapGuideComfort" style="left:320px" aria-hidden="true"></div>

        {{-- Content --}}
    <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-6 lg:p-8">
        @if (session('status'))
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
        @if (session('error'))
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
    </div>
</div>

{{-- Sidebar submenu toggles (plain JS so they work before Alpine loads) --}}
    <script>
        (function () {
            // ---- Sidebar resize handle: drag / keyboard / persist ----
            var MIN_W = 200, MAX_W = 480, DEFAULT_W = 220, STORE_KEY = 'admin.sidebar.width';
            var sidebar = document.getElementById('adminSidebar');
            var handle = document.getElementById('adminSidebarResizer');
            if (sidebar && handle) {
            var root = document.documentElement;

            // Snap points: default + comfortable widths (drag only).
            // Declared before setWidth() which reads SNAP_LABELS on init.
            var SNAP_PTS = [220, 320];
            var SNAP_LABELS = { 220: 'Default', 320: 'Comfortable' };
            var SNAP_TOLERANCE = 8;
            function applySnap(px) {
                for (var i = 0; i < SNAP_PTS.length; i++) {
                    if (Math.abs(px - SNAP_PTS[i]) <= SNAP_TOLERANCE) return SNAP_PTS[i];
                }
                return px;
            }
            var chip = document.getElementById('adminSidebarResizerChip');
            var guideDefault = document.getElementById('adminSnapGuideDefault');
            var guideComfort = document.getElementById('adminSnapGuideComfort');
            function updateSnapVisuals(px) {
                var snapped = SNAP_LABELS[px] || '';
                if (chip) {
                    chip.textContent = snapped ? px + ' \u00b7 ' + snapped : px + ' px';
                    chip.classList.toggle('snap', !!snapped);
                }
                if (guideDefault) guideDefault.classList.toggle('snap', px === 220);
                if (guideComfort) guideComfort.classList.toggle('snap', px === 320);
            }

            function clampWidth(px) {
                    return Math.round(Math.min(MAX_W, Math.max(MIN_W, px)));
                }
                // Authoritative width is tracked here — reading it back from
                // getComputedStyle() mid-transition returns animated snapshots.
                var cur = function () {
                    var saved = parseInt(localStorage.getItem(STORE_KEY), 10);
                    return isNaN(saved) ? DEFAULT_W : saved;
                }();
                function setWidth(px) {
                    cur = clampWidth(px);
                    root.style.setProperty('--admin-sidebar-w', cur + 'px');
                    root.style.setProperty('--admin-sidebar-ml', cur + 'px');
                    handle.setAttribute('aria-valuenow', String(cur));
                    handle.setAttribute('aria-valuetext', cur + ' pixels' + (SNAP_LABELS[cur] ? ', ' + SNAP_LABELS[cur].toLowerCase() : ''));
                    return cur;
                }
                setWidth(cur);
                function currentWidth() {
                    return cur;
                }
                function persist() {
                    try { localStorage.setItem(STORE_KEY, String(cur)); } catch (e) { /* private mode */ }
                    saveToServer(cur);
                }

                // Per-user persistence: PUT /admin/api/sidebar-width (debounced).
                // Failures are silent — localStorage keeps working as fallback.
                var saveTimer = null;
                var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                function saveToServer(px) {
                    if (saveTimer) clearTimeout(saveTimer);
                    saveTimer = setTimeout(function () {
                        fetch('/admin/api/sidebar-width', {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ width: px }),
                            keepalive: true
                        }).catch(function () { /* offline / session gone — local persists */ });
                    }, 600);
                }
                function resetWidth() {
                    setWidth(DEFAULT_W);
                    persist();
                }

                var dragging = false, startX = 0, startWidth = 0;

                function isDrawerMode() {
                    return !window.matchMedia('(min-width: 1024px)').matches;
                }

                handle.addEventListener('pointerdown', function (e) {
                    if (e.button !== 0 || document.body.classList.contains('sidebar-collapsed')) return;
                    if (isDrawerMode() && !document.body.classList.contains('admin-sidebar-open')) return;
                    dragging = true;
                    startX = e.clientX;
                    startWidth = currentWidth();
                    try { handle.setPointerCapture(e.pointerId); } catch (err) { /* older browsers */ }
                    document.body.classList.add('sidebar-resizing');
                    updateSnapVisuals(startWidth);
                    e.preventDefault();
                });

                handle.addEventListener('pointermove', function (e) {
                    if (!dragging) return;
                    var px = setWidth(applySnap(startWidth + (e.clientX - startX)));
                    updateSnapVisuals(px);
                });

                function endDrag(e) {
                    if (!dragging) return;
                    dragging = false;
                    document.body.classList.remove('sidebar-resizing');
                    if (e && e.pointerId !== undefined) {
                        try { handle.releasePointerCapture(e.pointerId); } catch (err) { /* already released */ }
                    }
                    persist();
                    // On tablets the sidebar is an overlay drawer — after saving
                    // the new width, close the drawer so the page is usable.
                    if (isDrawerMode() && window.Alpine) {
                        var data = Alpine.$data(sidebar);
                        if (data && 'open' in data) { data.open = false; }
                    }
                }
                handle.addEventListener('pointerup', endDrag);
                handle.addEventListener('pointercancel', endDrag);

                // Double-click snaps back to the default width
                handle.addEventListener('dblclick', resetWidth);

                // Keyboard: arrows nudge (Shift = coarse step), Home/Enter resets
                handle.addEventListener('keydown', function (e) {
                    var step = e.shiftKey ? 48 : 16;
                    if (e.key === 'ArrowLeft') { setWidth(currentWidth() - step); persist(); e.preventDefault(); }
                    else if (e.key === 'ArrowRight') { setWidth(currentWidth() + step); persist(); e.preventDefault(); }
                    else if (e.key === 'Home' || e.key === 'Enter') { resetWidth(); e.preventDefault(); }
                });
            }

            function wire() {
                document.querySelectorAll('[data-sidebar-group]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var sub = btn.nextElementSibling;
                        if (!sub) { return; }
                        var open = sub.classList.toggle('open');
                        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    });
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', wire);
            } else {
                wire();
            }
        })();
    </script>

    {{-- Alpine bundle for interactivity --}}    <script defer src="@assetVersion('/assets/laravel/dist/app.js')"></script>
    <style>
        [x-cloak]{display:none!important}
        .sidebar-group-label { @apply px-3 pt-5 pb-1.5 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-600 select-none; }
        .sidebar-link-modern { @apply flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 hover:text-indigo-700 dark:hover:text-indigo-300 transition-all duration-150 ease-out cursor-pointer w-full; }
        .sidebar-link-modern.active { @apply bg-indigo-600 text-white shadow-sm shadow-indigo-500/25 hover:bg-indigo-700 hover:text-white; }
        .sidebar-link-modern.active .sidebar-icon { @apply text-white/90; }
        .sidebar-icon { @apply w-4 h-4 flex-shrink-0 text-slate-400; }
        .sidebar-link-modern:hover .sidebar-icon { @apply text-indigo-500; }
        .sidebar-submenu { @apply hidden ml-7 mt-1 space-y-0.5 border-l border-slate-200 dark:border-slate-800 pl-3; }
        .sidebar-submenu.open { @apply block; }
        .sidebar-sublink { @apply block rounded-lg px-3 py-2 text-xs font-medium text-slate-500 dark:text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-indigo-700 dark:hover:text-indigo-400 transition-all duration-150; }
        .sidebar-sublink.active { @apply text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 font-semibold; }
        .sidebar-chevron { @apply w-3.5 h-3.5 text-slate-400 ml-auto transition-transform duration-200 flex-shrink-0; }
        [aria-expanded="true"] .sidebar-chevron { @apply rotate-180; }
        .sidebar-badge { @apply ml-auto inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] rounded-full bg-rose-500 text-[9px] font-bold text-white px-1 leading-none; }
        .topbar-btn { @apply inline-flex items-center justify-center rounded-xl transition-all duration-150 active:scale-95 focus-visible:outline-none; }
        @keyframes badge-pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.15); } }
        .badge-pulse { animation: badge-pulse 2s ease-in-out infinite; }
        .scroll-fade-in { opacity: 0; transform: translateY(12px); transition: opacity 0.4s ease, transform 0.4s ease; }
        .scroll-fade-in.visible { opacity: 1; transform: none; }
        .scroll-slide-up { opacity: 0; transform: translateY(20px); transition: opacity 0.45s ease, transform 0.45s ease; }
        .scroll-slide-up.visible { opacity: 1; transform: none; }
        .scroll-scale-in { opacity: 0; transform: scale(0.97); transition: opacity 0.35s ease, transform 0.35s ease; }
        .scroll-scale-in.visible { opacity: 1; transform: none; }
        [data-admin-user-dropdown] { transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s; transform: translateY(-6px) scale(0.97); }
        [data-admin-user-dropdown].open { opacity: 1 !important; visibility: visible !important; transform: translateY(0) scale(1) !important; pointer-events: auto !important; }
        .search-input:focus { box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
        #adminSidebar.compact { @apply w-[60px]; }
        #adminSidebar.compact .sidebar-group-label,
        #adminSidebar.compact .sidebar-sublink,
        #adminSidebar.compact span.truncate,
        #adminSidebar.compact .sidebar-badge,
        #adminSidebar.compact .sidebar-chevron { @apply hidden; }
        #adminSidebar.compact .sidebar-link-modern { @apply justify-center px-0; }
        #adminSidebar.compact .sidebar-icon { @apply w-5 h-5; }
        #adminSidebar.compact nav { @apply px-1; }

        /* ---- Sidebar resize handle (drag to adjust width) ---- */
        /* Sidebar width and main offset follow the same variables so they stay
           in sync; Tailwind w-[220px] remains the phone drawer width. Plain
           CSS only — the compiled Tailwind bundle may not contain new classes.
           Desktop (>=1024px): inline sidebar, handle always available.
           Tablet (768–1023px): the sidebar is an overlay drawer, so the handle
           appears only while the drawer is open (body.admin-sidebar-open is
           toggled by Alpine x-effect on the aside). */
        #adminSidebarResizer {
            display: none;
            --admin-resizer-hit: 10px;
            cursor: col-resize;
            touch-action: none;
            user-select: none;
            -webkit-user-select: none;
        }
        /* Larger grab target for fingers (touch/tablet) */
        @media (pointer: coarse) {
            #adminSidebarResizer { --admin-resizer-hit: 18px; }
        }
        @media (min-width: 1024px) {
            #adminSidebar { width: var(--admin-sidebar-w, 220px); }
            body:not(.sidebar-collapsed) main { margin-left: var(--admin-sidebar-ml, 220px); }
            #adminSidebarResizer {
                display: block;
                position: fixed;
                top: 0;
                left: calc(var(--admin-sidebar-w, 220px) - var(--admin-resizer-hit) / 2);
                width: var(--admin-resizer-hit);
                height: 100vh;
                z-index: 50;
            }
            body.sidebar-collapsed #adminSidebarResizer { display: none; }
        }
        /* Drawer backdrop: visible below 1024px while the drawer is open.
           Inline style on #sidebarOverlay needs !important to be overridden. */
        @media (max-width: 1023.98px) {
            body.admin-sidebar-open #sidebarOverlay {
                opacity: 1 !important;
                pointer-events: auto !important;
            }
        }
        @media (min-width: 768px) and (max-width: 1023.98px) {
            #adminSidebar { width: var(--admin-sidebar-w, 220px); }
            body.admin-sidebar-open #adminSidebarResizer {
                display: block;
                position: fixed;
                top: 4rem;
                left: calc(var(--admin-sidebar-w, 220px) - var(--admin-resizer-hit) / 2);
                width: var(--admin-resizer-hit);
                height: calc(100vh - 4rem);
                z-index: 50;
            }
        }
        #adminSidebarResizer::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 4px;
            height: 44px;
            transform: translate(-50%, -50%);
            border-radius: 9999px;
            background-color: rgb(203, 213, 225);
            transition: background-color 0.15s ease;
        }
        /* Dark mode is attribute-based here: html[data-theme=dark] */
        [data-theme="dark"] #adminSidebarResizer::after { background-color: rgb(51, 65, 85); }
        [data-theme="dark"] #adminSidebarResizer:hover::after,
        [data-theme="dark"] #adminSidebarResizer:focus-visible::after { background-color: rgb(129, 140, 248); }
        #adminSidebarResizer:focus-visible { outline: 2px solid rgb(99, 102, 241); outline-offset: -2px; }
        body.sidebar-resizing #adminSidebar,
        body.sidebar-resizing main { transition: none !important; }
        body.sidebar-resizing { cursor: col-resize; user-select: none; -webkit-user-select: none; }

        /* ---- Snap guides + live width chip ---- */
        .admin-snap-guide {
            display: none;
            position: fixed;
            top: 0;
            height: 100vh;
            width: 0;
            border-left: 1px dashed rgba(99, 102, 241, 0.35);
            z-index: 45;
            pointer-events: none;
        }
        body.sidebar-resizing .admin-snap-guide { display: block; }
        .admin-snap-guide.snap { border-left: 2px solid rgb(99, 102, 241); }
        #adminSidebarResizerChip {
            display: none;
            position: absolute;
            top: calc(50% + 34px);
            left: 50%;
            transform: translateX(-50%);
            z-index: 51;
            pointer-events: none;
            white-space: nowrap;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 10px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: rgb(30 41 59);
            background: rgb(226 232 240);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.25);
        }
        body.sidebar-resizing #adminSidebarResizerChip { display: block; }
        #adminSidebarResizerChip.snap {
            color: #fff;
            background: rgb(99, 102, 241);
        }
        [data-theme="dark"] #adminSidebarResizerChip {
            color: rgb(226 232 240);
            background: rgb(30 41 59);
        }
        [data-theme="dark"] #adminSidebarResizerChip.snap {
            color: #fff;
            background: rgb(99, 102, 241);
        }
    </style>
    <script>
    (function(){
        // Animate scroll-fade-in elements on load/scroll
        function initScrollAnimations(){
            var els = document.querySelectorAll('.scroll-fade-in, .scroll-slide-up, .scroll-scale-in');
            if(!els.length) return;
            if(!('IntersectionObserver' in window)){
                els.forEach(function(el){ el.classList.add('visible'); });
                return;
            }
            var observer = new IntersectionObserver(function(entries){
                entries.forEach(function(entry){
                    if(entry.isIntersecting){ entry.target.classList.add('visible'); observer.unobserve(entry.target); }
                });
            }, { rootMargin: '0px 0px -60px 0px', threshold: 0.08 });
            els.forEach(function(el){ observer.observe(el); });
        }
        if(document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', initScrollAnimations, { once: true });
        } else {
            initScrollAnimations();
        }
    })();
    </script>

    @stack('scripts')
</body>
</html>
