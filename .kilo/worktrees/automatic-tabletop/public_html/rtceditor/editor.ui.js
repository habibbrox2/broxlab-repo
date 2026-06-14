/**
 * Rich Text Editor - UI Helpers Module
 * Handles menu toggle, notifications, and lightweight UI utilities.
 */

(function (global) {
    'use strict';

    function installUIHelpers(RichTextEditor) {
        /**
         * Toggle the more options dropdown menu
         */
        RichTextEditor.prototype.toggleMoreMenu = function () {
            const dropdown = this.toolbar.querySelector('.rte-more-dropdown');
            if (!dropdown) {
                console.warn('[toggleMoreMenu] More dropdown not found');
                return;
            }

            const menu = dropdown.querySelector('.rte-more-menu');
            if (!menu) {
                console.warn('[toggleMoreMenu] More menu not found');
                return;
            }

            const isVisible = menu.style.display === 'block';
            menu.style.display = isVisible ? 'none' : 'block';
            window.RTE_debugLog('ui', `More menu ${isVisible ? 'closed' : 'opened'}`);

            if (!isVisible) {
                const closeMenu = (e) => {
                    if (!dropdown.contains(e.target)) {
                        menu.style.display = 'none';
                        document.removeEventListener('click', closeMenu);
                    }
                };
                setTimeout(() => document.addEventListener('click', closeMenu), 10);
            }
        };

        /**
         * Show a non-blocking notification message.
         */
        RichTextEditor.prototype.showNotification = function (message, type = 'info', duration = 3500) {
            if (!this.wrapper) return null;

            const existing = this.wrapper.querySelector('.rte-notification');
            if (existing) existing.remove();

            const note = document.createElement('div');
            note.className = `rte-notification rte-notification-${type}`;
            note.setAttribute('role', 'alert');
            note.setAttribute('aria-live', 'assertive');

            const textNode = document.createElement('span');
            textNode.className = 'rte-notification-text';
            textNode.textContent = String(message || '');
            note.appendChild(textNode);

            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'rte-notification-close';
            close.innerHTML = '&times;';
            close.setAttribute('aria-label', 'Close');
            close.addEventListener('click', () => note.remove());
            note.appendChild(close);

            this.wrapper.appendChild(note);

            if (duration > 0) {
                setTimeout(() => {
                    if (note.parentNode) note.remove();
                }, duration);
            }
            return note;
        };

        window.RTE_debugLog('ui', 'UI helpers installed');
        return true;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installUIHelpers };
    }
    if (typeof window !== 'undefined') {
        window.installUIHelpers = installUIHelpers;
    }
})(typeof window !== 'undefined' ? window : {});
