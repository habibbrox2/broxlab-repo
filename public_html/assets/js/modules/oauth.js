/**
 * OAuth Admin Module
 * Lazy-loaded via loadAdminModule("oauth").
 * Handles OAuth password set/change modals with validation.
 */

export function initOAuthPasswordModals({ byId, getCsrfToken, }) {
  const setPasswordForm = byId('setPasswordForm');
  const changePasswordForm = byId('changePasswordForm');
  if (!setPasswordForm && !changePasswordForm) return;

  const specialCharPattern = new RegExp("[!@#$%^&*()_+\\-=\\[\\]{};:'\",.<>?/\\\\]");

  function validatePasswordStrength(inputId) {
    const input = byId(inputId);
    if (!input) return;
    const password = input.value || '';
    const isChangeForm = inputId.includes('change');
    const prefix = isChangeForm ? 'changePwd' : 'pwd';

    const lengthCheck = byId(`${prefix }Length`);
    const upperCheck = byId(`${prefix }Upper`);
    const lowerCheck = byId(`${prefix }Lower`);
    const numberCheck = byId(`${prefix }Number`);
    const specialCheck = byId(`${prefix }Special`);

    if (lengthCheck) lengthCheck.classList.toggle('valid', password.length >= 8);
    if (upperCheck) upperCheck.classList.toggle('valid', /[A-Z]/.test(password));
    if (lowerCheck) lowerCheck.classList.toggle('valid', /[a-z]/.test(password));
    if (numberCheck) numberCheck.classList.toggle('valid', /[0-9]/.test(password));
    if (specialCheck)
      specialCheck.classList.toggle(
        'valid',
        specialCharPattern.test(password)
      );
  }

  const newPasswordInput = byId('newPassword');
  const changePasswordInput = byId('changeNewPassword');
  if (newPasswordInput)
    newPasswordInput.addEventListener('input', () => validatePasswordStrength('newPassword'));
  if (changePasswordInput)
    changePasswordInput.addEventListener('input', () =>
      validatePasswordStrength('changeNewPassword')
    );

  function showAlert(alertBox, message, type) {
    if (!alertBox) return;
    alertBox.className = `p-4 rounded-xl border ${ type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : type === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-700' : type === 'info' ? 'bg-sky-50 border-sky-200 text-sky-700' : 'bg-red-50 border-red-200 text-red-700'}`;
    alertBox.textContent = message;
  }

  function setPassword() {
    const form = byId('setPasswordForm');
    if (!form) return;
    const password = form.password?.value || '';
    const confirmPassword = form.password_confirm?.value || '';
    const csrfToken = form.csrf_token?.value || getCsrfToken();
    const alertBox = byId('setPasswordAlert');

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

    fetch('/api/oauth/set-password', {
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
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showAlert(alertBox, data.message, 'success');
          setTimeout(() => {
            form.reset();
            broxUI.Modal.getInstance(byId('setPasswordModal'))?.hide();
            location.reload();
          }, 1500);
        } else {
          showAlert(alertBox, data.error || 'Failed to set password', 'danger');
        }
      })
      .catch((error) => {
        console.error('Error:', error);
        showAlert(alertBox, 'An error occurred. Please try again.', 'danger');
      });
  }

  function changePassword() {
    const form = byId('changePasswordForm');
    if (!form) return;
    const currentPassword = form.current_password?.value || '';
    const newPassword = form.password?.value || '';
    const confirmPassword = form.password_confirm?.value || '';
    const csrfToken = form.csrf_token?.value || getCsrfToken();
    const alertBox = byId('changePasswordAlert');

    if (!currentPassword || !newPassword || !confirmPassword) {
      showAlert(alertBox, 'All fields are required', 'danger');
      return;
    }
    if (newPassword !== confirmPassword) {
      showAlert(alertBox, 'Passwords do not match', 'danger');
      return;
    }

    fetch('/user/change-password', {
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
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showAlert(alertBox, data.message, 'success');
          setTimeout(() => {
            form.reset();
            broxUI.Modal.getInstance(byId('changePasswordModal'))?.hide();
            location.reload();
          }, 1500);
        } else {
          showAlert(alertBox, data.error || 'Failed to change password', 'danger');
        }
      })
      .catch((error) => {
        console.error('Error:', error);
        showAlert(alertBox, 'An error occurred. Please try again.', 'danger');
      });
  }

  byId('setPasswordBtn')?.addEventListener('click', setPassword);
  byId('changePasswordBtn')?.addEventListener('click', changePassword);

  return {
    validatePasswordStrength,
    setPassword,
    changePassword,
  };
}
