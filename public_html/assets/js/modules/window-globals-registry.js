/**
 * Window Globals Registry
 *
 * LIVING DOCUMENTATION — Update this file when adding or removing window.* assignments.
 * This file serves as the authoritative reference for every property assigned to `window`
 * across the JS codebase. It documents why each global exists and whether it's truly needed.
 *
 * Categories:
 *   🔵 NECESSARY — Referenced from inline Twig <script> or onclick in generated HTML
 *   🟢 EXPORTED  — Module also exports this; window assignment is legacy compat
 *   🟡 BRIDGE    — Set by Twig inline script (backend → frontend data bridge)
 *   🟠 INTERNAL  — Only used within its own module; candidate for removal from window
 *   🔴 REMOVED   — Cleaned up in recent refactors; listed here for history
 */

const REGISTRY = {
  // ===================================================================
  // 🔵 NECESSARY — Referenced from inline Twig <script> or onclick
  // ===================================================================

  'window.showMessage': {
    source: 'sweetalert2-handler.js', purpose: 'Show SweetAlert2 toast/notification messages', category: 'NECESSARY',
    usedBy: '~80+ Twig inline scripts across admin, auth, cv, comments views',
  },
  'window.showToast': {
    source: 'sweetalert2-handler.js', purpose: 'Show SweetAlert2 toast', category: 'NECESSARY',
    usedBy: 'Twig inline scripts (subscribers, accounts)',
  },
  'window.showAlert': {
    source: 'sweetalert2-handler.js', purpose: 'Show SweetAlert2 alert modal', category: 'NECESSARY',
    usedBy: 'ai-system-admin.js, account-settings-shared.js, Twig inline scripts',
  },
  'window.showConfirm': {
    source: 'sweetalert2-handler.js', purpose: 'Show SweetAlert2 confirmation modal', category: 'NECESSARY',
    usedBy: 'Inline scripts in CV view, admin comments, account settings',
  },
  'window.showPrompt': {
    source: 'sweetalert2-handler.js', purpose: 'Show SweetAlert2 prompt modal', category: 'NECESSARY',
    usedBy: 'account-settings-shared.js, admin comments Twig',
  },
  'window.broxUI': {
    source: 'brox-ui.js', purpose: 'UI component library (Modal, Dropdown, etc.)', category: 'NECESSARY',
    usedBy: 'Multiple modules via inline scripts',
  },
  'window.broxI18n': {
    source: 'brox-i18n.js', purpose: 'Internationalization: translate(), currentLang(), switchLang()', category: 'NECESSARY',
    usedBy: 'layout.twig, theme-manager.js, multiple Twig templates',
  },
  'window.AppConfig': {
    source: 'app-config.js', purpose: 'Global app config accessor (alias for AppJsConfig)', category: 'NECESSARY',
    usedBy: 'Multiple entry points',
  },
  'window.AppJsConfig': {
    source: 'app-config.js', purpose: 'Safe nested config getter', category: 'NECESSARY',
    usedBy: 'Multiple entry points',
  },
  'window.__APP_CONFIG': {
    source: 'app-config.js', purpose: 'Legacy config object', category: 'NECESSARY',
    usedBy: 'AppConfig getter, legacy consumers',
  },
  'window.lucide': {
    source: 'lucide CDN / lucide.min.js', purpose: 'Lucide icon library createIcons()', category: 'NECESSARY',
    usedBy: 'Inline scripts in CV dashboard, marketplace, lucide-svg.js',
  },
  'window.mapLucideToIcon': {
    source: 'lucide-compat.js', purpose: 'Map icon class names to lucide names', category: 'NECESSARY',
    usedBy: 'Inline scripts using legacy icon class names',
  },
  'window.FeedDiscovery': {
    source: 'feed-discovery.js', purpose: 'RSS feed discovery and modal', category: 'NECESSARY',
    usedBy: 'home.twig inline scripts',
  },

  // CV Builder (all NECESSARY — data-action / onclick in Twig)
  'window.bldNextStep': { source: 'cv-builder-app.js', purpose: 'Next step', category: 'NECESSARY', usedBy: 'cv/form.twig, cv/guest-form.twig', },
  'window.bldPrevStep': { source: 'cv-builder-app.js', purpose: 'Previous step', category: 'NECESSARY', usedBy: 'cv/form.twig, cv/guest-form.twig', },
  'window.bldSkipStep': { source: 'cv-builder-app.js', purpose: 'Skip step', category: 'NECESSARY', usedBy: 'cv/form.twig, cv/guest-form.twig', },
  'window.bldSaveDraft': { source: 'cv-builder-app.js', purpose: 'Save draft', category: 'NECESSARY', usedBy: 'cv/form.twig, cv/guest-form.twig', },
  'window.bldSelectJob': { source: 'cv-builder-app.js', purpose: 'Select job', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldEditEntry': { source: 'cv-builder-app.js', purpose: 'Edit entry', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldDoneEditing': { source: 'cv-builder-app.js', purpose: 'Done editing', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldAddExperience': { source: 'cv-builder-app.js', purpose: 'Add experience', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldAddEducation': { source: 'cv-builder-app.js', purpose: 'Add education', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldAddLanguage': { source: 'cv-builder-app.js', purpose: 'Add language', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldAddSocialLink': { source: 'cv-builder-app.js', purpose: 'Add social link', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldAddCustomSection': { source: 'cv-builder-app.js', purpose: 'Add custom section', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldAddReference': { source: 'cv-builder-app.js', purpose: 'Add reference', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldRemoveEntry': { source: 'cv-builder-app.js', purpose: 'Remove entry', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldMoveEntry': { source: 'cv-builder-app.js', purpose: 'Move entry', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldAddSkill': { source: 'cv-builder-app.js', purpose: 'Add skill', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldRemoveSkill': { source: 'cv-builder-app.js', purpose: 'Remove skill', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldEditSkill': { source: 'cv-builder-app.js', purpose: 'Edit skill', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldSelectTemplate': { source: 'cv-builder-app.js', purpose: 'Select template', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldUploadPhoto': { source: 'cv-builder-app.js', purpose: 'Upload photo', category: 'NECESSARY', usedBy: 'cv/form.twig', },
  'window.bldRemovePhoto': { source: 'cv-builder-app.js', purpose: 'Remove photo', category: 'NECESSARY', usedBy: 'cv/form.twig', },

  // CV Dashboard
  'window.shareOnPlatform': { source: 'cv-dashboard.js', purpose: 'Share CV on social platform', category: 'NECESSARY', usedBy: 'onclick in share modal HTML', },
  'window.copyToClipboard': { source: 'cv-dashboard.js', purpose: 'Copy URL to clipboard', category: 'NECESSARY', usedBy: 'onclick in share modal HTML', },
  'window.closeShareModal': { source: 'cv-dashboard.js', purpose: 'Close share modal', category: 'NECESSARY', usedBy: 'onclick in share modal HTML', },

  // CV Marketplace
  'window.mktPreview': { source: 'cv-marketplace.js', purpose: 'Preview template', category: 'NECESSARY', usedBy: 'onclick in generated HTML', },
  'window.mktCloseModal': { source: 'cv-marketplace.js', purpose: 'Close marketplace modal', category: 'NECESSARY', usedBy: 'onclick in generated HTML', },
  'window.mktSelectTemplate': { source: 'cv-marketplace.js', purpose: 'Select template', category: 'NECESSARY', usedBy: 'onclick in generated HTML', },

  // Photo Studio (all NECESSARY — onclick in toolbar HTML)
  'window.photoStudio': { source: 'photo-studio/editor.js', purpose: 'PhotoStudio instance', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.setTool': { source: 'photo-studio/editor.js', purpose: 'Set active tool', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.rotateImage': { source: 'photo-studio/editor.js', purpose: 'Rotate 90 degrees', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.flipImage': { source: 'photo-studio/editor.js', purpose: 'Flip direction', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.applyFilter': { source: 'photo-studio/editor.js', purpose: 'Apply filter', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.applyAllFilters': { source: 'photo-studio/editor.js', purpose: 'Apply all filters', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.resetFilters': { source: 'photo-studio/editor.js', purpose: 'Reset filters', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.undo': { source: 'photo-studio/editor.js', purpose: 'Undo', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.redo': { source: 'photo-studio/editor.js', purpose: 'Redo', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.zoomIn': { source: 'photo-studio/editor.js', purpose: 'Zoom in', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.zoomOut': { source: 'photo-studio/editor.js', purpose: 'Zoom out', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.resetZoom': { source: 'photo-studio/editor.js', purpose: 'Reset zoom', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.bgRemove': { source: 'photo-studio/editor.js', purpose: 'Remove background', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.downloadImage': { source: 'photo-studio/editor.js', purpose: 'Download', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.toggleToolsPanel': { source: 'photo-studio/editor.js', purpose: 'Toggle tools panel', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.preparePrintReady': { source: 'photo-studio/editor.js', purpose: 'Prepare print-ready', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.fitToGuide': { source: 'photo-studio/editor.js', purpose: 'Fit to guide', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.centerSubject': { source: 'photo-studio/editor.js', purpose: 'Center subject', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.clearBackgroundLayer': { source: 'photo-studio/editor.js', purpose: 'Clear background', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.setBackgroundColor': { source: 'photo-studio/editor.js', purpose: 'Set bg color', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.toggleGuides': { source: 'photo-studio/editor.js', purpose: 'Toggle guides', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },
  'window.applyCrop': { source: 'photo-studio/editor.js', purpose: 'Apply crop', category: 'NECESSARY', usedBy: 'onclick in toolbar HTML', },

  // AI System Admin
  'window.aiSystemTestConnection': { source: 'ai-system-admin.js', purpose: 'Test connection', category: 'NECESSARY', usedBy: 'onclick in admin', },
  'window.aiSystemRunTest': { source: 'ai-system-admin.js', purpose: 'Run model test', category: 'NECESSARY', usedBy: 'onclick in admin', },
  'window.aiSystemToggleProvider': { source: 'ai-system-admin.js', purpose: 'Toggle provider', category: 'NECESSARY', usedBy: 'onclick in admin', },
  'window.aiSystemOpenEdit': { source: 'ai-system-admin.js', purpose: 'Open edit modal', category: 'NECESSARY', usedBy: 'onclick in admin', },
  'window.aiSystemDelete': { source: 'ai-system-admin.js', purpose: 'Delete provider', category: 'NECESSARY', usedBy: 'onclick in admin', },

  // Admin module globals (NECESSARY — onclick/data-action in admin Twig)
  'window.addSpec': { source: 'modules/mobile.js', purpose: 'Add spec row', category: 'NECESSARY', usedBy: 'Inline admin scripts', },
  'window.removeSpec': { source: 'modules/mobile.js', purpose: 'Remove spec row', category: 'NECESSARY', usedBy: 'Inline admin scripts', },
  'window.markImageForDeletion': { source: 'modules/mobile.js', purpose: 'Mark image for deletion', category: 'NECESSARY', usedBy: 'Inline admin scripts', },
  'window.undoImageDeletion': { source: 'modules/mobile.js', purpose: 'Undo deletion', category: 'NECESSARY', usedBy: 'Inline admin scripts', },
  'window.approveApplication': { source: 'modules/applications.js', purpose: 'Approve application', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.rejectApplication': { source: 'modules/applications.js', purpose: 'Reject application', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.markProcessing': { source: 'modules/applications.js', purpose: 'Mark processing', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.addNote': { source: 'modules/applications.js', purpose: 'Add note', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.activateService': { source: 'modules/applications.js', purpose: 'Activate service', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.revertStatus': { source: 'modules/applications.js', purpose: 'Revert status', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.deleteTemplate': { source: 'modules/email-templates.js', purpose: 'Delete template', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.applySubsFilter': { source: 'modules/subscribers.js', purpose: 'Apply filter', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.revokeDevice': { source: 'modules/subscribers.js', purpose: 'Revoke device', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.deleteDevicePermanent': { source: 'modules/subscribers.js', purpose: 'Permanent delete', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.revokeAllDevices': { source: 'modules/subscribers.js', purpose: 'Revoke all devices', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.copySecret': { source: 'modules/security-2fa.js', purpose: 'Copy 2FA secret', category: 'NECESSARY', usedBy: 'onclick in 2FA setup', },
  'window.goBack': { source: 'modules/security-2fa.js', purpose: 'Go back', category: 'NECESSARY', usedBy: 'onclick in 2FA setup', },
  'window.copyAllBackupCodes': { source: 'modules/security-2fa.js', purpose: 'Copy backup codes', category: 'NECESSARY', usedBy: 'onclick in 2FA setup', },
  'window.regenerateBackupCodes': { source: 'modules/security-2fa.js', purpose: 'Regenerate codes', category: 'NECESSARY', usedBy: 'onclick in 2FA setup', },
  'window.selectAll': { source: 'modules/rbac-users.js', purpose: 'Select all', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.deselectAll': { source: 'modules/rbac-users.js', purpose: 'Deselect all', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.selectUser': { source: 'modules/rbac-users.js', purpose: 'Select user', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.removeUserRole': { source: 'modules/rbac-users.js', purpose: 'Remove role', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.refreshAllData': { source: 'modules/realtime-monitoring.js', purpose: 'Refresh data', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.toggleAutoRefresh': { source: 'modules/realtime-monitoring.js', purpose: 'Toggle refresh', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.filterNotifications': { source: 'modules/notifications-dashboard.js', purpose: 'Filter notifications', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.loadNotificationDetail': { source: 'modules/notifications-dashboard.js', purpose: 'Load notification detail', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.deleteNotification': { source: 'modules/notifications-dashboard.js', purpose: 'Delete notification', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.refreshDeviceList': { source: 'modules/notifications-device-sync.js', purpose: 'Refresh device list', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.manualSync': { source: 'modules/notifications-device-sync.js', purpose: 'Manual sync', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.syncDevice': { source: 'modules/notifications-device-sync.js', purpose: 'Sync device', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.removeDevice': { source: 'modules/notifications-device-sync.js', purpose: 'Remove device', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.clearSyncLog': { source: 'modules/notifications-device-sync.js', purpose: 'Clear sync log', category: 'NECESSARY', usedBy: 'data-action in admin', },
  'window.filterSyncLog': { source: 'modules/notifications-device-sync.js', purpose: 'Filter sync log', category: 'NECESSARY', usedBy: 'data-action in admin', },

  // Medex pages
  'window.toggleSection': { source: 'medex-brand-page.js', purpose: 'Toggle section', category: 'NECESSARY', usedBy: 'onclick in brand template', },
  'window.expandAll': { source: 'medex-brand-page.js', purpose: 'Expand all', category: 'NECESSARY', usedBy: 'onclick in brand template', },
  'window.collapseAll': { source: 'medex-brand-page.js', purpose: 'Collapse all', category: 'NECESSARY', usedBy: 'onclick in brand template', },
  'window.sendMedexRefreshRequest': { source: 'medex-*.js', purpose: 'Request data refresh', category: 'NECESSARY', usedBy: 'onclick in medex templates', },
  'window.storeLoginRedirectUrl': { source: 'auth/login.js', purpose: 'Store redirect URL', category: 'NECESSARY', usedBy: 'login Twig inline script', },
  'window.validatePasswordStrength': { source: 'admin.js (DOMContentLoaded)', purpose: 'Validate password strength (from initPasswordModals)', category: 'NECESSARY', usedBy: 'corresponding panel', },
  'window.setPassword': { source: 'admin.js (DOMContentLoaded)', purpose: 'Set password (from initPasswordModals)', category: 'NECESSARY', usedBy: 'corresponding panel', },
  'window.changePassword': { source: 'admin.js (DOMContentLoaded)', purpose: 'Change password (from initPasswordModals)', category: 'NECESSARY', usedBy: 'corresponding panel', },

  // Legacy admin globals
  'window.adminContent': { source: 'modules/legacy-admin-globals.js', purpose: 'Categories/tags API', category: 'NECESSARY', usedBy: 'Inline admin scripts', },
  'window.transliterateAndGenerateSlug': { source: 'modules/legacy-admin-globals.js', purpose: 'Generate slug', category: 'NECESSARY', usedBy: 'Inline admin scripts', },
  'window.__adminInlineHelpers': { source: 'admin.js (module scope, no IIFE)', purpose: 'Inline helpers (byId, getCsrfToken, loadAdminModule, 50+ init* fns)', category: 'NECESSARY', usedBy: 'Inline admin scripts', },
  'window.PostFormEnhancements': { source: 'admin/form-enhancements.js', purpose: 'Form validation API', category: 'NECESSARY', usedBy: 'Potential external consumers', },
  'window.dashboardData': { source: 'analytics-dashboard.js', purpose: 'Analytics dashboard data', category: 'NECESSARY', usedBy: 'analytics-dashboard.js', },

  // Shared DOM helpers (synchronous <script> in layout.twig head)
  'window.debounce': {
    source: 'shared/dom-helpers.js (synced via shared/utils.js export)', purpose: 'Debounce function for scroll/resize/input handlers', category: 'NECESSARY',
    usedBy: 'Inline scripts, layout.twig, module entry points — loaded synchronously before deferred modules',
  },
  'window.throttle': {
    source: 'shared/dom-helpers.js (synced via shared/utils.js export)', purpose: 'Throttle function for scroll/resize handlers', category: 'NECESSARY',
    usedBy: 'Inline scripts, layout.twig, module entry points — loaded synchronously before deferred modules',
  },

  // ===================================================================
  // 🟡 BRIDGE — Set by Twig inline script (backend → frontend data)
  // ===================================================================

  'window.__APP_JS_CONFIG': { source: 'app-config.js + layout.twig', purpose: 'Merged config object', category: 'BRIDGE', usedBy: 'app-config.js, theme-manager.js', },
  'window.__APP_JS_CONFIG_OVERRIDES': { source: 'layout.twig', purpose: 'Server-side config overrides', category: 'BRIDGE', usedBy: 'app-config.js', },
  'window.__APP_CONFIG_OVERRIDES': { source: 'layout.twig', purpose: 'Legacy config overrides', category: 'BRIDGE', usedBy: 'app-config.js', },
  'window.__broxSiteLang': { source: 'layout.twig', purpose: 'Current language code', category: 'BRIDGE', usedBy: 'brox-i18n.js', },
  'window.__broxSiteLogo': { source: 'layout.twig', purpose: 'Site logo URL', category: 'BRIDGE', usedBy: 'analytics-dashboard.js', },
  'window.__broxSiteTranslations': { source: 'layout.twig', purpose: 'Translation strings', category: 'BRIDGE', usedBy: 'brox-i18n.js', },
  'window.__clientIp': { source: 'PHP server', purpose: 'Client IP for activity tracking', category: 'BRIDGE', usedBy: 'activity.js', },
  'window.__INITIAL_FLASH': { source: '_macros/flash.twig', purpose: 'Initial flash message', category: 'BRIDGE', usedBy: 'sweetalert2-handler.js', },
  'window.__INITIAL_FLASH_QUEUE': { source: '_macros/flash.twig', purpose: 'Flash message queue', category: 'BRIDGE', usedBy: 'sweetalert2-handler.js', },
  'window.__bldData': { source: 'cv/form.twig', purpose: 'CV builder form data', category: 'BRIDGE', usedBy: 'cv-builder-app.js', },
  'window.__bldCvId': { source: 'cv/form.twig', purpose: 'CV ID', category: 'BRIDGE', usedBy: 'cv-builder-app.js', },
  'window.__bldCsrf': { source: 'cv/form.twig', purpose: 'CSRF token', category: 'BRIDGE', usedBy: 'cv-builder-app.js', },
  'window.__bldJobPositions': { source: 'cv/form.twig', purpose: 'Job positions', category: 'BRIDGE', usedBy: 'cv-builder-app.js', },
  'window.__bldTemplates': { source: 'cv/form.twig', purpose: 'Available templates', category: 'BRIDGE', usedBy: 'cv-builder-app.js, cv-builder-renderers.js', },
  'window.__bldSelectedTemplate': { source: 'cv/form.twig', purpose: 'Selected template', category: 'BRIDGE', usedBy: 'cv-builder-app.js', },
  'window.__bldProfilePhoto': { source: 'cv/form.twig', purpose: 'Profile photo URL', category: 'BRIDGE', usedBy: 'cv-builder-app.js', },
  'window.__bldGuestMode': { source: 'cv/guest-form.twig', purpose: 'Guest mode flag', category: 'BRIDGE', usedBy: 'cv-builder-app.js', },
  'window.__bldGuestApiBase': { source: 'cv/guest-form.twig', purpose: 'Guest API base', category: 'BRIDGE', usedBy: 'cv-builder-app.js', },
  'window.__mktTemplates': { source: 'cv/marketplace.twig', purpose: 'Template data', category: 'BRIDGE', usedBy: 'cv-marketplace.js', },
  'window.__mktSlugs': { source: 'cv/marketplace.twig', purpose: 'Template slugs', category: 'BRIDGE', usedBy: 'cv-marketplace.js', },
  'window.__mktCsrf': { source: 'cv/marketplace.twig', purpose: 'CSRF token', category: 'BRIDGE', usedBy: 'cv-marketplace.js', },
  'window.__steps': { source: 'cv/editor.twig', purpose: 'CV editor steps', category: 'BRIDGE', usedBy: 'cv/editor.twig inline script', },
  'window.editor': { source: 'cv/editor.twig', purpose: 'CV editor instance', category: 'BRIDGE', usedBy: 'cv/editor.twig inline script', },
  'window.aiSystemSettings': { source: 'admin Twig', purpose: 'Provider settings', category: 'BRIDGE', usedBy: 'ai-system-admin.js', },
  'window.aiSystemProviders': { source: 'admin Twig', purpose: 'Provider list', category: 'BRIDGE', usedBy: 'ai-system-admin.js', },
  'window.guest_cvs_just_claimed': { source: 'PHP flash', purpose: 'Flag guest CVs claimed', category: 'BRIDGE', usedBy: 'CV/User/Admin dashboards', },

  // ===================================================================
  // 🟢 EXPORTED — Module has ES export; window assignment is legacy compat
  // ===================================================================

  'window.ActivityTracker': { source: 'activity.js', purpose: 'Activity tracking API', category: 'EXPORTED', usedBy: 'Exported, no Twig refs', },
  'window.BroxAccountSettings': { source: 'account-settings-shared.js', purpose: 'Account settings API', category: 'EXPORTED', usedBy: 'Exported as default, no Twig refs', },
  'window.AdminSidebarAPI': { source: 'modules/admin-sidebar-polish.js', purpose: 'Sidebar API', category: 'EXPORTED', usedBy: 'Exported, no Twig refs', },
  'window.themeManager': { source: 'theme-manager.js', purpose: 'Theme manager instance', category: 'EXPORTED', usedBy: 'Internalized to module scope', },
  'window.draftMgr': { source: 'modules/drafts.js', purpose: 'Draft manager instance', category: 'EXPORTED', usedBy: 'Internalized to module scope', },
  'window.formAutosave': { source: 'modules/autosave.js', purpose: 'Form autosave instance', category: 'EXPORTED', usedBy: 'Internalized to module scope', },

  // ===================================================================
  // 🟠 INTERNAL — State/data sharing between modules
  // ===================================================================

  'window.__broxClientTranslationCache': { source: 'brox-i18n.js', purpose: 'Translation cache', category: 'INTERNAL', usedBy: 'brox-i18n.js', },
  'window.__FLASH_RENDERED_ON_LOAD': { source: 'sweetalert2-handler.js', purpose: 'Flash render flag', category: 'INTERNAL', usedBy: 'sweetalert2-handler.js', },
  'window.__fcmMessagingSupported': { source: 'notification-runtime.js', purpose: 'FCM support flag', category: 'INTERNAL', usedBy: 'notification-runtime.js, notifications.js, script.js', },
  'window.__fcmTokenObtained': { source: 'notification-runtime.js', purpose: 'FCM token flag', category: 'INTERNAL', usedBy: 'notification-runtime.js', },
  'window.__requestFcmTokenSync': { source: 'notification-runtime.js', purpose: 'FCM token sync fn', category: 'INTERNAL', usedBy: 'notification-runtime.js, notifications.js, script.js', },
  'window.__pendingFcmTokenSync': { source: 'notification-runtime.js', purpose: 'Pending sync flag', category: 'INTERNAL', usedBy: 'notification-runtime.js', },
  'window.activitySessionInfo': { source: 'activity.js', purpose: 'Session info', category: 'INTERNAL', usedBy: 'activity.js', },
  'window.BroxNavbar': { source: 'script.js', purpose: 'Navbar component', category: 'INTERNAL', usedBy: 'script.js', },
  'window.CounterAnimation': { source: 'script.js', purpose: 'Counter animation', category: 'INTERNAL', usedBy: 'script.js', },
  'window.initializeCounters': { source: 'script.js', purpose: 'Init counters', category: 'INTERNAL', usedBy: 'script.js', },
  'window.reinitializeCarousels': { source: 'script.js', purpose: 'Reinit carousels', category: 'INTERNAL', usedBy: 'script.js', },
  'window.postCategoryIds': { source: 'modules/shared.js', purpose: 'Post categories', category: 'INTERNAL', usedBy: 'shared.js, inline admin scripts', },
  'window.postTagIds': { source: 'modules/shared.js', purpose: 'Post tags', category: 'INTERNAL', usedBy: 'shared.js, inline admin scripts', },
  'window.serviceCategoryIds': { source: 'modules/services.js', purpose: 'Service categories', category: 'INTERNAL', usedBy: 'modules/services.js', },
  'window.serviceTagIds': { source: 'modules/services.js', purpose: 'Service tags', category: 'INTERNAL', usedBy: 'modules/services.js', },
  'window.currentAppId': { source: 'modules/services.js', purpose: 'Current app ID', category: 'INTERNAL', usedBy: 'modules/services.js', },
  'window.DebugUtils': { source: 'modules/notifications.js', purpose: 'Debug utils', category: 'INTERNAL', usedBy: 'modules/notifications.js', },
  'window.debugUtilsReady': { source: 'modules/notifications.js', purpose: 'Debug ready flag', category: 'INTERNAL', usedBy: 'modules/notifications.js', },
  'window.BLOG_DASHBOARD': { source: 'modules/shared.js', purpose: 'Blog dashboard data', category: 'INTERNAL', usedBy: 'modules/shared.js', },

  // ===================================================================
  // 🔴 REMOVED — Cleaned up in recent refactoring; listed for history
  // ===================================================================

  'window.convertBijoyToUnicode': { source: 'bangla-converter.js', purpose: 'Bijoy → Unicode', category: 'REMOVED', usedBy: 'Removed from window; kept as ES export', },
  'window.convertUnicodeToBijoy': { source: 'bangla-converter.js', purpose: 'Unicode → Bijoy', category: 'REMOVED', usedBy: 'Removed from window; kept as ES export', },
  'window.writer': { source: 'admin-article-writer-stream.js', purpose: 'Writer instance', category: 'REMOVED', usedBy: 'Removed from window; kept as ES export', },
  'window.prepare': { source: 'lucide-svg.js', purpose: 'Prepare icons', category: 'REMOVED', usedBy: 'Removed from window; kept as ES export', },
  'window.runCreate': { source: 'lucide-svg.js', purpose: 'Create icons', category: 'REMOVED', usedBy: 'Removed from window; kept as ES export', },
  'window.loadAndRun': { source: 'lucide-svg.js', purpose: 'Load + create', category: 'REMOVED', usedBy: 'Removed from window; kept as ES export', },
  'window.init': { source: 'lucide-svg.js', purpose: 'Init icons', category: 'REMOVED', usedBy: 'Removed from window; kept as ES export', },
  'window.currentLogJson': { source: 'modules/activity-log.js', purpose: 'Log JSON for copy', category: 'REMOVED', usedBy: 'Internalized to module scope', },
};

export default REGISTRY;
export { REGISTRY };

/**
 * Log all registered globals to console grouped by category
 */
export function logRegistry() {
  const cats = {};
  Object.entries(REGISTRY).forEach(([key, entry,]) => {
    const c = entry.category || 'OTHER';
    if (!cats[c]) cats[c] = [];
    cats[c].push({ name: key, ...entry, });
  });

  console.group('Window Globals Registry');
  Object.entries(cats).forEach(([cat, items,]) => {
    console.group(`  ${cat} (${items.length})`);
    items.forEach(item => console.log(`  ${item.name} — ${item.purpose}`));
    console.groupEnd();
  });
  console.groupEnd();
}
