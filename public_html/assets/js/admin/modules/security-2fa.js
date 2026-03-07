import { escapeHtml } from './core.js';

const byIdDefault = (id) => document.getElementById(id);
const getCsrfTokenDefault = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

function setAlertHtml(container, type, message, strongLabel = '') {
    if (!container) return;
    const safeType = escapeHtml(type || 'info');
    const safeMessage = escapeHtml(message || '');
    const strong = strongLabel ? `<strong>${escapeHtml(strongLabel)}</strong> ` : '';
    const icon = safeType === 'success' ? 'check-circle' : 'exclamation-circle';

    container.innerHTML = `
        <div class="alert alert-${safeType} alert-dismissible fade show" role="alert">
            <i class="bi bi-${icon} me-2"></i>
            ${strong}${safeMessage}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

export function initSecurity2FASetup(options = {}) {
    const byId = options.byId || byIdDefault;
    const authCode = byId('authCode');
    if (!authCode) return;

    function verifyCode() {
        const code = authCode.value.trim();
        const alertBox = byId('verifyAlert');

        if (!code || code.length !== 6) {
            setAlertHtml(alertBox, 'danger', 'Please enter a 6-digit code');
            return;
        }

        const form = byId('verifyTwoFAForm');
        if (!form) return;
        const formData = new FormData(form);
        formData.set('code', code);

        fetch('/admin/security/2fa/verify', {
            method: 'POST',
            body: formData
        })
            .then((response) => {
                if (response.ok) {
                    setAlertHtml(alertBox, 'success', 'Two-Factor Authentication enabled successfully!');
                    setTimeout(() => {
                        window.location.href = '/admin/security/2fa';
                    }, 2000);
                    return;
                }
                setAlertHtml(alertBox, 'danger', 'Invalid code. Please try again.');
                authCode.value = '';
                authCode.focus();
            })
            .catch(() => {
                setAlertHtml(alertBox, 'danger', 'An error occurred. Please try again.');
            });
    }

    window.copySecret = function (buttonEl) {
        const secretKey = byId('secretKey');
        if (!secretKey) return;
        secretKey.select();
        document.execCommand('copy');
        const btn = buttonEl || document.querySelector('button[onclick*="copySecret"]');
        if (!btn) return;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check me-1"></i> Copied!';
        setTimeout(() => {
            btn.innerHTML = originalText;
        }, 2000);
    };

    window.goBack = function () {
        window.location.href = '/admin/security/2fa';
    };

    authCode.addEventListener('input', (event) => {
        event.target.value = event.target.value.replace(/[^0-9]/g, '');
    });

    byId('verifyBtn')?.addEventListener('click', verifyCode);
}

export function initSecurity2FABackup(options = {}) {
    const byId = options.byId || byIdDefault;
    const getCsrfToken = options.getCsrfToken || getCsrfTokenDefault;

    if (!document.querySelector('.code-box')) return;

    window.copyAllBackupCodes = function (buttonEl) {
        const codeElements = document.querySelectorAll('.code-box code');
        let allCodes = '';
        codeElements.forEach((code) => {
            allCodes += code.textContent.trim() + '\n';
        });

        navigator.clipboard.writeText(allCodes).then(() => {
            const btn = buttonEl || document.querySelector('button[onclick*="copyAllBackupCodes"]');
            if (!btn) return;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
            btn.classList.add('disabled');

            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('disabled');
            }, 2000);
        }).catch(() => {
            alert('Failed to copy codes. Please try again.');
        });
    };

    window.regenerateBackupCodes = function (buttonEl) {
        const password = byId('password')?.value.trim();
        if (!password) {
            alert('Please enter your password');
            return;
        }

        const btn = buttonEl || document.querySelector('button[onclick*="regenerateBackupCodes"]');
        if (!btn) return;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';

        fetch('/admin/security/2fa/backup-codes/regenerate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                password: password,
                csrf_token: getCsrfToken()
            })
        })
            .then((response) => response.json())
            .then((data) => {
                if (data?.success) {
                    alert('New backup codes generated! This page will refresh to display them.');
                    location.reload();
                    return;
                }
                alert('Error: ' + (data?.error || 'Failed to generate codes'));
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Generate New Codes';
            })
            .catch(() => {
                alert('An error occurred. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Generate New Codes';
            });
    };

    document.querySelectorAll('.code-box').forEach((box) => {
        box.addEventListener('click', () => {
            const codeEl = box.querySelector('code');
            if (!codeEl) return;
            navigator.clipboard.writeText(codeEl.textContent.trim()).then(() => {
                const originalBg = box.style.backgroundColor;
                box.style.backgroundColor = '#d4edda';
                setTimeout(() => {
                    box.style.backgroundColor = originalBg;
                }, 500);
            });
        });
    });
}

export function initSecurity2FA(options = {}) {
    const byId = options.byId || byIdDefault;
    const getCsrfToken = options.getCsrfToken || getCsrfTokenDefault;
    const csrfToken = () => getCsrfToken();

    function showAlert(message, type = 'success') {
        const container = byId('alert-container');
        const strong = type === 'success' ? 'Success!' : 'Error!';
        setAlertHtml(container, type, message, strong);
    }

    function showAlertInModal(container, message, type) {
        setAlertHtml(container, type, message);
    }

    function disableTwoFA(event) {
        const password = byId('disablePassword')?.value || '';
        const alertBox = byId('disableAlert');

        if (!password) {
            showAlertInModal(alertBox, 'Please enter your password', 'danger');
            return;
        }

        const button = event?.currentTarget || byId('confirmDisableTwoFABtn');
        if (!button) return;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Disabling...';

        fetch('/admin/security/2fa/disable', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ password, csrf_token: csrfToken() })
        })
            .then((response) => response.json())
            .then((data) => {
                if (data?.success) {
                    showAlert('2FA has been disabled successfully');
                    setTimeout(() => location.reload(), 1500);
                    return;
                }
                showAlertInModal(alertBox, data?.error || 'Failed to disable 2FA', 'danger');
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-trash me-1"></i> Disable 2FA';
            })
            .catch(() => {
                showAlertInModal(alertBox, 'An error occurred. Please try again.', 'danger');
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-trash me-1"></i> Disable 2FA';
            });
    }

    byId('confirmDisableTwoFABtn')?.addEventListener('click', (event) => disableTwoFA(event));
}
