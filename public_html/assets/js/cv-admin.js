(function () {
  const ready = function (fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true, });
    } else {
      fn();
    }
  };

  const copyToClipboard = async function (text) {
    if (!text) return false;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
        return true;
      }
    } catch (error) {
      return false;
    }

    try {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.focus();
      textarea.select();
      const success = document.execCommand('copy');
      document.body.removeChild(textarea);
      return success;
    } catch (error) {
      return false;
    }
  };

  const notify = function (message, type) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({
        icon: type || 'success',
        title: message,
        timer: 1600,
        showConfirmButton: false,
      });
      return;
    }
    alert(message);
  };

  const wireCopyButtons = function () {
    const buttons = document.querySelectorAll('[data-copy-template]');
    buttons.forEach((btn) => {
      btn.addEventListener('click', async () => {
        const key = btn.getAttribute('data-template-key');
        const ok = await copyToClipboard(key);
        if (ok) {
          notify('Template key copied');
        } else {
          notify('Copy failed', 'error');
        }
      });
    });
  };

  const wireSelectionCount = function () {
    const counter = document.querySelector('[data-selected-count]');
    if (!counter) return;

    const updateCount = function () {
      const selected = document.querySelectorAll('input.item-checkbox:checked').length;
      counter.textContent = String(selected);
    };

    document.addEventListener('change', (event) => {
      if (event.target && event.target.classList.contains('item-checkbox')) {
        updateCount();
      }
      if (event.target && event.target.id === 'select-all-checkbox') {
        updateCount();
      }
    });

    setTimeout(updateCount, 0);
  };

  const wireBulkExport = function () {
    const button = document.querySelector('[data-bulk-export]');
    if (!button) return;
    const errorBox = document.querySelector('[data-bulk-error]');

    button.addEventListener('click', () => {
      if (button.disabled) return;
      if (errorBox) {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
      }
      const selected = Array.from(document.querySelectorAll('input.item-checkbox:checked'))
        .map((cb) => { return (cb.value || '').trim(); })
        .filter(Boolean);

      if (selected.length === 0) {
        notify('Select at least one CV to export', 'warning');
        return;
      }

      const templateSelect = document.querySelector('[data-template-select]') || document.querySelector('select[name="template"]');
      const template = templateSelect ? templateSelect.value : 'modern';

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      button.disabled = true;
      const originalLabel = button.innerHTML;
      button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Preparing ZIP...';

      fetch('/admin/cvs/bulk/export-zip', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({ cv_ids: selected, template: template, }),
      })
        .then((response) => {
          if (!response.ok) {
            return response.json().then((data) => {
              throw new Error(data && data.error ? data.error : 'Bulk export failed');
            });
          }
          const disposition = response.headers.get('Content-Disposition') || '';
          const filenameMatch = disposition.match(/filename="?([^";]+)"?/i);
          const filename = filenameMatch ? filenameMatch[1] : 'cv-exports.zip';
          return response.blob().then((blob) => {
            return { blob: blob, filename: filename, };
          });
        })
        .then((result) => {
          const url = window.URL.createObjectURL(result.blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = result.filename || 'cv-exports.zip';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          window.URL.revokeObjectURL(url);
          notify('Bulk export ready');
        })
        .catch((error) => {
          const message = error.message || 'Bulk export failed';
          notify(message, 'error');
          if (errorBox) {
            errorBox.textContent = message;
            errorBox.classList.remove('d-none');
          }
        })
        .finally(() => {
          button.disabled = false;
          button.innerHTML = originalLabel;
        });
    });
  };

  const wireToggleButtons = function () {
    const forms = document.querySelectorAll('[data-toggle-form]');
    forms.forEach((form) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;

        const originalLabel = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Processing...';

        const formData = new FormData(form);

        fetch(form.action, {
          method: 'POST',
          body: formData,
        })
          .then((response) => {
            return response.json();
          })
          .then((data) => {
            if (data.success) {
              // Update button text and class
              const isActive = data.status === 'active';
              button.className = `modern-btn btn-sm ${ isActive ? 'btn-danger' : 'btn-success'}`;
              button.innerHTML = `<i class="bi ${ isActive ? 'bi-pause-fill' : 'bi-play-fill' } me-1"></i> ${
                isActive ? 'Disable' : 'Enable'}`;

              // Update status badge
              const card = form.closest('.cv-template-card');
              const title = card.querySelector('.cv-template-title');
              const badge = title.querySelector('.badge');
              if (badge) {
                badge.className = `badge ms-2 ${ isActive ? 'bg-success' : 'bg-danger'}`;
                badge.textContent = isActive ? 'Active' : 'Disabled';
              }

              notify(`Template ${ isActive ? 'enabled' : 'disabled'}`);
            } else {
              throw new Error(data.error || 'Toggle failed');
            }
          })
          .catch((error) => {
            const message = error.message || 'Toggle failed';
            notify(message, 'error');
          })
          .finally(() => {
            button.disabled = false;
            button.innerHTML = originalLabel;
          });
      });
    });
  };

  ready(() => {
    wireCopyButtons();
    wireSelectionCount();
    wireBulkExport();
    wireToggleButtons();
  });
})();
