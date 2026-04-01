/**
 * Notifications Module
 * Handles FCM notifications, bell dropdown, and related UI
 */

import { runWhenReady, getUserId, adminGetCsrfToken, adminEscapeHtml, adminToSafeUrl, adminFormatTime, adminSetListEmpty, adminUpdateBadge } from './utils.js';

const ADMIN_NAV_DROPDOWN_OPEN_EVENT = 'brox:navbar-dropdown-open';

const adminNotificationCoreState = new Map();
const adminNotificationBellState = new Map();

export function adminEmitFcmSupportResolved(supported, context = 'admin') {
    if (typeof window === 'undefined') return;
    const normalized = !!supported;
    window.__fcmMessagingSupported = normalized;
    try {
        window.dispatchEvent(new CustomEvent('fcm-support-resolved', {
            detail: { supported: normalized, context }
        }));
    } catch (err) {
        // Ignore dispatch failures.
    }
}

export function adminEmitNavbarDropdownState(kind, open) {
    try {
        document.dispatchEvent(new CustomEvent(
            open ? ADMIN_NAV_DROPDOWN_OPEN_EVENT : 'brox:navbar-dropdown-close',
            { detail: { kind, open: !!open, timestamp: Date.now() } }
        ));
    } catch (err) {
        // Ignore dispatch failures.
    }
}

export function adminRenderNotifications(listEl, notifications) {
    if (!listEl) return;
    if (!Array.isArray(notifications) || notifications.length === 0) {
        adminSetListEmpty(listEl, 'No new notifications');
        return;
    }

    listEl.innerHTML = notifications.map((notification) => {
        const id = Number.parseInt(notification?.id, 10) || 0;
        const title = adminEscapeHtml(notification?.title || 'Notification');
        const message = adminEscapeHtml(notification?.message || '');
        const createdAt = adminEscapeHtml(adminFormatTime(notification?.created_at));
        const href = adminToSafeUrl(notification?.action_url);
        const isRead = Number(notification?.is_read) === 1;
        const rowClass = isRead ? '' : 'bg-light border-start border-primary border-2';
        const urlAttr = href === '#' ? '' : ` data-action-url="${adminEscapeHtml(href)}"`;

        return `
            <div class="notification-entry p-2 mb-2 rounded ${rowClass}" data-notification-id="${id}"${urlAttr}>
                <div class="d-flex align-items-start gap-2">
                    <div class="flex-grow-1">
                        <div class="fw-semibold small mb-1">${title}</div>
                        <div class="small text-muted mb-1">${message}</div>
                        <div class="small text-secondary">${createdAt}</div>
                    </div>
                    ${isRead ? '' : `<button type="button" class="btn btn-sm btn-outline-primary" data-action="mark-read" data-notification-id="${id}">Read</button>`}
                </div>
            </div>
        `;
    }).join('');
}

