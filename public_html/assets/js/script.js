import './shared/logout-runtime.js';
import { initPublicHeaderDropdowns } from './modules/public-header.js';
import { initDatepickerLoader } from './datepicker-loader.js';


const getUserId = () => document.querySelector('meta[name="user-id"]')?.content || null;
const runWhenReady = (fn) => {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fn, { once: true, });
  } else {
    fn();
  }
};
const isAuthPageRoute = /\/(login|register|forgot-password|reset-password|verify-2fa)/.test(window.location.pathname);
const isLoggedIn = Boolean(getUserId());
const notificationContext = isLoggedIn ? 'user' : 'public';
const globalNotificationConfig = window.__APP_JS_CONFIG?.notifications || window.__APP_CONFIG?.notifications || {};
const NAV_DROPDOWN_OPEN_EVENT = 'brox:navbar-dropdown-open';
const NAV_DROPDOWN_CLOSE_EVENT = 'brox:navbar-dropdown-close';


const SITE_LANG = document.documentElement?.dataset.lang || 'en';
const SITE_TRANSLATIONS = window.__broxSiteTranslations || {};

// Initialize datepicker lazy loader
initDatepickerLoader();

function getCurrentSiteLanguage() {
  return document.documentElement?.dataset.lang || SITE_LANG || 'en';
}

function translateSiteText(key, defaultValue = key) {
  const lang = getCurrentSiteLanguage();
  const translations = SITE_TRANSLATIONS[lang] || {};
  return key && typeof key === 'string' ? (translations[key] ?? defaultValue) : defaultValue;
}

function applySiteTranslations(root = document) {
  if (!root || typeof root.querySelectorAll !== 'function') return;

  root.querySelectorAll('[data-i18n]').forEach((el) => {
    const key = el.getAttribute('data-i18n') || '';
    if (!key) return;
    const defaultText = el.dataset.i18nDefault || el.textContent.trim();
    el.textContent = translateSiteText(key, defaultText);
  });

  root.querySelectorAll('[data-i18n-placeholder]').forEach((el) => {
    const key = el.getAttribute('data-i18n-placeholder') || '';
    if (!key) return;
    const defaultText = el.dataset.i18nPlaceholderDefault || el.getAttribute('placeholder') || '';
    el.setAttribute('placeholder', translateSiteText(key, defaultText));
  });

  root.querySelectorAll('[data-i18n-title]').forEach((el) => {
    const key = el.getAttribute('data-i18n-title') || '';
    if (!key) return;
    const defaultText = el.dataset.i18nTitleDefault || el.getAttribute('title') || '';
    el.setAttribute('title', translateSiteText(key, defaultText));
  });

  root.querySelectorAll('[data-i18n-aria-label]').forEach((el) => {
    const key = el.getAttribute('data-i18n-aria-label') || '';
    if (!key) return;
    const defaultText = el.dataset.i18nAriaLabelDefault || el.getAttribute('aria-label') || '';
    el.setAttribute('aria-label', translateSiteText(key, defaultText));
  });
}

function registerSiteTranslations(lang, messages = {}) {
  SITE_TRANSLATIONS[lang] = Object.assign({}, SITE_TRANSLATIONS[lang] || {}, messages);
}

function setSiteLanguage(lang) {
  if (!lang) return;
  document.documentElement.dataset.lang = lang;
}

window.broxI18n = Object.assign(window.broxI18n || {}, {
  getCurrentLanguage: getCurrentSiteLanguage,
  translate: translateSiteText,
  apply: applySiteTranslations,
  registerTranslations: registerSiteTranslations,
  setLanguage: setSiteLanguage,
  messages: SITE_TRANSLATIONS,
});

runWhenReady(() => {
  window.broxI18n?.apply?.();
});

// Lazy-load notification runtime on non-auth pages
import('./public/modules/notification-runtime.js').then(mod => {
  mod.initPublicNotificationRuntime({
    runWhenReady,
    getUserId,
    translateSiteText,
    isLoggedIn,
    notificationContext,
    globalNotificationConfig,
    emitNavbarDropdownState,
    isAuthPageRoute,
  });
}).catch(() => {
  if (isAuthPageRoute) {
    window.__pendingFcmTokenSync = false;
    window.__requestFcmTokenSync = () => false;
    window.__fcmTokenObtained = false;
  }
});

/**
 * ============================================================
 * BROXBHAI NAVBAR - JavaScript Functions
 * ============================================================
 */


