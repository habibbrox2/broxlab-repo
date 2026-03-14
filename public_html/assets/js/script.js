import './shared/logout-runtime.js';





const getUserId = () => document.querySelector('meta[name="user-id"]')?.content || null;
const runWhenReady = (fn) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
        fn();
    }
};
const isAuthPageRoute = /\/(login|register|forgot-password|reset-password|verify-2fa)/.test(window.location.pathname);
const isLoggedIn = !!getUserId();
const notificationContext = isLoggedIn ? 'user' : 'public';
const globalNotificationConfig = window.__APP_JS_CONFIG?.notifications || window.__APP_CONFIG?.notifications || {};
const NAV_DROPDOWN_OPEN_EVENT = 'brox:navbar-dropdown-open';
const NAV_DROPDOWN_CLOSE_EVENT = 'brox:navbar-dropdown-close';


/* ===== Inlined Notification Runtime ===== */
const coreState = new Map();
const bellState = new Map();
const PERMISSION_POPUP_ID = 'notificationPermissionPopup';
const PERMISSION_POPUP_STYLE_ID = 'notificationPermissionPopupStyles';
const PERMISSION_REQUESTED_KEY = '__notification_perm_requested';
const PERMISSION_DISMISSED_KEY = '__notification_perm_dismissed';
const PERMISSION_SCOPE_FALLBACK = 'global';

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function emitFcmSupportResolved(supported, context = 'default') {
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

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char] || char));
}

function toSafeUrl(url) {
    const value = String(url || '').trim();
    if (!value) return '#';
    if (value.startsWith('/')) return value;
    if (/^https?:\/\//i.test(value)) return value;
    return '#';
}

function formatTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString();
}

function findElement(selector, attrName) {
    if (selector) {
        const selected = document.querySelector(selector);
        if (selected) return selected;
    }
    if (!attrName) return null;
    return document.querySelector(`[${attrName}]`);
}

function getDropdownMenuElement(bellEl, listEl) {
    // our structure places the menu inside the wrapper marked with
    // `data-notification-menu`; fall back to searching by class if needed.
    const wrapper = bellEl?.closest('[data-notification-menu]');
    if (wrapper) {
        const menu = wrapper.querySelector('[data-notification-dropdown]');
        if (menu) return menu;
    }
    return listEl?.closest('.brox-notification-dropdown') || null;
}

