/**
 * admin-cv.js — Admin CV Management Module
 * Vanilla JS for confirmations, toggle, bulk ops, purchases, and notifications.
 * Auto-initializes on DOMContentLoaded via event delegation.
 */
'use strict';

const getCsrfToken = () => {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) return meta.getAttribute('content');
  const input = document.querySelector('input[name="csrf_token"]');
  return input ? input.value : '';
};

const notify = (message, type = 'success') => {
  if (window.Swal && typeof window.Swal.fire === 'function') {
    window.Swal.fire({ icon: type, title: message, timer: 1600, showConfirmButton: false, });
    return;
  }
  if (window.showMessage) {
    window.showMessage(message, type === 'error' ? 'danger' : 'success');
  }
};

const setButtonLoading = (form, isLoading, label = 'Processing...') => {
  if (!form) return;
  const btn = form.querySelector('button[type="submit"]');
  if (!btn) return;
  if (isLoading) {
    if (!btn.dataset.originalHtml) {
      btn.dataset.originalHtml = btn.innerHTML;
    }
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    btn.classList.add('opacity-70', 'cursor-wait');
    btn.innerHTML = `<span class="inline-flex items-center gap-2"><span class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-r-transparent"></span>${label}</span>`;
  } else {
    btn.disabled = false;
    btn.removeAttribute('aria-busy');
    btn.classList.remove('opacity-70', 'cursor-wait');
    if (btn.dataset.originalHtml) {
      btn.innerHTML = btn.dataset.originalHtml;
      delete btn.dataset.originalHtml;
    }
  }
};

const submitFormWithLoading = (form, label) => {
  setButtonLoading(form, true, label);
  form.submit();
};

/* ── Generic confirm-action handler (delegated) ── */
const wireConfirmActions = () => {
  document.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-action]');
    if (!form) return;
    const action = form.getAttribute('data-action');

    if (action === 'confirm-delete-cv') {
      e.preventDefault();
      window.Swal && typeof window.Swal.fire === 'function'
        ? window.Swal.fire({
          title: 'Delete CV?',
          text: 'Delete this CV permanently? This action cannot be undone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, delete it',
          cancelButtonText: 'Cancel',
          reverseButtons: true,
        }).then((r) => { if (r.isConfirmed) submitFormWithLoading(form, 'Deleting...'); })
        : confirm('Delete this CV permanently?') && submitFormWithLoading(form, 'Deleting...');
    }

    if (action === 'confirm-delete-personal-info') {
      e.preventDefault();
      window.Swal && typeof window.Swal.fire === 'function'
        ? window.Swal.fire({
          title: 'Delete Record?',
          text: 'Delete this personal info record? This action cannot be undone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, delete it',
          cancelButtonText: 'Cancel',
          reverseButtons: true,
        }).then((r) => { if (r.isConfirmed) submitFormWithLoading(form, 'Deleting...'); })
        : confirm('Delete this record?') && submitFormWithLoading(form, 'Deleting...');
    }

    if (action === 'confirm-delete-template') {
      e.preventDefault();
      window.Swal && typeof window.Swal.fire === 'function'
        ? window.Swal.fire({
          title: 'Delete Template?',
          text: 'Delete this template? Custom templates can be deleted permanently.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, delete it',
          cancelButtonText: 'Cancel',
          reverseButtons: true,
        }).then((r) => { if (r.isConfirmed) submitFormWithLoading(form, 'Deleting...'); })
        : confirm('Delete this template?') && submitFormWithLoading(form, 'Deleting...');
    }

    if (action === 'confirm-restore-template') {
      e.preventDefault();
      window.Swal && typeof window.Swal.fire === 'function'
        ? window.Swal.fire({
          title: 'Restore Template?',
          text: 'Restore this soft-deleted template so it becomes available again?',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#059669',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, restore it',
          cancelButtonText: 'Cancel',
          reverseButtons: true,
        }).then((r) => { if (r.isConfirmed) submitFormWithLoading(form, 'Restoring...'); })
        : confirm('Restore this template?') && submitFormWithLoading(form, 'Restoring...');
    }

    if (action === 'toggle-template-status') {
      e.preventDefault();
      const btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
      if (btn) {
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="inline-flex items-center gap-2"><span class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-r-transparent"></span>Saving...</span>';
      }
      const fd = new FormData(form);
      fetch(form.action, { method: 'POST', body: fd, })
        .then((r) => r.json())
        .then((d) => {
          if (d.success) { notify(`Template ${d.status === 'active' ? 'enabled' : 'disabled'}`); setTimeout(() => window.location.reload(), 600); }
          else { notify(d.error || 'Toggle failed', 'error'); if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.originalHtml || btn.innerHTML; delete btn.dataset.originalHtml; } }
        })
        .catch(() => { notify('Toggle failed', 'error'); if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.originalHtml || btn.innerHTML; delete btn.dataset.originalHtml; } });
    }
  });
};

