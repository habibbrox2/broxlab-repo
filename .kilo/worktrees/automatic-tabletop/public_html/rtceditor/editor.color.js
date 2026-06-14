// Editor Color Helpers - Complete rewrite for reliability
(function(global) {
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
            global.RTE_debugLog('color', ...args);
            return;
        }
        if (global && global.RTE_DEBUG && typeof console.log === 'function') {
            console['log'](...args);
        }
    }

    function installColorHelpers(RichTextEditor) {

        /* ---------- Helper Functions ---------- */
    
    function rgbToHex(rgb) {
        if (global.RTE_utils && typeof global.RTE_utils.rgbToHex === 'function') {
            return global.RTE_utils.rgbToHex(rgb);
        }
        if (!rgb) return null;
        // Match RGB or RGBA
        const match = String(rgb).match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
        if (!match) return null;
        
        const r = parseInt(match[1], 10);
        const g = parseInt(match[2], 10);
        const b = parseInt(match[3], 10);
        
        return '#' + [r, g, b]
            .map(x => {
                const hex = x.toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            })
            .join('')
            .toLowerCase();
    }

    function normalizeColor(color) {
        if (global.RTE_utils && typeof global.RTE_utils.normalizeColor === 'function') {
            return global.RTE_utils.normalizeColor(color);
        }
        if (!color) return null;
        
        color = String(color).trim();
        if (!color || color === 'transparent' || color === 'rgba(0, 0, 0, 0)') return null;
        
        // Already hex
        if (/^#[0-9a-f]{6}$/i.test(color)) {
            return color.toLowerCase();
        }
        
        // Short hex to full hex
        if (/^#[0-9a-f]{3}$/i.test(color)) {
            return ('#' + color.slice(1).split('').map(c => c + c).join('')).toLowerCase();
        }
        
        // RGB/RGBA to hex
        if (/^rgba?/i.test(color)) {
            const hexColor = rgbToHex(color);
            if (!hexColor) return null;
            // Don't filter out pure black - it's a valid color
            if (hexColor !== '#000000' || (color.includes('0,') && color.includes('0,') && color.includes('0'))) {
                return hexColor;
            }
            // If it's truly transparent/black with alpha, filter it
            if (color.includes('rgba') && color.endsWith('0)')) {
                return null;
            }
            return hexColor;
        }
        
        // Named color or other - try to compute it
        try {
            const el = document.createElement('div');
            el.style.color = color;
            el.style.position = 'absolute';
            el.style.left = '-99999px';
            document.body.appendChild(el);
            const computed = window.getComputedStyle(el).color;
            if (el.parentNode) el.parentNode.removeChild(el);
            debugLog(`    Computed ${color} →`, computed);
            return rgbToHex(computed);
        } catch (e) {
            console.warn(`    Could not normalize color: ${color}`, e);
            return null;
        }
    }

    function getContrastColor(hex) {
        if (!hex) return '#000000';
        // hex -> r,g,b
        const m = String(hex).replace('#', '');
        if (m.length !== 6) return '#000000';
        const r = parseInt(m.substr(0,2),16);
        const g = parseInt(m.substr(2,2),16);
        const b = parseInt(m.substr(4,2),16);
        // Perceived luminance
        const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
        return lum > 160 ? '#000000' : '#ffffff';
    }

    /* ---------- Color Detection Method ---------- */
    
    RichTextEditor.prototype._getSelectionCommonColor = function (isBg = false) {
        const colorType = isBg ? 'background' : 'text';
        debugLog(`🎨 [Color] _getSelectionCommonColor called for ${colorType} color`);
        
        try {
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) {
                console.warn(`⚠️ [Color] No selection for ${colorType} color detection`);
                return null;
            }
            
            const range = sel.getRangeAt(0);
            debugLog(`📍 [Color] Selection range:`, {
                startContainer: range.startContainer.nodeName,
                endContainer: range.endContainer.nodeName,
                collapsed: range.collapsed
            });
            
            if (range.collapsed) {
                // Empty selection - check current element
                let node = range.startContainer;
                if (node.nodeType === 3) node = node.parentElement;
                
                if (!node) {
                    console.warn(`⚠️ [Color] No node found for ${colorType} color`);
                    return null;
                }
                
                const style = window.getComputedStyle(node);
                const colorValue = isBg ? style.backgroundColor : style.color;
                debugLog(`    Raw ${colorType} value:`, colorValue);
                const normalized = normalizeColor(colorValue);
                debugLog(`✅ [Color] Collapsed selection ${colorType} color:`, normalized);
                return normalized;
            }
            
            // Has selection - walk through original DOM starting from range boundaries
            const colors = new Set();
            
            // Get the common ancestor container and walk from there
            const container = range.commonAncestorContainer;
            const startNode = range.startContainer;
            const endNode = range.endContainer;
            const startOffset = range.startOffset;
            const endOffset = range.endOffset;
            
            debugLog(`    Walking from ${startNode.nodeName} to ${endNode.nodeName}`);
            
            // Helper to check if a node is in range
            const isInRange = (node, offset = 0) => {
                const comp = range.comparePoint(node, offset);
                // comparePoint returns -1 (before), 0 (inside), 1 (after)
                return comp === 0;
            };
            
            const walk = (node) => {
                if (!node) return;
                
                try {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if element intersects with range
                        if (range.intersectsNode(node)) {
                            const style = window.getComputedStyle(node);
                            const colorValue = isBg ? style.backgroundColor : style.color;
                            
                            if (colorValue && colorValue !== 'transparent' && colorValue !== 'rgba(0, 0, 0, 0)') {
                                debugLog(`      Found ${colorType} in <${node.nodeName}>:`, colorValue);
                                const normalized = normalizeColor(colorValue);
                                if (normalized) {
                                    colors.add(normalized);
                                }
                            }
                            
                            // Walk children
                            for (let i = 0; i < node.childNodes.length; i++) {
                                walk(node.childNodes[i]);
                            }
                        }
                    } else if (node.nodeType === Node.TEXT_NODE && node.nodeValue.trim()) {
                        // Text node - check parent element
                        const parent = node.parentElement;
                        if (parent && range.intersectsNode(parent)) {
                            const style = window.getComputedStyle(parent);
                            const colorValue = isBg ? style.backgroundColor : style.color;
                            
                            if (colorValue && colorValue !== 'transparent' && colorValue !== 'rgba(0, 0, 0, 0)') {
                                debugLog(`      Found ${colorType} on <${parent.nodeName}> parent of text:`, colorValue);
                                const normalized = normalizeColor(colorValue);
                                if (normalized) {
                                    colors.add(normalized);
                                }
                            }
                        }
                    }
                } catch (e) {
                    // Ignore node walking errors
                }
            };
            
            walk(container.nodeType === 3 ? container.parentElement : container);
            
            debugLog(`    Colors detected: ${colors.size} unique color(s)`, Array.from(colors));
            
            if (colors.size === 0) return null;
            if (colors.size === 1) return Array.from(colors)[0];
            
            debugLog(`    ⚠️ Mixed colors detected - returning null`);
            return null; // Multiple colors
        } catch (e) {
            console.error('Color detection error:', e);
            return null;
        }
    };

    /* ---------- Sync Color Inputs ---------- */
    
    RichTextEditor.prototype.syncColorInputs = function () {
        debugLog('🎨 [Color Sync] syncColorInputs called');
        debugLog(`  _selectionFinal: ${this._selectionFinal}`);
        
        if (!this.toolbar) {
            console.warn('⚠️ [Color Sync] No toolbar available');
            return;
        }
        
        // Only sync when selection is finalized
        if (!this._selectionFinal) {
            debugLog('⏳ [Color Sync] Waiting for selection to be finalized');
            return;
        }
        
        debugLog('✅ [Color Sync] Selection finalized - proceeding with sync');
        
        try {
            // Validate selection exists (can be collapsed or non-collapsed)
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) {
                console.warn('⚠️ [Color Sync] No selection available - skipping sync');
                return;
            }
            
            debugLog('✅ [Color Sync] Valid selection detected (cursor or range)');
            
            // Get color inputs
            const textColorInput = this.toolbar.querySelector('.rte-text-color-input');
            const bgColorInput = this.toolbar.querySelector('.rte-bg-color-input');
            
            if (textColorInput) {
                const textColor = this._getSelectionCommonColor(false);
                debugLog(`  Text color detected: ${textColor || 'none'}`);
                
                if (textColor) {
                    textColorInput.value = textColor;
                    textColorInput.removeAttribute('data-mixed');
                    textColorInput.removeAttribute('aria-mixed');
                    
                    // Update visual indicator - find color label in parent wrapper
                    const wrapper = textColorInput.closest('.rte-color-wrapper');
                    if (wrapper) {
                        const textColorLabel = wrapper.querySelector('.rte-color-label');
                            if (textColorLabel) {
                            textColorLabel.classList.remove('rte-color-mixed');
                            // For text color, show it on the icon (foreground) and on the label text
                            const icon = textColorLabel.querySelector('.rte-icon');
                            if (icon) icon.style.color = textColor;
                            // Also set label color so the button itself reflects the text color
                            textColorLabel.style.color = textColor;
                            textColorLabel.style.backgroundColor = 'transparent';
                            debugLog(`    └─ Icon and label color set to: ${textColor}`);
                        }
                        wrapper.classList.remove('rte-color-mixed');
                    }
                    textColorInput.title = `Text Color: ${textColor}`;
                    debugLog('✅ [Color Sync] Text color input updated:', textColor);
                } else {
                    textColorInput.setAttribute('data-mixed', 'true');
                    textColorInput.setAttribute('aria-mixed', 'true');
                    
                    const wrapper = textColorInput.closest('.rte-color-wrapper');
                    if (wrapper) {
                        const textColorLabel = wrapper.querySelector('.rte-color-label');
                            if (textColorLabel) {
                            textColorLabel.classList.add('rte-color-mixed');
                            // Clear inline styles when mixed
                            textColorLabel.style.backgroundColor = '';
                            textColorLabel.style.color = '';
                            const icon = textColorLabel.querySelector('.rte-icon');
                            if (icon) icon.style.color = '';
                            debugLog(`    └─ Label marked as mixed (multiple colors)`);
                        }
                        wrapper.classList.add('rte-color-mixed');
                    }
                    textColorInput.title = 'Mixed or no color';
                    debugLog('⚠️ [Color Sync] Text color: Mixed or empty');
                }
            }
            
            if (bgColorInput) {
                const bgColor = this._getSelectionCommonColor(true);
                debugLog(`  Background color detected: ${bgColor || 'none'}`);
                
                if (bgColor) {
                    bgColorInput.value = bgColor;
                    bgColorInput.removeAttribute('data-mixed');
                    bgColorInput.removeAttribute('aria-mixed');
                    
                    // Update visual indicator - find color label in parent wrapper
                    const wrapper = bgColorInput.closest('.rte-color-wrapper');
                    if (wrapper) {
                        const bgColorLabel = wrapper.querySelector('.rte-color-label');
                        if (bgColorLabel) {
                            bgColorLabel.classList.remove('rte-color-mixed');
                            // For background color, set label background and adjust icon contrast
                            bgColorLabel.style.backgroundColor = bgColor;
                            const icon = bgColorLabel.querySelector('.rte-icon');
                            if (icon) icon.style.color = getContrastColor(bgColor);
                            debugLog(`    └─ Label background set to: ${bgColor} (icon contrast applied)`);
                        }
                        wrapper.classList.remove('rte-color-mixed');
                    }
                    bgColorInput.title = `Background Color: ${bgColor}`;
                    debugLog('✅ [Color Sync] BG color input updated:', bgColor);
                } else {
                    bgColorInput.setAttribute('data-mixed', 'true');
                    bgColorInput.setAttribute('aria-mixed', 'true');
                    
                    const wrapper = bgColorInput.closest('.rte-color-wrapper');
                    if (wrapper) {
                        const bgColorLabel = wrapper.querySelector('.rte-color-label');
                        if (bgColorLabel) {
                            bgColorLabel.classList.add('rte-color-mixed');
                            // Clear inline styles when mixed
                            bgColorLabel.style.backgroundColor = '';
                            const icon = bgColorLabel.querySelector('.rte-icon');
                            if (icon) icon.style.color = '';
                            debugLog(`    └─ Label marked as mixed (multiple colors)`);
                        }
                        wrapper.classList.add('rte-color-mixed');
                    }
                    bgColorInput.title = 'Mixed or no color';
                    debugLog('⚠️ [Color Sync] BG color: Mixed or empty');
                }
            }
            
            debugLog('✅ [Color Sync] Complete - color inputs and icons updated');
        } catch (e) {
            console.warn('Sync color error:', e);
        }
    };

    /* ---------- Apply Color Method ---------- */
    
    RichTextEditor.prototype.applyColor = function (color, isBg = false) {
        const colorType = isBg ? 'background' : 'text';
        debugLog(`🎨 [Apply Color] applyColor called for ${colorType} color:`, color);
        
        if (!color) {
            console.warn(`⚠️ [Apply Color] No ${colorType} color provided`);
            return;
        }
        
        try {
            const normalized = normalizeColor(color);
            if (!normalized) {
                console.warn(`⚠️ [Apply Color] Cannot normalize ${colorType} color:`, color);
                return;
            }
            
            debugLog(`✅ [Apply Color] Normalized ${colorType} color:`, normalized);
            
            // Get the current selection
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) {
                console.error(`❌ [Apply Color] NO SELECTION AVAILABLE FOR ${colorType.toUpperCase()} COLOR`);
                debugLog('  Current focus:', document.activeElement?.tagName);
                debugLog('  Editor:', this.editor?.tagName);
                return;
            }
            
            const range = sel.getRangeAt(0);
            const property = isBg ? 'backgroundColor' : 'color';
            
            debugLog(`📍 [Apply Color] Selection range info:`, {
                collapsed: range.collapsed,
                startContainer: range.startContainer.nodeName,
                startOffset: range.startOffset,
                endContainer: range.endContainer.nodeName,
                endOffset: range.endOffset
            });
            
            // Check if selection is valid (inside editor)
            if (!this.editor.contains(range.commonAncestorContainer) && range.commonAncestorContainer !== this.editor) {
                console.error(`❌ [Apply Color] Selection not in editor`);
                return;
            }
            
            // Handle empty/collapsed selection (cursor position)
            if (range.collapsed) {
                debugLog(`📝 [Apply Color] Handling COLLAPSED selection (cursor position)`);
                
                const span = document.createElement('span');
                span.style[property] = normalized;
                span.textContent = '\u200B'; // Zero-width space
                
                range.insertNode(span);
                debugLog(`✅ [Apply Color] Inserted color span at cursor`);
                
                // Position cursor after the span
                range.setStart(span, 1);
                range.collapse(true);
                
                sel.removeAllRanges();
                sel.addRange(range);
                
                debugLog('✅ Color applied to cursor position');
            } else {
                // Has actual text selection
                debugLog('📝 Handling TEXT SELECTION');
                
                try {
                    // Clone the range to work with it
                    const clonedRange = range.cloneRange();
                    
                    // Extract the selected content
                    const selectedContent = clonedRange.extractContents();
                    
                    // Create span with color
                    const span = document.createElement('span');
                    span.style[property] = normalized;
                    span.appendChild(selectedContent);
                    
                    // Insert colored span back
                    clonedRange.insertNode(span);
                    
                    // Reselect the colored text
                    const newRange = document.createRange();
                    newRange.selectNodeContents(span);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                    
                    debugLog('✅ Color applied to selection, text reselected');
                } catch (innerErr) {
                    console.error('❌ Error wrapping selection:', innerErr);
                    return;
                }
            }
            
            // Update editor state BEFORE losing focus
            try {
                debugLog('💾 Updating hidden input and history...');
                this.updateHiddenInput();
                this.saveToHistory();
                debugLog('✅ Editor state updated and history saved');
            } catch (e) {
                console.warn('⚠️ Warning updating editor state:', e);
            }
            
            // Return focus to editor (CRITICAL - do it AFTER applying color)
            if (this.editor) {
                debugLog('🔄 Returning focus to editor');
                this.editor.focus();
            }
            
        } catch (e) {
            console.error('❌ CRITICAL: applyColor error:', e);
        }
    };

    /**
     * Set text color (convenience method)
     */
    RichTextEditor.prototype.setTextColor = function (color) {
        try {
            if (!color) return false;
            return this.applyColor(color, false);
        } catch (e) {
            console.error('❌ Error setting text color:', e);
            return false;
        }
    };

    /**
     * Set background color (convenience method)
     */
    RichTextEditor.prototype.setBackgroundColor = function (color) {
        try {
            if (!color) return false;
            return this.applyColor(color, true);
        } catch (e) {
            console.error('❌ Error setting background color:', e);
            return false;
        }
    };

        return true;
    }

    // Export for both CommonJS and ES6 module contexts
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installColorHelpers };
    }
    if (typeof window !== 'undefined') {
        window.installColorHelpers = installColorHelpers;
    }
})(typeof window !== 'undefined' ? window : {});