function resetDropdownViewportPosition(menuEl) {
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

function applyDropdownViewportFallback(menuEl, bellEl) {
    if (!menuEl || !bellEl) return;

    const computed = window.getComputedStyle(menuEl);
    if (computed.position === 'static') {
        // Mobile collapsed navbar uses static dropdown positioning by design.
        resetDropdownViewportPosition(menuEl);
        return;
    }

    const margin = 8;
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
    const rect = menuEl.getBoundingClientRect();
    const overflowed = (
        rect.left < margin ||
        rect.right > viewportWidth - margin ||
        rect.top < margin ||
        rect.bottom > viewportHeight - margin
    );

    if (!overflowed) {
        resetDropdownViewportPosition(menuEl);
        return;
    }

    const anchorRect = bellEl.getBoundingClientRect();
    const menuWidth = Math.min(rect.width || menuEl.offsetWidth || 320, Math.max(180, viewportWidth - (margin * 2)));
    const menuHeight = Math.min(rect.height || menuEl.offsetHeight || 360, Math.max(160, viewportHeight - (margin * 2)));

    let left = anchorRect.right - menuWidth;
    left = Math.max(margin, Math.min(left, viewportWidth - menuWidth - margin));

    let top = anchorRect.bottom + 8;
    if ((top + menuHeight) > (viewportHeight - margin)) {
        const upwardTop = anchorRect.top - menuHeight - 8;
        top = upwardTop >= margin
            ? upwardTop
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

function normalizePermissionScope(scope) {
    const raw = String(scope || '').trim().toLowerCase();
    if (!raw) return PERMISSION_SCOPE_FALLBACK;
    const normalized = raw.replace(/[^a-z0-9_-]/g, '');
    return normalized || PERMISSION_SCOPE_FALLBACK;
}

function resolvePermissionScope(options = {}) {
    const explicitScope = options.permissionScope ?? options.scope;
    if (explicitScope) return normalizePermissionScope(explicitScope);
    return normalizePermissionScope(options.context);
}

function permissionStorageKey(baseKey, scope) {
    const normalizedScope = normalizePermissionScope(scope);
    if (normalizedScope === PERMISSION_SCOPE_FALLBACK) return baseKey;
    return `${baseKey}__${normalizedScope}`;
}

function getPermissionStorageFlag(key) {
    try {
        return localStorage.getItem(key) === 'true';
    } catch (err) {
        return false;
    }
}

function setPermissionStorageFlag(key, value) {
    try {
        if (value) {
            localStorage.setItem(key, 'true');
        } else {
            localStorage.removeItem(key);
        }
    } catch (err) {
        // Ignore storage failures.
    }
}

function ensurePermissionPopupStyles() {
    if (document.getElementById(PERMISSION_POPUP_STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = PERMISSION_POPUP_STYLE_ID;
    style.textContent = `
        .notification-permission-popup {
            position: fixed;
            right: 16px;
            bottom: calc(16px + env(safe-area-inset-bottom, 0px));
            width: min(400px, calc(100vw - 24px));
            z-index: 1055;
            border-radius: 18px;
            padding: 18px;
            color: #0f172a;
            border: 1px solid rgba(148, 163, 184, 0.28);
            background:
                linear-gradient(165deg, rgba(255, 255, 255, 0.94), rgba(248, 250, 252, 0.9));
            box-shadow:
                0 22px 46px rgba(2, 6, 23, 0.24),
                0 1px 0 rgba(255, 255, 255, 0.6) inset;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            overflow: hidden;
            animation: notification-permission-popup-in 220ms ease-out;
        }
        .notification-permission-popup::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #0d6efd, #38bdf8 60%, #22c55e);
        }
        .notification-permission-popup__title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            margin: 0;
            font-size: 15px;
            line-height: 1.3;
        }
        .notification-permission-popup__title i {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: #0d6efd;
            background: rgba(13, 110, 253, 0.12);
            box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.2);
        }
        .notification-permission-popup__body {
            margin: 10px 0 0;
            font-size: 13px;
            line-height: 1.6;
            color: #334155;
        }
        .notification-permission-popup__actions {
            margin-top: 14px;
            display: flex;
            gap: 8px;
        }
        .notification-permission-popup__btn {
            flex: 1;
            min-height: 38px;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 120ms ease, box-shadow 140ms ease, background-color 140ms ease, color 140ms ease;
            border: 1px solid transparent;
        }
        .notification-permission-popup__btn:focus-visible {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.35);
        }
        .notification-permission-popup__btn:active {
            transform: translateY(1px);
        }
        .notification-permission-popup__btn--primary {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: #ffffff;
            box-shadow: 0 10px 18px rgba(13, 110, 253, 0.32);
        }
        .notification-permission-popup__btn--primary:hover {
            box-shadow: 0 12px 22px rgba(13, 110, 253, 0.4);
            filter: brightness(1.03);
        }
        .notification-permission-popup__btn--ghost {
            background: rgba(241, 245, 249, 0.8);
            color: #334155;
            border-color: rgba(148, 163, 184, 0.36);
        }
        .notification-permission-popup__btn--ghost:hover {
            background: rgba(226, 232, 240, 0.95);
        }
        .notification-permission-popup__btn:disabled {
            cursor: not-allowed;
            opacity: 0.75;
            box-shadow: none;
            filter: none;
        }
        [data-theme="dark"] .notification-permission-popup {
            color: #e2e8f0;
            border-color: rgba(71, 85, 105, 0.55);
            background:
                linear-gradient(165deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.88));
            box-shadow:
                0 22px 46px rgba(0, 0, 0, 0.45),
                0 1px 0 rgba(148, 163, 184, 0.16) inset;
        }
        [data-theme="dark"] .notification-permission-popup__title i {
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.2);
            box-shadow: inset 0 0 0 1px rgba(96, 165, 250, 0.28);
        }
        [data-theme="dark"] .notification-permission-popup__body {
            color: #cbd5e1;
        }
        [data-theme="dark"] .notification-permission-popup__btn--ghost {
            background: rgba(51, 65, 85, 0.9);
            color: #dbeafe;
            border-color: rgba(100, 116, 139, 0.6);
        }
        [data-theme="dark"] .notification-permission-popup__btn--ghost:hover {
            background: rgba(71, 85, 105, 0.95);
        }
        @media (max-width: 540px) {
            .notification-permission-popup {
                left: 12px;
                right: 12px;
                bottom: calc(12px + env(safe-area-inset-bottom, 0px));
                width: auto;
                padding: 16px;
            }
            .notification-permission-popup__actions {
                flex-direction: column;
            }
            .notification-permission-popup__btn {
                width: 100%;
            }
        }
        @keyframes notification-permission-popup-in {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    `;
    document.head.appendChild(style);
}

function removePermissionPopup() {
    document.getElementById(PERMISSION_POPUP_ID)?.remove();
}

async function requestNotificationPermissionTracked(options = {}) {
    const permissionScope = resolvePermissionScope(options);
    const requestedKey = permissionStorageKey(PERMISSION_REQUESTED_KEY, permissionScope);
    const dismissedKey = permissionStorageKey(PERMISSION_DISMISSED_KEY, permissionScope);

    if (typeof Notification === 'undefined') return 'unsupported';
    if (Notification.permission === 'granted') return 'granted';
    if (Notification.permission === 'denied') return 'denied';

    let permission = 'default';
    try {
        permission = await Notification.requestPermission();
    } catch (err) {
        permission = 'denied';
    }

    setPermissionStorageFlag(requestedKey, true);
    if (permission === 'granted') {
        setPermissionStorageFlag(dismissedKey, false);
        if (typeof options.onGranted === 'function') {
            await options.onGranted();
        }
    }
    return permission;
}

function showNotificationPermissionPopup(options = {}) {
    const permissionScope = resolvePermissionScope(options);
    const requestedKey = permissionStorageKey(PERMISSION_REQUESTED_KEY, permissionScope);
    const dismissedKey = permissionStorageKey(PERMISSION_DISMISSED_KEY, permissionScope);
    const force = options.force === true;
    if (typeof Notification === 'undefined' || Notification.permission !== 'default') return false;
    if (!force && (getPermissionStorageFlag(requestedKey) || getPermissionStorageFlag(dismissedKey))) {
        return false;
    }
    if (document.getElementById(PERMISSION_POPUP_ID)) return true;

    ensurePermissionPopupStyles();
    const title = options.title || 'Enable Push Notifications';
    const message = options.message || 'Stay updated with instant alerts and important updates.';
    const enableLabel = options.enableLabel || 'Enable';
    const laterLabel = options.laterLabel || 'Later';

    const popup = document.createElement('div');
    popup.id = PERMISSION_POPUP_ID;
    popup.className = 'notification-permission-popup';
    popup.innerHTML = `
        <div class="notification-permission-popup__title">
            <i class="bi bi-bell-fill text-primary"></i>
            <span>${escapeHtml(title)}</span>
        </div>
        <p class="notification-permission-popup__body">${escapeHtml(message)}</p>
        <div class="notification-permission-popup__actions">
            <button type="button" class="notification-permission-popup__btn notification-permission-popup__btn--primary" data-action="enable">${escapeHtml(enableLabel)}</button>
            <button type="button" class="notification-permission-popup__btn notification-permission-popup__btn--ghost" data-action="later">${escapeHtml(laterLabel)}</button>
        </div>
    `;
    document.body.appendChild(popup);

    const closeWithDismiss = () => {
        setPermissionStorageFlag(dismissedKey, true);
        removePermissionPopup();
    };

    popup.querySelector('[data-action="later"]')?.addEventListener('click', closeWithDismiss, { once: true });
    popup.querySelector('[data-action="enable"]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        if (button) button.disabled = true;
        await requestNotificationPermissionTracked({
            permissionScope,
            onGranted: options.onGranted
        });
        removePermissionPopup();
    }, { once: true });

    const autoHideMs = Number.isFinite(options.autoHideMs) ? options.autoHideMs : 15000;
    if (autoHideMs > 0) {
        window.setTimeout(() => {
            if (document.getElementById(PERMISSION_POPUP_ID)) {
                closeWithDismiss();
            }
        }, autoHideMs);
    }

    return true;
}

function getBellKey(options) {
    return [
        options.context || 'default',
        options.bellSelector || '',
        options.listSelector || ''
    ].join('|');
}

function setListEmpty(listEl, message) {
    if (!listEl) return;
    listEl.innerHTML = `
        <div class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-4"></i>
            <p class="mb-0 mt-2 small">${escapeHtml(message)}</p>
        </div>
    `;
}

function updateBadge(badgeEl, countEl, unreadCount) {
    const safeCount = Number.isFinite(unreadCount) ? Math.max(0, unreadCount) : 0;
    if (countEl) {
        countEl.textContent = String(safeCount);
    }
    if (badgeEl) {
        badgeEl.classList.toggle('d-none', safeCount <= 0);
    }
}

function renderNotifications(listEl, notifications) {
    if (!listEl) return;
    if (!Array.isArray(notifications) || notifications.length === 0) {
        setListEmpty(listEl, 'No new notifications');
        return;
    }

    listEl.innerHTML = notifications.map((notification) => {
        const id = Number.parseInt(notification?.id, 10) || 0;
        const title = escapeHtml(notification?.title || 'Notification');
        const message = escapeHtml(notification?.message || '');
        const createdAt = escapeHtml(formatTime(notification?.created_at));
        const href = toSafeUrl(notification?.action_url);
        const isRead = Number(notification?.is_read) === 1;
        const rowClass = isRead ? '' : 'bg-light border-start border-primary border-2';
        const urlAttr = href === '#' ? '' : ` data-action-url="${escapeHtml(href)}"`;

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

async function fetchNotifications(limit = 10) {
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

async function markNotificationRead(notificationId) {
    const response = await fetch('/api/notification/mark-read', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCsrfToken()
        },
        body: JSON.stringify({ notification_id: notificationId })
    });
    if (!response.ok) return false;
    const data = await response.json().catch(() => ({}));
    return data?.success !== false;
}

function initNotificationCore(options = {}) {
    const context = options.context || 'default';
    const existing = coreState.get(context);
    if (existing?.promise) return existing.promise;

    const state = { initialized: false, promise: null };
    const requestPermissionOnLoad = options.requestPermissionOnLoad === true;
    const promptDelayMs = Number.isFinite(options.permissionPromptDelayMs)
        ? options.permissionPromptDelayMs
        : (Number.isFinite(options.bannerDelayMs) ? options.bannerDelayMs : 3000);
    const showPermissionPopup = options.showPermissionPopup !== false;

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
            emitFcmSupportResolved(messagingSupported, context);
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
                // Silent fail: notification enhancements must not block page load.
            }

            if (window.__pendingFcmTokenSync) {
                window.__pendingFcmTokenSync = false;
                window.__requestFcmTokenSync?.();
            }

            if (showPermissionPopup) {
                const permissionScope = options.permissionScope || context;
                runWhenReady(() => {
                    setTimeout(() => {
                        showNotificationPermissionPopup({
                            context,
                            permissionScope,
                            onGranted: async () => {
                                if (typeof window.__requestFcmTokenSync === 'function') {
                                    await window.__requestFcmTokenSync();
                                } else {
                                    window.__pendingFcmTokenSync = true;
                                }
                            },
                            title: options.permissionTitle,
                            message: options.permissionMessage,
                            enableLabel: options.permissionEnableLabel,
                            laterLabel: options.permissionLaterLabel
                        });
                    }, promptDelayMs);
                });
            }

            if (requestPermissionOnLoad && typeof Notification !== 'undefined' && Notification.permission === 'default') {
                try {
                    await Notification.requestPermission();
                } catch (err) {
                    // Non-fatal by design.
                }
            }

            state.initialized = true;
            return true;
        } catch (err) {
            coreState.delete(context);
            return false;
        }
    })();

    coreState.set(context, state);
    return state.promise;
}

function initNotificationBell(options = {}) {
    const key = getBellKey(options);
    const previous = bellState.get(key);
    if (previous?.destroy) previous.destroy();

    const context = options.context || 'default';
    const pollIntervalMs = Number.isFinite(options.pollIntervalMs) ? options.pollIntervalMs : 60000;
    const limit = Number.isFinite(options.limit) ? options.limit : 10;
    const bellEl = findElement(options.bellSelector, 'data-notification-bell');
    const badgeEl = findElement(options.badgeSelector, 'data-notification-badge');
    const countEl = findElement(options.countSelector, 'data-notification-count');
    const listEl = findElement(options.listSelector, 'data-notification-list');

    // if bootstrap toggling is attached through attribute, remove it so our
    // custom handler has exclusive control. we still keep the element in a
    // `.dropdown` wrapper for styling, but the data attribute is unnecessary.
    if (bellEl && bellEl.hasAttribute('data-bs-toggle')) {
        bellEl.removeAttribute('data-bs-toggle');
    }

    if (!bellEl || !listEl) {
        return { active: false };
    }
    const menuEl = getDropdownMenuElement(bellEl, listEl);

    // make sure the dropdown is closed when we initialize; sometimes
    // the element may accidentally carry `show` from server-side rendering
    // or earlier scripts, which would keep it permanently visible.
    if (menuEl) {
        menuEl.classList.remove('show');
        bellEl.classList.remove('show');
        bellEl.closest('.dropdown')?.classList.remove('show');
        bellEl.setAttribute('aria-expanded', 'false');
    }

    const abortController = new AbortController();
    const state = {
        context,
        loading: false,
        initialized: false,
        pollId: null,
        destroy() {
            abortController.abort();
            if (state.pollId) {
                clearInterval(state.pollId);
                state.pollId = null;
            }
            bellState.delete(key);
        }
    };

    const loadAndRender = async () => {
        if (state.loading) return;
        state.loading = true;
        try {
            const data = await fetchNotifications(limit);
            renderNotifications(listEl, data.notifications);
            updateBadge(badgeEl, countEl, data.unreadCount);
            state.initialized = true;
        } catch (err) {
            if (!state.initialized) {
                setListEmpty(listEl, 'Failed to load notifications');
            }
            updateBadge(badgeEl, countEl, 0);
        } finally {
            state.loading = false;
        }
    };

    const handleListClick = async (event) => {
        const button = event.target.closest('[data-action="mark-read"]');
        if (button && listEl.contains(button)) {
            event.preventDefault();
            event.stopPropagation();
            const notificationId = Number.parseInt(button.dataset.notificationId || '0', 10);
            if (!notificationId) return;
            button.disabled = true;
            const ok = await markNotificationRead(notificationId);
            button.disabled = false;
            if (ok) {
                await loadAndRender();
            }
            return;
        }

        const entry = event.target.closest('.notification-entry[data-action-url]');
        if (!entry || !listEl.contains(entry)) return;
        const href = toSafeUrl(entry.dataset.actionUrl || '');
        if (href !== '#') {
            window.location.href = href;
        }
    };

    const hideMenuForExternalOpen = (event) => {
        const sourceKind = String(event?.detail?.kind || '');
        const isOpening = event?.detail?.open === true;
        if (!isOpening || sourceKind === 'notification') return;
        hideMenu();
    };

    // helper to show/hide menu without relying on Bootstrap
    const showMenu = () => {
        if (!menuEl) return;
        // ensure bell remains on top so it can be clicked again even if menu
        // overlaps it due to positioning fallback.
        menuEl.style.zIndex = '1079';
        bellEl.style.zIndex = '1081';

        menuEl.classList.add('show');
        bellEl.classList.add('show');
        bellEl.closest('.dropdown')?.classList.add('show');
        bellEl.setAttribute('aria-expanded', 'true');
        emitNavbarDropdownState('notification', true);
    };
    const hideMenu = () => {
        if (!menuEl) return;
        const wasOpen = menuEl.classList.contains('show');
        menuEl.classList.remove('show');
        bellEl.classList.remove('show');
        bellEl.closest('.dropdown')?.classList.remove('show');
        bellEl.setAttribute('aria-expanded', 'false');

        // reset styles applied by viewport fallback
        resetDropdownViewportPosition(menuEl);
        menuEl.style.zIndex = '';
        bellEl.style.zIndex = '';
        if (wasOpen) {
            emitNavbarDropdownState('notification', false);
        }
    };
    const toggleMenu = () => {
        if (menuEl && menuEl.classList.contains('show')) hideMenu();
        else {
            showMenu();
            loadAndRender();
            if (menuEl) {
                window.requestAnimationFrame(() => {
                    window.requestAnimationFrame(() => {
                        applyDropdownViewportFallback(menuEl, bellEl);
                    });
                });
            }
        }
    };

    // intercept all clicks on the bell and manage toggle ourselves
    const handleBellTrigger = (event) => {
        event.preventDefault();
        // stop bootstrap-lite document listener from firing
        event.stopImmediatePropagation();
        toggleMenu();
    };

    const handleDropdownShown = () => {
        loadAndRender();
        if (!menuEl) return;
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                applyDropdownViewportFallback(menuEl, bellEl);
            });
        });
    };

    const handleDropdownHidden = () => {
        if (!menuEl) return;
        resetDropdownViewportPosition(menuEl);
    };

    const handleViewportChange = () => {
        if (!menuEl || !menuEl.classList.contains('show')) return;
        applyDropdownViewportFallback(menuEl, bellEl);
    };

    listEl.addEventListener('click', handleListClick, { signal: abortController.signal });
    bellEl.addEventListener('click', handleBellTrigger, { signal: abortController.signal });

    // remove any residual bootstrap listeners – not strictly needed since we
    // removed data-bs-toggle, but keeping for safety
    bellEl.removeEventListener('shown.bs.dropdown', handleDropdownShown);
    bellEl.removeEventListener('hidden.bs.dropdown', handleDropdownHidden);

    window.addEventListener('resize', handleViewportChange, { signal: abortController.signal });
    window.addEventListener('scroll', handleViewportChange, { passive: true, signal: abortController.signal });

    // close the menu when clicking outside or pressing escape
    const globalClickHandler = (e) => {
        if (!menuEl || !bellEl) return;
        if (menuEl.classList.contains('show')) {
            const target = e.target;
            if (target instanceof Element) {
                if (bellEl.contains(target) || menuEl.contains(target)) return;
            }
            hideMenu();
        }
    };
    const escapeHandler = (e) => {
        if (e.key === 'Escape') {
            hideMenu();
        }
    };
    document.addEventListener('click', globalClickHandler, { signal: abortController.signal });
    document.addEventListener('keydown', escapeHandler, { signal: abortController.signal });
    document.addEventListener(NAV_DROPDOWN_OPEN_EVENT, hideMenuForExternalOpen, { signal: abortController.signal });

    runWhenReady(() => {
        loadAndRender();
    });

    state.pollId = window.setInterval(loadAndRender, pollIntervalMs);
    bellState.set(key, state);
    return { active: true, destroy: state.destroy };
}
/* ===== End Inlined Notification Runtime ===== */

