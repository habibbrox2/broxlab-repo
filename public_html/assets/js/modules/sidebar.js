/**
 * Sidebar Module - Mobile-First Responsive
 * Handles sidebar toggle, mini-mode, resizing, and responsive behaviors
 */

export function initSidebar() {
  'use strict';

  // ========== DOM ELEMENTS ==========
  const sidebar = document.querySelector('.sidebar');
  const sidebarToggles = document.querySelectorAll('.sidebar-toggle');
  const sidebarMiniToggle = document.querySelector('.sidebar-mini-toggle');
  const adminShellRow = document.querySelector('.admin-shell-row');
  const adminMain = document.querySelector('.admin-main');
  const sidebarResizer = document.getElementById('adminColumnResizer');

  // Guard: Essential elements
  if (!sidebar || !sidebarToggles.length) {
    console.warn('[Sidebar] Missing required elements');
    return;
  }

  // ========== CONSTANTS ==========
  const DESKTOP_WIDTH = 992;
  const DEFAULT_SIDEBAR_WIDTH = 280;
  const MIN_SIDEBAR_WIDTH = 220;
  const MAX_SIDEBAR_WIDTH = 520;
  const MINI_STORAGE_KEY = 'admin.sidebar.mini';
  const SIDEBAR_WIDTH_KEY = 'admin.sidebar.width';

  // ========== CREATE OVERLAY ==========
  let overlay = document.querySelector('.sidebar-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
  }

  // ========== HELPERS ==========
  const isDesktop = () => window.innerWidth >= DESKTOP_WIDTH;
  const isMobile = () => window.innerWidth < DESKTOP_WIDTH;
  const isSidebarOpen = () => sidebar.classList.contains('show');
  const isMiniMode = () => document.body.classList.contains('admin-sidebar-mini');

  // ========== SIDEBAR OPEN/CLOSE ==========
  const openSidebar = () => {
    if (isSidebarOpen()) return;
    sidebar.classList.add('show');
    overlay.classList.add('show');
    document.body.classList.add('admin-sidebar-open');
    sidebarToggles.forEach((toggle) => {
      toggle.setAttribute('aria-expanded', 'true');
    });
  };

  const closeSidebar = () => {
    if (!isSidebarOpen()) return;
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
    document.body.classList.remove('admin-sidebar-open');
    sidebarToggles.forEach((toggle) => {
      toggle.setAttribute('aria-expanded', 'false');
    });
  };

  const toggleSidebar = () => {
    isSidebarOpen() ? closeSidebar() : openSidebar();
  };

  // ========== VIEWPORT SYNC ==========
  const syncViewport = () => {
    if (isDesktop()) {
      closeSidebar();
    }
  };

  // ========== STORAGE ==========
  const readMiniState = () => {
    try {
      return localStorage.getItem(MINI_STORAGE_KEY) === '1';
    } catch (e) {
      return false;
    }
  };

  const writeMiniState = (enabled) => {
    try {
      localStorage.setItem(MINI_STORAGE_KEY, enabled ? '1' : '0');
    } catch (e) {
      // Silent fail
    }
  };

  const readSidebarWidth = () => {
    try {
      const value = parseInt(localStorage.getItem(SIDEBAR_WIDTH_KEY) || '', 10);
      return isNaN(value) ? DEFAULT_SIDEBAR_WIDTH : value;
    } catch (e) {
      return DEFAULT_SIDEBAR_WIDTH;
    }
  };

  const writeSidebarWidth = (width) => {
    try {
      localStorage.setItem(SIDEBAR_WIDTH_KEY, String(width));
    } catch (e) {
      // Silent fail
    }
  };

  // ========== WIDTH MANAGEMENT ==========
  const clampWidth = (width, rowWidth = 0) => {
    const maxByRow = rowWidth > 0 ? Math.max(MIN_SIDEBAR_WIDTH, rowWidth - 360) : MAX_SIDEBAR_WIDTH;
    const hardMax = Math.min(MAX_SIDEBAR_WIDTH, maxByRow);
    return Math.min(hardMax, Math.max(MIN_SIDEBAR_WIDTH, Math.round(width)));
  };

  const resetSidebarWidth = () => {
    sidebar.style.flex = '';
    sidebar.style.maxWidth = '';
    if (adminMain) {
      adminMain.style.flex = '';
      adminMain.style.maxWidth = '';
    }
  };

  const applySidebarWidth = (width) => {
    if (!adminShellRow || !adminMain) return;
    const rowRect = adminShellRow.getBoundingClientRect();
    const clamped = clampWidth(width, rowRect.width);
    sidebar.style.flex = `0 0 ${clamped}px`;
    sidebar.style.maxWidth = `${clamped}px`;
    adminMain.style.flex = `1 1 calc(100% - ${clamped}px)`;
    adminMain.style.maxWidth = `calc(100% - ${clamped}px)`;
  };

  // ========== MINI MODE ==========
  const applyMiniMode = (forceState = null) => {
    if (isMobile()) {
      document.body.classList.remove('admin-sidebar-mini', 'admin-sidebar-mini-expanded');
      resetSidebarWidth();
      return;
    }

    const shouldEnable = forceState !== null ? Boolean(forceState) : readMiniState();
    document.body.classList.toggle('admin-sidebar-mini', shouldEnable);

    if (shouldEnable) {
      resetSidebarWidth();
    } else {
      document.body.classList.remove('admin-sidebar-mini-expanded');
      applySidebarWidth(readSidebarWidth());
    }
  };

  const setMiniExpanded = (expanded) => {
    if (!isDesktop() || !isMiniMode()) return;
    document.body.classList.toggle('admin-sidebar-mini-expanded', Boolean(expanded));
  };

  // ========== INITIALIZATION ==========
  applyMiniMode();
  if (isDesktop()) applySidebarWidth(readSidebarWidth());

  // ========== TOGGLE BUTTON ==========
  sidebarToggles.forEach((toggle) => {
    toggle.addEventListener('click', (_e) => {
      _e.preventDefault();
      _e.stopPropagation();
      toggleSidebar();
    });
  });

  // ========== CLOSE BUTTONS ==========
  document.querySelectorAll('.sidebar-close').forEach((btn) => {
    btn.addEventListener('click', closeSidebar);
  });

  // ========== OVERLAY CLICK ==========
  overlay.addEventListener('click', closeSidebar);

  // ========== OUTSIDE CLICK (MOBILE) ==========
  let clickTimeout;
  document.addEventListener('click', (event) => {
    if (isDesktop() || !isSidebarOpen()) return;

    const target = event.target;
    if (!(target instanceof Element)) return;
    if (sidebar.contains(target)) return;
    if (overlay.contains(target)) return;
    if (Array.from(sidebarToggles).some((t) => t.contains(target))) return;

    clearTimeout(clickTimeout);
    clickTimeout = setTimeout(closeSidebar, 50);
  });

  // ========== MENU LINKS (MOBILE) ==========
  sidebar.querySelectorAll(
    'a.admin-sidebar-link[href]:not([data-sidebar-toggle="submenu"]), a.admin-sidebar-sublink[href]'
  ).forEach((link) => {
    link.addEventListener('click', () => {
      if (isMobile()) closeSidebar();
    });
  });

  // ========== KEYBOARD (ESC) ==========
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      if (isMobile() && isSidebarOpen()) {
        closeSidebar();
      } else if (isDesktop() && isMiniMode()) {
        setMiniExpanded(false);
      }
    }
  });

  // ========== MINI MODE TOGGLE (DESKTOP) ==========
  if (sidebarMiniToggle) {
    sidebarMiniToggle.addEventListener('click', (_e) => {
      _e.preventDefault();
      _e.stopPropagation();
      const nextState = !isMiniMode();
      document.body.classList.toggle('admin-sidebar-mini', nextState);
      document.body.classList.remove('admin-sidebar-mini-expanded');
      writeMiniState(nextState);

      if (nextState) {
        resetSidebarWidth();
      } else {
        applySidebarWidth(readSidebarWidth());
      }

      window.dispatchEvent(new CustomEvent('sidebar-mini-toggled', { detail: { enabled: nextState, }, }));
    });
  }

  // ========== MINI MODE HOVER (DESKTOP) ==========
  let hoverTimeout;
  sidebar.addEventListener('mouseenter', () => {
    clearTimeout(hoverTimeout);
    if (isDesktop() && isMiniMode()) {
      hoverTimeout = setTimeout(() => setMiniExpanded(true), 100);
    }
  });

  sidebar.addEventListener('mouseleave', () => {
    clearTimeout(hoverTimeout);
    if (isDesktop() && isMiniMode()) {
      setMiniExpanded(false);
    }
  });

  // ========== MINI MODE COLLAPSE (DESKTOP) ==========
  document.addEventListener('pointerdown', (event) => {
    if (!isDesktop() || !isMiniMode()) return;
    const target = event.target;
    if (sidebar.contains(target) || (sidebarMiniToggle && sidebarMiniToggle.contains(target))) return;
    setMiniExpanded(false);
  });

  // ========== WINDOW RESIZE ==========
  let resizeTimeout;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
      syncViewport();
      applyMiniMode();
    }, 150);
  });

  // ========== COLUMN RESIZER (DESKTOP) ==========
  if (sidebarResizer && adminShellRow && adminMain) {
    let isResizing = false;
    let pointerId = null;
    let liveWidth = readSidebarWidth();

    const startResize = (event) => {
      if (isMobile() || isMiniMode()) return;
      isResizing = true;
      pointerId = event.pointerId;
      try {
        sidebarResizer.setPointerCapture(pointerId);
      } catch {
        // ignore
      }
      adminShellRow.classList.add('is-resizing');
    };

    const moveResize = (event) => {
      if (!isResizing || pointerId !== event.pointerId) return;
      const rowRect = adminShellRow.getBoundingClientRect();
      liveWidth = clampWidth(event.clientX - rowRect.left, rowRect.width);
      applySidebarWidth(liveWidth);
    };

    const endResize = () => {
      if (!isResizing) return;
      isResizing = false;
      adminShellRow.classList.remove('is-resizing');
      if (isDesktop() && !isMiniMode()) {
        writeSidebarWidth(liveWidth);
      }
      if (pointerId !== null) {
        try {
          sidebarResizer.releasePointerCapture(pointerId);
        } catch {
          // ignore
        }
      }
      pointerId = null;
    };

    sidebarResizer.addEventListener('pointerdown', startResize);
    window.addEventListener('pointermove', moveResize);
    window.addEventListener('pointerup', endResize);
    window.addEventListener('pointercancel', endResize);

    // Double-click reset
    sidebarResizer.addEventListener('dblclick', () => {
      if (isMobile()) return;
      liveWidth = DEFAULT_SIDEBAR_WIDTH;
      writeSidebarWidth(liveWidth);
      applySidebarWidth(liveWidth);
    });
  }

  // ========== SUBMENU PERSISTENCE ==========
  try {
    const STORAGE_KEY = 'admin.sidebar.openSubmenus';
    const submenus = Array.from(sidebar.querySelectorAll('.admin-sidebar-submenu[id]'));
    const openIds = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');

    const setSubmenuState = (submenu, open) => {
      if (!submenu) return;
      submenu.classList.toggle('is-open', open);
      const trigger = sidebar.querySelector(
        `[data-sidebar-toggle="submenu"][data-sidebar-target="#${CSS.escape(submenu.id)}"]`
      );
      if (trigger) {
        trigger.classList.toggle('active', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      }
    };

    if (Array.isArray(openIds)) {
      openIds.slice(0, 1).forEach((id) => {
        const submenu = document.getElementById(id);
        setSubmenuState(submenu, true);
      });
    }

    sidebar.querySelectorAll('[data-sidebar-toggle="submenu"]').forEach((trigger) => {
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        const targetSelector = trigger.getAttribute('data-sidebar-target') || trigger.getAttribute('href');
        const submenu = targetSelector ? sidebar.querySelector(targetSelector) : null;
        if (!submenu) return;

        const willOpen = !submenu.classList.contains('is-open');
        submenus.forEach((item) => {
          if (item !== submenu) setSubmenuState(item, false);
        });
        setSubmenuState(submenu, willOpen);

        try {
          localStorage.setItem(STORAGE_KEY, JSON.stringify(willOpen ? [submenu.id,] : []));
        } catch (e) {
          // Silent fail
        }
      });
    });
  } catch (e) {
    // Silent fail
  }

  console.info('[Sidebar] Initialized');
}