// Active link highlighting based on current URL
document.addEventListener('DOMContentLoaded', () => {
  const currentPath = window.location.pathname;
  const navLinks = document.querySelectorAll('.brox-nav-link');

  navLinks.forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPath || (href !== '/' && currentPath.startsWith(href))) {
      link.classList.add('brox-active');
    }
  });
});

// Close mobile menu when clicking outside
document.addEventListener('click', (event) => {
  const navbarCollapse = document.getElementById('broxMainNav');
  const navbarToggler = document.querySelector('.brox-mobile-toggle');

  if (navbarCollapse && navbarToggler) {
    const isClickInsideNav = navbarCollapse.contains(event.target);
    const isClickOnToggler = navbarToggler.contains(event.target);

    if (!isClickInsideNav && !isClickOnToggler && navbarCollapse.classList.contains('show')) {
      new window.broxUI.Collapse(navbarCollapse, {
        toggle: true,
      });
    }
  }
});

function resetDropdownViewportStyles(menuEl) {
  if (!menuEl) return;
  menuEl.style.removeProperty('position');
  menuEl.style.removeProperty('left');
  menuEl.style.removeProperty('top');
  menuEl.style.removeProperty('right');
  menuEl.style.removeProperty('bottom');
  menuEl.style.removeProperty('inset');
  menuEl.style.removeProperty('transform');
  menuEl.style.removeProperty('z-index');
}

function emitNavbarDropdownState(kind, open) {
  try {
    document.dispatchEvent(new CustomEvent(
      open ? NAV_DROPDOWN_OPEN_EVENT : NAV_DROPDOWN_CLOSE_EVENT,
      { detail: { kind, open: Boolean(open), timestamp: Date.now(), }, }
    ));
  } catch (error) {
    // Silent fail by design.
  }
}

function applyDropdownViewportRepositionFallback(menuEl, toggleEl) {
  if (!menuEl || !toggleEl) return;

  const computed = window.getComputedStyle(menuEl);
  if (computed.position === 'static') {
    // Mobile collapsed navbar keeps dropdowns in document flow.
    resetDropdownViewportStyles(menuEl);
    return;
  }

  const margin = 8;
  const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
  const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
  const rect = menuEl.getBoundingClientRect();
  const isOverflowingViewport = (
    rect.left < margin ||
    rect.right > viewportWidth - margin ||
    rect.top < margin ||
    rect.bottom > viewportHeight - margin
  );

  if (!isOverflowingViewport) {
    resetDropdownViewportStyles(menuEl);
    return;
  }

  const anchorRect = toggleEl.getBoundingClientRect();
  const menuWidth = Math.min(rect.width || menuEl.offsetWidth || 320, Math.max(180, viewportWidth - (margin * 2)));
  const menuHeight = Math.min(rect.height || menuEl.offsetHeight || 360, Math.max(160, viewportHeight - (margin * 2)));

  let left = anchorRect.right - menuWidth;
  left = Math.max(margin, Math.min(left, viewportWidth - menuWidth - margin));

  let top = anchorRect.bottom + 8;
  if ((top + menuHeight) > (viewportHeight - margin)) {
    const openUpwardTop = anchorRect.top - menuHeight - 8;
    top = openUpwardTop >= margin
      ? openUpwardTop
      : Math.max(margin, viewportHeight - menuHeight - margin);
  }

  menuEl.style.position = 'fixed';
  menuEl.style.left = `${Math.round(left)}px`;
  menuEl.style.top = `${Math.round(top)}px`;
  menuEl.style.right = 'auto';
  menuEl.style.bottom = 'auto';
  menuEl.style.inset = 'auto';
  menuEl.style.transform = 'none';
  menuEl.style.zIndex = '1080';
}

function getNavbarDropdownMenu(toggleEl) {
  if (!toggleEl) return null;
  const parentDropdown = toggleEl.closest('.dropdown');
  return parentDropdown?.querySelector('.dropdown-menu') || null;
}

function initNavbarDropdownViewportFallback() {
  const navbar = document.querySelector('.brox-navbar-container');
  if (!navbar) return;

  const toggles = Array.from(
    navbar.querySelectorAll('.dropdown-toggle[data-brox-toggle="dropdown"], [data-notification-bell][data-brox-toggle="dropdown"]')
  );
  if (!toggles.length) return;

  const recalcVisibleDropdowns = () => {
    toggles.forEach((toggleEl) => {
      const menuEl = getNavbarDropdownMenu(toggleEl);
      if (!menuEl || !menuEl.classList.contains('show')) return;
      applyDropdownViewportRepositionFallback(menuEl, toggleEl);
    });
  };

  toggles.forEach((toggleEl) => {
    const menuEl = getNavbarDropdownMenu(toggleEl);
    if (!menuEl) return;

    const handleShown = () => {
      window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
          applyDropdownViewportRepositionFallback(menuEl, toggleEl);
        });
      });
    };

    const handleHidden = () => {
      resetDropdownViewportStyles(menuEl);
    };

    toggleEl.addEventListener('brox:shown', handleShown);
    toggleEl.addEventListener('brox:hidden', handleHidden);
  });

  window.addEventListener('resize', recalcVisibleDropdowns);
  window.addEventListener('scroll', recalcVisibleDropdowns, { passive: true, });
}

