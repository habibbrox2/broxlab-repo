{{-- ═══════════════════════════════════════════════════════════════════
   HEADER v2.0 — Sticky responsive navigation (Blade port of header-v2.twig)
   ═══════════════════════════════════════════════════════════════════ --}}

@php use App\Support\LanguageService; @endphp

<style>
@media (min-width: 1024px) {
  header nav li.group:hover > ul.header-submenu { display: flex !important; }
}
</style>

@php
    $navCurrentPath = request()->path() ?: '/';

    $navItems = $publicNavItems ?? [
        ['label' => 'Home',       'url' => '/',           'icon' => 'home',        'match' => '/'],
        ['label' => 'Mobiles',    'url' => '/mobiles',    'icon' => 'smartphone',  'match' => '/mobiles',
         'submenu' => [
            ['label' => 'Browse All',  'url' => '/mobiles',              'icon' => 'list'],
            ['label' => 'Top Phones',  'url' => '/mobiles?sort=popular', 'icon' => 'star'],
            ['label' => 'Brands',      'url' => '/mobiles/brands',       'icon' => 'tag']
         ]],
        ['label' => 'Articles',   'url' => '/posts',      'icon' => 'file-text',   'match' => '/posts',
         'submenu' => [
            ['label' => 'All Posts',   'url' => '/posts',              'icon' => 'list'],
            ['label' => 'Categories',  'url' => '/categories',         'icon' => 'folder'],
            ['label' => 'Latest',      'url' => '/posts?sort=latest',  'icon' => 'clock']
         ]],
        ['label' => 'Categories', 'url' => '/categories', 'icon' => 'grid',        'match' => '/categories'],
        ['label' => 'Services',   'url' => '/services',   'icon' => 'briefcase',   'match' => '/services',
         'submenu' => [
            ['label' => 'All Services', 'url' => '/services',    'icon' => 'list'],
            ['label' => 'Tools',        'url' => '/tools',       'icon' => 'wrench'],
            ['label' => 'Calculators',  'url' => '/calculators', 'icon' => 'calculator']
         ]],
        ['label' => 'Portfolio',  'url' => '/portfolio',  'icon' => 'image',   'match' => '/portfolio'],
        ['label' => 'Contact',    'url' => '/contact',    'icon' => 'mail',        'match' => '/contact'],
        ['label' => 'CV Builder',  'url' => '/cv-builder/templates', 'icon' => 'file-text', 'match' => '/cv-builder',
         'submenu' => [
            ['label' => 'Templates',    'url' => '/cv-builder/templates',    'icon' => 'layout'],
            ['label' => 'My CVs',       'url' => $isAuthenticated ? '/cv-builder' : '/cv-builder/guest', 'icon' => 'file']
         ]],
        ['label' => 'Weather',    'url' => '/weather',   'icon' => 'cloud-sun',   'match' => '/weather'],
        ['label' => 'Medicines',  'url' => '/medicines', 'icon' => 'pill',        'match' => '/medicines'],
        ['label' => 'News',       'url' => '/news',      'icon' => 'newspaper',   'match' => '/news']
    ];

    if (auth()->check()) {
        $dashboardUrl = ($isSuperAdmin || ($authUser && $authUser->role === 'admin')) ? '/admin/dashboard' : '/user/dashboard';
        $navItems[] = ['label' => 'Dashboard', 'url' => $dashboardUrl, 'icon' => 'gauge', 'match' => $dashboardUrl];
    }

    $isAdmin = auth()->check() && (($authUser && ($authUser->is_super_admin ?? false)) || ($authUser && $authUser->role === 'admin'));
    $unread = $unreadCount ?? 0;
@endphp

