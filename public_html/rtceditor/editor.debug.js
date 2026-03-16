/**
 * Rich Text Editor Debug Helper
 * Provides utilities for detailed selection and state debugging
 */

(function(global) {
    // Global debug object
    global.RTE_DEBUG = {
        // Current editor instance
        editor: null,
        
        /**
         * Attach debug to an editor instance
         */
        attach: function(editorInstance) {
            this.editor = editorInstance;
            console.log('🐛 RTE_DEBUG attached to editor:', editorInstance.editorId);
            this.printHelp();
        },
        
        /**
         * Print all available debug commands
         */
        printHelp: function() {
            console.group('🐛 === RTE DEBUG COMMANDS ===');
            console.log('Available debug commands:');
            console.log('');
            console.log('📊 RTE_DEBUG.selection() - Get current selection details');
            console.log('📝 RTE_DEBUG.getText() - Get selected text');
            console.log('🔍 RTE_DEBUG.analyze() - Full selection analysis');
            console.log('💾 RTE_DEBUG.saveSelection() - Save current selection');
            console.log('🔄 RTE_DEBUG.restoreSelection() - Restore saved selection');
            console.log('📜 RTE_DEBUG.getContent() - Get editor HTML content');
            console.log('🎯 RTE_DEBUG.focusEditor() - Focus editor');
            console.log('🗑️ RTE_DEBUG.clearContent() - Clear all content');
            console.log('🔄 RTE_DEBUG.syncToolbar() - Force toolbar sync');
            console.log('📋 RTE_DEBUG.dumpHistory() - Show edit history');
            console.log('🔌 RTE_DEBUG.checkHelpers() - Check loaded helper modules');
            console.log('');
            console.log('💡 Usage example:');
            console.log('  RTE_DEBUG.analysis()  // Full selection analysis');
            console.log('  RTE_DEBUG.selection() // Current selection info');
            console.groupEnd();
        },
        
        /**
         * Get current selection information
         */
        selection: function() {
            if (!this.editor) {
                console.error('❌ No editor attached');
                return null;
            }
            
            console.group('🔍 === SELECTION INFO ===');
            const sel = window.getSelection();
            
            console.log('📌 Window Selection:', sel);
            console.log('📊 rangeCount:', sel ? sel.rangeCount : 0);
            
            if (!sel || sel.rangeCount === 0) {
                console.warn('⚠️ No selection');
                console.groupEnd();
                return null;
            }
            
            const range = sel.getRangeAt(0);
            const text = range.toString();
            
            console.log('📍 START CONTAINER:', {
                type: range.startContainer.nodeType,
                name: range.startContainer.nodeName,
                offset: range.startOffset,
                content: range.startContainer.nodeValue?.substring(0, 100)
            });
            
            console.log('📍 END CONTAINER:', {
                type: range.endContainer.nodeType,
                name: range.endContainer.nodeName,
                offset: range.endOffset,
                content: range.endContainer.nodeValue?.substring(0, 100)
            });
            
            console.log('📝 SELECTED TEXT:', `"${text}"`);
            console.log('📏 TEXT LENGTH:', text.length);
            console.log('🔄 COLLAPSED:', range.collapsed);
            console.log('🏠 COMMON ANCESTOR:', range.commonAncestorContainer.nodeName);
            console.log(
                '📌 IN EDITOR:',
                this.editor.editor && this.editor.editor.contains
                    ? this.editor.editor.contains(range.commonAncestorContainer)
                    : this.editor.contains(range.commonAncestorContainer)
            );
            
            console.groupEnd();
            
            return {
                selection: sel,
                range: range,
                text: text,
                textLength: text.length,
                collapsed: range.collapsed,
                startOffset: range.startOffset,
                endOffset: range.endOffset
            };
        },
        
        /**
         * Get selected text only
         */
        getText: function() {
            if (!this.editor) {
                console.error('❌ No editor attached');
                return null;
            }
            
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return '';
            
            const text = sel.getRangeAt(0).toString();
            console.log('📝 Selected text:', `"${text}"`);
            return text;
        },
        
        /**
         * Full selection analysis using editor method
         */
        analyze: function() {
            if (!this.editor) {
                console.error('❌ No editor attached');
                return null;
            }
            
            if (typeof this.editor.analyzeSelection === 'function') {
                return this.editor.analyzeSelection();
            } else {
                console.warn('⚠️ analyzeSelection method not available');
                return this.selection();
            }
        },
        
        /**
         * Save current selection
         */
        saveSelection: function() {
            if (!this.editor) {
                console.error('❌ No editor attached');
                return;
            }
            
            if (typeof this.editor.saveSelection === 'function') {
                this.editor.saveSelection();
                console.log('✅ Selection saved');
            } else {
                console.warn('⚠️ saveSelection method not available');
            }
        },
        
        /**
         * Restore saved selection
         */
        restoreSelection: function() {
            if (!this.editor) {
                console.error('❌ No editor attached');
                return;
            }
            
            if (typeof this.editor.restoreSelection === 'function') {
                this.editor.restoreSelection();
                console.log('✅ Selection restored');
            } else {
                console.warn('⚠️ restoreSelection method not available');
            }
        },
        
        /**
         * Get editor content
         */
        getContent: function() {
            if (!this.editor) {
                console.error('❌ No editor attached');
                return null;
            }
            
            const content = this.editor.editor.innerHTML;
            console.group('📄 === EDITOR CONTENT ===');
            console.log('HTML:');
            console.log(content);
            console.groupEnd();
            return content;
        },
        
        /**
         * Focus editor
         */
        focusEditor: function() {
            if (!this.editor || !this.editor.editor) {
                console.error('❌ No editor attached');
                return;
            }
            
            this.editor.editor.focus();
            console.log('✅ Editor focused');
        },
        
        /**
         * Clear editor content
         */
        clearContent: function() {
            if (!this.editor) {
                console.error('❌ No editor attached');
                return;
            }
            
            this.editor.editor.innerHTML = '<p><br></p>';
            this.editor.updateHiddenInput();
            this.editor.saveToHistory();
            console.log('🗑️ Editor content cleared');
        },
        
        /**
         * Force toolbar sync
         */
        syncToolbar: function() {
            if (!this.editor) {
                console.error('❌ No editor attached');
                return;
            }
            
            console.log('🔄 Syncing toolbar...');
            
            try {
                if (typeof this.editor.updateButtonStates === 'function') {
                    this.editor.updateButtonStates();
                }
            } catch (e) {
                console.warn('⚠️ updateButtonStates error:', e.message);
            }
            
            try {
                if (typeof this.editor.syncHeadingSelect === 'function') {
                    this.editor.syncHeadingSelect();
                }
            } catch (e) {
                console.warn('⚠️ syncHeadingSelect error:', e.message);
            }
            
            try {
                if (typeof this.editor.syncFontSelect === 'function') {
                    this.editor.syncFontSelect();
                }
            } catch (e) {
                console.warn('⚠️ syncFontSelect error:', e.message);
            }
            
            try {
                if (typeof this.editor.syncFontSizeSelect === 'function') {
                    this.editor.syncFontSizeSelect();
                }
            } catch (e) {
                console.warn('⚠️ syncFontSizeSelect error:', e.message);
            }
            
            try {
                if (typeof this.editor.syncColorInputs === 'function') {
                    this.editor.syncColorInputs();
                }
            } catch (e) {
                console.warn('⚠️ syncColorInputs error:', e.message);
            }
            
            console.log('✅ Toolbar sync complete');
        },
        
        /**
         * Dump edit history
         */
        dumpHistory: function() {
            if (!this.editor) {
                console.error('❌ No editor attached');
                return;
            }
            
            console.group('📋 === EDIT HISTORY ===');
            console.log('📊 Total entries:', this.editor.history.length);
            console.log('🔖 Current index:', this.editor.historyIndex);
            console.log('');
            
            this.editor.history.forEach((entry, i) => {
                const marker = i === this.editor.historyIndex ? '▶️ ' : '  ';
                const timestamp = new Date(entry.timestamp).toLocaleTimeString();
                const preview = entry.html.substring(0, 60).replace(/<[^>]*>/g, '').trim();
                console.log(`${marker}[${i}] ${timestamp} - "${preview}..."`);
            });
            
            console.groupEnd();
        },
        
        /**
         * Check loaded helper modules
         */
        checkHelpers: function() {
            console.group('🔌 === LOADED HELPER MODULES ===');
            
            const methods = [
                'saveSelection',
                'restoreSelection',
                'selectAll',
                'analyzeSelection',
                'applyHeading',
                'convertToParagraph',
                'applyAlignment',
                'toggleBlockquote',
                'normalizeDOM',
                'sanitizeHTML',
                'applyColor',
                'setTextColor',
                'setBackgroundColor',
                'updateButtonStates',
                'syncHeadingSelect',
                'syncFontSelect',
                'syncFontSizeSelect',
                'insertLink',
                'insertVideo',
                'undo',
                'redo',
                'setupModals'
            ];
            
            let loaded = 0;
            let missing = 0;
            
            methods.forEach(method => {
                if (typeof this.editor[method] === 'function') {
                    console.log(`✅ ${method}`);
                    loaded++;
                } else {
                    console.warn(`❌ ${method} - NOT LOADED`);
                    missing++;
                }
            });
            
            console.log('');
            console.log(`📊 Loaded: ${loaded}/${methods.length}`);
            if (missing > 0) {
                console.warn(`⚠️ Missing: ${missing} methods`);
            }
            
            console.groupEnd();
        },
        
        /**
         * Log detailed DOM structure of editor
         */
        dumpDOM: function(maxDepth = 3) {
            if (!this.editor) {
                console.error('❌ No editor attached');
                return;
            }
            
            console.group('🌳 === EDITOR DOM STRUCTURE ===');
            
            const logNode = (node, depth = 0) => {
                if (depth > maxDepth) return;
                
                const indent = '  '.repeat(depth);
                if (node.nodeType === 1) { // Element
                    const attrs = node.attributes.length > 0 
                        ? ` [${Array.from(node.attributes).map(a => `${a.name}="${a.value}"`).join(', ')}]`
                        : '';
                    console.log(`${indent}<${node.tagName.toLowerCase()}>${attrs}`);
                } else if (node.nodeType === 3) { // Text
                    const text = node.nodeValue.trim();
                    if (text) {
                        console.log(`${indent}"${text.substring(0, 50)}${text.length > 50 ? '...' : ''}"`);
                    }
                }
                
                for (let child of node.childNodes) {
                    logNode(child, depth + 1);
                }
            };
            
            logNode(this.editor.editor);
            console.groupEnd();
        }
    };
    
    // Auto-attach to editor when ready
    if (typeof document !== 'undefined') {
        document.addEventListener('rte:ready', (e) => {
            if (e.detail && e.detail.editor) {
                global.RTE_DEBUG.attach(e.detail.editor);
            }
        });
    }
    
    console.log('🐛 Rich Text Editor Debug Helper loaded');
    console.log('💡 Type: RTE_DEBUG.printHelp() for available commands');

})(typeof window !== 'undefined' ? window : {});
