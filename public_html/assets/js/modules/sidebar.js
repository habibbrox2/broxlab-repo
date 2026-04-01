/**
 * Sidebar Module
 * Handles sidebar toggling, mini mode, resizing, and responsive behaviors
 */

export function initSidebar() {
    'use strict';

    // Sidebar Toggle Logic
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggles = document.querySelectorAll('.sidebar-toggle');
    const sidebarMiniToggle = document.querySelector('.sidebar-mini-toggle');
    const adminShellRow = document.querySelector('.admin-shell-row');
    const adminMain = document.querySelector('.admin-main');
    const sidebarResizer = document.getElementById('adminColumnResizer');
    const MINI_STORAGE_KEY = 'admin.sidebar.mini';
    const SIDEBAR_WIDTH_KEY = 'admin.sidebar.width';
    const MINI_EXPANDED_CLASS = 'admin-sidebar-mini-expanded';
    const MOBILE_OPEN_CLASS = 'admin-sidebar-open';
    const DESKTOP_WIDTH = 992;
    const DEFAULT_SIDEBAR_WIDTH = 280;
    const MIN_SIDEBAR_WIDTH = 220;
    const MAX_SIDEBAR_WIDTH = 520;

    // Create overlay element
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    const applyStackedTables = () => {
        const tables = document.querySelectorAll('table.table-stacked');
        if (!tables.length) return;

        tables.forEach((table) => {
            const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim());
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

        const clampSidebarWidth = (width, rowWidth = 0) => {
            const maxByRow = rowWidth > 0 ? Math.max(MIN_SIDEBAR_WIDTH, rowWidth - 360) : MAX_SIDEBAR_WIDTH;
            const hardMax = Math.min(MAX_SIDEBAR_WIDTH, maxByRow);
            return Math.min(hardMax, Math.max(MIN_SIDEBAR_WIDTH, Math.round(width)));
        };

        const readSidebarWidth = () => {
            try {
                const value = Number.parseInt(localStorage.getItem(SIDEBAR_WIDTH_KEY) || '', 10);
                return Number.isFinite(value) ? value : DEFAULT_SIDEBAR_WIDTH;
            } catch (err) {
                return DEFAULT_SIDEBAR_WIDTH;
            }
        };

        const writeSidebarWidth = (width) => {
            try {
                localStorage.setItem(SIDEBAR_WIDTH_KEY, String(width));
            } catch (err) {
                // Silent fail if storage is unavailable
            }
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
            const clamped = clampSidebarWidth(width, rowRect.width);
            sidebar.style.flex = `0 0 ${clamped}px`;
            sidebar.style.maxWidth = `${clamped}px`;
            adminMain.style.flex = `1 1 calc(100% - ${clamped}px)`;
            adminMain.style.maxWidth = `calc(100% - ${clamped}px)`;
        };

        const applyMiniSidebarState = (forceState = null) => {
            if (window.innerWidth < 992) {
                document.body.classList.remove('admin-sidebar-mini');
                document.body.classList.remove(MINI_EXPANDED_CLASS);
                document.body.classList.remove(MOBILE_OPEN_CLASS);
                resetSidebarWidth();
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
                applySidebarWidth(readSidebarWidth());
            } else {
                resetSidebarWidth();
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
                if (nextState) {
                    resetSidebarWidth();
                } else {
                    applySidebarWidth(readSidebarWidth());
                }
                sidebarMiniToggle.setAttribute('aria-pressed', nextState ? 'true' : 'false');
                sidebarMiniToggle.setAttribute('aria-expanded', 'false');
            });
        }

        if (sidebarResizer && adminShellRow && adminMain) {
            let isResizing = false;
            let pointerId = null;
            let liveWidth = readSidebarWidth();

            const updateResizerVisibility = () => {
                const isDesktop = window.innerWidth >= DESKTOP_WIDTH;
                const isMini = isMiniMode();

                // Reset all visibility classes first
                sidebarResizer.classList.remove('d-none', 'd-lg-flex', 'd-flex');

                if (isDesktop) {
                    // On desktop, show the resizer (as flex)
                    if (isMini) {
                        sidebarResizer.classList.add('d-flex', 'is-mini');
                    } else {
                        sidebarResizer.classList.add('d-lg-flex');
                    }
                } else {
                    // On mobile, hide completely
                    sidebarResizer.classList.add('d-none');
                }
            };

            const ensureMiniDisabledForResize = () => {
                if (!isMiniMode()) return;
                document.body.classList.remove('admin-sidebar-mini');
                document.body.classList.remove(MINI_EXPANDED_CLASS);
                writeMiniState(false);
                applySidebarWidth(readSidebarWidth());
                updateResizerVisibility();
            };

            const startResize = (event) => {
                if (window.innerWidth < DESKTOP_WIDTH) return;
                if (isMiniMode()) {
                    ensureMiniDisabledForResize();
                }
                isResizing = true;
                pointerId = event.pointerId;
                adminShellRow.classList.add('is-resizing');
                try {
                    sidebarResizer.setPointerCapture(pointerId);
                } catch (err) {
                    // ignore capture errors
                }
                event.preventDefault();
            };

            const moveResize = (event) => {
                if (!isResizing) return;
                const rowRect = adminShellRow.getBoundingClientRect();
                const next = clampSidebarWidth(event.clientX - rowRect.left, rowRect.width);
                liveWidth = next;
                applySidebarWidth(next);
            };

            const endResize = () => {
                if (!isResizing) return;
                isResizing = false;
                adminShellRow.classList.remove('is-resizing');
                if (window.innerWidth >= DESKTOP_WIDTH && !isMiniMode()) {
                    writeSidebarWidth(liveWidth);
                }
                if (pointerId !== null) {
                    try {
                        sidebarResizer.releasePointerCapture(pointerId);
                    } catch (err) {
                        // ignore release errors
                    }
                }
                pointerId = null;
            };

            sidebarResizer.addEventListener('pointerdown', startResize);
            window.addEventListener('pointermove', moveResize);
            window.addEventListener('pointerup', endResize);
            window.addEventListener('pointercancel', endResize);
            sidebarResizer.addEventListener('dblclick', () => {
                if (window.innerWidth < DESKTOP_WIDTH) return;
                if (isMiniMode()) {
                    ensureMiniDisabledForResize();
                }
                liveWidth = DEFAULT_SIDEBAR_WIDTH;
                writeSidebarWidth(liveWidth);
                applySidebarWidth(liveWidth);
            });
            sidebarResizer.addEventListener('keydown', (event) => {
                if (window.innerWidth < DESKTOP_WIDTH) return;
                if (isMiniMode()) {
                    ensureMiniDisabledForResize();
                }
                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
                event.preventDefault();
                const delta = event.key === 'ArrowLeft' ? -16 : 16;
                const rowRect = adminShellRow.getBoundingClientRect();
                liveWidth = clampSidebarWidth((liveWidth || readSidebarWidth()) + delta, rowRect.width);
                writeSidebarWidth(liveWidth);
                applySidebarWidth(liveWidth);
            });

            updateResizerVisibility();
            applySidebarWidth(readSidebarWidth());
            window.addEventListener('resize', updateResizerVisibility);
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
            if (!isMiniMode() && window.innerWidth >= DESKTOP_WIDTH) {
                applySidebarWidth(readSidebarWidth());
            }
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
}