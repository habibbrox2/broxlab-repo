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
import {
  ensureLegacyAdminGlobals,
  byId,
  getCsrfToken,
  getAdminDir,
  parseJson,
  escapeHtml
} from './modules/legacy-admin-globals.js';

document.addEventListener('DOMContentLoaded', () => {
  'use strict';


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

// Page loaded flag (called once)
runWhenReady(() => {
  if (!document.body.classList.contains('loaded')) {
    document.body.classList.add('loaded');
  }
});

// ==================== ADMIN INLINE SCRIPTS ====================
ensureLegacyAdminGlobals({ loadAdminModule, logModuleError, });

const moduleCache = new Map();
const moduleImporters = {
  core: () => import('./modules/core.js'),
  slug: () => import('./modules/slug.js'),
  autosave: () => import('./modules/autosave.js'),
  drafts: () => import('./modules/drafts.js'),
  mobile: () => import('./modules/mobile.js'),
  tagCombobox: () => import('./modules/tag-combobox.js'),
  categoryCombobox: () => import('./modules/category-combobox.js'),
  mediaUpload: () => import('./modules/media-upload.js'),
  notificationsAnalytics: () => import('./modules/notifications-analytics.js'),
  notificationsSend: () => import('./modules/notifications-send.js'),
  notificationsScheduled: () => import('./modules/notifications-scheduled.js'),
  notificationsDeviceSync: () => import('./modules/notifications-device-sync.js'),
  notificationsOfflineHandler: () => import('./modules/notifications-offline-handler.js'),
  notificationsList: () => import('./modules/notifications-list.js'),
  notificationsView: () => import('./modules/notifications-view.js'),
  notificationsDashboard: () => import('./modules/notifications-dashboard.js'),
  notificationsDrafts: () => import('./modules/notifications-drafts.js'),
  rbacUsers: () => import('./modules/rbac-users.js'),
  security2fa: () => import('./modules/security-2fa.js'),
  activityLog: () => import('./modules/activity-log.js'),
  services: () => import('./modules/services.js'),
  settings: () => import('./modules/settings.js'),
  emailTemplates: () => import('./modules/email-templates.js'),
  applications: () => import('./modules/applications.js'),
  oauth: () => import('./modules/oauth.js'),
  shared: () => import('./modules/shared.js'),
  subscribers: () => import('./modules/subscribers.js'),
  misc: () => import('./modules/misc.js'),
  serverStatus: () => import('./modules/server-status.js'),
  realtimeMonitoring: () => import('./modules/realtime-monitoring.js'),
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
  loadAdminModule('slug').then((slug) => slug.initUnifiedSlugFeatures()).catch((error) => logModuleError('slug', error));
}
function initContentPreviewSync() {
  loadAdminModule('core').then((core) => core.initContentPreviewSync('content', 'preview')).catch((error) => logModuleError('core', error));
}
function initAutosaveForContentForms() {
  loadAdminModule('autosave').then((autosave) => autosave.initAutosaveForContentForms()).catch((error) => logModuleError('autosave', error));
}
function initOfflineDraftForContentForms() {
  loadAdminModule('drafts').then((drafts) => drafts.initOfflineDraftForContentForms()).catch((error) => logModuleError('drafts', error));
}
function initFlashMessageAutoDismiss() {
  loadAdminModule('shared').then((mod) => mod.initFlashMessageAutoDismiss({ byId, })).catch((error) => logModuleError('shared', error));
}
function initOAuthPasswordModals() {
  loadAdminModule('oauth').then((mod) => mod.initOAuthPasswordModals({ byId, getCsrfToken, })).catch((error) => logModuleError('oauth', error));
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
      initFn({ theme: 'modern', accountsContainerId: 'oauth-accounts-container', providersContainerId: 'oauth-providers-container', alertsContainerId: 'alert-container', });
    })
    .catch((error) => console.error('Failed to initialize account settings helper:', error));
}

