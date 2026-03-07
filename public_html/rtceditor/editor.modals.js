// Editor Modals Helpers - Setup modal event handlers and modal actions
(function(global) {
    function installModalHelpers(RichTextEditor) {
    const bindOnce = (el, key) => {
        if (!el) return false;
        const attr = `data-rte-bound-${key}`;
        if (el.getAttribute(attr) === '1') return false;
        el.setAttribute(attr, '1');
        return true;
    };
    const isVisibleElement = (el) => {
        if (!el) return false;
        if (el.getAttribute('aria-hidden') === 'true') return false;
        const style = window.getComputedStyle(el);
        if (!style || style.visibility === 'hidden' || style.display === 'none') return false;
        return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    };

    // Setup modals
    RichTextEditor.prototype.setupModals = function () {
        // Link modal
        this.setupLinkModal();
        // Image modal
        this.setupImageModal();
        // Video modal
        this.setupVideoModal();
        // Special char modal
        this.setupSpecialCharModal();
        // Close on background click
        this.setupModalBackgroundClose();
        // Keyboard accessibility and focus trap
        this.setupModalAccessibility();
    };

    RichTextEditor.prototype._isSafeLinkUrl = function (rawUrl) {
        const value = String(rawUrl || '').trim();
        if (!value) return false;

        const lower = value.toLowerCase();
        if (lower.startsWith('javascript:') || lower.startsWith('vbscript:') || lower.startsWith('data:')) {
            return false;
        }

        return lower.startsWith('http://')
            || lower.startsWith('https://')
            || lower.startsWith('mailto:')
            || lower.startsWith('tel:')
            || lower.startsWith('/')
            || lower.startsWith('./')
            || lower.startsWith('../')
            || lower.startsWith('#');
    };

    RichTextEditor.prototype._isSafeMediaUrl = function (rawUrl) {
        const value = String(rawUrl || '').trim();
        if (!value) return false;

        const lower = value.toLowerCase();
        if (lower.startsWith('javascript:') || lower.startsWith('vbscript:') || lower.startsWith('data:')) {
            return false;
        }

        return lower.startsWith('http://')
            || lower.startsWith('https://')
            || lower.startsWith('/')
            || lower.startsWith('./')
            || lower.startsWith('../')
            || lower.startsWith('blob:');
    };

    RichTextEditor.prototype.getYouTubeVideoId = function (rawUrl) {
        const url = String(rawUrl || '').trim();
        if (!url) return null;
        const match = url.match(
            /(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,15})/i
        );
        return match ? match[1] : null;
    };

    RichTextEditor.prototype.getVimeoVideoId = function (rawUrl) {
        const url = String(rawUrl || '').trim();
        if (!url) return null;
        const match = url.match(/vimeo\.com\/(?:video\/)?(\d{6,12})/i);
        return match ? match[1] : null;
    };

    // Link modal
    RichTextEditor.prototype.setupLinkModal = function () {
        const linkModal = document.getElementById(`${this.editorId}-link-modal`);
        if (!linkModal || !bindOnce(linkModal, 'link')) return;
        const linkSubmit = linkModal.querySelector('.rte-link-submit');
        const linkClose = linkModal.querySelector('.rte-modal-close');
        const cancelBtn = linkModal.querySelector('.rte-btn-cancel');
        if (linkSubmit) linkSubmit.addEventListener('click', () => this.insertLink());
        if (linkClose) linkClose.addEventListener('click', () => this.closeModal('link'));
        if (cancelBtn) cancelBtn.addEventListener('click', () => this.closeModal('link'));
        const urlInput = linkModal.querySelector('.rte-link-url');
        if (urlInput) urlInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); this.insertLink(); }
        });
    };

    // Image modal
    RichTextEditor.prototype.setupImageModal = function () {
        const imageModal = document.getElementById(`${this.editorId}-image-modal`);
        if (!imageModal || !bindOnce(imageModal, 'image')) return;
        const imageSubmit = imageModal.querySelector('.rte-image-submit');
        const imageClose = imageModal.querySelector('.rte-modal-close');
        const cancelBtn = imageModal.querySelector('.rte-btn-cancel');
        const imageTabs = imageModal.querySelectorAll('.rte-image-tab-btn');
        const imageUrlInput = imageModal.querySelector('.rte-image-url');
        const fileInput = imageModal.querySelector('.rte-file-input');
        const urlTabContent = imageModal.querySelector('.rte-image-tab-url-content');
        const uploadTabContent = imageModal.querySelector('.rte-image-tab-upload-content');
        const urlPreviewImg = urlTabContent ? urlTabContent.querySelector('.rte-image-preview') : null;
        const uploadPreviewArea = uploadTabContent ? uploadTabContent.querySelector('.rte-image-preview-area') : null;
        const uploadPreviewImg = uploadTabContent ? uploadTabContent.querySelector('.rte-image-preview') : null;
        const previewInsertBtn = document.getElementById(`${this.editorId}-image-insert`);
        const previewCancelBtn = document.getElementById(`${this.editorId}-image-cancel`);

        const clearPendingUploadPreview = () => {
            if (this._pendingImagePreviewUrl) {
                URL.revokeObjectURL(this._pendingImagePreviewUrl);
                this._pendingImagePreviewUrl = null;
            }
            this._pendingImageFile = null;
            if (fileInput) fileInput.value = '';
            if (uploadPreviewImg) uploadPreviewImg.src = '';
            if (uploadPreviewArea) uploadPreviewArea.style.display = 'none';
        };

        if (imageSubmit) {
            imageSubmit.addEventListener('click', async () => {
                const activeTab = imageModal.querySelector('.rte-image-tab-btn.active')?.dataset?.tab || 'url';
                if (activeTab === 'upload') {
                    await this.insertUploadedImageFromModal(imageSubmit);
                    return;
                }
                this.insertImageFromURL();
            });
        }
        if (imageClose) imageClose.addEventListener('click', () => this.closeModal('image'));
        if (cancelBtn) cancelBtn.addEventListener('click', () => this.closeModal('image'));

        imageTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                imageTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const tabName = tab.dataset.tab;
                const tabContents = imageModal.querySelectorAll('.rte-image-tab-content');
                tabContents.forEach(content => content.style.display = 'none');
                const targetContent = imageModal.querySelector(`.rte-image-tab-${tabName}-content`);
                if (targetContent) targetContent.style.display = 'block';
            });
        });

        if (imageUrlInput) {
            imageUrlInput.addEventListener('input', () => {
                const url = imageUrlInput.value.trim();
                if (url && url !== 'https://') {
                    if (urlPreviewImg) {
                        urlPreviewImg.src = url;
                        urlPreviewImg.style.display = 'block';
                    }
                } else if (urlPreviewImg) {
                    urlPreviewImg.src = '';
                    urlPreviewImg.style.display = 'none';
                }
            });
            imageUrlInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); this.insertImageFromURL(); }
            });
        }

        const dropzone = imageModal.querySelector('.rte-dropzone');
        if (dropzone && fileInput) {
            dropzone.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    if (typeof this.showNotification === 'function') {
                        this.showNotification('Please select an image file', 'warning');
                    }
                    return;
                }
                clearPendingUploadPreview();
                this._pendingImageFile = file;
                this._pendingImagePreviewUrl = URL.createObjectURL(file);
                if (uploadPreviewImg) uploadPreviewImg.src = this._pendingImagePreviewUrl;
                if (uploadPreviewArea) uploadPreviewArea.style.display = 'flex';
            });
        }

        if (previewCancelBtn) {
            previewCancelBtn.addEventListener('click', () => {
                clearPendingUploadPreview();
            });
        }

        if (previewInsertBtn) {
            previewInsertBtn.addEventListener('click', async () => {
                await this.insertUploadedImageFromModal(previewInsertBtn);
            });
        }
    };

    // Video modal
    RichTextEditor.prototype.setupVideoModal = function () {
        const videoModal = document.getElementById(`${this.editorId}-video-modal`);
        if (!videoModal || !bindOnce(videoModal, 'video')) return;
        const videoSubmit = videoModal.querySelector('.rte-video-submit');
        const videoClose = videoModal.querySelector('.rte-modal-close');
        const cancelBtn = videoModal.querySelector('.rte-btn-cancel');
        const urlInput = videoModal.querySelector('.rte-video-url');
        if (videoSubmit) videoSubmit.addEventListener('click', () => this.insertVideo());
        if (videoClose) videoClose.addEventListener('click', () => this.closeModal('video'));
        if (cancelBtn) cancelBtn.addEventListener('click', () => this.closeModal('video'));
        if (urlInput) urlInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); this.insertVideo(); } });
    };

    // Special char modal
    RichTextEditor.prototype.setupSpecialCharModal = function () {
        const specialCharModal = document.getElementById(`${this.editorId}-special-char-modal`);
        if (!specialCharModal || !bindOnce(specialCharModal, 'special-char')) return;
        const specialCharClose = specialCharModal.querySelector('.rte-modal-close');
        if (specialCharClose) specialCharClose.addEventListener('click', () => this.closeModal('specialChar'));
        this.loadSpecialCharacters();
    };

    // Close background click
    RichTextEditor.prototype.setupModalBackgroundClose = function () {
        const modalIds = [
            `${this.editorId}-link-modal`,
            `${this.editorId}-image-modal`,
            `${this.editorId}-video-modal`,
            `${this.editorId}-special-char-modal`
        ];
        const modals = modalIds
            .map(id => document.getElementById(id))
            .filter(Boolean);

        modals.forEach(modal => {
            if (!bindOnce(modal, `backdrop-${this.editorId}`)) return;
            modal.addEventListener('click', (e) => {
                if (e.target !== modal) return;
                const modalType = this._resolveModalTypeFromId(modal.id);
                if (modalType) {
                    this.closeModal(modalType);
                }
            });
        });
    };

    RichTextEditor.prototype._rememberModalTrigger = function () {
        const active = document.activeElement;
        if (!active || active === document.body) {
            this._lastModalTrigger = null;
            return;
        }
        this._lastModalTrigger = active;
        if (active.getAttribute && active.getAttribute('aria-haspopup') === 'dialog') {
            active.setAttribute('aria-expanded', 'true');
        }
    };

    RichTextEditor.prototype._getFocusableModalElements = function (modal) {
        if (!modal) return [];
        return Array.from(modal.querySelectorAll(
            'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter((el) => isVisibleElement(el));
    };

    RichTextEditor.prototype._focusModalHeading = function (modal) {
        if (!modal) return false;
        const heading = modal.querySelector('[data-rte-modal-title], .rte-modal-header h3');
        if (!heading) return false;
        if (!heading.hasAttribute('tabindex')) {
            heading.setAttribute('tabindex', '-1');
        }
        setTimeout(() => heading.focus(), 10);
        return true;
    };

    RichTextEditor.prototype._focusFirstModalControl = function (modal) {
        if (!modal) return;
        const focusable = this._getFocusableModalElements(modal);
        if (focusable.length > 0) {
            setTimeout(() => focusable[0].focus(), 20);
        }
    };

    RichTextEditor.prototype._openModal = function (modal) {
        if (!modal) return false;
        this._rememberModalTrigger();
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');

        // Prefer dialog title focus for screen readers; fall back to first field.
        if (!this._focusModalHeading(modal)) {
            this._focusFirstModalControl(modal);
        }
        return true;
    };

    RichTextEditor.prototype._resolveModalTypeFromId = function (modalId = '') {
        const raw = String(modalId || '');
        if (raw === `${this.editorId}-special-char-modal`) return 'specialChar';
        if (raw === `${this.editorId}-link-modal`) return 'link';
        if (raw === `${this.editorId}-image-modal`) return 'image';
        if (raw === `${this.editorId}-video-modal`) return 'video';
        return null;
    };

    RichTextEditor.prototype._setupModalAccessibility = function (modal) {
        if (!modal || !bindOnce(modal, `a11y-${this.editorId}`)) return;

        modal.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modalType = this._resolveModalTypeFromId(modal.id);
                if (modalType) {
                    e.preventDefault();
                    this.closeModal(modalType);
                }
                return;
            }

            if (e.key !== 'Tab') return;
            const focusable = this._getFocusableModalElements(modal);
            if (!focusable.length) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const active = document.activeElement;

            if (!modal.contains(active)) {
                e.preventDefault();
                first.focus();
                return;
            }

            if (e.shiftKey && active === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && active === last) {
                e.preventDefault();
                first.focus();
            }
        });
    };

    RichTextEditor.prototype.setupModalAccessibility = function () {
        const modalIds = [
            `${this.editorId}-link-modal`,
            `${this.editorId}-image-modal`,
            `${this.editorId}-video-modal`,
            `${this.editorId}-special-char-modal`
        ];
        modalIds.forEach((id) => this._setupModalAccessibility(document.getElementById(id)));
    };

    // Show link modal
    RichTextEditor.prototype.showLinkModal = function () {
        const modal = document.getElementById(`${this.editorId}-link-modal`);
        if (!modal) return;
        this.saveSelection();
        const selection = window.getSelection();
        const selectedText = selection.toString();
        const textInput = modal.querySelector('.rte-link-text');
        const urlInput = modal.querySelector('.rte-link-url');
        const targetInput = modal.querySelector('.rte-link-target');
        if (textInput) textInput.value = selectedText;
        if (urlInput) { urlInput.value = 'https://'; }
        if (targetInput) targetInput.checked = false;
        this._openModal(modal);
    };

    // Insert link
    RichTextEditor.prototype.insertLink = function () {
        const modal = document.getElementById(`${this.editorId}-link-modal`);
        if (!modal) return;
        const urlInput = modal.querySelector('.rte-link-url');
        const textInput = modal.querySelector('.rte-link-text');
        const targetInput = modal.querySelector('.rte-link-target');
        const url = urlInput ? urlInput.value.trim() : '';
        const text = textInput ? textInput.value.trim() : '';
        const target = targetInput ? targetInput.checked : false;
        if (!url || url === 'https://') {
            if (typeof this.showNotification === 'function') {
                this.showNotification('Please enter a valid URL', 'warning');
            }
            if (urlInput) urlInput.focus();
            return;
        }
        if (!this._isSafeLinkUrl(url)) {
            if (typeof this.showNotification === 'function') {
                this.showNotification('Please enter a safe URL (http/https/mailto/tel/relative).', 'warning');
            }
            if (urlInput) urlInput.focus();
            return;
        }
        this.restoreSelection();
        try {
            const selection = window.getSelection();
            const selectedText = selection.toString();
            const linkText = text || selectedText || url;
            const link = document.createElement('a');
            link.href = url;
            link.textContent = linkText;
            if (target) { link.target = '_blank'; link.rel = 'noopener noreferrer'; }
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                range.deleteContents();
                range.insertNode(link);
                range.setStartAfter(link);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
            }
            this.updateHiddenInput();
            this.saveToHistory();
        } catch (e) {
            console.error('insertLink error (modal helper):', e);
            if (typeof this.showNotification === 'function') {
                this.showNotification('Error inserting link', 'error');
            }
        }
        this.closeModal('link');
        setTimeout(() => this.editor.focus(), 10);
    };

    // Reset image modal transient state
    RichTextEditor.prototype.resetImageModalState = function (modal = null) {
        const imageModal = modal || document.getElementById(`${this.editorId}-image-modal`);
        if (!imageModal) return;

        if (this._pendingImagePreviewUrl) {
            URL.revokeObjectURL(this._pendingImagePreviewUrl);
            this._pendingImagePreviewUrl = null;
        }
        this._pendingImageFile = null;
        this._pendingImageData = null;

        const urlInput = imageModal.querySelector('.rte-image-url');
        const fileInput = imageModal.querySelector('.rte-file-input');
        const widthInput = imageModal.querySelector('.rte-image-width');
        const heightInput = imageModal.querySelector('.rte-image-height');
        const altInput = imageModal.querySelector('.rte-image-alt');
        const urlPreviewImg = imageModal.querySelector('.rte-image-tab-url-content .rte-image-preview');
        const uploadPreviewArea = imageModal.querySelector('.rte-image-tab-upload-content .rte-image-preview-area');
        const uploadPreviewImg = imageModal.querySelector('.rte-image-tab-upload-content .rte-image-preview');

        if (urlInput) urlInput.value = 'https://';
        if (fileInput) fileInput.value = '';
        if (widthInput) widthInput.value = '';
        if (heightInput) heightInput.value = '';
        if (altInput) altInput.value = '';
        if (urlPreviewImg) {
            urlPreviewImg.src = '';
            urlPreviewImg.style.display = 'none';
        }
        if (uploadPreviewImg) uploadPreviewImg.src = '';
        if (uploadPreviewArea) uploadPreviewArea.style.display = 'none';
    };

    // Show image modal
    RichTextEditor.prototype.showImageModal = function () {
        const modal = document.getElementById(`${this.editorId}-image-modal`);
        if (!modal) return;
        this.saveSelection();
        this.resetImageModalState(modal);
        const urlTab = modal.querySelector('.rte-image-tab-url');
        if (urlTab) urlTab.click();
        this._openModal(modal);
    };

    // Handle image drop
    RichTextEditor.prototype.handleImageDrop = async function (file) {
        if (!file?.type?.startsWith('image/')) {
            if (typeof this.showNotification === 'function') {
                this.showNotification('Please drop an image file', 'warning');
            }
            return;
        }

        try {
            const uploadResult = await this.uploadEditorImage(file);
            this.restoreSelection();
            this.insertImageFromData(uploadResult.uploadedUrl);
        } catch (error) {
            if (typeof this.showNotification === 'function') {
                this.showNotification(error?.message || 'Image upload failed.', 'error');
            }
        }
    };

    // Show video modal
    RichTextEditor.prototype.showVideoModal = function () {
        const modal = document.getElementById(`${this.editorId}-video-modal`);
        if (!modal) return;
        this.saveSelection();
        const urlInput = modal.querySelector('.rte-video-url');
        if (urlInput) { urlInput.value = 'https://'; }
        this._openModal(modal);
    };

    RichTextEditor.prototype._buildVideoEmbedHTML = function (url) {
        const youtubeId = this.getYouTubeVideoId(url);
        if (youtubeId) {
            return `<div class="rte-video-wrapper">
                <iframe
                    width="560"
                    height="315"
                    src="https://www.youtube.com/embed/${youtubeId}"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            </div><p><br></p>`;
        }

        const vimeoId = this.getVimeoVideoId(url);
        if (vimeoId) {
            return `<div class="rte-video-wrapper">
                <iframe
                    src="https://player.vimeo.com/video/${vimeoId}"
                    width="640"
                    height="360"
                    frameborder="0"
                    allow="autoplay; fullscreen"
                    allowfullscreen>
                </iframe>
            </div><p><br></p>`;
        }

        return `<div class="rte-video-wrapper">
            <video width="640" height="360" controls>
                <source src="${this.escapeHtml(url)}" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div><p><br></p>`;
    };

    // Insert video
    RichTextEditor.prototype.insertVideo = function () {
        const modal = document.getElementById(`${this.editorId}-video-modal`);
        if (!modal) return;
        const urlInput = modal.querySelector('.rte-video-url');
        const url = urlInput ? urlInput.value.trim() : '';
        if (!url || url === 'https://') {
            if (typeof this.showNotification === 'function') {
                this.showNotification('Please enter a valid video URL', 'warning');
            }
            if (urlInput) urlInput.focus();
            return;
        }
        if (!this._isSafeMediaUrl(url)) {
            if (typeof this.showNotification === 'function') {
                this.showNotification('Please enter a safe video URL (http/https/relative).', 'warning');
            }
            if (urlInput) urlInput.focus();
            return;
        }
        let embedHTML = '';
        try {
            embedHTML = this._buildVideoEmbedHTML(url);
        } catch (e) {
            console.error('insertVideo build error:', e);
            embedHTML = '';
        }
        if (!embedHTML) {
            if (typeof this.showNotification === 'function') {
                this.showNotification('Invalid video URL', 'warning');
            }
            return;
        }
        this.restoreSelection();
        try {
            document.execCommand('insertHTML', false, embedHTML);
        } catch (e) {
            console.warn('insertHTML error (modal helper):', e);
            const div = document.createElement('div');
            div.innerHTML = embedHTML;
            if (div.firstChild) this.editor.appendChild(div.firstChild);
        }
        this.closeModal('video');
        this.updateHiddenInput();
        this.saveToHistory();
        setTimeout(() => this.editor.focus(), 10);
    };

    // Special char modal show
    RichTextEditor.prototype.showSpecialCharModal = function () {
        const modal = document.getElementById(`${this.editorId}-special-char-modal`);
        if (!modal) return;
        this.saveSelection();
        this._openModal(modal);
        this.loadSpecialCharacters(); // Load characters when modal opens
    };

    RichTextEditor.prototype.insertSpecialCharacter = function (char) {
        const value = String(char || '');
        if (!value) return;

        this.restoreSelection();
        try {
            document.execCommand('insertText', false, value);
        } catch (err) {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) return;

            const range = selection.getRangeAt(0);
            const textNode = document.createTextNode(value);
            range.insertNode(textNode);
            range.setStartAfter(textNode);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
        }

        this.closeModal('specialChar');
        this.updateHiddenInput();
        this.saveToHistory();
        setTimeout(() => {
            try {
                if (this && this.editor) this.editor.focus();
            } catch (e) { /* ignore */ }
        }, 10);
    };

    // Load special characters
    RichTextEditor.prototype.loadSpecialCharacters = function () {
        const modal = document.getElementById(`${this.editorId}-special-char-modal`);
        if (!modal) {
            console.warn('⚠️ [RTE] Special char modal not found');
            return;
        }
        const grid = modal.querySelector('.rte-special-char-grid');
        if (!grid) {
            console.warn('⚠️ [RTE] Special char grid not found');
            return;
        }
        if (grid.children.length > 0) {
            window.RTE_debugLog('modals', 'Special characters already loaded');
            return; // Already loaded
        }
        const specialChars = [
            '©','®','™','€','£','¥','§','¶','†','‡','•','…','‰','′','″','‴',
            '∀','∂','∃','∅','∇','∈','∉','∋','∏','∑','−','∗','√','∝','∞','∠',
            '∧','∨','∩','∪','∫','∴','∼','≅','≈','≠','≡','≤','≥','⊂','⊃','⊄',
            '⊅','⊆','⊇','⊕','⊖','⊗','⊘','⊙','→','↓','↔','↕','↖','↗','↘','↙',
            '♠','♣','♥','♦','♭','♮','♯','«','»','‹','›','"','\'','"','"','\'','\'','"','\'',
            '¡','¿','·','°','‰','∘','⁄','€','℅','№','µ','ß','ñ','ü','ö','ä'
        ];
        grid.innerHTML = '';
        window.RTE_debugLog('modals', `Loading ${specialChars.length} special characters`);
        specialChars.forEach(char => {
            const button = document.createElement('button');
            button.className = 'rte-special-char-btn';
            button.textContent = char;
            button.type = 'button';
            button.title = `Insert: ${char}`;
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.insertSpecialCharacter(char);
            });
            grid.appendChild(button);
        });
        window.RTE_debugLog('modals', `${specialChars.length} special characters loaded successfully`);
    };

    // Close modal
    RichTextEditor.prototype._getModalId = function (modalType) {
        const map = {
            link: `${this.editorId}-link-modal`,
            image: `${this.editorId}-image-modal`,
            video: `${this.editorId}-video-modal`,
            specialChar: `${this.editorId}-special-char-modal`
        };
        return map[modalType] || null;
    };

    RichTextEditor.prototype.closeModal = function (modalType) {
        const modalId = this._getModalId(modalType);
        if (!modalId) return;
        const modal = document.getElementById(modalId);
        const triggerToRestore = this._lastModalTrigger && typeof this._lastModalTrigger.focus === 'function'
            ? this._lastModalTrigger
            : null;
        if (modalType === 'image' && typeof this.resetImageModalState === 'function') {
            this.resetImageModalState(modal);
        }
        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
        this.restoreSelection();
        this._lastModalTrigger = null;
        setTimeout(() => {
            try {
                if (triggerToRestore && document.contains(triggerToRestore) && isVisibleElement(triggerToRestore)) {
                    if (triggerToRestore.getAttribute && triggerToRestore.getAttribute('aria-haspopup') === 'dialog') {
                        triggerToRestore.setAttribute('aria-expanded', 'false');
                    }
                    triggerToRestore.focus();
                    return;
                }
                if (this && this.editor) this.editor.focus();
            } catch (e) {}
        }, 10);
    };

    return true;
}

    // Export for both CommonJS and ES6 module contexts
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installModalHelpers };
    }
    if (typeof window !== 'undefined') {
        window.installModalHelpers = installModalHelpers;
    }
})(typeof window !== 'undefined' ? window : {});
