// Rich Text Editor Selection Manager - Complete selection handling and analysis
// এই ফাইল rich text editor এর জন্য সকল selection related functionality প্রদান করে
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
            global.RTE_debugLog('selection', ...args);
            return;
        }
        if (global && global.RTE_DEBUG && typeof console.log === 'function') {
            console['log'](...args);
        }
    }
    function debugGroup(label) {
        if (global && typeof global.RTE_debugLog === 'function') {
            global.RTE_debugLog('selection', label);
            return;
        }
        if (global && global.RTE_DEBUG && typeof console.group === 'function') {
            console.group(label);
        }
    }
    function debugGroupEnd() {
        if (global && global.RTE_debugLog) {
            return;
        }
        if (global && global.RTE_DEBUG && typeof console.groupEnd === 'function') {
            console.groupEnd();
        }
    }

    /**
     * RichTextEditor-এ সকল selection-related methods ইনস্টল করে
     * @param {Object} RichTextEditor - RichTextEditor constructor
     */
    function installSelectionManager(RichTextEditor) {
        
        // ========================================
        // SECTION 1: Selection Save/Restore
        // ========================================
        
        /**
         * বর্তমান selection সেভ করে পরে restore করার জন্য
         * @returns {boolean} - সফল হলে true, ব্যর্থ হলে false
         */
        RichTextEditor.prototype.saveSelection = function () {
            try {
                const sel = window.getSelection();
                debugLog('📌 saveSelection called');
                debugLog('🔍 Selection object:', sel);
                debugLog('📊 rangeCount:', sel.rangeCount);
                
                if (sel.rangeCount > 0) {
                    const range = sel.getRangeAt(0);
                    debugLog('✅ Range found:', {
                        startContainer: range.startContainer,
                        startOffset: range.startOffset,
                        endContainer: range.endContainer,
                        endOffset: range.endOffset,
                        collapsed: range.collapsed,
                        commonAncestorContainer: range.commonAncestorContainer
                    });
                    
                    const selectedText = range.toString();
                    debugLog('📝 Selected Text:', `"${selectedText}"`);
                    debugLog('📏 Selected Text Length:', selectedText.length);
                    
                    this._savedRange = range.cloneRange();
                    debugLog('💾 Saved Range stored');
                    return true;
                } else {
                    console.warn('⚠️ No ranges found in selection');
                    return false;
                }
            } catch (e) {
                console.error('❌ saveSelection error:', e);
                console.trace('Stack trace:', e);
                return false;
            }
        };

        /**
         * পূর্বে সেভ করা selection restore করে
         * @returns {boolean} - সফল হলে true, ব্যর্থ হলে false
         */
        RichTextEditor.prototype.restoreSelection = function () {
            debugLog('🔄 restoreSelection called');
            
            if (!this._savedRange) {
                console.warn('⚠️ No saved range available');
                return false;
            }
            
            try {
                const sel = window.getSelection();
                debugLog('📌 Clearing existing ranges. Current rangeCount:', sel.rangeCount);
                
                sel.removeAllRanges();
                debugLog('✅ Ranges cleared');
                
                debugLog('📍 Adding saved range back:', this._savedRange);
                sel.addRange(this._savedRange);
                
                const restoredText = this._savedRange.toString();
                debugLog('📝 Restored text:', `"${restoredText}"`);
                
                if (this.editor) {
                    this.editor.focus();
                    debugLog('✅ Editor focused');
                }
                
                debugLog('✅ Selection restored successfully');
                return true;
            } catch (e) {
                console.error('❌ restoreSelection error:', e);
                console.trace('Stack trace:', e);
                return false;
            }
        };

        /**
         * Editor এর সকল content select করে
         * @returns {boolean} - সফল হলে true, ব্যর্থ হলে false
         */
        RichTextEditor.prototype.selectAll = function () {
            debugLog('🎯 selectAll called');
            
            try {
                debugLog('📍 Creating range for editor:', this.editor);
                const range = document.createRange();
                range.selectNodeContents(this.editor);
                
                debugLog('📊 Range created:', {
                    startOffset: range.startOffset,
                    endOffset: range.endOffset,
                    collapsed: range.collapsed
                });
                
                const sel = window.getSelection();
                debugLog('🔍 Current selection rangeCount:', sel.rangeCount);
                
                sel.removeAllRanges();
                debugLog('✅ Old ranges removed');
                
                sel.addRange(range);
                debugLog('✅ New range added');
                
                const selectedText = range.toString();
                debugLog('📝 All selected text:', `"${selectedText}"`);
                debugLog('📏 Total length:', selectedText.length);
                
                if (this.editor) {
                    this.editor.focus();
                    debugLog('✅ Editor focused');
                }
                
                debugLog('✅ selectAll completed successfully');
                return true;
            } catch (e) {
                console.error('❌ selectAll error:', e);
                console.trace('Stack trace:', e);
                return false;
            }
        };

        // ========================================
        // SECTION 2: Font Normalization
        // ========================================
        
        /**
         * পুরাতন <font> ট্যাগকে <span> এ রূপান্তর করে
         * আধুনিক HTML standard অনুযায়ী
         */
        
        // ========================================
        // SECTION 3: Selection Query Methods
        // ========================================
        
        /**
         * বর্তমান selection এর element খুঁজে বের করে
         * @returns {Element|null} - Selection এর element অথবা null
         */
        RichTextEditor.prototype._getSelectionElement = function () {
            debugLog('🔎 _getSelectionElement called');
            
            try {
                const sel = window.getSelection();
                debugLog('📌 Selection:', sel);
                
                if (!sel || !sel.rangeCount) {
                    console.warn('⚠️ No selection or rangeCount is 0');
                    return null;
                }
                
                const range = sel.getRangeAt(0);
                const node = range.startContainer;
                
                debugLog('📍 Start container node:', {
                    nodeType: node.nodeType,
                    nodeName: node.nodeName,
                    nodeValue: node.nodeValue?.substring(0, 50),
                    node: node
                });
                
                // Text node হলে তার parent element নিবো
                const element = (node.nodeType === 3) ? node.parentElement : node;
                
                debugLog('✅ Selection element found:', {
                    tagName: element?.tagName,
                    className: element?.className,
                    id: element?.id,
                    element: element
                });
                
                return element;
            } catch (e) {
                console.error('❌ _getSelectionElement error:', e);
                return null;
            }
        };

        /**
         * Selection এর common font খুঁজে বের করে
         * @returns {string|null} - Font name অথবা null
         */
        RichTextEditor.prototype._getTextNodesInRange = function (range) {
            if (!range) return [];

            const nodes = [];
            const walker = document.createTreeWalker(
                range.commonAncestorContainer,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );

            if (range.startContainer.nodeType === 3) {
                nodes.push(range.startContainer);
            }

            while (walker.nextNode()) {
                const n = walker.currentNode;
                if (range.intersectsNode(n)) {
                    nodes.push(n);
                }
            }
            return nodes;
        };

        RichTextEditor.prototype._getSelectionCommonFont = function () {
            const select = this.toolbar ? this.toolbar.querySelector('.rte-font-select') : null;
            if (!select) return null;
            
            const options = Array.from(select.options).map(o => o.value).filter(v => v);
            
            try {
                const sel = window.getSelection();
                if (!sel || !sel.rangeCount) return null;
                
                const range = sel.getRangeAt(0);
                const nodes = this._getTextNodesInRange(range);
                if (nodes.length === 0) return null;

                const matches = new Set();
                for (const node of nodes) {
                    const el = node.parentElement || node;
                    const computed = window.getComputedStyle(el);
                    const ff = (computed.fontFamily || '').toLowerCase();
                    
                    let matched = '';
                    for (const opt of options) {
                        if (ff.indexOf(opt.toLowerCase()) !== -1) {
                            matched = opt;
                            break;
                        }
                    }
                    
                    if (!matched) return null; // Unknown font
                    matches.add(matched);
                    
                    if (matches.size > 1) return null; // Multiple fonts
                }

                return matches.size === 1 ? Array.from(matches)[0] : null;
            } catch (e) {
                console.warn('❌ _getSelectionCommonFont error:', e);
                return null;
            }
        };

        /**
         * Selection এর common font size খুঁজে বের করে
         * @returns {string|null} - Font size অথবা null
         */
        RichTextEditor.prototype._getSelectionCommonFontSize = function () {
            // support both <select> and <input list="..."> style controls
            const control = this.toolbar ? this.toolbar.querySelector('.rte-font-size-select') : null;
            if (!control) return null;

            // gather numeric options from the control or its attached datalist
            let options = [];
            if (control.tagName === 'SELECT') {
                options = Array.from(control.options)
                    .map(o => o.value)
                    .filter(v => v)
                    .map(v => parseInt(v, 10));
            } else if (control.tagName === 'INPUT' && control.getAttribute('list')) {
                const dl = document.getElementById(control.getAttribute('list'));
                if (dl) {
                    options = Array.from(dl.options)
                        .map(o => o.value)
                        .filter(v => v)
                        .map(v => parseInt(v, 10));
                }
            }
            if (options.length === 0) {
                // nothing to compare against
                return null;
            }

            debugLog('📐 [Selection] font size control type:', control.tagName, 'options count:', options.length);

            try {
                const sel = window.getSelection();
                if (!sel || !sel.rangeCount) return null;
                
                const range = sel.getRangeAt(0);
                const nodes = this._getTextNodesInRange(range);
                if (nodes.length === 0) return null;

                const matches = new Set();
                for (const node of nodes) {
                    const el = node.parentElement || node;
                    const computed = window.getComputedStyle(el);
                    const px = parseInt((computed.fontSize || '').toString(), 10);
                    
                    if (Number.isNaN(px)) return null;
                    
                    // সবচেয়ে কাছের option খুঁজে বের করা
                    let closest = null;
                    options.forEach(opt => {
                        const diff = Math.abs(px - opt);
                        if (closest === null || diff < closest.diff) {
                            closest = { val: opt, diff };
                        }
                    });
                    
                    if (!closest) return null;
                    matches.add(String(closest.val));
                    
                    if (matches.size > 1) return null; // Multiple sizes
                }

                return matches.size === 1 ? Array.from(matches)[0] : null;
            } catch (e) {
                console.warn('❌ _getSelectionCommonFontSize error:', e);
                return null;
            }
        };

        // ========================================
        // SECTION 4: Selection Analysis
        // ========================================
        
        /**
         * বর্তমান selection এর সম্পূর্ণ বিশ্লেষণ
         * @returns {Object|null} - বিস্তারিত analysis অথবা null
         */
        RichTextEditor.prototype.analyzeSelection = function () {
            debugGroup('📊 === SELECTION ANALYSIS ===');
            const sel = window.getSelection();
            
            debugLog('🔍 Window Selection:', sel);
            debugLog('📌 rangeCount:', sel ? sel.rangeCount : 'No selection');
            
            if (!sel || sel.rangeCount === 0) {
                console.warn('⚠️ No selection available');
                debugGroupEnd();
                return null;
            }

            const range = sel.getRangeAt(0);
            const {
                startContainer,
                endContainer,
                startOffset,
                endOffset,
                collapsed
            } = range;

            debugLog('📍 START CONTAINER:', {
                nodeName: startContainer.nodeName,
                nodeType: startContainer.nodeType,
                offset: startOffset,
                content: startContainer.nodeValue ? startContainer.nodeValue.substring(0, 100) : null
            });
            
            debugLog('📍 END CONTAINER:', {
                nodeName: endContainer.nodeName,
                nodeType: endContainer.nodeType,
                offset: endOffset,
                content: endContainer.nodeValue ? endContainer.nodeValue.substring(0, 100) : null
            });

            // Selection এর মধ্যে থাকা text nodes খুঁজে বের করা
            const textNodes = this._getSelectedTextNodes(range);
            debugLog('📝 Text Nodes Selected:', textNodes.length);
            textNodes.forEach((nodeInfo, i) => {
                debugLog(`  [${i}]: "${nodeInfo.text}"`);
            });
            
            // Selection এর মধ্যে থাকা element nodes
            const elements = this._getSelectedElements(range);
            debugLog('🏷️ Elements Selected:', elements.length);
            elements.forEach((el, i) => {
                debugLog(`  [${i}]: <${el.tagName.toLowerCase()}> - ${el.className || 'no class'}`);
            });
            
            // Block elements
            const blockElements = elements.filter(el => this._isBlockElement(el));
            debugLog('📦 Block Elements:', blockElements.length);
            blockElements.forEach((el, i) => {
                debugLog(`  [${i}]: <${el.tagName.toLowerCase()}>`);
            });

            // বর্তমান formatting detect করা
            const formatting = this._detectCurrentFormatting(range);

            const result = {
                collapsed,
                text: range.toString(),
                textLength: range.toString().length,
                startContainer: {
                    nodeName: startContainer.nodeName,
                    nodeType: startContainer.nodeType,
                    offset: startOffset,
                    content: startContainer.nodeValue ? startContainer.nodeValue.substring(0, 50) : null
                },
                endContainer: {
                    nodeName: endContainer.nodeName,
                    nodeType: endContainer.nodeType,
                    offset: endOffset,
                    content: endContainer.nodeValue ? endContainer.nodeValue.substring(0, 50) : null
                },
                textNodeCount: textNodes.length,
                elementCount: elements.length,
                blockElementCount: blockElements.length,
                formatting,
                commonAncestor: range.commonAncestorContainer.nodeName,
                inEditor: this.editor.contains(range.commonAncestorContainer)
            };
            
            debugLog('✅ ANALYSIS RESULT:', result);
            debugLog(`📊 Summary: "${result.text}" (${result.textLength} chars, collapsed: ${result.collapsed})`);
            debugLog('✅ === END SELECTION ANALYSIS ===');
            debugGroupEnd();
            
            return result;
        };

        /**
         * Selection এ প্রভাবিত সকল text nodes খুঁজে বের করে
         * @param {Range} range - DOM Range object
         * @returns {Array} - Text node information array
         */
        RichTextEditor.prototype._getSelectedTextNodes = function (range) {
            const textNodes = [];
            const walker = document.createTreeWalker(
                range.commonAncestorContainer,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );

            let node;
            while (node = walker.nextNode()) {
                if (range.intersectsNode(node) && this.editor.contains(node)) {
                    const startOffset = node === range.startContainer ? range.startOffset : 0;
                    const endOffset = node === range.endContainer ? range.endOffset : node.length;
                    
                    textNodes.push({
                        node,
                        text: node.nodeValue.substring(startOffset, endOffset),
                        fullText: node.nodeValue,
                        startOffset,
                        endOffset,
                        inRange: true
                    });
                }
            }

            return textNodes;
        };

        /**
         * Selection এর মধ্যে সকল element nodes খুঁজে বের করে
         * @param {Range} range - DOM Range object
         * @returns {Array} - Element nodes array
         */
        RichTextEditor.prototype._getSelectedElements = function (range) {
            const elements = [];
            const walker = document.createTreeWalker(
                range.commonAncestorContainer,
                NodeFilter.SHOW_ELEMENT,
                null,
                false
            );

            let node;
            while (node = walker.nextNode()) {
                if (range.intersectsNode(node) && 
                    node !== this.editor && 
                    this.editor.contains(node)) {
                    elements.push(node);
                }
            }

            return elements;
        };

        /**
         * Element টি block-level কিনা চেক করে
         * @param {Element} element - DOM element
         * @returns {boolean} - Block element হলে true
         */
        RichTextEditor.prototype._isBlockElement = function (element) {
            const blockTags = [
                'P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
                'BLOCKQUOTE', 'PRE', 'UL', 'OL', 'LI', 
                'SECTION', 'ARTICLE', 'HEADER', 'FOOTER', 'NAV'
            ];
            return blockTags.includes(element.tagName);
        };

        /**
         * বর্তমান selection এর active formatting detect করে
         * @param {Range} range - DOM Range object
         * @returns {Object} - Formatting information
         */
        RichTextEditor.prototype._detectCurrentFormatting = function (range) {
            const elements = this._getSelectedElements(range);
            
            const formatting = {
                bold: elements.some(el => el.tagName === 'STRONG' || el.tagName === 'B'),
                italic: elements.some(el => el.tagName === 'EM' || el.tagName === 'I'),
                underline: elements.some(el => el.tagName === 'U'),
                strikethrough: elements.some(el => el.tagName === 'S' || el.tagName === 'STRIKE'),
                hasLink: elements.some(el => el.tagName === 'A'),
                hasImage: elements.some(el => el.tagName === 'IMG'),
                textColor: null,
                backgroundColor: null,
                fontFamily: this._getSelectionCommonFont(),
                fontSize: this._getSelectionCommonFontSize(),
                blockType: this.getCurrentBlockType()
            };

            // Common text color খুঁজে বের করা
            if (elements.length > 0) {
                const firstElement = elements[0];
                const computedStyle = window.getComputedStyle(firstElement);
                const color = computedStyle.color;
                if (color) {
                    formatting.textColor = this._rgbToHex ? this._rgbToHex(color) : color;
                }
                const bgColor = computedStyle.backgroundColor;
                if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)') {
                    formatting.backgroundColor = this._rgbToHex ? this._rgbToHex(bgColor) : bgColor;
                }
            }

            return formatting;
        };

        /**
         * বর্তমান block element type নির্ণয় করে (p, h1, h2, etc.)
         * @returns {string|null} - Block type অথবা null
         */
        RichTextEditor.prototype.getCurrentBlockType = function () {
            const sel = window.getSelection();
            
            if (!sel || sel.rangeCount === 0) return null;

            const range = sel.getRangeAt(0);
            let node = range.startContainer;

            // Parent block element খুঁজে বের করা
            while (node && node !== this.editor) {
                if (this._isBlockElement(node)) {
                    return node.tagName.toLowerCase();
                }
                node = node.parentNode;
            }

            return 'p'; // Default paragraph
        };

        /**
         * Formatting parent element খুঁজে বের করে
         * @param {Node} node - Optional node, না দিলে current selection
         * @returns {Element|null} - Formatting parent অথবা null
         */
        RichTextEditor.prototype.getFormattingParent = function (node = null) {
            const sel = window.getSelection();
            
            if (!node && sel && sel.rangeCount > 0) {
                node = sel.getRangeAt(0).startContainer;
            }

            if (!node) return null;

            const formattingTags = ['STRONG', 'EM', 'U', 'S', 'STRIKE', 'SPAN', 'A', 'FONT', 'B', 'I'];
            
            let parent = node.parentElement || node.parentNode;
            
            while (parent && parent !== this.editor) {
                if (formattingTags.includes(parent.tagName)) {
                    return parent;
                }
                parent = parent.parentElement;
            }

            return null;
        };

        // ========================================
        // SECTION 5: Utility Methods
        // ========================================
        
        /**
         * Selection এ text আছে কিনা চেক করে
         * @returns {boolean} - Text selection থাকলে true
         */
        RichTextEditor.prototype.hasSelection = function () {
            const sel = window.getSelection();
            return sel && sel.rangeCount > 0 && !sel.getRangeAt(0).collapsed;
        };

        /**
         * Selected text রিটার্ন করে
         * @returns {string} - Selected text অথবা empty string
         */
        RichTextEditor.prototype.getSelectedText = function () {
            const sel = window.getSelection();
            return sel ? sel.toString() : '';
        };

        /**
         * Selection এর HTML content রিটার্ন করে
         * @returns {string} - Selected HTML অথবা empty string
         */
        RichTextEditor.prototype.getSelectedHTML = function () {
            try {
                const sel = window.getSelection();
                if (!sel || sel.rangeCount === 0) return '';
                
                const range = sel.getRangeAt(0);
                const div = document.createElement('div');
                div.appendChild(range.cloneContents());
                return div.innerHTML;
            } catch (e) {
                console.error('❌ getSelectedHTML error:', e);
                return '';
            }
        };

        /**
         * RGB color কে HEX এ রূপান্তর করে
         * @param {string} rgb - RGB color string
         * @returns {string} - HEX color
         */
        RichTextEditor.prototype._rgbToHex = function (rgb) {
            try {
                // যদি ইতিমধ্যে hex হয়
                if (rgb.startsWith('#')) return rgb;
                
                const result = rgb.match(/\d+/g);
                if (!result || result.length < 3) return rgb;
                
                const r = parseInt(result[0]);
                const g = parseInt(result[1]);
                const b = parseInt(result[2]);
                
                return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
            } catch (e) {
                console.warn('❌ _rgbToHex error:', e);
                return rgb;
            }
        };

        /**
         * Selection কে একটি নির্দিষ্ট element এ সেট করে
         * @param {Element} element - Target element
         * @returns {boolean} - সফল হলে true
         */
        RichTextEditor.prototype.selectElement = function (element) {
            try {
                const range = document.createRange();
                range.selectNodeContents(element);
                
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
                
                debugLog('✅ Element selected:', element);
                return true;
            } catch (e) {
                console.error('❌ selectElement error:', e);
                return false;
            }
        };

        /**
         * Selection collapse করে (cursor position এ নিয়ে আসে)
         * @param {boolean} toStart - true হলে শুরুতে, false হলে শেষে
         * @returns {boolean} - সফল হলে true
         */
        RichTextEditor.prototype.collapseSelection = function (toStart = false) {
            try {
                const sel = window.getSelection();
                if (!sel || sel.rangeCount === 0) return false;
                
                const range = sel.getRangeAt(0);
                range.collapse(toStart);
                
                debugLog(`✅ Selection collapsed to ${toStart ? 'start' : 'end'}`);
                return true;
            } catch (e) {
                console.error('❌ collapseSelection error:', e);
                return false;
            }
        };

        /**
         * Selection এর range object রিটার্ন করে
         * @returns {Range|null} - Range object অথবা null
         */
        RichTextEditor.prototype.getSelectionRange = function () {
            try {
                const sel = window.getSelection();
                if (!sel || sel.rangeCount === 0) return null;
                return sel.getRangeAt(0);
            } catch (e) {
                console.error('❌ getSelectionRange error:', e);
                return null;
            }
        };

        debugLog('✅ Selection Manager installed successfully');
        return true;
    }

    // ========================================
    // Module Export
    // ========================================
    
    // CommonJS export
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installSelectionManager };
    }
    
    // Browser global export
    if (typeof window !== 'undefined') {
        window.installSelectionManager = installSelectionManager;
    }
    
    // ES6 export (যদি supported হয়)
    if (typeof global !== 'undefined') {
        global.installSelectionManager = installSelectionManager;
    }

})(typeof window !== 'undefined' ? window : typeof global !== 'undefined' ? global : {});

