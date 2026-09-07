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
    $adminNav = [
        ['label' => null, 'items' => [
            ['name' => 'Dashboard', 'icon' => 'lucide-gauge', 'url' => '/admin/dashboard', 'key' => 'dashboard'],
        ]],
        ['label' => 'Content', 'items' => [
            ['name' => 'Posts', 'icon' => 'lucide-file-text', 'url' => '/admin/posts', 'key' => 'posts'],
            ['name' => 'Categories', 'icon' => 'lucide-folder', 'url' => '/admin/categories', 'key' => 'categories'],
            ['name' => 'Tags', 'icon' => 'lucide-hash', 'url' => '/admin/tags', 'key' => 'tags'],
            ['name' => 'Comments', 'icon' => 'lucide-message-circle', 'url' => '/admin/comments', 'key' => 'comments', 'badge' => 'comments'],
        ]],
        ['label' => 'Catalog', 'items' => [
            ['name' => 'Mobiles', 'icon' => 'lucide-smartphone', 'url' => '/admin/mobiles', 'key' => 'mobiles'],
        ]],
        ['label' => 'Services', 'items' => [
            ['name' => 'Applications', 'icon' => 'lucide-briefcase', 'url' => '/admin/applications', 'key' => 'applications', 'badge' => 'applications'],
            ['name' => 'Receipts', 'icon' => 'lucide-receipt', 'url' => '/admin/applications/receipts', 'key' => 'receipts'],
        ]],
        ['label' => 'People', 'items' => [
            ['name' => 'Users', 'icon' => 'lucide-users', 'url' => '/admin/users', 'key' => 'users'],
            ['name' => 'Contact', 'icon' => 'lucide-mail', 'url' => '/admin/contact', 'key' => 'contact', 'badge' => 'contact'],
        ]],
        ['label' => 'Insights', 'items' => [
            ['name' => 'Analytics', 'icon' => 'lucide-bar-chart-3', 'url' => '/admin/analytics', 'key' => 'analytics'],
            ['name' => 'Logs', 'icon' => 'lucide-scroll-text', 'url' => '/admin/logs', 'key' => 'logs'],
        ]],
        ['label' => 'Settings', 'items' => [
            ['name' => 'Account Settings', 'icon' => 'lucide-settings', 'url' => '/admin/account-settings', 'key' => 'account-settings'],
        ]],
    ];
    $currentPath = request()->path() === '/' ? '/' : '/'.request()->path();
@endphp

<div class="min-h-screen flex flex-col">

    {{-- Top header --}}
    <header class="sticky top-0 z-40 h-14 flex items-center
                   border-b border-slate-200/70 dark:border-slate-800/70
                   bg-white/95 dark:bg-slate-950/95 backdrop-blur-xl">
        <div class="flex items-center w-full px-4 sm:px-5 gap-3">

            <button type="button" aria-label="Open navigation"
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
                    <div class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-0.5">Admin Panel</div>
                </div>
            </a>

            <div class="ml-auto flex items-center gap-1">
                {{-- Notifications bell --}}
                <div class="relative">
                    <button id="adminNotificationBell" type="button" aria-expanded="false" aria-label="Notifications"
                            title="Notifications"
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
                <button type="button" title="Toggle theme"
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
                            title="Server status" aria-label="Server status"
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
                            <img src="{{ asset($admin_user->profile_pic) }}" alt="Profile" width="28" height="28"
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
                                         alt="Profile" width="40" height="40"
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
                                <span class="font-medium">Account Settings</span>
                            </a>
                            <a href="/profile" class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 dark:text-slate-300
                                                                  rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800
                                                                  hover:text-indigo-700 dark:hover:text-indigo-400 transition-all duration-150">
                                <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                                    <i class="lucide lucide-user-circle w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400"></i>
                                </div>
                                <span class="font-medium">View Profile</span>
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
                                    <span>Sign out</span>
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

        {{-- Mobile overlay --}}
    <div id="sidebarOverlay"
         class="fixed inset-0 bg-black/50 backdrop-blur-sm z-30 lg:hidden transition-opacity duration-300"
         style="opacity:0; pointer-events:none;">
    </div>

    {{-- Sidebar --}}
    <aside id="adminSidebar" x-data="{ open: false, mini: false }" x-on:toggle-sidebar.window="open = !open"
           class="w-[220px] flex-shrink-0 bg-white dark:bg-slate-950
                  border-r border-slate-200/70 dark:border-slate-800/70
                  overflow-y-auto overflow-x-hidden transition-all duration-300 ease-out
                  fixed lg:sticky left-0 top-0 h-full z-40
                  -translate-x-full lg:translate-x-0"
           :class="open && '!translate-x-0'"
           aria-label="Primary navigation">

        <div class="flex items-center justify-between h-10 px-3.5 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/60 dark:bg-slate-900/30">
            <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-600 select-none flex items-center gap-1.5">
                <i class="lucide lucide-layout-dashboard w-3 h-3"></i> Navigation
            </span>
            <button type="button" id="sidebarMiniToggle" aria-label="Collapse sidebar" aria-pressed="false"
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
                        <a href="{{ $item['url'] }}"
                           class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-sm font-medium transition-all duration-150
                                  {{ $currentPath === $item['url'] || str_starts_with($currentPath, rtrim($item['url'], '/').'/') || ($item['key'] ?? '') === request()->get('tab')
                                      ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300'
                                      : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-indigo-600 dark:hover:text-indigo-400' }}">
                            <i class="lucide {{ $item['icon'] }} w-4 h-4 flex-shrink-0"></i>
                            <span class="truncate">{{ $item['name'] }}</span>
                            @if (!empty($item['badge']))
                                <span class="sidebar-badge ml-auto hidden items-center justify-center min-w-[1.1rem] h-[1.1rem] px-1 text-[9px] font-bold text-white bg-rose-500 rounded-full" data-badge-key="{{ $item['badge'] }}"></span>
                            @endif
                        </a>
                    @endforeach
                @endforeach

                <div class="pt-3 mt-2 border-t border-slate-100 dark:border-slate-800/80">
                    <div class="px-2 pb-1 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-600">Back to site</div>
                    <a href="/" class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-indigo-600">
                        <i class="lucide lucide-arrow-left w-4 h-4 flex-shrink-0"></i>
                        <span>Public site</span>
                    </a>
                </div>
            </nav>
        </aside>

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
