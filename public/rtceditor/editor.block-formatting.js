// Editor Block Formatting Helper - Heading, paragraph, alignment, and block-level formatting
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
            global.RTE_debugLog('block-format', ...args);
            return;
        }
        if (global && global.RTE_DEBUG && typeof console.log === 'function') {
            console['log'](...args);
        }
    }

    function installBlockFormattingHelpers(RichTextEditor) {

    /**
     * Apply heading format to selected block
     * level: 1-6 for H1-H6
     */
    RichTextEditor.prototype.applyHeading = function (level) {
        debugLog(`📰 [Block Format] applyHeading called for H${level}`);
        
        const sel = window.getSelection();
        
        if (!sel || sel.rangeCount === 0) {
            console.warn('⚠️ [Block Format] No selection for heading');
            return false;
        }

        try {
            const range = sel.getRangeAt(0);
            debugLog(`📍 [Block Format] Heading range:`, {
                startContainer: range.startContainer.nodeName,
                endContainer: range.endContainer.nodeName,
                collapsed: range.collapsed
            });
            
            const elements = this._getSelectedElements(range);
            debugLog(`🔍 [Block Format] Selected elements count: ${elements.length}`);
            
            const blockElements = elements.filter(el => this._isBlockElement(el));
            debugLog(`📦 [Block Format] Block elements found: ${blockElements.length}`);

            if (blockElements.length === 0) {
                // Wrap selection in heading
                debugLog(`➕ [Block Format] Wrapping selection in H${level}`);
                return this._wrapInHeading(range, level);
            }

            // Transform existing blocks to heading
            debugLog(`🔄 [Block Format] Transforming ${blockElements.length} block(s) to H${level}`);
            blockElements.forEach((block, idx) => {
                debugLog(`  [${idx}] <${block.tagName}> → <H${level}>`);
                const heading = document.createElement(`H${level}`);
                
                // Copy content and attributes
                while (block.firstChild) {
                    heading.appendChild(block.firstChild);
                }
                
                // Preserve alignment if exists
                if (block.style.textAlign) {
                    heading.style.textAlign = block.style.textAlign;
                }

                block.parentNode.replaceChild(heading, block);
            });

            this.updateHiddenInput();
            this.saveToHistory();
            debugLog(`✅ Applied H${level} heading`);
            return true;

        } catch (error) {
            console.error('❌ Error applying heading:', error);
            return false;
        }
    };

    /**
     * Wrap selection in heading element
     */
    RichTextEditor.prototype._wrapInHeading = function (range, level) {
        try {
            const heading = document.createElement(`H${level}`);
            const content = range.extractContents();
            heading.appendChild(content);
            range.insertNode(heading);
            
            // Re-select the heading
            const newRange = document.createRange();
            newRange.selectNodeContents(heading);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(newRange);
            
            return true;
        } catch (error) {
            console.error('❌ Error wrapping in heading:', error);
            return false;
        }
    };

    /**
     * Convert current block to paragraph
     */
    RichTextEditor.prototype.convertToParagraph = function () {
        const sel = window.getSelection();
        
        if (!sel || sel.rangeCount === 0) {
            console.warn('⚠️ No selection to convert');
            return false;
        }

        try {
            const range = sel.getRangeAt(0);
            const elements = this._getSelectedElements(range);
            const blockElements = elements.filter(el => this._isBlockElement(el));

            blockElements.forEach(block => {
                if (block.tagName !== 'P') {
                    const p = document.createElement('p');
                    
                    while (block.firstChild) {
                        p.appendChild(block.firstChild);
                    }
                    
                    // Preserve styles
                    if (block.style.textAlign) {
                        p.style.textAlign = block.style.textAlign;
                    }

                    block.parentNode.replaceChild(p, block);
                }
            });

            this.updateHiddenInput();
            this.saveToHistory();
            debugLog('✅ Converted to paragraph');
            return true;
        } catch (error) {
            console.error('❌ Error converting to paragraph:', error);
            return false;
        }
    };

    /**
     * Apply text alignment to selected blocks
     */
    RichTextEditor.prototype.applyAlignment = function (alignment) {
        const sel = window.getSelection();
        
        if (!sel || sel.rangeCount === 0) {
            console.warn('⚠️ No selection for alignment');
            return false;
        }

        try {
            const range = sel.getRangeAt(0);
            const elements = this._getSelectedElements(range);
            const blockElements = elements.filter(el => this._isBlockElement(el));

            if (blockElements.length === 0) {
                // Apply to editor itself or wrap content
                this.editor.style.textAlign = alignment;
            } else {
                blockElements.forEach(block => {
                    block.style.textAlign = alignment;
                });
            }

            this.updateHiddenInput();
            this.saveToHistory();
            debugLog(`✅ Applied alignment: ${alignment}`);
            return true;
        } catch (error) {
            console.error('❌ Error applying alignment:', error);
            return false;
        }
    };

    /**
     * Get current alignment of selected block
     */
    RichTextEditor.prototype.getCurrentAlignment = function () {
        const sel = window.getSelection();
        
        if (!sel || sel.rangeCount === 0) return 'left';

        const range = sel.getRangeAt(0);
        let node = range.startContainer;

        // Find parent block element
        while (node && node !== this.editor) {
            if (this._isBlockElement(node)) {
                return window.getComputedStyle(node).textAlign || 'left';
            }
            node = node.parentNode;
        }

        return 'left';
    };

    /**
     * Toggle blockquote on selection
     */
    RichTextEditor.prototype.toggleBlockquote = function () {
        const sel = window.getSelection();
        
        if (!sel || sel.rangeCount === 0) return false;

        try {
            const range = sel.getRangeAt(0);
            const elements = this._getSelectedElements(range);
            const blockElements = elements.filter(el => this._isBlockElement(el));

            if (blockElements.some(el => el.tagName === 'BLOCKQUOTE')) {
                // Remove blockquote
                blockElements.forEach(block => {
                    if (block.tagName === 'BLOCKQUOTE') {
                        while (block.firstChild) {
                            block.parentNode.insertBefore(block.firstChild, block);
                        }
                        block.parentNode.removeChild(block);
                    }
                });
            } else {
                // Add blockquote
                blockElements.forEach(block => {
                    if (block.tagName !== 'BLOCKQUOTE') {
                        const quote = document.createElement('blockquote');
                        while (block.firstChild) {
                            quote.appendChild(block.firstChild);
                        }
                        block.parentNode.replaceChild(quote, block);
                    }
                });
            }

            this.updateHiddenInput();
            this.saveToHistory();
            debugLog('✅ Blockquote toggled');
            return true;
        } catch (error) {
            console.error('❌ Error toggling blockquote:', error);
            return false;
        }
    };

    return true;
}

    // Export for both CommonJS and ES6 module contexts
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installBlockFormattingHelpers };
    }
    if (typeof window !== 'undefined') {
        window.installBlockFormattingHelpers = installBlockFormattingHelpers;
    }
})(typeof window !== 'undefined' ? window : {});