<header role="banner"
        class="sticky top-0 z-50 w-full
               bg-[rgb(var(--surface))] text-[rgb(var(--text))]
               dark:bg-slate-950/95 border-b border-[rgb(var(--border))]
               shadow-lg shadow-slate-900/20 backdrop-blur-xl
               transition-all duration-300">

  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <nav role="navigation"
         aria-label="Primary navigation"
         class="flex items-center h-16 gap-2 lg:gap-4">

      {{-- LOGO --}}
      <a href="{{ $studioHeaderUrl ?? '/' }}"
         title="{{ $appSettings['site_name'] ?? 'BroxLab' }}"
         class="flex items-center gap-2.5 flex-shrink-0 group">
        @if(!empty($appSettings['site_logo']))
          <img src="{{ $appSettings['site_logo'] }}"
               alt="{{ $appSettings['site_name'] ?? 'BroxLab' }}"
               width="120" height="40"
               fetchpriority="high"
               decoding="async"
               class="h-9 sm:h-10 w-auto rounded-lg object-contain shadow-md group-hover:shadow-lg group-hover:-translate-y-0.5 transition-all duration-300">
          <div class="hidden sm:flex flex-col">
            <span class="text-sm font-bold text-[rgb(var(--text))] leading-tight">{{ $appSettings['site_name'] ?? 'BroxLab' }}</span>
            <span class="text-[10px] text-indigo-500/80 leading-tight">Tech Platform</span>
          </div>
        @else
          <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 via-indigo-600 to-purple-600 shadow-lg shadow-indigo-500/30 group-hover:shadow-xl group-hover:shadow-indigo-500/40 group-hover:scale-105 transition-all duration-300">
            <i class="lucide lucide-smartphone text-white text-lg" aria-hidden="true"></i>
          </div>
          <div class="hidden sm:flex flex-col">
            <span class="text-sm font-bold text-[rgb(var(--text))] leading-tight">{{ $appSettings['site_name'] ?? 'BroxLab' }}</span>
            <span class="text-[10px] text-indigo-500/80 leading-tight">Platform</span>
          </div>
        @endif
      </a>

      {{-- MOBILE HAMBURGER --}}
      <button type="button"
              id="mobileMenuToggle"
              aria-controls="broxMainNav"
              x-bind:aria-expanded="open"
              aria-label="Toggle navigation menu"
              x-data="mobileMenu()"
              @click="toggle()"
              class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg border border-indigo-500/30 bg-[rgb(var(--surface-soft))] text-indigo-600 hover:bg-[rgb(var(--surface))] hover:text-indigo-500 dark:hover:bg-slate-700/60 dark:hover:text-indigo-400 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none active:scale-95 transition-all duration-300 shadow-md hover:shadow-lg hover:shadow-indigo-500/20">
        <svg data-icon-hamburger x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
        <svg data-icon-close x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>

      {{-- MAIN NAV MENU --}}
      <div id="broxMainNav"
           x-data="mobileMenu()"
           x-show="open"
           @click.outside="close()"
           @keydown.escape.window="close()"
           x-effect="document.body.style.overflow = open ? 'hidden' : ''"
           role="menubar"
           class="flex absolute lg:static top-16 lg:top-auto left-0 lg:left-auto right-0 lg:right-auto w-full lg:w-auto lg:flex-1 flex-col lg:flex-row bg-[rgb(var(--surface))] dark:bg-slate-900/95 lg:bg-transparent dark:lg:bg-transparent border-b lg:border-0 border-[rgb(var(--border))] shadow-lg lg:shadow-none lg:gap-1 max-h-0 lg:max-h-none overflow-hidden lg:overflow-visible transition-[max-height] duration-300 ease-in-out data-[expanded=true]:max-h-screen data-[expanded=true]:overflow-y-auto data-[expanded=true]:py-2 z-40 lg:z-auto lg:justify-center">

        <ul class="flex flex-col lg:flex-row items-stretch lg:items-center gap-0 lg:gap-0.5 w-full lg:w-auto py-2 lg:py-0 lg:flex-wrap lg:justify-center">
          @foreach($navItems as $item)
            @if(isset($item['enabled']) && !$item['enabled'])
                @continue
            @endif

            @php
                $_url   = $item['url'] ?? '/';
                $_match = $item['match'] ?? $_url;
                $_isActive = ($_match === '/')
                    ? ($navCurrentPath === '/')
                    : ($navCurrentPath === $_match || str_starts_with($navCurrentPath, $_match . '/'));
                $_hasSub = isset($item['submenu']) && is_array($item['submenu']) && count($item['submenu']) > 0;
            @endphp

            <li class="w-full lg:w-auto group relative" role="none">
                <a href="{{ $_url }}"
                   role="menuitem"
                   @if($_isActive)aria-current="page"@endif
                   class="flex lg:inline-flex items-center gap-2 px-4 lg:px-3.5 py-3 lg:py-2 rounded-none lg:rounded-lg font-semibold text-sm transition-all duration-200 w-full lg:w-auto focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none hover:no-underline
                   @if($_isActive)
                     bg-indigo-600 lg:bg-indigo-600 text-white lg:shadow-lg lg:shadow-indigo-500/40 hover:bg-indigo-500
                   @else
                     text-[rgb(var(--text))] hover:bg-[rgb(var(--surface-soft))] hover:text-indigo-600 lg:hover:bg-transparent lg:hover:text-indigo-600
                   @endif">
                  @if(!empty($item['icon']))
                    <i class="lucide lucide-{{ $item['icon'] }} w-4 h-4 flex-shrink-0" aria-hidden="true"></i>
                  @endif
                  <span class="flex-1 lg:flex-initial" data-i18n="{{ $item['label'] }}">{{ $item['label'] }}</span>
                  @if($_hasSub)
                    <i class="lucide lucide-chevron-down w-3.5 h-3.5 flex-shrink-0 lg:group-hover:rotate-180 transition-transform duration-300" aria-hidden="true"></i>
                  @endif
                </a>

                @if($_hasSub)
                  <ul class="header-submenu hidden absolute top-full left-0 mt-1 w-48 flex-col bg-[rgb(var(--surface))] backdrop-blur-md border border-[rgb(var(--border))] rounded-xl shadow-xl shadow-slate-900/30 overflow-hidden z-50">
                    @foreach($item['submenu'] as $sub)
                      <li role="none">
                        <a href="{{ $sub['url'] ?? '#' }}"
                           role="menuitem"
                           class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-[rgb(var(--text))] hover:bg-[rgb(var(--surface-soft))] hover:text-indigo-600 focus-visible:ring-inset focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none transition-colors duration-150">
                          @if(!empty($sub['icon']))
                            <i class="lucide lucide-{{ $sub['icon'] }} w-4 h-4 flex-shrink-0" aria-hidden="true"></i>
                          @endif
                          <span data-i18n="{{ $sub['label'] }}">{{ $sub['label'] }}</span>
                        </a>
                      </li>
                    @endforeach
                  </ul>
                @endif
              </li>
          @endforeach
        </ul>
      </div>

      {{-- RIGHT-SIDE ACTIONS --}}
      <div class="flex items-center gap-1 lg:gap-2 ml-auto flex-shrink-0" data-header-actions>

        {{-- Notifications (authenticated only) --}}
        @auth
          <div class="relative" x-data="notifications()">
            <button type="button"
                    id="broxNotificationBell"
                    x-bind:aria-expanded="open"
                    aria-haspopup="true"
                    aria-label="View notifications"
                    title="Notifications"
                    @click="toggle()"
                    class="relative inline-flex items-center justify-center w-10 h-10 rounded-lg bg-[rgb(var(--surface-soft))] hover:bg-[rgb(var(--surface))] text-[rgb(var(--muted))] hover:text-indigo-600 dark:bg-slate-700/40 dark:hover:bg-slate-700/60 dark:text-slate-400 dark:hover:text-indigo-400 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none hover:-translate-y-0.5 transition-all duration-300 shadow-md hover:shadow-lg hover:shadow-indigo-500/20 group">
              <i class="lucide lucide-bell w-5 h-5 group-hover:scale-110 transition-transform duration-300" aria-hidden="true"></i>
              @if($unread > 0)
                <span class="absolute -top-1 -right-1 flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-bold text-white bg-indigo-600 rounded-full shadow-lg animate-pulse" aria-label="{{ $unread }} unread notifications">
                  {{ $unread > 9 ? '9+' : $unread }}
                </span>
              @endif
            </button>

            <div id="notificationDropdown"
                 x-show="open"
                 @click.outside="close()"
                 @keydown.escape.window="close()"
                 x-ref="list"
                 aria-labelledby="broxNotificationBell"
                 class="fixed sm:absolute bottom-0 sm:bottom-auto top-auto sm:top-full left-0 right-0 sm:left-auto sm:right-0 sm:mt-2 w-full sm:w-96 max-h-[85vh] sm:max-h-96 rounded-t-3xl sm:rounded-2xl border-t sm:border border-[rgb(var(--border))] bg-white dark:bg-slate-900 shadow-2xl dark:shadow-black/50 overflow-hidden z-50 opacity-0 invisible scale-95 sm:scale-100 origin-top-right transition-all duration-200 flex flex-col"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">

              <div class="sticky top-0 flex items-center justify-between px-4 py-3.5 border-b border-[rgb(var(--border))] bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-slate-800/60 dark:to-slate-800/40 backdrop-blur-sm rounded-t-3xl sm:rounded-t-xl">
                <div class="flex items-center gap-2">
                  <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-600/10 dark:bg-indigo-600/20">
                    <i class="lucide lucide-bell w-4 h-4 text-indigo-600 dark:text-indigo-400" aria-hidden="true"></i>
                  </div>
                  <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Notifications</h2>
                </div>
                @if($unread > 0)
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold text-white bg-gradient-to-r from-indigo-600 to-indigo-700 shadow-lg shadow-indigo-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                    {{ $unread }} new
                  </span>
                @endif
              </div>

              <div id="broxNotificationsList"
                   data-notification-list
                   class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/50">
                @if(isset($notifications) && is_array($notifications) && count($notifications) > 0)
                  @foreach(array_slice($notifications, 0, 8) as $notif)
                    <div class="flex items-start gap-3 px-4 py-3.5 hover:bg-indigo-50 dark:hover:bg-slate-800/40 cursor-pointer transition-all duration-150 border-l-4 border-transparent hover:border-indigo-500"
                         data-notification-id="{{ $notif['id'] ?? '' }}"
                         role="button"
                         tabindex="0"
                         @if(!empty($notif['action_url']))data-action-url="{{ $notif['action_url'] }}'@endif>
                      @php
                          $notifIcon = 'info';
                          if (!empty($notif['type'])) {
                              if ($notif['type'] === 'error' || $notif['type'] === 'warning') $notifIcon = 'alert-circle';
                              elseif ($notif['type'] === 'success') $notifIcon = 'check-circle-2';
                              elseif ($notif['type'] === 'announcement') $notifIcon = 'megaphone';
                              elseif ($notif['type'] === 'message') $notifIcon = 'message-circle';
                              elseif ($notif['type'] === 'update') $notifIcon = 'zap';
                          }
                      @endphp
                      <div class="mt-0.5 flex-shrink-0">
                        <i class="lucide lucide-{{ $notifIcon }} w-5 h-5 flex-shrink-0 text-indigo-600 dark:text-indigo-400" aria-hidden="true"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $notif['title'] ?? '' }}</p>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 line-clamp-2">{{ $notif['message'] ?? '' }}</p>
                        <time class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5 flex items-center gap-1">
                          <i class="lucide lucide-clock w-3 h-3" aria-hidden="true"></i>
                          {{ $notif['created_at'] ?? '' }}
                        </time>
                      </div>
                    </div>
                  @endforeach
                @else
                  <div class="flex flex-col items-center justify-center py-12 px-4 text-slate-400 dark:text-slate-500">
                    <div class="flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 mb-3">
                      <i class="lucide lucide-inbox w-6 h-6 opacity-50" aria-hidden="true"></i>
                    </div>
                    <p class="text-sm font-medium">No notifications yet</p>
                    <p class="text-xs mt-1 text-center">We'll notify you about important updates and messages</p>
                  </div>
                @endif
              </div>

              <div class="border-t border-[rgb(var(--border))] px-3 py-2 bg-slate-50 dark:bg-slate-800/30 flex-shrink-0">
                <a href="/user/notifications"
                   class="flex items-center justify-center gap-2 w-full py-2.5 px-3 rounded-lg text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 hover:text-indigo-700 dark:hover:text-indigo-300 transition-all duration-150 group">
                  <span>View all notifications</span>
                  <i class="lucide lucide-arrow-right w-4 h-4 group-hover:translate-x-1 transition-transform duration-300" aria-hidden="true"></i>
                </a>
              </div>
            </div>
          </div>
        @endauth

        {{-- User menu (authenticated) / Auth links (guest) --}}
        @auth
          <div class="relative" x-data="userMenu()">
            <button type="button"
                    id="broxNavbarUser"
                    x-bind:aria-expanded="open"
                    aria-haspopup="true"
                    aria-label="Account menu"
                    title="Account menu"
                    @click="toggle()"
                    class="inline-flex items-center justify-center gap-1.5 w-10 h-10 rounded-lg bg-[rgb(var(--surface-soft))] hover:bg-[rgb(var(--surface))] text-[rgb(var(--muted))] hover:text-indigo-600 dark:bg-slate-700/40 dark:hover:bg-slate-700/60 dark:text-slate-400 dark:hover:text-indigo-400 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none hover:-translate-y-0.5 transition-all duration-300 shadow-md hover:shadow-lg hover:shadow-indigo-500/20 group">
              @if(!empty($authUser->profile_pic))
                <img src="{{ $authUser->profile_pic }}"
                     alt="{{ $authUser->username ?? 'User' }}"
                     width="32" height="32"
                     loading="lazy" decoding="async"
                     class="w-8 h-8 rounded-lg object-cover ring-2 ring-[rgb(var(--border))] dark:ring-slate-700 group-hover:ring-indigo-500 group-hover:scale-105 transition-all duration-300">
              @else
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold">
                  {{ mb_strtoupper(mb_substr($authUser->username ?? 'U', 0, 1)) }}
                </div>
              @endif
              <i class="lucide lucide-chevron-down hidden sm:block w-3 h-3 opacity-60 group-hover:opacity-100 transition-all duration-300" aria-hidden="true"></i>
            </button>

            <div id="userDropdown"
                 x-show="open"
                 @click.outside="close()"
                 @keydown.escape.window="close()"
                 aria-labelledby="broxNavbarUser"
                 class="fixed sm:absolute bottom-0 sm:bottom-auto top-auto sm:top-full left-0 right-0 sm:left-auto sm:right-0 sm:mt-2 w-full sm:w-72 rounded-t-2xl sm:rounded-2xl border-t sm:border border-[rgb(var(--border))] bg-white dark:bg-slate-900 shadow-2xl dark:shadow-black/50 overflow-hidden z-50 opacity-0 invisible scale-95 sm:scale-100 origin-top-right transition-all duration-200"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">

              <div class="px-4 py-4 border-b border-[rgb(var(--border))] bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-slate-800/60 dark:to-slate-800/30 rounded-t-2xl sm:rounded-t-xl">
                <div class="flex items-center gap-3">
                  @if(!empty($authUser->profile_pic))
                    <img src="{{ $authUser->profile_pic }}"
                         alt="{{ $authUser->username ?? 'User' }}"
                         width="48" height="48"
                         loading="lazy" decoding="async"
                         class="w-12 h-12 rounded-xl object-cover ring-2 ring-indigo-300 dark:ring-indigo-700">
                  @else
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold text-lg">
                      {{ mb_strtoupper(mb_substr($authUser->username ?? 'U', 0, 1)) }}
                    </div>
                  @endif
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $authUser->username ?? 'User' }}</p>
                    <span class="mt-1 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">
                      <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse flex-shrink-0"></span>
                      {{ $authUser->roles && count($authUser->roles) > 0 ? ($authUser->roles[0]['name'] ?? ($authUser->role ?? 'User')) : 'User' }}
                    </span>
                  </div>
                </div>
              </div>

              @php
                  $_profileUrl    = $isAdmin ? '/admin/profile'           : '/profile';
                  $_dashboardLink = $isAdmin ? '/admin/dashboard'         : '/user/dashboard';
                  $_notifUrl      = $isAdmin ? '/admin/my/notifications'  : '/user/notifications';
                  $_settingsUrl   = $isAdmin ? '/admin/account-settings'  : '/user/settings';
                  $_userLinks = [
                    ['href' => $_profileUrl,    'icon' => 'user',             'label' => 'Profile'],
                    ['href' => $_dashboardLink, 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
                    ['href' => $_notifUrl,      'icon' => 'bell',             'label' => 'Notifications'],
                    ['href' => $_settingsUrl,   'icon' => 'settings',         'label' => 'Settings']
                  ];
              @endphp

              <nav class="py-2">
                @foreach($_userLinks as $link)
                  <a href="{{ $link['href'] }}"
                     class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 focus-visible:ring-inset focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none transition-colors duration-150">
                    <i class="lucide lucide-{{ $link['icon'] }} w-4 h-4 flex-shrink-0" aria-hidden="true"></i>
                    <span data-i18n="{{ $link['label'] }}">{{ $link['label'] }}</span>
                  </a>
                @endforeach
              </nav>

              <div class="border-t border-[rgb(var(--border))] p-2 bg-slate-50 dark:bg-slate-800/30">
                <a href="/logout"
                   data-unified-logout
                   class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg text-sm font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:outline-none transition-colors duration-150">
                  <i class="lucide lucide-log-out w-4 h-4" aria-hidden="true"></i>
                  <span data-i18n="Logout">Logout</span>
                </a>
              </div>
            </div>
          </div>
        @else
          <a href="/login"
             title="Login"
             class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-[rgb(var(--muted))] border border-[rgb(var(--border))] hover:bg-[rgb(var(--surface-soft))] hover:text-indigo-600 dark:hover:bg-slate-700/60 dark:hover:text-indigo-400 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none transition-all duration-300 shadow-md hover:shadow-lg hover:shadow-indigo-500/20">
            <i class="lucide lucide-log-in w-4 h-4" aria-hidden="true"></i>
            Login
          </a>
          <a href="/register"
             title="Join Now"
             class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none shadow-lg shadow-indigo-500/40 hover:shadow-xl hover:shadow-indigo-500/60 active:scale-95 transition-all duration-300">
            <i class="lucide lucide-user-plus w-4 h-4" aria-hidden="true"></i>
            <span class="hidden sm:inline">Join Now</span>
          </a>
        @endauth

        <div class="hidden lg:block h-6 w-px bg-[rgb(var(--border))] mx-1" aria-hidden="true"></div>

        {{-- Theme toggle --}}
        <button type="button"
                id="broxThemeToggle"
                aria-pressed="false"
                aria-label="Toggle dark/light theme"
                title="Toggle theme"
                class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-[rgb(var(--surface-soft))] hover:bg-[rgb(var(--surface))] text-amber-500 hover:text-amber-400 focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:outline-none hover:-translate-y-0.5 transition-all duration-300 shadow-md hover:shadow-lg hover:shadow-amber-500/20 group">
          <i class="lucide lucide-sun dark:hidden block w-5 h-5 group-hover:scale-110 group-hover:rotate-12 transition-transform duration-300" aria-hidden="true"></i>
          <i class="lucide lucide-moon hidden dark:block w-5 h-5 group-hover:scale-110 group-hover:rotate-12 transition-transform duration-300" aria-hidden="true"></i>
        </button>

        {{-- Language toggle --}}
        @if(isset($availableLanguages) && count($availableLanguages) > 1)
          <button type="button"
                  data-lang-btn="{{ app(LanguageService::class)->current() == 'bn' ? 'en' : 'bn' }}"
                  aria-label="{{ app(LanguageService::class)->current() == 'bn' ? 'Switch to English' : 'বাংলায় পরিবর্তন করুন' }}"
                  title="{{ app(LanguageService::class)->current() == 'bn' ? 'English' : 'বাংলা' }}"
                  class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-[rgb(var(--surface-soft))] hover:bg-[rgb(var(--surface))] text-[rgb(var(--muted))] hover:text-indigo-600 dark:hover:bg-slate-700/60 dark:hover:text-indigo-400 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none hover:-translate-y-0.5 transition-all duration-300 shadow-md hover:shadow-lg hover:shadow-indigo-500/20 group">
            @if(app(LanguageService::class)->current() === 'bn')
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40" class="w-5 h-5 rounded-sm shadow-sm group-hover:scale-110 transition-transform duration-300" aria-hidden="true">
                <rect width="60" height="40" fill="#006a4e"/>
                <circle cx="23" cy="20" r="12" fill="#f42a41"/>
              </svg>
            @else
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40" class="w-5 h-5 rounded-sm shadow-sm group-hover:scale-110 transition-transform duration-300" aria-hidden="true">
                <rect width="60" height="40" fill="#fff"/>
                <g fill="#b22234">
                  <rect y="0" width="60" height="3.08"/><rect y="6.15" width="60" height="3.08"/>
                  <rect y="12.31" width="60" height="3.08"/><rect y="18.46" width="60" height="3.08"/>
                  <rect y="24.62" width="60" height="3.08"/><rect y="30.77" width="60" height="3.08"/>
                  <rect y="36.92" width="60" height="3.08"/>
                </g>
                <rect width="24" height="21.54" fill="#3c3b6e"/>
              </svg>
            @endif
          </button>
        @endif

      </div>
    </nav>
  </div>
</header>
