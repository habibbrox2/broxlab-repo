/**
 * Settings Admin Module
 * Lazy-loaded via loadAdminModule('settings').
 */

export function initSettingsPage({ byId, getCsrfToken, getAdminDir, }) {
  const settingsRoot = document.querySelector('.settings-page');
  if (!settingsRoot) return;
  const adminPath = getAdminDir();
  const submitBtn = settingsRoot.querySelector('button[type="submit"]');

  document.querySelectorAll('.settings-page input, .settings-page select, .settings-page textarea').forEach((input) => {
    input.addEventListener('change', () => {
      if (submitBtn) submitBtn.classList.add('bg-amber-500', 'hover:bg-amber-600', 'text-white');
    });
  });

  const logoUpload = byId('siteLogoUpload');
  const logoPreview = byId('logoPreview');
  if (logoUpload && logoPreview) {
    logoUpload.addEventListener('change', (event) => {
      const file = event.target.files?.[0];
      if (!file) return;
      const url = URL.createObjectURL(file);
      logoPreview.src = url;
      logoPreview.classList.remove('hidden');
      logoPreview.onload = () => URL.revokeObjectURL(url);
    });
  }

  const faviconUpload = byId('faviconUpload');
  const faviconPreview = byId('faviconPreview');
  if (faviconUpload && faviconPreview) {
    faviconUpload.addEventListener('change', (event) => {
      const file = event.target.files?.[0];
      if (!file) return;
      const url = URL.createObjectURL(file);
      faviconPreview.src = url;
      faviconPreview.onload = () => URL.revokeObjectURL(url);
    });
  }

  byId('removeLogoBtn')?.addEventListener('click', async () => {
    if (!(await window.showConfirm('Remove site logo? This will clear the saved logo.'))) return;
    const hidden = byId('remove_site_logo');
    if (hidden) hidden.value = '1';
    settingsRoot.querySelector('form.needs-validation')?.submit();
  });

  const testEmailBtn = byId('sendTestEmailBtn');
  if (testEmailBtn) {
    testEmailBtn.addEventListener('click', async () => {
      const recipient = byId('testEmailRecipient')?.value || '';
      const action = `${adminPath}/app-settings/send-test-email-ajax`;

      testEmailBtn.disabled = true;
      const originalText = testEmailBtn.textContent;
      testEmailBtn.textContent = 'Sending...';

      try {
        const resp = await fetch(action, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', },
          body: new URLSearchParams({ test_email: recipient, }),
        });
        const data = await resp.json();
        showTestEmailModal(data.success, data.message || 'No response');
      } catch {
        showTestEmailModal(false, 'Request failed');
      } finally {
        testEmailBtn.disabled = false;
        testEmailBtn.textContent = originalText;
      }
    });
  }

  function showTestEmailModal(success, message) {
    let modalEl = byId('testEmailModal');
    if (!modalEl) {
      modalEl = document.createElement('div');
      modalEl.id = 'testEmailModal';
      modalEl.className = 'fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50';
      modalEl.tabIndex = -1;
      modalEl.innerHTML = `
                    <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4">
                      <div class="flex items-center justify-between p-4 border-b border-slate-200">
                        <h5 class="font-semibold text-slate-900">Test Email</h5>
                        <button type="button" class="modern-btn-close" data-brox-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="p-4">
                        <p id="testEmailModalMessage"></p>
                      </div>
                      <div class="flex justify-end gap-2 p-4 border-t border-slate-200">
                        <button type="button" class="modern-btn modern-btn-secondary" data-brox-dismiss="modal">Close</button>
                      </div>
                    </div>`;
      document.body.appendChild(modalEl);
    }

    const msgEl = modalEl.querySelector('#testEmailModalMessage');
    if (msgEl) {
      msgEl.textContent = message;
      msgEl.classList.toggle('text-emerald-600', Boolean(success));
      msgEl.classList.toggle('text-red-600', !success);
    }

    const bootstrapModal = new broxUI.Modal(modalEl);
    bootstrapModal.show();
  }

  const save2faAdminBtn = byId('save2faAdminBtn');
  if (save2faAdminBtn) {
    save2faAdminBtn.addEventListener('click', () => {
      const isRequired = byId('require2faAdmin')?.checked;
      const btn = save2faAdminBtn;
      const originalText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="inline-spinner inline-spinner-sm mr-2"></span>Saving...';

      const csrfToken = getCsrfToken() || '';
      const body = new URLSearchParams({
        csrf_token: csrfToken,
        key: 'require_2fa_for_admin',
        value: isRequired ? '1' : '0',
      });
      fetch('/admin/app-settings/security/update', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: body.toString(),
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.success) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'p-4 rounded-lg bg-emerald-50 border-emerald-200 text-emerald-700 mt-3';
            alertDiv.innerHTML = `
                            <i class="lucide lucide-check-circle mr-2" style="width:1rem;height:1rem;"></i>
                            <strong>Success!</strong> ${data.message}
                            <button type="button" class="modern-btn-close" data-brox-dismiss="alert"></button>
                        `;
            const tabPane = document.querySelector('#security');
            tabPane?.insertBefore(alertDiv, tabPane.firstChild);
            broxUI.Modal.getInstance(byId('require2faAdminModal'))?.hide();
          } else {
            window.showMessage(`Error: ${data.message}`, 'danger');
          }
        })
        .catch(() => {
          window.showMessage('An error occurred. Please try again.', 'danger');
        })
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = originalText;
        });
    });
  }
}