// Smooth scroll for anchor links
document.querySelectorAll('.brox-nav-link[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    const href = this.getAttribute('href');
    if (href !== '#' && href !== '') {
      e.preventDefault();
      const target = document.querySelector(href);
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start',
        });
      }
    }
  });
});

// Add scroll state to navbar without scroll-linked positioning writes.
function initNavbarScrollState() {
  const broxNavbar = document.querySelector('.brox-navbar-container');
  if (!broxNavbar) return;

  const applyScrolledState = (isScrolled) => {
    broxNavbar.classList.toggle('is-scrolled', Boolean(isScrolled));
  };

  if ('IntersectionObserver' in window) {
    let sentinel = document.querySelector('[data-brox-scroll-sentinel]');
    if (!sentinel) {
      sentinel = document.createElement('span');
      sentinel.setAttribute('data-brox-scroll-sentinel', '1');
      sentinel.setAttribute('aria-hidden', 'true');
      sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:1px;pointer-events:none;';
      document.body.prepend(sentinel);
    }

    const observer = new IntersectionObserver((entries) => {
      const entry = entries[0];
      applyScrolledState(!entry.isIntersecting);
    }, {
      root: null,
      threshold: [0,],
      rootMargin: '-8px 0px 0px 0px',
    });

    observer.observe(sentinel);
    return;
  }

  const onScroll = () => {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
    applyScrolledState(scrollTop > 8);
  };

  window.addEventListener('scroll', onScroll, { passive: true, });
  onScroll();
}

function initNavbarUserDropdownMobileFallback() {
  const toggleEl = document.getElementById('broxNavbarUser');
  if (!toggleEl) return;

  const dropdownEl = toggleEl.closest('.dropdown');
  const menuEl = dropdownEl?.querySelector('.dropdown-menu');
  if (!dropdownEl || !menuEl) return;

  const isMobileViewport = () => window.matchMedia('(max-width: 991.98px)').matches;
  const isOpen = () => menuEl.classList.contains('show');

  const setOpenState = (open) => {
    const wasOpen = isOpen();
    menuEl.classList.toggle('show', open);
    toggleEl.classList.toggle('show', open);
    dropdownEl.classList.toggle('show', open);
    toggleEl.setAttribute('aria-expanded', open ? 'true' : 'false');

    if (open) {
      menuEl.style.display = 'block';
      menuEl.style.visibility = 'visible';
      menuEl.style.opacity = '1';
      menuEl.style.pointerEvents = 'auto';
    } else {
      menuEl.style.display = '';
      menuEl.style.visibility = '';
      menuEl.style.opacity = '';
      menuEl.style.pointerEvents = '';
      resetDropdownViewportStyles(menuEl);
    }
    if (open !== wasOpen) {
      emitNavbarDropdownState('user', open);
    }
  };

  const hideForExternalOpen = (event) => {
    const sourceKind = String(event?.detail?.kind || '');
    const isOpening = event?.detail?.open === true;
    if (!isOpening || sourceKind === 'user') return;
    if (!isOpen()) return;

    if (!isMobileViewport() && window.broxUI.Dropdown.getOrCreateInstance) {
      const instance = window.broxUI.Dropdown.getOrCreateInstance(toggleEl);
      if (instance && typeof instance.hide === 'function') {
        instance.hide();
        return;
      }
    }
    setOpenState(false);
  };

  toggleEl.addEventListener('click', (event) => {
    if (!isMobileViewport()) return;
    event.preventDefault();
    event.stopPropagation();
    setOpenState(!isOpen());
  });

  menuEl.addEventListener('click', (event) => {
    if (!isMobileViewport()) return;
    const itemEl = event.target instanceof Element
      ? event.target.closest('.dropdown-item, .brox-dropdown-item')
      : null;
    if (itemEl) {
      setOpenState(false);
    }
  });

  toggleEl.addEventListener('brox:shown', () => {
    if (!isMobileViewport()) {
      emitNavbarDropdownState('user', true);
    }
  });
  toggleEl.addEventListener('brox:hidden', () => {
    if (!isMobileViewport()) {
      emitNavbarDropdownState('user', false);
    }
  });

  document.addEventListener(NAV_DROPDOWN_OPEN_EVENT, hideForExternalOpen);

  document.addEventListener('click', (event) => {
    if (!isMobileViewport() || !isOpen()) return;
    const targetEl = event.target;
    if (!(targetEl instanceof Element)) return;
    if (toggleEl.contains(targetEl) || menuEl.contains(targetEl)) return;
    setOpenState(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && isOpen()) {
      setOpenState(false);
    }
  });

  window.addEventListener('resize', () => {
    if (!isMobileViewport() && isOpen()) {
      setOpenState(false);
    }
  });
}

