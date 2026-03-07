// Editor Image Helpers - backend upload and URL-based insertion
(function (global) {
    function sanitizeImageDimension(rawValue) {
        const value = String(rawValue || '').trim().toLowerCase();
        if (!value) return '';
        if (value === 'auto') return 'auto';
        if (/^\d+(\.\d+)?$/.test(value)) return `${value}px`;
        if (/^\d+(\.\d+)?(px|%)$/.test(value)) return value;
        return '';
    }

    function isSafeImageUrl(rawUrl) {
        const value = String(rawUrl || '').trim();
        if (!value) return false;

        const lower = value.toLowerCase();
        if (lower.startsWith('javascript:') || lower.startsWith('vbscript:')) return false;
        if (lower.startsWith('data:')) {
            return /^data:image\/(?:png|jpe?g|gif|webp|bmp|avif);base64,[a-z0-9+/=\s]+$/i.test(value);
        }
        if (lower.startsWith('http://') || lower.startsWith('https://')) return true;
        if (lower.startsWith('/') || lower.startsWith('./') || lower.startsWith('../')) return true;
        if (lower.startsWith('blob:')) return true;
        return false;
    }

    function isSafeUploadEndpoint(rawUrl) {
        const value = String(rawUrl || '').trim();
        if (!value) return false;
        const lower = value.toLowerCase();
        if (lower.startsWith('javascript:') || lower.startsWith('vbscript:') || lower.startsWith('data:')) {
            return false;
        }
        if (lower.startsWith('http://') || lower.startsWith('https://')) return true;
        if (lower.startsWith('/')) return true;
        if (lower.startsWith('./') || lower.startsWith('../')) return true;
        return false;
    }

    function buildImageStyle(width, height) {
        const parts = ['max-width: 100%'];
        if (width) parts.push(`width: ${width}`);
        parts.push(`height: ${height || 'auto'}`);
        return parts.join('; ') + ';';
    }

    function installImageHelpers(RichTextEditor) {
        RichTextEditor.prototype.getCsrfTokenForUpload = function (explicitToken = '') {
            const fromArg = String(explicitToken || '').trim();
            if (fromArg) return fromArg;

            const metaToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            if (metaToken) return metaToken;

            const wrapper = document.getElementById(`${this.editorId}-wrapper`);
            const formToken = wrapper?.closest('form')?.querySelector('input[name="csrf_token"]')?.value || '';
            if (formToken) return formToken;

            return document.querySelector('input[name="csrf_token"]')?.value || '';
        };

        RichTextEditor.prototype.getImageModalOptions = function (modal = null) {
            const imageModal = modal || document.getElementById(`${this.editorId}-image-modal`);
            const alt = imageModal?.querySelector('.rte-image-alt')?.value || '';
            const width = imageModal?.querySelector('.rte-image-width')?.value || '';
            const height = imageModal?.querySelector('.rte-image-height')?.value || '';

            return {
                alt: String(alt).trim(),
                width: sanitizeImageDimension(width),
                height: sanitizeImageDimension(height)
            };
        };

        RichTextEditor.prototype._getUploadUrl = function () {
            const attrUrl = this.wrapper?.dataset?.uploadUrl;
            if (attrUrl && isSafeUploadEndpoint(attrUrl)) return attrUrl;
            if (window.RTE_CONFIG?.uploadUrl && isSafeUploadEndpoint(window.RTE_CONFIG.uploadUrl)) {
                return window.RTE_CONFIG.uploadUrl;
            }
            return '/upload';
        };

        RichTextEditor.prototype.uploadEditorImage = async function (file, meta = {}) {
            if (!(file instanceof File)) {
                throw new Error('Please select a valid image file.');
            }
            if (!file.type || !file.type.startsWith('image/')) {
                throw new Error('Only image files are allowed.');
            }

            const csrfToken = this.getCsrfTokenForUpload(meta.csrfToken);
            if (!csrfToken) {
                throw new Error('Security token missing. Refresh and try again.');
            }

            const formData = new FormData();
            formData.append('image', file);
            formData.append('csrf_token', csrfToken);
            if (meta.alt) {
                formData.append('alt', String(meta.alt).trim());
            }

            const response = await fetch(this._getUploadUrl(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            });

            const data = await response.json().catch(() => ({}));
            if (response.status === 401) {
                throw new Error('Authentication required to upload images.');
            }
            if (!response.ok || data?.success !== true) {
                throw new Error(data?.error || `Image upload failed (${response.status}).`);
            }

            const uploadedUrl = data.file || data.full_url || data.url || '';
            if (!uploadedUrl) {
                throw new Error('Upload completed but no image URL was returned.');
            }

            return { ...data, uploadedUrl };
        };

        RichTextEditor.prototype.insertImageFromData = function (imageUrl, options = {}) {
            if (!imageUrl) return false;

            const src = this.escapeHtml(String(imageUrl));
            const altText = this.escapeHtml(options.alt || 'Image');
            const style = this.escapeHtml(buildImageStyle(options.width, options.height));
            const figureHtml = `<figure contenteditable="false"><img src="${src}" alt="${altText}" style="${style}"><figcaption contenteditable="true">Caption</figcaption></figure><p><br></p>`;

            try {
                this.restoreSelection();
            } catch (e) {
                console.warn('restoreSelection error in insertImage (images helper):', e);
            }

            let inserted = false;
            try {
                const beforeCount = this.editor.querySelectorAll('figure').length;
                document.execCommand('insertHTML', false, figureHtml);
                const afterCount = this.editor.querySelectorAll('figure').length;
                if (afterCount > beforeCount) inserted = true;
            } catch (e) {
                console.warn('insertHTML error (images helper):', e);
            }

            if (!inserted) {
                const div = document.createElement('div');
                div.innerHTML = figureHtml;
                while (div.firstChild) this.editor.appendChild(div.firstChild);
            }

            try {
                const figures = this.editor.querySelectorAll('figure');
                if (figures.length > 0) {
                    const lastFigure = figures[figures.length - 1];
                    const range = document.createRange();
                    range.setStartAfter(lastFigure);
                    range.collapse(true);
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            } catch (e) {
                console.warn('Cursor positioning error (images helper):', e);
            }

            this.updateHiddenInput();
            if (typeof this.autoGrowEditorHeight === 'function') {
                this.autoGrowEditorHeight();
            }
            this.saveToHistory();
            if (this.editor) {
                setTimeout(() => {
                    try { if (this && this.editor) this.editor.focus(); } catch (e) { /* ignore */ }
                }, 10);
            }
            return true;
        };

        RichTextEditor.prototype.insertImageFromURL = function () {
            const modal = document.getElementById(`${this.editorId}-image-modal`);
            if (!modal) return;

            const urlInput = modal.querySelector('.rte-image-url');
            const url = urlInput ? urlInput.value.trim() : '';
            if (!url || url === 'https://') {
                if (typeof this.showNotification === 'function') {
                    this.showNotification('Please enter a valid image URL', 'warning');
                }
                return;
            }
            if (!isSafeImageUrl(url)) {
                if (typeof this.showNotification === 'function') {
                    this.showNotification('Please enter a safe image URL (http/https/relative/data-image).', 'warning');
                }
                return;
            }

            const options = this.getImageModalOptions(modal);
            this.restoreSelection();
            this.insertImageFromData(url, options);
            this.closeModal('image');
        };

        RichTextEditor.prototype.insertUploadedImageFromModal = async function (triggerButton = null) {
            const modal = document.getElementById(`${this.editorId}-image-modal`);
            if (!modal) return;
            if (!(this._pendingImageFile instanceof File)) {
                if (typeof this.showNotification === 'function') {
                    this.showNotification('Please select an image first', 'warning');
                }
                return;
            }

            const options = this.getImageModalOptions(modal);
            const button = triggerButton || modal.querySelector('.rte-image-submit');
            const originalText = button ? button.textContent : '';
            if (button) {
                button.disabled = true;
                button.textContent = 'Uploading...';
            }

            try {
                const uploadResult = await this.uploadEditorImage(this._pendingImageFile, { alt: options.alt });
                this.restoreSelection();
                this.insertImageFromData(uploadResult.uploadedUrl, options);
                this.closeModal('image');
            } catch (error) {
                if (typeof this.showNotification === 'function') {
                    this.showNotification(error?.message || 'Image upload failed.', 'error');
                }
            } finally {
                if (button) {
                    button.disabled = false;
                    button.textContent = originalText;
                }
            }
        };

        return true;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installImageHelpers };
    }
    if (typeof window !== 'undefined') {
        window.installImageHelpers = installImageHelpers;
    }
})(typeof window !== 'undefined' ? window : {});
