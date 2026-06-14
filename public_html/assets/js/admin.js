/**
 * Admin Panel JavaScript
 * Handles sidebar toggling and responsive behaviors
 */

import {
  initAdminNotificationRuntime,
  initAdminUserDropdownSync,
  initAdminDebugUtils,
  initAdminUnifiedLogout
} from './modules/notifications.js';
import { initPasswordModals } from './modules/security.js';
import { initPresetAutoSelector } from './modules/preset-selector.js';
import { runWhenReady } from './modules/utils.js';
import { initDatepickerLoader } from './datepicker-loader.js';

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  // Initialize datepicker lazy loader
  initDatepickerLoader();

  // Initialize modules
  initAdminNotificationRuntime();
  runWhenReady(initAdminUserDropdownSync);
  initAdminDebugUtils();
  initAdminUnifiedLogout();
  initPresetAutoSelector();
  // Initialize password modals and get functions for global access
  runWhenReady(() => {
    const passwordFunctions = initPasswordModals();
    if (passwordFunctions) {
      window.validatePasswordStrength = passwordFunctions.validatePasswordStrength;
      window.setPassword = passwordFunctions.setPassword;
      window.changePassword = passwordFunctions.changePassword;
    }
  });

  // The rest of the original code...

  const applyStackedTables = () => {
    const tables = document.querySelectorAll('table.table-stacked');
    if (!tables.length) return;

    tables.forEach((table) => {
      const headers = Array.from(table.querySelectorAll('thead th')).map((th) =>
        th.textContent.trim()
      );
      if (!headers.length) return;

      const rows = table.querySelectorAll('tbody tr');
      rows.forEach((row) => {
        const cells = Array.from(row.children).filter((cell) => cell.tagName === 'TD');
        if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
          return;
        }
        cells.forEach((cell, index) => {
          if (cell.hasAttribute('data-label')) return;
          let label = headers[index] || '';
          if (!label) {
            const hasCheckbox = cell.querySelector('input[type="checkbox"]');
            if (hasCheckbox) label = 'Select';
          }
          if (label) {
            cell.setAttribute('data-label', label);
          }
        });
      });
    });
  };

  applyStackedTables();
});

// Page loaded flag
runWhenReady(() => {
  document.body.classList.add('loaded');
});