runWhenReady(initNavbarScrollState);
runWhenReady(initNavbarDropdownViewportFallback);
runWhenReady(initNavbarUserDropdownMobileFallback);
runWhenReady(initPublicHeaderDropdowns);

// Export functions for external use
let notificationSystemApiPromise = null;
const loadNotificationSystemApi = () => {
  if (!notificationSystemApiPromise) {
    const moduleUrl = typeof withAssetVersion === 'function'
      ? withAssetVersion('/assets/firebase/v2/dist/notification-system.js')
      : '/assets/firebase/v2/dist/notification-system.js';
    notificationSystemApiPromise = import(moduleUrl)
      .catch((error) => {
        notificationSystemApiPromise = null;
        throw error;
      });
  }
  return notificationSystemApiPromise;
};

window.BroxNavbar = {
  // Lazy wrappers: prefer module APIs, fallback to legacy helpers if available
  loadNotifications: async function (...args) {
    try {
      const mod = await loadNotificationSystemApi();
      if (typeof mod.loadUserNotifications === 'function') {
        return mod.loadUserNotifications(...args);
      }
      if (typeof mod.broxLoadNotifications === 'function') {
        return mod.broxLoadNotifications(...args);
      }
    } catch (error) {
      // Fall through to no-op return.
    }
    // No-op fallback
    return Promise.resolve(null);
  },
  markNotificationRead: async function (notificationId, ...args) {
    try {
      const mod = await loadNotificationSystemApi();
      if (typeof mod.markNotificationAsRead === 'function') {
        return mod.markNotificationAsRead(notificationId, ...args);
      }
      if (typeof mod.broxMarkNotificationRead === 'function') {
        return mod.broxMarkNotificationRead(notificationId, ...args);
      }
    } catch (error) {
      // Fall through to no-op return.
    }
    return Promise.resolve(false);
  },
};

// ==================== CAROUSEL / COUNTER RUNTIME ====================
const CAROUSEL_SELECTOR = [
  '[id^="post_carousel_"]',
  '[id^="page_carousel_"]',
  '[id^="tag_carousel_"]',
  '[id^="category_carousel_"]',
  '[id^="related_post_carousel_"]',
  '[id^="related_page_carousel_"]',
  '[id^="related_mobile_carousel_"]',
].join(',');

const CAROUSEL_DEFAULT_OPTIONS = {
  interval: 5000,
  wrap: true,
  keyboard: true,
  pause: 'hover',
  touch: true,
};

function initializeCarouselElement(el) {
  if (!el || el.dataset.carouselInitialized === 'true') return;
  if (typeof window.broxUI.Carousel !== 'function') return;

  const options = {
    ...CAROUSEL_DEFAULT_OPTIONS,
    interval: el.dataset.interval ? Number(el.dataset.interval) : CAROUSEL_DEFAULT_OPTIONS.interval,
    pause: el.dataset.pause ?? CAROUSEL_DEFAULT_OPTIONS.pause,
    wrap: el.dataset.wrap !== 'false',
    keyboard: el.dataset.keyboard !== 'false',
    touch: el.dataset.touch !== 'false',
  };

  try {
    new window.broxUI.Carousel(el, options);
    el.dataset.carouselInitialized = 'true';
  } catch (error) {
    // Keep the page functional even if one carousel fails.
  }
}

