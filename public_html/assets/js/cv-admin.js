import { escapeHtml } from './shared/utils.js';

'use strict';

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
  window.showMessage(message, type === 'error' ? 'danger' : 'success');
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
    if (event.target && event.target.matches('[data-select-all-checkbox], #select-all-checkbox')) {
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
      errorBox.classList.add('hidden');
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
    button.innerHTML = '<i class="lucide lucide-hourglass mr-1"></i> Preparing ZIP...';

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
          errorBox.classList.remove('hidden');
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
      button.innerHTML = '<i class="lucide lucide-hourglass mr-1"></i> Processing...';

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
            button.className = `modern-btn text-xs px-2.5 py-1 rounded-lg ${ isActive ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-emerald-600 text-white hover:bg-emerald-700'}`;
            button.innerHTML = `<i class="lucide ${ isActive ? 'lucide-pause' : 'lucide-play' } mr-1"></i> ${
              isActive ? 'Disable' : 'Enable'}`;

            // Update status badge
            const card = form.closest('.cv-template-card');
            const title = card.querySelector('.cv-template-title');
            const badge = title.querySelector('.badge');
            if (badge) {
              badge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ml-2 ${ isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'}`;
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

const wireDeleteButtons = function () {
  const forms = document.querySelectorAll('[data-delete-form]');
  forms.forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      if (!button || button.disabled) return;

      const templateName = form.getAttribute('data-template-name') || 'this template';

      if (window.Swal && typeof window.Swal.fire === 'function') {
        window.Swal.fire({
          title: 'Delete Template?',
          text: `Are you sure you want to delete "${templateName}"? This action cannot be undone.`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, delete it',
          cancelButtonText: 'Cancel',
          reverseButtons: true,
          showLoaderOnConfirm: true,
          preConfirm: () => {
            button.disabled = true;
            const originalLabel = button.innerHTML;
            button.innerHTML = '<i class="lucide lucide-hourglass mr-1"></i> Deleting...';

            return fetch(form.action, {
              method: 'POST',
              body: new FormData(form),
            })
              .then((response) => {
                if (!response.ok) {
                  return response.json().then((data) => {
                    throw new Error(data && data.message ? data.message : 'Delete failed');
                  });
                }
                return response.json();
              })
              .then((data) => {
                if (!data.success) {
                  throw new Error(data.message || 'Delete failed');
                }
                return data;
              })
              .catch((error) => {
                button.disabled = false;
                button.innerHTML = originalLabel;
                throw error;
              });
          },
        }).then((result) => {
          if (result.isConfirmed) {
            const card = form.closest('.cv-template-rounded-xl');
            if (card) {
              card.style.transition = 'all 0.3s ease';
              card.style.opacity = '0';
              card.style.transform = 'scale(0.95)';
              setTimeout(() => {
                card.remove();
              }, 300);
            }
            notify(`Template "${templateName}" deleted`);
          }
        });
      } else {
        // Fallback: native confirm
        if (window.confirmAction) {
          // Already has Swal.fire wrapper above - this is fallback
          window.showConfirm(`Delete "${templateName}"? This cannot be undone.`).then(confirmed => {
            if (confirmed) form.submit();
          });
        }
      }
    });
  });
};

