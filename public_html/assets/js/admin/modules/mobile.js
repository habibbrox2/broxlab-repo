import { escapeHtml } from './core.js';

const byIdDefault = (id) => document.getElementById(id);
const parseJsonDefault = (value, fallback) => {
    if (!value) return fallback;
    try {
        return JSON.parse(value);
    } catch (error) {
        return fallback;
    }
};

const notifyDefault = (message, type = 'info') => {
    if (typeof window.showMessage === 'function') {
        window.showMessage(message, type);
    }
};

export function initDeleteMobile(options = {}) {
    const byId = options.byId || byIdDefault;
    const notify = options.notify || notifyDefault;

    const form = byId('deleteForm');
    const checkbox = byId('confirmDelete');
    const deleteBtn = byId('deleteBtn');
    if (!form || !checkbox || !deleteBtn) return;

    checkbox.addEventListener('change', () => {
        deleteBtn.disabled = !checkbox.checked;
    });

    form.addEventListener('submit', (event) => {
        if (checkbox.checked) return;
        event.preventDefault();
        notify('Please confirm deletion', 'warning');
    });
}

export function initMobileFormShared(options = {}) {
    const byId = options.byId || byIdDefault;
    const parseJson = options.parseJson || parseJsonDefault;
    const notify = options.notify || notifyDefault;

    const specsContainer = byId('specs-container');
    if (!specsContainer) return;

    const deletedImageIds = new Set();
    const allSpecKeys = Array.from(document.querySelectorAll('#spec_keys option')).map((option) => option.value);

    const updateSpecOptions = () => {
        const specKeysList = byId('spec_keys');
        if (!specKeysList) return;

        const selectedKeys = Array.from(document.querySelectorAll('.spec-input'))
            .map((input) => (input.value || '').trim())
            .filter(Boolean);

        specKeysList.innerHTML = allSpecKeys
            .filter((key) => !selectedKeys.includes(key))
            .map((key) => `<option value="${escapeHtml(key)}"></option>`)
            .join('');
    };

    window.addSpec = function (key = '', value = '') {
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 spec-row align-items-center';
        row.innerHTML = `
            <div class="col-md-5">
                <input list="spec_keys" name="specifications[key][]" class="form-control spec-input" placeholder="Select or type key" value="${escapeHtml(key)}">
            </div>
            <div class="col-md-6">
                <input type="text" name="specifications[value][]" class="form-control" placeholder="Value" value="${escapeHtml(value)}">
            </div>
            <div class="col-md-1">
                <button type="button" class="modern-btn modern-btn-danger btn-sm remove-spec" title="Remove"><i class="bi bi-x-lg"></i></button>
            </div>
        `;
        specsContainer.appendChild(row);
        updateSpecOptions();
    };

    window.removeSpec = function (button) {
        const row = button.closest('.spec-row');
        if (!row) return;
        row.remove();
        updateSpecOptions();
    };

    specsContainer.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-spec');
        if (button) {
            window.removeSpec(button);
        }
    });

    specsContainer.addEventListener('change', (event) => {
        if (!event.target || !event.target.classList.contains('spec-input')) return;
        updateSpecOptions();
    });

    const tagsSelect = byId('tags');

    const normalizeTagIdList = (value) => {
        if (!Array.isArray(value)) return [];
        return value
            .map((item) => {
                if (typeof item === 'string' || typeof item === 'number') return String(item);
                if (item && typeof item === 'object' && item.id !== undefined) return String(item.id);
                return '';
            })
            .filter(Boolean);
    };

    function applyTags(selectedIds = []) {
        if (!tagsSelect) return;
        if (window.adminContent?.fetchTags) {
            window.adminContent.fetchTags(selectedIds, '#tags');
        }
        if (window.adminContent?.initializeTagsSelect) {
            window.adminContent.initializeTagsSelect('#tags');
        }
    }

    const mobileEditData = byId('mobile-edit-data');
    if (mobileEditData) {
        const selectedTagIds = normalizeTagIdList(parseJson(mobileEditData.dataset.selectedTagIds, []));
        applyTags(selectedTagIds);
    }

    const mobileInsertData = byId('mobile-insert-data');
    if (mobileInsertData) {
        const selectedIds = normalizeTagIdList(parseJson(mobileInsertData.dataset.initialTags, []));
        applyTags(selectedIds);
    }

    if (!mobileEditData && !mobileInsertData && tagsSelect) {
        applyTags([]);
    }

    const imagesInput = byId('images-input') || byId('images');
    const imagesPreview = byId('new-images-preview') || byId('images-preview');

    function buildInputFilesFromVisiblePreviews() {
        if (!imagesInput || !imagesInput.files || !imagesInput.files.length || !imagesPreview) return;

        const visibleNames = Array.from(imagesPreview.querySelectorAll('[data-file-name]'))
            .map((element) => element.dataset.fileName);
        if (!visibleNames.length) return;

        const dt = new DataTransfer();
        Array.from(imagesInput.files).forEach((file) => {
            if (visibleNames.includes(file.name)) {
                dt.items.add(file);
            }
        });

        imagesInput.files = dt.files;
    }

    if (imagesInput && imagesPreview) {
        imagesInput.addEventListener('change', function () {
            imagesPreview.innerHTML = '';

            Array.from(this.files).forEach((file) => {
                const maxSize = 5 * 1024 * 1024;
                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (file.size > maxSize || !allowedTypes.includes(file.type)) return;

                const reader = new FileReader();
                reader.onload = function (event) {
                    const container = document.createElement('div');
                    container.className = 'position-relative image-container-100';
                    container.dataset.fileName = file.name;
                    container.innerHTML = `
                        <img src="${event.target.result}" class="img-thumbnail image-thumbnail-full">
                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 remove-image"><i class="bi bi-x-lg"></i></button>
                    `;

                    imagesPreview.appendChild(container);
                    container.querySelector('.remove-image')?.addEventListener('click', () => {
                        container.remove();
                        buildInputFilesFromVisiblePreviews();
                    });
                };
                reader.readAsDataURL(file);
            });
        });
    }

    window.markImageForDeletion = function (imageId) {
        const container = document.querySelector(`[data-id="${imageId}"][data-existing="true"]`);
        if (!container) return;

        deletedImageIds.add(String(imageId));
        const deleteInput = container.querySelector(`input[data-delete-input="${imageId}"]`);
        if (deleteInput) deleteInput.value = imageId;

        container.style.opacity = '0.5';
        const image = container.querySelector('img');
        if (image) image.style.filter = 'grayscale(100%)';

        const button = container.querySelector('.remove-existing-image');
        if (!button) return;
        button.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i>';
        button.classList.remove('btn-danger');
        button.classList.add('btn-outline-secondary');
        button.title = 'Undo delete';
        button.onclick = function () {
            window.undoImageDeletion(imageId);
        };
    };

    window.undoImageDeletion = function (imageId) {
        const container = document.querySelector(`[data-id="${imageId}"][data-existing="true"]`);
        if (!container) return;

        deletedImageIds.delete(String(imageId));
        const deleteInput = container.querySelector(`input[data-delete-input="${imageId}"]`);
        if (deleteInput) deleteInput.value = '';

        container.style.opacity = '1';
        const image = container.querySelector('img');
        if (image) image.style.filter = '';

        const button = container.querySelector('.remove-existing-image');
        if (!button) return;
        button.innerHTML = '<i class="bi bi-x-lg"></i>';
        button.classList.remove('btn-outline-secondary');
        button.classList.add('btn-danger');
        button.title = 'Remove';
        button.onclick = function () {
            window.markImageForDeletion(imageId);
        };
    };

    byId('existing-images-container')?.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-existing-image');
        if (!button) return;

        const imageId = button.dataset.imageId || button.getAttribute('data-image-id');
        if (!imageId) return;

        const isDeleted = deletedImageIds.has(String(imageId));
        if (isDeleted) {
            window.undoImageDeletion(imageId);
        } else {
            window.markImageForDeletion(imageId);
        }
    });

    byId('mobileForm')?.addEventListener('submit', function (event) {
        if (tagsSelect && tagsSelect.selectedOptions.length === 0) {
            event.preventDefault();
            notify('Please select at least one tag', 'warning');
            return false;
        }

        document.querySelectorAll('.spec-row').forEach((row) => {
            const key = row.querySelector('input[name="specifications[key][]"]')?.value?.trim() || '';
            const value = row.querySelector('input[name="specifications[value][]"]')?.value?.trim() || '';
            if (!key && !value) {
                row.remove();
            }
        });

        return true;
    });

    updateSpecOptions();
}