async function initializeNotificationRuntime() {
    try {
        await initNotificationCore({
            context: notificationContext,
            permissionScope: notificationContext,
            requestPermissionOnLoad: false,
            userId: getUserId(),
            permissionTitle: 'Enable Push Notifications',
            permissionMessage: 'Stay updated with instant alerts and important updates.',
            permissionEnableLabel: 'Enable',
            permissionLaterLabel: 'Later',
            showPermissionPopup: globalNotificationConfig.permissionPopupEnabled !== false
        });

        runWhenReady(() => {
            initNotificationBell({
                context: notificationContext,
                bellSelector: '#broxNotificationBell',
                badgeSelector: '#broxNotificationBadge',
                countSelector: '#broxNotificationCount',
                listSelector: '#broxNotificationsList'
            });
        });
    } catch (e) {
        // Notification system must never block page interaction.
    }
}

function isMessagingUnsupported() {
    return window.__fcmMessagingSupported === false;
}

function hideEnableNotificationsButton() {
    const enableBtn = document.getElementById('enableNotificationsBtn');
    if (enableBtn) enableBtn.style.display = 'none';
}

async function maybeSyncFcmToken() {
    if (isMessagingUnsupported()) return;
    try {
        if (typeof window.__requestFcmTokenSync === 'function') {
            await window.__requestFcmTokenSync();
        } else {
            window.__pendingFcmTokenSync = true;
        }
    } catch (e) {
        // Silent fail
    }
}

