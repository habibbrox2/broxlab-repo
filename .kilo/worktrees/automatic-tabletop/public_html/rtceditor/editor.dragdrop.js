/**
 * Rich Text Editor - Drag-Drop Image Handler Helper
 * Manages drag-over, drag-leave, and drop events for image upload.
 */
(function() {
    function installDragDropHelpers(RTE) {
        /**
         * Handle dragover event
         */
        RTE.prototype.handleDragOver = function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (this.editor) {
                this.editor.classList.add('rte-drag-over');
            }
        };
        
        /**
         * Handle dragleave event
         */
        RTE.prototype.handleDragLeave = function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (this.editor) {
                this.editor.classList.remove('rte-drag-over');
            }
        };
        
        /**
         * Handle drop event - process dropped files
         */
        RTE.prototype.handleDrop = function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (this.editor) {
                this.editor.classList.remove('rte-drag-over');
            }
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                for (let file of files) {
                    if (file.type.startsWith('image/')) {
                        const maybePromise = this.handleImageDrop(file);
                        if (maybePromise && typeof maybePromise.catch === 'function') {
                            maybePromise.catch((error) => {
                                console.warn('RTE drag-drop image upload failed:', error);
                            });
                        }
                    }
                }
            }
        };
        
        /**
         * Setup drag and drop event listeners
         */
        RTE.prototype.setupDragDropHandlers = function() {
            if (!this.editor) return;
            this.editor.addEventListener('dragover', (e) => this.handleDragOver(e));
            this.editor.addEventListener('dragleave', (e) => this.handleDragLeave(e));
            this.editor.addEventListener('drop', (e) => this.handleDrop(e));
        };

        if (window.RTE_debugLog) window.RTE_debugLog('dragdrop', 'Drag-drop helpers installed');
    }

    if (typeof window !== 'undefined') {
        window.installDragDropHelpers = installDragDropHelpers;
    }
})();
