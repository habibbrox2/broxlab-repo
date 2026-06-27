/**
 * Security module functions (ES Module)
 * Replaces previous IIFE + window.Brox pattern.
 */
import { adminGetCsrfToken } from './utils.js';

/**
 * Validate password strength and update visual indicator
 * @param {string} inputId - ID of the password input element
 */
export function validatePasswordStrength(inputId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const val = input.value;
  const meter = input.parentElement?.querySelector('.password-strength-meter');
  if (!meter) return;

  let score = 0;
  if (val.length >= 8) score++;
  if (val.length >= 12) score++;
  if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;
  if (/\d/.test(val)) score++;
  if (/[^a-zA-Z0-9]/.test(val)) score++;

  const labels = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong',];
  const colors = ['danger', 'warning', 'info', 'primary', 'success',];

  meter.className = 'password-strength-meter block text-xs mt-1';
  meter.textContent = labels[score] || '';
  const colorMap = { danger: '#dc2626', warning: '#d97706', info: '#0284c7', primary: '#4f46e5', success: '#16a34a', };
  meter.style.color = colorMap[colors[score]] || '#64748b';
}

/**
 * Show an alert message inside a container element
 * @param {HTMLElement} container - Container to insert alert into
 * @param {string} message - Alert message text
 * @param {string} [type='info'] - Alert type (success, danger, warning, info)
 */
export function showAlert(container, message, type = 'info') {
  if (!container) return;
  const iconMap = { success: 'lucide-check-circle', danger: 'lucide-alert-triangle', warning: 'lucide-alert-circle', info: 'lucide-info', };
  const alertColors = {
    success: 'bg-emerald-50 border border-emerald-200 text-emerald-800',
    danger: 'bg-red-50 border border-red-200 text-red-800',
    info: 'bg-sky-50 border border-sky-200 text-sky-800',
    warning: 'bg-amber-50 border border-amber-200 text-amber-800',
  };
  const alertClass = alertColors[type] || alertColors.info;
  container.innerHTML = `
    <div class="${alertClass} rounded-xl p-4 flex items-start gap-3 mb-3" role="alert">
      <i class="lucide ${iconMap[type] || iconMap.info} mt-0.5 shrink-0"></i>
      <div class="flex-1 text-sm">${message}</div>
      <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors shrink-0 ml-auto" data-brox-dismiss="alert" aria-label="Close">
        <i class="lucide lucide-x"></i>
      </button>
    </div>`;
}

/**
 * Set a new password for the current user
 */
export async function setPassword() {
  const form = document.getElementById('setPasswordForm');
  if (!form) return;
  const alertBox = form.querySelector('.password-alert');
  const password = form.querySelector('#new_password')?.value || '';
  const confirm = form.querySelector('#confirm_password')?.value || '';

  if (!password || !confirm) {
    showAlert(alertBox, 'All fields are required', 'danger');
    return;
  }
  if (password !== confirm) {
    showAlert(alertBox, 'Passwords do not match', 'danger');
    return;
  }
  if (password.length < 8) {
    showAlert(alertBox, 'Password must be at least 8 characters', 'danger');
    return;
  }

  try {
    const csrfToken = form.csrf_token?.value || adminGetCsrfToken();
    const res = await fetch('/user/security/set-password', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ password, password_confirm: confirm, }),
    });
    const data = await res.json();
    if (data.success) {
      showAlert(alertBox, data.message, 'success');
      form.reset();
    } else {
      showAlert(alertBox, data.error || 'Failed to set password', 'danger');
    }
  } catch (err) {
    showAlert(alertBox, 'An error occurred. Please try again.', 'danger');
  }
}

/**
 * Change password for the current user
 */
export async function changePassword() {
  const form = document.getElementById('changePasswordForm');
  if (!form) return;
  const alertBox = form.querySelector('.password-alert');
  const current = form.querySelector('#current_password')?.value || '';
  const password = form.querySelector('#new_password')?.value || '';
  const confirm = form.querySelector('#confirm_password')?.value || '';

  if (!current || !password || !confirm) {
    showAlert(alertBox, 'All fields are required', 'danger');
    return;
  }
  if (password !== confirm) {
    showAlert(alertBox, 'Passwords do not match', 'danger');
    return;
  }
  if (password.length < 8) {
    showAlert(alertBox, 'Password must be at least 8 characters', 'danger');
    return;
  }

  try {
    const csrfToken = form.csrf_token?.value || adminGetCsrfToken();
    const res = await fetch('/user/security/change-password', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ current_password: current, password, password_confirm: confirm, }),
    });
    const data = await res.json();
    if (data.success) {
      showAlert(alertBox, data.message, 'success');
      form.reset();
    } else {
      showAlert(alertBox, data.error || 'Failed to change password', 'danger');
    }
  } catch (err) {
    showAlert(alertBox, 'An error occurred. Please try again.', 'danger');
  }
}

/**
 * Initialize password-related modals (set + change)
 */
export function initPasswordModals() {
  // Set password modal
  const setPwBtn = document.getElementById('setPasswordBtn');
  if (setPwBtn) {
    setPwBtn.addEventListener('click', setPassword);
  }

  // Change password modal
  const changePwBtn = document.getElementById('changePasswordBtn');
  if (changePwBtn) {
    changePwBtn.addEventListener('click', changePassword);
  }

  // Password strength validation
  const pwFields = document.querySelectorAll('#new_password, #changePasswordForm #new_password');
  pwFields.forEach((field) => {
    field.addEventListener('input', () => validatePasswordStrength(field.id));
  });
}