// ==================== ADMIN INLINE SCRIPTS (MIGRATED) ====================
(function () {
  'use strict';


  const byId = (id) => document.getElementById(id);
  const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  const getAdminDir = () => document.body?.dataset?.adminDir || '/admin';
  const TAG_COMBOBOX_DEFAULT_OPTIONS = {
    allowCreate: true,
    searchMode: 'client',
    sourceEndpoint: '/api/tags/list-json',
    createEndpoint: '/api/tags/create',
    maxResults: 50,
  };
  const CATEGORY_COMBOBOX_DEFAULT_OPTIONS = {
    allowCreate: true,
    searchMode: 'client',
    sourceEndpoint: '/api/categories/list-json',
    createEndpoint: '/api/categories/create',
    maxResults: 50,
  };
  const parseJson = (value, fallback) => {
    if (!value) return fallback;
    try {
      return JSON.parse(value);
    } catch {
      return fallback;
    }
  };
  const escapeHtml = (text) => {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;', };
    return String(text ?? '').replace(/[&<>"']/g, (char) => map[char]);
  };

  function ensureLegacyAdminGlobals() {
    if (typeof window.showMessage !== 'function') {
      window.showMessage = function (message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        const normalized = String(type || 'info').toLowerCase();
        const map = {
          success: 'success',
          danger: 'danger',
          error: 'danger',
          warning: 'warning',
          info: 'info',
        };
        const cls = map[normalized] || 'info';
        toast.className = `fixed top-4 right-4 z-50 p-4 rounded-xl shadow-lg border transition-all duration-300 ${cls === 'danger' ? 'bg-red-50 border-red-200 text-red-700' : cls === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-sky-50 border-sky-200 text-sky-700'}`;
        toast.style.zIndex = '9999';
        toast.innerHTML = `
                    ${String(message || '')}
                    <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" data-brox-dismiss="alert"></button>
                `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), Number(duration) || 5000);
      };
    }

    if (typeof window.transliterateAndGenerateSlug !== 'function') {
      const bnDigitMap = {
        '\u09E6': '0',
        '\u09E7': '1',
        '\u09E8': '2',
        '\u09E9': '3',
        '\u09EA': '4',
        '\u09EB': '5',
        '\u09EC': '6',
        '\u09ED': '7',
        '\u09EE': '8',
        '\u09EF': '9',
      };
      const bnBasicMap = {
        '\u0985': 'o',
        '\u0986': 'a',
        '\u0987': 'i',
        '\u0988': 'i',
        '\u0989': 'u',
        '\u098A': 'u',
        '\u098F': 'e',
        '\u0990': 'oi',
        '\u0993': 'o',
        '\u0994': 'ou',
        '\u0995': 'k',
        '\u0996': 'kh',
        '\u0997': 'g',
        '\u0998': 'gh',
        '\u0999': 'ng',
        '\u099A': 'ch',
        '\u099B': 'chh',
        '\u099C': 'j',
        '\u099D': 'jh',
        '\u099E': 'n',
        '\u099F': 't',
        '\u09A0': 'th',
        '\u09A1': 'd',
        '\u09A2': 'dh',
        '\u09A3': 'n',
        '\u09A4': 't',
        '\u09A5': 'th',
        '\u09A6': 'd',
        '\u09A7': 'dh',
        '\u09A8': 'n',
        '\u09AA': 'p',
        '\u09AB': 'ph',
        '\u09AC': 'b',
        '\u09AD': 'bh',
        '\u09AE': 'm',
        '\u09AF': 'y',
        '\u09B0': 'r',
        '\u09B2': 'l',
        '\u09B6': 'sh',
        '\u09B7': 'sh',
        '\u09B8': 's',
        '\u09B9': 'h',
        '\u09BE': 'a',
        '\u09BF': 'i',
        '\u09C0': 'i',
        '\u09C1': 'u',
        '\u09C2': 'u',
        '\u09C7': 'e',
        '\u09C8': 'oi',
        '\u09CB': 'o',
        '\u09CC': 'ou',
        '\u0982': 'ng',
        '\u0983': 'h',
        '\u0981': 'n',
      };
      const transliterateBn = (text) =>
        String(text || '')
          .split('')
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
          if (!slug) {
            setFeedback('');
            return;
          }
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
        } else {
          generate();
        }

        return {
          destroy() {
            if (timer) clearTimeout(timer);
          },
        };
      };
    }

    window.adminContent = window.adminContent || {};

    const normalizeNumericIds = (value) => {
      if (!Array.isArray(value)) return [];
      return value
        .map((item) => {
          if (typeof item === 'number' || typeof item === 'string') return String(item).trim();
          if (item && typeof item === 'object' && item.id !== undefined)
            return String(item.id).trim();
          return '';
        })
        .filter((id) => /^\d+$/.test(id));
    };

    if (typeof window.adminContent.fetchTags !== 'function') {
      window.adminContent.fetchTags = function (selectedIds = [], selector = '#tags') {
        return loadAdminModule('tagCombobox')
          .then((tagCombobox) =>
            tagCombobox.loadTagOptions(
              selector,
              normalizeNumericIds(selectedIds),
              TAG_COMBOBOX_DEFAULT_OPTIONS
            )
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
          .then((tagCombobox) =>
            tagCombobox.createTagAndSelect(selector, name, TAG_COMBOBOX_DEFAULT_OPTIONS)
          )
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
          .then((tagCombobox) =>
            tagCombobox.initTagCombobox(selector, TAG_COMBOBOX_DEFAULT_OPTIONS)
          )
          .catch((error) => {
            logModuleError('tagCombobox', error);
            return null;
          });
      };
    }

    if (typeof window.adminContent.fetchCategories !== 'function') {
      window.adminContent.fetchCategories = function (
        selectedIds = [],
        selector = '#category_ids_select'
      ) {
        return loadAdminModule('categoryCombobox')
          .then((categoryCombobox) =>
            categoryCombobox.loadCategoryOptions(
              selector,
              normalizeNumericIds(selectedIds),
              CATEGORY_COMBOBOX_DEFAULT_OPTIONS
            )
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
          .then((categoryCombobox) =>
            categoryCombobox.createCategoryAndSelect(
              selector,
              name,
              CATEGORY_COMBOBOX_DEFAULT_OPTIONS
            )
          )
          .catch((error) => {
            logModuleError('categoryCombobox', error);
            window.showMessage?.(error?.message || 'Failed to create category', 'danger', 5000);
            return null;
          });
      };
    }

    if (typeof window.adminContent.initializeCategoriesSelect !== 'function') {
      window.adminContent.initializeCategoriesSelect = function (
        selector = '#category_ids_select'
      ) {
        return loadAdminModule('categoryCombobox')
          .then((categoryCombobox) =>
            categoryCombobox.initCategoryCombobox(selector, CATEGORY_COMBOBOX_DEFAULT_OPTIONS)
          )
          .catch((error) => {
            logModuleError('categoryCombobox', error);
            return null;
          });
      };
    }

    if (typeof window.adminContent.initializeCategoryUI !== 'function') {
      window.adminContent.initializeCategoryUI = function (
        selectedIds = [],
        selector = '#category_ids_select'
      ) {
        if (window.adminContent?.fetchCategories) {
          window.adminContent.fetchCategories(selectedIds, selector);
        }
        if (window.adminContent?.initializeCategoriesSelect) {
          window.adminContent.initializeCategoriesSelect(selector);
        }
      };
    }
  }

  ensureLegacyAdminGlobals();

  const moduleCache = new Map();
  const moduleImporters = {
    core: () => import('./admin/modules/core.js'),
    slug: () => import('./admin/modules/slug.js'),
    autosave: () => import('./admin/modules/autosave.js'),
    drafts: () => import('./admin/modules/drafts.js'),
    mobile: () => import('./admin/modules/mobile.js'),
    tagCombobox: () => import('./admin/modules/tag-combobox.js'),
    categoryCombobox: () => import('./admin/modules/category-combobox.js'),
    mediaUpload: () => import('./admin/modules/media-upload.js'),
    notificationsAnalytics: () => import('./admin/modules/notifications-analytics.js'),
    notificationsWorkflows: () => import('./admin/modules/notifications-workflows.js'),
    rbacUsers: () => import('./admin/modules/rbac-users.js'),
    security2fa: () => import('./admin/modules/security-2fa.js'),
    activityLog: () => import('./admin/modules/activity-log.js'),
    services: () => import('./admin/modules/services.js'),
    settings: () => import('./admin/modules/settings.js'),
    emailTemplates: () => import('./admin/modules/email-templates.js'),
    applications: () => import('./admin/modules/applications.js'),
    oauth: () => import('./admin/modules/oauth.js'),
    shared: () => import('./admin/modules/shared.js'),
    subscribers: () => import('./admin/modules/subscribers.js'),
    misc: () => import('./admin/modules/misc.js'),
    serverStatus: () => import('./admin/modules/server-status.js'),
    realtimeMonitoring: () => import('./admin/modules/realtime-monitoring.js'),
  };

  function logModuleError(moduleName, error) {
    if (window.ADMIN_DEBUG === true) {
      console.error(`[Admin] Failed to load module "${moduleName}"`, error);
    }
  }

  function loadAdminModule(moduleName) {
    if (moduleCache.has(moduleName)) {
      return moduleCache.get(moduleName);
    }

    const importer = moduleImporters[moduleName];
    if (typeof importer !== 'function') {
      return Promise.reject(new Error(`Unknown admin module: ${moduleName}`));
    }

    const loading = importer().catch((error) => {
      moduleCache.delete(moduleName);
      throw error;
    });

    moduleCache.set(moduleName, loading);
    return loading;
  }

  function initUnifiedSlugFeatures() {
    loadAdminModule('slug')
      .then((slug) => slug.initUnifiedSlugFeatures())
      .catch((error) => logModuleError('slug', error));
  }

  function initContentPreviewSync() {
    loadAdminModule('core')
      .then((core) => core.initContentPreviewSync('content', 'preview'))
      .catch((error) => logModuleError('core', error));
  }

  function initAutosaveForContentForms() {
    loadAdminModule('autosave')
      .then((autosave) => autosave.initAutosaveForContentForms())
      .catch((error) => logModuleError('autosave', error));
  }

  function initOfflineDraftForContentForms() {
    loadAdminModule('drafts')
      .then((drafts) => drafts.initOfflineDraftForContentForms())
      .catch((error) => logModuleError('drafts', error));
  }

  function initFlashMessageAutoDismiss() {
    loadAdminModule('shared')
      .then((mod) => mod.initFlashMessageAutoDismiss({ byId, }))
      .catch((error) => logModuleError('shared', error));
  }

  function initOAuthPasswordModals() {
    loadAdminModule('oauth')
      .then((mod) => mod.initOAuthPasswordModals({ byId, getCsrfToken, }))
      .catch((error) => logModuleError('oauth', error));
  }

  function initAccountSettings() {
    const container = byId('oauth-accounts-container');
    const setPasswordForm = byId('setPasswordForm');
    const changePasswordForm = byId('changePasswordForm');
    if (!container && !setPasswordForm && !changePasswordForm) return;

    import('./account-settings-shared.js')
      .then((mod) => {
        const initFn = mod?.initAccountSettingsOAuth || mod?.default?.initAccountSettingsOAuth;
        if (typeof initFn !== 'function') return;
        initFn({
          theme: 'modern',
          accountsContainerId: 'oauth-accounts-container',
          providersContainerId: 'oauth-providers-container',
          alertsContainerId: 'alert-container',
        });
      })
      .catch((error) => {
        console.error('Failed to initialize account settings helper:', error);
      });
  }

  function initActivityLog() {
    loadAdminModule('activityLog')
      .then((mod) => mod.initActivityLog({ byId, escapeHtml, getCsrfToken, }))
      .catch((error) => logModuleError('activityLog', error));
  }

  function initDashboardData() {
    loadAdminModule('shared')
      .then((mod) => mod.initDashboardData({ byId, parseJson, }))
      .catch((error) => logModuleError('shared', error));
  }

  function initContentFormData() {
    loadAdminModule('shared')
      .then((mod) => mod.initContentFormData({ byId, parseJson, }))
      .catch((error) => logModuleError('shared', error));
  }

  function initEmailTemplatesEdit() {
    loadAdminModule('emailTemplates')
      .then((mod) => mod.initEmailTemplatesEdit({ byId, getAdminDir, escapeHtml, }))
      .catch((error) => logModuleError('emailTemplates', error));
  }

  function initEmailTemplatesList() {
    loadAdminModule('emailTemplates')
      .then((mod) => mod.initEmailTemplatesList({ getAdminDir, }))
      .catch((error) => logModuleError('emailTemplates', error));
  }

  function initMediaDetail() {
    loadAdminModule('shared')
      .then((mod) => mod.initMediaDetail())
      .catch((error) => logModuleError('shared', error));
  }

  function initMediaUpload() {
    loadAdminModule('mediaUpload')
      .then((mediaUpload) => mediaUpload.initMediaUpload({ byId, }))
      .catch((error) => logModuleError('mediaUpload', error));
  }

  function initDeleteMobile() {
    loadAdminModule('mobile')
      .then((mobile) => {
        mobile.initDeleteMobile({
          byId,
          notify: (message, type) => window.showMessage?.(message, type),
        });
      })
      .catch((error) => logModuleError('mobile', error));
  }

  function initMobileFormShared() {
    loadAdminModule('mobile')
      .then((mobile) => {
        mobile.initMobileFormShared({
          byId,
          parseJson,
          notify: (message, type) => window.showMessage?.(message, type),
        });
      })
      .catch((error) => logModuleError('mobile', error));
  }

  function initApplicationsView() {
    loadAdminModule('applications')
      .then((mod) => mod.initApplicationsView({ byId, getCsrfToken, }))
      .catch((error) => logModuleError('applications', error));
  }

  function initSettingsPage() {
    loadAdminModule('settings')
      .then((mod) => mod.initSettingsPage({ byId, getCsrfToken, getAdminDir, }))
      .catch((error) => logModuleError('settings', error));
  }

  function initAppSecuritySettings() {
    loadAdminModule('settings')
      .then((mod) => mod.initAppSecuritySettings({ byId, getCsrfToken, }))
      .catch((error) => logModuleError('settings', error));
  }

  function initRbacPermissionsList() {
    loadAdminModule('rbacUsers')
      .then((mod) => mod.initRbacPermissionsList())
      .catch((error) => logModuleError('rbacUsers', error));
  }

  function initRbacRolesEdit() {
    loadAdminModule('rbacUsers')
      .then((rbacUsers) => rbacUsers.initRbacRolesEdit())
      .catch((error) => logModuleError('rbacUsers', error));
  }

  function initRbacUserRoles() {
    loadAdminModule('rbacUsers')
      .then((rbacUsers) => rbacUsers.initRbacUserRoles({ byId, }))
      .catch((error) => logModuleError('rbacUsers', error));
  }

  function initSecurity2FASetup() {
    loadAdminModule('security2fa')
      .then((security2fa) => security2fa.initSecurity2FASetup({ byId, }))
      .catch((error) => logModuleError('security2fa', error));
  }

  function initSecurity2FABackup() {
    loadAdminModule('security2fa')
      .then((security2fa) => security2fa.initSecurity2FABackup({ byId, getCsrfToken, }))
      .catch((error) => logModuleError('security2fa', error));
  }

  function initSecurity2FA() {
    loadAdminModule('security2fa')
      .then((security2fa) => security2fa.initSecurity2FA({ byId, getCsrfToken, }))
      .catch((error) => logModuleError('security2fa', error));
  }

  function initUsersAddUser() {
    loadAdminModule('rbacUsers')
      .then((rbacUsers) => rbacUsers.initUsersAddUser())
      .catch((error) => logModuleError('rbacUsers', error));
  }

  function initUsersEditUser() {
    loadAdminModule('rbacUsers')
      .then((rbacUsers) => rbacUsers.initUsersEditUser({ byId, }))
      .catch((error) => logModuleError('rbacUsers', error));
  }

  function initServicesForms() {
    loadAdminModule('services')
      .then((mod) => mod.initServicesForms({ byId, parseJson, }))
      .catch((error) => logModuleError('services', error));
  }

  function initServicesIndex() {
    loadAdminModule('services')
      .then((mod) => mod.initServicesIndex({ byId, getCsrfToken, }))
      .catch((error) => logModuleError('services', error));
  }

  function initServicesApplications() {
    loadAdminModule('services')
      .then((mod) => mod.initServicesApplications({ byId, escapeHtml, }))
      .catch((error) => logModuleError('services', error));
  }

  function initServerStatusIndicator() {
    loadAdminModule('serverStatus')
      .then((mod) => mod.initServerStatusIndicator())
      .catch((error) => logModuleError('serverStatus', error));
  }

  function initRealtimeMonitoring() {
    loadAdminModule('realtimeMonitoring')
      .then((mod) => mod.initRealtimeMonitoring())
      .catch((error) => logModuleError('realtimeMonitoring', error));
  }

  window.__adminInlineHelpers = {
    byId,
    getCsrfToken,
    getAdminDir,
    escapeHtml,
    parseJson,
    loadAdminModule,
    logModuleError,
    initUnifiedSlugFeatures,
    initContentPreviewSync,
    initAutosaveForContentForms,
    initOfflineDraftForContentForms,
    initFlashMessageAutoDismiss,
    initOAuthPasswordModals,
    initAccountSettings,
    initActivityLog,
    initDashboardData,
    initContentFormData,
    initEmailTemplatesEdit,
    initEmailTemplatesList,
    initMediaDetail,
    initMediaUpload,
    initDeleteMobile,
    initMobileFormShared,
    initApplicationsView,
    initSettingsPage,
    initAppSecuritySettings,
    initRbacPermissionsList,
    initRbacRolesEdit,
    initRbacUserRoles,
    initSecurity2FASetup,
    initSecurity2FABackup,
    initSecurity2FA,
    initUsersAddUser,
    initUsersEditUser,
    initServicesForms,
    initServicesIndex,
    initServicesApplications,
    initServerStatusIndicator,
    initRealtimeMonitoring,
  };
})();


