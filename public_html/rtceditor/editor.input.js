/**
 * Rich Text Editor - Input & Content Handler Helper
 * Manages paste events, content sanitization, and placeholder updates.
 */
(function() {
    function installInputHelpers(RTE) {
        function isEditorStructurallyEmpty(editor) {
            if (!editor) return true;

            const text = String(editor.textContent || '')
                .replace(/\u200B/g, '')
                .replace(/\u00A0/g, ' ')
                .trim();
            if (text.length > 0) {
                return false;
            }

            // Media or structural content should not show placeholder.
            if (editor.querySelector('img, video, iframe, table, hr, ul, ol, blockquote, pre, figure')) {
                return false;
            }

            const compactHtml = String(editor.innerHTML || '')
                .replace(/\s+/g, '')
                .toLowerCase();

            return compactHtml === ''
                || compactHtml === '<br>'
                || compactHtml === '<p><br></p>'
                || compactHtml === '<div><br></div>'
                || compactHtml === '<p>&nbsp;</p>'
                || compactHtml === '<div>&nbsp;</div>';
        }
        const buildClipboardImageFile = (blob, index = 0) => {
            if (!(blob instanceof Blob)) return null;
            const mimeType = String(blob.type || '').toLowerCase() || 'image/png';
            const extMap = {
                'image/png': 'png',
                'image/jpeg': 'jpg',
                'image/jpg': 'jpg',
                'image/gif': 'gif',
                'image/webp': 'webp',
                'image/bmp': 'bmp',
                'image/svg+xml': 'svg'
            };
            const ext = extMap[mimeType] || 'png';
            const fileName = `clipboard-image-${Date.now()}-${index + 1}.${ext}`;

            if (typeof File === 'function') {
                try {
                    return new File([blob], fileName, { type: mimeType || blob.type || 'image/png' });
                } catch (err) {
                    // Fall through to blob fallback.
                }
            }

            blob.name = fileName;
            return blob;
        };
        const fileToDataURL = (file) => new Promise((resolve, reject) => {
            try {
                const reader = new FileReader();
                reader.onload = () => resolve(String(reader.result || ''));
                reader.onerror = () => reject(reader.error || new Error('Failed to read image file'));
                reader.readAsDataURL(file);
            } catch (err) {
                reject(err);
            }
        });

        /**
         * Insert clipboard HTML/text into editor after sanitization
         * @param {string} html
         * @param {string} text
         * @returns {boolean}
         */
        RTE.prototype.insertClipboardContent = function(html = '', text = '') {
            let normalizedHtml = String(html || '');
            const normalizedText = String(text || '').replace(/\r\n/g, '\n');

            if (!normalizedHtml && normalizedText) {
                const escapeFn = (window.RTE_utils && typeof window.RTE_utils.escapeHtml === 'function')
                    ? window.RTE_utils.escapeHtml
                    : (typeof this.escapeHtml === 'function' ? this.escapeHtml.bind(this) : (value) => String(value || ''));
                const escapedText = escapeFn(normalizedText);
                normalizedHtml = escapedText.replace(/\n/g, '<br>');
            }

            if (!normalizedHtml) {
                return false;
            }

            const cleanedHTML = typeof this.sanitizeHTML === 'function'
                ? this.sanitizeHTML(normalizedHtml)
                : normalizedHtml;

            try {
                const selection = window.getSelection();
                if (!selection) return false;

                let range = null;
                if (selection.rangeCount > 0) {
                    range = selection.getRangeAt(0);
                    if (!this.editor.contains(range.commonAncestorContainer)) {
                        range = null;
                    }
                }

                if (!range) {
                    range = document.createRange();
                    range.selectNodeContents(this.editor);
                    range.collapse(false);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }

                range.deleteContents();

                const fragment = range.createContextualFragment(cleanedHTML);
                range.insertNode(fragment);
                range.collapse(false);

                selection.removeAllRanges();
                selection.addRange(range);
                return true;
            } catch (err) {
                console.warn('Paste fallback:', err);
                try {
                    document.execCommand('insertHTML', false, cleanedHTML);
                    return true;
                } catch (fallbackErr) {
                    console.warn('insertHTML fallback error:', fallbackErr);
                    return false;
                }
            }
        };

        /**
         * Extract image files from DataTransfer/ClipboardData object
         * @param {DataTransfer|ClipboardData|null} clipboardData
         * @returns {Array<File|Blob>}
         */
        RTE.prototype.getClipboardImageFiles = function(clipboardData = null) {
            const files = [];
            if (!clipboardData) return files;

            // Prefer DataTransferItemList when available.
            try {
                const items = clipboardData.items ? Array.from(clipboardData.items) : [];
                items.forEach((item, index) => {
                    if (item && item.kind === 'file' && String(item.type || '').startsWith('image/')) {
                        const file = item.getAsFile ? item.getAsFile() : null;
                        if (file) {
                            files.push(file);
                        } else if (item.getType) {
                            // Some environments may expose Blob via getType only.
                            // This branch is handled in clipboard.read() flow.
                            window.RTE_debugLog('clipboard', `Image item ${index + 1} requires async getType`);
                        }
                    }
                });
            } catch (err) {
                console.warn('getClipboardImageFiles(items) error:', err);
            }

            // Fallback to FileList.
            if (files.length === 0) {
                try {
                    const fileList = clipboardData.files ? Array.from(clipboardData.files) : [];
                    fileList.forEach(file => {
                        if (file && String(file.type || '').startsWith('image/')) {
                            files.push(file);
                        }
                    });
                } catch (err) {
                    console.warn('getClipboardImageFiles(files) error:', err);
                }
            }

            return files;
        };

        /**
         * Paste one or more clipboard image files into editor
         * Uploads to backend when available, falls back to data URL insertion.
         * @param {Array<File|Blob>} imageFiles
         * @returns {Promise<boolean>}
         */
        RTE.prototype.pasteClipboardImages = async function(imageFiles = []) {
            if (!Array.isArray(imageFiles) || imageFiles.length === 0) return false;

            let insertedAny = false;

            for (let i = 0; i < imageFiles.length; i++) {
                const rawFile = imageFiles[i];
                if (!rawFile || !String(rawFile.type || '').startsWith('image/')) continue;

                let inserted = false;
                const altText = rawFile.name || `Pasted image ${i + 1}`;

                if (typeof this.uploadEditorImage === 'function' && typeof this.insertImageFromData === 'function') {
                    try {
                        const uploadResult = await this.uploadEditorImage(rawFile, { alt: altText });
                        if (uploadResult?.uploadedUrl) {
                            this.restoreSelection();
                            this.insertImageFromData(uploadResult.uploadedUrl, { alt: altText });
                            this.saveSelection();
                            inserted = true;
                        }
                    } catch (err) {
                        console.warn('Clipboard image upload failed, using local fallback:', err);
                    }
                }

                if (!inserted && typeof this.insertImageFromData === 'function') {
                    try {
                        const dataUrl = await fileToDataURL(rawFile);
                        if (dataUrl) {
                            this.restoreSelection();
                            this.insertImageFromData(dataUrl, { alt: altText });
                            this.saveSelection();
                            inserted = true;
                        }
                    } catch (err) {
                        console.warn('Clipboard image data URL fallback failed:', err);
                    }
                }

                if (inserted) {
                    insertedAny = true;
                }
            }

            return insertedAny;
        };

        /**
         * Identify non-portable image src values that should be replaced
         * using clipboard file payloads when available.
         * @param {string} src
         * @returns {boolean}
         */
        RTE.prototype.isClipboardImageSrcHydrationCandidate = function(src = '') {
            const value = String(src || '').trim().toLowerCase();
            if (!value) return true;

            return value.startsWith('data:image/')
                || value.startsWith('blob:')
                || value.startsWith('file:')
                || value.startsWith('cid:')
                || value.startsWith('webkit-fake-url:')
                || value === 'about:blank';
        };

        /**
         * Replace non-portable <img src> entries in clipboard HTML with
         * uploaded/dataURL sources from clipboard image files.
         * @param {string} html
         * @param {Array<File|Blob>} imageFiles
         * @returns {Promise<{html: string, usedCount: number, changed: boolean}>}
         */
        RTE.prototype.hydrateClipboardHtmlImages = async function(html = '', imageFiles = []) {
            const normalizedHtml = String(html || '');
            if (!normalizedHtml || !Array.isArray(imageFiles) || imageFiles.length === 0) {
                return { html: normalizedHtml, usedCount: 0, changed: false };
            }

            let container;
            try {
                container = document.createElement('div');
                container.innerHTML = normalizedHtml;
            } catch (err) {
                console.warn('hydrateClipboardHtmlImages parse failed:', err);
                return { html: normalizedHtml, usedCount: 0, changed: false };
            }

            const images = Array.from(container.querySelectorAll('img'));
            if (images.length === 0) {
                return { html: normalizedHtml, usedCount: 0, changed: false };
            }

            let usedCount = 0;
            let changed = false;

            for (let i = 0; i < images.length && usedCount < imageFiles.length; i++) {
                const img = images[i];
                const src = img.getAttribute('src') || '';
                if (!this.isClipboardImageSrcHydrationCandidate(src)) continue;

                const rawFile = imageFiles[usedCount];
                usedCount += 1;

                if (!rawFile || !String(rawFile.type || '').startsWith('image/')) {
                    continue;
                }

                const altText = img.getAttribute('alt') || rawFile.name || `Pasted image ${usedCount}`;
                let replacementSrc = '';

                if (typeof this.uploadEditorImage === 'function' && rawFile instanceof File) {
                    try {
                        const uploadResult = await this.uploadEditorImage(rawFile, { alt: altText });
                        if (uploadResult?.uploadedUrl) {
                            replacementSrc = uploadResult.uploadedUrl;
                        }
                    } catch (err) {
                        console.warn('Clipboard HTML image upload fallback to data URL:', err);
                    }
                }

                if (!replacementSrc) {
                    try {
                        replacementSrc = await fileToDataURL(rawFile);
                    } catch (err) {
                        console.warn('Clipboard HTML image data URL generation failed:', err);
                    }
                }

                if (replacementSrc) {
                    img.setAttribute('src', replacementSrc);
                    if (!img.getAttribute('alt')) {
                        img.setAttribute('alt', altText);
                    }
                    changed = true;
                }
            }

            return {
                html: container.innerHTML,
                usedCount,
                changed
            };
        };
        
        /**
         * Handle paste events - sanitize HTML content
         */
        RTE.prototype.handlePaste = async function(e) {
            e.preventDefault();

            const clipboardData = e.clipboardData || window.clipboardData;
            const html = clipboardData ? clipboardData.getData('text/html') : '';
            const text = clipboardData ? clipboardData.getData('text/plain') : '';
            const imageFiles = this.getClipboardImageFiles(clipboardData);
            let htmlForInsert = html;
            let remainingImageFiles = imageFiles;
            let inserted = false;

            if (htmlForInsert && imageFiles.length > 0) {
                const hydrated = await this.hydrateClipboardHtmlImages(htmlForInsert, imageFiles);
                htmlForInsert = hydrated.html;
                if (hydrated.usedCount > 0) {
                    remainingImageFiles = imageFiles.slice(hydrated.usedCount);
                }
            }

            // Prefer rich/plain text payload when present.
            if (htmlForInsert || text) {
                inserted = this.insertClipboardContent(htmlForInsert, text);
                if (inserted) {
                    this.updateHiddenInput();
                    if (typeof this.autoGrowEditorHeight === 'function') {
                        this.autoGrowEditorHeight();
                    }
                    this.saveToHistory();
                    this.updateButtonStates();
                    return;
                }
            }

            if (remainingImageFiles.length > 0) {
                this.saveSelection();
                inserted = await this.pasteClipboardImages(remainingImageFiles);
                if (inserted && typeof this.updateToolbarStates === 'function') {
                    setTimeout(() => this.updateToolbarStates(), 0);
                }
                return;
            }

            if (this.insertClipboardContent('', text)) {
                this.updateHiddenInput();
                if (typeof this.autoGrowEditorHeight === 'function') {
                    this.autoGrowEditorHeight();
                }
                this.saveToHistory();
                this.updateButtonStates();
            }
        };
        
        /**
         * Setup paste event listener
         */
        RTE.prototype.setupPasteHandler = function() {
            if (!this.editor) return;
            this.editor.addEventListener('paste', (e) => this.handlePaste(e));
        };

        /**
         * Copy selected editor content to system clipboard
         * Returns false so toolbar handler skips history updates for copy action.
         * @returns {Promise<boolean|false>}
         */
        RTE.prototype.copyToClipboard = async function() {
            if (!this.editor) return false;

            this.restoreSelection();
            this.editor.focus();

            const sel = window.getSelection();
            let selectedText = '';
            let selectedHTML = '';
            let execCommandSelectionPrepared = false;

            if (sel && sel.rangeCount > 0 && !sel.getRangeAt(0).collapsed) {
                const range = sel.getRangeAt(0);
                if (this.editor.contains(range.commonAncestorContainer)) {
                    selectedText = sel.toString();
                    selectedHTML = typeof this.getSelectedHTML === 'function' ? this.getSelectedHTML() : '';
                    execCommandSelectionPrepared = true;
                }
            }

            // Fallback for toolbar image selection (click-selected figure with collapsed range).
            if (!selectedHTML) {
                const selectedFigure = this.editor.querySelector('figure.rte-figure-selected');
                if (selectedFigure) {
                    selectedHTML = selectedFigure.outerHTML;
                    selectedText = selectedFigure.textContent ? selectedFigure.textContent.trim() : 'Image';
                    try {
                        if (typeof this.selectElement === 'function') {
                            this.selectElement(selectedFigure);
                            execCommandSelectionPrepared = true;
                        }
                    } catch (err) {
                        console.warn('Figure selection prep for copy failed:', err);
                    }
                }
            }

            if (!selectedHTML && !selectedText) {
                window.RTE_debugLog('clipboard', 'Copy skipped: no active selection');
                return false;
            }

            if (navigator.clipboard && window.isSecureContext) {
                try {
                    if (navigator.clipboard.write && window.ClipboardItem && selectedHTML) {
                        const item = new ClipboardItem({
                            'text/plain': new Blob([selectedText], { type: 'text/plain' }),
                            'text/html': new Blob([selectedHTML], { type: 'text/html' })
                        });
                        await navigator.clipboard.write([item]);
                        return false;
                    }

                    if (navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(selectedText);
                        return false;
                    }
                } catch (err) {
                    console.warn('Clipboard API copy failed, using execCommand fallback:', err);
                }
            }

            if (execCommandSelectionPrepared) {
                try {
                    document.execCommand('copy');
                } catch (err) {
                    console.warn('execCommand copy failed:', err);
                }
            }

            return false;
        };

        /**
         * Paste clipboard content into editor with sanitization
         * Returns false so toolbar handler skips duplicate history updates.
         * @returns {Promise<boolean|false>}
         */
        RTE.prototype.pasteFromClipboard = async function() {
            if (!this.editor) return false;

            this.restoreSelection();
            this.editor.focus();

            let html = '';
            let text = '';
            const imageFiles = [];
            let inserted = false;
            let insertedViaImageFlow = false;

            if (navigator.clipboard && window.isSecureContext) {
                if (navigator.clipboard.read) {
                    try {
                        const items = await navigator.clipboard.read();
                        for (let index = 0; index < items.length; index++) {
                            const item = items[index];
                            if (!html && item.types.includes('text/html')) {
                                const htmlBlob = await item.getType('text/html');
                                html = await htmlBlob.text();
                            }
                            if (!text && item.types.includes('text/plain')) {
                                const textBlob = await item.getType('text/plain');
                                text = await textBlob.text();
                            }
                            const imageTypes = item.types.filter(type => String(type).startsWith('image/'));
                            for (const imageType of imageTypes) {
                                try {
                                    const imageBlob = await item.getType(imageType);
                                    const fileLike = buildClipboardImageFile(imageBlob, index);
                                    if (fileLike) imageFiles.push(fileLike);
                                } catch (imageErr) {
                                    console.warn(`Clipboard image read failed (${imageType}):`, imageErr);
                                }
                            }
                            if (html && text) break;
                        }
                    } catch (err) {
                        console.warn('Clipboard read() failed:', err);
                    }
                }

                if (!html && !text && navigator.clipboard.readText) {
                    try {
                        text = await navigator.clipboard.readText();
                    } catch (err) {
                        console.warn('Clipboard readText() failed:', err);
                    }
                }
            }

            let htmlForInsert = html;
            let remainingImageFiles = imageFiles;

            if (htmlForInsert && imageFiles.length > 0) {
                const hydrated = await this.hydrateClipboardHtmlImages(htmlForInsert, imageFiles);
                htmlForInsert = hydrated.html;
                if (hydrated.usedCount > 0) {
                    remainingImageFiles = imageFiles.slice(hydrated.usedCount);
                }
            }

            if (htmlForInsert || text) {
                inserted = this.insertClipboardContent(htmlForInsert, text);
            }

            if (!inserted && remainingImageFiles.length > 0) {
                insertedViaImageFlow = await this.pasteClipboardImages(remainingImageFiles);
                inserted = insertedViaImageFlow;
            }

            if (!inserted) {
                try {
                    inserted = document.execCommand('paste');
                } catch (err) {
                    console.warn('execCommand paste failed:', err);
                }
            }

            if (inserted && !insertedViaImageFlow) {
                this.updateHiddenInput();
                if (typeof this.autoGrowEditorHeight === 'function') {
                    this.autoGrowEditorHeight();
                }
                this.saveToHistory();
                this.updateButtonStates();
            }

            if (inserted && typeof this.updateToolbarStates === 'function') {
                setTimeout(() => this.updateToolbarStates(), 0);
            }

            return false;
        };
        
        /**
         * Update placeholder visibility
         */
        RTE.prototype.updatePlaceholder = function() {
            if (!this.editor || !this.wrapper) return;

            const isEmpty = isEditorStructurallyEmpty(this.editor);
            this.wrapper.classList.toggle('rte-empty', isEmpty);
            this.editor.classList.toggle('rte-placeholder', isEmpty);
        };
        
        /**
         * Update hidden input with editor content
         */
        RTE.prototype.updateHiddenInput = function() {
            if (!this.editor || !this.hiddenInput) return;
            this.hiddenInput.value = this.editor.innerHTML;
            if (this.currentView === 'source' && this.sourceView) {
                this.sourceView.value = this.editor.innerHTML;
            }
        };

        if (window.RTE_debugLog) window.RTE_debugLog('input', 'Input helpers installed');
    }

    if (typeof window !== 'undefined') {
        window.installInputHelpers = installInputHelpers;
    }
})();
