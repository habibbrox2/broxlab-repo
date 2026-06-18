/**
 * Applications Admin Module
 * Lazy-loaded via loadAdminModule("applications").
 * Handles application approval/rejection workflow with modals.
 */

export function initApplicationsView({ byId, getCsrfToken, }) {
  if (!byId('approveModal') || !byId('rejectModal')) return;
  let appIdToAction = null;
  const csrf = getCsrfToken();

  function submitAction(url, body) {
    return fetch(url, { method: 'POST', body, }).then((r) => r.json());
  }

  window.approveApplication = function (appId) {
    appIdToAction = appId;
    new broxUI.Modal(byId('approveModal')).show();
  };

  window.confirmApprove = function () {
    const notes = byId('approveNotes')?.value || '';
    const formData = new FormData();
    formData.append('csrf_token', csrf);
    if (notes) formData.append('notes', notes);
    submitAction(`/admin/applications/${appIdToAction}/approve`, formData)
      .then((data) => {
        showToast('Success', data.message, 'success');
        setTimeout(() => window.location.reload(), 1500);
      })
      .catch(() => showToast('Error', 'Failed to approve application', 'error'));
  };

  window.rejectApplication = function (appId) {
    appIdToAction = appId;
    new broxUI.Modal(byId('rejectModal')).show();
  };

  window.confirmReject = function () {
    const reason = byId('rejectReason')?.value || '';
    if (!reason) {
      showToast('Error', 'Rejection reason is required', 'error');
      return;
    }
    const formData = new FormData();
    formData.append('csrf_token', csrf);
    formData.append('reason', reason);
    submitAction(`/admin/applications/${appIdToAction}/reject`, formData)
      .then((data) => {
        showToast('Success', data.message, 'success');
        setTimeout(() => window.location.reload(), 1500);
      })
      .catch(() => showToast('Error', 'Failed to reject application', 'error'));
  };

  window.markProcessing = function (appId) {
    appIdToAction = appId;
    new broxUI.Modal(byId('processingModal')).show();
  };

  window.confirmProcessing = function () {
    const notes = byId('processingNotes')?.value || '';
    const formData = new FormData();
    formData.append('csrf_token', csrf);
    if (notes) formData.append('notes', notes);
    submitAction(`/admin/applications/${appIdToAction}/processing`, formData)
      .then((data) => {
        showToast('Success', data.message, 'success');
        setTimeout(() => window.location.reload(), 1500);
      })
      .catch(() => showToast('Error', 'Failed to update application', 'error'));
  };

  window.addNote = function (appId) {
    const note = byId('noteText')?.value || '';
    if (!note) {
      showToast('Error', 'Note cannot be empty', 'error');
      return;
    }
    const formData = new FormData();
    formData.append('csrf_token', csrf);
    formData.append('note', note);
    submitAction(`/admin/applications/${appId}/note`, formData)
      .then((data) => {
        showToast('Success', data.message, 'success');
        byId('noteText').value = '';
        setTimeout(() => window.location.reload(), 1500);
      })
      .catch(() => showToast('Error', 'Failed to add note', 'error'));
  };

  window.activateService = async function (appId) {
    if (!(await window.showConfirm('Activate this service for the user?'))) return;
    const formData = new FormData();
    formData.append('csrf_token', csrf);
    submitAction(`/admin/applications/${appId}/activate`, formData)
      .then((data) => {
        showToast('Success', data.message, 'success');
        setTimeout(() => window.location.reload(), 1500);
      })
      .catch(() => showToast('Error', 'Failed to activate service', 'error'));
  };

  window.revertStatus = async function (appId) {
    if (!(await window.showConfirm('Revert application status to pending?'))) return;
    fetch(`/api/admin/applications/${appId}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, },
      body: JSON.stringify({ status: 'pending', }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          showToast('Success', data.message || 'Status reverted', 'success');
          setTimeout(() => window.location.reload(), 1000);
        } else {
          showToast('Error', data.message || 'Failed to revert status', 'error');
        }
      })
      .catch(() => showToast('Error', 'Failed to revert status', 'error'));
  };
}
