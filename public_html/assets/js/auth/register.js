/**
 * Register Form Handler - Production Ready
 * Handles:
 * - Local email/password registration
 * - Firebase OAuth sign-up (Google, Facebook, Anonymous)
 * - Password strength validation
 * - Password confirmation matching
 * - Password visibility toggle (shared utility)
 * - Error handling & user feedback
 */

import AuthUIHandler from '/assets/firebase/v2/dist/auth-ui-handler.js';
import { checkPasswordRequirements, getPasswordStrength, validateConfirmation, PASSWORD_REQUIREMENTS } from '../shared/form-validators.js';

(function () {
    'use strict';

    // ============ SELECTORS ============
    const SELECTORS = {
        // Form
        form: '#registerForm',

        // Personal info
        emailInput: '#email',
        usernameInput: '#username',
        firstNameInput: '#first_name',
        lastNameInput: '#last_name',

        // Password
        passwordInput: '#password',
        confirmPasswordInput: '#confirm_password',
        passwordToggles: '[data-toggle-password]',
        passwordFeedback: '#passwordFeedback',
        confirmFeedback: '#confirmFeedback',
        strengthBars: '.strength-bar',

        // Terms
        termsCheckbox: '#terms',

        // Submit
        submitBtn: '#submitBtn',

        // OAuth buttons
        googleAuthBtn: '#auth-google-btn',
        facebookAuthBtn: '#auth-facebook-btn',
        guestAuthBtn: '#auth-guest-btn',
        authButtons: '#auth-buttons',
        authCard: '.auth-card',

        // Status
        statusDisplay: '#oauth-status'
    };

    // ============ UI ELEMENTS ============
    let elements = {
        form: null,
        email: null,
        username: null,
        firstName: null,
        lastName: null,
        password: null,
        confirmPassword: null,
        passwordToggles: [],
        passwordFeedback: null,
        confirmFeedback: null,
        strengthBars: null,
        termsCheckbox: null,
        submitBtn: null,
        googleAuthBtn: null,
        facebookAuthBtn: null,
        guestAuthBtn: null,
        authButtons: null,
        authCard: null,
        statusDisplay: null
    };

    // ============ PASSWORD REQUIREMENTS ============
    // (imported from shared form validators)
    // const PASSWORD_REQUIREMENTS = ... (see shared/form-validators.js)

    // ============ CACHE DOM ============
    function cacheElements() {
        elements.form = document.querySelector(SELECTORS.form);
        elements.email = document.querySelector(SELECTORS.emailInput);
        elements.username = document.querySelector(SELECTORS.usernameInput);
        elements.firstName = document.querySelector(SELECTORS.firstNameInput);
        elements.lastName = document.querySelector(SELECTORS.lastNameInput);
        elements.password = document.querySelector(SELECTORS.passwordInput);
        elements.confirmPassword = document.querySelector(SELECTORS.confirmPasswordInput);
        elements.passwordToggles = Array.from(document.querySelectorAll(SELECTORS.passwordToggles));
        elements.passwordFeedback = document.querySelector(SELECTORS.passwordFeedback);
        elements.confirmFeedback = document.querySelector(SELECTORS.confirmFeedback);
        elements.strengthBars = document.querySelectorAll(SELECTORS.strengthBars);
        elements.termsCheckbox = document.querySelector(SELECTORS.termsCheckbox);
        elements.submitBtn = document.querySelector(SELECTORS.submitBtn);
        elements.googleAuthBtn = document.querySelector(SELECTORS.googleAuthBtn);
        elements.facebookAuthBtn = document.querySelector(SELECTORS.facebookAuthBtn);
        elements.guestAuthBtn = document.querySelector(SELECTORS.guestAuthBtn);
        elements.authButtons = document.querySelector(SELECTORS.authButtons);
        elements.authCard = document.querySelector(SELECTORS.authCard);
        elements.statusDisplay = document.querySelector(SELECTORS.statusDisplay);
    }

    // ============ STATUS DISPLAY ============
    function showStatus(message, type = 'info') {
        if (!elements.statusDisplay) return;
        elements.statusDisplay.textContent = message;
        elements.statusDisplay.className = `auth-response auth-response--${type}`;
        elements.statusDisplay.setAttribute('role', 'status');
        elements.statusDisplay.setAttribute('aria-live', 'polite');
        elements.statusDisplay.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearStatus() {
        if (!elements.statusDisplay) return;
        elements.statusDisplay.textContent = '';
        elements.statusDisplay.className = 'auth-response auth-response-hidden';
    }

    function getOAuthButtons() {
        return [
            elements.googleAuthBtn,
            elements.facebookAuthBtn,
            elements.guestAuthBtn
        ].filter(Boolean);
    }

    function setOAuthLoadingState(activeButton) {
        if (!activeButton) return;

        const buttons = getOAuthButtons();
        if (elements.authCard) {
            elements.authCard.classList.add('auth-card--oauth-loading');
        }
        if (elements.authButtons) {
            elements.authButtons.classList.add('auth-buttons--busy');
        }

        buttons.forEach((btn) => {
            if (!btn.dataset.originalHtml) {
                btn.dataset.originalHtml = btn.innerHTML;
            }

            const isActive = btn === activeButton;
            btn.disabled = true;
            btn.classList.toggle('auth-btn--loading', isActive);
            btn.setAttribute('aria-busy', isActive ? 'true' : 'false');

            if (isActive) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            }
        });
    }

    function clearOAuthLoadingState() {
        const buttons = getOAuthButtons();

        if (elements.authCard) {
            elements.authCard.classList.remove('auth-card--oauth-loading');
        }
        if (elements.authButtons) {
            elements.authButtons.classList.remove('auth-buttons--busy');
        }

        buttons.forEach((btn) => {
            btn.disabled = false;
            btn.classList.remove('auth-btn--loading');
            btn.removeAttribute('aria-busy');

            if (btn.dataset.originalHtml) {
                btn.innerHTML = btn.dataset.originalHtml;
            }
        });
    }

    // ============ PASSWORD UTILITIES ============
    // functions imported from shared form validators

    function updateStrengthBars() {
        if (!elements.password || !elements.strengthBars.length) return;

        const pwd = elements.password.value;
        const strength = getPasswordStrength(pwd);

        elements.strengthBars.forEach((bar, index) => {
            bar.classList.remove('filled', 'warning', 'danger', 'success');

            if (index < strength) {
                if (strength <= 2) {
                    bar.classList.add('danger');
                } else if (strength <= 3) {
                    bar.classList.add('warning');
                } else {
                    bar.classList.add('success', 'filled');
                }
            }
        });
    }

    function generatePasswordFeedback() {
        if (!elements.password || !elements.passwordFeedback) return;

        const pwd = elements.password.value;
        if (!pwd) {
            elements.passwordFeedback.innerHTML = '';
            return;
        }

        const requirements = checkPasswordRequirements(pwd);
        const met = Object.values(requirements).filter(Boolean).length;
        const total = Object.keys(requirements).length;

        let html = `<small class="password-strength-text">Strength: ${met}/${total}</small><ul class="password-requirements">`;

        for (const [key, req] of Object.entries(PASSWORD_REQUIREMENTS)) {
            const isMet = requirements[key];
            const className = isMet ? 'met' : 'unmet';
            const icon = isMet ? '<i class="bi bi-check-circle"></i>' : '<i class="bi bi-circle"></i>';
            html += `<li class="${className}">${icon} ${req.label}</li>`;
        }

        html += '</ul>';
        elements.passwordFeedback.innerHTML = html;

        const isValid = met === total;
        elements.password.classList.toggle('is-valid', isValid);
        elements.password.classList.toggle('is-invalid', pwd && !isValid);
    }

    // confirmation logic now provided by shared validator

    // ============ PASSWORD TOGGLE ============
    function initPasswordToggles() {
        if (!elements.passwordToggles.length) return;

        elements.passwordToggles.forEach((toggleEl) => {
            const toggle = () => {
                const targetId = toggleEl.dataset.togglePassword;
                if (!targetId) return;

                const targetInput = document.getElementById(targetId);
                if (!targetInput) return;

                const isPassword = targetInput.type === 'password';
                targetInput.type = isPassword ? 'text' : 'password';
                toggleEl.className = isPassword
                    ? 'bi bi-eye-slash password-toggle'
                    : 'bi bi-eye password-toggle';
                toggleEl.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                toggleEl.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                targetInput.focus();
            };

            toggleEl.addEventListener('click', (e) => {
                e.preventDefault();
                toggle();
            });

            toggleEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle();
                }
            });
        });
    }

    // ============ FORM VALIDATION ============
    function validateForm() {
        const pwd = elements.password?.value || '';
        const strength = getPasswordStrength(pwd);
        const total = Object.keys(PASSWORD_REQUIREMENTS).length;
        const confirmValid = validateConfirmation();
        const termsChecked = elements.termsCheckbox?.checked || false;

        if (!termsChecked) {
            showStatus('Please accept the terms and conditions', 'warning');
            return false;
        }

        if (strength !== total) {
            showStatus('Password does not meet all requirements', 'warning');
            return false;
        }

        if (!confirmValid) {
            showStatus('Passwords do not match', 'warning');
            return false;
        }

        return true;
    }

    // ============ LOCAL FORM HANDLER ============
    function initFormHandler() {
        if (!elements.form) return;

        elements.password?.addEventListener('input', function () {
            updateStrengthBars();
            generatePasswordFeedback();
            validateConfirmation();
        });

        elements.confirmPassword?.addEventListener('input', validateConfirmation);
        elements.confirmPassword?.addEventListener('blur', validateConfirmation);

        elements.form.addEventListener('submit', (e) => {
            e.preventDefault();

            if (!validateForm()) {
                return;
            }

            // Form will submit to backend (traditional POST)
            // Backend handles user creation
            elements.form.submit();
        });
    }

    // ============ OAUTH HANDLERS ============
    async function handleGoogleAuth() {
        setOAuthLoadingState(elements.googleAuthBtn);
        try {
            const redirectUrl = elements.form?.dataset.redirectUrl || '/user/dashboard';
            await AuthUIHandler.signInWithGoogle({
                redirectTo: redirectUrl
            });
        } catch (error) {
            clearOAuthLoadingState();
            console.error('Google auth error:', error);
        }
    }

    async function handleFacebookAuth() {
        setOAuthLoadingState(elements.facebookAuthBtn);
        try {
            const redirectUrl = elements.form?.dataset.redirectUrl || '/user/dashboard';
            await AuthUIHandler.signInWithFacebook({
                redirectTo: redirectUrl
            });
        } catch (error) {
            clearOAuthLoadingState();
            console.error('Facebook auth error:', error);
        }
    }

    async function handleGuestAuth() {
        setOAuthLoadingState(elements.guestAuthBtn);
        try {
            const redirectUrl = elements.form?.dataset.redirectUrl || '/user/dashboard';
            await AuthUIHandler.signInAnonymous({
                redirectTo: redirectUrl
            });
        } catch (error) {
            clearOAuthLoadingState();
            console.error('Guest auth error:', error);
        }
    }

    function initOAuthButtons() {
        if (elements.googleAuthBtn) {
            elements.googleAuthBtn.addEventListener('click', (e) => {
                e.preventDefault();
                handleGoogleAuth();
            });
            elements.googleAuthBtn.setAttribute('data-auth-provider', 'google');
        }

        if (elements.facebookAuthBtn) {
            elements.facebookAuthBtn.addEventListener('click', (e) => {
                e.preventDefault();
                handleFacebookAuth();
            });
            elements.facebookAuthBtn.setAttribute('data-auth-provider', 'facebook');
        }

        if (elements.guestAuthBtn) {
            elements.guestAuthBtn.addEventListener('click', (e) => {
                e.preventDefault();
                handleGuestAuth();
            });
            elements.guestAuthBtn.setAttribute('data-auth-provider', 'anonymous');
        }
    }

    // ============ SETUP CALLBACKS ============
    function setupAuthCallbacks() {
        AuthUIHandler.setAllCallbacks({
            onStatus: showStatus,
            onSuccess: (result) => {
                clearOAuthLoadingState();
                clearStatus();
                showStatus('Registration successful! Redirecting...', 'success');
            },
            onConflict: (conflict, provider, user) => {
                clearOAuthLoadingState();
                showStatus(`This email is already registered. Please sign in instead.`, 'warning');
            },
            onError: (error) => {
                clearOAuthLoadingState();
                console.error('Auth error:', error);
                // Status already shown by handler
            }
        });
    }

    // ============ INITIALIZATION ============
    function init() {
        cacheElements();

        if (!elements.form) {
            console.warn('Register form not found');
            return;
        }

        initPasswordToggles();
        initFormHandler();
        initOAuthButtons();
        setupAuthCallbacks();

        // Trigger initial validation if form has values (after error)
        if (elements.password?.value) {
            updateStrengthBars();
            generatePasswordFeedback();
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
