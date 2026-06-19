/**
 * brox-i18n.js — Client-Side Bangla/English Language Switcher
 *
 * Features:
 *  - Instant switch via data-i18n-en / data-i18n-bn attributes (no API call)
 *  - data-i18n fallback with server cache check (window.__broxSiteTranslations)
 *  - Dynamic API fallback via /api/translate (batch, chunked)
 *  - data-lang-btn toggle buttons (no page reload)
 *  - URL update via history.replaceState (shareable URLs)
 *  - localStorage preference persistence
 *  - Custom events for cross-component sync
 *  - Updates <html lang>, meta tags, and SEO hreflang
 *  - Integrates with existing MedEx lang toggle
 *
 * Usage:
 *   <span data-i18n="Home" data-i18n-en="Home" data-i18n-bn="হোম">Home</span>
 *   <a data-lang-btn="bn" href="?lang=bn">বাংলা</a>
 */

const BROXI18N_STORAGE_KEY = 'brox-i18n-lang';
const BROXI18N_LANG_CHANGE = 'brox:langchange';
const BROXI18N_ACCEPTED_LANGS = ['en', 'bn',];

let _currentLang = detectInitialLang();
let _isTranslating = false;
const _cache = window.__broxClientTranslationCache || {};
window.__broxClientTranslationCache = _cache;

// ======================== Initialisation ========================

function detectInitialLang() {
  // Priority: URL param > localStorage > html lang attr > default 'en'
  try {
    const urlParams = new URLSearchParams(window.location.search);
    const urlLang = urlParams.get('lang');
    if (BROXI18N_ACCEPTED_LANGS.includes(urlLang)) return urlLang;

    const stored = localStorage.getItem(BROXI18N_STORAGE_KEY);
    if (BROXI18N_ACCEPTED_LANGS.includes(stored)) return stored;
  } catch (_) { /* ignore */ }

  const htmlLang = document.documentElement.getAttribute('lang');
  if (BROXI18N_ACCEPTED_LANGS.includes(htmlLang)) return htmlLang;

  return 'en';
}

// ======================== Cache Helpers ========================

function cacheKey(text, lang) {
  return `${lang}::${text}`;
}

function getCached(text, lang) {
  if (_cache[cacheKey(text, lang)]) return _cache[cacheKey(text, lang)];

  // Check server-side pre-loaded translations
  const serverT = window.__broxSiteTranslations;
  if (serverT && serverT[lang] && serverT[lang][text]) {
    _cache[cacheKey(text, lang)] = serverT[lang][text];
    return serverT[lang][text];
  }
  return null;
}

function setCached(text, lang, translation) {
  if (text && lang && translation) {
    _cache[cacheKey(text, lang)] = translation;
  }
}

// ======================== DOM Translation ========================

function applyTranslation(root) {
  root = root || document;
  if (!root || typeof root.querySelectorAll !== 'function') return;

  // Method 1: data-i18n-en + data-i18n-bn (instant, no API call)
  root.querySelectorAll('[data-i18n-en][data-i18n-bn]').forEach((el) => {
    const attr = `data-i18n-${_currentLang}`;
    const translation = el.getAttribute(attr);
    if (translation !== null && translation !== '') {
      el.innerHTML = translation;
    }
  });

  // Method 2: data-i18n (fallback — checks cache, then queues API batch)
  root.querySelectorAll('[data-i18n]').forEach((el) => {
    // Skip if already handled by Method 1
    if (el.hasAttribute('data-i18n-en') && el.hasAttribute('data-i18n-bn')) return;
    const key = el.getAttribute('data-i18n') || '';
    if (!key) return;
    const cached = getCached(key, _currentLang);
    if (cached) {
      el.innerHTML = cached;
      return;
    }
    el.dataset.i18nPending = key;
  });

  // data-i18n-title
  root.querySelectorAll('[data-i18n-title]').forEach((el) => {
    const key = el.getAttribute('data-i18n-title') || '';
    if (!key) return;
    const cached = getCached(key, _currentLang);
    if (cached) {
      el.setAttribute('title', cached);
    } else {
      el.dataset.i18nTitlePending = key;
    }
  });

  // data-i18n-aria-label
  root.querySelectorAll('[data-i18n-aria-label]').forEach((el) => {
    const key = el.getAttribute('data-i18n-aria-label') || '';
    if (!key) return;
    const cached = getCached(key, _currentLang);
    if (cached) {
      el.setAttribute('aria-label', cached);
    } else {
      el.dataset.i18nAriaLabelPending = key;
    }
  });

  // data-i18n-placeholder
  root.querySelectorAll('[data-i18n-placeholder]').forEach((el) => {
    const key = el.getAttribute('data-i18n-placeholder') || '';
    if (!key) return;
    const cached = getCached(key, _currentLang);
    if (cached) {
      el.setAttribute('placeholder', cached);
    } else {
      el.dataset.i18nPlaceholderPending = key;
    }
  });

  fetchPendingBatch();
}