async function requestNotificationPermission() {
    if (isMessagingUnsupported()) {
        hideEnableNotificationsButton();
        return false;
    }

    if (!('Notification' in window)) {
        if (window.__FC_DEBUG) console.warn('[Notifications] Not supported in this browser');
        return false;
    }

    if (Notification.permission === 'granted') {
        await maybeSyncFcmToken();
        return true;
    }

    if (Notification.permission === 'denied') {
        return false;
    }

    const shown = showNotificationPermissionPopup({
        context: 'public',
        permissionScope: 'public',
        force: true,
        title: 'Enable Push Notifications',
        message: 'Stay updated with instant alerts and important updates.',
        enableLabel: 'Enable',
        laterLabel: 'Later',
        onGranted: async () => {
            await maybeSyncFcmToken();
        }
    });
    return !!shown;
}

function setupNotificationPermissionUI() {
    const enableBtn = document.getElementById('enableNotificationsBtn');

    if (isMessagingUnsupported()) {
        hideEnableNotificationsButton();
        return;
    }

    if (!('Notification' in window)) {
        if (enableBtn) enableBtn.style.display = 'none';
        return;
    }

    if (enableBtn) {
        enableBtn.addEventListener('click', async () => {
            await requestNotificationPermission();
        });
    }

    if (!isLoggedIn && window.__FC_DEBUG) {
        console.log('[Notifications] Checking notification status...');
    }

    if (Notification.permission === 'denied') {
        if (enableBtn) enableBtn.style.display = 'block';
    } else if (Notification.permission === 'granted') {
        if (enableBtn) enableBtn.style.display = 'none';
        maybeSyncFcmToken();
    } else if (Notification.permission === 'default') {
        if (enableBtn) enableBtn.style.display = 'none';
    }
}