export function initAppSecuritySettings({ byId, getCsrfToken, }) {
  if (!byId('btnExport')) return;

  const csrfToken = getCsrfToken();

  byId('btnExport')?.addEventListener('click', () => {
    window.location.href = '/admin/app-settings/security/export';
  });

  byId('btnImport')?.addEventListener('click', () => {
    const modal = new broxUI.Modal(byId('importModal'));
    modal.show();
  });

  byId('confirmImport')?.addEventListener('click', () => {
    const fileInput = byId('settingsFile');
    const file = fileInput?.files?.[0];
    if (!file) {
      window.showMessage('Please select a file', 'warning');
      return;
    }
    const formData = new FormData(byId('importForm'));
    fetch('/admin/app-settings/security/import', { method: 'POST', body: formData, })
      .then((res) => res.json())
      .then((data) => {
        broxUI.Modal.getInstance(byId('importModal'))?.hide();
        if (data.success) {
          showAlert(`Successfully imported ${data.updated} settings`, 'success');
          setTimeout(() => location.reload(), 1500);
        } else {
          showAlert(data.message || 'Import failed', 'danger');
        }
      })
      .catch((err) => {
        showAlert(`Import error: ${err.message}`, 'danger');
      });
  });

  byId('btnReset')?.addEventListener('click', () => {
    const modal = new broxUI.Modal(byId('resetModal'));
    modal.show();
  });

  byId('resetConfirmation')?.addEventListener('input', (e) => {
    const confirmBtn = byId('confirmReset');
    if (confirmBtn) confirmBtn.disabled = e.target.value !== 'RESET_ALL_SETTINGS';
  });

  byId('confirmReset')?.addEventListener('click', () => {
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('confirm', 'RESET_ALL_SETTINGS');
    fetch('/admin/app-settings/security/reset', { method: 'POST', body: formData, })
      .then((res) => res.json())
      .then((data) => {
        broxUI.Modal.getInstance(byId('resetModal'))?.hide();
        if (data.success) {
          showAlert(data.message, 'success');
          setTimeout(() => location.reload(), 1500);
        } else {
          showAlert(data.message || 'Reset failed', 'danger');
        }
      })
      .catch((err) => {
        showAlert(`Reset error: ${err.message}`, 'danger');
      });
  });

  document.querySelectorAll('.save-single-setting').forEach((btn) => {
    btn.addEventListener('click', function () {
      const key = this.dataset.key;
      const inputId = this.dataset.inputId || key;
      const input = byId(inputId) || byId(key);
      const type = input?.dataset?.type;
      let value = input?.value;
      if (!input) return;
      if (type === 'boolean') value = input.checked ? '1' : '0';
      else if (type === 'json') {
        try {
          JSON.parse(value);
        } catch {
          showAlert(`Invalid JSON for ${key}`, 'danger');
          return;
        }
      }
      const formData = new FormData();
      formData.append('csrf_token', csrfToken);
      formData.append('key', key);
      formData.append('value', value);
      this.disabled = true;
      const originalText = this.innerHTML;
      fetch('/admin/app-settings/security/update', { method: 'POST', body: formData, })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) showAlert(data.message, 'success');
          else showAlert(data.message || 'Save failed', 'danger');
        })
        .catch((err) => {
          showAlert(`Error: ${err.message}`, 'danger');
        })
        .finally(() => {
          this.disabled = false;
          this.innerHTML = originalText;
        });
    });
  });

  document.querySelectorAll('.setting-input').forEach((input) => {
    const originalValue = input.value;
    input.addEventListener('change', function () {
      const resetBtn = this.parentElement.querySelector('.reset-single-setting');
      if (resetBtn)
        resetBtn.style.display = this.value !== originalValue ? 'inline-block' : 'none';
    });
  });

  document.querySelectorAll('.reset-single-setting').forEach((btn) => {
    btn.addEventListener('click', () => {
      location.reload();
    });
  });

  function showAlert(message, type = 'info') {
    const alertDiv = byId('settingsAlert');
    const alertMsg = byId('alertMessage');
    if (!alertDiv || !alertMsg) return;
    const alert = alertDiv.querySelector('.alert');
    alertMsg.textContent = message;
    if (alert) alert.className = `p-4 rounded-xl border ${type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : type === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-700' : type === 'info' ? 'bg-sky-50 border-sky-200 text-sky-700' : 'bg-red-50 border-red-200 text-red-700'}`;
    alertDiv.style.display = 'block';
    setTimeout(() => {
      alertDiv.style.display = 'none';
    }, 5000);
  }
}