const wireBulkDelete = function () {
  const container = document.querySelector('[data-bulk-container]');
  const bar = document.querySelector('[data-bulk-delete-bar]');
  const selectAll = document.querySelector('[data-bulk-select-all]');
  const bulkBtn = document.querySelector('[data-bulk-delete]');
  const cancelBtn = document.querySelector('[data-bulk-cancel]');
  const counter = document.querySelector('[data-selected-count]');

  if (!container || !bar) return;

  const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

  const getSelectedCheckboxes = function () {
    return container.querySelectorAll('input.item-checkbox:checked');
  };

  const getSelectedSlugs = function () {
    return Array.from(getSelectedCheckboxes()).map((cb) => {
      return cb.getAttribute('data-template-slug');
    }).filter(Boolean);
  };

  const getSelectedNames = function () {
    return Array.from(getSelectedCheckboxes()).map((cb) => {
      return cb.getAttribute('data-template-name');
    }).filter(Boolean);
  };

  const updateUI = function () {
    const checked = container.querySelectorAll('input.item-checkbox:checked').length;
    bar.classList.toggle('hidden', checked === 0);
    if (counter) counter.textContent = String(checked);
    bulkBtn.disabled = checked === 0;
  };

  const removeCards = function (slugs) {
    slugs.forEach((slug) => {
      const card = container.querySelector(`[data-template-card="${ slug }"]`);
      if (card) {
        card.style.transition = 'all 0.3s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.95)';
        setTimeout(() => {
          card.remove();
        }, 300);
      }
    });
  };

  // Select All toggle
  if (selectAll) {
    selectAll.addEventListener('change', () => {
      const checked = selectAll.checked;
      container.querySelectorAll('input.item-checkbox').forEach((cb) => {
        cb.checked = checked;
      });
      updateUI();
    });
  }

  // Individual checkbox change
  container.addEventListener('change', (event) => {
    const cb = event.target;
    if (cb && cb.classList.contains('item-checkbox')) {
      // Uncheck select-all if not all selected
      if (selectAll) {
        const all = container.querySelectorAll('input.item-checkbox');
        const checked = container.querySelectorAll('input.item-checkbox:checked');
        selectAll.checked = all.length > 0 && all.length === checked.length;
        selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
      }
      updateUI();
    }
  });

  // Cancel button
  if (cancelBtn) {
    cancelBtn.addEventListener('click', () => {
      container.querySelectorAll('input.item-checkbox').forEach((cb) => {
        cb.checked = false;
      });
      if (selectAll) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
      }
      updateUI();
    });
  }

  // Bulk delete button
  if (bulkBtn) {
    bulkBtn.addEventListener('click', () => {
      const slugs = getSelectedSlugs();
      const names = getSelectedNames();
      if (slugs.length === 0) return;

      const count = slugs.length;
      const nameList = names.slice(0, 3).join(', ');
      const suffix = names.length > 3 ? ` and ${ names.length - 3 } more` : '';

      if (window.Swal && typeof window.Swal.fire === 'function') {
        window.Swal.fire({
          title: `Delete ${ count } Template${ count > 1 ? 's' : '' }?`,
          html: 'Are you sure you want to delete the following templates?<br><br>'
            + `<span class="font-medium">${ escapeHtml(nameList + suffix) }</span><br><br>`
            + 'This action <strong>cannot be undone</strong>.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6b7280',
          confirmButtonText: `Yes, delete ${ count}`,
          cancelButtonText: 'Cancel',
          reverseButtons: true,
          showLoaderOnConfirm: true,
          preConfirm: function () {
            return fetch('/admin/cv-templates/bulk-delete', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
              },
              body: JSON.stringify({ slugs: slugs, }),
            })
              .then((response) => {
                if (!response.ok) {
                  return response.json().then((data) => {
                    throw new Error(data && data.error ? data.error : 'Bulk delete failed');
                  });
                }
                return response.json();
              })
              .then((data) => {
                // Don't throw on partial success — let the .then handler remove
                // successfully deleted cards and show a warning about failures
                if (data.error) throw new Error(data.error);
                return data;
              });
          },
        }).then((result) => {
          if (result.isConfirmed) {
            const res = result.value || {};
            const deletedSlugs = (res.results && res.results.success) || slugs;
            const failed = (res.results && res.results.failures) || [];

            // Animate and remove successfully deleted cards
            removeCards(deletedSlugs);

            // Uncheck remaining checkboxes and hide bar
            if (selectAll) {
              selectAll.checked = false;
              selectAll.indeterminate = false;
            }
            const remainingCbs = container.querySelectorAll('input.item-checkbox');
            remainingCbs.forEach((cb) => {
              if (!container.contains(cb) || !cb.closest('[data-template-card]')) {
                return;
              }
              cb.checked = false;
            });
            updateUI();

            if (failed.length > 0) {
              notify(`${failed.length } template(s) could not be deleted`, 'warning');
            } else {
              notify(`${deletedSlugs.length } template(s) deleted`);
            }
          }
        });
      } else {
        // Fallback: native confirm
        window.showConfirm(`Delete ${ count } template${ count > 1 ? 's' : '' }? This cannot be undone.`).then(confirmed => {
          if (!confirmed) return;
          fetch('/admin/cv-templates/bulk-delete', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ slugs: slugs, }),
          })
            .then((r) => { return r.json(); })
            .then((data) => {
              const removed = (data.results && data.results.success) || slugs;
              removeCards(removed);
              updateUI();
              if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
              }
            })
            .catch(() => {
              notify('Bulk delete failed. Please try again.', 'error');
            });
        });
      }
    });
  }
};

ready(() => {
  wireCopyButtons();
  wireSelectionCount();
  wireBulkExport();
  wireToggleButtons();
  wireDeleteButtons();
  wireBulkDelete();
});

export { copyToClipboard, notify };

