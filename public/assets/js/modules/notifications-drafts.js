/**
 * Notifications Drafts
 * Extracted from notifications-workflows.js.
 */

import { getCsrfToken } from '../shared/utils.js';

const byId = (id) => document.getElementById(id);

export async function initNotificationsDrafts() {
  const list = byId('draftsList');
  if (!list) return;
  const notificationSystem =
    await import('/assets/firebase/v2/dist/notification-system.js').catch(() => null);
  const showSuccess = notificationSystem?.showSuccess || window.showSuccess || window.showMessage;
  const showError = notificationSystem?.showError || window.showError || window.showMessage;

  async function loadDrafts() {
    try {
      const response = await fetch('/api/notification/list-drafts');
      const data = await response.json();
      if (data.success && data.drafts.length > 0) {
        let html = `
                        <table class="table table-hover mb-0">
                            <thead class="bg-slate-100">
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Message</th>
                                    <th>Type</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
        data.drafts.forEach((draft) => {
          const createdAt = new Date(draft.created_at).toLocaleDateString('bn-BD', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
          });
          html += `
                            <tr>
                                <td>#${draft.id}</td>
                                <td><strong>${draft.title}</strong></td>
                                <td>${draft.message.substring(0, 50)}...</td>
                                <td><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">${draft.type}</span></td>
                                <td>${createdAt}</td>
                                <td>
                                    <button class="modern-btn text-xs px-2.5 py-1 bg-indigo-600 text-white hover:bg-indigo-700 rounded-lg" data-action="edit-draft" data-draft-id="${draft.id}">
                                        <i class="lucide lucide-pencil" style="width:1rem;height:1rem;"></i> Edit
                                    </button>
                                    <button class="modern-btn text-xs px-2.5 py-1 bg-emerald-600 text-white hover:bg-emerald-700 rounded-lg" data-action="send-draft" data-draft-id="${draft.id}">
                                        <i class="lucide lucide-send" style="width:1rem;height:1rem;"></i> Send
                                    </button>
                                    <button class="modern-btn text-xs px-2.5 py-1 bg-red-600 text-white hover:bg-red-700 rounded-lg" data-action="delete-draft" data-draft-id="${draft.id}">
                                        <i class="lucide lucide-trash-2" style="width:1rem;height:1rem;"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        `;
        });
        html += '</tbody></table>';
        list.innerHTML = html;
      } else {
        list.innerHTML = `
                        <div class="p-4 text-center text-slate-500">
                            <i class="lucide lucide-inbox mb-3 block" style="width:2rem;height:2rem;"></i>
                            <strong>No drafts found</strong><br>
                            <small><a href="/admin/notifications/send" class="no-underline">Create a new notification draft</a></small>
                        </div>
                    `;
      }
    } catch (error) {
      list.innerHTML = `<div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200 m-3">Error: ${error.message}</div>`;
    }
  }

  function editDraft(draftId) {
    fetch(`/api/notification/draft-detail?draft_id=${draftId}`)
      .then((r) => r.json())
      .then((data) => {
        if (data.success && data.draft) {
          const draft = data.draft;
          byId('editDraftId').value = draft.id;
          byId('editTitle').value = draft.title;
          byId('editMessage').value = draft.message;
          byId('editType').value = draft.type;
          byId('editActionUrl').value = draft.action_url || '';
          byId('editRecipientType').value = draft.recipient_type;
          new broxUI.Modal(byId('editDraftModal')).show();
        } else {
          showError?.('Failed to load draft details');
        }
      })
      .catch((err) => {
        console.error('Error:', err);
        showError?.(`Error: ${err.message}`);
      });
  }

  async function deleteDraft(draftId) {
    if (!(await window.showConfirm('Do you want to delete this draft?'))) return;
    try {
      const response = await fetch('/api/notification/delete-draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
        body: JSON.stringify({ draft_id: draftId, }),
      });
      const data = await response.json();
      if (data.success) {
        showSuccess?.('Draft deleted successfully');
        loadDrafts();
      } else {
        showError?.('Failed to delete draft');
      }
    } catch (error) {
      console.error('Error:', error);
      showError?.(`Error: ${error.message}`);
    }
  }

  function renderSuccessState(notificationId) {
    if (!list) return;
    list.innerHTML = `
      <div class="flex flex-col items-center justify-center py-12 text-center">
        <div class="w-16 h-16 rounded-2xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mb-5">
          <i class="lucide lucide-check-circle w-8 h-8 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Draft Sent Successfully!</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6 max-w-xs">Your draft notification has been sent to the selected recipients.</p>
        <div class="flex flex-wrap items-center justify-center gap-3">
          <a href="/admin/notifications/view?id=${notificationId}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-500/20 hover:shadow-md hover:shadow-indigo-500/25 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-eye w-4 h-4"></i> View Notification
          </a>
          <a href="/admin/notifications/list" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-list w-4 h-4"></i> Back to List
          </a>
        </div>
      </div>
    `;
  }

  async function sendDraft(draftId) {
    if (!(await window.showConfirm('Do you want to send this draft now?'))) return;
    try {
      const response = await fetch('/api/notification/send-draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
        body: JSON.stringify({ draft_id: draftId, }),
      });
      const data = await response.json();
      if (data.success) {
        showSuccess?.(data.message || 'Draft sent successfully');
        renderSuccessState(data.notification_id);
      } else {
        showError?.(`Error: ${data.error || 'Unknown error'}`);
      }
    } catch (error) {
      console.error('Error:', error);
      showError?.(`Error: ${error.message}`);
    }
  }

  async function saveEditedDraft() {
    const draftId = byId('editDraftId').value;
    const data = {
      draft_id: draftId,
      title: byId('editTitle').value,
      message: byId('editMessage').value,
      type: byId('editType').value,
      action_url: byId('editActionUrl').value,
      recipient_type: byId('editRecipientType').value,
      channels: ['push',],
      recipient_ids: [],
    };
    try {
      const response = await fetch('/api/notification/update-draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
        body: JSON.stringify(data),
      });
      const result = await response.json();
      if (result.success) {
        showSuccess?.(result.message || 'Draft updated successfully');
        broxUI.Modal.getInstance(byId('editDraftModal'))?.hide();
        loadDrafts();
      } else {
        showError?.('Failed to update draft');
      }
    } catch (error) {
      showError?.(`Error: ${error.message}`);
    }
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest?.('[data-action]');
    if (!button) return;
    const action = button.dataset.action;
    if (action === 'save-draft') return saveEditedDraft();
    if (action === 'edit-draft') return editDraft(parseInt(button.dataset.draftId, 10));
    if (action === 'send-draft') return sendDraft(parseInt(button.dataset.draftId, 10));
    if (action === 'delete-draft') return deleteDraft(parseInt(button.dataset.draftId, 10));
  });

  loadDrafts();
}
