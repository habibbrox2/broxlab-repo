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

  meter.className = 'password-strength-meter d-block mt-1 small';
  meter.textContent = labels[score] || '';
  meter.style.color = `var(--bs-${colors[score] || 'secondary'})`;
}

/**
 * Show an alert message inside a container element
 * @param {HTMLElement} container - Container to insert alert into
 * @param {string} message - Alert message text
 * @param {string} [type='info'] - Alert type (success, danger, warning, info)
 */
export function showAlert(container, message, type = 'info') {
  if (!container) return;
  const iconMap = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-triangle-fill', warning: 'bi-exclamation-circle-fill', info: 'bi-info-circle-fill', };
  container.innerHTML = `
    <div class="alert alert-${type} alert-dismissible fade show d-flex align-items-center gap-2 py-2 px-3 mb-3" role="alert">
      <i class="bi ${iconMap[type] || iconMap.info} fs-5"></i>
      <span>${message}</span>
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
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
