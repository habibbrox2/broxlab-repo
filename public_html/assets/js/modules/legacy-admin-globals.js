/**
 * Legacy Admin Globals
 *
 * Extracted from admin.js IIFE to reduce complexity.
 * Provides helpers and global window assignments for backward
 * compatibility with inline <script> blocks in admin Twig templates.
 *
 * Usage:
 *   import { ensureLegacyAdminGlobals, byId, ... } from './modules/legacy-admin-globals.js';
 *   ensureLegacyAdminGlobals({ loadAdminModule, logModuleError, ... });
 */

/**
 * Get element by ID.
 * @param {string} id
 * @returns {HTMLElement|null}
 */
export function byId(id) {
  return document.getElementById(id);
}

/**
 * Retrieve CSRF token from DOM.
 * @returns {string}
 */
export function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/**
 * Get admin directory path from body dataset.
 * @returns {string}
 */
export function getAdminDir() {
  return document.body?.dataset?.adminDir || '/admin';
}

/** @type {Object} Default tag combobox options */
export const TAG_COMBOBOX_DEFAULT_OPTIONS = {
  allowCreate: true,
  searchMode: 'client',
  sourceEndpoint: '/api/tags/list-json',
  createEndpoint: '/api/tags/create',
  maxResults: 50,
};

/** @type {Object} Default category combobox options */
export const CATEGORY_COMBOBOX_DEFAULT_OPTIONS = {
  allowCreate: true,
  searchMode: 'client',
  sourceEndpoint: '/api/categories/list-json',
  createEndpoint: '/api/categories/create',
  maxResults: 50,
};

/**
 * Safely parse JSON with fallback.
 * @param {*} value - JSON string to parse
 * @param {*} fallback - Value to return if parsing fails
 * @returns {*} Parsed object or fallback
 */
export function parseJson(value, fallback) {
  if (!value) return fallback;
  try {
    return JSON.parse(value);
  } catch {
    return fallback;
  }
}

/**
 * Escape HTML characters for safe display.
 * @param {*} text - Text to escape
 * @returns {string}
 */
