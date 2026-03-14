import { fetchJson } from './shared/fetch-utils.js';

const PROVIDER_UI = {
    google: { name: 'Google', icon: 'google', color: '#EA4335', btn: 'danger' },
    facebook: { name: 'Facebook', icon: 'facebook', color: '#1877F2', btn: 'primary' },
    github: { name: 'GitHub', icon: 'github', color: '#333333', btn: 'dark' }
};

const runtimeAssetVersion = (() => {
    try {
        return new URL(import.meta.url).searchParams.get('v') || '';
    } catch (error) {
        return '';
    }
})();

function withAssetVersion(url) {
    if (!runtimeAssetVersion) return url;
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}v=${encodeURIComponent(runtimeAssetVersion)}`;
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(value);
    return div.innerHTML;
}

function normalizeAlertType(type) {
    if (!type) return 'info';
    if (type === 'error') return 'danger';
    return type;
}

function defaultShowAlert(message, type = 'info', containerId = 'alerts-container') {
    const normalizedType = normalizeAlertType(type);
    const safeMessage = String(message || '').trim() || 'Request completed.';

    if (typeof window.showMessage === 'function') {
        window.showMessage(safeMessage, normalizedType, 5000);
        return;
    }

    const container = document.getElementById(containerId)
        || document.getElementById('alert-container')
        || document.getElementById('alerts-container');

    if (!container) {
        const logFn = normalizedType === 'danger' ? console.error : console.log;
        logFn('[AccountSettings]', safeMessage);
        return;
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${normalizedType} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
        <i class="bi bi-${normalizedType === 'success' ? 'check-circle-fill' : normalizedType === 'danger' ? 'exclamation-circle-fill' : 'info-circle-fill'} me-2"></i>
        ${escapeHtml(safeMessage)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    container.appendChild(alertDiv);
    setTimeout(() => {
        alertDiv.remove?.();
    }, 5000);
}

function getCsrfToken(selector) {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta?.content) return meta.content;

    if (selector) {
        const el = document.querySelector(selector);
        if (el) return el.value || el.content || '';
    }

    const hidden = document.getElementById('csrf_token');
    if (hidden?.value) return hidden.value;
    return '';
}

function mapErrorCodeToMessage(code, fallbackMessage) {
    const normalized = String(code || '').toLowerCase().replace(/^auth\//, '');
    const messages = {
        credential_already_in_use: 'This provider account is already linked to another user.',
        no_such_provider: 'This provider is not linked to your account.',
        provider_not_linked: 'This provider is not linked to your account.',
        reauth_required: 'Please re-authenticate before unlinking this account.',
        invalid_credentials: 'The provided credentials are incorrect.',
        invalid_token: 'Authentication token is invalid or expired. Please try again.',
        invalid_provider: 'Invalid OAuth provider selected.',
        popup_blocked: 'Popup was blocked by your browser. Please allow popups and try again.',
        oauth_provider_disabled: 'OAuth provider is currently disabled.',
        missing_idtoken: 'Authentication token is missing. Please try again.',
        csrf_token_invalid: 'Security token is invalid. Please refresh and try again.',
        server_error: 'Server error. Please try again.',
        cannot_unlink_last_method: 'Cannot unlink your only login method. Please set a password first.',
        unauthorized: 'Please log in again to continue.'
    };

    return messages[normalized] || fallbackMessage || 'Request failed. Please try again.';
}

function mapFirebaseRuntimeMessage(message, fallbackMessage) {
    const raw = String(message || '');
    const normalized = raw.toLowerCase();

    if (
        normalized.includes('redirect_uri_mismatch') ||
        normalized.includes('redirect uri mismatch') ||
        normalized.includes('url mismatch')
    ) {
        return 'OAuth provider redirect URL mismatch. Please contact admin to verify provider redirect settings.';
    }

    if (normalized.includes('unauthorized-domain') || normalized.includes('unauthorized domain')) {
        return 'This domain is not authorized for Firebase OAuth. Please contact admin.';
    }

    return fallbackMessage || raw || 'Authentication failed. Please try again.';
}

function normalizeChannelPreferenceKey(channel) {
    const normalized = String(channel || '').trim().toLowerCase();
    if (normalized === 'in_app' || normalized === 'inapp') return 'in-app';
    return normalized;
}

function channelPreferenceInputId(channel) {
    const key = normalizeChannelPreferenceKey(channel);
    if (!key) return '';
    return `${key}-notifications`;
}

function renderProviders(providersContainer, providers, theme, onLinkProvider) {
    if (!providersContainer) return;

    const providerEntries = Object.entries(providers || {});
    if (providerEntries.length === 0) {
        providersContainer.innerHTML = `
            <div class="alert alert-info small mb-0">
                <i class="bi bi-info-circle-fill me-2"></i>
                No OAuth providers are currently available.
            </div>
        `;
        return;
    }

    const html = providerEntries.map(([provider, info]) => {
        const meta = PROVIDER_UI[provider] || {};
        const name = escapeHtml(info?.name || meta.name || provider);
        const icon = escapeHtml(meta.icon || provider);

        if (theme === 'modern') {
            return `
                <button type="button" class="modern-btn modern-btn-white modern-btn-sm js-oauth-link-btn" data-provider="${escapeHtml(provider)}">
                    <i class="bi bi-${icon} me-1"></i> Link ${name} Account
                </button>
            `;
        }

        const btnColor = escapeHtml(meta.btn || 'secondary');
        return `
            <button type="button" class="btn btn-outline-${btnColor} btn-sm js-oauth-link-btn" data-provider="${escapeHtml(provider)}">
                <i class="bi bi-${icon} me-1"></i> Link ${name} Account
            </button>
        `;
    }).join('');

    providersContainer.innerHTML = html;
    providersContainer.querySelectorAll('.js-oauth-link-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const provider = btn.dataset.provider || '';
            if (provider) onLinkProvider(provider);
        });
    });
}

function renderAccounts(accountsContainer, accounts, theme, onSetPrimary, onUnlink) {
    if (!accountsContainer) return;

    if (!Array.isArray(accounts) || accounts.length === 0) {
        accountsContainer.innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>No linked accounts yet.</strong> Link an account below to get started.
            </div>
        `;
        return;
    }

    const rows = accounts.map((account) => {
        const provider = String(account.provider || '').toLowerCase();
        const providerMeta = PROVIDER_UI[provider] || {};
        const providerLabel = escapeHtml(providerMeta.name || (provider.charAt(0).toUpperCase() + provider.slice(1)));
        const icon = escapeHtml(providerMeta.icon || 'link-45deg');
        const color = escapeHtml(providerMeta.color || '#0d6efd');
        const email = escapeHtml(account.provider_email || account.email || 'Not available');
        const linkedDate = account.linked_at ? new Date(account.linked_at).toLocaleDateString() : 'N/A';
        const isPrimary = Number(account.is_primary) === 1;

        if (theme === 'modern') {
            return `
                <div class="admin-panel-card mb-3 border-0" style="background-color: #f8f9fa;">
                    <div class="admin-panel-card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-${icon}" style="font-size: 1.5rem; color: ${color};"></i>
                                <div>
                                    <h6 class="mb-0">${providerLabel}</h6>
                                    <small class="text-muted">${email}</small>
                                </div>
                            </div>
                            <span class="badge ${isPrimary ? 'bg-primary' : 'bg-success'}">
                                <i class="bi bi-${isPrimary ? 'star-fill' : 'check-circle'} me-1"></i>
                                ${isPrimary ? 'Primary' : 'Connected'}
                            </span>
                        </div>
                        <p class="text-muted small mb-3">Linked on ${escapeHtml(linkedDate)}</p>
                        <div class="d-flex gap-2">
                            ${isPrimary ? '' : `<button type="button" class="modern-btn modern-btn-primary modern-btn-sm js-oauth-set-primary" data-provider="${escapeHtml(provider)}"><i class="bi bi-star me-1"></i> Set Primary</button>`}
                            <button type="button" class="modern-btn modern-btn-danger modern-btn-sm js-oauth-unlink" data-provider="${escapeHtml(provider)}">
                                <i class="bi bi-unlink me-1"></i> Unlink
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        return `
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <div>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-${icon} me-2 fs-5"></i>
                        <div>
                            <strong class="d-block">${providerLabel} Account</strong>
                            <small class="text-muted d-block">${email}</small>
                            <small class="text-muted d-block">Linked: ${escapeHtml(linkedDate)}</small>
                        </div>
                        ${isPrimary ? '<span class="badge bg-success ms-2">Primary</span>' : ''}
                    </div>
                </div>
                <div class="btn-group" role="group">
                    ${isPrimary ? '' : `<button type="button" class="btn btn-sm btn-outline-secondary js-oauth-set-primary" data-provider="${escapeHtml(provider)}">Set Primary</button>`}
                    <button type="button" class="btn btn-sm btn-outline-danger js-oauth-unlink" data-provider="${escapeHtml(provider)}">Unlink</button>
                </div>
            </div>
        `;
    }).join('');

    accountsContainer.innerHTML = theme === 'modern'
        ? rows
        : `<div class="list-group list-group-flush">${rows}</div>`;

    accountsContainer.querySelectorAll('.js-oauth-set-primary').forEach((btn) => {
        btn.addEventListener('click', () => {
            const provider = btn.dataset.provider || '';
            if (provider) onSetPrimary(provider);
        });
    });

    accountsContainer.querySelectorAll('.js-oauth-unlink').forEach((btn) => {
        btn.addEventListener('click', () => {
            const provider = btn.dataset.provider || '';
            if (provider) onUnlink(provider);
        });
    });
}

function updatePasswordStrengthMeter(password, config = {}) {
    const requirements = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*()_+\-=\[\]{};:'"",.<>?\/\\]/.test(password)
    };

    const met = Object.values(requirements).filter(Boolean).length;
    const strength = met / 5;

    const meterFill = document.getElementById(config.meterFillId || 'strength-meter-fill');
    if (meterFill) {
        meterFill.style.width = `${Math.round(strength * 100)}%`;
        meterFill.style.backgroundColor = strength < 0.4 ? '#dc3545' : strength < 0.7 ? '#ffc107' : '#28a745';
    }

    const meterText = document.getElementById(config.meterTextId || 'strength-text');
    if (meterText) {
        meterText.textContent = strength < 0.4 ? 'Very Weak' : strength < 0.7 ? 'Weak' : strength < 0.9 ? 'Good' : 'Strong';
    }

    const reqIds = config.requirementIds || {
        length: 'req-length',
        uppercase: 'req-uppercase',
        lowercase: 'req-lowercase',
        number: 'req-number',
        special: 'req-special'
    };
    Object.keys(reqIds).forEach((key) => {
        const el = document.getElementById(reqIds[key]);
        if (el) el.classList.toggle('met', Boolean(requirements[key]));
    });
}

export function initNotificationPreferences(options = {}) {
    const saveBtn = document.getElementById(options.saveButtonId || 'save-notifications-btn');
    const hasChannelInputs = document.querySelectorAll('.notification-channel').length > 0;

    if (!saveBtn && !hasChannelInputs) {
        return null;
    }

    const showAlert = typeof options.showAlert === 'function'
        ? options.showAlert
        : (message, type = 'info') => defaultShowAlert(message, type, options.alertsContainerId || 'alerts-container');
    const csrfToken = () => getCsrfToken(options.csrfTokenSelector);

    const getChannelState = (channelKey) => {
        const el = document.getElementById(channelPreferenceInputId(channelKey));
        return Boolean(el?.checked);
    };

    const setChannelState = (channelKey, enabled) => {
        const el = document.getElementById(channelPreferenceInputId(channelKey));
        if (el) el.checked = Boolean(enabled);
    };

    const loadCurrent = async () => {
        const { data, ok } = await fetchJson('/api/user/notification-preferences', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            timeoutMs: 10000
        });

        if (!ok || !data?.success) {
            return false;
        }

        const channels = data?.channels;
        if (channels && typeof channels === 'object') {
            Object.keys(channels).forEach((channelKey) => {
                setChannelState(channelKey, channels[channelKey]);
            });
        }

        const marketingId = options.marketingCheckboxId || 'marketing-emails';
        if (Object.prototype.hasOwnProperty.call(data, 'marketing_emails')) {
            const marketingEl = document.getElementById(marketingId);
            if (marketingEl) {
                marketingEl.checked = Boolean(data.marketing_emails);
            }
        }

        return true;
    };

    const saveCurrent = async () => {
        if (saveBtn) {
            saveBtn.disabled = true;
        }

        const payload = {
            email_notifications: getChannelState('email') ? '1' : '0',
            push_notifications: getChannelState('push') ? '1' : '0',
            sms_notifications: getChannelState('sms') ? '1' : '0',
            'in-app_notifications': getChannelState('in-app') ? '1' : '0',
            csrf_token: csrfToken()
        };

        const marketingId = options.marketingCheckboxId || 'marketing-emails';
        const marketingEl = document.getElementById(marketingId);
        if (marketingEl) {
            payload.marketing_emails = marketingEl.checked ? '1' : '0';
        }

        const { data, ok } = await fetchJson('/api/user/notification-preferences', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(payload),
            timeoutMs: 12000
        });

        if (saveBtn) {
            saveBtn.disabled = false;
        }

        if (ok && data?.success) {
            showAlert('Notification preferences saved successfully', 'success');
            return true;
        }

        showAlert(data?.error || data?.message || 'Failed to save notification preferences', 'danger');
        return false;
    };

    saveBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        saveCurrent().catch((error) => {
            console.error('Failed to save notification preferences:', error);
            if (saveBtn) saveBtn.disabled = false;
            showAlert('An error occurred while saving preferences', 'danger');
        });
    });

    loadCurrent().catch((error) => {
        console.error('Failed to load notification preferences:', error);
    });

    return {
        reload: loadCurrent,
        save: saveCurrent
    };
}

export function initAccountSettingsOAuth(options = {}) {
    const theme = options.theme === 'modern' ? 'modern' : 'bootstrap';
    const accountsContainer = document.getElementById(options.accountsContainerId || 'oauth-accounts-container');
    const providersContainer = document.getElementById(options.providersContainerId || 'oauth-providers-container');

    if (!accountsContainer && !providersContainer) {
        return null;
    }

    const showAlert = typeof options.showAlert === 'function'
        ? options.showAlert
        : (message, type = 'info') => defaultShowAlert(message, type, options.alertsContainerId || 'alerts-container');

    const csrfToken = () => getCsrfToken(options.csrfTokenSelector);

    const popupCancelCodes = new Set([
        'popup-closed-by-user',
        'popup_closed_by_user',
        'cancelled-popup-request',
        'cancelled_popup_request'
    ]);
    const normalizeErrorCode = (value) => String(value || '')
        .toLowerCase()
        .replace(/^auth\//, '')
        .replace(/-/g, '_')
        .trim();
    const getPayloadErrorCode = (payload) => normalizeErrorCode(payload?.error_code || payload?.code || payload?.errorCode);
    const isPopupCancelError = (err) => popupCancelCodes.has(normalizeErrorCode(err?.code || err?.errorCode));

    let firebaseAuthModulePromise = null;
    const getFirebaseAuthModule = async () => {
        if (!firebaseAuthModulePromise) {
            firebaseAuthModulePromise = import(withAssetVersion('/assets/firebase/v2/dist/auth.js'))
                .catch((error) => {
                    firebaseAuthModulePromise = null;
                    throw error;
                });
        }
        return firebaseAuthModulePromise;
    };

    const providerSignInMap = {
        google: 'signInWithGoogle',
        facebook: 'signInWithFacebook',
        github: 'signInWithGithub'
    };
    let currentHasPassword = true;

    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    const waitForFirebaseIdToken = async (authMod, timeoutMs = 10000) => {
        const getCurrentUserFn = authMod?.getCurrentUser || authMod?.default?.getCurrentUser;
        const getIdTokenFn = authMod?.getIdToken || authMod?.default?.getIdToken;
        const startedAt = Date.now();

        while ((Date.now() - startedAt) < timeoutMs) {
            try {
                if (typeof getCurrentUserFn === 'function') {
                    const currentUser = await getCurrentUserFn();
                    if (currentUser?.getIdToken) {
                        const tokenFromUser = await currentUser.getIdToken(true).catch(() => '');
                        if (tokenFromUser) return tokenFromUser;
                    }
                }

                if (typeof getIdTokenFn === 'function') {
                    const token = await getIdTokenFn(true).catch(() => '');
                    if (token) return token;
                }
            } catch (e) {
                // Keep polling until timeout
            }

            await sleep(300);
        }

        return '';
    };

    const obtainIdTokenViaPopup = async (provider) => {
        const normalizedProvider = String(provider || '').toLowerCase();
        const fnName = providerSignInMap[normalizedProvider];
        if (!fnName) {
            throw new Error('Invalid OAuth provider');
        }

        const authMod = await getFirebaseAuthModule();
        const signInFn = authMod?.[fnName] || authMod?.default?.[fnName];
        if (typeof signInFn !== 'function') {
            throw new Error('firebase_auth_unavailable');
        }
        const authResult = await signInFn({ syncWithBackend: false });
        const idToken = await authResult?.user?.getIdToken(true);
        if (!idToken) {
            throw new Error('missing_idtoken');
        }
        return idToken;
    };

    const setPrimary = async (provider) => {
        const { data } = await fetchJson('/api/oauth/set-primary', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                provider,
                csrf_token: csrfToken()
            })
        });

        if (data?.success) {
            showAlert(data.message || `${provider} set as primary`, 'success');
        } else {
            showAlert(data?.error || data?.message || 'Failed to set primary account', 'danger');
        }

        await loadLinkedAccounts();
    };

    const unlink = async (provider) => {
        const providerLabel = provider.charAt(0).toUpperCase() + provider.slice(1);
        if (!window.confirm(`Are you sure you want to unlink your ${providerLabel} account?`)) {
            return;
        }

        const reauthOk = await ensureRecentReauth(provider);
        if (!reauthOk) {
            return;
        }

        const { data } = await fetchJson('/api/oauth/unlink', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                provider,
                csrf_token: csrfToken()
            })
        });

        if (data?.success) {
            showAlert(data.message || `${providerLabel} account unlinked`, 'success');
        } else {
            const errorCode = getPayloadErrorCode(data);
            showAlert(mapErrorCodeToMessage(errorCode, data?.error || data?.message || 'Failed to unlink account'), 'danger');
        }

        await loadLinkedAccounts();
    };

    const ensureRecentReauth = async (provider) => {
        const token = csrfToken();

        if (currentHasPassword) {
            const currentPassword = window.prompt('For security, enter your current password to continue:');
            if (currentPassword === null) {
                showAlert('Re-authentication was cancelled.', 'warning');
                return false;
            }

            if (!String(currentPassword).trim()) {
                showAlert('Current password is required for re-authentication.', 'warning');
                return false;
            }

            const { data } = await fetchJson('/api/oauth/reauth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    csrf_token: token
                })
            });

            if (data?.success) {
                return true;
            }

            const errorCode = getPayloadErrorCode(data);
            showAlert(mapErrorCodeToMessage(errorCode, data?.error || data?.message || 'Re-authentication failed'), 'danger');
            return false;
        }

        const normalizedProvider = String(provider || '').toLowerCase();
        const fnName = providerSignInMap[normalizedProvider];
        if (!fnName) {
            showAlert('Invalid OAuth provider for re-authentication.', 'danger');
            return false;
        }

        try {
            const idToken = await obtainIdTokenViaPopup(normalizedProvider);

            const { data } = await fetchJson('/api/oauth/reauth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    idToken,
                    provider: normalizedProvider,
                    csrf_token: token
                })
            });

            if (data?.success) {
                return true;
            }

            const errorCode = getPayloadErrorCode(data);
            showAlert(mapErrorCodeToMessage(errorCode, data?.error || data?.message || 'Re-authentication failed'), 'danger');
            return false;
        } catch (err) {
            const errorCode = normalizeErrorCode(err?.code || err?.errorCode);
            if (errorCode === 'popup_blocked' || errorCode === 'operation_not_supported_in_this_environment') {
                showAlert(mapErrorCodeToMessage('popup_blocked', 'Popup was blocked by your browser.'), 'warning');
                return false;
            }

            if (isPopupCancelError(err)) {
                showAlert('Re-authentication popup was closed before completion.', 'warning');
                return false;
            }

            const code = normalizeErrorCode(err?.code || err?.errorCode);
            const message = mapFirebaseRuntimeMessage(err?.message || err?.error, 'OAuth re-authentication failed');
            showAlert(mapErrorCodeToMessage(code, message), 'danger');
            return false;
        }
    };

    const linkProvider = async (provider) => {
        const normalizedProvider = String(provider || '').toLowerCase();
        const fnName = providerSignInMap[normalizedProvider];
        if (!fnName) {
            showAlert('Invalid OAuth provider.', 'danger');
            return;
        }

        if (accountsContainer) {
            accountsContainer.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <p class="text-muted small mt-2">Opening ${escapeHtml(normalizedProvider)} sign-in popup...</p>
                </div>
            `;
        }

        try {
            const idToken = await obtainIdTokenViaPopup(normalizedProvider);

            const token = csrfToken();
            const { data, status } = await fetchJson('/api/firebase/link', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    idToken,
                    provider: normalizedProvider,
                    csrf_token: token
                })
            });

            if (data?.success) {
                showAlert(data.message || 'Account linked successfully', 'success');
            } else if (status === 409) {
                const errorCode = getPayloadErrorCode(data) || 'credential_already_in_use';
                showAlert(mapErrorCodeToMessage(errorCode, data?.error || 'This provider account is already linked to another user.'), 'danger');
            } else {
                const errorCode = getPayloadErrorCode(data);
                showAlert(mapErrorCodeToMessage(errorCode, data?.error || data?.message || 'Failed to link account'), 'danger');
            }
        } catch (err) {
            const errorCode = normalizeErrorCode(err?.code || err?.errorCode);
            if (errorCode === 'popup_blocked' || errorCode === 'operation_not_supported_in_this_environment') {
                showAlert(mapErrorCodeToMessage('popup_blocked', 'Popup was blocked by your browser.'), 'warning');
                await loadLinkedAccounts();
                return;
            }

            if (isPopupCancelError(err)) {
                showAlert('Login popup was closed before completing authentication.', 'warning');
            } else {
                const code = normalizeErrorCode(err?.code || err?.errorCode);
                const message = err?.message || err?.error || 'Authentication failed while linking account.';
                showAlert(mapErrorCodeToMessage(code, mapFirebaseRuntimeMessage(String(message), String(message))), 'danger');
            }
        }

        await loadLinkedAccounts();
    };

    const loadProviders = async () => {
        if (!providersContainer) return;
        // Show available OAuth providers for linking via Firebase
        const providers = {
            google: { name: 'Google' },
            facebook: { name: 'Facebook' },
            github: { name: 'GitHub' }
        };

        renderProviders(providersContainer, providers, theme, linkProvider);
    };

    const loadLinkedAccounts = async () => {
        if (!accountsContainer) return;
        accountsContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                <p class="text-muted small mt-2">Loading your linked accounts...</p>
            </div>
        `;

        const { data, ok } = await fetchJson('/api/oauth/linked-accounts', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            timeoutMs: 12000
        });

        if (!ok) {
            showAlert('Failed to load linked accounts. Please refresh and try again.', 'danger');
            renderAccounts(accountsContainer, [], theme, setPrimary, unlink);
            return;
        }

        currentHasPassword = Boolean(data?.has_password);

        const accounts = Array.isArray(data?.linked_accounts)
            ? data.linked_accounts
            : (Array.isArray(data?.data) ? data.data : []);

        renderAccounts(accountsContainer, accounts, theme, setPrimary, unlink);
    };

    window.linkOAuthProvider = linkProvider;
    window.setPrimaryOAuthAccount = (_accountId, provider) => setPrimary(String(provider || '').toLowerCase());
    window.unlinkOAuthAccount = (_accountId, provider) => unlink(String(provider || '').toLowerCase());

    const notificationPreferences = initNotificationPreferences({
        showAlert,
        csrfTokenSelector: options.csrfTokenSelector,
        alertsContainerId: options.alertsContainerId
    });

    loadLinkedAccounts();
    loadProviders();

    return {
        reloadAccounts: loadLinkedAccounts,
        reloadProviders: loadProviders,
        notificationPreferences
    };
}

export function initAccountPasswordChange(options = {}) {
    const form = document.getElementById(options.formId || 'change-password-form');
    if (!form) return null;

    const showAlert = typeof options.showAlert === 'function'
        ? options.showAlert
        : (message, type = 'info') => window.showAlert(message, type);

    const currentPasswordInput = document.getElementById(options.currentPasswordId || 'current_password');
    const newPasswordInput = document.getElementById(options.newPasswordId || 'new_password');
    const confirmPasswordInput = document.getElementById(options.confirmPasswordId || 'confirm_password');

    if (!currentPasswordInput || !newPasswordInput || !confirmPasswordInput) return null;

    const csrfToken = () => getCsrfToken(options.csrfTokenSelector || '#csrf_token');

    newPasswordInput.addEventListener('input', () => {
        updatePasswordStrengthMeter(newPasswordInput.value || '', options.strengthConfig || {});
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const currentPassword = currentPasswordInput.value || '';
        const newPassword = newPasswordInput.value || '';
        const confirmPassword = confirmPasswordInput.value || '';

        if (!currentPassword || !newPassword || !confirmPassword) {
            showAlert('All password fields are required', 'warning');
            return;
        }

        if (newPassword !== confirmPassword) {
            showAlert('New passwords do not match', 'danger');
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn?.innerHTML || '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
        }

        const { data } = await fetchJson('/user/change-password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                current_password: currentPassword,
                password: newPassword,
                password_confirm: confirmPassword,
                csrf_token: csrfToken()
            })
        });

        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }

        if (data?.success) {
            form.reset();
            updatePasswordStrengthMeter('', options.strengthConfig || {});
            showAlert(data.message || 'Password updated successfully', 'success');
            if (typeof options.onSuccess === 'function') options.onSuccess(data);
            return;
        }

        showAlert(data?.error || data?.message || 'Failed to update password', 'danger');
    });

    return { form };
}

const accountSettingsAPI = {
    initAccountSettingsOAuth,
    initAccountPasswordChange,
    initNotificationPreferences
};

if (typeof window !== 'undefined') {
    window.BroxAccountSettings = accountSettingsAPI;
}

export default accountSettingsAPI;
