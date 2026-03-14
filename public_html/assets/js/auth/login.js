/**
 * Login Form Handler - Production Ready
 * Handles:
 * - Local email/password authentication via traditional form
 * - Firebase OAuth sign-in (Google, Facebook, Anonymous)
 * - Password visibility toggle
 * - Error handling & user feedback
 * - Account conflict resolution
 */

import AuthUIHandler from '/assets/firebase/v2/dist/auth-ui-handler.js';

(function () {
    'use strict';

    // ============ SELECTORS ============
    const SELECTORS = {
        // Form elements
        form: '#loginForm',
        usernameInput: '#username',
        passwordInput: '#password',
        passwordToggle: '#passwordToggle',
        rememberMe: '#remember_me',

        // OAuth buttons
        googleAuthBtn: '#auth-google-btn',
        facebookAuthBtn: '#auth-facebook-btn',
        guestAuthBtn: '#auth-guest-btn',
        authButtons: '#auth-buttons',
        authCard: '.auth-card',

        // Status display
        statusDisplay: '#oauth-status',

        // Container
        loginContainer: '.login-container'
    };

    // ============ UI STATE ============
    let elements = {
        form: null,
        usernameInput: null,
        passwordInput: null,
        passwordToggle: null,
        rememberMe: null,
        googleAuthBtn: null,
        facebookAuthBtn: null,
        guestAuthBtn: null,
        authButtons: null,
        authCard: null,
        statusDisplay: null
    };

    // ============ CACHE DOM ============
    function cacheElements() {
        elements.form = document.querySelector(SELECTORS.form);
        elements.usernameInput = document.querySelector(SELECTORS.usernameInput);
        elements.passwordInput = document.querySelector(SELECTORS.passwordInput);
        elements.passwordToggle = document.querySelector(SELECTORS.passwordToggle);
        elements.rememberMe = document.querySelector(SELECTORS.rememberMe);
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

    function showInitialStatusFromServer() {
        if (!elements.statusDisplay) return;

        const message = (elements.statusDisplay.dataset.initialMessage || '').trim();
        if (!message) return;

        const rawType = (elements.statusDisplay.dataset.initialType || 'danger').trim().toLowerCase();
        const safeType = ['success', 'danger', 'warning', 'info'].includes(rawType) ? rawType : 'danger';
        showStatus(message, safeType);
    }

    // ============ PASSWORD TOGGLE ============
    function initPasswordToggle() {
        if (!elements.passwordToggle || !elements.passwordInput) return;

        const toggle = () => {
            const isPassword = elements.passwordInput.type === 'password';
            elements.passwordInput.type = isPassword ? 'text' : 'password';
            elements.passwordToggle.className = isPassword
                ? 'bi bi-eye-slash password-toggle'
                : 'bi bi-eye password-toggle';
            elements.passwordToggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            elements.passwordToggle.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            elements.passwordInput.focus();
        };

        elements.passwordToggle.addEventListener('click', (e) => {
            e.preventDefault();
            toggle();
        });

        elements.passwordToggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle();
            }
        });
    }

    // ============ LOCAL AUTH (EMAIL/PASSWORD) ============
    function initFormHandler() {
        if (!elements.form) return;

        elements.form.addEventListener('submit', function (e) {
            e.preventDefault();

            const username = elements.usernameInput?.value.trim();
            const password = elements.passwordInput?.value;

            if (!username || !password) {
                showStatus('Please enter both email/username and password', 'warning');
                return;
            }

            // Form will submit to backend (traditional POST)
            // Backend handles email/password verification
            this.submit();
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
                showStatus('Login successful! Redirecting...', 'success');
                // fallback redirect in case handler doesn't perform it
                const redirectUrl = elements.form?.dataset.redirectUrl || '/user/dashboard';
                if (redirectUrl) {
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 500);
                }
            },
            onConflict: (conflict, provider, user) => {
                clearOAuthLoadingState();
                showStatus(`This email is linked to another account. Please use that provider to sign in.`, 'warning');
                // Could open modal to resolve conflict
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
            console.warn('Login form not found');
            return;
        }

        initPasswordToggle();
        initFormHandler();
        initOAuthButtons();
        setupAuthCallbacks();
        showInitialStatusFromServer();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