// ======================== Batch API Translation ========================

function fetchPendingBatch() {
  if (_isTranslating) return;

  const texts = [];
  const items = [];

  document.querySelectorAll('[data-i18n-pending]').forEach((el) => {
    const t = el.dataset.i18nPending;
    if (t && texts.indexOf(t) === -1) {
      texts.push(t);
      items.push({ el: el, attr: 'innerHTML', key: t, });
    }
    delete el.dataset.i18nPending;
  });
  document.querySelectorAll('[data-i18n-title-pending]').forEach((el) => {
    const t = el.dataset.i18nTitlePending;
    if (t && texts.indexOf(t) === -1) {
      texts.push(t);
      items.push({ el: el, attr: 'title', key: t, });
    }
    delete el.dataset.i18nTitlePending;
  });
  document.querySelectorAll('[data-i18n-aria-label-pending]').forEach((el) => {
    const t = el.dataset.i18nAriaLabelPending;
    if (t && texts.indexOf(t) === -1) {
      texts.push(t);
      items.push({ el: el, attr: 'ariaLabel', key: t, });
    }
    delete el.dataset.i18nAriaLabelPending;
  });
  document.querySelectorAll('[data-i18n-placeholder-pending]').forEach((el) => {
    const t = el.dataset.i18nPlaceholderPending;
    if (t && texts.indexOf(t) === -1) {
      texts.push(t);
      items.push({ el: el, attr: 'placeholder', key: t, });
    }
    delete el.dataset.i18nPlaceholderPending;
  });

  if (texts.length === 0) return;

  _isTranslating = true;
  const BATCH_SIZE = 10;

  (function sendBatch(start) {
    const batch = texts.slice(start, start + BATCH_SIZE);
    if (batch.length === 0) {
      _isTranslating = false;
      return;
    }

    fetch('/api/translate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', },
      body: JSON.stringify({ texts: batch, from: 'en', to: _currentLang, }),
    })
      .then((res) => { return res.json(); })
      .then((data) => {
        if (data.success && data.translations) {
          items.forEach((item) => {
            const translated = data.translations[item.key];
            if (!translated) return;
            setCached(item.key, _currentLang, translated);
            if (item.el && item.el.isConnected) {
              if (item.attr === 'innerHTML') item.el.innerHTML = translated;
              else if (item.attr === 'title') item.el.setAttribute('title', translated);
              else if (item.attr === 'ariaLabel') item.el.setAttribute('aria-label', translated);
              else if (item.attr === 'placeholder') item.el.setAttribute('placeholder', translated);
            }
          });
        }
      })
      .catch((err) => {
        console.warn('[brox-i18n] Batch translate failed:', err);
      })
      .finally(() => {
        sendBatch(start + BATCH_SIZE);
      });
  })(0);
}

// ======================== Wire data-lang-btn ========================

