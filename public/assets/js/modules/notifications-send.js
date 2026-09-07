/**
 * Notifications Send
 * Extracted from notifications-workflows.js.
 */

import { escapeHtml } from './core.js';
import { getCsrfToken } from '../shared/utils.js';

const byId = (id) => document.getElementById(id);

import { initNotificationModuleHelpers } from './notifications-shared-helpers.js';

export async function initNotificationsSend() {
  const form = byId('notificationForm');
  if (!form) return;
  const { notificationSystem, analytics, } = await initNotificationModuleHelpers();
  const showWarning = notificationSystem?.showWarning || window.showWarning || window.showMessage;
  const showSuccess = notificationSystem?.showSuccess || window.showSuccess || window.showMessage;
  const showError = notificationSystem?.showError || window.showError || window.showMessage;
  const trackSend = analytics?.trackAdminNotificationSend;

  const recipientType = byId('recipientType');
  const notificationTitle = byId('notificationTitle');
  const notificationMessage = byId('notificationMessage');
  const notificationType = byId('notificationType');
  const notificationTemplate = byId('notificationTemplate');
  const templateVariables = byId('templateVariables');
  const templateVariablesWrap = byId('templateVariablesWrap');
  const applyTemplatePreviewBtn = byId('applyTemplatePreviewBtn');
  const submitBtn = byId('submitBtn');

  const parseJsonObject = (raw) => {
    if (!raw || !String(raw).trim()) return {};
    try {
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
    } catch {
      return null;
    }
  };

  const normalizeTemplateChannels = (channels) => {
    if (!Array.isArray(channels)) return [];
    const normalized = [];
    channels.forEach((channel) => {
      let key = String(channel || '').trim().toLowerCase();
      if (!key) return;
      if (key === 'fcm' || key === 'firebase') key = 'push';
      if (key === 'in-app' || key === 'inapp') key = 'in_app';
      if (!normalized.includes(key)) normalized.push(key);
    });
    return normalized;
  };

  function setTemplateModeState() {
    const hasTemplate = Boolean(notificationTemplate?.value);
    if (templateVariablesWrap) {
      templateVariablesWrap.style.display = hasTemplate ? 'block' : 'none';
    }
    if (notificationTitle) {
      notificationTitle.required = !hasTemplate;
    }
    if (notificationMessage) {
      notificationMessage.required = !hasTemplate;
    }
  }

  function readTemplateVariables(showErrorToast = false) {
    if (!notificationTemplate?.value) return {};
    const parsed = parseJsonObject(templateVariables?.value || '');
    if (parsed === null) {
      if (showErrorToast) {
        showWarning?.('Template variables must be a valid JSON object');
      }
      return null;
    }
    return parsed;
  }

  function applyChannelsFromTemplate(channels = []) {
    const normalized = normalizeTemplateChannels(channels);
    if (!normalized.length) return;

    const channelPush = byId('channelPush');
    const channelInApp = byId('channelInApp');
    const channelEmail = byId('channelEmail');

    if (channelPush) channelPush.checked = normalized.includes('push');
    if (channelInApp) channelInApp.checked = normalized.includes('in_app');
    if (channelEmail) channelEmail.checked = normalized.includes('email');
  }

  async function applySelectedTemplatePreview() {
    if (!notificationTemplate?.value) return true;

    const selectedOption = notificationTemplate.selectedOptions?.[0];
    const templateId = parseInt(selectedOption?.dataset.templateId || '0', 10);
    if (!templateId) return true;

    const variables = readTemplateVariables(true);
    if (variables === null) return false;

    const params = new URLSearchParams();
    if (Object.keys(variables).length > 0) {
      params.set('vars', JSON.stringify(variables));
    }

    const endpoint = `/admin/notification-templates/${templateId}/preview${params.toString() ? `?${params.toString()}` : ''}`;
    try {
      const response = await fetch(endpoint);
      const data = await response.json();
      if (!data?.success) {
        throw new Error(data?.error || 'Template preview failed');
      }

      if (notificationTitle) {
        notificationTitle.value = data.title || '';
        notificationTitle.dispatchEvent(new Event('input'));
      }
      if (notificationMessage) {
        notificationMessage.value = data.body || '';
        notificationMessage.dispatchEvent(new Event('input'));
      }

      applyChannelsFromTemplate(data.channels || []);
      return true;
    } catch {
      showWarning?.('Template preview failed. Notification will use template during send.');
      return false;
    }
  }

  function updateRecipientInfo() {
    const type = recipientType?.value;
    const infoEl = byId('recipientInfo');
    let info = '';
    switch (type) {
      case 'all':
        info = 'This will send to all users and guest devices';
        break;
      case 'guest':
        info = 'Only guest users (not logged in)';
        break;
      case 'specific':
        info = 'Only the specific users you select will receive notifications';
        break;
      case 'role':
        info = 'All users with the selected role will receive notifications';
        break;
      case 'permission':
        info = 'All users with the selected permission will receive notifications';
        break;
    }
    if (info && infoEl) {
      infoEl.innerHTML = `<small class="text-emerald-600 block mt-2"><i class="lucide lucide-check-circle" style="width:0.875rem;height:0.875rem;"></i> ${info}</small>`;
    }
  }

  async function updateRecipientCount() {
    const type = recipientType?.value;
    if (!type) return;
    if (type === 'specific') {
      const ids = Array.from(byId('specificUsers')?.selectedOptions || [])
        .map((o) => parseInt(o.value, 10))
        .filter((id) => !Number.isNaN(id));
      if (!ids.length) {
        byId('recipientCount').textContent = '0';
        const totalUsers = byId('totalUsers');
        if (totalUsers) totalUsers.textContent = '0';
        const guestUsers = byId('guestUsers');
        if (guestUsers) guestUsers.textContent = '0';
        return;
      }
    }
    if (type === 'role' && !byId('roleSelect')?.value) {
      byId('recipientCount').textContent = '0';
      const totalUsers = byId('totalUsers');
      if (totalUsers) totalUsers.textContent = '0';
      const guestUsers = byId('guestUsers');
      if (guestUsers) guestUsers.textContent = '0';
      return;
    }
    if (type === 'permission' && !byId('permissionSelect')?.value) {
      byId('recipientCount').textContent = '0';
      const totalUsers = byId('totalUsers');
      if (totalUsers) totalUsers.textContent = '0';
      const guestUsers = byId('guestUsers');
      if (guestUsers) guestUsers.textContent = '0';
      return;
    }
    try {
      const params = new URLSearchParams({ type, });
      if (type === 'specific') {
        const ids = Array.from(byId('specificUsers')?.selectedOptions || [])
          .map((o) => parseInt(o.value, 10))
          .filter((id) => !Number.isNaN(id));
        if (ids.length) params.set('ids', ids.join(','));
      }
      if (type === 'role') {
        const role = byId('roleSelect')?.value;
        if (role) params.set('role', role);
      }
      if (type === 'permission') {
        const perm = byId('permissionSelect')?.value;
        if (perm) params.set('permission', perm);
      }
      const response = await fetch(`/api/notification/count-recipients?${params.toString()}`);
      const data = await response.json();
      byId('recipientCount').textContent = data.count;
      const totalUsers = byId('totalUsers');
      if (totalUsers) totalUsers.textContent = data.count;
      const guestUsers = byId('guestUsers');
      if (guestUsers) guestUsers.textContent = data.guest_count || 0;
    } catch (error) {
      console.error('Error:', error);
    }
  }

  function initRecipientDefaults() {
    if (!recipientType?.value) recipientType.value = 'all';
    byId('specificUserDiv').style.display = recipientType.value === 'specific' ? 'block' : 'none';
    byId('roleDiv').style.display = recipientType.value === 'role' ? 'block' : 'none';
    byId('permissionDiv').style.display = recipientType.value === 'permission' ? 'block' : 'none';
    byId('recipientPreview').style.display = recipientType.value ? 'block' : 'none';
    if (submitBtn) submitBtn.disabled = !recipientType.value;
    updateRecipientInfo();
    updateRecipientCount();
  }

  initRecipientDefaults();
  setTemplateModeState();

  notificationTemplate?.addEventListener('change', async function () {
    setTemplateModeState();

    const selectedOption = this.selectedOptions?.[0];
    const parsedVars = parseJsonObject(selectedOption?.dataset.templateVars || '{}');
    if (templateVariables) {
      const keys = parsedVars && typeof parsedVars === 'object' ? Object.keys(parsedVars) : [];
      const defaultVars = {};
      keys.forEach((key) => {
        defaultVars[key] = '';
      });
      templateVariables.value = keys.length ? JSON.stringify(defaultVars, null, 2) : '{}';
    }

    await applySelectedTemplatePreview();
  });

  applyTemplatePreviewBtn?.addEventListener('click', async () => {
    await applySelectedTemplatePreview();
  });

  fetch('/api/notification/roles')
    .then(r => r.json())
    .then(data => {
      const select = byId('roleSelect');
      data.roles?.forEach(role => {
        const option = document.createElement('option');
        option.value = role.name;
        option.textContent = role.name;
        select.appendChild(option);
      });
    });

  fetch('/api/notification/permissions')
    .then(r => r.json())
    .then(data => {
      const select = byId('permissionSelect');
      data.permissions?.forEach(perm => {
        const option = document.createElement('option');
        option.value = perm.name;
        option.textContent = perm.name;
        select.appendChild(option);
      });
    });

  recipientType?.addEventListener('change', function () {
    byId('specificUserDiv').style.display = this.value === 'specific' ? 'block' : 'none';
    byId('roleDiv').style.display = this.value === 'role' ? 'block' : 'none';
    byId('permissionDiv').style.display = this.value === 'permission' ? 'block' : 'none';
    byId('recipientPreview').style.display = this.value ? 'block' : 'none';
    if (submitBtn) submitBtn.disabled = !this.value;
    updateRecipientCount();
    updateRecipientInfo();
  });
  byId('specificUsers')?.addEventListener('change', updateRecipientCount);
  byId('roleSelect')?.addEventListener('change', updateRecipientCount);
  byId('permissionSelect')?.addEventListener('change', updateRecipientCount);

  notificationTitle?.addEventListener('input', function () {
    const val = this.value || '';
    byId('previewTitle').textContent = val || 'Notification Title';

    // Title validation (Max 100 chars typically for push)
    if (val.length > 0 && val.length <= 100) {
      this.classList.remove('is-invalid');
      this.classList.add('is-valid');
    } else if (val.length > 100) {
      this.classList.remove('is-valid');
      this.classList.add('is-invalid');
    } else {
      this.classList.remove('is-valid', 'is-invalid');
    }
  });

  notificationMessage?.addEventListener('input', function () {
    const val = this.value || '';
    byId('previewMessage').textContent = val || 'Your notification message will appear here...';

    const count = val.length;
    const wordCountEl = byId('wordCount');

    if (wordCountEl) {
      wordCountEl.textContent = `${count} chars / ${val.split(/\s+/).filter(w => w).length} words`;

      // Visual indicators for length
      wordCountEl.className = 'text-sm font-bold text-slate-900 ';
      if (count > 450) {
        wordCountEl.classList.add('text-red-600');
      } else if (count > 350) {
        wordCountEl.classList.add('text-amber-600');
      } else {
        wordCountEl.classList.add('text-slate-500');
      }
    }

    // Message validation (Max 500 chars)
    if (count > 0 && count <= 500) {
      this.classList.remove('is-invalid');
      this.classList.add('is-valid');
    } else if (count > 500) {
      this.classList.remove('is-valid');
      this.classList.add('is-invalid');
    } else {
      this.classList.remove('is-valid', 'is-invalid');
    }
  });

  notificationType?.addEventListener('change', function () {
    const typeLabels = {
      'general': 'General',
      'promotion': 'Promotion',
      'announcement': 'Announcement',
      'update': 'Update',
      'warning': 'Warning',
      'urgent': 'Urgent',
    };
    byId('previewType').textContent = `Type: ${typeLabels[this.value] || this.value}`;
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const selectedChannels = [];
    if (byId('channelPush')?.checked) selectedChannels.push('push');
    if (byId('channelInApp')?.checked) selectedChannels.push('in_app');
    if (byId('channelEmail')?.checked) selectedChannels.push('email');

    if (selectedChannels.length === 0) {
      showWarning?.('Please select at least one delivery channel');
      return;
    }

    const recipientTypeVal = recipientType?.value;
    let specificIds = [];
    if (recipientTypeVal === 'specific') {
      const opts = byId('specificUsers')?.selectedOptions || [];
      specificIds = Array.from(opts).map(o => parseInt(o.value, 10)).filter(Boolean);
      if (specificIds.length === 0) {
        showWarning?.('Please select at least one specific user');
        return;
      }
    }

    let recipientCount;
    if (recipientTypeVal === 'specific') {
      recipientCount = specificIds.length;
    } else {
      recipientCount = parseInt(byId('recipientCount')?.textContent || '0', 10) || 0;
    }

    const templateSlug = notificationTemplate?.value || '';
    const templateVars = readTemplateVariables(true);
    if (templateVars === null) {
      return;
    }

    const payload = {
      recipient_type: recipientTypeVal,
      specific_ids: specificIds,
      role_name: byId('roleSelect')?.value || '',
      permission_name: byId('permissionSelect')?.value || '',
      title: notificationTitle?.value || '',
      message: notificationMessage?.value || '',
      template_slug: templateSlug,
      template_variables: templateVars,
      type: notificationType?.value || 'general',
      action_url: byId('actionUrl')?.value || '',
      channels: selectedChannels,
      scheduled_at: byId('scheduledTime')?.value || null,
      is_draft: Boolean(byId('saveDraft')?.checked),
      recipient_count: recipientCount,
    };

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="inline-spinner inline-spinner-sm mr-2"></span>Sending...';
    }

    try {
      const response = await fetch('/api/notification/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (data.success) {
        trackSend?.(payload, payload.recipient_count || 0);
        showSuccess?.(data.message || 'Notification sent successfully');
        setTimeout(() => window.location.href = '/admin/notifications', 1500);
      } else {
        showError?.(`Error: ${data.error || 'Unknown error'}`);
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="lucide lucide-check mr-2" style="width:1rem;height:1rem;"></i>Send Notification';
        }
      }
    } catch (error) {
      console.error('Error:', error);
      showError?.(`Notification sending failed: ${error.message}`);
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="lucide lucide-check mr-2" style="width:1rem;height:1rem;"></i>Send Notification';
      }
    }
  });

  fetch('/api/notification/users')
    .then(r => r.json())
    .then(data => {
      const select = byId('specificUsers');
      if (!select) return;
      select.innerHTML = '';
      data.users?.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = `${user.username} (${user.email})`;
        select.appendChild(option);
      });
    });

  const recipientSearchInput = byId('recipientSearchInput');
  const recipientDeviceFilter = byId('recipientDeviceFilter');
  const recipientFilterReset = byId('recipientFilterReset');
  const recipientFilteredCountEl = byId('recipientFilteredCount');
  const recipientFilterHintEl = byId('recipientFilterHint');

  const normalizeText = (value) => String(value || '').trim().toLowerCase();
  const getDeviceCategory = (value) => {
    const v = normalizeText(value);
    if (!v) return 'web';
    if (v.includes('android')) return 'android';
    if (v.includes('iphone') || v.includes('ipad') || v.includes('ios')) return 'ios';
    if (v.includes('windows') || v.includes('mac') || v.includes('linux') || v.includes('desktop')) return 'desktop';
    if (v.includes('web') || v.includes('browser')) return 'web';
    return 'web';
  };

  function updateFilteredMeta(visibleCount, totalCount) {
    if (recipientFilteredCountEl) {
      recipientFilteredCountEl.textContent = String(visibleCount);
    }
    if (!recipientFilterHintEl) return;
    if (totalCount <= 0) {
      recipientFilterHintEl.textContent = 'Preview recipients to use filters';
      return;
    }
    if (visibleCount === totalCount) {
      recipientFilterHintEl.textContent = 'Showing all recipients';
    } else if (visibleCount === 0) {
      recipientFilterHintEl.textContent = 'No recipient matched current filters';
    } else {
      recipientFilterHintEl.textContent = `Showing ${visibleCount} of ${totalCount}`;
    }
  }

  function setFilteredEmptyState(container, show) {
    if (!container) return;
    let emptyState = byId('recipientFilterEmptyState');
    if (!show) {
      if (emptyState) emptyState.remove();
      return;
    }
    if (!emptyState) {
      emptyState = document.createElement('div');
      emptyState.id = 'recipientFilterEmptyState';
      emptyState.className = 'recipient-empty-filter';
      emptyState.innerHTML = '<i class="lucide lucide-search mr-1" style="width:1rem;height:1rem;"></i>No recipients matched the selected filters';
      container.appendChild(emptyState);
    }
  }

  function applyRecipientFilters() {
    const list = byId('recipientList');
    if (!list) return;
    const cards = Array.from(list.querySelectorAll('.recipient-card'));
    if (!cards.length) {
      updateFilteredMeta(0, 0);
      setFilteredEmptyState(list, false);
      return;
    }

    const searchQuery = normalizeText(recipientSearchInput?.value);
    const deviceQuery = normalizeText(recipientDeviceFilter?.value);
    let visibleCount = 0;

    cards.forEach((card) => {
      const name = normalizeText(card.dataset.recipientName);
      const email = normalizeText(card.dataset.recipientEmail);
      const device = normalizeText(card.dataset.recipientDevice);
      const deviceCategory = normalizeText(card.dataset.recipientDeviceCategory);

      const textMatched = !searchQuery
        || name.includes(searchQuery)
        || email.includes(searchQuery);
      const deviceMatched = !deviceQuery
        || deviceCategory === deviceQuery
        || device.includes(deviceQuery);

      const visible = textMatched && deviceMatched;
      card.classList.toggle('is-hidden', !visible);
      if (visible) visibleCount += 1;
    });

    updateFilteredMeta(visibleCount, cards.length);
    setFilteredEmptyState(list, visibleCount === 0);
  }

  if (recipientSearchInput && recipientSearchInput.dataset.bound !== '1') {
    recipientSearchInput.addEventListener('input', applyRecipientFilters);
    recipientSearchInput.dataset.bound = '1';
  }
  if (recipientDeviceFilter && recipientDeviceFilter.dataset.bound !== '1') {
    recipientDeviceFilter.addEventListener('change', applyRecipientFilters);
    recipientDeviceFilter.dataset.bound = '1';
  }
  if (recipientFilterReset && recipientFilterReset.dataset.bound !== '1') {
    recipientFilterReset.addEventListener('click', () => {
      if (recipientSearchInput) recipientSearchInput.value = '';
      if (recipientDeviceFilter) recipientDeviceFilter.value = '';
      applyRecipientFilters();
    });
    recipientFilterReset.dataset.bound = '1';
  }

  updateFilteredMeta(0, 0);

  byId('previewBtn')?.addEventListener('click', async () => {
    const type = recipientType?.value;
    const params = new URLSearchParams({ type, });
    if (type === 'specific') {
      const ids = Array.from(byId('specificUsers')?.selectedOptions || [])
        .map((o) => parseInt(o.value, 10))
        .filter((id) => !Number.isNaN(id));
      if (!ids.length) {
        showWarning?.('বিশেষ ব্যবহারকারী নির্বাচন করুন');
        return;
      }
      params.set('ids', ids.join(','));
    } else if (type === 'role') {
      const role = byId('roleSelect')?.value;
      if (!role) {
        showWarning?.('একটি ভূমিকা নির্বাচন করুন');
        return;
      }
      params.set('role', role);
    } else if (type === 'permission') {
      const perm = byId('permissionSelect')?.value;
      if (!perm) {
        showWarning?.('একটি অনুমতি নির্বাচন করুন');
        return;
      }
      params.set('permission', perm);
    }
    const modal = new broxUI.Modal(byId('recipientModal'));
    try {
      const response = await fetch(`/api/notification/preview-recipients?${params.toString()}`);
      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch {
        throw new Error(`Invalid response from server: ${text.substring(0, 100)}`);
      }

      const list = byId('recipientList');
      const totalCountEl = byId('recipientTotalCount');
      list.innerHTML = '';

      if (data.error) {
        if (totalCountEl) totalCountEl.textContent = '0';
        list.innerHTML = `<div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200 mb-0"><i class="lucide lucide-alert-circle mr-2" style="width:1rem;height:1rem;"></i>Recipient load error: ${data.error}</div>`;
        updateFilteredMeta(0, 0);
      } else if (!data.recipients || data.recipients.length === 0) {
        if (totalCountEl) totalCountEl.textContent = '0';
        const warning = data.warning ? `<div class="p-4 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 mb-2"><i class="lucide lucide-alert-triangle mr-2" style="width:1rem;height:1rem;"></i>${data.warning}</div>` : '';
        list.innerHTML = `${warning}<div class="p-4 rounded-lg bg-sky-50 text-sky-700 border border-sky-200 text-center mb-0"><i class="lucide lucide-info mr-2" style="width:1rem;height:1rem;"></i>No recipients found for this selection</div>`;
        updateFilteredMeta(0, 0);
      } else {
        const actualTotal = data.count ?? data.recipients.length;
        if (totalCountEl) totalCountEl.textContent = String(actualTotal);
        if (data.recipients.length < actualTotal) {
          const notice = document.createElement('div');
          notice.className = 'text-end text-slate-500 text-sm mt-2';
          notice.textContent = `Showing first ${data.recipients.length} of ${actualTotal}`;
          list.parentElement?.appendChild(notice);
        }
        const gridHtml = data.recipients.map((recipient, index) => {
          const enabledDate = new Date(recipient.enabled_at);
          const formattedDate = enabledDate.toLocaleDateString('bn-BD', { year: 'numeric', month: '2-digit', day: '2-digit', });
          const formattedTime = enabledDate.toLocaleTimeString('bn-BD', { hour: '2-digit', minute: '2-digit', });
          const username = escapeHtml(recipient.username || 'Unknown');
          const email = escapeHtml(recipient.email || '');
          const deviceInfo = escapeHtml(recipient.device_info || 'Web');
          const rawDeviceInfo = String(recipient.device_info || 'Web');
          const deviceCategory = escapeHtml(getDeviceCategory(rawDeviceInfo));
          return `
                        <div class="w-full md:w-1/2 lg:w-1/3 px-3 mb-3">
                            <div class="recipient-card h-100"
                                data-recipient-name="${username}"
                                data-recipient-email="${email}"
                                data-recipient-device="${deviceInfo}"
                                data-recipient-device-category="${deviceCategory}">
                                <div class="recipient-header">
                                    <div class="flex-1">
                                        <div class="text-truncate">
                                            <strong class="block text-slate-900 truncate">${username}</strong>
                                            ${email ? `<small class="text-slate-500 truncate">${email}</small>` : ''}
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 ml-2">${index + 1}</span>
                                </div>
                                <div class="recipient-body">
                                    <div class="recipient-info-item">
                                        <div class="recipient-info-label">
                                            <i class="lucide lucide-smartphone" style="width:1rem;height:1rem;"></i>
                                            Device
                                        </div>
                                        <div class="flex-1 text-end">
                                            <small class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-50 text-slate-700">${deviceInfo}</small>
                                        </div>
                                    </div>
                                    <div class="recipient-info-item">
                                        <div class="recipient-info-label">
                                            <i class="lucide lucide-calendar-clock" style="width:1rem;height:1rem;"></i>
                                            Date
                                        </div>
                                        <div class="flex-1 text-end">
                                            <small class="text-slate-500">${formattedDate}</small>
                                        </div>
                                    </div>
                                    <div class="recipient-info-item">
                                        <div class="recipient-info-label">
                                            <i class="lucide lucide-clock" style="width:1rem;height:1rem;"></i>
                                            Time
                                        </div>
                                        <div class="flex-1 text-end">
                                            <small class="text-slate-500">${formattedTime}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
        }).join('');
        list.innerHTML = `<div class="row g-3">${gridHtml}</div>`;
        applyRecipientFilters();
      }
    } catch (error) {
      console.error('Fetch error:', error);
      const totalCountEl = byId('recipientTotalCount');
      if (totalCountEl) totalCountEl.textContent = '0';
      updateFilteredMeta(0, 0);
      byId('recipientList').innerHTML = `<div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200 mb-0"><i class="lucide lucide-alert-triangle mr-2" style="width:1rem;height:1rem;"></i>Error: ${error.message}</div>`;
    }

    modal.show();
  });
}