function initActivityLog() {
  loadAdminModule('activityLog').then((mod) => mod.initActivityLog({ byId, escapeHtml, getCsrfToken, })).catch((error) => logModuleError('activityLog', error));
}
function initDashboardData() {
  loadAdminModule('shared').then((mod) => mod.initDashboardData({ byId, parseJson, })).catch((error) => logModuleError('shared', error));
}
function initContentFormData() {
  loadAdminModule('shared').then((mod) => mod.initContentFormData({ byId, parseJson, })).catch((error) => logModuleError('shared', error));
}
function initEmailTemplatesEdit() {
  loadAdminModule('emailTemplates').then((mod) => mod.initEmailTemplatesEdit({ byId, getAdminDir, escapeHtml, })).catch((error) => logModuleError('emailTemplates', error));
}
function initEmailTemplatesList() {
  loadAdminModule('emailTemplates').then((mod) => mod.initEmailTemplatesList({ getAdminDir, })).catch((error) => logModuleError('emailTemplates', error));
}
function initMediaDetail() {
  loadAdminModule('shared').then((mod) => mod.initMediaDetail()).catch((error) => logModuleError('shared', error));
}
function initMediaUpload() {
  loadAdminModule('mediaUpload').then((mediaUpload) => mediaUpload.initMediaUpload({ byId, })).catch((error) => logModuleError('mediaUpload', error));
}
function initDeleteMobile() {
  loadAdminModule('mobile').then((mobile) => { mobile.initDeleteMobile({ byId, notify: (message, type) => window.showMessage?.(message, type), }); }).catch((error) => logModuleError('mobile', error));
}
function initMobileFormShared() {
  loadAdminModule('mobile').then((mobile) => { mobile.initMobileFormShared({ byId, parseJson, notify: (message, type) => window.showMessage?.(message, type), }); }).catch((error) => logModuleError('mobile', error));
}
function initApplicationsView() {
  loadAdminModule('applications').then((mod) => mod.initApplicationsView({ byId, getCsrfToken, })).catch((error) => logModuleError('applications', error));
}
function initSettingsPage() {
  loadAdminModule('settings').then((mod) => mod.initSettingsPage({ byId, getCsrfToken, getAdminDir, })).catch((error) => logModuleError('settings', error));
}
function initAppSecuritySettings() {
  loadAdminModule('settings').then((mod) => mod.initAppSecuritySettings({ byId, getCsrfToken, })).catch((error) => logModuleError('settings', error));
}
function initRbacPermissionsList() {
  loadAdminModule('rbacUsers').then((mod) => mod.initRbacPermissionsList()).catch((error) => logModuleError('rbacUsers', error));
}
function initRbacRolesEdit() {
  loadAdminModule('rbacUsers').then((rbacUsers) => rbacUsers.initRbacRolesEdit()).catch((error) => logModuleError('rbacUsers', error));
}
function initRbacUserRoles() {
  loadAdminModule('rbacUsers').then((rbacUsers) => rbacUsers.initRbacUserRoles({ byId, })).catch((error) => logModuleError('rbacUsers', error));
}
function initSecurity2FASetup() {
  loadAdminModule('security2fa').then((security2fa) => security2fa.initSecurity2FASetup({ byId, })).catch((error) => logModuleError('security2fa', error));
}
function initSecurity2FABackup() {
  loadAdminModule('security2fa').then((security2fa) => security2fa.initSecurity2FABackup({ byId, getCsrfToken, })).catch((error) => logModuleError('security2fa', error));
}
function initSecurity2FA() {
  loadAdminModule('security2fa').then((security2fa) => security2fa.initSecurity2FA({ byId, getCsrfToken, })).catch((error) => logModuleError('security2fa', error));
}
function initUsersAddUser() {
  loadAdminModule('rbacUsers').then((rbacUsers) => rbacUsers.initUsersAddUser()).catch((error) => logModuleError('rbacUsers', error));
}
function initUsersEditUser() {
  loadAdminModule('rbacUsers').then((rbacUsers) => rbacUsers.initUsersEditUser({ byId, })).catch((error) => logModuleError('rbacUsers', error));
}
function initServicesForms() {
  loadAdminModule('services').then((mod) => mod.initServicesForms({ byId, parseJson, })).catch((error) => logModuleError('services', error));
}
function initServicesIndex() {
  loadAdminModule('services').then((mod) => mod.initServicesIndex({ byId, getCsrfToken, })).catch((error) => logModuleError('services', error));
}
function initServicesApplications() {
  loadAdminModule('services').then((mod) => mod.initServicesApplications({ byId, escapeHtml, })).catch((error) => logModuleError('services', error));
}
function initServerStatusIndicator() {
  loadAdminModule('serverStatus').then((mod) => mod.initServerStatusIndicator()).catch((error) => logModuleError('serverStatus', error));
}
function initRealtimeMonitoring() {
  loadAdminModule('realtimeMonitoring').then((mod) => mod.initRealtimeMonitoring()).catch((error) => logModuleError('realtimeMonitoring', error));
}

