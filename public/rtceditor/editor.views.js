/**
 * Rich Text Editor - Views Module
 * Handles switching between compose, source, and history views.
 */

(function (global) {
    'use strict';

    /**
     * Very simple pretty formatter for HTML strings.
     * Falls back to identity if DOMParser is not available.
     */
    function formatHtml(html) {
        try {
            const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
            const raw = doc && doc.body ? doc.body.innerHTML : String(html || '');

            let indent = 0;
            return raw.replace(/(<\/?.+?>)/g, (match) => {
                if (/^<\//.test(match)) {
                    indent -= 2;
                }
                const padding = ' '.repeat(Math.max(indent, 0));
                if (/^<[^/?].*?>/.test(match) && !/<br\s*\/?|<img/i.test(match)) {
                    indent += 2;
                }
                return '\n' + padding + match;
            }).trim();
        } catch (e) {
            return String(html || '');
        }
    }

    function installViewHelpers(RichTextEditor) {
        /**
         * Switch between different editor views (compose, source, history).
         */
        RichTextEditor.prototype.switchView = function (view) {
            const targetView = String(view || '').toLowerCase();
            const container = this.wrapper || this.editor.parentElement;
            const buttons = this.toolbar.querySelectorAll('.rte-btn-view');

            if (!targetView || targetView === this.currentView) {
                return;
            }

            buttons.forEach(btn => btn.classList.remove('rte-btn-active'));

            // If we are leaving source mode, synchronize source changes first.
            if (this.currentView === 'source' && targetView !== 'source') {
                const ok = this.syncSourceToEditor();
                if (!ok) {
                    window.RTE_debugLog('views', 'Aborted view switch due to invalid HTML in source');
                    return;
                }
                this.updateHiddenInput();
                this.saveToHistory();
            }

            switch (targetView) {
                case 'compose': {
                    if (container) {
                        container.classList.remove('view-compose', 'view-source', 'view-history');
                        container.classList.add('view-compose');
                    }

                    const composeBtn = this.toolbar.querySelector('.rte-view-compose');
                    if (composeBtn) composeBtn.classList.add('rte-btn-active');

                    this.currentView = 'compose';
                    if (typeof this.autoGrowEditorHeight === 'function') {
                        this.autoGrowEditorHeight({ force: true });
                    }
                    setTimeout(() => {
                        try { if (this && this.editor) this.editor.focus(); } catch (e) { }
                    }, 10);
                    window.RTE_debugLog('views', 'Switched to COMPOSE view');
                    break;
                }

                case 'source': {
                    if (!this.sourceView) {
                        console.warn('[switchView] Source view not available');
                        return;
                    }

                    let html = this.editor.innerHTML;
                    try { html = formatHtml(html); } catch (e) { }
                    this.sourceView.value = html;

                    if (window.CodeMirror) {
                        if (!this._cmInstance) {
                            this._cmInstance = CodeMirror.fromTextArea(this.sourceView, {
                                mode: 'htmlmixed',
                                lineNumbers: true,
                                theme: 'default',
                                autoCloseTags: true,
                                matchBrackets: true
                            });
                        }
                        this._cmInstance.setValue(this.sourceView.value);
                        this._cmInstance.refresh();
                    }

                    if (container) {
                        container.classList.remove('view-compose', 'view-source', 'view-history');
                        container.classList.add('view-source');
                    }

                    const htmlBtn = this.toolbar.querySelector('.rte-view-html');
                    if (htmlBtn) htmlBtn.classList.add('rte-btn-active');

                    this.currentView = 'source';
                    setTimeout(() => {
                        if (this._cmInstance) {
                            this._cmInstance.focus();
                        } else {
                            this.sourceView.focus();
                        }
                    }, 10);
                    window.RTE_debugLog('views', 'Switched to SOURCE view');
                    break;
                }

                case 'history': {
                    if (!this.historyView) {
                        console.warn('[switchView] History view not available');
                        return;
                    }

                    this.updateHistoryView();
                    if (container) {
                        container.classList.remove('view-compose', 'view-source', 'view-history');
                        container.classList.add('view-history');
                    }

                    const historyBtn = this.toolbar.querySelector('.rte-view-history');
                    if (historyBtn) historyBtn.classList.add('rte-btn-active');

                    this.currentView = 'history';
                    window.RTE_debugLog('views', 'Switched to HISTORY view');
                    break;
                }

                default:
                    console.warn('[switchView] Unknown view:', targetView);
            }
        };

        /**
         * Sync HTML from source view back to main editor.
         */
        RichTextEditor.prototype.syncSourceToEditor = function () {
            if (!this.sourceView) {
                console.warn('[syncSourceToEditor] Source view not available');
                if (typeof this.showNotification === 'function') {
                    this.showNotification('Source view is not available.', 'error');
                }
                return false;
            }

            try {
                let rawHtml = this.sourceView.value;
                if (this._cmInstance) {
                    try {
                        rawHtml = this._cmInstance.getValue();
                    } catch (e) {
                        console.warn('[syncSourceToEditor] CodeMirror read failed, using textarea fallback:', e);
                        rawHtml = this.sourceView.value;
                    }
                }

                const hasUserHtml = String(rawHtml || '').trim().length > 0;
                if (typeof this.sanitizeHTML !== 'function' && hasUserHtml) {
                    console.error('[syncSourceToEditor] sanitizeHTML helper missing for non-empty source');
                    if (typeof this.showNotification === 'function') {
                        this.showNotification('Sanitizer unavailable. Source view sync blocked.', 'error');
                    }
                    return false;
                }

                let sanitizedHtml = '';
                if (hasUserHtml) {
                    try {
                        sanitizedHtml = String(this.sanitizeHTML(rawHtml) || '');
                        window.RTE_debugLog('views', 'Source HTML sanitized via sanitizeHTML');
                    } catch (sanitizeErr) {
                        console.error('sanitizeHTML failed:', sanitizeErr);
                        if (typeof this.showNotification === 'function') {
                            this.showNotification('Invalid HTML detected. Please review source.', 'error');
                        }
                        return false;
                    }
                }

                this.editor.innerHTML = String(sanitizedHtml || '').trim() ? sanitizedHtml : '<p><br></p>';
                this.updatePlaceholder();

                if (typeof this.autoGrowEditorHeight === 'function') {
                    this.autoGrowEditorHeight({ force: true });
                }

                window.RTE_debugLog('views', 'HTML synchronized from source to editor');
                return true;
            } catch (e) {
                console.error('[syncSourceToEditor] Sync error:', e);
                if (typeof this.showNotification === 'function') {
                    this.showNotification('HTML sync failed: ' + e.message, 'error');
                }
                return false;
            }
        };

        window.RTE_debugLog('views', 'View management helpers installed');
        return true;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installViewHelpers };
    }
    if (typeof window !== 'undefined') {
        window.installViewHelpers = installViewHelpers;
    }
})(typeof window !== 'undefined' ? window : {});