function wireLangButtons(root) {
  root = root || document;
  if (!root || typeof root.querySelectorAll !== 'function') return;

  root.querySelectorAll('[data-lang-btn]').forEach((btn) => {
    // Remove existing listener to avoid duplicates
    btn.removeEventListener('click', handleLangBtnClick);
    btn.addEventListener('click', handleLangBtnClick);
  });
}

function handleLangBtnClick(e) {
  e.preventDefault();
  const lang = this.getAttribute('data-lang-btn');
  if (!lang) return;
  switchLanguage(lang);
}

// ======================== Flag SVG Templates ========================

/** @type {string} Bangladesh flag inner SVG content */
const FLAG_BD_INNER = '<rect width="60" height="40" fill="#006a4e"/><circle cx="23" cy="20" r="12" fill="#f42a41"/>';

/** @type {string} USA flag inner SVG content */
const FLAG_US_INNER = '<rect width="60" height="40" fill="#fff"/><g fill="#b22234"><rect y="0" width="60" height="3.08"/><rect y="6.15" width="60" height="3.08"/><rect y="12.31" width="60" height="3.08"/><rect y="18.46" width="60" height="3.08"/><rect y="24.62" width="60" height="3.08"/><rect y="30.77" width="60" height="3.08"/><rect y="36.92" width="60" height="3.08"/></g><rect width="24" height="21.54" fill="#3c3b6e"/>';

/**
 * Get the correct flag SVG markup for the currently active language.
 * @param {string} currentLang - The currently active language ('en' | 'bn')
 * @returns {string}
 */
function getFlagSvgMarkup(currentLang) {
  return currentLang === 'bn' ? FLAG_BD_INNER : FLAG_US_INNER;
}

/**
 * Keep toggle buttons visually and semantically in sync with the active language.
 * @param {HTMLElement} btn
 * @param {string} currentLang
 */
function updateLangToggleButton(btn, currentLang) {
  if (!btn || !btn.matches('[data-lang-btn]')) return;

  const targetLang = currentLang === 'bn' ? 'en' : 'bn';
  btn.setAttribute('data-lang-btn', targetLang);
  btn.setAttribute(
    'aria-label',
    currentLang === 'bn' ? 'Switch to English' : 'বাংলায় পরিবর্তন করুন'
  );
  btn.setAttribute(
    'title',
    currentLang === 'bn' ? 'English' : 'বাংলা'
  );

  const svg = btn.querySelector('svg');
  if (svg) {
    svg.innerHTML = getFlagSvgMarkup(currentLang);
  }
}

/**
 * Swap the inline flag SVG content inside [data-lang-btn] buttons
 * so the flag reflects the CURRENT active language.
 * @param {string} currentLang - The currently active language ('en' | 'bn')
 */
function swapFlagSvgs(currentLang) {
  const toggleBtns = document.querySelectorAll('[data-lang-btn]');
  for (let i = 0; i < toggleBtns.length; i++) {
    updateLangToggleButton(toggleBtns[i], currentLang);
  }
}

// ======================== Language Switch ========================

function switchLanguage(lang) {
  if (!BROXI18N_ACCEPTED_LANGS.includes(lang)) return;
  if (lang === _currentLang) return;

  const oldLang = _currentLang;
  _currentLang = lang;

  // Update <html> element
  document.documentElement.setAttribute('lang', lang);
  if (document.documentElement.dataset) {
    document.documentElement.dataset.lang = lang;
  }

  // Update URL without reload
  try {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', lang);
    window.history.replaceState({ lang: lang, }, '', url.toString());
  } catch (_) { /* ignore */ }

  // Persist preference
  try {
    localStorage.setItem(BROXI18N_STORAGE_KEY, lang);
  } catch (_) { /* ignore */ }

  // Update canonical / hreflang links
  updateSeoLinks(lang);

  // Apply translations to visible DOM
  applyTranslation();

  // Wire any dynamically added lang buttons
  wireLangButtons();

  // Dispatch custom event for cross-component sync
  try {
    window.dispatchEvent(new CustomEvent(BROXI18N_LANG_CHANGE, {
      detail: { oldLang: oldLang, newLang: lang, },
    }));
  } catch (_) { /* ignore */ }

  // Notify MedEx if available
  if (window.medexTogglePageLang) {
    window.medexTogglePageLang(lang);
  }

  // Swap flag SVGs to show the current language's flag
  swapFlagSvgs(lang);
}

