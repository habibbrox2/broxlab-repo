const byIdDefault = (id) => document.getElementById(id);

import { validateFile, ALLOWED_TYPES } from '../../shared/form-validators.js';

export function initMediaUpload(options = {}) {
    const byId = options.byId || byIdDefault;

    const form = byId('mediaUploadForm');
    if (!form) return;

    const uploadArea = byId('uploadArea');
    const fileInput = byId('file');
    const progressDiv = byId('uploadProgress');
    const progressBar = byId('progressBar');
    const progressText = byId('progressText');
    const uploadStatus = byId('uploadStatus');
    const fileInfo = byId('fileInfo');
    const submitBtn = byId('submitBtn');
    const alertContainer = byId('alertContainer');

    const maxFileSize = parseInt(form.dataset.maxFileSize || '52428800', 10);

    // maxFileSize remains local


    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const file = this.files?.[0];
            if (!file) return;

            const validation = validateFile(file);
            if (!validation.valid) {
                window.showAlert(validation.error, 'danger');
                this.value = '';
                if (submitBtn) submitBtn.disabled = true;
                return;
            }

            if (fileInfo) {
                const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
                fileInfo.innerHTML = `
                    <strong>Selected File:</strong> ${file.name}<br>
                    <strong>Size:</strong> ${fileSizeMB} MB<br>
                    <strong>Type:</strong> ${file.type}
                `;
            }

            if (submitBtn) submitBtn.disabled = false;
        });
    }

    if (uploadArea && fileInput) {
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (event) => {
            event.preventDefault();
            uploadArea.classList.add('dragover');
        });
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        uploadArea.addEventListener('drop', (event) => {
            event.preventDefault();
            uploadArea.classList.remove('dragover');
            if (!event.dataTransfer.files.length) return;
            fileInput.files = event.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const file = fileInput?.files?.[0];
        const validation = validateFile(file);
        if (!validation.valid) {
            window.showAlert(validation.error, 'danger');
            return;
        }

        const formData = new FormData(form);
        if (progressDiv) progressDiv.style.display = 'block';
        if (submitBtn) submitBtn.disabled = true;
        if (uploadStatus) uploadStatus.textContent = 'Uploading...';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);

        xhr.upload.onprogress = function (uploadEvent) {
            if (!uploadEvent.lengthComputable || !progressBar || !progressText) return;
            const percent = Math.round((uploadEvent.loaded / uploadEvent.total) * 100);
            progressBar.style.width = `${percent}%`;
            progressText.textContent = `${percent}%`;
        };

        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        window.showAlert(result.message || 'Upload successful', 'success');
                        form.reset();
                        if (fileInfo) fileInfo.innerHTML = '';
                    } else {
                        window.showAlert(result.message || 'Upload failed', 'danger');
                    }
                } catch (error) {
                    window.showAlert('Unexpected response format', 'danger');
                }
            } else {
                window.showAlert('Upload failed. Please try again.', 'danger');
            }

            if (progressDiv) progressDiv.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;
            if (uploadStatus) uploadStatus.textContent = '';
        };

        xhr.onerror = function () {
            window.showAlert('Upload failed. Please check your connection.', 'danger');
            if (progressDiv) progressDiv.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;
            if (uploadStatus) uploadStatus.textContent = '';
        };

        xhr.send(formData);
    });
}
