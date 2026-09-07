/**
 * Rich Text Editor - History Management Module
 * Handles undo/redo, history navigation, and history view updates
 */

(function(global) {
    'use strict';

    function installHistoryHelpers(RichTextEditor) {
        function getSafeHistoryHtml(editorInstance, entry) {
            const html = String(entry?.html || '');
            if (!html.trim()) return '<p><br></p>';

            try {
                if (typeof editorInstance.sanitizeHTML === 'function') {
                    const sanitized = editorInstance.sanitizeHTML(html);
                    return String(sanitized || '<p><br></p>');
                }
            } catch (e) {
                console.warn('sanitizeHTML failed while restoring history:', e);
            }

            return '<p><br></p>';
        }

        /**
         * Save current editor state to history
         */
        RichTextEditor.prototype.saveToHistory = function() {
            const currentHtml = this.editor.innerHTML;
            
            // Don't save if same as last entry
            if (this.history.length > 0 && this.history[this.historyIndex]?.html === currentHtml) {
                return;
            }
            
            // Remove any states after current index
            this.history = this.history.slice(0, this.historyIndex + 1);
            
            // Add new state
            this.history.push({
                html: currentHtml,
                timestamp: Date.now()
            });
            
            // Limit history size
            if (this.history.length > this.maxHistorySize) {
                this.history.shift();
            } else {
                this.historyIndex++;
            }
            
            window.RTE_debugLog('history', `State saved. History size: ${this.history.length}, Index: ${this.historyIndex}`);
        };

        /**
         * Debounced save to history (called during input)
         */
        RichTextEditor.prototype.debouncedSaveToHistory = function() {
            if (this.historyTimeout) {
                clearTimeout(this.historyTimeout);
            }
            
            this.historyTimeout = setTimeout(() => {
                this.saveToHistory();
            }, this.historyDelay);
        };

        /**
         * Undo last action
         */
        RichTextEditor.prototype.undo = function() {
            if (this.historyIndex > 0) {
                this.historyIndex--;
                this.editor.innerHTML = getSafeHistoryHtml(this, this.history[this.historyIndex]);
                this.updateHiddenInput();
                this.updateButtonStates();
                this.updatePlaceholder();
                if (typeof this.autoGrowEditorHeight === 'function') {
                    this.autoGrowEditorHeight({ force: true });
                }
                if (typeof this.updateToolbarStates === 'function') {
                    this.updateToolbarStates();
                }
                window.RTE_debugLog('history', `Undo to index ${this.historyIndex}`);
            } else {
                window.RTE_debugLog('history', 'Cannot undo - at beginning of history');
            }
        };

        /**
         * Redo last undone action
         */
        RichTextEditor.prototype.redo = function() {
            if (this.historyIndex < this.history.length - 1) {
                this.historyIndex++;
                this.editor.innerHTML = getSafeHistoryHtml(this, this.history[this.historyIndex]);
                this.updateHiddenInput();
                this.updateButtonStates();
                this.updatePlaceholder();
                if (typeof this.autoGrowEditorHeight === 'function') {
                    this.autoGrowEditorHeight({ force: true });
                }
                if (typeof this.updateToolbarStates === 'function') {
                    this.updateToolbarStates();
                }
                window.RTE_debugLog('history', `Redo to index ${this.historyIndex}`);
            } else {
                window.RTE_debugLog('history', 'Cannot redo - at end of history');
            }
        };

        /**
         * Restore a specific history entry
         */
        RichTextEditor.prototype.restoreFromHistory = function(index) {
            if (index < 0 || index >= this.history.length) {
                console.warn('⚠️ [restoreFromHistory] Invalid history index:', index);
                return;
            }
            
            this.historyIndex = index;
            this.editor.innerHTML = getSafeHistoryHtml(this, this.history[index]);
            this.updateHiddenInput();
            this.updatePlaceholder();
            if (typeof this.autoGrowEditorHeight === 'function') {
                this.autoGrowEditorHeight({ force: true });
            }
            this.updateHistoryView();
            this.switchView('compose');
            window.RTE_debugLog('history', `Restored from history index ${index}`);
        };

        /**
         * Update history view display
         */
        RichTextEditor.prototype.updateHistoryView = function() {
            if (!this.historyView) return;
            
            const historyList = this.historyView.querySelector('.rte-history-list');
            if (!historyList) return;
            
            historyList.innerHTML = '';
            
            this.history.forEach((entry, index) => {
                const item = document.createElement('div');
                item.className = 'rte-history-item';
                
                if (index === this.historyIndex) {
                    item.classList.add('rte-history-active');
                }
                
                const timestamp = new Date(entry.timestamp).toLocaleTimeString();
                const preview = entry.html.replace(/<[^>]*>/g, '').trim().substring(0, 50) || '(empty)';

                const timeSpan = document.createElement('span');
                timeSpan.className = 'rte-history-time';
                timeSpan.textContent = timestamp;

                const previewSpan = document.createElement('span');
                previewSpan.className = 'rte-history-preview';
                previewSpan.textContent = preview + '...';

                item.appendChild(timeSpan);
                item.appendChild(previewSpan);
                
                item.addEventListener('click', () => this.restoreFromHistory(index));
                historyList.appendChild(item);
            });
            
            window.RTE_debugLog('history', `History view updated with ${this.history.length} entries`);
        };

        /**
         * Check if can undo
         */
        RichTextEditor.prototype.canUndo = function() {
            try {
                return this.historyIndex > 0;
            } catch (e) {
                console.warn('canUndo error:', e);
                return false;
            }
        };

        /**
         * Check if can redo
         */
        RichTextEditor.prototype.canRedo = function() {
            try {
                return this.historyIndex < this.history.length - 1;
            } catch (e) {
                console.warn('canRedo error:', e);
                return false;
            }
        };

        window.RTE_debugLog('history', 'History management helpers installed');
        return true;
    }

    // Export for different module systems
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installHistoryHelpers };
    }
    if (typeof window !== 'undefined') {
        window.installHistoryHelpers = installHistoryHelpers;
    }
})(typeof window !== 'undefined' ? window : {});