// ==================== NOTIFICATION SCRIPTS ====================
function initNotificationsList() {
  loadAdminModule('notificationsList').then((mod) => mod.initNotificationsList()).catch((error) => logModuleError('notificationsSend', error));
}
function initNotificationsView() {
  loadAdminModule('notificationsView').then((mod) => mod.initNotificationsView()).catch((error) => logModuleError('notificationsSend', error));
}
function initNotificationsDashboard() {
  loadAdminModule('notificationsDashboard').then((mod) => mod.initNotificationsDashboard()).catch((error) => logModuleError('notificationsSend', error));
}
function initNotificationsDrafts() {
  loadAdminModule('notificationsDrafts').then((mod) => mod.initNotificationsDrafts()).catch((error) => logModuleError('notificationsSend', error));
}
function initNotificationsSend() {
  loadAdminModule('notificationsSend').then((mod) => mod.initNotificationsSend()).catch((error) => logModuleError('notificationsSend', error));
}
function initNotificationsScheduled() {
  loadAdminModule('notificationsScheduled').then((mod) => mod.initNotificationsScheduled()).catch((error) => logModuleError('notificationsSend', error));
}
function initNotificationsDashboardRealtime() {
  loadAdminModule('misc').then((mod) => mod.initNotificationsDashboardRealtime()).catch((error) => logModuleError('misc', error));
}
function initNotificationsDeviceSync() {
  loadAdminModule('notificationsDeviceSync').then((mod) => mod.initNotificationsDeviceSync()).catch((error) => logModuleError('notificationsSend', error));
}
function initNotificationsOfflineHandler() {
  loadAdminModule('notificationsOfflineHandler').then((mod) => mod.initNotificationsOfflineHandler()).catch((error) => logModuleError('notificationsSend', error));
}
function initNotificationsSubscribers() {
  loadAdminModule('subscribers').then((mod) => mod.initNotificationsSubscribers({ byId, getCsrfToken, escapeHtml, })).catch((error) => logModuleError('subscribers', error));
}
function initNotificationsPauseResume() {
  loadAdminModule('subscribers').then((mod) => mod.initNotificationsPauseResume({ byId, getCsrfToken, })).catch((error) => logModuleError('subscribers', error));
}
function initNotificationsRateLimit() {
  loadAdminModule('subscribers').then((mod) => mod.initNotificationsRateLimit({ byId, getCsrfToken, })).catch((error) => logModuleError('subscribers', error));
}
function initNotificationsTopicsManagement() {
  loadAdminModule('misc').then((mod) => mod.initNotificationsTopicsManagement()).catch((error) => logModuleError('misc', error));
}
function initNotificationsSendByTopic() {
  loadAdminModule('misc').then((mod) => mod.initNotificationsSendByTopic()).catch((error) => logModuleError('misc', error));
}
function initNotificationsKillSwitch() {
  loadAdminModule('subscribers').then((mod) => mod.initNotificationsKillSwitch({ byId, })).catch((error) => logModuleError('subscribers', error));
}
function initNotificationsSubscribersLegacy() {
  loadAdminModule('misc').then((mod) => mod.initNotificationsSubscribersLegacy()).catch((error) => logModuleError('misc', error));
}
function initNotificationsDashboardLegacy() {
  loadAdminModule('misc').then((mod) => mod.initNotificationsDashboardLegacy()).catch((error) => logModuleError('misc', error));
}
function initNotificationsAnalytics() {
  loadAdminModule('notificationsAnalytics').then((notificationsAnalytics) => {
    const runInit = () => notificationsAnalytics.initNotificationsAnalytics({ byId, });
    runInit();
    if (typeof window.Chart === 'undefined') {
      window.addEventListener('load', runInit, { once: true, });
    }
  }).catch((error) => logModuleError('notificationsAnalytics', error));
}

const ready = (fn) => {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fn, { once: true, });
  } else { fn(); }
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

// Single __adminInlineHelpers declaration (no duplicate)
window.__adminInlineHelpers = {
  byId, getCsrfToken, getAdminDir, escapeHtml, parseJson,
  loadAdminModule, logModuleError,
  initUnifiedSlugFeatures, initContentPreviewSync,
  initAutosaveForContentForms, initOfflineDraftForContentForms,
  initFlashMessageAutoDismiss, initOAuthPasswordModals,
  initAccountSettings, initActivityLog,
  initDashboardData, initContentFormData,
  initEmailTemplatesEdit, initEmailTemplatesList,
  initMediaDetail, initMediaUpload,
  initDeleteMobile, initMobileFormShared,
  initApplicationsView, initSettingsPage, initAppSecuritySettings,
  initRbacPermissionsList, initRbacRolesEdit, initRbacUserRoles,
  initSecurity2FASetup, initSecurity2FABackup, initSecurity2FA,
  initUsersAddUser, initUsersEditUser,
  initServicesForms, initServicesIndex, initServicesApplications,
  initServerStatusIndicator, initRealtimeMonitoring,
};
