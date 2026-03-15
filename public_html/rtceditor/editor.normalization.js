// Editor DOM Normalization Helper - Clean up, consolidate, and validate DOM structure
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
            global.RTE_debugLog('normalization', ...args);
            return;
        }
        if (global && global.RTE_DEBUG && typeof console.log === 'function') {
            console['log'](...args);
        }
    }

    function installNormalizationHelpers(RichTextEditor) {

    /**
     * Comprehensive DOM cleanup after operations
     */
    RichTextEditor.prototype.normalizeDOM = function () {
        try {
            this._removeEmptyNodes();
            this._mergeAdjacentSpans();
            this._fixInvalidNesting();
            this._removeEmptyFormatting();
            this._ensureEditorHasContent();
            debugLog('✅ DOM normalized');
        } catch (error) {
            console.error('❌ Error during normalization:', error);
        }
    };

    /**
     * Remove completely empty elements (text nodes and formatting elements)
     */
    RichTextEditor.prototype._removeEmptyNodes = function () {
        const emptyElements = this.editor.querySelectorAll(
            'strong, em, u, s, span, a, font, b, i'
        );

        const toRemove = [];

        emptyElements.forEach(element => {
            // Check if element is truly empty
            if (!element.textContent || !element.textContent.trim()) {
                toRemove.push(element);
            }
        });

        toRemove.forEach(element => {
            // Move children out before removing
            while (element.firstChild) {
                element.parentNode.insertBefore(element.firstChild, element);
            }
            element.parentNode.removeChild(element);
        });
    };

    /**
     * Remove empty text nodes (pure whitespace)
     */
    RichTextEditor.prototype._removeEmptyTextNodes = function (element = this.editor) {
        const nodesToRemove = [];
        
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );

        let node;
        while (node = walker.nextNode()) {
            // Only remove if pure whitespace and not in PRE
            if (!node.nodeValue.trim() && node.parentElement.tagName !== 'PRE') {
                nodesToRemove.push(node);
            }
        }

        nodesToRemove.forEach(node => {
            node.parentNode.removeChild(node);
        });
    };

    /**
     * Merge adjacent spans with same styles
     */
    RichTextEditor.prototype._mergeAdjacentSpans = function () {
        const spans = Array.from(this.editor.querySelectorAll('span'));
        const toRemove = [];

        for (let i = 0; i < spans.length - 1; i++) {
            const span = spans[i];
            const nextSpan = span.nextElementSibling;
            
            if (nextSpan && nextSpan.tagName === 'SPAN') {
                // Compare computed styles
                if (this._stylesMatch(span, nextSpan)) {
                    // Move next span's content into current span
                    while (nextSpan.firstChild) {
                        span.appendChild(nextSpan.firstChild);
                    }
                    toRemove.push(nextSpan);
                }
            }
        }

        toRemove.forEach(element => {
            if (element.parentNode) {
                element.parentNode.removeChild(element);
            }
        });
    };

    /**
     * Check if two elements have matching styles
     */
    RichTextEditor.prototype._stylesMatch = function (el1, el2) {
        if (el1.tagName !== el2.tagName) return false;

        const style1 = window.getComputedStyle(el1);
        const style2 = window.getComputedStyle(el2);

        // Compare key style properties
        const props = ['color', 'backgroundColor', 'fontSize', 'fontFamily', 'fontWeight', 'fontStyle', 'textDecoration'];
        
        return props.every(prop => {
            return style1[prop] === style2[prop];
        });
    };

    /**
     * Fix invalid nesting (e.g., block inside inline)
     */
    RichTextEditor.prototype._fixInvalidNesting = function () {
        // Inline elements shouldn't contain block elements
        const inlineElements = this.editor.querySelectorAll('span, strong, em, u, s, a, font');
        
        inlineElements.forEach(inline => {
            const blocks = inline.querySelectorAll('p, div, h1, h2, h3, h4, h5, h6, blockquote, ul, ol');
            blocks.forEach(block => {
                // Move block outside of inline
                inline.parentNode.insertBefore(block, inline);
            });
        });
    };

    /**
     * Remove empty formatting elements with special care
     */
    RichTextEditor.prototype._removeEmptyFormatting = function () {
        const formattingTags = ['STRONG', 'EM', 'U', 'S', 'A', 'SPAN', 'FONT', 'B', 'I'];
        
        this.editor.querySelectorAll(formattingTags.join(', ')).forEach(element => {
            // Check if element has any meaningful content
            const hasContent = element.textContent && element.textContent.trim().length > 0;
            const hasChildren = element.children.length > 0;

            if (!hasContent && !hasChildren) {
                // Remove completely empty element
                element.parentNode.removeChild(element);
            } else if (!hasContent && hasChildren) {
                // Has children but no text - unwrap and keep children
                while (element.firstChild) {
                    element.parentNode.insertBefore(element.firstChild, element);
                }
                element.parentNode.removeChild(element);
            }
        });
    };

    /**
     * Ensure editor always has content
     */
    RichTextEditor.prototype._ensureEditorHasContent = function () {
        if (!this.editor.innerHTML || !this.editor.innerHTML.trim()) {
            this.editor.innerHTML = '<p><br></p>';
        }
    };

    /**
     * Merge adjacent text formatting elements of same type
     * E.g., <strong>Hello</strong><strong>World</strong> → <strong>HelloWorld</strong>
     */
    RichTextEditor.prototype._mergeAdjacentFormatting = function () {
        const formattingTags = ['STRONG', 'EM', 'U', 'S', 'B', 'I'];
        
        formattingTags.forEach(tag => {
            const elements = Array.from(this.editor.querySelectorAll(tag));
            
            for (let i = 0; i < elements.length - 1; i++) {
                const el = elements[i];
                const nextEl = el.nextElementSibling;
                
                if (nextEl && nextEl.tagName === tag) {
                    // Move content from next element into current
                    while (nextEl.firstChild) {
                        el.appendChild(nextEl.firstChild);
                    }
                    nextEl.parentNode.removeChild(nextEl);
                }
            }
        });
    };

    /**
     * Normalize all formatting in editor
     * Removes deprecated tags and consolidates styles
     */
    RichTextEditor.prototype._normalizeFormatting = function () {
        // Replace deprecated <font> with <span>
        const fonts = this.editor.querySelectorAll('font');
        fonts.forEach(font => {
            const span = document.createElement('span');
            if (font.color) span.style.color = font.color;
            if (font.size) span.style.fontSize = font.size + 'px';
            if (font.face) span.style.fontFamily = font.face;
            
            while (font.firstChild) {
                span.appendChild(font.firstChild);
            }
            
            font.parentNode.replaceChild(span, font);
        });

        // Replace <b> with <strong>
        const bTags = this.editor.querySelectorAll('b');
        bTags.forEach(b => {
            const strong = document.createElement('strong');
            while (b.firstChild) {
                strong.appendChild(b.firstChild);
            }
            b.parentNode.replaceChild(strong, b);
        });

        // Replace <i> with <em>
        const iTags = this.editor.querySelectorAll('i:not(.icon, [class*="icon"])');
        iTags.forEach(i => {
            // Skip icon classes
            if (!i.className.includes('icon')) {
                const em = document.createElement('em');
                while (i.firstChild) {
                    em.appendChild(i.firstChild);
                }
                i.parentNode.replaceChild(em, i);
            }
        });
    };

    /**
     * Complete finalization after editing session
     */
    RichTextEditor.prototype.finalizeEdit = function () {
        this.normalizeDOM();
        this._normalizeFormatting();
        this._mergeAdjacentFormatting();
        this.updateHiddenInput();
        this.saveToHistory();
        debugLog('✅ Edit session finalized and saved');
        return true;
    };

    /**
     * Get editor HTML with cleaned structure
     */
    RichTextEditor.prototype.getCleanHTML = function () {
        // Make a copy
        const temp = this.editor.cloneNode(true);
        
        // Create temporary instance to normalize
        const tempDiv = document.createElement('div');
        tempDiv.appendChild(temp);

        // Remove all data attributes
        temp.querySelectorAll('*').forEach(el => {
            Array.from(el.attributes).forEach(attr => {
                if (attr.name.startsWith('data-')) {
                    el.removeAttribute(attr.name);
                }
            });
        });

        return temp.innerHTML;
    };

    return true;
}

    // Export for both CommonJS and ES6 module contexts
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installNormalizationHelpers };
    }
    if (typeof window !== 'undefined') {
        window.installNormalizationHelpers = installNormalizationHelpers;
    }
})(typeof window !== 'undefined' ? window : {});
