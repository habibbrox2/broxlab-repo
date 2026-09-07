/**
 * Email Templates Admin Module
 * Lazy-loaded via loadAdminModule('emailTemplates').
 */

export function initEmailTemplatesEdit({ byId, getAdminDir, escapeHtml, }) {
  const form = byId('emailTemplateForm');
  const previewBtn = byId('previewBtn');
  if (!form || !previewBtn) return;
  const templateId = form.dataset.templateId;
  const adminDir = getAdminDir();

  function previewTemplate() {
    const formData = new FormData(form);
    const originalHTML = previewBtn.innerHTML;
    previewBtn.disabled = true;
    previewBtn.innerHTML = '<i class="lucide lucide-hourglass" style="width:1rem;height:1rem;"></i> Loading...';

    fetch(`${adminDir}/email-templates/${templateId}/preview`, {
      method: 'POST',
      body: formData,
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          byId('previewSubject').innerHTML = `<strong>${escapeHtml(data.subject)}</strong>`;
          byId('previewBody').innerHTML = data.body;
          showToast('Preview updated successfully', 'success');
        } else {
          showToast(`Preview failed: ${data.message}`, 'danger');
        }
      })
      .catch((err) => {
        showToast(`Error: ${err}`, 'danger');
        console.error('Preview error:', err);
      })
      .finally(() => {
        previewBtn.disabled = false;
        previewBtn.innerHTML = originalHTML;
      });
  }

  previewBtn.addEventListener('click', previewTemplate);
}

export function initEmailTemplatesList({ getAdminDir, }) {
  if (!document.querySelector('[data-email-template-list]')) return;
  window.deleteTemplate = async function (id, name) {
    const confirmed = await window.showConfirm(
      `Are you sure you want to delete the email template "${name}"? This action cannot be undone.`
    );
    if (!confirmed) {
      return;
    }
    fetch(`${getAdminDir()}/email-templates/${id}/delete`, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', },
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          showToast(data.message, 'success');
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(data.message, 'danger');
        }
      })
      .catch((err) => showToast(`Error: ${err}`, 'danger'));
  };
}