export async function adminFetchNotifications(limit = 10) {
    const response = await fetch(`/api/user-notifications?limit=${encodeURIComponent(limit)}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
    });
    if (!response.ok) {
        throw new Error(`Failed to load notifications (${response.status})`);
    }
    const data = await response.json().catch(() => ({}));
    const notifications = Array.isArray(data.notifications) ? data.notifications : [];
    const unreadCount = Number.isFinite(Number(data.unread_count))
        ? Number(data.unread_count)
        : notifications.filter((row) => Number(row?.is_read) !== 1).length;
    return { notifications, unreadCount };
}

export async function adminMarkNotificationRead(notificationId) {
    const response = await fetch('/api/notification/mark-read', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': adminGetCsrfToken()
        },
        body: JSON.stringify({ notification_id: notificationId })
    });
    if (!response.ok) return false;
    const data = await response.json().catch(() => ({}));
    return data?.success !== false;
}

export function adminGetBellKey(options) {
    return [
        options.context || 'admin',
        options.bellSelector || '',
        options.listSelector || ''
    ].join('|');
}

export function adminFindElement(selector, attrName) {
    if (selector) {
        const selected = document.querySelector(selector);
        if (selected) return selected;
    }
    if (!attrName) return null;
    return document.querySelector(`[${attrName}]`);
}

export function adminGetDropdownMenuElement(bellEl, listEl) {
    const wrapper = bellEl?.closest('[data-notification-menu]');
    if (wrapper) {
        const menu = wrapper.querySelector('[data-notification-dropdown]');
        if (menu) return menu;
    }
    return listEl?.closest('.admin-notification-dropdown, .brox-notification-dropdown') || null;
}

export async function adminInitNotificationCore(options = {}) {
    const context = options.context || 'admin';
    const existing = adminNotificationCoreState.get(context);
    if (existing?.promise) return existing.promise;

    const state = { initialized: false, promise: null };
    state.promise = (async () => {
        try {
            const userId = options.userId ?? getUserId();
            const [{ initFirebase }, messagingMod] = await Promise.all([
                import('/assets/firebase/v2/dist/init.js'),
                import('/assets/firebase/v2/dist/messaging.js')
            ]);

            const {
                autoInitializeFCMToken,
                obtainAndSendFCMToken,
                autoInitializeForegroundListener,
                isMessagingSupported
            } = messagingMod;

            const messagingSupported = typeof isMessagingSupported === 'function'
                ? (await isMessagingSupported()) === true
                : true;
            adminEmitFcmSupportResolved(messagingSupported, context);

            if (!messagingSupported) {
                window.__fcmTokenObtained = false;
                window.__requestFcmTokenSync = async () => false;
                if (window.__pendingFcmTokenSync) {
                    window.__pendingFcmTokenSync = false;
                }
                state.initialized = true;
                return true;
            }

            window.__requestFcmTokenSync = async (syncOptions = {}) => {
                try {
                    window.__fcmTokenObtained = true;
                    const effectiveUserId = syncOptions.userId ?? userId;
                    await obtainAndSendFCMToken({
                        requestPermission: false,
                        userId: effectiveUserId || undefined,
                        deviceId: syncOptions.deviceId
                    });
                    return true;
                } catch (err) {
                    window.__fcmTokenObtained = false;
                    return false;
                }
            };

            autoInitializeForegroundListener();
            autoInitializeFCMToken({
                userId,
                onSuccess: () => { },
                onError: () => { },
                autoRetry: true
            });

            try {
                await initFirebase();
            } catch (err) {
                // Non-fatal by design.
            }

            if (window.__pendingFcmTokenSync) {
                window.__pendingFcmTokenSync = false;
                window.__requestFcmTokenSync?.();
            }

            state.initialized = true;
            return true;
        } catch (err) {
            adminNotificationCoreState.delete(context);
            return false;
        }
    })();

    adminNotificationCoreState.set(context, state);
    return state.promise;
}

export function adminInitNotificationBell(options = {}) {
    const key = adminGetBellKey(options);
    const previous = adminNotificationBellState.get(key);
    if (previous?.destroy) previous.destroy();

    const pollIntervalMs = Number.isFinite(options.pollIntervalMs) ? options.pollIntervalMs : 60000;
    const limit = Number.isFinite(options.limit) ? options.limit : 10;
    const bellEl = adminFindElement(options.bellSelector, 'data-notification-bell');
    const badgeEl = adminFindElement(options.badgeSelector, 'data-notification-badge');
    const countEl = adminFindElement(options.countSelector, 'data-notification-count');
    const listEl = adminFindElement(options.listSelector, 'data-notification-list');
    const menuEl = adminGetDropdownMenuElement(bellEl, listEl);

    if (!bellEl || !listEl || !menuEl) {
        return { active: false };
    }

    if (bellEl.hasAttribute('data-bs-toggle')) {
        bellEl.removeAttribute('data-bs-toggle');
    }

    menuEl.classList.remove('show');
    bellEl.classList.remove('show');
    bellEl.closest('.dropdown')?.classList.remove('show');
    bellEl.setAttribute('aria-expanded', 'false');

    const abortController = new AbortController();
    const state = {
        loading: false,
        initialized: false,
        pollId: null,
        destroy() {
            abortController.abort();
            if (state.pollId) {
                clearInterval(state.pollId);
                state.pollId = null;
            }
            adminNotificationBellState.delete(key);
        }
    };

    const loadAndRender = async () => {
        if (state.loading) return;
        state.loading = true;
        try {
            const data = await adminFetchNotifications(limit);
            adminRenderNotifications(listEl, data.notifications);
            adminUpdateBadge(badgeEl, countEl, data.unreadCount);
            state.initialized = true;
        } catch (err) {
            if (!state.initialized) {
                adminSetListEmpty(listEl, 'Failed to load notifications');
            }
            adminUpdateBadge(badgeEl, countEl, 0);
        } finally {
            state.loading = false;
        }
    };

    const showMenu = () => {
        menuEl.classList.add('show');
        bellEl.classList.add('show');
        bellEl.closest('.dropdown')?.classList.add('show');
        bellEl.setAttribute('aria-expanded', 'true');
        adminEmitNavbarDropdownState('notification', true);
    };

    const hideMenu = () => {
        const wasOpen = menuEl.classList.contains('show');
        menuEl.classList.remove('show');
        bellEl.classList.remove('show');
        bellEl.closest('.dropdown')?.classList.remove('show');
        bellEl.setAttribute('aria-expanded', 'false');
        if (wasOpen) {
            adminEmitNavbarDropdownState('notification', false);
        }
    };

    const toggleMenu = () => {
        if (menuEl.classList.contains('show')) {
            hideMenu();
            return;
        }
        showMenu();
        loadAndRender();
    };

    const handleListClick = async (event) => {
        const button = event.target.closest('[data-action="mark-read"]');
        if (button && listEl.contains(button)) {
            event.preventDefault();
            event.stopPropagation();
            const notificationId = Number.parseInt(button.dataset.notificationId || '0', 10);
            if (!notificationId) return;
            button.disabled = true;
            const ok = await adminMarkNotificationRead(notificationId);
            button.disabled = false;
            if (ok) await loadAndRender();
            return;
        }

        const entry = event.target.closest('.notification-entry[data-action-url]');
        if (!entry || !listEl.contains(entry)) return;
        const href = adminToSafeUrl(entry.dataset.actionUrl || '');
        if (href !== '#') {
            window.location.href = href;
        }
    };

    const closeForExternalOpen = (event) => {
        const sourceKind = String(event?.detail?.kind || '');
        const isOpening = event?.detail?.open === true;
        if (!isOpening || sourceKind === 'notification') return;
        hideMenu();
    };

    const globalClickHandler = (event) => {
        if (!menuEl.classList.contains('show')) return;
        const target = event.target;
        if (target instanceof Element && (bellEl.contains(target) || menuEl.contains(target))) return;
        hideMenu();
    };

    const escapeHandler = (event) => {
        if (event.key === 'Escape') hideMenu();
    };

    bellEl.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        toggleMenu();
    }, { signal: abortController.signal });

    listEl.addEventListener('click', handleListClick, { signal: abortController.signal });
    document.addEventListener('click', globalClickHandler, { signal: abortController.signal });
    document.addEventListener('keydown', escapeHandler, { signal: abortController.signal });
    document.addEventListener(ADMIN_NAV_DROPDOWN_OPEN_EVENT, closeForExternalOpen, { signal: abortController.signal });

    runWhenReady(() => {
        loadAndRender();
    });

    state.pollId = window.setInterval(loadAndRender, pollIntervalMs);
    adminNotificationBellState.set(key, state);
    return { active: true, destroy: state.destroy };
}

export async function initAdminNotificationRuntime() {
    try {
        await adminInitNotificationCore({
            context: 'admin',
            permissionScope: 'admin',
            requestPermissionOnLoad: false,
            userId: getUserId(),
            permissionTitle: 'Enable Push Notifications',
            permissionMessage: 'Stay updated with instant alerts and important updates.',
            permissionEnableLabel: 'Enable',
            permissionLaterLabel: 'Later'
        });

        runWhenReady(() => {
            adminInitNotificationBell({
                context: 'admin',
                bellSelector: '#adminNotificationBell',
                badgeSelector: '#adminNotificationBadge',
                countSelector: '#adminNotificationCount',
                listSelector: '#adminNotificationsList'
            });
        });
    } catch (err) {
        // Silent fail
    }
}

export function initAdminUserDropdownSync() {
    const userToggle = document.getElementById('adminUserMenu');
    if (!userToggle) return;
    const userMenu = userToggle.closest('[data-admin-user-menu]');
    const userDropdown = userMenu?.querySelector('[data-admin-user-dropdown]');

    const closeUserDropdown = () => {
        if (window.bootstrap?.Dropdown?.getOrCreateInstance) {
            const instance = window.bootstrap.Dropdown.getOrCreateInstance(userToggle);
            if (instance && typeof instance.hide === 'function') {
                instance.hide();
                return;
            }
        }

        const wrapper = userMenu || userToggle.closest('.dropdown');
        const menu = userDropdown || wrapper?.querySelector('[data-admin-user-dropdown], .dropdown-menu');
        if (!wrapper || !menu) return;

        menu.classList.remove('show');
        userToggle.classList.remove('show');
        wrapper.classList.remove('show');
        userToggle.setAttribute('aria-expanded', 'false');
    };

    userToggle.addEventListener('shown.bs.dropdown', () => {
        adminEmitNavbarDropdownState('user', true);
    });

    userToggle.addEventListener('hidden.bs.dropdown', () => {
        adminEmitNavbarDropdownState('user', false);
    });

    document.addEventListener(ADMIN_NAV_DROPDOWN_OPEN_EVENT, (event) => {
        const sourceKind = String(event?.detail?.kind || '');
        const isOpening = event?.detail?.open === true;
        if (!isOpening || sourceKind === 'user') return;
        closeUserDropdown();
    });
}

export async function initAdminDebugUtils() {
    try {
        const mod = await import('/assets/firebase/v2/dist/debug.js');
        const DebugUtils = mod.default || mod.DebugUtils;
        if (!DebugUtils) return;

        window.DebugUtils = DebugUtils;
        window.debugUtilsReady = true;
        console.log('[DebugUtils] Initialized and ready');
        window.dispatchEvent(new CustomEvent('debugUtilsLoaded', { detail: DebugUtils }));
    } catch (err) {
        // Silent fail
    }
}

export async function initAdminUnifiedLogout() {
    try {
        const logoutRuntime = await import('./shared/logout-runtime.js');
        logoutRuntime.initUnifiedLogout({ context: 'admin' });
    } catch (err) {
        // Silent fail
    }
}

