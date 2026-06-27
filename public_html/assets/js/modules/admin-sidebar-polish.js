/**
 * Admin Sidebar Polish Module
 * Modern, polished sidebar with smooth animations, keyboard support, and enhanced UX
 */

export function initSidebarPolish() {
  'use strict';

  // ========== DOM ELEMENTS ==========
  const sidebar = document.getElementById('adminSidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarClose = document.getElementById('sidebarClose');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const sidebarMiniToggle = document.getElementById('sidebarMiniToggle');
  const menuToggles = document.querySelectorAll('[data-menu-toggle]');

  if (!sidebar || !sidebarToggle) {
    console.warn('[SidebarPolish] Missing required DOM elements');
    return;
  }

  // ========== STATE MANAGEMENT ==========
  const state = {
    isOpen: false,
    isMini: localStorage.getItem('admin.sidebar.mini') === '1',
    lastToggleTime: 0,
  };

  // ========== CONSTANTS ==========
  const BREAKPOINT_LG = 1024;
  const DEBOUNCE_DELAY = 150;
  const TOGGLE_COOLDOWN = 300;

  // ========== INITIALIZATION ==========
  function init() {
    applyInitialState();

    // CRITICAL: Remove all 'open' classes from submenus on initialization
    // This ensures clean state regardless of HTML markup
    document.querySelectorAll('.sidebar-submenu.open').forEach((submenu) => {
      submenu.classList.remove('open');
    });
    document.querySelectorAll('[data-menu-toggle][aria-expanded="true"]').forEach((toggle) => {
      toggle.setAttribute('aria-expanded', 'false');
    });

    setupEventListeners();
    setupKeyboardShortcuts();
    setupResizeObserver();
    setupSubmenuInteractions();
  }

  // ========== STATE HELPERS ==========
  function applyInitialState() {
    if (state.isMini) {
      document.body.classList.add('sidebar-collapsed');
      sidebarMiniToggle?.setAttribute('aria-pressed', 'true');
    }
  }

  function isDesktop() {
    return window.innerWidth >= BREAKPOINT_LG;
  }

  function isMobile() {
    return window.innerWidth < BREAKPOINT_LG;
  }

  function canToggle() {
    const now = Date.now();
    if (now - state.lastToggleTime < TOGGLE_COOLDOWN) {
      return false;
    }
    state.lastToggleTime = now;
    return true;
  }

  // ========== SIDEBAR OPEN/CLOSE LOGIC ==========
  function openSidebar() {
    if (state.isOpen || isDesktop() || !canToggle()) return;

    state.isOpen = true;
    sidebar.classList.remove('-translate-x-full');
    sidebar.classList.add('translate-x-0');

    if (sidebarOverlay) {
      sidebarOverlay.classList.remove('hidden');
      // Trigger animation
      requestAnimationFrame(() => {
        sidebarOverlay.classList.add('opacity-100');
      });
    }

    sidebarToggle?.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';

    // Announce to screen readers
    announceToScreen('Sidebar opened');
  }

  function closeSidebar() {
    if (!state.isOpen || !canToggle()) return;

    state.isOpen = false;
    sidebar.classList.add('-translate-x-full');
    sidebar.classList.remove('translate-x-0');

    if (sidebarOverlay) {
      sidebarOverlay.classList.add('hidden');
      sidebarOverlay.classList.remove('opacity-100');
    }

    sidebarToggle?.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';

    // Announce to screen readers
    announceToScreen('Sidebar closed');
  }

  function toggleSidebar() {
    state.isOpen ? closeSidebar() : openSidebar();
  }

  // ========== MINI MODE TOGGLE ==========
  function toggleMiniMode() {
    if (!canToggle()) return;

    const isMiniNow = document.body.classList.toggle('sidebar-collapsed');
    state.isMini = isMiniNow;

    // Update aria-pressed
    sidebarMiniToggle?.setAttribute('aria-pressed', isMiniNow ? 'true' : 'false');

    // Persist state
    try {
      localStorage.setItem('admin.sidebar.mini', isMiniNow ? '1' : '0');
    } catch (e) {
      console.warn('Failed to save sidebar state:', e);
    }

    // Announce to screen readers
    announceToScreen(isMiniNow ? 'Sidebar collapsed' : 'Sidebar expanded');

    // Trigger layout recalculation
    window.dispatchEvent(new Event('sidebar-mini-toggled', { detail: { isMini: isMiniNow, }, }));
  }

  // ========== SUBMENU INTERACTIONS ==========
  function setupSubmenuInteractions() {
    menuToggles.forEach((toggle) => {
      // Click handler
      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        handleSubmenuToggle(toggle);
      });

      // Add keyboard support
      toggle.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          handleSubmenuToggle(toggle);
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault();
          handleSubmenuNavigation(toggle, e.key === 'ArrowDown');
        }
      });
    });

    // Close submenus when clicking on submenu links
    const submenuLinks = sidebar.querySelectorAll('.sidebar-submenu a');
    submenuLinks.forEach((link) => {
      link.addEventListener('click', () => {
        closeAllSubmenus();
      });
    });
  }

  function closeAllSubmenus() {
    menuToggles.forEach((toggle) => {
      const submenuId = toggle.getAttribute('data-menu-toggle');
      const submenu = document.getElementById(submenuId);
      if (submenu) {
        submenu.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function handleSubmenuToggle(toggle) {
    if (!canToggle()) return;

    const submenuId = toggle.getAttribute('data-menu-toggle');
    const submenu = document.getElementById(submenuId);
    const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

    if (!submenu) return;

    // Toggle submenu
    if (isExpanded) {
      submenu.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
      announceToScreen(`${toggle.textContent.trim()} collapsed`);
    } else {
      // Close all other submenus first
      closeAllSubmenus();

      submenu.classList.add('open');
      toggle.setAttribute('aria-expanded', 'true');
      announceToScreen(`${toggle.textContent.trim()} expanded`);

      // Focus first item in submenu after animation
      setTimeout(() => {
        const firstLink = submenu.querySelector('a');
        if (firstLink) {
          firstLink.focus();
        }
      }, 150);
    }
  }

  function handleSubmenuNavigation(toggle, goDown) {
    const menuItems = Array.from(menuToggles);
    const currentIndex = menuItems.indexOf(toggle);

    if (goDown && currentIndex < menuItems.length - 1) {
      menuItems[currentIndex + 1].focus();
    } else if (!goDown && currentIndex > 0) {
      menuItems[currentIndex - 1].focus();
    }
  }

  // ========== VIEWPORT SYNC ==========
  let resizeTimeout;
  function handleWindowResize() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
      // Auto-close sidebar on desktop
      if (isDesktop()) {
        closeSidebar();
      }
    }, DEBOUNCE_DELAY);
  }

  // ========== KEYBOARD SHORTCUTS ==========
  function setupKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
      // Cmd+K or Ctrl+K for search (if applicable)
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('adminGlobalSearch');
        if (searchInput) {
          searchInput.focus();
        }
      }

      // Escape to close sidebar on mobile
      if (e.key === 'Escape' && state.isOpen && isMobile()) {
        closeSidebar();
      }

      // Alt+S to toggle sidebar on mobile
      if (e.altKey && e.key === 's' && isMobile()) {
        e.preventDefault();
        toggleSidebar();
      }
    });
  }

  // ========== EVENT LISTENERS ==========
  function setupEventListeners() {
    // Sidebar toggle button
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', toggleSidebar);
    }

    // Sidebar close button
    if (sidebarClose) {
      sidebarClose.addEventListener('click', closeSidebar);
    }

    // Overlay click
    if (sidebarOverlay) {
      sidebarOverlay.addEventListener('click', closeSidebar);
    }

    // Mini toggle button
    if (sidebarMiniToggle) {
      sidebarMiniToggle.addEventListener('click', toggleMiniMode);
    }

    // Window resize
    window.addEventListener('resize', handleWindowResize);

    // Sidebar links - close on navigation (mobile only)
    const sidebarLinks = sidebar.querySelectorAll('a');
    sidebarLinks.forEach((link) => {
      link.addEventListener('click', () => {
        if (isMobile()) {
          closeSidebar();
        }
      });
    });

    // URL change detection for browser back/forward navigation
    window.addEventListener('popstate', () => {
      updateActiveLink();
    });

    // Intercept pushState/replaceState to catch programmatic navigation.
    // Guard against double-wrapping if initSidebarPolish() runs more than once.
    if (!history.__sidebarPolishWired) {
      history.__sidebarPolishWired = true;
      const origPushState = history.pushState;
      history.pushState = function () {
        origPushState.apply(this, arguments);
        updateActiveLink();
      };
      const origReplaceState = history.replaceState;
      history.replaceState = function () {
        origReplaceState.apply(this, arguments);
        updateActiveLink();
      };
    }
  }

  // ========== RESIZE OBSERVER FOR SMOOTH RESIZING ==========
  function setupResizeObserver() {
    if (typeof ResizeObserver === 'undefined') return;

    const resizer = document.getElementById('adminColumnResizer');
    if (!resizer) return;

    let isResizing = false;
    let startX = 0;
    let startWidth = 0;

    resizer.addEventListener('mousedown', (e) => {
      if (state.isMini) return; // Don't resize in mini mode

      isResizing = true;
      startX = e.clientX;
      startWidth = sidebar.offsetWidth;

      document.addEventListener('mousemove', handleResizeMove);
      document.addEventListener('mouseup', handleResizeEnd);

      // Prevent text selection during drag
      document.body.style.userSelect = 'none';
      document.body.style.cursor = 'col-resize';
    });

    function handleResizeMove(e) {
      if (!isResizing) return;

      const diff = e.clientX - startX;
      const newWidth = Math.max(220, Math.min(520, startWidth + diff));

      sidebar.style.width = `${newWidth}px`;
    }

    function handleResizeEnd() {
      isResizing = false;
      document.removeEventListener('mousemove', handleResizeMove);
      document.removeEventListener('mouseup', handleResizeEnd);
      document.body.style.userSelect = '';
      document.body.style.cursor = '';

      // Persist width
      try {
        localStorage.setItem('admin.sidebar.width', sidebar.offsetWidth);
      } catch (e) {
        console.warn('Failed to save sidebar width:', e);
      }
    }
  }

  // ========== ACCESSIBILITY HELPERS ==========
  function announceToScreen(message) {
    const announcement = document.createElement('div');
    announcement.setAttribute('role', 'status');
    announcement.setAttribute('aria-live', 'polite');
    announcement.className = 'sr-only';
    announcement.textContent = message;
    document.body.appendChild(announcement);

    setTimeout(() => {
      announcement.remove();
    }, 1000);
  }

  // ========== ACTIVE LINK HIGHLIGHTING ==========
  function updateActiveLink() {
    const currentPath = window.location.pathname;
    const currentSearch = window.location.search;
    const currentFull = currentPath + currentSearch;

    const sidebarLinks = sidebar.querySelectorAll('a[href]');
    const sidebarToggles = sidebar.querySelectorAll('[data-menu-toggle]');

    // Close all submenus for a clean slate before re-determining state
    closeAllSubmenus();

    // Reset all active states
    sidebarLinks.forEach((link) => {
      link.classList.remove('active');
      link.removeAttribute('aria-current');
    });
    sidebarToggles.forEach((toggle) => {
      toggle.classList.remove('active');
    });

    // ── Pass 1: Find EXACT matches (pathname + query string) ──
    // This prevents sibling links like /admin/applications and
    // /admin/applications?status=pending from both lighting up.
    let bestExactLink = null;
    let bestExactSubmenu = null;

    sidebarLinks.forEach((link) => {
      const href = link.getAttribute('href');
      if (!href || href === '#') return;

      try {
        const linkUrl = new URL(href, window.location.origin);
        const linkFull = linkUrl.pathname + linkUrl.search;

        if (linkFull === currentFull) {
          link.classList.add('active');
          link.setAttribute('aria-current', 'page');
          bestExactLink = link;

          const submenuParent = link.closest('.sidebar-submenu');
          if (submenuParent) {
            bestExactSubmenu = submenuParent;
          }
        }
      } catch (e) { /* skip */ }
    });

    // ── Pass 2: For unmatched paths, find best parent prefix ──
    // Only activate when NO exact match was found in the same submenu.
    // This prevents sibling links from both lighting up (e.g. /admin/notifications
    // and /admin/notifications/list when visiting /admin/notifications/list).
    // Links with query strings are skipped — they should match via exact only.
    if (!bestExactLink) {
      sidebarLinks.forEach((link) => {
        const href = link.getAttribute('href');
        if (!href || href === '#') return;

        try {
          const linkUrl = new URL(href, window.location.origin);

          // Skip root/empty paths and links with query strings
          if (linkUrl.pathname === '/' || linkUrl.pathname === '') return;
          if (linkUrl.search) return;

          // Only activate if current path starts with linkPath + '/'
          // and no link in this submenu already has active
          if (currentPath.startsWith(`${linkUrl.pathname }/`)) {
            const submenuParent = link.closest('.sidebar-submenu');
            if (submenuParent && submenuParent.querySelector('a.active')) return;

            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
            bestExactLink = link;

            if (submenuParent) {
              bestExactSubmenu = submenuParent;
            }
          }
        } catch (e) { /* skip */ }
      });
    }

    // ── Expand parent submenu for the active link ──
    if (bestExactSubmenu) {
      const parentToggle = bestExactSubmenu.previousElementSibling;
      if (parentToggle && parentToggle.hasAttribute('data-menu-toggle')) {
        parentToggle.setAttribute('aria-expanded', 'true');
        parentToggle.classList.add('active');
      }
    }

    // ── Fallback: activate parent toggle if a submenu contains current path ──
    if (!bestExactSubmenu) {
      sidebarToggles.forEach((toggle) => {
        const submenuId = toggle.getAttribute('data-menu-toggle');
        const submenu = document.getElementById(submenuId);
        if (!submenu) return;

        const childLinks = submenu.querySelectorAll('a[href]');
        for (const child of childLinks) {
          const href = child.getAttribute('href');
          if (!href || href === '#') continue;
          try {
            const childUrl = new URL(href, window.location.origin);
            const childFull = childUrl.pathname + childUrl.search;
            if (childFull === currentFull || currentPath.startsWith(`${childUrl.pathname }/`)) {
              toggle.classList.add('active');
              toggle.setAttribute('aria-expanded', 'true');
              break;
            }
          } catch (e) { /* skip */ }
        }
      });
    }
  }

  // ========== PUBLIC API ==========
  const api = {
    open: openSidebar,
    close: closeSidebar,
    toggle: toggleSidebar,
    toggleMini: toggleMiniMode,
    getState: () => ({ ...state, }),
    updateActiveLink,
  };

  // ========== INITIALIZE & EXPOSE API ==========
  init();
  updateActiveLink();

  // Expose to window for external access
  window.AdminSidebarAPI = api;

  return api;
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSidebarPolish);
} else {
  initSidebarPolish();
}
