// Editor Toolbar Helpers - Install sync functions for heading/font/size and button states
(function (global) {
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
            global.RTE_debugLog('toolbar', ...args);
            return;
        }
        if (global && global.RTE_DEBUG && typeof console.log === 'function') {
            console['log'](...args);
        }
    }

    function installToolbarHelpers(RichTextEditor) {
        // Update button states based on current selection
        RichTextEditor.prototype.updateButtonStates = function () {
            debugLog('🔄 [Toolbar Helper] updateButtonStates called');
            const buttons = this.toolbar.querySelectorAll('button[data-command]');
            debugLog(`🔘 [Toolbar] Found ${buttons.length} buttons`);

            let activeCount = 0;
            buttons.forEach(button => {
                const command = button.dataset.command;
                try {
                    const state = document.queryCommandState(command);
                    if (state) {
                        button.classList.add('rte-btn-active');
                        activeCount++;
                        debugLog(`  ✅ [${command}] - ACTIVE (state: ${state})`);
                    } else {
                        button.classList.remove('rte-btn-active');
                        debugLog(`  ⚪ [${command}] - inactive`);
                    }
                } catch (err) {
                    button.classList.remove('rte-btn-active');
                    console.warn(`  ❌ [${command}] - error: ${err.message}`);
                }
            });
            debugLog(`✅ [Toolbar] updateButtonStates done: ${activeCount}/${buttons.length} active`);
        };

        // Sync heading select with current block
        RichTextEditor.prototype.syncHeadingSelect = function () {
            debugLog('🎯 [Toolbar Helper] syncHeadingSelect called');
            const select = this.toolbar ? this.toolbar.querySelector('.rte-heading-select') : null;
            if (!select) {
                console.warn('⚠️ [Toolbar] Heading select not found');
                return;
            }
            try {
                const sel = window.getSelection();
                debugLog('🔍 Window selection:', sel);
                debugLog('📊 rangeCount:', sel ? sel.rangeCount : 0);

                if (!sel || !sel.rangeCount) {
                    debugLog('⚠️ [Toolbar] No selection, setting to "p"');
                    select.value = 'p';
                    return;
                }
                const node = sel.getRangeAt(0).startContainer;
                let el = (node.nodeType === 3) ? node.parentElement : node;

                debugLog('🏷️ [Toolbar] Starting element:', el.tagName);

                while (el && el !== this.editor) {
                    debugLog(`  📍 Checking element: <${el.tagName}>`);
                    if (/^(P|H1|H2|H3|H4|H5|H6)$/i.test(el.tagName)) {
                        const blockType = el.tagName.toLowerCase();
                        debugLog(`✅ [Toolbar] Found block type: ${blockType}`);
                        select.value = blockType;
                        return;
                    }
                    el = el.parentElement;
                }

                debugLog('⚠️ [Toolbar] No block element found, setting to "p"');
                select.value = 'p';
            } catch (e) {
                console.error('❌ syncHeadingSelect error (toolbar helper):', e);
                console.trace('Stack:', e);
                select.value = 'p';
            }
        };

        // Sync font family select with current selection
        RichTextEditor.prototype.syncFontSelect = function () {
            debugLog('🔤 [Toolbar Helper] syncFontSelect called');
            const select = this.toolbar ? this.toolbar.querySelector('.rte-font-select') : null;
            if (!select) {
                console.warn('⚠️ [Toolbar] Font select not found');
                return;
            }
            try {
                const val = this._getSelectionCommonFont ? this._getSelectionCommonFont() : null;
                debugLog('📊 [Toolbar] Common font value:', val);
                debugLog('  _getSelectionCommonFont method exists:', typeof this._getSelectionCommonFont);

                if (val) {
                    debugLog(`✅ [Toolbar] Setting font select to: ${val}`);
                    select.value = val;
                    select.removeAttribute('data-mixed');
                    select.removeAttribute('aria-mixed');
                } else {
                    // if editor is empty keep default
                    if (this.editor && this.editor.innerText.trim() === '') {
                        select.value = select.value || 'Times New Roman';
                        select.removeAttribute('data-mixed');
                        select.removeAttribute('aria-mixed');
                        debugLog('ℹ️ [Toolbar] Editor empty, preserving default font');
                    } else {
                        debugLog('⚠️ [Toolbar] Mixed or no font selected');
                        select.value = '';
                        select.setAttribute('data-mixed', 'true');
                        select.setAttribute('aria-mixed', 'true');
                    }
                }
            } catch (e) {
                console.error('❌ syncFontSelect error (toolbar helper):', e);
                console.trace('Stack:', e);
                select.value = '';
            }
        };

        // Sync font size select with current selection
        RichTextEditor.prototype.syncFontSizeSelect = function () {
            debugLog('📏 [Toolbar Helper] syncFontSizeSelect called');
            const control = this.toolbar ? this.toolbar.querySelector('.rte-font-size-select') : null;
            if (!control) {
                console.warn('⚠️ [Toolbar] Font size control not found');
                return;
            }
            debugLog('  [Toolbar] font size control tag:', control.tagName);
            try {
                const val = this._getSelectionCommonFontSize ? this._getSelectionCommonFontSize() : null;
                debugLog('📊 [Toolbar] Common font size value:', val);
                debugLog('  _getSelectionCommonFontSize method exists:', typeof this._getSelectionCommonFontSize);

                if (val) {
                    debugLog(`✅ [Toolbar] Setting font size control to: ${val}`);
                    control.value = val + (String(val).toLowerCase().endsWith('px') ? '' : 'px');
                    control.removeAttribute('data-mixed');
                    control.removeAttribute('aria-mixed');
                } else {
                    // if editor empty, preserve default like font select does
                    if (this.editor && this.editor.innerText.trim() === '') {
                        control.value = control.value || '12';
                        control.removeAttribute('data-mixed');
                        control.removeAttribute('aria-mixed');
                        debugLog('ℹ️ [Toolbar] Editor empty, preserving default size');
                    } else {
                        debugLog('⚠️ [Toolbar] Mixed or no font size selected');
                        control.value = '';
                        control.setAttribute('data-mixed', 'true');
                        control.setAttribute('aria-mixed', 'true');
                    }
                }
            } catch (e) {
                console.warn('syncFontSizeSelect error (toolbar helper):', e);
                console.trace('Stack:', e);
                control.value = '';
            }
        };

        /**
         * Update all toolbar button states based on current selection
         * Called from finalizeSelection() to sync all toolbar controls
         */
        RichTextEditor.prototype.updateToolbarStates = function () {
            try {
                debugLog('🔄 [Toolbar] updateToolbarStates called - syncing all controls');

                // Update button pressed states based on current commands
                this.updateButtonStates();

                // Update heading/block select with current block type
                this.syncHeadingSelect();

                // Update font family select
                if (typeof this.syncFontSelect === 'function') {
                    this.syncFontSelect();
                }

                // Update font size select
                if (typeof this.syncFontSizeSelect === 'function') {
                    this.syncFontSizeSelect();
                }

                // Update color inputs - sync for both cursor position and text selection
                if (typeof this.syncColorInputs === 'function') {
                    this.syncColorInputs();
                }

                debugLog('✅ [Toolbar] All toolbar states updated');
                return true;
            } catch (e) {
                console.warn('updateToolbarStates error:', e);
                return false;
            }
        };

        return true;
    }

    // Export for both CommonJS and ES6 module contexts
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installToolbarHelpers };
    }
    if (typeof window !== 'undefined') {
        window.installToolbarHelpers = installToolbarHelpers;
    }
})(typeof window !== 'undefined' ? window : {});