function setupUnifiedCarousels() {
  const MAX_RETRY = 5;
  const RETRY_DELAY_MS = 800;
  let retryCount = 0;

  const initializeCarousels = (root = document) => {
    if (typeof window.broxUI.Carousel !== 'function') {
      if (retryCount < MAX_RETRY) {
        retryCount += 1;
        setTimeout(() => initializeCarousels(root), RETRY_DELAY_MS);
      }
      return;
    }

    const carousels = root.querySelectorAll ? root.querySelectorAll(CAROUSEL_SELECTOR) : [];
    if (!carousels.length) return;

    requestAnimationFrame(() => {
      carousels.forEach((el) => initializeCarouselElement(el));
    });
  };

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node && node.nodeType === 1) {
          initializeCarousels(node);
        }
      });
    });
  });

  runWhenReady(() => {
    initializeCarousels();
    if (document.body) {
      observer.observe(document.body, { childList: true, subtree: true, });
    }
  });

  window.reinitializeCarousels = initializeCarousels;
}

class CounterAnimation {
  constructor(element, options = {}) {
    this.element = element;
    this.target = parseInt(element?.dataset?.target || '0', 10) || 0;
    this.current = 0;
    this.duration = Number(options.duration || 2000);
    this.decimals = Number(options.decimals || 0);
    this.prefix = options.prefix || '';
    this.suffix = options.suffix || '';
    this.separator = options.separator ?? ',';
    this.animated = false;
  }

  easeOutQuad(t) {
    return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
  }

  formatNumber(num) {
    let formatted = this.decimals > 0 ? Number(num).toFixed(this.decimals) : Math.floor(num).toString();
    if (this.decimals > 0) {
      formatted = parseFloat(formatted).toString();
    }
    if (this.separator !== '') {
      formatted = formatted.replace(/\B(?=(\d{3})+(?!\d))/g, this.separator);
    }
    return `${this.prefix}${formatted}${this.suffix}`;
  }

  start() {
    if (!this.element || this.animated) return;
    this.animated = true;

    const startTime = performance.now();
    const startValue = this.current;

    const animate = (currentTime) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / this.duration, 1);
      const easedProgress = this.easeOutQuad(progress);
      this.current = startValue + (this.target - startValue) * easedProgress;
      this.element.textContent = this.formatNumber(this.current);

      if (progress < 1) {
        requestAnimationFrame(animate);
      } else {
        this.current = this.target;
        this.element.textContent = this.formatNumber(this.target);
      }
    };

    requestAnimationFrame(animate);
  }
}

function initializeCounters(selector = '.counter', options = {}) {
  const counters = document.querySelectorAll(selector);
  if (!counters.length) return;

  const observer = new IntersectionObserver((entries, obs) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting || entry.target.dataset.animating === 'true') return;
      entry.target.dataset.animating = 'true';
      const counter = new CounterAnimation(entry.target, options);
      counter.start();
      obs.unobserve(entry.target);
    });
  }, { threshold: 0.5, });

  counters.forEach((counterEl) => observer.observe(counterEl));
}

async function fetchStatistics(endpoint = '/api/statistics') {
  try {
    const response = await fetch(endpoint);
    if (!response.ok) throw new Error(`Failed to fetch statistics (${response.status})`);
    return await response.json();
  } catch (error) {
    return null;
  }
}

function updateCounterValue(selector, value) {
  const element = document.querySelector(selector);
  if (!element) return;

  element.dataset.target = String(value);
  const counter = new CounterAnimation(element);
  counter.start();
}

async function initializeRealtimeCounters(endpoint = '/api/statistics') {
  const stats = await fetchStatistics(endpoint);
  if (stats && typeof stats === 'object') {
    Object.entries(stats).forEach(([key, value,]) => {
      updateCounterValue(`[data-stat="${key}"]`, value);
    });
  }

  setInterval(async () => {
    const updatedStats = await fetchStatistics(endpoint);
    if (!updatedStats || typeof updatedStats !== 'object') return;

    Object.entries(updatedStats).forEach(([key, value,]) => {
      const selector = `[data-stat="${key}"]`;
      const element = document.querySelector(selector);
      if (!element) return;

      const previous = parseInt(element.dataset.target || '0', 10);
      if (previous === Number(value)) return;

      element.dataset.target = String(value);
      const counter = new CounterAnimation(element);
      counter.current = previous;
      counter.target = Number(value);
      counter.animated = false;
      counter.start();
    });
  }, 30000);
}

runWhenReady(() => {
  setupUnifiedCarousels();
  if (document.querySelector('[data-stat]')) {
    initializeRealtimeCounters();
  } else {
    initializeCounters();
  }
});

window.CounterAnimation = CounterAnimation;
window.initializeCounters = initializeCounters;
window.fetchStatistics = fetchStatistics;
window.updateCounterValue = updateCounterValue;
window.initializeRealtimeCounters = initializeRealtimeCounters;
