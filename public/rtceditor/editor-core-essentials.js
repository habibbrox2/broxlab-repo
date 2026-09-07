/**
 * Rich Text Editor - Core Essentials Module
 * Consolidated from 5 small helpers: editor.ui.js, editor.utils.js, editor.keyboard.js, editor.dragdrop.js, editor.formatting.js
 * Reduces 5 separate HTTP requests to 1.
 */
(function (global) {
    'use strict';
    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text == null ? '' : text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }
    function rgbToHex(rgb) {
        var match = String(rgb || '').match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
        if (!match) return null;
        var r = parseInt(match[1], 10), g = parseInt(match[2], 10), b = parseInt(match[3], 10);
        if ([r, g, b].some(function (n) { return Number.isNaN(n); })) return null;
        return '#' + [r, g, b].map(function (n) { return n.toString(16).padStart(2, '0'); }).join('').toLowerCase();
    }
    function normalizeColor(color) {
        var raw = String(color || '').trim();
        if (!raw || raw === 'transparent' || raw === 'rgba(0, 0, 0, 0)') return null;
        if (/^#[0-9a-f]{6}$/i.test(raw)) return raw.toLowerCase();
        if (/^#[0-9a-f]{3}$/i.test(raw)) return '#' + raw.slice(1).split('').map(function (c) { return c + c; }).join('').toLowerCase();
        if (/^rgba?/i.test(raw)) return rgbToHex(raw);
        try {
            var el = document.createElement('div');
            el.style.color = raw; el.style.position = 'absolute'; el.style.left = '-99999px';
            document.body.appendChild(el);
            var computed = window.getComputedStyle(el).color;
            if (el.parentNode) el.parentNode.removeChild(el);
            return rgbToHex(computed);
        } catch (e) { return null; }
    }
    function installCoreEssentials(RichTextEditor) {
        RichTextEditor.prototype.toggleMoreMenu = function () {
            var dropdown = this.toolbar.querySelector('.rte-more-dropdown');
            if (!dropdown) { if (window.RTE_DEBUG) console.warn('More dropdown not found'); return; }
            var menu = dropdown.querySelector('.rte-more-menu');
            if (!menu) { if (window.RTE_DEBUG) console.warn('More menu not found'); return; }
            var isVisible = menu.style.display === 'block';
            menu.style.display = isVisible ? 'none' : 'block';
            window.RTE_debugLog('ui', 'More menu ' + (isVisible ? 'closed' : 'opened'));
            if (!isVisible) {
                var closeMenu = function (e) {
                    if (!dropdown.contains(e.target)) { menu.style.display = 'none'; document.removeEventListener('click', closeMenu); }
                };
                setTimeout(function () { document.addEventListener('click', closeMenu); }, 10);
            }
        };
        RichTextEditor.prototype.showNotification = function (message, type, duration) {
            type = type || 'info'; duration = duration || 3500;
            if (!this.wrapper) return null;
            var existing = this.wrapper.querySelector('.rte-notification');
            if (existing) existing.remove();
            var note = document.createElement('div');
            note.className = 'rte-notification rte-notification-' + type;
            note.setAttribute('role', 'alert'); note.setAttribute('aria-live', 'assertive');
            var textNode = document.createElement('span');
            textNode.className = 'rte-notification-text';
            textNode.textContent = String(message || '');
            note.appendChild(textNode);
            var close = document.createElement('button');
            close.type = 'button'; close.className = 'rte-notification-close';
            close.innerHTML = '&times;';
            close.setAttribute('aria-label', 'Close');
            close.addEventListener('click', function () { note.remove(); });
            note.appendChild(close);
            this.wrapper.appendChild(note);
            if (duration > 0) setTimeout(function () { if (note.parentNode) note.remove(); }, duration);
            return note;
        };
        RichTextEditor.prototype.setupKeyboardShortcuts = function () {
            if (!this.editor) return;
            var isMac = /MAC/i.test(navigator.platform);
            var isCommandKey = function (e) { return isMac ? e.metaKey : e.ctrlKey; };
            var self = this;
            this.editor.addEventListener('keydown', function (e) {
                if (!isCommandKey(e)) return;
                var shortcuts = { b: 'bold', i: 'italic', u: 'underline' };
                var cmd = shortcuts[e.key.toLowerCase()];
                if (cmd) { e.preventDefault(); e.stopPropagation(); self.executeCommand(cmd); }
                if (e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? self.redo() : self.undo(); }
                if (e.key.toLowerCase() === 'y') { e.preventDefault(); self.redo(); }
            });
        };
        RichTextEditor.prototype.handleDragOver = function (e) {
            e.preventDefault(); e.stopPropagation();
            if (this.editor) this.editor.classList.add('rte-drag-over');
        };
        RichTextEditor.prototype.handleDragLeave = function (e) {
            e.preventDefault(); e.stopPropagation();
            if (this.editor) this.editor.classList.remove('rte-drag-over');
        };
        RichTextEditor.prototype.handleDrop = function (e) {
            e.preventDefault(); e.stopPropagation();
            if (this.editor) this.editor.classList.remove('rte-drag-over');
            var files = e.dataTransfer.files;
            if (files.length > 0) {
                for (var i = 0; i < files.length; i++) {
                    if (files[i].type.startsWith('image/')) {
                        var maybePromise = this.handleImageDrop(files[i]);
                        if (maybePromise && typeof maybePromise.catch === 'function')
                            maybePromise.catch(function (error) { console.warn('RTE drag-drop upload failed:', error); });
                    }
                }
            }
        };
        RichTextEditor.prototype.setupDragDropHandlers = function () {
            if (!this.editor) return;
            var self = this;
            this.editor.addEventListener('dragover', function (e) { self.handleDragOver(e); });
            this.editor.addEventListener('dragleave', function (e) { self.handleDragLeave(e); });
            this.editor.addEventListener('drop', function (e) { self.handleDrop(e); });
        };
        RichTextEditor.prototype.clearFormatting = function () {
            try {
                var selection = window.getSelection();
                if (!selection.rangeCount) { if (window.RTE_DEBUG) console.warn('No selection'); return; }
                var range = selection.getRangeAt(0);
                var fragment = range.extractContents();
                var div = document.createElement('div');
                div.appendChild(fragment);
                var textNode = document.createTextNode(div.textContent);
                range.insertNode(textNode);
                range.setStartAfter(textNode); range.collapse(true);
                selection.removeAllRanges(); selection.addRange(range);
                this.updateHiddenInput(); this.saveToHistory();
                window.RTE_debugLog('formatting', 'Formatting cleared');
            } catch (e) { if (window.RTE_DEBUG) console.warn('clearFormatting error:', e); }
        };
        RichTextEditor.prototype.setLTR = function () {
            try {
                this.editor.style.direction = 'ltr'; this.editor.setAttribute('dir', 'ltr');
                this.wrapper.classList.remove('rte-rtl'); this.wrapper.classList.add('rte-ltr');
                this.isRTL = false; this.updateHiddenInput();
                window.RTE_debugLog('formatting', 'Direction set to LTR');
            } catch (e) { if (window.RTE_DEBUG) console.warn('setLTR error:', e); }
        };
        RichTextEditor.prototype.setRTL = function () {
            try {
                this.editor.style.direction = 'rtl'; this.editor.setAttribute('dir', 'rtl');
                this.wrapper.classList.remove('rte-ltr'); this.wrapper.classList.add('rte-rtl');
                this.isRTL = true; this.updateHiddenInput();
                window.RTE_debugLog('formatting', 'Direction set to RTL');
            } catch (e) { if (window.RTE_DEBUG) console.warn('setRTL error:', e); }
        };
        window.RTE_debugLog('essentials', 'Core Essentials installed successfully');
        return true;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installCoreEssentials };
    }
    if (typeof window !== 'undefined') {
        window.installCoreEssentials = installCoreEssentials;
    }
    if (typeof global !== 'undefined') {
        global.installCoreEssentials = installCoreEssentials;
    }

})(typeof window !== 'undefined' ? window : typeof global !== 'undefined' ? global : {});