// Page loaded flag
runWhenReady(() => {
  document.body.classList.add('loaded');
});

// ==================== ADMIN INLINE SCRIPTS (MIGRATED) ====================
(function () {
  'use strict';

  const {
    byId,
    getCsrfToken,
    getAdminDir,
    escapeHtml,
    parseJson,
    loadAdminModule,
    logModuleError,
    initFlashMessageAutoDismiss,
    initOAuthPasswordModals,
    initAccountSettings,
    initActivityLog,
    initDashboardData,
    initContentFormData,
    initUnifiedSlugFeatures,
    initContentPreviewSync,
    initAutosaveForContentForms,
    initOfflineDraftForContentForms,
    initEmailTemplatesEdit,
    initEmailTemplatesList,
    initMediaDetail,
    initMediaUpload,
    initDeleteMobile,
    initMobileFormShared,
    initApplicationsView,
    initSettingsPage,
    initAppSecuritySettings,
    initRbacPermissionsList,
    initRbacRolesEdit,
    initRbacUserRoles,
    initSecurity2FASetup,
    initSecurity2FABackup,
    initSecurity2FA,
    initUsersAddUser,
    initUsersEditUser,
    initServicesForms,
    initServicesIndex,
    initServicesApplications,
    initServerStatusIndicator,
    initRealtimeMonitoring,
  } = window.__adminInlineHelpers || {};

  // ==================== ADMIN INLINE SCRIPTS ====================

  function initNotificationsList() {
    loadAdminModule('notificationsWorkflows')
      .then((mod) => mod.initNotificationsList())
      .catch((error) => logModuleError('notificationsWorkflows', error));
  }

  function initNotificationsView() {
    loadAdminModule('notificationsWorkflows')
      .then((mod) => mod.initNotificationsView())
      .catch((error) => logModuleError('notificationsWorkflows', error));
  }

  function initNotificationsDashboard() {
    loadAdminModule('notificationsWorkflows')
      .then((mod) => mod.initNotificationsDashboard())
      .catch((error) => logModuleError('notificationsWorkflows', error));
  }

  function initNotificationsDrafts() {
    loadAdminModule('notificationsWorkflows')
      .then((mod) => mod.initNotificationsDrafts())
      .catch((error) => logModuleError('notificationsWorkflows', error));
  }

  function initNotificationsSend() {
    loadAdminModule('notificationsWorkflows')
      .then((notificationsWorkflows) => notificationsWorkflows.initNotificationsSend())
      .catch((error) => logModuleError('notificationsWorkflows', error));
  }
  function initNotificationsScheduled() {
    loadAdminModule('notificationsWorkflows')
      .then((notificationsWorkflows) => notificationsWorkflows.initNotificationsScheduled())
      .catch((error) => logModuleError('notificationsWorkflows', error));
  }
  function initNotificationsDashboardRealtime() {
    loadAdminModule('misc')
      .then((mod) => mod.initNotificationsDashboardRealtime())
      .catch((error) => logModuleError('misc', error));
  }

  function initNotificationsDeviceSync() {
    loadAdminModule('notificationsWorkflows')
      .then((notificationsWorkflows) => notificationsWorkflows.initNotificationsDeviceSync())
      .catch((error) => logModuleError('notificationsWorkflows', error));
  }
  function initNotificationsOfflineHandler() {
    loadAdminModule('notificationsWorkflows')
      .then((notificationsWorkflows) => notificationsWorkflows.initNotificationsOfflineHandler())
      .catch((error) => logModuleError('notificationsWorkflows', error));
  }
  function initNotificationsSubscribers() {
    loadAdminModule('subscribers')
      .then((mod) => mod.initNotificationsSubscribers({ byId, getCsrfToken, escapeHtml, }))
      .catch((error) => logModuleError('subscribers', error));
  }

  function initNotificationsPauseResume() {
    loadAdminModule('subscribers')
      .then((mod) => mod.initNotificationsPauseResume({ byId, getCsrfToken, }))
      .catch((error) => logModuleError('subscribers', error));
  }

  function initNotificationsRateLimit() {
    loadAdminModule('subscribers')
      .then((mod) => mod.initNotificationsRateLimit({ byId, getCsrfToken, }))
      .catch((error) => logModuleError('subscribers', error));
  }

  function initNotificationsTopicsManagement() {
    loadAdminModule('misc')
      .then((mod) => mod.initNotificationsTopicsManagement())
      .catch((error) => logModuleError('misc', error));
  }

  function initNotificationsSendByTopic() {
    loadAdminModule('misc')
      .then((mod) => mod.initNotificationsSendByTopic())
      .catch((error) => logModuleError('misc', error));
  }

  function initNotificationsKillSwitch() {
    loadAdminModule('subscribers')
      .then((mod) => mod.initNotificationsKillSwitch({ byId, }))
      .catch((error) => logModuleError('subscribers', error));
  }

  function initNotificationsSubscribersLegacy() {
    loadAdminModule('misc')
      .then((mod) => mod.initNotificationsSubscribersLegacy())
      .catch((error) => logModuleError('misc', error));
  }

  function initNotificationsDashboardLegacy() {
    loadAdminModule('misc')
      .then((mod) => mod.initNotificationsDashboardLegacy())
      .catch((error) => logModuleError('misc', error));
  }

  function initNotificationsAnalytics() {
    loadAdminModule('notificationsAnalytics')
      .then((notificationsAnalytics) => {
        const runInit = () => notificationsAnalytics.initNotificationsAnalytics({ byId, });
        runInit();
        if (typeof window.Chart === 'undefined') {
          window.addEventListener('load', runInit, { once: true, });
        }
      })
      .catch((error) => logModuleError('notificationsAnalytics', error));
  }

  // Additional migrated handlers are appended below in smaller patches.

  const ready = (fn) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true, });
    } else {
      fn();
    }
  };

  ready(() => {
    initFlashMessageAutoDismiss();
    initOAuthPasswordModals();
    initAccountSettings();
    initActivityLog();
    initDashboardData();
    initContentFormData();
    initUnifiedSlugFeatures();
    initContentPreviewSync();
    initAutosaveForContentForms();
    initOfflineDraftForContentForms();
    initEmailTemplatesEdit();
    initEmailTemplatesList();
    initMediaDetail();
    initMediaUpload();
    initDeleteMobile();
    initMobileFormShared();
    initApplicationsView();
    initSettingsPage();
    initAppSecuritySettings();
    initRbacPermissionsList();
    initRbacRolesEdit();
    initRbacUserRoles();
    initSecurity2FASetup();
    initSecurity2FABackup();
    initSecurity2FA();
    initUsersAddUser();
    initUsersEditUser();
    initServicesForms();
    initServicesIndex();
    initServicesApplications();
    initNotificationsList();
    initNotificationsView();
    initNotificationsDashboard();
    initNotificationsDrafts();
    initNotificationsSend();
    initNotificationsScheduled();
    initNotificationsDashboardRealtime();
    initNotificationsDeviceSync();
    initNotificationsOfflineHandler();
    initNotificationsSubscribers();
    initNotificationsPauseResume();
    initNotificationsRateLimit();
    initNotificationsTopicsManagement();
    initNotificationsSendByTopic();
    initNotificationsKillSwitch();
    initNotificationsSubscribersLegacy();
    initNotificationsDashboardLegacy();
    initNotificationsAnalytics();
    initServerStatusIndicator();
    initRealtimeMonitoring();
  });


  window.__adminInlineHelpers = {
    byId,
    getCsrfToken,
    getAdminDir,
    escapeHtml,
    parseJson,
    loadAdminModule,
    logModuleError,
    initUnifiedSlugFeatures,
    initContentPreviewSync,
    initAutosaveForContentForms,
    initOfflineDraftForContentForms,
    initFlashMessageAutoDismiss,
    initOAuthPasswordModals,
    initAccountSettings,
    initActivityLog,
    initDashboardData,
    initContentFormData,
    initEmailTemplatesEdit,
    initEmailTemplatesList,
    initMediaDetail,
    initMediaUpload,
    initDeleteMobile,
    initMobileFormShared,
    initApplicationsView,
    initSettingsPage,
    initAppSecuritySettings,
    initRbacPermissionsList,
    initRbacRolesEdit,
    initRbacUserRoles,
    initSecurity2FASetup,
    initSecurity2FABackup,
    initSecurity2FA,
    initUsersAddUser,
    initUsersEditUser,
    initServicesForms,
    initServicesIndex,
    initServicesApplications,
    initServerStatusIndicator,
    initRealtimeMonitoring,
  };
})();
