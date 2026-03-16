import { escapeHtml } from './core.js';

const byId = (id) => document.getElementById(id);
const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

async function initNotificationModuleHelpers() {
    try {
        const notificationSystem = await import('/assets/firebase/v2/dist/notification-system.js');
        const analytics = await import('/assets/firebase/v2/dist/analytics.js');
        return { notificationSystem, analytics };
    } catch (e) {
        return { notificationSystem: null, analytics: null };
    }
}

export async function initNotificationsSend() {
    const form = byId('notificationForm');
    if (!form) return;
    const { notificationSystem, analytics } = await initNotificationModuleHelpers();
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
        } catch (error) {
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
        const hasTemplate = !!notificationTemplate?.value;
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
        } catch (error) {
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
            infoEl.innerHTML = '<small class="text-success d-block mt-2"><i class="bi bi-check-circle"></i> ' + info + '</small>';
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
            const params = new URLSearchParams({ type });
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
            const response = await fetch('/api/notification/count-recipients?' + params.toString());
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
            wordCountEl.className = 'small fw-bold ';
            if (count > 450) {
                wordCountEl.classList.add('text-danger');
            } else if (count > 350) {
                wordCountEl.classList.add('text-warning');
            } else {
                wordCountEl.classList.add('text-muted');
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
            'urgent': 'Urgent'
        };
        byId('previewType').textContent = 'Type: ' + (typeLabels[this.value] || this.value);
    });

    form.addEventListener('submit', async function (e) {
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

        let recipientCount = 0;
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
            is_draft: !!byId('saveDraft')?.checked,
            recipient_count: recipientCount
        };

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
        }

        try {
            const response = await fetch('/api/notification/send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (data.success) {
                trackSend?.(payload, payload.recipient_count || 0);
                showSuccess?.(data.message || 'Notification sent successfully');
                setTimeout(() => window.location.href = '/admin/notifications', 1500);
            } else {
                showError?.('Error: ' + (data.error || 'Unknown error'));
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check me-2"></i>Send Notification';
                }
            }
        } catch (error) {
            console.error('Error:', error);
            showError?.('Notification sending failed: ' + error.message);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check me-2"></i>Send Notification';
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
                option.textContent = user.username + ' (' + user.email + ')';
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
            emptyState.innerHTML = '<i class="bi bi-search me-1"></i>No recipients matched the selected filters';
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

    byId('previewBtn')?.addEventListener('click', async function () {
        const type = recipientType?.value;
        const params = new URLSearchParams({ type });
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
        const modal = new bootstrap.Modal(byId('recipientModal'));
        try {
            const response = await fetch('/api/notification/preview-recipients?' + params.toString());
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid response from server: ' + text.substring(0, 100));
            }

            const list = byId('recipientList');
            const totalCountEl = byId('recipientTotalCount');
            list.innerHTML = '';

            if (data.error) {
                if (totalCountEl) totalCountEl.textContent = '0';
                list.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-circle me-2"></i>Recipient load error: ' + data.error + '</div>';
                updateFilteredMeta(0, 0);
            } else if (!data.recipients || data.recipients.length === 0) {
                if (totalCountEl) totalCountEl.textContent = '0';
                const warning = data.warning ? `<div class="alert alert-warning mb-2"><i class="bi bi-exclamation-triangle me-2"></i>${data.warning}</div>` : '';
                list.innerHTML = warning + '<div class="alert alert-info text-center mb-0"><i class="bi bi-info-circle me-2"></i>No recipients found for this selection</div>';
                updateFilteredMeta(0, 0);
            } else {
                const actualTotal = data.count ?? data.recipients.length;
                if (totalCountEl) totalCountEl.textContent = String(actualTotal);
                if (data.recipients.length < actualTotal) {
                    const notice = document.createElement('div');
                    notice.className = 'text-end text-muted small mt-2';
                    notice.textContent = `Showing first ${data.recipients.length} of ${actualTotal}`;
                    list.parentElement?.appendChild(notice);
                }
                const gridHtml = data.recipients.map((recipient, index) => {
                    const enabledDate = new Date(recipient.enabled_at);
                    const formattedDate = enabledDate.toLocaleDateString('bn-BD', { year: 'numeric', month: '2-digit', day: '2-digit' });
                    const formattedTime = enabledDate.toLocaleTimeString('bn-BD', { hour: '2-digit', minute: '2-digit' });
                    const username = escapeHtml(recipient.username || 'Unknown');
                    const email = escapeHtml(recipient.email || '');
                    const deviceInfo = escapeHtml(recipient.device_info || 'Web');
                    const rawDeviceInfo = String(recipient.device_info || 'Web');
                    const deviceCategory = escapeHtml(getDeviceCategory(rawDeviceInfo));
                    return `
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="recipient-card h-100"
                                data-recipient-name="${username}"
                                data-recipient-email="${email}"
                                data-recipient-device="${deviceInfo}"
                                data-recipient-device-category="${deviceCategory}">
                                <div class="recipient-header">
                                    <div class="flex-grow-1">
                                        <div class="text-truncate">
                                            <strong class="d-block text-dark text-truncate">${username}</strong>
                                            ${email ? `<small class="text-muted text-truncate">${email}</small>` : ''}
                                        </div>
                                    </div>
                                    <span class="badge bg-primary ms-2">${index + 1}</span>
                                </div>
                                <div class="recipient-body">
                                    <div class="recipient-info-item">
                                        <div class="recipient-info-label">
                                            <i class="bi bi-device-type"></i>
                                            Device
                                        </div>
                                        <div class="flex-grow-1 text-end">
                                            <small class="badge bg-light text-dark">${deviceInfo}</small>
                                        </div>
                                    </div>
                                    <div class="recipient-info-item">
                                        <div class="recipient-info-label">
                                            <i class="bi bi-calendar-event"></i>
                                            Date
                                        </div>
                                        <div class="flex-grow-1 text-end">
                                            <small class="text-muted">${formattedDate}</small>
                                        </div>
                                    </div>
                                    <div class="recipient-info-item">
                                        <div class="recipient-info-label">
                                            <i class="bi bi-clock"></i>
                                            Time
                                        </div>
                                        <div class="flex-grow-1 text-end">
                                            <small class="text-muted">${formattedTime}</small>
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
            byId('recipientList').innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Error: ' + error.message + '</div>';
        }

        modal.show();
    });
}


export async function initNotificationsScheduled() {
    if (!byId('scheduledNotificationsRoot')) return;

    let scheduledClient = null;
    try {
        const mod = await import('/assets/firebase/v2/dist/scheduled-notifications.js');
        const ScheduledNotifications = mod.ScheduledNotifications || mod.default;
        if (ScheduledNotifications) {
            scheduledClient = new ScheduledNotifications();
        }
    } catch (e) {
        return;
    }

    const scheduleModalEl = byId('scheduleModal');
    const scheduleModal = scheduleModalEl ? new bootstrap.Modal(scheduleModalEl) : null;

    function openScheduleModal() {
        const form = byId('scheduleForm');
        if (form) form.reset();
        handleRecipientTypeChange();
        scheduleModal?.show();
    }

    function handleRecipientTypeChange() {
        const type = byId('recipientType')?.value;
        const div = byId('recipientIdsDiv');
        if (div) div.style.display = type === 'user' ? 'block' : 'none';
    }

    async function submitScheduleForm(event) {
        event.preventDefault();
        const submitBtn = byId('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Scheduling...';
        }

        try {
            const date = byId('scheduledDate')?.value;
            const time = byId('scheduledTime')?.value;
            const scheduledAt = `${date}T${time}:00`;

            const channels = [];
            document.querySelectorAll('input[id^="channel-"]:checked').forEach((el) => {
                channels.push(el.value);
            });

            const recipientType = byId('recipientType')?.value;
            let recipientIds = [];
            if (recipientType === 'user') {
                const ids = byId('recipientIds')?.value || '';
                recipientIds = ids.split(',').map((id) => parseInt(id.trim(), 10)).filter((id) => !Number.isNaN(id));
            }

            const result = await scheduledClient.scheduleNotification({
                title: byId('notifTitle')?.value || '',
                body: byId('notifBody')?.value || '',
                scheduled_at: scheduledAt,
                user_timezone: byId('userTimezone')?.value || 'Asia/Dhaka',
                recipient_type: recipientType,
                recipient_ids: recipientIds,
                channels
            });

            if (result?.success) {
                alert('Notification scheduled successfully.');
                scheduleModal?.hide();
                loadScheduledNotifications('scheduled');
            } else {
                alert('Failed to schedule notification: ' + (result?.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Server error: ' + error.message);
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Schedule';
            }
        }
    }

    function getStatusBadge(status) {
        const badges = {
            scheduled: '<span class="badge bg-info">Scheduled</span>',
            sending: '<span class="badge bg-warning">Sending</span>',
            sent: '<span class="badge bg-success">Sent</span>',
            failed: '<span class="badge bg-danger">Failed</span>',
            cancelled: '<span class="badge bg-secondary">Cancelled</span>',
            draft: '<span class="badge bg-secondary">Draft</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    }

    async function loadScheduledNotifications(status = 'scheduled') {
        try {
            const response = await fetch(`/api/notification/list-scheduled?status=${encodeURIComponent(status)}&limit=50`);
            const data = await response.json();
            const container = byId(`${status}-list`);
            if (!container) return;
            container.innerHTML = '';

            const list = (data && (data.scheduled || data.notifications)) || [];
            if (!data?.success || !Array.isArray(list) || list.length === 0) {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox display-4 mb-3 d-block"></i>
                            <p>No notifications found.</p>
                        </div>
                    </div>
                `;
                return;
            }

            list.forEach((notif) => {
                const statusBadge = getStatusBadge(notif.status);
                container.innerHTML += `
                    <div class="col-lg-6 mb-4">
                        <div class="admin-panel-card h-100">
                            <div class="admin-panel-card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="mb-0">${escapeHtml(notif.title)}</h5>
                                        <small class="text-muted">${escapeHtml(notif.created_at || '-')}</small>
                                    </div>
                                    ${statusBadge}
                                </div>

                                <p class="text-muted mb-3">${escapeHtml(notif.body || notif.message || '')}</p>

                                <div class="mb-3">
                                    <div class="mb-2">
                                        <small class="text-muted"><i class="bi bi-calendar me-1"></i>Scheduled:</small>
                                        <div class="fw-bold">${escapeHtml(notif.scheduled_at || '-')}</div>
                                    </div>
                                    <div>
                                        <small class="text-muted"><i class="bi bi-people me-1"></i>Recipient:</small>
                                        <span class="badge bg-info text-capitalize">${escapeHtml(notif.recipient_type || 'all')}</span>
                                    </div>
                                </div>

                                <div class="mt-auto pt-2 border-top">
                                    <div class="btn-group btn-group-sm w-100" role="group">
                                        <button class="btn btn-outline-primary" data-action="view-scheduled" data-notification-id="${notif.id}">
                                            <i class="bi bi-eye me-1"></i>Details
                                        </button>
                                        ${notif.status === 'scheduled' ? `
                                            <button class="btn btn-outline-danger" data-action="cancel-scheduled" data-notification-id="${notif.id}">
                                                <i class="bi bi-x me-1"></i>Cancel
                                            </button>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        } catch (error) {
            console.error('Error:', error);
            const container = byId(`${status}-list`);
            if (container) {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>Error: ${escapeHtml(error.message || 'Failed to load')}
                        </div>
                    </div>
                `;
            }
        }
    }

    async function cancelScheduled(id) {
        if (!confirm('Cancel this scheduled notification?')) return;
        try {
            const response = await fetch(`/api/notification/scheduled/${id}`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() }
            });
            const data = await response.json();
            if (data.success) {
                alert('Schedule cancelled.');
                loadScheduledNotifications('scheduled');
            } else {
                alert('Failed: ' + (data.message || data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Server error while cancelling.');
        }
    }

    function viewScheduledDetail(id) {
        alert('Detailed view is coming soon. ID: ' + id);
    }

    document.addEventListener('click', (event) => {
        const openBtn = event.target.closest?.('[data-action="open-schedule-modal"]');
        if (openBtn) {
            openScheduleModal();
            return;
        }

        const viewBtn = event.target.closest?.('[data-action="view-scheduled"]');
        if (viewBtn) {
            const id = parseInt(viewBtn.dataset.notificationId, 10);
            if (!Number.isNaN(id)) viewScheduledDetail(id);
            return;
        }

        const cancelBtn = event.target.closest?.('[data-action="cancel-scheduled"]');
        if (cancelBtn) {
            const id = parseInt(cancelBtn.dataset.notificationId, 10);
            if (!Number.isNaN(id)) cancelScheduled(id);
        }
    });

    byId('scheduleForm')?.addEventListener('submit', submitScheduleForm);
    byId('recipientType')?.addEventListener('change', handleRecipientTypeChange);

    handleRecipientTypeChange();
    loadScheduledNotifications('scheduled');
    byId('scheduled-tab')?.addEventListener('shown.bs.tab', () => loadScheduledNotifications('scheduled'));
    byId('sent-tab')?.addEventListener('shown.bs.tab', () => loadScheduledNotifications('sent'));
    byId('failed-tab')?.addEventListener('shown.bs.tab', () => loadScheduledNotifications('failed'));
    byId('draft-tab')?.addEventListener('shown.bs.tab', () => loadScheduledNotifications('draft'));
}


export async function initNotificationsDeviceSync() {
    const root = byId('deviceSyncRoot');
    if (!root) return;
    let autoSyncInterval = null;

    function getCurrentDeviceId() {
        const key = '__fcm_device_id';
        try {
            const existing = localStorage.getItem(key);
            if (existing) return existing;
            const generated = `${Date.now()}-${Math.random().toString(36).slice(2, 11)}`;
            localStorage.setItem(key, generated);
            return generated;
        } catch (e) {
            return `admin-${Date.now()}`;
        }
    }

    function shortId(value) {
        const text = String(value || '');
        return text ? `${text.substring(0, 8)}...` : 'N/A';
    }

    function getActionBadge(action) {
        const badges = {
            read: '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Read</span>',
            dismissed: '<span class="badge bg-warning"><i class="bi bi-x-circle me-1"></i>Dismissed</span>',
            deleted: '<span class="badge bg-danger"><i class="bi bi-trash me-1"></i>Deleted</span>',
            sync: '<span class="badge bg-info"><i class="bi bi-arrow-repeat me-1"></i>Sync</span>'
        };
        return badges[action] || `<span class="badge bg-secondary">${escapeHtml(action || 'unknown')}</span>`;
    }

    function getSyncStatusBadge(log) {
        const status = String(log?.status || '').toLowerCase();
        if (status === 'sent' || status === 'success' || status === 'synced' || log?.synced_at) {
            return '<span class="badge bg-success">Synced</span>';
        }
        if (status === 'failed' || status === 'error') {
            return '<span class="badge bg-danger">Failed</span>';
        }
        return '<span class="badge bg-warning">Pending</span>';
    }

    async function parseJsonResponse(response) {
        const raw = await response.text();
        const cleaned = raw.replace(/^\uFEFF/, '').trim();
        try {
            return JSON.parse(cleaned || '{}');
        } catch (e) {
            throw new Error(`Invalid JSON response (${response.status}): ${cleaned.slice(0, 160)}`);
        }
    }

    function updateSyncStats(data) {
        try {
            const activeDevices = typeof data.count === 'number'
                ? data.count
                : (Array.isArray(data.devices) ? data.devices.length : 0);
            byId('activeDevicesCount').textContent = activeDevices;
            byId('pendingSyncCount').textContent = data.pending_count || 0;
            byId('syncedItemsCount').textContent = data.synced_count || 0;

            const total = (data.pending_count || 0) + (data.synced_count || 0);
            const syncRate = total > 0 ? Math.round(((data.synced_count || 0) / total) * 100) : 0;
            byId('syncRatePercent').textContent = syncRate + '%';
        } catch (error) {
            console.error('Error updating stats:', error);
        }
    }

    async function loadDevicesList() {
        try {
            const response = await fetch('/api/notification/device-list');
            const data = await parseJsonResponse(response);

            if (!data.success) {
                throw new Error(data.error || data.message || 'Failed to load device list');
            }

            const tbody = byId('devicesTableBody');
            if (!tbody) return;
            tbody.innerHTML = '';

            if (!Array.isArray(data.devices) || data.devices.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="bi bi-inbox"></i> No devices found
                        </td>
                    </tr>
                `;
                updateSyncStats(data);
                return;
            }

            data.devices.forEach((device) => {
                const deviceId = String(device.device_id || '');
                const deviceName = device.device_name || device.username || 'Unknown Device';
                const platform = device.device_type || device.platform || 'web';
                const lastSeenRaw = device.last_active || device.last_sync || device.created_at || null;
                const lastSeen = lastSeenRaw ? new Date(lastSeenRaw).toLocaleString('bn-BD') : 'N/A';

                tbody.innerHTML += `
                    <tr>
                        <td>
                            <i class="bi bi-phone me-2"></i>
                            ${escapeHtml(deviceName)}
                        </td>
                        <td><code>${escapeHtml(shortId(deviceId))}</code></td>
                        <td><span class="badge bg-light text-dark">${escapeHtml(platform)}</span></td>
                        <td><small class="text-muted">${escapeHtml(lastSeen)}</small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="syncDevice('${escapeHtml(deviceId)}')">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="removeDevice('${escapeHtml(deviceId)}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            updateSyncStats(data);
        } catch (error) {
            console.error('Error:', error);
            const tbody = byId('devicesTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-danger py-4">
                            <i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(error.message || 'Failed')}
                        </td>
                    </tr>
                `;
            }
        }
    }

    async function loadSyncLog(filter = 'all') {
        try {
            const response = await fetch(`/api/notification/sync-log?action=${filter !== 'all' ? encodeURIComponent(filter) : ''}`);
            const data = await parseJsonResponse(response);

            const tbody = byId('syncLogBody');
            if (!tbody) return;
            tbody.innerHTML = '';

            if (!data.success || !Array.isArray(data.logs) || data.logs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="bi bi-inbox"></i> No sync logs
                        </td>
                    </tr>
                `;
                return;
            }

            data.logs.forEach((log) => {
                const actionBadge = getActionBadge(log.action);
                const statusBadge = getSyncStatusBadge(log);
                const timestampRaw = log.synced_at || log.created_at || null;
                const timestamp = timestampRaw ? new Date(timestampRaw).toLocaleString('bn-BD') : 'N/A';

                tbody.innerHTML += `
                    <tr>
                        <td><small>${escapeHtml(timestamp)}</small></td>
                        <td>${actionBadge}</td>
                        <td><code>${escapeHtml(log.notification_id ?? '-')}</code></td>
                        <td><code>${escapeHtml(shortId(log.device_id || ''))}</code></td>
                        <td>${statusBadge}</td>
                    </tr>
                `;
            });
        } catch (error) {
            console.error('Error:', error);
        }
    }

    async function manualSync() {
        try {
            const response = await fetch('/api/notification/sync-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify({
                    device_id: getCurrentDeviceId(),
                    device_type: 'web',
                    action: 'sync'
                })
            });
            const data = await parseJsonResponse(response);
            if (data.success) {
                alert('Sync completed successfully');
                loadSyncLog();
                loadDevicesList();
            } else {
                alert('Sync failed: ' + (data.error || data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Server error while syncing');
        }
    }

    async function syncDevice(deviceId) {
        try {
            const response = await fetch('/api/notification/sync-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify({
                    device_id: String(deviceId || ''),
                    device_type: 'web',
                    action: 'sync'
                })
            });
            const data = await parseJsonResponse(response);
            if (data.success) {
                alert('Device synced successfully');
                loadDevicesList();
                loadSyncLog();
            } else {
                alert('Device sync failed: ' + (data.error || data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Server error while syncing device');
        }
    }

    async function removeDevice(deviceId) {
        if (!confirm('Are you sure you want to remove this device?')) return;
        try {
            const response = await fetch(`/api/notification/devices/${encodeURIComponent(deviceId)}`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() }
            });
            const data = await parseJsonResponse(response);
            if (data.success) {
                alert('Device removed successfully');
                loadDevicesList();
                loadSyncLog();
            } else {
                alert('Failed to remove device: ' + (data.error || data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Server error while removing device');
        }
    }

    async function clearSyncLog() {
        alert('Clear log API is not configured in backend.');
        loadSyncLog();
    }

    function filterSyncLog(filter) {
        loadSyncLog(filter);
    }

    function refreshDeviceList() {
        loadDevicesList();
    }

    window.refreshDeviceList = refreshDeviceList;
    window.manualSync = manualSync;
    window.clearSyncLog = clearSyncLog;
    window.filterSyncLog = filterSyncLog;
    window.syncDevice = syncDevice;
    window.removeDevice = removeDevice;

    byId('autoSyncToggle')?.addEventListener('change', function () {
        if (this.checked) {
            manualSync();
            autoSyncInterval = setInterval(manualSync, 30000);
        } else {
            clearInterval(autoSyncInterval);
        }
    });

    loadDevicesList();
    loadSyncLog();
    if (byId('autoSyncToggle')?.checked) {
        autoSyncInterval = setInterval(manualSync, 30000);
    }
}


export async function initNotificationsOfflineHandler() {
    if (!byId('offlineHandlerRoot')) return;
    try {
        const mod = await import('/assets/firebase/v2/dist/offline-handler.js');
        const OfflineNotificationHandler = mod.OfflineNotificationHandler || mod.default;
        if (!OfflineNotificationHandler) return;

        const offlineHandler = new OfflineNotificationHandler();
        let offlineModal = null;

        function initializeOfflineModal() {
            if (offlineModal) return;
            const offlineModalElement = byId('offlineModal');
            if (offlineModalElement && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                offlineModal = new bootstrap.Modal(offlineModalElement);
            }
        }

        async function refreshBuffer() {
            try {
                const buffered = await offlineHandler?.getBufferedNotifications?.() || [];
                const tbody = byId('bufferedTable');
                tbody.innerHTML = '';

                if (buffered.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox"></i> No buffered notifications found
                            </td>
                        </tr>
                    `;
                    return;
                }

                buffered.forEach(notif => {
                    const savedTime = new Date(notif.savedAt).toLocaleString('bn-BD');
                    const html = `
                        <tr>
                            <td><code>${notif.id.substring(0, 8)}...</code></td>
                            <td>${escapeHtml(notif.title)}</td>
                            <td><small>${savedTime}</small></td>
                            <td><span class="badge bg-info">Buffered</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" data-action="remove-buffer" data-notification-id="${notif.id}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += html;
                });

                byId('bufferCount').textContent = buffered.length;
            } catch (error) {
                console.error('Error:', error);
            }
        }

        async function loadRetryQueue(filter = 'all') {
            try {
                const retries = await offlineHandler?.getRetryQueue?.() || [];
                const tbody = byId('retryQueueTable');
                tbody.innerHTML = '';

                const filtered = filter === 'all' ? retries : retries.filter(r => {
                    if (filter === 'pending') return r.status === 'pending';
                    if (filter === 'failed') return r.status === 'failed';
                    return true;
                });

                if (filtered.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox"></i> No retry queue items
                            </td>
                        </tr>
                    `;
                    return;
                }

                filtered.forEach(retry => {
                    const nextRetry = new Date(retry.nextRetryTime).toLocaleString('bn-BD');
                    const statusBadge = retry.status === 'pending'
                        ? '<span class="badge bg-warning">Pending</span>'
                        : '<span class="badge bg-danger">Failed</span>';
                    const html = `
                        <tr>
                            <td><code>${retry.id.substring(0, 8)}...</code></td>
                            <td>${retry.notificationId.substring(0, 8)}...</td>
                            <td>${retry.retryCount}</td>
                            <td><small>${nextRetry}</small></td>
                            <td>${statusBadge}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-action="force-retry" data-retry-id="${retry.id}">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += html;
                });

                byId('retryCount').textContent = filtered.length;
            } catch (error) {
                console.error('Error:', error);
            }
        }

        async function removeFromBuffer(id) {
            try {
                await offlineHandler?.removeFromBuffer?.(id);
                refreshBuffer();
            } catch (error) {
                console.error('Error removing from buffer:', error);
            }
        }

        async function forceRetry(id) {
            try {
                await offlineHandler?.forceRetry?.(id);
                loadRetryQueue();
            } catch (error) {
                console.error('Error forcing retry:', error);
            }
        }

        async function processQueue() {
            try {
                await offlineHandler?.processQueue?.();
                alert('Queue processed successfully');
                refreshBuffer();
                loadRetryQueue();
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function clearExpiredCache() {
            try {
                await offlineHandler?.clearExpiredCache?.();
                alert('Expired cache cleared successfully');
                refreshBuffer();
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function clearAllBuffer() {
            if (!confirm('Do you want to clear all buffered notifications? This action cannot be undone.')) return;
            try {
                await offlineHandler?.clearCache?.();
                alert('All buffered notifications were cleared');
                refreshBuffer();
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        function simulateOfflineMode() {
            if (!offlineModal) {
                initializeOfflineModal();
            }
            if (offlineModal) {
                offlineModal.show();
            } else {
                alert('Offline simulation modal is not available');
            }
        }

        function applyOfflineMode() {
            const mode = document.querySelector('input[name="offlineMode"]:checked')?.value;
            alert(`Offline simulation mode set to: ${mode}. Verify behavior in DevTools network panel.`);
            offlineModal?.hide();
        }

        function filterRetryQueue(filter) {
            loadRetryQueue(filter);
        }

        async function filterDeliveryHistory(filter) {
            try {
                const history = await offlineHandler?.getDeliveryHistory?.() || [];
                const tbody = byId('deliveryHistoryTable');
                tbody.innerHTML = '';

                const filtered = filter === 'all' ? history : history.filter(h => {
                    if (filter === 'success') return h.status === 'success';
                    if (filter === 'failed') return h.status === 'failed';
                    return true;
                });

                if (filtered.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox"></i> No delivery history records
                            </td>
                        </tr>
                    `;
                    return;
                }

                filtered.forEach(h => {
                    const time = new Date(h.timestamp).toLocaleString('bn-BD');
                    const statusBadge = h.status === 'success'
                        ? '<span class="badge bg-success">Success</span>'
                        : '<span class="badge bg-danger">Failed</span>';
                    const html = `
                        <tr>
                            <td><small>${time}</small></td>
                            <td><code>${h.notificationId.substring(0, 8)}...</code></td>
                            <td>${h.retryCount}</td>
                            <td>${statusBadge}</td>
                            <td>
                                <small class="text-muted">${escapeHtml(h.error || 'N/A')}</small>
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += html;
                });
            } catch (error) {
                console.error('Error:', error);
            }
        }

        document.addEventListener('click', (event) => {
            const button = event.target.closest?.('[data-action]');
            if (!button) return;
            const action = button.dataset.action;
            if (action === 'refresh-buffer') return refreshBuffer();
            if (action === 'clear-expired-cache') return clearExpiredCache();
            if (action === 'process-queue') return processQueue();
            if (action === 'simulate-offline') return simulateOfflineMode();
            if (action === 'clear-all-buffer') return clearAllBuffer();
            if (action === 'remove-buffer') return removeFromBuffer(button.dataset.notificationId);
            if (action === 'force-retry') return forceRetry(button.dataset.retryId);
            if (action === 'filter-retry-queue') return filterRetryQueue(button.dataset.filter || 'all');
            if (action === 'filter-history') return filterDeliveryHistory(button.dataset.filter || 'all');
            if (action === 'apply-offline-mode') return applyOfflineMode();
        });

        function initializePageContent() {
            if (typeof bootstrap === 'undefined') {
                setTimeout(initializePageContent, 100);
                return;
            }
            initializeOfflineModal();
            refreshBuffer();
            loadRetryQueue();
            filterDeliveryHistory('all');
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializePageContent);
        } else {
            initializePageContent();
        }
    } catch (e) { }
}
