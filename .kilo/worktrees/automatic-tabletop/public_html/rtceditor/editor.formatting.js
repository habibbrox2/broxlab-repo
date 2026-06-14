/**
 * Rich Text Editor - Formatting Module
 * Handles text formatting operations (clear formatting, RTL/LTR direction)
 */

(function(global) {
    'use strict';
    const nativeConsole = (global && global.console) || (typeof globalThis !== 'undefined' ? globalThis.console : null);
    const fallbackConsole = nativeConsole || {
        log: function () { },
        warn: function () { },
        error: function () { },
        trace: function () { }
    };
    const console = fallbackConsole;
    function debugLog(...args) {
        if (global && typeof global.RTE_debugLog === 'function') {
            global.RTE_debugLog('formatting', ...args);
            return;
        }
        if (global && global.RTE_DEBUG && typeof console.log === 'function') {
            console['log'](...args);
        }
    }

    function installFormattingHelpers(RichTextEditor) {
        /**
         * Clear all formatting from selection
         */
        RichTextEditor.prototype.clearFormatting = function() {
            try {
                const selection = window.getSelection();
                if (!selection.rangeCount) {
                    console.warn('⚠️ [clearFormatting] No selection available');
                    return;
                }
                
                const range = selection.getRangeAt(0);
                const fragment = range.extractContents();
                
                // Get text content only
                const div = document.createElement('div');
                div.appendChild(fragment);
                const text = div.textContent;
                
                // Insert plain text
                const textNode = document.createTextNode(text);
                range.insertNode(textNode);
                
                // Move cursor to end
                range.setStartAfter(textNode);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
                
                this.updateHiddenInput();
                this.saveToHistory();
                debugLog('🧹 [clearFormatting] All formatting cleared from selection');
            } catch (e) {
                console.warn('❌ [clearFormatting] Error:', e);
            }
        };

        /**
         * Set text direction to LTR (Left-to-Right)
         */
        RichTextEditor.prototype.setLTR = function() {
            try {
                this.editor.style.direction = 'ltr';
                this.editor.setAttribute('dir', 'ltr');
                this.wrapper.classList.remove('rte-rtl');
                this.wrapper.classList.add('rte-ltr');
                this.isRTL = false;
                this.updateHiddenInput();
                debugLog('➡️ [setLTR] Direction set to LTR (Left-to-Right)');
            } catch (e) {
                console.warn('❌ [setLTR] Error:', e);
            }
        };

        /**
         * Set text direction to RTL (Right-to-Left)
         * Useful for Arabic, Hebrew, Bengali, Urdu, and other RTL languages
         */
        RichTextEditor.prototype.setRTL = function() {
            try {
                this.editor.style.direction = 'rtl';
                this.editor.setAttribute('dir', 'rtl');
                this.wrapper.classList.remove('rte-ltr');
                this.wrapper.classList.add('rte-rtl');
                this.isRTL = true;
                this.updateHiddenInput();
                debugLog('⬅️ [setRTL] Direction set to RTL (Right-to-Left)');
            } catch (e) {
                console.warn('❌ [setRTL] Error:', e);
            }
        };

        /**
         * Toggle between LTR and RTL
         */
        RichTextEditor.prototype.toggleDirection = function() {
            if (this.isRTL) {
                this.setLTR();
            } else {
                this.setRTL();
            }
        };

        debugLog('✅ [installFormattingHelpers] Formatting helpers installed');
        return true;
    }

    // Export for different module systems
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installFormattingHelpers };
    }
    if (typeof window !== 'undefined') {
        window.installFormattingHelpers = installFormattingHelpers;
    }
})(typeof window !== 'undefined' ? window : {});