// ======================== SEO Link Updates ========================

function updateSeoLinks(lang) {
  // Update hreflang links
  const alternateEn = document.querySelector('link[hreflang="en"]');
  const alternateBn = document.querySelector('link[hreflang="bn"]');

  const currentUrl = window.location.href;

  if (alternateEn) {
    const enUrl = setUrlParam(currentUrl, 'lang', 'en');
    alternateEn.setAttribute('href', enUrl);
  }
  if (alternateBn) {
    const bnUrl = setUrlParam(currentUrl, 'lang', 'bn');
    alternateBn.setAttribute('href', bnUrl);
  }

  // Update OG locale
  const ogLocale = document.querySelector('meta[property="og:locale"]');
  if (ogLocale) {
    ogLocale.setAttribute('content', lang === 'bn' ? 'bn_BD' : 'en_US');
  }

  // Update language meta and html lang attribute
  const langMeta = document.querySelector('meta[name="language"]');
  if (langMeta) {
    langMeta.setAttribute('content', lang === 'bn' ? 'Bengali' : 'English');
  }

  // Update JSON-LD inLanguage
  const ldJsonScripts = document.querySelectorAll('script[type="application/ld+json"]');
  ldJsonScripts.forEach((script) => {
    try {
      const json = JSON.parse(script.textContent);
      if (json.inLanguage) {
        json.inLanguage = lang === 'bn' ? 'bn-BD' : 'en-US';
        script.textContent = JSON.stringify(json, null, 2);
      }
    } catch (_) { /* ignore — not all JSON-LD has inLanguage */ }
  });
}

function setUrlParam(url, key, value) {
  try {
    const u = new URL(url, window.location.origin);
    u.searchParams.set(key, value);
    return u.toString();
  } catch (_) {
    return url;
  }
}

// ======================== Public API ========================

const broxI18n = {
  /** Get current language code ('en' | 'bn') */
  getLang: function () { return _currentLang; },

  /** Set language and trigger switch */
  setLanguage: function (lang) { switchLanguage(lang); },

  /** Apply translations to root element (or entire document) */
  translate: function (root) { applyTranslation(root || document); },

  /** Wire lang buttons within root */
  wire: function (root) { wireLangButtons(root || document); },

  /** Force re-init (e.g., after dynamic content load) */
  refresh: function () {
    applyTranslation();
    wireLangButtons();
  },

  /** Translate a single text string (returns cached or null) */
  translateText: function (text, to) {
    if (!to) to = _currentLang;
    if (to === 'en') return text; // English is source, no translation needed
    return getCached(text, to) || text;
  },
};

// Export to global for backward compatibility
window.broxI18n = broxI18n;

// ======================== Auto-Init ========================

function init() {
  // Apply initial language to DOM
  applyTranslation();

  // Wire all data-lang-btn elements
  wireLangButtons();

  // Listen for MedEx page language toggles
  document.addEventListener('brox:medex-langchange', (e) => {
    if (e.detail && e.detail.lang) {
      switchLanguage(e.detail.lang);
    }
  });

  // If URL has lang param but it differs from detected, update
  try {
    const urlLang = new URLSearchParams(window.location.search).get('lang');
    if (urlLang && urlLang !== _currentLang && BROXI18N_ACCEPTED_LANGS.includes(urlLang)) {
      _currentLang = urlLang;
      applyTranslation();
    }
  } catch (_) { /* ignore */ }
}

// Run on DOMContentLoaded (safe to call multiple times)
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true, });
} else {
  init();
}

// Re-init when new content is loaded dynamically (for frameworks like HTMX/Turbo)
// These are included for compatibility but have no effect if the frameworks aren't present.

export default broxI18n;