export function escapeHtml(text) {
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;', };
  return String(text ?? '').replace(/[&<>"']/g, (char) => map[char]);
}

/**
 * Normalize an array of values to numeric ID strings.
 * @param {*} value
 * @returns {string[]}
 */
function normalizeNumericIds(value) {
  if (!Array.isArray(value)) return [];
  return value
    .map((item) => {
      if (typeof item === 'number' || typeof item === 'string') return String(item).trim();
      if (item && typeof item === 'object' && item.id !== undefined)
        return String(item.id).trim();
      return '';
    })
    .filter((id) => /^\d+$/.test(id));
}

/**
 * Ensure backward-compatible globals on `window` for inline <script> blocks
 * in admin Twig templates.
 *
 * @param {Object} deps
 * @param {Function} deps.loadAdminModule - Admin module lazy loader
 * @param {Function} deps.logModuleError - Error logger for module loads
 */
export function ensureLegacyAdminGlobals(deps) {
  const { loadAdminModule, logModuleError, } = deps;

  if (typeof window.transliterateAndGenerateSlug !== 'function') {
    const bnDigitMap = {
      '\u09E6': '0', '\u09E7': '1', '\u09E8': '2', '\u09E9': '3',
      '\u09EA': '4', '\u09EB': '5', '\u09EC': '6', '\u09ED': '7',
      '\u09EE': '8', '\u09EF': '9',
    };
    const bnBasicMap = {
      '\u0985': 'o', '\u0986': 'a', '\u0987': 'i', '\u0988': 'i',
      '\u0989': 'u', '\u098A': 'u', '\u098F': 'e', '\u0990': 'oi',
      '\u0993': 'o', '\u0994': 'ou', '\u0995': 'k', '\u0996': 'kh',
      '\u0997': 'g', '\u0998': 'gh', '\u0999': 'ng', '\u099A': 'ch',
      '\u099B': 'chh', '\u099C': 'j', '\u099D': 'jh', '\u099E': 'n',
      '\u099F': 't', '\u09A0': 'th', '\u09A1': 'd', '\u09A2': 'dh',
      '\u09A3': 'n', '\u09A4': 't', '\u09A5': 'th', '\u09A6': 'd',
      '\u09A7': 'dh', '\u09A8': 'n', '\u09AA': 'p', '\u09AB': 'ph',
      '\u09AC': 'b', '\u09AD': 'bh', '\u09AE': 'm', '\u09AF': 'y',
      '\u09B0': 'r', '\u09B2': 'l', '\u09B6': 'sh', '\u09B7': 'sh',
      '\u09B8': 's', '\u09B9': 'h', '\u09BE': 'a', '\u09BF': 'i',
      '\u09C0': 'i', '\u09C1': 'u', '\u09C2': 'u', '\u09C7': 'e',
      '\u09C8': 'oi', '\u09CB': 'o', '\u09CC': 'ou', '\u0982': 'ng',
      '\u0983': 'h', '\u0981': 'n',
    };
    const transliterateBn = (text) =>
      String(text || '').split('')
        .map((ch) => bnDigitMap[ch] ?? bnBasicMap[ch] ?? ch)
        .join('');

    window.transliterateAndGenerateSlug = function (text) {
      const raw = transliterateBn(text);
      return raw
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, ' ')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 200);
    };
  }

  if (typeof window.initializeServiceSlugGenerator !== 'function') {
    window.initializeServiceSlugGenerator = function (excludeId = null) {
      const nameInput = document.querySelector('input[name="name"]');
      const slugInput = document.querySelector('input[name="slug"]');
      const feedback = document.querySelector('#slug-feedback');
      if (!nameInput || !slugInput) return null;

      let manualEdit = false;
      let timer = null;

      const setFeedback = (message = '', state = '') => {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.classList.remove('text-emerald-600', 'text-red-600', 'text-slate-400');
        if (state === 'ok') feedback.classList.add('text-emerald-600');
        else if (state === 'bad') feedback.classList.add('text-red-600');
        else feedback.classList.add('text-slate-400');
      };

      const checkSlug = async (slug) => {
        if (!slug) { setFeedback(''); return; }
        try {
          const q = new URLSearchParams({ slug: slug, });
          if (excludeId) q.set('exclude_id', String(excludeId));
          const res = await fetch(`/api/services/check-slug?${q.toString()}`);
          const data = await res.json();
          if (data?.success && data?.available) {
            setFeedback(data.message || 'Slug available', 'ok');
          } else {
            setFeedback(data?.message || 'Slug unavailable', 'bad');
          }
        } catch {
          setFeedback('Could not verify slug right now', 'bad');
        }
      };

      const generate = () => {
        if (manualEdit) return;
        const slug = window.transliterateAndGenerateSlug(nameInput.value || '');
        slugInput.value = slug;
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => checkSlug(slug), 300);
      };

      nameInput.addEventListener('input', generate);
      slugInput.addEventListener('input', () => {
        manualEdit = true;
        const value = window.transliterateAndGenerateSlug(slugInput.value || '');
        if (slugInput.value !== value) slugInput.value = value;
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => checkSlug(value), 300);
      });

      if (slugInput.value) {
        checkSlug(window.transliterateAndGenerateSlug(slugInput.value));
      } else { generate(); }

      return { destroy() { if (timer) clearTimeout(timer); }, };
    };
  }

  window.adminContent = window.adminContent || {};

  if (typeof window.adminContent.fetchTags !== 'function') {
    window.adminContent.fetchTags = function (selectedIds = [], selector = '#tags') {
      return loadAdminModule('tagCombobox')
        .then((tagCombobox) =>
          tagCombobox.loadTagOptions(selector, normalizeNumericIds(selectedIds), TAG_COMBOBOX_DEFAULT_OPTIONS)
        )
        .catch((error) => {
          logModuleError('tagCombobox', error);
          window.showMessage?.('Failed to load tags', 'danger', 5000);
        });
    };
  }

  if (typeof window.adminContent.createNewTag !== 'function') {
    window.adminContent.createNewTag = function (data, selector = '#tags') {
      const name = typeof data === 'string' ? data : data?.text || data?.name || '';
      if (!String(name || '').trim()) return Promise.resolve(null);
      return loadAdminModule('tagCombobox')
        .then((tagCombobox) => tagCombobox.createTagAndSelect(selector, name, TAG_COMBOBOX_DEFAULT_OPTIONS))
        .catch((error) => {
          logModuleError('tagCombobox', error);
          window.showMessage?.(error?.message || 'Failed to create tag', 'danger', 5000);
          return null;
        });
    };
  }

  if (typeof window.adminContent.initializeTagsSelect !== 'function') {
    window.adminContent.initializeTagsSelect = function (selector = '#tags') {
      return loadAdminModule('tagCombobox')
        .then((tagCombobox) => tagCombobox.initTagCombobox(selector, TAG_COMBOBOX_DEFAULT_OPTIONS))
        .catch((error) => { logModuleError('tagCombobox', error); return null; });
    };
  }

  if (typeof window.adminContent.fetchCategories !== 'function') {
    window.adminContent.fetchCategories = function (selectedIds = [], selector = '#category_ids_select') {
      return loadAdminModule('categoryCombobox')
        .then((categoryCombobox) =>
          categoryCombobox.loadCategoryOptions(selector, normalizeNumericIds(selectedIds), CATEGORY_COMBOBOX_DEFAULT_OPTIONS)
        )
        .catch((error) => {
          logModuleError('categoryCombobox', error);
          window.showMessage?.('Failed to load categories', 'danger', 5000);
        });
    };
  }

  if (typeof window.adminContent.createNewCategory !== 'function') {
    window.adminContent.createNewCategory = function (data, selector = '#category_ids_select') {
      const name = typeof data === 'string' ? data : data?.text || data?.name || '';
      if (!String(name || '').trim()) return Promise.resolve(null);
      return loadAdminModule('categoryCombobox')
        .then((categoryCombobox) => categoryCombobox.createCategoryAndSelect(selector, name, CATEGORY_COMBOBOX_DEFAULT_OPTIONS))
        .catch((error) => {
          logModuleError('categoryCombobox', error);
          window.showMessage?.(error?.message || 'Failed to create category', 'danger', 5000);
          return null;
        });
    };
  }

  if (typeof window.adminContent.initializeCategoriesSelect !== 'function') {
    window.adminContent.initializeCategoriesSelect = function (selector = '#category_ids_select') {
      return loadAdminModule('categoryCombobox')
        .then((categoryCombobox) => categoryCombobox.initCategoryCombobox(selector, CATEGORY_COMBOBOX_DEFAULT_OPTIONS))
        .catch((error) => { logModuleError('categoryCombobox', error); return null; });
    };
  }

  if (typeof window.adminContent.initializeCategoryUI !== 'function') {
    window.adminContent.initializeCategoryUI = function (selectedIds = [], selector = '#category_ids_select') {
      if (window.adminContent?.fetchCategories) {
        window.adminContent.fetchCategories(selectedIds, selector);
      }
      if (window.adminContent?.initializeCategoriesSelect) {
        window.adminContent.initializeCategoriesSelect(selector);
      }
    };
  }
}
