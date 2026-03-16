/**
 * Admin Panel JavaScript
 * Handles sidebar toggling and responsive behaviors
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // Sidebar Toggle Logic
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggles = document.querySelectorAll('.sidebar-toggle');
    const sidebarMiniToggle = document.querySelector('.sidebar-mini-toggle');
    const MINI_STORAGE_KEY = 'admin.sidebar.mini';
    const MINI_EXPANDED_CLASS = 'admin-sidebar-mini-expanded';
    const MOBILE_OPEN_CLASS = 'admin-sidebar-open';
    const DESKTOP_WIDTH = 992;

    // Create overlay element
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    if (sidebar && sidebarToggles.length > 0) {
        const normalizePath = (value) => {
            const raw = String(value || '').trim();
            if (!raw) return '/';
            const stripped = raw.replace(/\/+$/, '');
            return stripped === '' ? '/' : stripped;
        };

        const cssEscape = (value) => {
            if (typeof window.CSS !== 'undefined' && typeof window.CSS.escape === 'function') {
                return window.CSS.escape(value);
            }
            return String(value).replace(/([ !"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
        };

        const syncSidebarActiveState = () => {
            const links = Array.from(sidebar.querySelectorAll('a.list-group-item-action[href]'));
            if (!links.length) return;

            const currentPath = normalizePath(window.location.pathname);
            const currentPathWithQuery = `${currentPath}${window.location.search || ''}`;
            let bestMatch = null;
            let bestScore = -1;

            links.forEach((link) => {
                const href = String(link.getAttribute('href') || '').trim();
                if (!href || href === '#' || href.startsWith('javascript:') || href.startsWith('#')) {
                    return;
                }

                let targetUrl = null;
                try {
                    targetUrl = new URL(href, window.location.origin);
                } catch (err) {
                    return;
                }

                const targetPath = normalizePath(targetUrl.pathname);
                const targetPathWithQuery = `${targetPath}${targetUrl.search || ''}`;
                let score = -1;

                if (currentPathWithQuery === targetPathWithQuery) {
                    score = 5000 + targetPathWithQuery.length;
                } else if (currentPath === targetPath) {
                    score = 4000 + targetPath.length;
                } else if (targetPath !== '/' && currentPath.startsWith(`${targetPath}/`)) {
                    score = 3000 + targetPath.length;
                }

                if (score > bestScore) {
                    bestScore = score;
                    bestMatch = link;
                }
            });

            if (!bestMatch) return;

            links.forEach((link) => {
                link.classList.remove('active');
                link.removeAttribute('aria-current');
            });

            const collapseToggles = Array.from(sidebar.querySelectorAll('a[data-bs-toggle="collapse"]'));
            collapseToggles.forEach((toggle) => {
                toggle.classList.remove('active');
            });

            bestMatch.classList.add('active');
            bestMatch.setAttribute('aria-current', 'page');

            let parentCollapse = bestMatch.closest('.collapse');
            while (parentCollapse && parentCollapse.id) {
                parentCollapse.classList.add('show');
                const selector = `a[data-bs-toggle="collapse"][href="#${cssEscape(parentCollapse.id)}"]`;
                const toggle = sidebar.querySelector(selector);
                if (toggle) {
                    toggle.classList.add('active');
                    toggle.setAttribute('aria-expanded', 'true');
                }
                parentCollapse = parentCollapse.parentElement?.closest('.collapse') || null;
            }
        };

        const syncMobileSidebarState = () => {
            const isMobile = window.innerWidth < DESKTOP_WIDTH;
            const isOpen = sidebar.classList.contains('show');
            document.body.classList.toggle(MOBILE_OPEN_CLASS, isMobile && isOpen);
        };

        const toggleSidebar = () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            syncMobileSidebarState();
        };

        const closeSidebar = () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            syncMobileSidebarState();
        };

        const readMiniState = () => {
            try {
                return localStorage.getItem(MINI_STORAGE_KEY) === '1';
            } catch (err) {
                return false;
            }
        };

        const writeMiniState = (enabled) => {
            try {
                localStorage.setItem(MINI_STORAGE_KEY, enabled ? '1' : '0');
            } catch (err) {
                // Silent fail if storage is unavailable
            }
        };

        const applyMiniSidebarState = (forceState = null) => {
            if (window.innerWidth < 992) {
                document.body.classList.remove('admin-sidebar-mini');
                document.body.classList.remove(MINI_EXPANDED_CLASS);
                document.body.classList.remove(MOBILE_OPEN_CLASS);
                if (sidebarMiniToggle) {
                    sidebarMiniToggle.setAttribute('aria-expanded', 'false');
                }
                return;
            }
            const shouldEnable = forceState !== null
                ? !!forceState
                : readMiniState();
            document.body.classList.toggle('admin-sidebar-mini', shouldEnable);
            if (!shouldEnable) {
                document.body.classList.remove(MINI_EXPANDED_CLASS);
            }
            if (sidebarMiniToggle) {
                sidebarMiniToggle.setAttribute('aria-pressed', shouldEnable ? 'true' : 'false');
                sidebarMiniToggle.setAttribute('aria-expanded', 'false');
            }
        };

        const isDesktop = () => window.innerWidth >= DESKTOP_WIDTH;
        const isMiniMode = () => document.body.classList.contains('admin-sidebar-mini');
        const setMiniExpanded = (expanded) => {
            const shouldExpand = !!expanded && isDesktop() && isMiniMode();
            document.body.classList.toggle(MINI_EXPANDED_CLASS, shouldExpand);
            if (sidebarMiniToggle) {
                sidebarMiniToggle.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
            }
        };

        applyMiniSidebarState();

        if (sidebarMiniToggle) {
            sidebarMiniToggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const nextState = !document.body.classList.contains('admin-sidebar-mini');
                document.body.classList.toggle('admin-sidebar-mini', nextState);
                document.body.classList.remove(MINI_EXPANDED_CLASS);
                writeMiniState(nextState);
                sidebarMiniToggle.setAttribute('aria-pressed', nextState ? 'true' : 'false');
                sidebarMiniToggle.setAttribute('aria-expanded', 'false');
            });
        }

        // Desktop mini-sidebar behavior:
        // Hover/focus on sidebar => expand to full
        // Click/focus outside sidebar => collapse back to mini
        sidebar.addEventListener('mouseenter', function () {
            if (isDesktop() && isMiniMode()) {
                setMiniExpanded(true);
            }
        });

        sidebar.addEventListener('focusin', function () {
            if (isDesktop() && isMiniMode()) {
                setMiniExpanded(true);
            }
        });

        sidebar.addEventListener('mouseleave', function () {
            if (isDesktop() && isMiniMode()) {
                setMiniExpanded(false);
            }
        });

        document.addEventListener('pointerdown', function (event) {
            if (!isDesktop() || !isMiniMode()) return;
            const target = event.target;
            if (sidebar.contains(target)) return;
            if (sidebarMiniToggle && sidebarMiniToggle.contains(target)) return;
            setMiniExpanded(false);
        });

        document.addEventListener('focusin', function (event) {
            if (!isDesktop() || !isMiniMode()) return;
            const target = event.target;
            if (sidebar.contains(target)) return;
            if (sidebarMiniToggle && sidebarMiniToggle.contains(target)) return;
            setMiniExpanded(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && window.innerWidth < DESKTOP_WIDTH && sidebar.classList.contains('show')) {
                closeSidebar();
                return;
            }
            if (!isDesktop() || !isMiniMode()) return;
            if (event.key === 'Escape') {
                setMiniExpanded(false);
            }
        });

        // Toggle sidebar on click
        sidebarToggles.forEach(function (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation(); // Prevent document click from immediately closing it
                toggleSidebar();
            });
        });

        // Close sidebar on button click (Mobile)
        const closeBtns = document.querySelectorAll('.sidebar-close');
        closeBtns.forEach(function (btn) {
            btn.addEventListener('click', closeSidebar);
        });

        // Close sidebar when clicking on overlay
        overlay.addEventListener('click', closeSidebar);

        // Close sidebar on outside click in mobile view.
        document.addEventListener('click', function (event) {
            if (window.innerWidth >= DESKTOP_WIDTH) return;
            if (!sidebar.classList.contains('show')) return;

            const target = event.target;
            if (!(target instanceof Element)) return;
            if (sidebar.contains(target)) return;
            if (overlay.contains(target)) return;
            if (Array.from(sidebarToggles).some((toggle) => toggle.contains(target))) return;

            closeSidebar();
        });

        // Handle window resize: remove .show class if switching to desktop view
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992 && sidebar.classList.contains('show')) {
                closeSidebar();
            }
            applyMiniSidebarState();
            syncMobileSidebarState();
        });

        // Close sidebar when a menu link is clicked (Mobile)
        const menuLinks = sidebar.querySelectorAll('a.list-group-item:not([data-bs-toggle])');
        menuLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) {
                    closeSidebar();
                }
            });
        });

        // Persist open submenu state across page loads
        try {
            const STORAGE_KEY = 'admin.sidebar.openSubmenus';
            const collapses = sidebar.querySelectorAll('.collapse[id]');
            const openSet = new Set(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'));

            // Restore saved open submenus
            openSet.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    try {
                        const bs = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
                        bs.show();
                    } catch (e) {
                        // ignore if bootstrap not available yet
                    }
                }
            });

            // Track show/hide events
            collapses.forEach(c => {
                c.addEventListener('shown.bs.collapse', function () {
                    openSet.add(c.id);
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(openSet)));
                });
                c.addEventListener('hidden.bs.collapse', function () {
                    openSet.delete(c.id);
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(openSet)));
                });
            });
        } catch (err) {
            // localStorage or bootstrap events not available; fail silently
        }

        // Enforce single expanded submenu at a time.
        if (typeof bootstrap !== 'undefined') {
            const STORAGE_KEY = 'admin.sidebar.openSubmenus';
            const collapses = Array.from(sidebar.querySelectorAll('.collapse[id]'));
            const persistSingleOpen = (id) => {
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(id ? [id] : []));
                } catch (err) {
                    // Ignore storage failures.
                }
            };
            const hideCollapse = (el) => {
                if (!el || !el.classList.contains('show')) return;
                try {
                    bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide();
                } catch (err) {
                    el.classList.remove('show');
                }
            };

            const opened = collapses.filter((el) => el.classList.contains('show'));
            if (opened.length <= 1) {
                persistSingleOpen(opened[0]?.id || null);
            } else {
                opened.slice(1).forEach(hideCollapse);
                persistSingleOpen(opened[0].id);
            }

            collapses.forEach((current) => {
                current.addEventListener('show.bs.collapse', function () {
                    collapses.forEach((other) => {
                        if (other !== current) hideCollapse(other);
                    });
                });

                current.addEventListener('shown.bs.collapse', function () {
                    persistSingleOpen(current.id);
                });

                current.addEventListener('hidden.bs.collapse', function () {
                    const active = collapses.find((el) => el.classList.contains('show'));
                    persistSingleOpen(active ? active.id : null);
                });
            });
        }

        syncSidebarActiveState();
        syncMobileSidebarState();
    }
});

const runWhenReady = (fn) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
        fn();
    }
};

const getUserId = () => document.querySelector('meta[name="user-id"]')?.content || null;
const ADMIN_NAV_DROPDOWN_OPEN_EVENT = 'brox:navbar-dropdown-open';

const adminNotificationCoreState = new Map();
const adminNotificationBellState = new Map();

function adminEmitFcmSupportResolved(supported, context = 'admin') {
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

function adminEmitNavbarDropdownState(kind, open) {
    try {
        document.dispatchEvent(new CustomEvent(
            open ? ADMIN_NAV_DROPDOWN_OPEN_EVENT : 'brox:navbar-dropdown-close',
            { detail: { kind, open: !!open, timestamp: Date.now() } }
        ));
    } catch (err) {
        // Ignore dispatch failures.
    }
}

function adminGetCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function adminEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char] || char));
}

function adminToSafeUrl(url) {
    const value = String(url || '').trim();
    if (!value) return '#';
    if (value.startsWith('/')) return value;
    if (/^https?:\/\//i.test(value)) return value;
    return '#';
}

function adminFormatTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString();
}

function adminSetListEmpty(listEl, message) {
    if (!listEl) return;
    listEl.innerHTML = `
        <div class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-4"></i>
            <p class="mb-0 mt-2 small">${adminEscapeHtml(message)}</p>
        </div>
    `;
}

function adminUpdateBadge(badgeEl, countEl, unreadCount) {
    const safeCount = Number.isFinite(unreadCount) ? Math.max(0, unreadCount) : 0;
    if (countEl) {
        countEl.textContent = String(safeCount);
    }
    if (badgeEl) {
        badgeEl.classList.toggle('d-none', safeCount <= 0);
    }
}

function adminRenderNotifications(listEl, notifications) {
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

async function adminFetchNotifications(limit = 10) {
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

async function adminMarkNotificationRead(notificationId) {
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

function adminGetBellKey(options) {
    return [
        options.context || 'admin',
        options.bellSelector || '',
        options.listSelector || ''
    ].join('|');
}

function adminFindElement(selector, attrName) {
    if (selector) {
        const selected = document.querySelector(selector);
        if (selected) return selected;
    }
    if (!attrName) return null;
    return document.querySelector(`[${attrName}]`);
}

function adminGetDropdownMenuElement(bellEl, listEl) {
    const wrapper = bellEl?.closest('[data-notification-menu]');
    if (wrapper) {
        const menu = wrapper.querySelector('[data-notification-dropdown]');
        if (menu) return menu;
    }
    return listEl?.closest('.admin-notification-dropdown, .brox-notification-dropdown') || null;
}

async function adminInitNotificationCore(options = {}) {
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

function adminInitNotificationBell(options = {}) {
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

async function initAdminNotificationRuntime() {
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

function initAdminUserDropdownSync() {
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

async function initAdminDebugUtils() {
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

async function initAdminUnifiedLogout() {
    try {
        const logoutRuntime = await import('./shared/logout-runtime.js');
        logoutRuntime.initUnifiedLogout({ context: 'admin' });
    } catch (err) {
        // Silent fail
    }
}

initAdminNotificationRuntime();
runWhenReady(initAdminUserDropdownSync);
initAdminDebugUtils();
initAdminUnifiedLogout();

runWhenReady(() => {
    document.body.classList.add('loaded');
});

// ==================== ADMIN INLINE SCRIPTS (MIGRATED) ====================
(function () {
    'use strict';

    const onReady = (typeof runWhenReady === 'function')
        ? runWhenReady
        : (fn) => {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fn, { once: true });
            } else {
                fn();
            }
        };

    const byId = (id) => document.getElementById(id);
    const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const getAdminDir = () => document.body?.dataset?.adminDir || '/admin';
    const TAG_COMBOBOX_DEFAULT_OPTIONS = {
        allowCreate: true,
        searchMode: 'client',
        sourceEndpoint: '/api/tags/list-json',
        createEndpoint: '/api/tags/create',
        maxResults: 50
    };
    const CATEGORY_COMBOBOX_DEFAULT_OPTIONS = {
        allowCreate: true,
        searchMode: 'client',
        sourceEndpoint: '/api/categories/list-json',
        createEndpoint: '/api/categories/create',
        maxResults: 50
    };
    const parseJson = (value, fallback) => {
        if (!value) return fallback;
        try { return JSON.parse(value); } catch (e) { return fallback; }
    };
    const escapeHtml = (text) => {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
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
                    info: 'info'
                };
                const cls = map[normalized] || 'info';
                toast.className = `alert alert-${cls} alert-dismissible show position-fixed top-0 end-0 m-3`;
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    ${String(message || '')}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), Number(duration) || 5000);
            };
        }


        if (typeof window.transliterateAndGenerateSlug !== 'function') {
            const bnDigitMap = {
                '\u09E6': '0', '\u09E7': '1', '\u09E8': '2', '\u09E9': '3', '\u09EA': '4',
                '\u09EB': '5', '\u09EC': '6', '\u09ED': '7', '\u09EE': '8', '\u09EF': '9'
            };
            const bnBasicMap = {
                '\u0985': 'o', '\u0986': 'a', '\u0987': 'i', '\u0988': 'i', '\u0989': 'u', '\u098A': 'u',
                '\u098F': 'e', '\u0990': 'oi', '\u0993': 'o', '\u0994': 'ou',
                '\u0995': 'k', '\u0996': 'kh', '\u0997': 'g', '\u0998': 'gh', '\u0999': 'ng',
                '\u099A': 'ch', '\u099B': 'chh', '\u099C': 'j', '\u099D': 'jh', '\u099E': 'n',
                '\u099F': 't', '\u09A0': 'th', '\u09A1': 'd', '\u09A2': 'dh', '\u09A3': 'n',
                '\u09A4': 't', '\u09A5': 'th', '\u09A6': 'd', '\u09A7': 'dh', '\u09A8': 'n',
                '\u09AA': 'p', '\u09AB': 'ph', '\u09AC': 'b', '\u09AD': 'bh', '\u09AE': 'm',
                '\u09AF': 'y', '\u09B0': 'r', '\u09B2': 'l', '\u09B6': 'sh', '\u09B7': 'sh', '\u09B8': 's', '\u09B9': 'h',
                '\u09BE': 'a', '\u09BF': 'i', '\u09C0': 'i', '\u09C1': 'u', '\u09C2': 'u', '\u09C7': 'e', '\u09C8': 'oi', '\u09CB': 'o', '\u09CC': 'ou',
                '\u0982': 'ng', '\u0983': 'h', '\u0981': 'n'
            };
            const transliterateBn = (text) => String(text || '').split('').map(ch => bnDigitMap[ch] ?? bnBasicMap[ch] ?? ch).join('');

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
                    feedback.classList.remove('text-success', 'text-danger', 'text-muted');
                    if (state === 'ok') feedback.classList.add('text-success');
                    else if (state === 'bad') feedback.classList.add('text-danger');
                    else feedback.classList.add('text-muted');
                };

                const checkSlug = async (slug) => {
                    if (!slug) {
                        setFeedback('');
                        return;
                    }
                    try {
                        const q = new URLSearchParams({ slug: slug });
                        if (excludeId) q.set('exclude_id', String(excludeId));
                        const res = await fetch(`/api/services/check-slug?${q.toString()}`);
                        const data = await res.json();
                        if (data?.success && data?.available) {
                            setFeedback(data.message || 'Slug available', 'ok');
                        } else {
                            setFeedback(data?.message || 'Slug unavailable', 'bad');
                        }
                    } catch (e) {
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
                    }
                };
            };
        }

        window.adminContent = window.adminContent || {};

        const normalizeNumericIds = (value) => {
            if (!Array.isArray(value)) return [];
            return value
                .map((item) => {
                    if (typeof item === 'number' || typeof item === 'string') return String(item).trim();
                    if (item && typeof item === 'object' && item.id !== undefined) return String(item.id).trim();
                    return '';
                })
                .filter((id) => /^\d+$/.test(id));
        };

        if (typeof window.adminContent.fetchTags !== 'function') {
            window.adminContent.fetchTags = function (selectedIds = [], selector = '#tags') {
                return loadAdminModule('tagCombobox')
                    .then((tagCombobox) => tagCombobox.loadTagOptions(selector, normalizeNumericIds(selectedIds), TAG_COMBOBOX_DEFAULT_OPTIONS))
                    .catch((error) => {
                        logModuleError('tagCombobox', error);
                        window.showMessage?.('Failed to load tags', 'danger', 5000);
                    });
            };
        }

        if (typeof window.adminContent.createNewTag !== 'function') {
            window.adminContent.createNewTag = function (data, selector = '#tags') {
                const name = typeof data === 'string' ? data : (data?.text || data?.name || '');
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
                    .catch((error) => {
                        logModuleError('tagCombobox', error);
                        return null;
                    });
            };
        }

        if (typeof window.adminContent.fetchCategories !== 'function') {
            window.adminContent.fetchCategories = function (selectedIds = [], selector = '#category_ids_select') {
                return loadAdminModule('categoryCombobox')
                    .then((categoryCombobox) => categoryCombobox.loadCategoryOptions(selector, normalizeNumericIds(selectedIds), CATEGORY_COMBOBOX_DEFAULT_OPTIONS))
                    .catch((error) => {
                        logModuleError('categoryCombobox', error);
                        window.showMessage?.('Failed to load categories', 'danger', 5000);
                    });
            };
        }

        if (typeof window.adminContent.createNewCategory !== 'function') {
            window.adminContent.createNewCategory = function (data, selector = '#category_ids_select') {
                const name = typeof data === 'string' ? data : (data?.text || data?.name || '');
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
                    .catch((error) => {
                        logModuleError('categoryCombobox', error);
                        return null;
                    });
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
        security2fa: () => import('./admin/modules/security-2fa.js')
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
        const flashMsg = byId('flash-message');
        if (!flashMsg || typeof bootstrap === 'undefined') return;
        setTimeout(() => {
            try { new bootstrap.Alert(flashMsg).close(); } catch (e) { }
        }, 5000);
    }

    function initPasswordModals() {
        const setPasswordForm = byId('setPasswordForm');
        const changePasswordForm = byId('changePasswordForm');
        if (!setPasswordForm && !changePasswordForm) return;

        function validatePasswordStrength(inputId) {
            const input = byId(inputId);
            if (!input) return;
            const password = input.value || '';
            const isChangeForm = inputId.includes('change');
            const prefix = isChangeForm ? 'changePwd' : 'pwd';

            const lengthCheck = byId(prefix + 'Length');
            const upperCheck = byId(prefix + 'Upper');
            const lowerCheck = byId(prefix + 'Lower');
            const numberCheck = byId(prefix + 'Number');
            const specialCheck = byId(prefix + 'Special');

            if (lengthCheck) lengthCheck.classList.toggle('valid', password.length >= 8);
            if (upperCheck) upperCheck.classList.toggle('valid', /[A-Z]/.test(password));
            if (lowerCheck) lowerCheck.classList.toggle('valid', /[a-z]/.test(password));
            if (numberCheck) numberCheck.classList.toggle('valid', /[0-9]/.test(password));
            if (specialCheck) specialCheck.classList.toggle('valid', /[!@#$%^&*()_+\-=\[\]{};:'"",.<>?\/\\]/.test(password));
        }

        const newPasswordInput = byId('newPassword');
        const changePasswordInput = byId('changeNewPassword');
        if (newPasswordInput) newPasswordInput.addEventListener('input', () => validatePasswordStrength('newPassword'));
        if (changePasswordInput) changePasswordInput.addEventListener('input', () => validatePasswordStrength('changeNewPassword'));

        function showAlert(alertBox, message, type = 'danger') {
            if (!alertBox) return;
            alertBox.className = `alert alert-${type} alert-dismissible show`;
            alertBox.textContent = message;
        }

        function setPassword() {
            const form = byId('setPasswordForm');
            if (!form) return;
            const password = form.password?.value || '';
            const confirmPassword = form.password_confirm?.value || '';
            const csrfToken = form.csrf_token?.value || getCsrfToken();
            const alertBox = byId('setPasswordAlert');

            if (!password || !confirmPassword) {
                showAlert(alertBox, 'All fields are required', 'danger');
                return;
            }
            if (password !== confirmPassword) {
                showAlert(alertBox, 'Passwords do not match', 'danger');
                return;
            }
            if (password.length < 8) {
                showAlert(alertBox, 'Password must be at least 8 characters', 'danger');
                return;
            }

            fetch('/api/oauth/set-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    password: password,
                    password_confirm: confirmPassword,
                    csrf_token: csrfToken
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(alertBox, data.message, 'success');
                        setTimeout(() => {
                            form.reset();
                            bootstrap.Modal.getInstance(byId('setPasswordModal'))?.hide();
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert(alertBox, data.error || 'Failed to set password', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert(alertBox, 'An error occurred. Please try again.', 'danger');
                });
        }

        function changePassword() {
            const form = byId('changePasswordForm');
            if (!form) return;
            const currentPassword = form.current_password?.value || '';
            const newPassword = form.password?.value || '';
            const confirmPassword = form.password_confirm?.value || '';
            const csrfToken = form.csrf_token?.value || getCsrfToken();
            const alertBox = byId('changePasswordAlert');

            if (!currentPassword || !newPassword || !confirmPassword) {
                showAlert(alertBox, 'All fields are required', 'danger');
                return;
            }
            if (newPassword !== confirmPassword) {
                showAlert(alertBox, 'Passwords do not match', 'danger');
                return;
            }

            fetch('/user/change-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    current_password: currentPassword,
                    password: newPassword,
                    password_confirm: confirmPassword,
                    csrf_token: csrfToken
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(alertBox, data.message, 'success');
                        setTimeout(() => {
                            form.reset();
                            bootstrap.Modal.getInstance(byId('changePasswordModal'))?.hide();
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert(alertBox, data.error || 'Failed to change password', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert(alertBox, 'An error occurred. Please try again.', 'danger');
                });
        }

        byId('setPasswordBtn')?.addEventListener('click', setPassword);
        byId('changePasswordBtn')?.addEventListener('click', changePassword);
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
                    alertsContainerId: 'alert-container'
                });
            })
            .catch((error) => {
                console.error('Failed to initialize account settings helper:', error);
            });
    }

    function initActivityLog() {
        const tbody = byId('log-table-body');
        if (!tbody) return;

        let currentPage = 1;
        let perPage = 50;
        let currentSort = { by: 'created_at', order: 'DESC' };
        let totalRecords = 0;
        let totalPages = 1;
        let activityEnabled = tbody.dataset.activityEnabled === 'true';

        function parseBrowserInfo(userAgent) {
            if (!userAgent) return 'Unknown';
            let browser = 'Unknown';
            let os = 'Unknown';
            let version = '';
            if (userAgent.includes('Edge')) { browser = 'Edge'; const match = userAgent.match(/Edge\/(\d+)/); if (match) version = match[1]; }
            else if (userAgent.includes('Chrome')) { browser = 'Chrome'; const match = userAgent.match(/Chrome\/(\d+)/); if (match) version = match[1]; }
            else if (userAgent.includes('Safari')) { browser = 'Safari'; const match = userAgent.match(/Version\/(\d+)/); if (match) version = match[1]; }
            else if (userAgent.includes('Firefox')) { browser = 'Firefox'; const match = userAgent.match(/Firefox\/(\d+)/); if (match) version = match[1]; }
            else if (userAgent.includes('Opera')) { browser = 'Opera'; const match = userAgent.match(/Version\/(\d+)/); if (match) version = match[1]; }
            else if (userAgent.includes('MSIE') || userAgent.includes('Trident')) { browser = 'Internet Explorer'; const match = userAgent.match(/MSIE (\d+)/) || userAgent.match(/rv:(\d+)/); if (match) version = match[1]; }

            if (userAgent.includes('Windows')) {
                if (userAgent.includes('Windows NT 10.0')) os = 'Windows 10/11';
                else if (userAgent.includes('Windows NT 6.3')) os = 'Windows 8.1';
                else if (userAgent.includes('Windows NT 6.2')) os = 'Windows 8';
                else if (userAgent.includes('Windows NT 6.1')) os = 'Windows 7';
                else os = 'Windows';
            } else if (userAgent.includes('Mac OS X')) os = 'macOS';
            else if (userAgent.includes('Linux')) os = 'Linux';
            else if (userAgent.includes('iPhone') || userAgent.includes('iPad')) os = 'iOS';
            else if (userAgent.includes('Android')) os = 'Android';

            let info = browser;
            if (version) info += ` ${version}`;
            info += ` (${os})`;
            return info;
        }

        function renderLog(log) {
            const time = new Date(log.created_at).toLocaleString();
            const statusClass = log.status === 'success' ? 'bg-success' : 'bg-danger';
            const username = log.username || ('#' + (log.user_id || '0'));
            let browserInfo = 'Unknown';
            if (log.details && log.details._browser) browserInfo = log.details._browser;
            else if (log.user_agent) browserInfo = parseBrowserInfo(log.user_agent);

            const row = document.createElement('tr');
            row.dataset.id = log.id;
            row.innerHTML = `
                <td class="log-time">${time}</td>
                <td class="log-user"><i class="bi bi-person-circle me-2"></i>${username}</td>
                <td class="log-action">${log.action}</td>
                <td><span class="resource-type">${log.resource_type || 'N/A'} <strong>#${log.resource_id || 'N/A'}</strong></span></td>
                <td><span class="badge ${statusClass}">${log.status}</span></td>
                <td class="ip-badge" title="${escapeHtml(log.user_agent || 'N/A')}">
                    <div style="font-size: 0.8rem; color: #333; font-weight: 500;">${log.ip_address || 'N/A'}</div>
                    <div style="font-size: 0.75rem; color: #999;">${browserInfo}</div>
                </td>
            `;
            row.addEventListener('click', (e) => {
                if (e.target.tagName !== 'A') showLogDetailsModal(log);
            });
            return row;
        }

        function showLogDetailsModal(log) {
            const time = new Date(log.created_at).toLocaleString();
            const statusClass = log.status === 'success' ? 'bg-success' : 'bg-danger';
            const username = log.username || ('#' + (log.user_id || '0'));
            byId('modalLogId').textContent = log.id;
            byId('modalLogTime').textContent = time;
            byId('modalLogUser').textContent = username;
            byId('modalLogRole').textContent = log.role;
            byId('modalLogAction').textContent = log.action;
            byId('modalLogResource').textContent = (log.resource_type || 'N/A') + ' #' + (log.resource_id || 'N/A');
            byId('modalLogStatus').innerHTML = `<span class="badge ${statusClass}">${log.status}</span>`;
            byId('modalLogIp').textContent = log.ip_address || 'N/A';
            byId('modalLogAgent').textContent = log.user_agent || 'N/A';

            let browserInfo = 'Not available';
            if (log.details && log.details._browser) browserInfo = log.details._browser;
            else if (log.user_agent) browserInfo = parseBrowserInfo(log.user_agent);
            byId('modalLogBrowser').innerHTML = `<span class="badge bg-info">${escapeHtml(browserInfo)}</span>`;

            const detailsJson = log.details ? JSON.stringify(log.details, null, 2) : 'No additional details';
            byId('modalLogDetails').textContent = detailsJson;
            window.currentLogJson = JSON.stringify(log, null, 2);
            const modal = new bootstrap.Modal(byId('logDetailsModal'));
            modal.show();
        }

        async function fetchLogs(page = 1) {
            const q = byId('searchBox')?.value?.trim() || '';
            const status = byId('filterStatus')?.value || '';
            const user = byId('filterUser')?.value?.trim() || '';
            const resource = byId('filterResource')?.value?.trim() || '';

            const params = new URLSearchParams({ page, perPage, sort_by: currentSort.by, sort_order: currentSort.order });
            if (q) params.set('q', q);
            if (status) params.set('status', status);
            if (user) params.set('user_id', user);
            if (resource) params.set('resource_type', resource);

            try {
                const res = await fetch(`/api/log-activity?${params.toString()}`);
                const data = await res.json();

                tbody.innerHTML = '';
                if (data.logs && data.logs.length) {
                    data.logs.forEach(log => tbody.appendChild(renderLog(log)));
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><div><i class="bi bi-inbox"></i><p>No logs found</p></div></td></tr>';
                }

                currentPage = data.page;
                totalRecords = data.total;
                totalPages = data.totalPages;

                const start = (currentPage - 1) * perPage + 1;
                const end = Math.min(currentPage * perPage, totalRecords);
                byId('startRecord').textContent = totalRecords > 0 ? start : 0;
                byId('endRecord').textContent = end;
                byId('totalRecord').textContent = totalRecords;
                byId('pageIndicator').textContent = `Page ${currentPage} of ${totalPages}`;

                byId('prevPage').disabled = currentPage <= 1;
                byId('nextPage').disabled = currentPage >= totalPages;
            } catch (err) {
                console.error('Error fetching logs', err);
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="bi bi-exclamation-circle"></i> Error loading logs</td></tr>';
            }
        }

        document.querySelectorAll('thead th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const sortBy = th.dataset.sort;
                const sortSpan = byId(`sort-${sortBy}`);
                document.querySelectorAll('.sort-indicator').forEach(s => {
                    s.textContent = '';
                    s.classList.remove('active');
                });

                if (currentSort.by === sortBy) {
                    currentSort.order = currentSort.order === 'DESC' ? 'ASC' : 'DESC';
                } else {
                    currentSort.by = sortBy;
                    currentSort.order = 'DESC';
                }

                if (sortSpan) {
                    sortSpan.classList.add('active');
                    sortSpan.textContent = currentSort.order === 'DESC' ? 'v' : '^';
                }

                currentPage = 1;
                fetchLogs();
            });
        });

        byId('prevPage')?.addEventListener('click', () => fetchLogs(currentPage - 1));
        byId('nextPage')?.addEventListener('click', () => fetchLogs(currentPage + 1));
        byId('btnRefresh')?.addEventListener('click', () => {
            currentPage = 1;
            fetchLogs(1);
        });

        ['searchBox', 'filterStatus', 'filterUser', 'filterResource'].forEach(id => {
            byId(id)?.addEventListener('change', () => {
                currentPage = 1;
                fetchLogs(1);
            });
        });

        byId('perPageSelect')?.addEventListener('change', (e) => {
            perPage = parseInt(e.target.value, 10);
            currentPage = 1;
            fetchLogs(1);
        });

        byId('exportCsv')?.addEventListener('click', (e) => {
            e.preventDefault();
            exportLogs('csv');
        });
        byId('exportJson')?.addEventListener('click', (e) => {
            e.preventDefault();
            exportLogs('json');
        });

        function exportLogs(format) {
            const q = byId('searchBox')?.value?.trim() || '';
            const status = byId('filterStatus')?.value || '';
            const user = byId('filterUser')?.value?.trim() || '';
            const resource = byId('filterResource')?.value?.trim() || '';

            const params = new URLSearchParams({ format });
            if (q) params.set('q', q);
            if (status) params.set('status', status);
            if (user) params.set('user_id', user);
            if (resource) params.set('resource_type', resource);

            window.location.href = `/api/log-activity/export?${params.toString()}`;
        }

        byId('modalCopyJson')?.addEventListener('click', () => {
            if (!window.currentLogJson) return;
            navigator.clipboard.writeText(window.currentLogJson).then(() => {
                const btn = byId('modalCopyJson');
                if (!btn) return;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check me-2"></i>Copied!';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-success');
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-primary');
                }, 2000);
            }).catch(() => {
                alert('Failed to copy to clipboard');
            });
        });

        byId('toggleActivity')?.addEventListener('click', async () => {
            const target = !activityEnabled;
            try {
                const res = await fetch('/api/log-activity/toggle', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                    body: JSON.stringify({ enabled: target })
                });
                const data = await res.json();
                if (data.success) {
                    activityEnabled = !!data.enabled;
                    byId('toggleActivityLabel').textContent = activityEnabled ? 'Activity: ON' : 'Activity: OFF';
                    alert(data.message || (activityEnabled ? 'Activity logging enabled' : 'Activity logging disabled'));
                } else {
                    alert(data.message || 'Failed to update activity logging');
                }
            } catch (e) {
                alert('Error updating activity logging');
            }
        });

        fetchLogs(1);
    }

    function initDashboardData() {
        const dataEl = byId('admin-dashboard-data');
        if (!dataEl) return;
        window.BLOG_DASHBOARD = {
            trendLabels: parseJson(dataEl.dataset.trendLabels, []),
            trendSeries: parseJson(dataEl.dataset.trendSeries, [])
        };
    }

    function initContentFormData() {
        const dataEl = byId('admin-content-data');
        if (!dataEl) return;
        const categoryIds = parseJson(dataEl.dataset.categoryIds, []);
        const tagIds = parseJson(dataEl.dataset.tagIds, []);
        const contentType = dataEl.dataset.contentType || '';

        window.itemCategoryIds = categoryIds;
        window.itemTagIds = tagIds;
        if (contentType === 'posts') {
            window.postCategoryIds = categoryIds;
            window.postTagIds = tagIds;
        } else if (contentType === 'pages') {
            window.pageCategoryIds = categoryIds;
            window.pageTagIds = tagIds;
        }

        if (window.adminContent?.fetchCategories) {
            window.adminContent.fetchCategories(categoryIds, '#category_ids_select');
        }
        if (window.adminContent?.initializeCategoriesSelect) {
            window.adminContent.initializeCategoriesSelect('#category_ids_select');
        }
        if (window.adminContent?.fetchTags) {
            window.adminContent.fetchTags(tagIds, '#tags');
        }
        if (window.adminContent?.initializeTagsSelect) {
            window.adminContent.initializeTagsSelect('#tags');
        }
    }

    function initEmailTemplatesEdit() {
        const form = byId('emailTemplateForm');
        const previewBtn = byId('previewBtn');
        if (!form || !previewBtn) return;
        const templateId = form.dataset.templateId;
        const adminDir = getAdminDir();

        function previewTemplate() {
            const formData = new FormData(form);
            const originalHTML = previewBtn.innerHTML;
            previewBtn.disabled = true;
            previewBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Loading...';

            fetch(`${adminDir}/email-templates/${templateId}/preview`, {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        byId('previewSubject').innerHTML = '<strong>' + escapeHtml(data.subject) + '</strong>';
                        byId('previewBody').innerHTML = data.body;
                        showToast('Preview updated successfully', 'success');
                    } else {
                        showToast('Preview failed: ' + data.message, 'danger');
                    }
                })
                .catch(err => {
                    showToast('Error: ' + err, 'danger');
                    console.error('Preview error:', err);
                })
                .finally(() => {
                    previewBtn.disabled = false;
                    previewBtn.innerHTML = originalHTML;
                });
        }

        previewBtn.addEventListener('click', previewTemplate);
    }

    function initEmailTemplatesList() {
        if (!document.querySelector('[data-email-template-list]')) return;
        window.deleteTemplate = function (id, name) {
            if (!confirm(`Are you sure you want to delete the email template \"${name}\"? This action cannot be undone.`)) {
                return;
            }
            fetch(`${getAdminDir()}/email-templates/${id}/delete`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message, 'danger');
                    }
                })
                .catch(err => showToast('Error: ' + err, 'danger'));
        };
    }

    function initMediaDetail() {
        if (!document.querySelector('[data-media-detail]')) return;
        window.copyToClipboard = function (text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('URL copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        };
    }

    function initMediaUpload() {
        loadAdminModule('mediaUpload')
            .then((mediaUpload) => mediaUpload.initMediaUpload({ byId }))
            .catch((error) => logModuleError('mediaUpload', error));
    }

    function initDeleteMobile() {
        loadAdminModule('mobile')
            .then((mobile) => {
                mobile.initDeleteMobile({
                    byId,
                    notify: (message, type) => window.showMessage?.(message, type)
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
                    notify: (message, type) => window.showMessage?.(message, type)
                });
            })
            .catch((error) => logModuleError('mobile', error));
    }

    function initApplicationsView() {
        if (!byId('approveModal') || !byId('rejectModal')) return;
        let appIdToAction = null;
        const csrf = getCsrfToken();

        function submitAction(url, body) {
            return fetch(url, { method: 'POST', body }).then(r => r.json());
        }

        window.approveApplication = function (appId) {
            appIdToAction = appId;
            new bootstrap.Modal(byId('approveModal')).show();
        };

        window.confirmApprove = function () {
            const notes = byId('approveNotes')?.value || '';
            const formData = new FormData();
            formData.append('csrf_token', csrf);
            if (notes) formData.append('notes', notes);
            submitAction(`/admin/applications/${appIdToAction}/approve`, formData)
                .then(data => {
                    showToast('Success', data.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                })
                .catch(() => showToast('Error', 'Failed to approve application', 'error'));
        };

        window.rejectApplication = function (appId) {
            appIdToAction = appId;
            new bootstrap.Modal(byId('rejectModal')).show();
        };

        window.confirmReject = function () {
            const reason = byId('rejectReason')?.value || '';
            if (!reason) {
                showToast('Error', 'Rejection reason is required', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('csrf_token', csrf);
            formData.append('reason', reason);
            submitAction(`/admin/applications/${appIdToAction}/reject`, formData)
                .then(data => {
                    showToast('Success', data.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                })
                .catch(() => showToast('Error', 'Failed to reject application', 'error'));
        };

        window.markProcessing = function (appId) {
            appIdToAction = appId;
            new bootstrap.Modal(byId('processingModal')).show();
        };

        window.confirmProcessing = function () {
            const notes = byId('processingNotes')?.value || '';
            const formData = new FormData();
            formData.append('csrf_token', csrf);
            if (notes) formData.append('notes', notes);
            submitAction(`/admin/applications/${appIdToAction}/processing`, formData)
                .then(data => {
                    showToast('Success', data.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                })
                .catch(() => showToast('Error', 'Failed to update application', 'error'));
        };

        window.addNote = function (appId) {
            const note = byId('noteText')?.value || '';
            if (!note) {
                showToast('Error', 'Note cannot be empty', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('csrf_token', csrf);
            formData.append('note', note);
            submitAction(`/admin/applications/${appId}/note`, formData)
                .then(data => {
                    showToast('Success', data.message, 'success');
                    byId('noteText').value = '';
                    setTimeout(() => window.location.reload(), 1500);
                })
                .catch(() => showToast('Error', 'Failed to add note', 'error'));
        };

        window.activateService = function (appId) {
            if (!confirm('Activate this service for the user?')) return;
            const formData = new FormData();
            formData.append('csrf_token', csrf);
            submitAction(`/admin/applications/${appId}/activate`, formData)
                .then(data => {
                    showToast('Success', data.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                })
                .catch(() => showToast('Error', 'Failed to activate service', 'error'));
        };

        window.revertStatus = function (appId) {
            if (!confirm('Revert application status to pending?')) return;
            fetch(`/api/admin/applications/${appId}/status`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ status: 'pending' })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success', data.message || 'Status reverted', 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast('Error', data.message || 'Failed to revert status', 'error');
                    }
                })
                .catch(() => showToast('Error', 'Failed to revert status', 'error'));
        };
    }

    function initSettingsPage() {
        const settingsRoot = document.querySelector('.settings-page');
        if (!settingsRoot) return;
        const adminPath = getAdminDir();
        const submitBtn = settingsRoot.querySelector('button[type="submit"]');

        document.querySelectorAll('.form-control, .form-select, .form-check-input').forEach(input => {
            input.addEventListener('change', function () {
                if (submitBtn) submitBtn.classList.add('btn-warning');
            });
        });

        const logoUpload = byId('siteLogoUpload');
        const logoPreview = byId('logoPreview');
        if (logoUpload && logoPreview) {
            logoUpload.addEventListener('change', function (event) {
                const file = event.target.files?.[0];
                if (!file) return;
                const url = URL.createObjectURL(file);
                logoPreview.src = url;
                logoPreview.classList.remove('d-none');
                logoPreview.onload = () => URL.revokeObjectURL(url);
            });
        }

        const faviconUpload = byId('faviconUpload');
        const faviconPreview = byId('faviconPreview');
        if (faviconUpload && faviconPreview) {
            faviconUpload.addEventListener('change', function (event) {
                const file = event.target.files?.[0];
                if (!file) return;
                const url = URL.createObjectURL(file);
                faviconPreview.src = url;
                faviconPreview.onload = () => URL.revokeObjectURL(url);
            });
        }

        byId('removeLogoBtn')?.addEventListener('click', function () {
            if (!confirm('Remove site logo? This will clear the saved logo.')) return;
            const hidden = byId('remove_site_logo');
            if (hidden) hidden.value = '1';
            settingsRoot.querySelector('form.needs-validation')?.submit();
        });

        const testEmailBtn = byId('sendTestEmailBtn');
        if (testEmailBtn) {
            testEmailBtn.addEventListener('click', async function () {
                const recipient = byId('testEmailRecipient')?.value || '';
                const action = `${adminPath}/app-settings/send-test-email-ajax`;

                testEmailBtn.disabled = true;
                const originalText = testEmailBtn.textContent;
                testEmailBtn.textContent = 'Sending...';

                try {
                    const resp = await fetch(action, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ test_email: recipient })
                    });
                    const data = await resp.json();
                    showTestEmailModal(data.success, data.message || 'No response');
                } catch (err) {
                    showTestEmailModal(false, 'Request failed: ' + (err.message || err));
                } finally {
                    testEmailBtn.disabled = false;
                    testEmailBtn.textContent = originalText;
                }
            });
        }

        function showTestEmailModal(success, message) {
            let modalEl = byId('testEmailModal');
            if (!modalEl) {
                modalEl = document.createElement('div');
                modalEl.id = 'testEmailModal';
                modalEl.className = 'modal fade';
                modalEl.tabIndex = -1;
                modalEl.innerHTML = `
                    <div class="modal-dialog modal-sm modal-dialog-centered">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title">Test Email</h5>
                          <button type="button" class="modern-btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                          <p id="testEmailModalMessage"></p>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="modern-btn modern-btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                      </div>
                    </div>`;
                document.body.appendChild(modalEl);
            }

            const msgEl = modalEl.querySelector('#testEmailModalMessage');
            if (msgEl) {
                msgEl.textContent = message;
                msgEl.classList.toggle('text-success', !!success);
                msgEl.classList.toggle('text-danger', !success);
            }

            const bootstrapModal = new bootstrap.Modal(modalEl);
            bootstrapModal.show();
        }

        const save2faAdminBtn = byId('save2faAdminBtn');
        if (save2faAdminBtn) {
            save2faAdminBtn.addEventListener('click', function () {
                const isRequired = byId('require2faAdmin')?.checked;
                const btn = save2faAdminBtn;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

                const csrfToken = getCsrfToken() || '';
                const body = new URLSearchParams({
                    csrf_token: csrfToken,
                    key: 'require_2fa_for_admin',
                    value: isRequired ? '1' : '0'
                });
                fetch('/admin/app-settings/security/update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body.toString()
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-success alert-dismissible show mt-3';
                            alertDiv.innerHTML = `
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Success!</strong> ${data.message}
                            <button type="button" class="modern-btn-close" data-bs-dismiss="alert"></button>
                        `;
                            const tabPane = document.querySelector('#security');
                            tabPane?.insertBefore(alertDiv, tabPane.firstChild);
                            bootstrap.Modal.getInstance(byId('require2faAdminModal'))?.hide();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(() => {
                        alert('An error occurred. Please try again.');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
            });
        }
    }

    function initAppSecuritySettings() {
        if (!byId('btnExport')) return;

        const csrfToken = getCsrfToken();

        byId('btnExport')?.addEventListener('click', () => {
            window.location.href = '/admin/app-settings/security/export';
        });

        byId('btnImport')?.addEventListener('click', () => {
            const modal = new bootstrap.Modal(byId('importModal'));
            modal.show();
        });

        byId('confirmImport')?.addEventListener('click', () => {
            const fileInput = byId('settingsFile');
            const file = fileInput?.files?.[0];
            if (!file) {
                alert('Please select a file');
                return;
            }
            const formData = new FormData(byId('importForm'));
            fetch('/admin/app-settings/security/import', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    bootstrap.Modal.getInstance(byId('importModal'))?.hide();
                    if (data.success) {
                        showAlert(`Successfully imported ${data.updated} settings`, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert(data.message || 'Import failed', 'danger');
                    }
                })
                .catch(err => {
                    showAlert('Import error: ' + err.message, 'danger');
                });
        });

        byId('btnReset')?.addEventListener('click', () => {
            const modal = new bootstrap.Modal(byId('resetModal'));
            modal.show();
        });

        byId('resetConfirmation')?.addEventListener('input', function (e) {
            const confirmBtn = byId('confirmReset');
            if (confirmBtn) confirmBtn.disabled = e.target.value !== 'RESET_ALL_SETTINGS';
        });

        byId('confirmReset')?.addEventListener('click', () => {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('confirm', 'RESET_ALL_SETTINGS');
            fetch('/admin/app-settings/security/reset', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    bootstrap.Modal.getInstance(byId('resetModal'))?.hide();
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert(data.message || 'Reset failed', 'danger');
                    }
                })
                .catch(err => {
                    showAlert('Reset error: ' + err.message, 'danger');
                });
        });

        document.querySelectorAll('.save-single-setting').forEach(btn => {
            btn.addEventListener('click', function () {
                const key = this.dataset.key;
                const inputId = this.dataset.inputId || key;
                const input = byId(inputId) || byId(key);
                const type = input?.dataset?.type;
                let value = input?.value;
                if (!input) return;
                if (type === 'boolean') value = input.checked ? '1' : '0';
                else if (type === 'json') {
                    try { JSON.parse(value); } catch { showAlert(`Invalid JSON for ${key}`, 'danger'); return; }
                }
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('key', key);
                formData.append('value', value);
                this.disabled = true;
                const originalText = this.innerHTML;
                fetch('/admin/app-settings/security/update', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) showAlert(data.message, 'success');
                        else showAlert(data.message || 'Save failed', 'danger');
                    })
                    .catch(err => { showAlert('Error: ' + err.message, 'danger'); })
                    .finally(() => { this.disabled = false; this.innerHTML = originalText; });
            });
        });

        document.querySelectorAll('.setting-input').forEach(input => {
            const originalValue = input.value;
            input.addEventListener('change', function () {
                const resetBtn = this.parentElement.querySelector('.reset-single-setting');
                if (resetBtn) resetBtn.style.display = this.value !== originalValue ? 'inline-block' : 'none';
            });
        });

        document.querySelectorAll('.reset-single-setting').forEach(btn => {
            btn.addEventListener('click', function () {
                location.reload();
            });
        });

        function showAlert(message, type = 'info') {
            const alertDiv = byId('settingsAlert');
            const alertMsg = byId('alertMessage');
            if (!alertDiv || !alertMsg) return;
            const alert = alertDiv.querySelector('.alert');
            alertMsg.textContent = message;
            if (alert) alert.className = `alert alert-${type} alert-dismissible show`;
            alertDiv.style.display = 'block';
            setTimeout(() => { alertDiv.style.display = 'none'; }, 5000);
        }
    }

    function initRbacPermissionsList() {
        const searchBox = byId('searchBox');
        if (!searchBox) return;
        searchBox.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('.permission-row').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }

    function initRbacRolesEdit() {
        loadAdminModule('rbacUsers')
            .then((rbacUsers) => rbacUsers.initRbacRolesEdit())
            .catch((error) => logModuleError('rbacUsers', error));
    }

    function initRbacUserRoles() {
        loadAdminModule('rbacUsers')
            .then((rbacUsers) => rbacUsers.initRbacUserRoles({ byId }))
            .catch((error) => logModuleError('rbacUsers', error));
    }

    function initSecurity2FASetup() {
        loadAdminModule('security2fa')
            .then((security2fa) => security2fa.initSecurity2FASetup({ byId }))
            .catch((error) => logModuleError('security2fa', error));
    }

    function initSecurity2FABackup() {
        loadAdminModule('security2fa')
            .then((security2fa) => security2fa.initSecurity2FABackup({ byId, getCsrfToken }))
            .catch((error) => logModuleError('security2fa', error));
    }

    function initSecurity2FA() {
        loadAdminModule('security2fa')
            .then((security2fa) => security2fa.initSecurity2FA({ byId, getCsrfToken }))
            .catch((error) => logModuleError('security2fa', error));
    }

    function initUsersAddUser() {
        loadAdminModule('rbacUsers')
            .then((rbacUsers) => rbacUsers.initUsersAddUser())
            .catch((error) => logModuleError('rbacUsers', error));
    }

    function initUsersEditUser() {
        loadAdminModule('rbacUsers')
            .then((rbacUsers) => rbacUsers.initUsersEditUser({ byId }))
            .catch((error) => logModuleError('rbacUsers', error));
    }

    function initServicesForms() {
        const dataEl = byId('service-form-data');
        if (!dataEl) return;
        const serviceCategoryIds = parseJson(dataEl.dataset.serviceCategoryIds, []);
        const serviceTagIds = parseJson(dataEl.dataset.serviceTagIds, []);
        const serviceAllTags = parseJson(dataEl.dataset.serviceAllTags, []);
        const serviceAllCategories = parseJson(dataEl.dataset.serviceAllCategories, []);
        const excludeId = dataEl.dataset.excludeId ? parseInt(dataEl.dataset.excludeId, 10) : null;

        window.serviceCategoryIds = serviceCategoryIds;
        window.serviceTagIds = serviceTagIds;
        window.serviceAllTags = serviceAllTags;
        window.serviceAllCategories = serviceAllCategories;
        window.serviceExcludeId = excludeId;

        if (typeof window.initializeServiceSlugGenerator === 'function') {
            window.initializeServiceSlugGenerator(excludeId);
        }

        if (window.adminContent?.fetchCategories) {
            window.adminContent.fetchCategories(serviceCategoryIds, '#service_categories');
        }
        if (window.adminContent?.initializeCategoriesSelect) {
            window.adminContent.initializeCategoriesSelect('#service_categories');
        }
        if (window.adminContent?.fetchTags) {
            window.adminContent.fetchTags(serviceTagIds, '#tags');
        }
        if (window.adminContent?.initializeTagsSelect) {
            window.adminContent.initializeTagsSelect('#tags');
        }

        const iconUploadBtn = byId('iconUploadBtn');
        const iconUploadInput = byId('iconUploadInput');
        const iconPreview = byId('iconPreview');
        const iconPreviewContainer = byId('iconPreviewContainer');
        const iconInput = byId('iconInput');
        const removeIconBtn = byId('removeIconBtn');

        iconUploadBtn?.addEventListener('click', () => iconUploadInput?.click());
        iconUploadInput?.addEventListener('change', function (e) {
            const file = e.target.files?.[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    if (iconPreview) iconPreview.src = event.target.result;
                    iconPreviewContainer?.classList.remove('d-none');
                    if (iconInput) iconInput.value = file.name;
                };
                reader.readAsDataURL(file);
            }
        });
        removeIconBtn?.addEventListener('click', function () {
            if (iconUploadInput) iconUploadInput.value = '';
            if (iconInput) iconInput.value = '';
            iconPreviewContainer?.classList.add('d-none');
        });

        const dropZone = byId('dropZone');
        const imageUploadInput = byId('imageUploadInput');
        const imagePreviewContainer = byId('imagePreviewContainer');

        function handleImageFiles(files) {
            Array.from(files).forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    if (file.size > 10 * 1024 * 1024) {
                        alert(`Image \"${file.name}\" is too large (max 10MB)`);
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function (event) {
                        const previewId = 'preview-' + Date.now() + '-' + index;
                        const fileKey = `${file.name}::${file.size}::${file.lastModified}`;
                        const previewHTML = `
                            <div class="col-md-6 col-lg-12 col-xl-6 mb-3" id="${previewId}-container" data-file-key="${fileKey}">
                                <div class="admin-panel-card border shadow-sm h-100">
                                    <img src="${event.target.result}" class="card-img-top" style="height: 150px; object-fit: cover;" alt="Preview">
                                    <div class="card-body p-3">
                                        <h6 class="card-title text-truncate mb-2" title="${file.name}">
                                            <i class="bi bi-image text-primary me-1"></i>${file.name}
                                        </h6>
                                        <p class="card-text small text-muted mb-2">
                                            <i class="bi bi-file-earmark me-1"></i>
                                            ${(file.size / 1024).toFixed(2)} KB
                                        </p>
                                        <button type="button" class="btn btn-sm btn-danger w-100" onclick="removePreview('${previewId}-container')">
                                            <i class="bi bi-trash me-1"></i>Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        imagePreviewContainer?.insertAdjacentHTML('beforeend', previewHTML);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        window.removePreview = function (containerId) {
            const element = byId(containerId);
            if (!element) return;
            const fileKey = element.getAttribute('data-file-key');
            if (fileKey && imageUploadInput?.files?.length) {
                const dt = new DataTransfer();
                Array.from(imageUploadInput.files).forEach(f => {
                    const k = `${f.name}::${f.size}::${f.lastModified}`;
                    if (k !== fileKey) dt.items.add(f);
                });
                imageUploadInput.files = dt.files;
            }
            element.remove();
        };

        dropZone?.addEventListener('click', () => imageUploadInput?.click());
        dropZone?.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('border-primary', 'bg-primary-subtle');
        });
        dropZone?.addEventListener('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('border-primary', 'bg-primary-subtle');
        });
        dropZone?.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('border-primary', 'bg-primary-subtle');
            const files = e.dataTransfer.files;
            handleImageFiles(files);
        });
        imageUploadInput?.addEventListener('change', function (e) {
            handleImageFiles(e.target.files);
        });

        window.removeMetadata = function (btn) {
            btn.closest('.row')?.remove();
        };

        byId('addMetadataBtn')?.addEventListener('click', function () {
            const html = `
                <div class="row g-2 mb-2 align-items-center">
                    <div class="col-md-5">
                        <input type="text" class="form-control" placeholder="Key" data-metadata-key>
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control" placeholder="Value" data-metadata-value>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeMetadata(this)">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
            `;
            byId('metadataFields')?.insertAdjacentHTML('beforeend', html);
        });

        window.removeFormField = function (btn) {
            btn.closest('.form-field-item')?.remove();
        };

        byId('addFormFieldBtn')?.addEventListener('click', function () {
            const html = `
                <div class="admin-panel-card mb-2 border form-field-item">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <input type="text" class="form-control" placeholder="Field Name" data-field-label>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" data-field-type>
                                    <option value="text">Text</option>
                                    <option value="email">Email</option>
                                    <option value="phone">Phone</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="select">Select</option>
                                    <option value="date">Date</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" data-field-required>
                                    <label class="form-check-label small">Required</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" placeholder="Placeholder text" data-field-placeholder>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeFormField(this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            byId('formFieldsContainer')?.insertAdjacentHTML('beforeend', html);
        });

        window.removeImage = function (btn) {
            const item = btn.closest('.image-item');
            if (!item) return;
            const imageId = item.getAttribute('data-image-id');
            if (imageId) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'deleted_image_ids[]';
                input.value = imageId;
                byId('serviceForm')?.appendChild(input);
            }
            item.remove();
        };

        const serviceForm = byId('serviceForm');
        serviceForm?.addEventListener('submit', async function (e) {
            e.preventDefault();

            const editor = window.editor_content;
            const hiddenDescriptionInput = byId('content-input');
            const contentEl = byId('content');
            const fallbackContent = contentEl
                ? (typeof contentEl.value === 'string' ? contentEl.value : (contentEl.innerHTML || ''))
                : '';
            const descriptionHtml = (editor && typeof editor.getContent === 'function')
                ? (editor.getContent() || '')
                : (hiddenDescriptionInput?.value || fallbackContent || '');
            const descriptionText = String(descriptionHtml || '')
                .replace(/<[^>]+>/g, ' ')
                .replace(/&nbsp;/gi, ' ')
                .trim();

            if (!descriptionText) {
                window.showMessage?.('Service description is required', 'danger');
                return;
            }

            if (hiddenDescriptionInput) {
                hiddenDescriptionInput.value = descriptionHtml;
            }

            const metadata = {};
            document.querySelectorAll('#metadataFields [data-metadata-key]').forEach((keyInput) => {
                const key = keyInput.value;
                const value = keyInput.parentElement.parentElement.querySelector('[data-metadata-value]')?.value;
                if (key) metadata[key] = value;
            });

            const formFields = [];
            document.querySelectorAll('#formFieldsContainer .form-field-item').forEach((item) => {
                const labelInput = item.querySelector('[data-field-label]');
                const typeSelect = item.querySelector('[data-field-type]');
                const requiredCheckbox = item.querySelector('[data-field-required]');
                const placeholderInput = item.querySelector('[data-field-placeholder]');
                if (labelInput && labelInput.value.trim()) {
                    formFields.push({
                        label: labelInput.value.trim(),
                        field_type: typeSelect ? typeSelect.value : 'text',
                        required: requiredCheckbox ? (requiredCheckbox.checked ? 1 : 0) : 0,
                        placeholder: placeholderInput ? placeholderInput.value.trim() : ''
                    });
                }
            });

            const imageUpdates = [];
            document.querySelectorAll('.image-item').forEach(item => {
                const id = item.getAttribute('data-image-id');
                if (!id) return;
                const altInput = item.querySelector('[data-field="alt_text"]');
                const captionInput = item.querySelector('[data-field="caption"]');
                const orderInput = item.querySelector('[data-field="display_order"]');
                const alt = altInput ? altInput.value.trim() : '';
                const caption = captionInput ? captionInput.value.trim() : '';
                const displayOrder = orderInput ? parseInt(orderInput.value || 0, 10) : 0;
                const featuredRadio = document.querySelector('input[name=\"featured_image\"]:checked');
                const isFeatured = featuredRadio && featuredRadio.value == id ? 1 : 0;

                imageUpdates.push({
                    id: parseInt(id, 10),
                    alt_text: alt,
                    caption: caption,
                    display_order: displayOrder,
                    is_featured: isFeatured
                });
            });

            const formData = new FormData(serviceForm);
            formData.append('metadata', JSON.stringify(metadata));
            formData.append('form_fields', JSON.stringify(formFields));
            formData.append('image_updates', JSON.stringify(imageUpdates));

            try {
                const endpoint = formData.get('service_id') ? '/admin/services/update' : '/admin/services/create';
                const response = await fetch(endpoint, { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    window.showMessage?.(data.message || 'Service saved successfully!', 'success');
                    setTimeout(() => window.location.href = '/admin/services/details/' + (data.service_id || formData.get('service_id')), 2000);
                } else {
                    window.showMessage?.(data.message || 'Failed to save service', 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                window.showMessage?.('An error occurred. Please try again.', 'danger');
            }
        });
    }

    function initServicesIndex() {
        const deleteModal = byId('deleteModal');
        if (!deleteModal) return;
        const csrfToken = getCsrfToken();
        let deleteServiceId = null;
        const modal = new bootstrap.Modal(deleteModal);

        document.querySelectorAll('.delete-service').forEach(btn => {
            btn.addEventListener('click', function () {
                deleteServiceId = this.dataset.id;
                modal.show();
            });
        });

        byId('confirmDelete')?.addEventListener('click', function () {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/admin/services/details/' + deleteServiceId + '/delete';
            form.innerHTML = `<input type="hidden" name="csrf_token" value="${csrfToken}">`;
            document.body.appendChild(form);
            form.submit();
        });
    }

    function initServicesApplications() {
        const listView = byId('listView');
        if (!listView) return;

        let currentPage = 1;
        const pageSize = 20;

        const viewToggle = document.querySelectorAll('input[name=\"view\"]');
        const dashboardView = byId('dashboardView');

        viewToggle.forEach(radio => {
            radio.addEventListener('change', function () {
                if (this.value === 'list') {
                    listView.style.display = 'block';
                    if (dashboardView) dashboardView.style.display = 'none';
                    loadApplications();
                } else {
                    listView.style.display = 'none';
                    if (dashboardView) dashboardView.style.display = 'block';
                    loadDashboard();
                }
            });
        });

        async function loadApplications() {
            const status = byId('filterStatus')?.value || '';
            const priority = byId('filterPriority')?.value || '';
            const dateFrom = byId('filterDateFrom')?.value || '';
            const dateTo = byId('filterDateTo')?.value || '';

            const params = new URLSearchParams({
                limit: pageSize,
                offset: (currentPage - 1) * pageSize
            });
            if (status) params.set('status', status);
            if (priority) params.set('priority', priority);
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);

            try {
                const response = await fetch(`/api/admin/applications?${params}`);
                const data = await response.json();
                if (data.success) {
                    renderApplicationsTable(data.data);
                    updateStats();
                    updatePagination(data.total);
                }
            } catch (error) {
                console.error('Error loading applications:', error);
                byId('applicationsTable').innerHTML = '<tr><td colspan=\"8\" class=\"text-center text-danger py-4\">Failed to load applications</td></tr>';
            }
        }

        function renderApplicationsTable(apps) {
            const html = apps.map(app => `
                <tr>
                    <td class="ps-4"><strong>#${app.id}</strong></td>
                    <td>${app.user_name} <br><small class="text-muted">${app.user_email}</small></td>
                    <td>${app.service_name}</td>
                    <td><span class="badge ${getStatusBadgeClass(app.status)}">${app.status}</span></td>
                    <td><span class="badge ${getPriorityBadgeClass(app.priority)}">${app.priority}</span></td>
                    <td class="small text-muted">${new Date(app.created_at).toLocaleDateString()}</td>
                    <td>${app.approved_by_name || '--'}</td>
                    <td>
                        <button class="modern-btn btn-sm btn-outline-primary rounded-2" onclick="viewApplication(${app.id})">
                            <i class="bi bi-eye"></i> View
                        </button>
                    </td>
                </tr>
            `).join('');

            byId('applicationsTable').innerHTML = html;
        }

        function getStatusBadgeClass(status) {
            const classes = { pending: 'bg-warning', processing: 'bg-info', approved: 'bg-success', rejected: 'bg-danger' };
            return classes[status] || 'bg-secondary';
        }

        function getPriorityBadgeClass(priority) {
            const classes = { low: 'bg-secondary', normal: 'bg-info', high: 'bg-danger' };
            return classes[priority] || 'bg-secondary';
        }

        async function updateStats() {
            try {
                const response = await fetch('/api/admin/applications/stats');
                const data = await response.json();
                if (data.success) {
                    byId('stat-total').textContent = data.data.total;
                    byId('stat-pending').textContent = data.data.pending;
                    byId('stat-approved').textContent = data.data.approved;
                    byId('stat-rejected').textContent = data.data.rejected;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        window.viewApplication = async function (appId) {
            try {
                const response = await fetch(`/api/admin/applications/${appId}`);
                const data = await response.json();
                if (data.success) {
                    renderApplicationDetail(data.data);
                    const modal = new bootstrap.Modal(byId('detailModal'));
                    modal.show();
                }
            } catch (error) {
                console.error('Error loading application:', error);
                window.showMessage?.('Failed to load application details', 'danger');
            }
        };

        function renderApplicationDetail(app) {
            const content = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="small text-muted">User</div>
                        <div class="fw-bold">${app.user.username}</div>
                        <div class="small">${app.user.email}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Service</div>
                        <div class="fw-bold">${app.service.name}</div>
                        <div class="small">${app.service.categories ? app.service.categories.map(c => c.name).join(', ') : '-'}</div>
                    </div>
                </div>

                <hr>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="small text-muted">Status</div>
                        <select class="form-select form-select-sm rounded-2" id="appStatus">
                            <option value="pending" ${app.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="processing" ${app.status === 'processing' ? 'selected' : ''}>Processing</option>
                            <option value="approved" ${app.status === 'approved' ? 'selected' : ''}>Approved</option>
                            <option value="rejected" ${app.status === 'rejected' ? 'selected' : ''}>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Priority</div>
                        <select class="form-select form-select-sm rounded-2" id="appPriority">
                            <option value="low" ${app.priority === 'low' ? 'selected' : ''}>Low</option>
                            <option value="normal" ${app.priority === 'normal' ? 'selected' : ''}>Normal</option>
                            <option value="high" ${app.priority === 'high' ? 'selected' : ''}>High</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Activated</div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="appActivated" ${app.service_activated ? 'checked' : ''}>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Submitted</div>
                        <div>${new Date(app.created_at).toLocaleDateString()}</div>
                    </div>
                </div>

                <hr>

                <div class="mb-3">
                    <label class="form-label small text-muted text-uppercase">Application Data</label>
                    <pre class="bg-light p-3 rounded-2 small"><code>${JSON.stringify(app.application_data, null, 2)}</code></pre>
                </div>

                ${app.status === 'rejected' && app.rejection_reason ? `
                    <div class="alert alert-danger rounded-2">
                        <strong>Rejection Reason:</strong>
                        <p class="mb-0">${app.rejection_reason}</p>
                    </div>
                ` : ''}

                <div class="mb-3">
                    <label class="form-label small text-muted text-uppercase">Admin Notes</label>
                    <textarea class="form-control rounded-2" id="appAdminNotes" rows="4">${app.admin_notes || ''}</textarea>
                </div>

                ${app.status === 'rejected' ? `
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase">Rejection Reason</label>
                        <input type="text" class="form-control rounded-2" id="appRejectionReason" value="${app.rejection_reason || ''}">
                    </div>
                ` : ''}

                <div class="mt-4">
                    <h6 class="fw-bold mb-2">Audit Log</h6>
                    <div class="timeline small">
                        ${app.audit_log.map(log => `
                            <div class="mb-2">
                                <div class="text-muted"><small>${new Date(log.created_at).toLocaleString()}</small></div>
                                <div><strong>${log.action_type}</strong>: ${log.description}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;

            byId('detailContent').innerHTML = content;
            window.currentAppId = app.id;
        }

        byId('applyFilters')?.addEventListener('click', () => {
            currentPage = 1;
            loadApplications();
        });

        byId('clearFilters')?.addEventListener('click', () => {
            byId('filterStatus').value = '';
            byId('filterPriority').value = '';
            byId('filterDateFrom').value = '';
            byId('filterDateTo').value = '';
            currentPage = 1;
            loadApplications();
        });

        function updatePagination(total) {
            const maxPages = Math.ceil(total / pageSize);
            const container = byId('paginationContainer');
            if (!container) return;
            if (maxPages > 1) {
                container.style.display = 'block';
                byId('pageInfo').textContent = `Page ${currentPage} of ${maxPages}`;
                byId('prevPage').onclick = (e) => {
                    e.preventDefault();
                    if (currentPage > 1) {
                        currentPage--;
                        loadApplications();
                    }
                };
                byId('nextPage').onclick = (e) => {
                    e.preventDefault();
                    if (currentPage < maxPages) {
                        currentPage++;
                        loadApplications();
                    }
                };
            } else {
                container.style.display = 'none';
            }
        }

        async function loadDashboard() {
            await updateStats();
        }

        loadApplications();
    }

    async function initNotificationModuleHelpers() {
        try {
            const notificationSystem = await import('/assets/firebase/v2/dist/notification-system.js');
            const analytics = await import('/assets/firebase/v2/dist/analytics.js');
            return { notificationSystem, analytics };
        } catch (e) {
            return { notificationSystem: null, analytics: null };
        }
    }

    async function initNotificationsList() {
        const hasAction = document.querySelector('[data-action="resend-notification"]');
        if (!hasAction) return;
        const { notificationSystem, analytics } = await initNotificationModuleHelpers();
        const showSuccess = notificationSystem?.showSuccess || window.showSuccess || window.showMessage;
        const showError = notificationSystem?.showError || window.showError || window.showMessage;
        const trackResend = analytics?.trackAdminNotificationResend;

        document.addEventListener('click', (event) => {
            const button = event.target.closest?.('[data-action="resend-notification"]');
            if (!button) return;
            const notificationId = parseInt(button.dataset.notificationId, 10);
            if (Number.isNaN(notificationId)) return;
            fetch(`/api/notification/resend`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify({ notification_id: notificationId, channels: ['push'] })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (trackResend) trackResend(notificationId, data.recipient_count || 0);
                        showSuccess?.(data.message || 'Notification resent');
                        location.reload();
                    } else {
                        showError?.('? Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => showError?.(err.message || 'Error resending'));
        });
    }

    async function initNotificationsView() {
        const dataEl = byId('notification-view-data');
        if (!dataEl) return;
        const notificationId = parseInt(dataEl.dataset.notificationId || '0', 10);
        if (!notificationId) return;
        const { notificationSystem, analytics } = await initNotificationModuleHelpers();
        const showSuccess = notificationSystem?.showSuccess || window.showSuccess || window.showMessage;
        const showError = notificationSystem?.showError || window.showError || window.showMessage;
        const showInfo = notificationSystem?.showInfo || window.showInfo || window.showMessage;
        const trackResend = analytics?.trackAdminNotificationResend;

        async function resendNotificationNow() {
            if (!confirm('Do you want to resend this notification now?')) return;
            try {
                const response = await fetch('/api/notification/resend', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                    body: JSON.stringify({ notification_id: notificationId, channels: ['push'] })
                });
                const data = await response.json();
                if (data.success) {
                    trackResend?.(notificationId, data.recipient_count || 0);
                    showSuccess?.(data.message || 'Notification resent');
                    location.reload();
                } else {
                    showError?.('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                showError?.('Error: ' + error.message);
            }
        }

        function duplicateNotification() {
            showInfo?.('Duplicate notification feature is coming soon.');
        }

        function deleteNotification() {
            if (confirm('Do you want to delete this notification?')) {
                showInfo?.('Delete notification feature is coming soon.');
            }
        }

        document.addEventListener('click', (event) => {
            const button = event.target.closest?.('[data-action]');
            if (!button) return;
            const action = button.dataset.action;
            if (action === 'resend-notification') return resendNotificationNow();
            if (action === 'duplicate-notification') return duplicateNotification();
            if (action === 'delete-notification') return deleteNotification();
        });
    }

    async function initNotificationsDashboard() {
        const root = byId('notificationsDashboardRoot');
        if (!root) return;
        const filterEndpoints = [
            root.dataset.filterEndpoint,
            '/api/notification/list',
            '/api/admin/notifications'
        ].filter(Boolean);

        async function tryFilter(status) {
            for (const endpoint of filterEndpoints) {
                const url = status ? `${endpoint}?status=${encodeURIComponent(status)}` : endpoint;
                try {
                    const res = await fetch(url);
                    if (!res.ok) continue;
                    const data = await res.json();
                    if (data && data.success) return true;
                } catch (e) {
                    continue;
                }
            }
            return false;
        }

        window.filterNotifications = async function (status = null) {
            const ok = await tryFilter(status);
            if (ok) location.reload();
        };

        window.loadNotificationDetail = function (notifId) {
            const detailContent = byId('detailContent');
            if (!detailContent) return;
            fetch(`/api/notification/${notifId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const notif = data.notification;
                        detailContent.innerHTML = `
                            <div class="mb-3">
                                <label class="form-label fw-bold">Title</label>
                                <p>${notif.title}</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Message</label>
                                <p>${notif.message}</p>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Recipient Type</label>
                                    <p><span class="badge bg-info">${notif.recipient_type}</span></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <p><span class="badge bg-secondary">${notif.status}</span></p>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Delivery Channels</label>
                                <p>
                                    ${notif.channels.includes('push') ? '<span class="badge bg-primary me-2"><i class="bi bi-phone"></i> Push</span>' : ''}
                                    ${notif.channels.includes('email') ? '<span class="badge bg-success me-2"><i class="bi bi-envelope"></i> Email</span>' : ''}
                                    ${notif.channels.includes('in_app') ? '<span class="badge bg-warning"><i class="bi bi-chat"></i> In-App</span>' : ''}
                                </p>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Created At</label>
                                    <p>${new Date(notif.created_at).toLocaleString('bn-BD')}</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Scheduled At</label>
                                    <p>${notif.scheduled_at ? new Date(notif.scheduled_at).toLocaleString('bn-BD') : 'Not scheduled'}</p>
                                </div>
                            </div>
                        `;

                        if (data.delivery_logs && data.delivery_logs.length > 0) {
                            const rows = data.delivery_logs.map(l => `
                                <tr>
                                    <td>${l.id}</td>
                                    <td>${l.user_id || 'guest'}</td>
                                    <td>${l.device_id || '-'}</td>
                                    <td>${l.channel || '-'}</td>
                                    <td>${l.ip_address || '-'}</td>
                                    <td><small>${l.token || '-'}</small></td>
                                    <td>${l.status}</td>
                                    <td><small>${l.message_id || '-'}</small></td>
                                    <td><small>${l.provider_response ? (l.provider_response.substring(0, 200) + (l.provider_response.length > 200 ? '...' : '')) : '-'}</small></td>
                                    <td class="small text-muted">${l.created_at}</td>
                                </tr>
                            `).join('');

                            detailContent.innerHTML += `
                                <hr>
                                <h6>Delivery Logs</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Device</th>
                                                <th>Channel</th>
                                                <th>IP</th>
                                                <th>Token</th>
                                                <th>Status</th>
                                                <th>Message ID</th>
                                                <th>Provider Response</th>
                                                <th>When</th>
                                            </tr>
                                        </thead>
                                        <tbody>${rows}</tbody>
                                    </table>
                                </div>
                            `;
                        }
                    }
                })
                .catch(() => {
                    if (detailContent) {
                        detailContent.innerHTML = '<div class="alert alert-danger">Failed to load notification details</div>';
                    }
                });
        };

        window.deleteNotification = function (notifId) {
            if (!confirm('Do you want to delete this notification?')) return;
            fetch(`/api/notification/${notifId}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-Token': getCsrfToken() }
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to delete notification');
                    }
                });
        };
    }

    async function initNotificationsDrafts() {
        const list = byId('draftsList');
        if (!list) return;
        const notificationSystem = await import('/assets/firebase/v2/dist/notification-system.js').catch(() => null);
        const showSuccess = notificationSystem?.showSuccess || window.showSuccess || window.showMessage;
        const showError = notificationSystem?.showError || window.showError || window.showMessage;

        async function loadDrafts() {
            try {
                const response = await fetch('/api/notification/list-drafts');
                const data = await response.json();
                if (data.success && data.drafts.length > 0) {
                    let html = `
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Message</th>
                                    <th>Type</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    data.drafts.forEach(draft => {
                        const createdAt = new Date(draft.created_at).toLocaleDateString('bn-BD', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        html += `
                            <tr>
                                <td>#${draft.id}</td>
                                <td><strong>${draft.title}</strong></td>
                                <td>${draft.message.substring(0, 50)}...</td>
                                <td><span class="badge bg-primary">${draft.type}</span></td>
                                <td>${createdAt}</td>
                                <td>
                                    <button class="modern-btn btn-sm btn-primary" data-action="edit-draft" data-draft-id="${draft.id}">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="modern-btn btn-sm btn-success" data-action="send-draft" data-draft-id="${draft.id}">
                                        <i class="bi bi-send"></i> Send
                                    </button>
                                    <button class="modern-btn btn-sm btn-danger" data-action="delete-draft" data-draft-id="${draft.id}">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    html += `</tbody></table>`;
                    list.innerHTML = html;
                } else {
                    list.innerHTML = `
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-inbox mb-3 d-block"></i>
                            <strong>No drafts found</strong><br>
                            <small><a href="/admin/notifications/send" class="text-decoration-none">Create a new notification draft</a></small>
                        </div>
                    `;
                }
            } catch (error) {
                list.innerHTML = `<div class="alert alert-danger m-3">Error: ${error.message}</div>`;
            }
        }

        function editDraft(draftId) {
            fetch(`/api/notification/draft-detail?draft_id=${draftId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.draft) {
                        const draft = data.draft;
                        byId('editDraftId').value = draft.id;
                        byId('editTitle').value = draft.title;
                        byId('editMessage').value = draft.message;
                        byId('editType').value = draft.type;
                        byId('editActionUrl').value = draft.action_url || '';
                        byId('editRecipientType').value = draft.recipient_type;
                        new bootstrap.Modal(byId('editDraftModal')).show();
                    } else {
                        showError?.('Failed to load draft details');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    showError?.('Error: ' + err.message);
                });
        }

        async function deleteDraft(draftId) {
            if (!confirm('Do you want to delete this draft?')) return;
            try {
                const response = await fetch('/api/notification/delete-draft', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                    body: JSON.stringify({ draft_id: draftId })
                });
                const data = await response.json();
                if (data.success) {
                    showSuccess?.('Draft deleted successfully');
                    loadDrafts();
                } else {
                    showError?.('Failed to delete draft');
                }
            } catch (error) {
                console.error('Error:', error);
                showError?.('Error: ' + error.message);
            }
        }

        async function sendDraft(draftId) {
            if (!confirm('Do you want to send this draft now?')) return;
            try {
                const response = await fetch('/api/notification/send-draft', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                    body: JSON.stringify({ draft_id: draftId })
                });
                const data = await response.json();
                if (data.success) {
                    showSuccess?.(data.message || 'Draft sent successfully');
                    loadDrafts();
                    setTimeout(() => { window.location.href = `/admin/notifications/view?id=${data.notification_id}`; }, 1500);
                } else {
                    showError?.('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                showError?.('Error: ' + error.message);
            }
        }

        async function saveEditedDraft() {
            const draftId = byId('editDraftId').value;
            const data = {
                draft_id: draftId,
                title: byId('editTitle').value,
                message: byId('editMessage').value,
                type: byId('editType').value,
                action_url: byId('editActionUrl').value,
                recipient_type: byId('editRecipientType').value,
                channels: ['push'],
                recipient_ids: []
            };
            try {
                const response = await fetch('/api/notification/update-draft', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showSuccess?.(result.message || 'Draft updated successfully');
                    bootstrap.Modal.getInstance(byId('editDraftModal'))?.hide();
                    loadDrafts();
                } else {
                    showError?.('Failed to update draft');
                }
            } catch (error) {
                showError?.('Error: ' + error.message);
            }
        }

        document.addEventListener('click', (event) => {
            const button = event.target.closest?.('[data-action]');
            if (!button) return;
            const action = button.dataset.action;
            if (action === 'save-draft') return saveEditedDraft();
            if (action === 'edit-draft') return editDraft(parseInt(button.dataset.draftId, 10));
            if (action === 'send-draft') return sendDraft(parseInt(button.dataset.draftId, 10));
            if (action === 'delete-draft') return deleteDraft(parseInt(button.dataset.draftId, 10));
        });

        loadDrafts();
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
    async function initNotificationsDashboardRealtime() {
        if (!byId('notificationsDashboardRealtime')) return;
        try {
            const [
                scheduledMod,
                notificationSystemMod,
                offlineMod
            ] = await Promise.all([
                import('/assets/firebase/v2/dist/scheduled-notifications.js'),
                import('/assets/firebase/v2/dist/notification-system.js'),
                import('/assets/firebase/v2/dist/offline-handler.js')
            ]);

            const ScheduledNotifications = scheduledMod.ScheduledNotifications || scheduledMod.default;
            const MultiDeviceSync = notificationSystemMod.MultiDeviceSync || notificationSystemMod.default?.MultiDeviceSync;
            const OfflineNotificationHandler = offlineMod.OfflineNotificationHandler || offlineMod.default;

            console.log('Notifications dashboard modules loaded');
            console.log('Available modules:', {
                ScheduledNotifications,
                MultiDeviceSync,
                OfflineNotificationHandler
            });
        } catch (e) { }
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
        const root = byId('notificationSubscribersRoot');
        if (!root) return;

        const tableBody = byId('subscribersTableBody');
        const totalCountEl = byId('subsTotalCount');

        const toast = (title, message, type) => {
            if (typeof window.showNotificationToast === 'function') {
                window.showNotificationToast(title, message, type);
            } else if (typeof window.showMessage === 'function') {
                window.showMessage(message, type === 'success' ? 'success' : 'danger');
            } else {
                alert(message);
            }
        };

        function getFilters() {
            return {
                recipient: byId('recipientFilter')?.value || 'all',
                permission: byId('permissionFilter')?.value || 'granted',
                search: (byId('searchInput')?.value || '').trim(),
                per_page: byId('perPage')?.value || '20'
            };
        }

        function renderSubscribers(rows) {
            if (!tableBody) return;
            if (!Array.isArray(rows) || rows.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No subscribers found</td></tr>';
                return;
            }

            tableBody.innerHTML = rows.map((s) => {
                const id = escapeHtml(s.id ?? '-');
                const deviceId = escapeHtml(s.device_id ?? '');
                const deviceName = escapeHtml(s.device_name ?? '-');
                const token = String(s.token ?? '');
                const tokenShort = escapeHtml(token.length > 40 ? token.slice(0, 40) + '...' : token);
                const permission = escapeHtml(s.permission ?? 'granted');
                const permClass = permission === 'granted' ? 'bg-success' : (permission === 'default' ? 'bg-secondary' : 'bg-danger');
                const type = escapeHtml(s.device_type ?? '-');
                const created = escapeHtml(s.created_at ?? '-');
                const userLabel = s.user_id
                    ? `<strong>${escapeHtml(s.username || s.email || ('UID:' + s.user_id))}</strong>`
                    : '<span class="text-muted">Guest</span>';

                return `
                        <tr>
                            <td>${id}</td>
                            <td>${userLabel}</td>
                            <td><small>${deviceId || '-'}</small></td>
                            <td><small>${deviceName}</small></td>
                            <td><small>${tokenShort || '-'}</small></td>
                            <td><span class="badge ${permClass}">${permission}</span></td>
                            <td>${type}</td>
                            <td><small>${created}</small></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="modern-btn modern-btn-danger btn-sm" onclick="revokeDevice('${deviceId}', this)">Revoke</button>
                                    <button class="modern-btn modern-btn-outline-danger btn-sm" onclick="deleteDevicePermanent('${deviceId}', this)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    `;
            }).join('');
        }

        async function reloadSubscribersTable() {
            const filters = getFilters();
            const q = new URLSearchParams();
            if (filters.recipient) q.set('recipient', filters.recipient);
            if (filters.search) q.set('search', filters.search);
            if (filters.permission) q.set('permission', filters.permission);
            if (filters.per_page) q.set('per_page', filters.per_page);

            try {
                const res = await fetch('/api/admin/notification-subscribers?' + q.toString(), {
                    headers: { 'X-CSRF-Token': getCsrfToken() }
                });
                const data = await res.json();
                if (!data || !data.success) {
                    throw new Error(data?.error || 'Failed to fetch subscribers');
                }
                renderSubscribers(data.subscribers || []);
                if (totalCountEl) totalCountEl.textContent = String(data.pagination?.total ?? 0);
            } catch (err) {
                console.error(err);
                toast('Error', 'Subscribers data fetch failed', 'danger');
            }
        }

        window.applySubsFilter = function () {
            const filters = getFilters();
            const q = new URLSearchParams();
            if (filters.recipient) q.set('recipient', filters.recipient);
            if (filters.search) q.set('search', filters.search);
            if (filters.permission) q.set('permission', filters.permission);
            if (filters.per_page) q.set('per_page', filters.per_page);
            window.history.replaceState({}, '', window.location.pathname + '?' + q.toString());
            reloadSubscribersTable();
        };

        window.revokeDevice = async function (deviceId, btn) {
            if (!deviceId) return;
            if (!confirm('Do you want to revoke subscription for this device?')) return;
            if (btn) btn.disabled = true;
            try {
                const endpoints = [
                    '/api/notification/revoke-device',
                    '/api/admin/notification-subscribers/revoke'
                ];
                let data = null;
                for (const endpoint of endpoints) {
                    try {
                        const res = await fetch(endpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                            body: JSON.stringify({ device_id: deviceId })
                        });
                        data = await res.json();
                        if (data && data.success) break;
                    } catch (err) {
                        data = null;
                    }
                }
                if (data && data.success) {
                    toast('Success', 'Subscription revoked', 'success');
                    await reloadSubscribersTable();
                } else {
                    toast('Error', (data && data.error) ? data.error : 'Revoke failed', 'danger');
                    if (btn) btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                toast('Error', 'Network error', 'danger');
                if (btn) btn.disabled = false;
            }
        };

        window.deleteDevicePermanent = async function (deviceId, btn) {
            if (!deviceId) return;
            if (!confirm('Delete this device permanently? This action cannot be undone.')) return;
            if (btn) btn.disabled = true;
            try {
                const res = await fetch('/api/admin/notification-subscribers/revoke', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                    body: JSON.stringify({ device_id: deviceId, permanent: true })
                });
                const data = await res.json();
                if (data.success) {
                    toast('Success', 'Device deleted permanently', 'success');
                    await reloadSubscribersTable();
                } else {
                    toast('Error', data.error || 'Delete failed', 'danger');
                    if (btn) btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                toast('Error', 'Network error', 'danger');
                if (btn) btn.disabled = false;
            }
        };

        window.revokeAllDevices = async function (permanent = false) {
            const filters = getFilters();
            const scopeLabel = filters.search
                ? `recipient=${filters.recipient}, search="${filters.search}"`
                : `recipient=${filters.recipient}`;
            const confirmText = permanent
                ? `Delete all filtered devices permanently?\n(${scopeLabel})\nThis action cannot be undone.`
                : `Revoke all filtered devices?\n(${scopeLabel})`;
            if (!confirm(confirmText)) return;

            try {
                const res = await fetch('/api/admin/notification-subscribers/revoke-all', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                    body: JSON.stringify({
                        recipient: filters.recipient,
                        search: filters.search,
                        permanent
                    })
                });
                const data = await res.json();
                if (!data || !data.success) {
                    throw new Error(data?.error || 'Bulk action failed');
                }

                const affected = Number(data.affected || 0);
                toast('Success', `${affected} device ${permanent ? 'deleted' : 'revoked'}`, 'success');
                await reloadSubscribersTable();
            } catch (err) {
                console.error(err);
                toast('Error', err.message || 'Bulk action failed', 'danger');
            }
        };

        byId('subsFilterForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            window.applySubsFilter();
        });
        byId('revokeAllBtn')?.addEventListener('click', () => window.revokeAllDevices(false));
        byId('deleteAllBtn')?.addEventListener('click', () => window.revokeAllDevices(true));

        reloadSubscribersTable();
    }

    function initNotificationsPauseResume() {
        const form = byId('pauseResumeForm');
        if (!form) return;
        const csrf = getCsrfToken();
        const notify = (message, type = 'success', duration = 5000) => {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type, duration);
                return;
            }
            window.showMessage?.(message, type, duration);
        };

        function post(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify(body)
            }).then(r => r.json());
        }

        byId('pauseBtn')?.addEventListener('click', async () => {
            const id = byId('notification_id')?.value;
            const reason = byId('reason')?.value || '';
            if (!id) { notify('Provide notification id', 'error'); return; }
            if (reason && reason.length > 500) { notify('Reason too long (max 500 chars)', 'error'); return; }
            const res = await post('/api/notification/' + id + '/pause', { reason });
            if (res && res.success) notify(res.message || 'Paused');
            else notify(res.error || 'Failed', 'error');
        });

        byId('resumeBtn')?.addEventListener('click', async () => {
            const id = byId('notification_id')?.value;
            if (!id) { notify('Provide notification id', 'error'); return; }
            const res = await post('/api/notification/' + id + '/resume', {});
            if (res && res.success) notify(res.message || 'Resumed');
            else notify(res.error || 'Failed', 'error');
        });
    }

    function initNotificationsRateLimit() {
        const form = byId('rateLimitForm');
        if (!form) return;
        const csrf = getCsrfToken();
        const notify = (message, type = 'success', duration = 5000) => {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type, duration);
                return;
            }
            window.showMessage?.(message, type, duration);
        };

        async function getLimits() {
            const res = await fetch('/api/notification/admin-rate-limit', { headers: { 'X-CSRF-Token': csrf } }).then(r => r.json());
            if (res.success) {
                const limits = res.limits || {};
                byId('currentLimits').innerText = 'Current limits: ' + JSON.stringify(limits);
                byId('hourly').value = limits.hourly || '';
                byId('daily').value = limits.daily || '';
            } else {
                byId('currentLimits').innerText = 'Error loading limits';
            }
        }

        async function post(url, body) {
            return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body) })
                .then(r => r.json());
        }

        byId('saveBtn')?.addEventListener('click', async () => {
            const hourlyRaw = byId('hourly')?.value;
            const dailyRaw = byId('daily')?.value;
            let hourly = null;
            let daily = null;
            if (hourlyRaw !== '') {
                if (isNaN(Number(hourlyRaw)) || Number(hourlyRaw) < 0) { notify('Hourly must be a non-negative number', 'error'); return; }
                hourly = parseInt(hourlyRaw, 10);
            }
            if (dailyRaw !== '') {
                if (isNaN(Number(dailyRaw)) || Number(dailyRaw) < 0) { notify('Daily must be a non-negative number', 'error'); return; }
                daily = parseInt(dailyRaw, 10);
            }
            if (hourly !== null && daily !== null && daily < hourly) { notify('Daily must be greater than or equal to hourly', 'error'); return; }

            const body = {};
            if (hourly !== null) body.hourly = hourly;
            if (daily !== null) body.daily = daily;

            const res = await post('/api/notification/admin-rate-limit', body);
            if (res && res.success) {
                notify(res.message || 'Limits updated');
                getLimits();
            } else {
                notify(res.error || 'Failed to save', 'error');
            }
        });

        getLimits();
    }

    function initNotificationsTopicsManagement() {
        const root = byId('topicsManagementRoot');
        if (!root) return;
        async function load() {
            const res = await fetch('/api/topics/list');
            const data = await res.json();
            const list = byId('topicsList');
            if (!list) return;
            if (data.success) {
                const html = data.topics.map(t => `<div><strong>${t.name}</strong> (${t.slug}) - default: ${t.default_enabled}</div>`).join('');
                list.innerHTML = html;
            }
        }
        load();
    }

    function initNotificationsSendByTopic() {
        const select = byId('topicSelect');
        if (!select) return;
        async function loadTopics() {
            const res = await fetch('/api/topics/list');
            const j = await res.json();
            if (j.success) {
                select.innerHTML = j.topics.map(t => `<option value="${t.slug}">${t.name}</option>`).join('');
            }
        }

        byId('sendBtn')?.addEventListener('click', async () => {
            const topic = select.value;
            const title = byId('title')?.value || '';
            const message = byId('message')?.value || '';
            const res = await fetch('/api/admin/send-by-topic', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ topic, title, message, channels: ['push'] })
            });
            const j = await res.json();
            if (j.success) alert('Queued: ' + j.notification_id);
            else alert('Error: ' + (j.error || 'unknown'));
        });

        loadTopics();
    }

    function initNotificationsKillSwitch() {
        const toggleBtn = byId('toggleBtn');
        const saveBtn = byId('saveBtn');
        const maintenanceMsg = byId('maintenanceMsg');
        if (!toggleBtn || !saveBtn || !maintenanceMsg) return;

        async function load() {
            const res = await fetch('/api/admin/notifications/kill-switch');
            const data = await res.json();
            if (data.success) {
                toggleBtn.textContent = data.enabled ? 'Enabled' : 'Disabled';
                toggleBtn.dataset.enabled = data.enabled ? '1' : '0';
                maintenanceMsg.value = data.message || '';
            }
        }

        toggleBtn.addEventListener('click', () => {
            const current = toggleBtn.dataset.enabled === '1';
            toggleBtn.dataset.enabled = current ? '0' : '1';
            toggleBtn.textContent = current ? 'Disabled' : 'Enabled';
        });

        saveBtn.addEventListener('click', async () => {
            const enabled = toggleBtn.dataset.enabled === '1' ? 1 : 0;
            const message = maintenanceMsg.value || '';
            const res = await fetch('/api/admin/notifications/kill-switch', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled, message })
            });
            const j = await res.json();
            if (j.success) alert('Saved'); else alert('Error');
        });

        load();
    }

    function initNotificationsSubscribersLegacy() {
        const root = byId('notificationsSubscribersLegacyRoot');
        if (!root) return;
        window.applySubsFilter = window.applySubsFilter || function () { };
    }

    function initNotificationsDashboardLegacy() {
        const root = byId('notificationsDashboardLegacyRoot');
        if (!root) return;
        window.filterNotifications = window.filterNotifications || function () { };
    }

    function initNotificationsAnalytics() {
        loadAdminModule('notificationsAnalytics')
            .then((notificationsAnalytics) => {
                const runInit = () => notificationsAnalytics.initNotificationsAnalytics({ byId });
                runInit();
                if (typeof window.Chart === 'undefined') {
                    window.addEventListener('load', runInit, { once: true });
                }
            })
            .catch((error) => logModuleError('notificationsAnalytics', error));
    }

    // Additional migrated handlers are appended below in smaller patches.

    onReady(() => {
        initFlashMessageAutoDismiss();
        initPasswordModals();
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
    });
})();



// (function () {
//     // 1. Get CSRF token from meta tag
//     const meta = document.querySelector('meta[name="csrf-token"]');
//     if (!meta) return;

//     const csrfToken = meta.getAttribute('content');
//     if (!csrfToken) return;

//     // 2. Inject CSRF token into all forms
//     document.querySelectorAll('form').forEach(form => {
//         let input = form.querySelector('input[name="csrf_token"]');

//         if (!input) {
//             // Create hidden input if not exists
//             input = document.createElement('input');
//             input.type = 'hidden';
//             input.name = 'csrf_token';
//             form.appendChild(input);
//         }

//         // Update token value
//         input.value = csrfToken;
//     });

//     // 3. Attach CSRF token to ALL fetch requests
//     const originalFetch = window.fetch;
//     window.fetch = function (url, options = {}) {
//         options.headers = options.headers || {};
//         options.headers['X-CSRF-TOKEN'] = csrfToken;
//         return originalFetch(url, options);
//     };

//     // 4. Attach CSRF token to XMLHttpRequest
//     const originalOpen = XMLHttpRequest.prototype.open;
//     XMLHttpRequest.prototype.open = function () {
//         this.addEventListener('readystatechange', function () {
//             if (this.readyState === 1) {
//                 this.setRequestHeader('X-CSRF-TOKEN', csrfToken);
//             }
//         });
//         originalOpen.apply(this, arguments);
//     };
// })();
