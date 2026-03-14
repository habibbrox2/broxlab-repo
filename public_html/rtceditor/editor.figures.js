/**
 * Rich Text Editor - Figure & Content Management Helper
 * Handles figure selection, deletion, and related DOM operations.
 */
(function() {
    function installFigureHelpers(RTE) {
        /**
         * Handle figure click - toggle selected class
         */
        RTE.prototype.handleFigureClick = function(e) {
            if (!this.editor) return;
            const fig = e.target.closest('figure');
            this.editor.querySelectorAll('figure.rte-figure-selected').forEach(f => {
                f.classList.remove('rte-figure-selected');
            });
            if (fig) {
                fig.classList.add('rte-figure-selected');
            }
            setTimeout(() => this.updateButtonStates(), 10);
        };
        
        /**
         * Handle figure deletion via Backspace/Delete keys
         */
        RTE.prototype.handleFigureDeletion = function(e) {
            if (!this.editor) return;
            if ((e.key === 'Backspace' || e.key === 'Delete')) {
                const sel = window.getSelection();
                if (!sel || !sel.rangeCount) return;
                
                if (sel.isCollapsed) {
                    const range = sel.getRangeAt(0);
                    const container = range.startContainer;
                    const offset = range.startOffset;
                    let prevNode = null;
                    let nextNode = null;

                    if (container.nodeType === 3) { // Text node
                        if (offset === 0) {
                            prevNode = container.previousSibling;
                        } else if (offset === container.length) {
                            nextNode = container.nextSibling;
                        }
                    } else { // Element node
                        prevNode = container.childNodes[offset - 1] || null;
                        nextNode = container.childNodes[offset] || null;
                    }

                    const isFigure = (n) => n && n.nodeType === 1 && n.tagName === 'FIGURE';
                    
                    if (e.key === 'Backspace' && isFigure(prevNode)) {
                        e.preventDefault();
                        prevNode.parentNode.removeChild(prevNode);
                        this.updateHiddenInput();
                        this.saveToHistory();
                        return;
                    }
                    
                    if (e.key === 'Delete' && isFigure(nextNode)) {
                        e.preventDefault();
                        nextNode.parentNode.removeChild(nextNode);
                        this.updateHiddenInput();
                        this.saveToHistory();
                        return;
                    }
                }
            }
        };
        
        /**
         * Setup figure event handlers
         */
        RTE.prototype.setupFigureHandlers = function() {
            if (!this.editor) return;
            this.editor.addEventListener('click', (e) => this.handleFigureClick(e));
            this.editor.addEventListener('keydown', (e) => this.handleFigureDeletion(e));
        };

        if (window.RTE_debugLog) window.RTE_debugLog('figures', 'Figure helpers installed');
    }

    if (typeof window !== 'undefined') {
        window.installFigureHelpers = installFigureHelpers;
    }
})();