if (!isAuthPageRoute) {
    initializeNotificationRuntime();
    runWhenReady(setupNotificationPermissionUI);

    window.addEventListener('fcm-support-resolved', (event) => {
        if (event?.detail?.supported === false) {
            hideEnableNotificationsButton();
        }
    });

    document.addEventListener('firebase-initialized', async () => {
        if (isMessagingUnsupported()) return;
        if (window.__FC_DEBUG) console.log('[Notifications] Firebase initialized, checking notification status...');
        if ('Notification' in window && Notification.permission === 'granted') {
            await maybeSyncFcmToken();
        }
    });
} else {
    window.__pendingFcmTokenSync = false;
    window.__requestFcmTokenSync = async () => false;
    window.__fcmTokenObtained = false;
}

/**
 * ============================================================
 * BROXBHAI NAVBAR - JavaScript Functions
 * ============================================================
 */





// Active link highlighting based on current URL
document.addEventListener('DOMContentLoaded', function () {
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
document.addEventListener('click', function (event) {
    const navbarCollapse = document.getElementById('broxMainNav');
    const navbarToggler = document.querySelector('.brox-mobile-toggle');

    if (navbarCollapse && navbarToggler) {
        const isClickInsideNav = navbarCollapse.contains(event.target);
        const isClickOnToggler = navbarToggler.contains(event.target);

        if (!isClickInsideNav && !isClickOnToggler && navbarCollapse.classList.contains('show')) {
            const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                toggle: true
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
            { detail: { kind, open: !!open, timestamp: Date.now() } }
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
        navbar.querySelectorAll('.dropdown-toggle[data-bs-toggle="dropdown"], [data-notification-bell][data-bs-toggle="dropdown"]')
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

        toggleEl.addEventListener('shown.bs.dropdown', handleShown);
        toggleEl.addEventListener('hidden.bs.dropdown', handleHidden);
    });

    window.addEventListener('resize', recalcVisibleDropdowns);
    window.addEventListener('scroll', recalcVisibleDropdowns, { passive: true });
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
                    block: 'start'
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
        broxNavbar.classList.toggle('is-scrolled', !!isScrolled);
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
            threshold: [0],
            rootMargin: '-8px 0px 0px 0px'
        });

        observer.observe(sentinel);
        return;
    }

    const onScroll = () => {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
        applyScrolledState(scrollTop > 8);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
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
        if (!open) {
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

        if (!isMobileViewport() && window.bootstrap?.Dropdown?.getOrCreateInstance) {
            const instance = window.bootstrap.Dropdown.getOrCreateInstance(toggleEl);
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

    toggleEl.addEventListener('shown.bs.dropdown', () => {
        if (!isMobileViewport()) {
            emitNavbarDropdownState('user', true);
        }
    });
    toggleEl.addEventListener('hidden.bs.dropdown', () => {
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

// Export functions for external use
let notificationSystemApiPromise = null;
const loadNotificationSystemApi = async () => {
    if (!notificationSystemApiPromise) {
        notificationSystemApiPromise = import(withAssetVersion('/assets/firebase/v2/dist/notification-system.js'))
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
    }
};

// ==================== CAROUSEL / COUNTER RUNTIME ====================
const CAROUSEL_SELECTOR = [
    '[id^="post_carousel_"]',
    '[id^="page_carousel_"]',
    '[id^="tag_carousel_"]',
    '[id^="category_carousel_"]',
    '[id^="related_post_carousel_"]',
    '[id^="related_page_carousel_"]',
    '[id^="related_mobile_carousel_"]'
].join(',');

const CAROUSEL_DEFAULT_OPTIONS = {
    interval: 5000,
    wrap: true,
    keyboard: true,
    pause: 'hover',
    touch: true
};

function initializeCarouselElement(el) {
    if (!el || el.dataset.carouselInitialized === 'true') return;
    if (!(window.bootstrap && typeof window.bootstrap.Carousel === 'function')) return;

    const options = {
        ...CAROUSEL_DEFAULT_OPTIONS,
        interval: el.dataset.interval ? Number(el.dataset.interval) : CAROUSEL_DEFAULT_OPTIONS.interval,
        pause: el.dataset.pause ?? CAROUSEL_DEFAULT_OPTIONS.pause,
        wrap: el.dataset.wrap !== 'false',
        keyboard: el.dataset.keyboard !== 'false',
        touch: el.dataset.touch !== 'false'
    };

    try {
        new window.bootstrap.Carousel(el, options);
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
        if (!(window.bootstrap && typeof window.bootstrap.Carousel === 'function')) {
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
            observer.observe(document.body, { childList: true, subtree: true });
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
    }, { threshold: 0.5 });

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
        Object.entries(stats).forEach(([key, value]) => {
            updateCounterValue(`[data-stat="${key}"]`, value);
        });
    }

    setInterval(async () => {
        const updatedStats = await fetchStatistics(endpoint);
        if (!updatedStats || typeof updatedStats !== 'object') return;

        Object.entries(updatedStats).forEach(([key, value]) => {
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
