const STATE = { uploading: false, };

function init() {
  const dropZone = document.getElementById('tpl-upload-dropzone');
  const fileInput = document.getElementById('tpl-upload-input');
  const form = document.getElementById('tpl-upload-form');
  const resultArea = document.getElementById('tpl-upload-result');
  if (!dropZone || !fileInput || !form) return;

  dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.add('drag-over');
  });
  dropZone.addEventListener('dragleave', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.remove('drag-over');
  });
  dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.remove('drag-over');
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      fileInput.files = e.dataTransfer.files;
      handleFileSelect(fileInput.files[0], dropZone, resultArea);
    }
  });
  dropZone.addEventListener('click', () => {
    if (!STATE.uploading) fileInput.click();
  });
  fileInput.addEventListener('change', function () {
    if (this.files && this.files.length > 0) {
      handleFileSelect(this.files[0], dropZone, resultArea);
    }
  });
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (STATE.uploading || !fileInput.files || fileInput.files.length === 0) return;
    uploadTemplate(fileInput, form, dropZone, resultArea);
  });
}

function handleFileSelect(file, dropZone, resultArea) {
  const isValid = file.name.toLowerCase().endsWith('.zip');
  const sizeOk = file.size <= 10 * 1024 * 1024;
  const statusEl = dropZone.querySelector('.upload-status-text');
  if (!isValid) {
    showResult('error', 'Invalid file type. Please select a .zip file.', resultArea);
    if (statusEl) statusEl.textContent = 'No file selected';
    return;
  }
  if (!sizeOk) {
    showResult('error', 'File exceeds 10MB limit.', resultArea);
    if (statusEl) statusEl.textContent = 'No file selected';
    return;
  }
  if (statusEl) statusEl.textContent = `${file.name} (${formatSize(file.size)})`;
  const uploadBtn = document.getElementById('tpl-upload-btn');
  if (uploadBtn) uploadBtn.disabled = false;
}

function uploadTemplate(fileInput, form, dropZone, resultArea) {
  STATE.uploading = true;
  const uploadBtn = document.getElementById('tpl-upload-btn');
  const progressBar = document.getElementById('tpl-upload-progress');
  const progressContainer = document.getElementById('tpl-progress-container');

  if (uploadBtn) {
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<span class="inline-block animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2 align-middle"></span> Installing...';
  }
  if (progressContainer) progressContainer.style.display = 'block';

  const fd = new FormData(form);
  const xhr = new XMLHttpRequest();
  xhr.upload.addEventListener('progress', (e) => {
    if (e.lengthComputable && progressBar) {
      const pct = Math.round((e.loaded / e.total) * 100);
      progressBar.style.width = `${pct}%`;
      progressBar.setAttribute('aria-valuenow', pct);
    }
  });
  xhr.addEventListener('load', () => {
    STATE.uploading = false;
    if (progressContainer) progressContainer.style.display = 'none';
    if (progressBar) progressBar.style.width = '0%';
    if (uploadBtn) {
      uploadBtn.disabled = false;
      uploadBtn.innerHTML = '<i class="lucide lucide-upload mr-1"></i> Install Template';
    }
    try {
      const response = JSON.parse(xhr.responseText);
      if (response.success) {
        showResult('success', response.message, resultArea);
        if (response.warnings && response.warnings.length > 0) {
          const wHtml = `<div class="mt-2 text-xs text-amber-700"><strong>Warnings:</strong><ul class="mt-1 list-disc pl-4">${
            response.warnings.map((w) => `<li>${escHtml(w)}</li>`).join('')
          }</ul></div>`;
          const rc = resultArea.querySelector('.upload-result-content');
          if (rc) rc.insertAdjacentHTML('beforeend', wHtml);
        }
        fileInput.value = '';
        const se = dropZone.querySelector('.upload-status-text');
        if (se) se.textContent = 'No file selected';
        setTimeout(() => { window.location.reload(); }, 2000);
      } else {
        let errMsg = response.error || 'Installation failed.';
        if (response.errors && response.errors.length > 0) {
          errMsg += `<ul class="mt-1 list-disc pl-4">${
            response.errors.map((e) => `<li>${escHtml(e)}</li>`).join('')
          }</ul>`;
        }
        showResult('error', errMsg, resultArea);
        if (response.warnings && response.warnings.length > 0) {
          const wh = `<div class="mt-2 text-xs text-amber-700"><strong>Warnings:</strong><ul class="mt-1 list-disc pl-4">${
            response.warnings.map((w) => `<li>${escHtml(w)}</li>`).join('')
          }</ul></div>`;
          const rc2 = resultArea.querySelector('.upload-result-content');
          if (rc2) rc2.insertAdjacentHTML('beforeend', wh);
        }
      }
    } catch (e) {
      showResult('error', 'Invalid server response.', resultArea);
    }
  });
  xhr.addEventListener('error', () => {
    STATE.uploading = false;
    if (progressContainer) progressContainer.style.display = 'none';
    if (progressBar) progressBar.style.width = '0%';
    if (uploadBtn) {
      uploadBtn.disabled = false;
      uploadBtn.innerHTML = '<i class="lucide lucide-upload mr-1"></i> Install Template';
    }
    showResult('error', 'Network error.', resultArea);
  });
  xhr.open('POST', form.action);
  xhr.send(fd);
}

function showResult(type, msg, area) {
  if (!area) return;
  const ok = type === 'success';
  const bg = ok ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800';
  const ic = ok ? 'text-emerald-500' : 'text-red-500';
  const ico = ok ? 'check-circle' : 'alert-triangle';
  area.style.display = 'block';
  area.innerHTML = `<div class="rounded-lg border p-4 ${bg} relative">
      <button type="button" class="absolute top-2 right-2 text-current opacity-50 hover:opacity-100" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
      <div class="flex items-start gap-3 upload-result-content">
        <i class="lucide lucide-${ico} ${ic} mt-0.5 flex-shrink-0"></i>
        <div>${msg}</div>
      </div>
    </div>`;
}

function formatSize(b) {
  if (b < 1024) return `${b} B`;
  if (b < 1048576) return `${(b / 1024).toFixed(1)} KB`;
  return `${(b / 1048576).toFixed(1)} MB`;
}

function escHtml(t) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(t));
  return d.innerHTML;
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
