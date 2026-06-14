/**
 * Security Module
 * Handles password validation, 2FA, and security settings
 */

import { adminGetCsrfToken } from './utils.js';

const SPECIAL_CHAR_PATTERN = new RegExp("[!@#$%^&*()_+\\-=\\[\\]{};:'\",.<>?/\\\\]");

export function validatePasswordStrength(inputId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const password = input.value || '';
  const isChangeForm = inputId.includes('change');
  const prefix = isChangeForm ? 'changePwd' : 'pwd';

  const lengthCheck = document.getElementById(`${prefix }Length`);
  const upperCheck = document.getElementById(`${prefix }Upper`);
  const lowerCheck = document.getElementById(`${prefix }Lower`);
  const numberCheck = document.getElementById(`${prefix }Number`);
  const specialCheck = document.getElementById(`${prefix }Special`);

  if (lengthCheck) lengthCheck.classList.toggle('valid', password.length >= 8);
  if (upperCheck) upperCheck.classList.toggle('valid', /[A-Z]/.test(password));
  if (lowerCheck) lowerCheck.classList.toggle('valid', /[a-z]/.test(password));
  if (numberCheck) numberCheck.classList.toggle('valid', /[0-9]/.test(password));
  if (specialCheck) specialCheck.classList.toggle('valid', SPECIAL_CHAR_PATTERN.test(password));
}

export async function setPassword() {
  const form = document.getElementById('setPasswordForm');
  if (!form) return;
  const password = form.password?.value || '';
  const confirmPassword = form.password_confirm?.value || '';
  const csrfToken = form.csrf_token?.value || adminGetCsrfToken();
  const alertBox = document.getElementById('setPasswordAlert');

  if (!password || !confirmPassword) {
    showAlert(alertBox, 'All fields are required', 'danger');
    return;
  }
  if (password !== confirmPassword) {
    showAlert(alertBox, 'Passwords do not match', 'danger');
    return;
  }
  if (password.length < 8) {
    showAlert(alertBox, 'Password must be at least 8 characters', 'danger');
    return;
  }

  try {
    const response = await fetch('/api/oauth/set-password', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: new URLSearchParams({
        password: password,
        password_confirm: confirmPassword,
        csrf_token: csrfToken,
      }),
    });
    const data = await response.json();
    if (data.success) {
      showAlert(alertBox, data.message, 'success');
      setTimeout(() => {
        form.reset();
        bootstrap.Modal.getInstance(document.getElementById('setPasswordModal'))?.hide();
        location.reload();
      }, 1500);
    } else {
      showAlert(alertBox, data.error || 'Failed to set password', 'danger');
    }
  } catch (error) {
    console.error('Error:', error);
    showAlert(alertBox, 'An error occurred. Please try again.', 'danger');
  }
}

export async function changePassword() {
  const form = document.getElementById('changePasswordForm');
  if (!form) return;
  const currentPassword = form.current_password?.value || '';
  const newPassword = form.password?.value || '';
  const confirmPassword = form.password_confirm?.value || '';
  const csrfToken = form.csrf_token?.value || adminGetCsrfToken();
  const alertBox = document.getElementById('changePasswordAlert');

  if (!currentPassword || !newPassword || !confirmPassword) {
    showAlert(alertBox, 'All fields are required', 'danger');
    return;
  }
  if (newPassword !== confirmPassword) {
    showAlert(alertBox, 'Passwords do not match', 'danger');
    return;
  }

  try {
    const response = await fetch('/user/change-password', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: new URLSearchParams({
        current_password: currentPassword,
        password: newPassword,
        password_confirm: confirmPassword,
        csrf_token: csrfToken,
      }),
    });
    const data = await response.json();
    if (data.success) {
      showAlert(alertBox, data.message, 'success');
      setTimeout(() => {
        form.reset();
        bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'))?.hide();
        location.reload();
      }, 1500);
    } else {
      showAlert(alertBox, data.error || 'Failed to change password', 'danger');
    }
  } catch (error) {
    console.error('Error:', error);
    showAlert(alertBox, 'An error occurred. Please try again.', 'danger');
  }
}

function showAlert(alertBox, message, type = 'danger') {
  if (!alertBox) return;
  alertBox.className = `alert alert-${type} alert-dismissible show`;
  alertBox.textContent = message;
}

export function initPasswordModals() {
  const setPasswordForm = document.getElementById('setPasswordForm');
  const changePasswordForm = document.getElementById('changePasswordForm');
  if (!setPasswordForm && !changePasswordForm) return;

  const newPasswordInput = document.getElementById('newPassword');
  const changePasswordInput = document.getElementById('changeNewPassword');
  if (newPasswordInput) newPasswordInput.addEventListener('input', () => validatePasswordStrength('newPassword'));
  if (changePasswordInput) changePasswordInput.addEventListener('input', () => validatePasswordStrength('changeNewPassword'));

  document.getElementById('setPasswordBtn')?.addEventListener('click', setPassword);
  document.getElementById('changePasswordBtn')?.addEventListener('click', changePassword);
}

// Placeholder for 2FA functions - would need to extract from the full code
export function initSecurity2FA() {
  // Implementation would go here
}

export function initSecurity2FASetup() {
  // Implementation would go here
}

export function initSecurity2FABackup() {
  // Implementation would go here
}

export function initAppSecuritySettings() {
  // Implementation would go here
}