/* ── Purchase confirm/cancel ── */
const wirePurchaseActions = () => {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-purchase-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-purchase-action');
    const id = btn.getAttribute('data-purchase-id');
    if (!id || !action) return;
    e.preventDefault();

    if (action === 'confirm') {
      window.Swal && typeof window.Swal.fire === 'function'
        ? window.Swal.fire({
          title: 'Confirm Purchase?',
          text: 'Mark this premium template purchase as completed?',
          icon: 'question',
          input: 'textarea',
          inputLabel: 'Admin Note (optional)',
          inputPlaceholder: 'e.g., Manually confirmed by admin',
          showCancelButton: true,
          confirmButtonColor: '#059669',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, confirm',
          cancelButtonText: 'Cancel',
          reverseButtons: true,
          preConfirm: (note) => {
            const f = document.createElement('form');
            f.method = 'POST'; f.action = `/admin/cv-purchases/${id}/confirm`;
            const c = document.createElement('input');
            c.type = 'hidden'; c.name = 'csrf_token'; c.value = getCsrfToken();
            f.appendChild(c);
            if (note) { const n = document.createElement('input'); n.type = 'hidden'; n.name = 'note'; n.value = note; f.appendChild(n); }
            document.body.appendChild(f); f.submit();
          },
        })
        : confirm('Confirm this purchase?') && (window.location.href = `/admin/cv-purchases/${id}/confirm`);
    }

    if (action === 'cancel') {
      window.Swal && typeof window.Swal.fire === 'function'
        ? window.Swal.fire({
          title: 'Cancel Purchase?',
          text: 'Reject this premium template purchase?',
          icon: 'warning',
          input: 'textarea',
          inputLabel: 'Reason (required)',
          inputPlaceholder: 'Why is this purchase being cancelled?',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, cancel',
          cancelButtonText: 'Go back',
          reverseButtons: true,
          preConfirm: (reason) => {
            if (!reason) { window.Swal.showValidationMessage('Please provide a reason'); return; }
            const f = document.createElement('form');
            f.method = 'POST'; f.action = `/admin/cv-purchases/${id}/cancel`;
            const c = document.createElement('input');
            c.type = 'hidden'; c.name = 'csrf_token'; c.value = getCsrfToken();
            f.appendChild(c);
            const n = document.createElement('input');
            n.type = 'hidden'; n.name = 'note'; n.value = reason;
            f.appendChild(n);
            document.body.appendChild(f); f.submit();
          },
        })
        : confirm('Cancel this purchase?') && (window.location.href = `/admin/cv-purchases/${id}/cancel`);
    }
  });
};

/* ── Personal info list: fix template references from items → records ── */
/* Also wire up any template view links by slug */
const wireTemplateViewLinks = () => {
  document.querySelectorAll('[data-template-view-link]').forEach((link) => {
    const slug = link.getAttribute('data-template-slug');
    if (slug) {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = `/admin/cv-templates/view/${slug}`;
      });
    }
  });
};

/* ── Personal info delete buttons ── */
const wirePersonalInfoActions = () => {
  // Wire delete buttons in personal info list
  document.querySelectorAll('[data-action="delete-personal-info"]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const url = btn.getAttribute('data-url') || btn.getAttribute('href');
      if (!url) return;
      window.Swal && typeof window.Swal.fire === 'function'
        ? window.Swal.fire({
          title: 'Delete Record?',
          text: 'Delete this personal info record?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, delete',
          cancelButtonText: 'Cancel',
          reverseButtons: true,
        }).then((r) => { if (r.isConfirmed) window.location.href = url; })
        : confirm('Delete this record?') && (window.location.href = url);
    });
  });
};

document.addEventListener('DOMContentLoaded', () => {
  wireConfirmActions();
  wirePurchaseActions();
  wireTemplateViewLinks();
  wirePersonalInfoActions();
});

export { notify, getCsrfToken };
